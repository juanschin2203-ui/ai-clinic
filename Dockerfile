# ------------------------------------------------------------------------------
# Rocket Coding — production image
# ------------------------------------------------------------------------------
# Multi-stage build. The final `runtime` image is what ships to production.
#
# Layers are ordered to maximize cache hits:
#   1. composer.json changes alone         → only PHP deps rebuild
#   2. source-code-only changes            → only the app copy layer rebuilds
#   3. package.json / vite.config changes  → only the node build rebuilds
#
# Non-root runtime user. ext-pcntl is compiled in (Horizon requires it).
# ------------------------------------------------------------------------------

# =============================================================================
# Stage 1 — composer (PHP dependency install)
# =============================================================================
FROM composer:2 AS composer-deps

WORKDIR /app

# Copy only the dep manifests first for better cache hits.
COPY composer.json composer.lock ./

# --no-scripts defers artisan package:discover until the app code is present.
# --no-dev strips dev-only deps (Pest, Mockery, Pail) from the production image.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# =============================================================================
# Stage 2 — node (frontend asset build, if using Vite)
# =============================================================================
FROM node:25-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json* vite.config.js* ./
# Only install when there IS a package-lock — dev may skip frontend entirely.
RUN if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; fi

COPY resources/ ./resources/
COPY public/ ./public/
RUN if [ -f package-lock.json ]; then npm run build; fi

# =============================================================================
# Stage 3 — runtime
# =============================================================================
FROM php:8.3-fpm-alpine AS runtime

ARG APP_USER_ID=1000
ARG APP_GROUP_ID=1000

# System packages + PHP extensions Rocket Coding needs:
#   - pdo_mysql       — MySQL connectivity
#   - redis           — phpredis for queue/cache (faster than predis)
#   - bcmath          — precise money math for invoices
#   - opcache         — compiled bytecode in RAM (huge perf win)
#   - pcntl + posix   — required by Horizon for supervisor forks
#   - zip / intl / gd — standard Laravel dependencies
#   - sockets         — required for some redis client features
RUN apk add --no-cache \
        nginx \
        supervisor \
        mysql-client \
        bash \
        curl \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql bcmath intl zip gd pcntl sockets opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear /tmp/*

# Non-root runtime user.
RUN addgroup -g ${APP_GROUP_ID} -S rocket \
    && adduser -u ${APP_USER_ID} -S -G rocket -H -D rocket

WORKDIR /var/www/html

# Copy app source (order: deps → node build → app code, for cache).
COPY --chown=rocket:rocket --from=composer-deps /app/vendor ./vendor
COPY --chown=rocket:rocket --from=node-build /app/public ./public
COPY --chown=rocket:rocket . .

# Finalize composer autoloader now that source is in place.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
        --working-dir=/var/www/html \
    && php artisan package:discover --ansi \
    && chown -R rocket:rocket /var/www/html/storage /var/www/html/bootstrap/cache

# OPcache + php.ini prod tuning
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/99-rocket-coding.ini
COPY docker/prod/nginx.conf /etc/nginx/nginx.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

# supervisord manages: nginx + php-fpm + horizon + scheduler (via cron).
# Role of the container is chosen by the env var CONTAINER_ROLE:
#   - web     → nginx + php-fpm (default)
#   - worker  → horizon only
#   - cron    → schedule:work only
# This lets a single image serve all three tiers in production.
USER rocket
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["web"]
