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
# hadolint ignore=SC2016,DL3008
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip ca-certificates sqlite3; \
    php -v; \
    install-php-extensions apcu imagick intl opcache pcntl pdo_pgsql zip; \
    php -r 'foreach (["apcu", "imagick", "intl", "pcntl", "pdo_pgsql", "zip", "Zend OPcache"] as $ext) { extension_loaded($ext) || throw new RuntimeException("Extension not loaded: ".$ext); } new Imagick();'; \
    mkdir -p /app; \
    sync

# ImageMagick's OpenMP threads conflict with FrankenPHP's worker threads
# (https://frankenphp.dev/docs/known-issues/) — cap ImageMagick to one thread.
ENV MAGICK_THREAD_LIMIT=1

# libgomp (pulled in by imagick.so) requires static TLS. When a FrankenPHP thread
# reboot re-dlopens imagick.so with dozens of threads alive, the static TLS surplus
# can be exhausted ("cannot allocate memory in static TLS block") and the thread is
# left without imagick until the process restarts. Preloading libgomp reserves its
# TLS up front, before any thread exists. It must go through /etc/ld.so.preload:
# the frankenphp binary carries cap_net_bind_service, so the loader runs in
# secure-execution mode and ignores the LD_PRELOAD environment variable.
RUN ldconfig -p | awk '$1 == "libgomp.so.1" { print $NF; exit }' > /etc/ld.so.preload; \
    test -s /etc/ld.so.preload

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN sed -i -r s/"(www-data:x:)([[:digit:]]+):([[:digit:]]+):"/""\\1${EXTERNAL_USER_ID}:${EXTERNAL_USER_ID}:/g /etc/passwd; \
    sed -i -r s/"(www-data:x:)([[:digit:]]+):"/\\1${EXTERNAL_USER_ID}:/g /etc/group; \
    mkdir -p /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml; \
    chown -R www-data:www-data /app /var/www "$PHP_INI_DIR" /var/run/php /data /config /app/var/indexes /app/public/uploads /app/data/weather/ml

VOLUME /config
VOLUME /data
VOLUME /app/public/uploads
VOLUME /app/var/indexes

COPY --chown=www-data:www-data .infra/docker/php/Caddyfile /etc/frankenphp/Caddyfile
COPY --chown=www-data:www-data .infra/docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

ARG APP_ENV=prod
ARG APP_DEBUG=false

USER ${EXTERNAL_USER_ID}
WORKDIR /app

RUN ln -s "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --chown=www-data:www-data .infra/docker/php/conf.d/symfony.prod.ini $PHP_INI_DIR/conf.d/symfony.ini

COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./

RUN --mount=type=cache,target=/var/www/.cache/composer \
    composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer clear-cache; \
    rm -f \
        vendor/nitotm/efficient-language-detector/resources/ngrams/large.php \
        vendor/nitotm/efficient-language-detector/resources/ngrams/extralarge.php

# Pinned to $BUILDPLATFORM: the admin bundle is static, arch-independent output, so build it once on
# the builder's native arch and reuse it for every target — no arm64 QEMU emulation of npm/webpack.
FROM --platform=$BUILDPLATFORM node:${NODE_VERSION}-${DEBIAN_VERSION} AS node

# vendor/ is required here: package.json has file:../../vendor/sulu/... dependencies and the
# webpack config comes from vendor/sulu/sulu.
COPY --from=base /app /app

# Deps first: an admin-source-only change re-runs webpack but not npm install. The .npmrc is
# required (legacy-peer-deps, install-links — the Sulu skeleton setup is incompatible with a
# plain npm ci/install).
COPY --chown=www-data:www-data assets/admin/package.json assets/admin/package-lock.json assets/admin/.npmrc /app/assets/admin/

WORKDIR /app/assets/admin

RUN --mount=type=cache,id=npm-cache,target=/root/.npm \
    npm install

COPY --chown=www-data:www-data assets/admin /app/assets/admin/

RUN npm run build

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
    mkdir -p var/cache var/log && \
    composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    chmod +x bin/console && \
    php -d memory_limit=-1 bin/console cache:clear && \
    php -d memory_limit=-1 bin/console cache:warmup -eprod && \
    php bin/console importmap:install && \
    php bin/console asset-map:compile && \
    sync

COPY --from=ghcr.io/alexandre-daubois/ember:latest /ember /usr/local/bin/ember

# Through PHP on purpose: a poisoned worker thread must turn the container unhealthy,
# which Caddy's :2019/metrics endpoint (no PHP execution) can never detect.
HEALTHCHECK --start-period=60s CMD [ "curl", "-fs", "http://localhost/healthz" ]

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
    mkdir -p var/cache var/log && \
    composer dump-autoload --optimize --no-dev --classmap-authoritative && \
    chmod +x bin/console && \
    php bin/console cache:clear && \
    php bin/console cache:warmup -eprod && \
    sync

HEALTHCHECK CMD [ "echo", "OK" ]

# Receivers named explicitly: async (Forgie & co) + the Symfony Scheduler transport.
CMD [ "php", "bin/console", "messenger:consume", "async", "scheduler_default", "--time-limit=3600", "--failure-limit=10", "-vv" ]


# Database backup runner. Standalone (not FROM base): it needs the PostgreSQL 18 client, not PHP.
# postgres:18-alpine is multi-arch and ships a pg_dump >= the bundled server; restic streams the dump
# to an S3 repository with GFS retention (see .infra/docker/backup/backup.sh). Run by the Helm CronJob.
FROM postgres:18-alpine AS backup

# hadolint ignore=DL3018
RUN apk add --no-cache restic

COPY --chmod=755 .infra/docker/backup/backup.sh /usr/local/bin/backup.sh

ENTRYPOINT ["/usr/local/bin/backup.sh"]
