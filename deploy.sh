#!/bin/sh
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_DIR=${HOME:?HOME must be set}/SmolCms
LOG_DIR=$STATE_DIR/logs
PORT=${SMOLCMS_DEV_PORT:-8080}

# Prefer a running rootless daemon when one is configured. An explicit
# DOCKER_CONTEXT or DOCKER_HOST from the caller takes precedence.
. "$PROJECT_DIR/docker/use-rootless-context.sh"

mkdir -p "$LOG_DIR"
chgrp "$(id -g)" "$LOG_DIR"
chmod 2770 "$LOG_DIR"
for log_file in "$LOG_DIR"/*.log; do
    if [ -f "$log_file" ] && [ "$(stat -c %u "$log_file")" = "$(id -u)" ]; then
        chgrp "$(id -g)" "$log_file"
        chmod 0660 "$log_file"
    fi
done

docker build \
    --file "$PROJECT_DIR/docker/Dockerfile" \
    --target dev \
    --tag smolcms:dev \
    "$PROJECT_DIR"

if docker container inspect smolcms-dev >/dev/null 2>&1; then
    docker container rm --force smolcms-dev
fi

docker run --detach \
    --name smolcms-dev \
    --restart unless-stopped \
    --publish "127.0.0.1:$PORT:8080" \
    --user 10001:10001 \
    --group-add 0 \
    --read-only \
    --tmpfs /tmp:rw,nosuid,nodev,noexec,size=64m,mode=1777 \
    --cap-drop ALL \
    --security-opt no-new-privileges:true \
    --mount type=volume,source=smolcms-dev-db,target=/var/www/html/private/db \
    --mount type=volume,source=smolcms-dev-upload,target=/var/www/html/private/upload \
    --mount "type=bind,source=$LOG_DIR,target=/var/www/html/private/logs" \
    smolcms:dev

printf 'SmolCms dev is running at http://localhost:%s\n' "$PORT"
