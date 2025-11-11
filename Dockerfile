#syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1.9
ARG PHP_VERSION=8.4
ARG NODE_VERSION=22
ARG DEBIAN_VERSION=trixie

FROM dunglas/frankenphp:${FRANKENPHP_VERSION}-php${PHP_VERSION}-${DEBIAN_VERSION} AS base

LABEL org.opencontainers.image.source=https://github.com/CVVFCM/website
LABEL org.opencontainers.image.licenses=GPL-3.0-or-later
LABEL org.opencontainers.image.authors="Yohan Giarelli <yohan@cvvfcm.fr>"
LABEL org.opencontainers.image.description="This is the website for the CVVFCM sailing club"

ARG EXTERNAL_USER_ID

# persistent / runtime deps
# hadolint ignore=DL3008
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip ca-certificates sqlite3; \
    php -v; \
    install-php-extensions apcu imagick intl opcache pcntl pdo_pgsql zip; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*; \
    mkdir -p /app; \
    sync

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:${EXTERNAL_USER_ID}:/g /etc/passwd; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:/g /etc/group; \
    mkdir -p /var/run/php /data /config /app/var/indexes /app/public/uploads; \
    chown -R www-data:www-data /app /var/www /usr/local/etc/php /var/run/php /data /config /app/var/indexes /app/public/uploads

VOLUME /config
VOLUME /data
VOLUME /app/var/indexes
VOLUME /app/public/uploads

COPY --chown=www-data:www-data infra/docker/php/Caddyfile /etc/caddy/Caddyfile
COPY --chown=www-data:www-data infra/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

ARG STAGE=dev

RUN ln -s "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY infra/docker/php/conf.d/symfony.prod.ini $PHP_INI_DIR/conf.d/symfony.ini

ARG APP_ENV=prod
ARG APP_DEBUG=false

USER www-data
WORKDIR /app

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./
RUN set -eux; \
    composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer clear-cache

COPY --chown=www-data:www-data assets assets/

FROM node:${NODE_VERSION}-${DEBIAN_VERSION} AS node

COPY --from=base /app /app
WORKDIR /app/assets/admin

RUN set -eux; \
    npm install; \
    npm run build

FROM base AS php

COPY --chown=www-data:www-data .env ./
COPY --chown=www-data:www-data bin bin/
COPY --chown=www-data:www-data config config/
COPY --chown=www-data:www-data migrations migrations/
COPY --chown=www-data:www-data public public/
COPY --chown=www-data:www-data src src/
COPY --chown=www-data:www-data templates templates/
COPY --chown=www-data:www-data translations translations/

COPY --from=node --chown=www-data:www-data /app/public/build public/build

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer install --prefer-dist --no-dev --no-progress; \
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
