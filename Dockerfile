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

# Set a dummy APP_KEY for build-time operations
ARG APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
ENV APP_KEY=${APP_KEY}

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
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear

# ------------------------------------------------------------
# Render / container port
# ------------------------------------------------------------
EXPOSE 80

# ------------------------------------------------------------
# Start Apache
# ------------------------------------------------------------
CMD ["apache2-foreground"]
