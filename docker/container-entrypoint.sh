#!/usr/bin/env bash
set -eu

umask 0007

APP_ROOT=${APP_ROOT:-/var/www/html}
DATABASE_FILE=$APP_ROOT/private/db/smolcms.sq3

mkdir -p \
    "$APP_ROOT/private/db" \
    "$APP_ROOT/private/img" \
    "$APP_ROOT/private/logs" \
    "$APP_ROOT/private/upload/img" \
    "$APP_ROOT/private/upload/tmp" \
    /tmp/nginx/client_body \
    /tmp/nginx/fastcgi \
    /tmp/nginx/proxy \
    /tmp/nginx/scgi \
    /tmp/nginx/uwsgi

# A previous container may have stopped while leaving the processor lock file behind.
rm -f "$APP_ROOT/private/img/processing.lock"

php "$APP_ROOT/src/app.php" --init-db
php "$APP_ROOT/src/app.php" --migrations --noconfirm

for file_path in "$DATABASE_FILE" "$APP_ROOT"/private/logs/*.log
do
    if [[ -f "$file_path" && -O "$file_path" ]]; then
        chmod 0660 "$file_path"
    fi
done

# Background worker: reconcile flat original uploads every second (up to 100 image rows per run).
# The CLI command holds the processing lock, so overlapping runs exit immediately.
(
    while true; do
        php "$APP_ROOT/src/app.php" --process-images 100 >/dev/null 2>&1 || true
        sleep 1
    done
) &
image_worker_pid=$!

php-fpm -F --fpm-config /usr/local/etc/php-fpm.conf &
php_fpm_pid=$!
nginx -g 'daemon off;' -c /etc/nginx/nginx.conf &
nginx_pid=$!

shutdown() {
    kill -TERM "$php_fpm_pid" "$nginx_pid" "$image_worker_pid" 2>/dev/null || true
}

trap shutdown INT TERM

if wait -n "$php_fpm_pid" "$nginx_pid"; then
    status=0
else
    status=$?
fi

shutdown
wait "$php_fpm_pid" 2>/dev/null || true
wait "$nginx_pid" 2>/dev/null || true
wait "$image_worker_pid" 2>/dev/null || true
exit "$status"
