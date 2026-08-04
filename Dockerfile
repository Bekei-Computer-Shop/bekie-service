# Stage 1: Composer builder
FROM composer:2 as composer_builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-progress

# Stage 2: Node builder
FROM node:lts as node_builder
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci --no-audit --no-fund
RUN npm run build

# Stage 3: Final PHP-FPM runtime
FROM php:8.3-fpm
WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false

RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    jpeg-dev \
    nginx \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure zip \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/cache/apk/*

# Copy application code
COPY . .

# Copy vendor from composer_builder
COPY --from=composer_builder /app/vendor ./vendor

# Copy built assets from node_builder
COPY --from=node_builder /app/public/build ./public/build
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/start.sh \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Optimize autoloader (disable scripts to avoid package:discover errors during build)
RUN composer dump-autoload --optimize --no-scripts \
    && rm /usr/bin/composer

# Set permissions on storage and bootstrap/cache (already owned by www-data due to above chown)
# But ensure they are writable
RUN chmod -R 775 storage bootstrap/cache

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:${PORT:-8080}/ || exit 1

ENTRYPOINT ["/usr/local/bin/start.sh"]
EXPOSE 8080
# Note: Render will use the PORT environment variable, which we use in the nginx configuration.
