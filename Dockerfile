# Stage 1: Composer builder
FROM php:8.2-fpm-alpine AS composer_builder
WORKDIR /app

# Install OS-level dependencies for PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    jpeg-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    libzip-dev

# Install PHP extensions required by the project
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) bcmath exif gd pcntl pdo_mysql pdo_pgsql pgsql zip && \
    pecl install redis && docker-php-ext-enable redis && \
    apk del .build-deps

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# Stage 2: Node builder
FROM node:22-alpine AS node_builder
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci
RUN npm run build
# Stage 3: PHP Builder with OS dependencies
FROM php:8.2-fpm-alpine AS php_builder
WORKDIR /var/www/html
# Install dev dependencies to build PHP extensions (This is now redundant but kept for structural integrity as per previous changes)
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    freetype-dev \
    jpeg-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    libzip-dev
# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath exif gd pcntl pdo_mysql pdo_pgsql pgsql zip
RUN pecl install redis && docker-php-ext-enable redis
# Cleanup dev dependencies
RUN apk del .build-deps

# Stage 4: Final Production Image
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html
# Install only runtime dependencies
RUN apk add --no-cache \
    bash \
    curl \
    nginx \
    supervisor \
    libjpeg \
    libpng \
    libxml2 \
    oniguruma \
    postgresql-libs \
    libzip

# Copy built extensions and assets from builder stages
COPY --from=php_builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php_builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=composer_builder /app/vendor ./vendor
COPY --from=node_builder /app/public/build ./public/build

# Copy application code and configs
COPY . .

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/logs \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
