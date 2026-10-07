# RPPL on Render (free Docker web service + free PostgreSQL). See README, "Deploying on Render".

# ---- 1. PHP dependencies (cached until composer.json / composer.lock change) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs

# ---- 2. Frontend assets (Vite + Tailwind) ----
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# resources/css/app.css makes Tailwind scan the framework's pagination views too.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources vendor/laravel/framework/src/Illuminate/Pagination/resources
RUN npm run build

# ---- 3. The app: Apache + PHP ----
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev libpq-dev libicu-dev libpng-dev libjpeg-dev libfreetype6-dev \
        tesseract-ocr \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql pdo_pgsql zip gd intl bcmath exif pcntl opcache \
    && a2enmod rewrite remoteip \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-render.conf /etc/apache2/conf-available/render.conf
COPY docker/php-render.ini /usr/local/etc/php/conf.d/render.ini
RUN a2enconf render \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY --from=assets /app/public/firebase-messaging-sw.js ./public/firebase-messaging-sw.js

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /start.sh
RUN sed -i 's/\r$//' /start.sh && chmod +x /start.sh

# Render sets $PORT (default 10000); start.sh points Apache at it.
EXPOSE 10000
CMD ["/start.sh"]
