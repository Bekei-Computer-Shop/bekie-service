# ============================================================
# Stage 1: Build frontend assets
# ============================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy dependency files first for Docker layer caching
COPY package*.json ./

# Install frontend dependencies
RUN npm ci

# Copy application source
COPY . .

# Build Vite assets
RUN npm run build


# ============================================================
# Stage 2: Laravel production application
# ============================================================
FROM php:8.2-apache

WORKDIR /var/www/html

# ------------------------------------------------------------
# Install system dependencies
# ------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# Configure PHP extensions
# ------------------------------------------------------------
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mbstring \
    zip \
    intl \
    bcmath \
    exif \
    pcntl \
    gd

# ------------------------------------------------------------
# Install Redis PHP extension
# ------------------------------------------------------------
RUN pecl install redis \
    && docker-php-ext-enable redis

# ------------------------------------------------------------
# Enable Apache rewrite
# ------------------------------------------------------------
RUN a2enmod rewrite

# ------------------------------------------------------------
# Install Composer
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ------------------------------------------------------------
# Copy Laravel application
# ------------------------------------------------------------
COPY . .

# ------------------------------------------------------------
# Copy Vite production build
# ------------------------------------------------------------
COPY --from=frontend /app/public/build ./public/build

# ------------------------------------------------------------
# Install production PHP dependencies
# ------------------------------------------------------------
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ------------------------------------------------------------
# Configure Apache document root
# ------------------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/sites-available/default-ssl.conf

# ------------------------------------------------------------
# Laravel permissions
# ------------------------------------------------------------
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data \
    storage \
    bootstrap/cache

RUN chmod -R 775 \
    storage \
    bootstrap/cache

# ------------------------------------------------------------
# Laravel optimization
# ------------------------------------------------------------
RUN php artisan package:discover --ansi

# Don't cache config/routes here because Render environment
# variables are injected at runtime.
#
# Force CACHE_STORE=file so cache:clear does not need a database
# connection. Without it, the absence of .env at build time makes
# config/cache.php fall back to the 'database' store, which then
# requires a SQLite file the image does not — and should not —
# ship. Runtime env vars (Render / docker-compose) override this
# to CACHE_STORE=redis; only the build-time clear is affected.
# Railway only injects production variables after the image is built. Do not
# run cache-clearing commands here: Laravel's default SQLite connection would
# otherwise be selected before Railway's database is available.

# ------------------------------------------------------------
# Render / container port
# ------------------------------------------------------------
EXPOSE 8080

# ------------------------------------------------------------
# Start Apache on Railway's runtime PORT.
# ------------------------------------------------------------
COPY docker/railway-entrypoint.sh /usr/local/bin/railway-entrypoint
RUN chmod +x /usr/local/bin/railway-entrypoint

ENTRYPOINT ["railway-entrypoint"]
CMD ["apache2-foreground"]
