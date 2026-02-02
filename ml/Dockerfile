ARG PYTHON_VERSION=3.13
ARG DEBIAN_VERSION=trixie

FROM python:${PYTHON_VERSION}-slim-${DEBIAN_VERSION} AS python

SHELL ["/bin/bash", "-o", "pipefail", "-eux", "-c"]

ARG EXTERNAL_USER_ID

# hadolint ignore=DL3008,DL3013
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    --mount=type=cache,target=/root/.cache/pip \
    set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      python3-tk \
    ; \
    pip install poetry ruff; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*; \
    sync

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN usermod -u ${EXTERNAL_USER_ID} www-data; \
    groupmod -g ${EXTERNAL_USER_ID} www-data; \
    mkdir -p /app/ml /var/www /app/data/weather/ml; \
    chown -R www-data:www-data /app /var/www /app/data/weather/ml

USER www-data
WORKDIR /app/ml

VOLUME /app/data/weather/ml

COPY --chown=www-data:www-data pyproject.toml poetry.lock /app/ml/


ENV POETRY_VIRTUALENVS_IN_PROJECT=true

RUN poetry install --only main

ENV PATH="/app/ml/.venv/bin:$PATH"
