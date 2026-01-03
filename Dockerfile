#syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1.11
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

COPY --link --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN usermod -u ${EXTERNAL_USER_ID} www-data; \
    groupmod -g ${EXTERNAL_USER_ID} www-data; \
    mkdir -p /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml; \
    chown -R www-data:www-data /app /var/www /usr/local/etc/php /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml

VOLUME /config
VOLUME /data
VOLUME /app/data/weather/ml
VOLUME /app/public/uploads
VOLUME /app/var/indexes

COPY --chown=www-data:www-data infra/docker/php/Caddyfile /etc/caddy/Caddyfile
COPY --chown=www-data:www-data infra/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

ARG STAGE=dev

RUN ln -s "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --link infra/docker/php/conf.d/symfony.prod.ini $PHP_INI_DIR/conf.d/symfony.ini

ARG APP_ENV=prod
ARG APP_DEBUG=false

USER www-data
WORKDIR /app

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./

RUN --mount=type=cache,target=/var/www/.cache/composer \
    composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer clear-cache; \
    rm -f \
        vendor/nitotm/efficient-language-detector/resources/ngrams/large.php \
        vendor/nitotm/efficient-language-detector/resources/ngrams/extralarge.php

FROM node:${NODE_VERSION}-${DEBIAN_VERSION} AS node

COPY --from=base /app /app
COPY --chown=www-data:www-data assets /app/assets/

WORKDIR /app/assets/admin

RUN --mount=type=cache,target=/app/assets/admin/node_modules \
    --mount=type=cache,target=/root/.npm \
    npm install; \
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

HEALTHCHECK CMD curl -f http://localhost:2019/metrics || exit 1

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

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

CMD [ "php", "bin/console", "messenger:consume", "-vv" ]
