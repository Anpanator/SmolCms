#!/bin/sh
set -eu

PROJECT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
STATE_DIR=${HOME:?HOME must be set}/SmolCms
HOST_GID=$(id -g)

. "$PROJECT_DIR/docker/use-rootless-context.sh"

for directory in \
    "$STATE_DIR/db" \
    "$STATE_DIR/upload" \
    "$STATE_DIR/upload/img" \
    "$STATE_DIR/logs"
do
    mkdir -p "$directory"
    chgrp "$HOST_GID" "$directory"
    chmod 2770 "$directory"
done

DATABASE_FILE=$STATE_DIR/db/smolcms.sq3
if [ -f "$DATABASE_FILE" ] && [ "$(stat -c %u "$DATABASE_FILE")" = "$(id -u)" ]; then
    chgrp "$HOST_GID" "$DATABASE_FILE"
    chmod 0660 "$DATABASE_FILE"
fi

for log_file in "$STATE_DIR/logs"/*.log; do
    if [ -f "$log_file" ] && [ "$(stat -c %u "$log_file")" = "$(id -u)" ]; then
        chgrp "$HOST_GID" "$log_file"
        chmod 0660 "$log_file"
    fi
done

cd "$PROJECT_DIR/docker"

docker compose \
    --file compose.yaml \
    --profile prod \
    up --build --detach
