#syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1.12
ARG PHP_VERSION=8.5
ARG NODE_VERSION=24
ARG DEBIAN_VERSION=trixie

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_VERSION} AS base

LABEL org.opencontainers.image.source=https://github.com/CVVFCM/website
LABEL org.opencontainers.image.licenses=GPL-3.0-or-later
LABEL org.opencontainers.image.authors="Yohan Giarelli <yohan@cvvfcm.fr>"
LABEL org.opencontainers.image.description="This is the website for the CVVFCM sailing club"

SHELL ["/bin/bash", "-o", "pipefail", "-eux", "-c"]

ARG EXTERNAL_USER_ID

# persistent / runtime deps
# hadolint ignore=DL3008
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip ca-certificates sqlite3; \
    php -v; \
    install-php-extensions apcu imagick intl opcache pcntl pdo_pgsql zip; \
    mkdir -p /app; \
    sync

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN sed -i -r s/"(www-data:x:)([[:digit:]]+):([[:digit:]]+):"/""\\1${EXTERNAL_USER_ID}:${EXTERNAL_USER_ID}:/g /etc/passwd; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:/g /etc/group; \
    mkdir -p /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml; \
    chown -R www-data:www-data /app /var/www "$PHP_INI_DIR" /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml

VOLUME /config
VOLUME /data
VOLUME /app/data/weather/ml
VOLUME /app/public/uploads
VOLUME /app/var/indexes

COPY --chown=www-data:www-data infra/docker/php/Caddyfile /etc/frankenphp/Caddyfile
COPY --chown=www-data:www-data infra/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

ARG APP_ENV=prod
ARG APP_DEBUG=false

USER www-data
WORKDIR /app

RUN ln -s "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --chown=www-data:www-data infra/docker/php/conf.d/symfony.prod.ini $PHP_INI_DIR/conf.d/symfony.ini

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./

RUN --mount=type=cache,target=/var/www/.cache/composer \
    composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer clear-cache; \
    rm -f \
        vendor/nitotm/efficient-language-detector/resources/ngrams/large.php \
        vendor/nitotm/efficient-language-detector/resources/ngrams/extralarge.php

FROM node:${NODE_VERSION}-${DEBIAN_VERSION} AS node

# Provided by BuildKit per target platform; used to isolate the npm cache per arch.
ARG TARGETARCH

COPY --from=base /app /app
COPY --chown=www-data:www-data assets /app/assets/

WORKDIR /app/assets/admin

# No node_modules cache mount: with multi-arch builds it is shared across the amd64/arm64
# stages and races, and with install-links=true (.npmrc) npm then skips re-materializing the
# Sulu `file:` deps → "Can't resolve 'sulu-admin-bundle/*'". Install fresh each build (like the
# local `make` build); keep only the per-arch npm download cache for speed.
RUN --mount=type=cache,id=npm-cache-${TARGETARCH},target=/root/.npm \
    npm install && \
    npm run build

FROM base AS php

COPY --chown=www-data:www-data .env ./
COPY --chown=www-data:www-data assets/website assets/website/
COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
COPY --chown=www-data:www-data migrations migrations/
COPY --chown=www-data:www-data public public/
COPY --chown=www-data:www-data src src/
COPY --chown=www-data:www-data templates templates/
COPY --chown=www-data:www-data translations translations/

COPY --chown=www-data:www-data importmap.php ./

COPY --from=node --chown=www-data:www-data /app/public/build public/build

RUN --mount=type=cache,target=/var/www/.cache/composer \
    mkdir -p var/cache var/log; \
    composer dump-autoload --optimize --no-dev --classmap-authoritative; \
    chmod +x bin/console; \
    php bin/console cache:clear; \
    php bin/console cache:warmup -eprod; \
    php bin/console importmap:install; \
    php bin/console asset-map:compile; \
    sync

COPY --from=ghcr.io/alexandre-daubois/ember:latest /ember /usr/local/bin/ember

HEALTHCHECK CMD curl -f http://localhost:2019/metrics || exit 1

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

EXPOSE 80
EXPOSE 443
EXPOSE 443/udp
EXPOSE 2019


FROM base AS consumer

COPY --chown=www-data:www-data .env ./
COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
COPY --chown=www-data:www-data src src/
COPY --chown=www-data:www-data translations translations/

RUN --mount=type=cache,target=/var/www/.cache/composer \
    mkdir -p var/cache var/log; \
    composer dump-autoload --optimize --no-dev --classmap-authoritative; \
    chmod +x bin/console; \
    php bin/console cache:clear; \
    php bin/console cache:warmup -eprod; \
    sync

HEALTHCHECK CMD echo "OK"

CMD [ "php", "bin/console", "messenger:consume", "--time-limit=3600", "--failure-limit=10", "-vv" ]
