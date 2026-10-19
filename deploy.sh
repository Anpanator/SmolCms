#!/bin/sh
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_DIR=${HOME:?HOME must be set}/SmolCms
LOG_DIR=$STATE_DIR/logs
PORT=${SMOLCMS_DEV_PORT:-8080}

# Prefer a running rootless daemon when one is configured. An explicit
# DOCKER_CONTEXT or DOCKER_HOST from the caller takes precedence.
. "$PROJECT_DIR/docker/use-rootless-context.sh"

for data_dir in \
    "$STATE_DIR/db" \
    "$STATE_DIR/upload" \
    "$STATE_DIR/img" \
    "$STATE_DIR/public-img" \
    "$LOG_DIR"
do
    mkdir -p "$data_dir"
    chgrp "$(id -g)" "$data_dir"
    chmod 2770 "$data_dir"
done

DATABASE_FILE=$STATE_DIR/db/smolcms.sq3
if [ -f "$DATABASE_FILE" ] && [ "$(stat -c %u "$DATABASE_FILE")" = "$(id -u)" ]; then
    chgrp "$(id -g)" "$DATABASE_FILE"
    chmod 0660 "$DATABASE_FILE"
fi

for log_file in "$LOG_DIR"/*.log; do
    if [ -f "$log_file" ] && [ "$(stat -c %u "$log_file")" = "$(id -u)" ]; then
        chgrp "$(id -g)" "$log_file"
        chmod 0660 "$log_file"
    fi
done

docker compose --file "$PROJECT_DIR/docker/compose.yaml" up --build --detach

printf 'SmolCms dev is running at http://localhost:%s\n' "$PORT"
