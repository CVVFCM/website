#!/usr/bin/env sh

set -e

envsubst < /config/config.json.template > /config/config.json

exec $@
