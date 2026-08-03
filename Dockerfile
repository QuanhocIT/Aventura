FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    nginx supervisor curl icu-dev oniguruma-dev libzip-dev libpng-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip gd bcmath opcache pcntl posix

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── PHP build stage ───────────────────────────────────────────────────────────
FROM base AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize

# ─── Frontend build stage (cần PHP + composer cho wayfinder) ──────────────────
FROM node:20-alpine AS frontend

# Cài PHP nhẹ để wayfinder có thể gọi `php artisan` trong lúc vite build
RUN apk add --no-cache php83 php83-phar php83-mbstring php83-openssl \
    php83-pdo php83-pdo_mysql php83-pdo_sqlite php83-sqlite3 php83-intl \
    php83-tokenizer php83-xml php83-xmlwriter php83-dom php83-fileinfo \
    php83-session php83-ctype php83-curl php83-pcntl php83-posix \
    && ln -sf /usr/bin/php83 /usr/bin/php

WORKDIR /app

# Copy vendor (composer deps) từ build stage
COPY --from=build /var/www/html/vendor ./vendor

# Cài npm deps
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

# Copy toàn bộ source
COPY . .

# Wayfinder boots Laravel during the Vite build. Keep this build-only
# invocation on an in-memory SQLite/cache configuration.
ENV APP_ENV=production \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=:memory: \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync

# Build frontend assets
RUN npm run build

# ─── Production stage ──────────────────────────────────────────────────────────
FROM base AS production

COPY --from=build /var/www/html /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build

# Clear cached bootstrap files (có thể chứa path Windows) rồi cache lại trên Linux
RUN php artisan config:clear --no-interaction \
    && php artisan route:clear --no-interaction \
    && php artisan view:clear --no-interaction \
    && php artisan config:cache --no-interaction \
    && php artisan route:cache --no-interaction \
    && php artisan view:cache --no-interaction

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /usr/local/bin/aventura-start.sh
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 10000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
