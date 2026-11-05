FROM node:22-alpine AS frontend

WORKDIR /app

# Render passes service environment values to Docker builds as build arguments.
# These values are public browser configuration, not Pusher server credentials.
# The authoritative defaults live in .env.production, so these args are only
# optional overrides.
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_APP_CLUSTER
ARG VITE_SUPABASE_URL
ARG VITE_SUPABASE_ANON_KEY

COPY package.json package-lock.json ./
RUN npm ci

COPY resources resources
COPY public public
COPY vite.config.js .
RUN npm run build

FROM php:8.4-apache

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    ghostscript \
    imagemagick \
    libfreetype6-dev \
    libjpeg-dev \
    libmagickwand-dev \
    libpng-dev \
    libpq-dev \
    libwebp-dev \
    libzip-dev \
    tesseract-ocr \
    tesseract-ocr-eng \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && (sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-6/policy.xml 2>/dev/null || true) \
    && (sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/g' /etc/ImageMagick-7/policy.xml 2>/dev/null || true) \
    && (printf '<?xml version="1.0" encoding="UTF-8"?>\n<policymap>\n  <policy domain="coder" rights="read|write" pattern="PDF" />\n</policymap>\n' > /etc/ImageMagick-6/config/policy.xml 2>/dev/null || true) \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend /app/public/build public/build

RUN composer dump-autoload --optimize --no-scripts

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs storage/app/private/temp bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 10000

CMD ["sh", "-c", "php artisan migrate --force || true; php artisan queue:work redis --queue=document-ai,emails,default --tries=3 --timeout=300 & php artisan schedule:work & cd public && php -d upload_max_filesize=20M -d post_max_size=22M -d max_file_uploads=100 -d memory_limit=512M -S 0.0.0.0:${PORT:-10000} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"]
