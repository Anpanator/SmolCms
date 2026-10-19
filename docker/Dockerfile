FROM php:8.5-fpm AS app-base

ARG APP_UID=10001
ARG APP_GID=10001

ENV APP_ROOT=/var/www/html

WORKDIR ${APP_ROOT}

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libavif-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        nginx \
    && docker-php-ext-configure gd \
        --with-avif \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd \
    && php -r 'foreach (["mbstring", "pdo_sqlite"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } }' \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${APP_GID}" smolcms \
    && useradd \
        --uid "${APP_UID}" \
        --gid "${APP_GID}" \
        --home-dir /var/www/html \
        --no-create-home \
        --shell /usr/sbin/nologin \
        smolcms

COPY composer.json ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# Copy only runtime application content; the /docker and /docs trees are not
# deployed into the application document root.
COPY --chown=${APP_UID}:${APP_GID} constants.php config.php ./
COPY --chown=${APP_UID}:${APP_GID} src/ ./src/
COPY --chown=${APP_UID}:${APP_GID} public/ ./public/
COPY --chown=${APP_UID}:${APP_GID} private/init/ ./private/init/
COPY --chown=${APP_UID}:${APP_GID} private/sql/ ./private/sql/
COPY --chown=${APP_UID}:${APP_GID} private/assets/ ./private/assets/

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/container-entrypoint.sh /usr/local/bin/smolcms-entrypoint

RUN rm -f /usr/local/etc/php-fpm.d/docker.conf \
    && mkdir -p \
        private/db \
        private/logs \
        private/img \
        private/img/sample \
        private/img/dl \
        private/upload/img \
        private/upload/tmp \
        public/img \
        public/img/thumb \
        /tmp/nginx/client_body \
        /tmp/nginx/fastcgi \
        /tmp/nginx/proxy \
        /tmp/nginx/scgi \
        /tmp/nginx/uwsgi \
    && chown -R "${APP_UID}:${APP_GID}" "${APP_ROOT}" /tmp/nginx \
    && chmod 0755 /usr/local/bin/smolcms-entrypoint

USER ${APP_UID}:${APP_GID}

RUN php src/app.php --generate-js-integrity \
    && rm -f private/db/smolcms.sq3 \
    && php-fpm -t --fpm-config /usr/local/etc/php-fpm.conf \
    && nginx -t -c /etc/nginx/nginx.conf

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/smolcms-entrypoint"]

FROM app-base AS dev
COPY --chown=10001:10001 docker/php-dev.ini /usr/local/etc/php/conf.d/zz-smolcms.ini
ENV APP_ENV=development

FROM app-base AS prod
COPY --chown=10001:10001 docker/php-prod.ini /usr/local/etc/php/conf.d/zz-smolcms.ini
ENV APP_ENV=production
