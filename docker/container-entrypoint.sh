#!/usr/bin/env bash
set -Eeuo pipefail

umask 0007

APP_ROOT=${APP_ROOT:-/var/www/html}
DATABASE_FILE=$APP_ROOT/private/db/smolcms.sq3

mkdir -p \
    "$APP_ROOT/private/db" \
    "$APP_ROOT/private/logs" \
    "$APP_ROOT/private/upload/img" \
    "$APP_ROOT/private/upload/tmp" \
    /tmp/nginx/client_body \
    /tmp/nginx/fastcgi \
    /tmp/nginx/proxy \
    /tmp/nginx/scgi \
    /tmp/nginx/uwsgi

for log_name in \
    error.log \
    info.log \
    nginx-access.log \
    nginx-error.log \
    php-error.log \
    php-fpm-access.log \
    php-fpm-error.log
do
    log_path=$APP_ROOT/private/logs/$log_name
    if [[ ! -e "$log_path" ]]; then
        touch "$log_path"
    fi
    if [[ -O "$log_path" ]]; then
        chmod 0660 "$log_path"
    fi
done

if [[ ! -f "$DATABASE_FILE" ]]; then
    php /usr/local/lib/smolcms/init-db.php
fi
if [[ -O "$DATABASE_FILE" ]]; then
    chmod 0660 "$DATABASE_FILE"
fi

php-fpm -F --fpm-config /usr/local/etc/php-fpm.conf &
php_fpm_pid=$!
nginx -g 'daemon off;' -c /etc/nginx/nginx.conf &
nginx_pid=$!

shutdown() {
    kill -TERM "$php_fpm_pid" "$nginx_pid" 2>/dev/null || true
}

trap shutdown INT TERM

set +e
wait -n "$php_fpm_pid" "$nginx_pid"
status=$?
set -e

shutdown
wait "$php_fpm_pid" 2>/dev/null || true
wait "$nginx_pid" 2>/dev/null || true
exit "$status"
