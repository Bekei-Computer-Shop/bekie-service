# Stage 1: Composer builder
FROM php:8.2-cli-alpine AS composer_builder
WORKDIR /app

RUN apk add --no-cache \
    curl \
    freetype-dev \
    jpeg-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    postgresql-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_pgsql pgsql zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/cache/apk/*

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# Stage 2: Node builder
FROM node:22-alpine AS node_builder
WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci
RUN npm run build

# Stage 3: Final runtime image
FROM php:8.2-fpm-alpine
WORKDIR /var/www/html

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

COPY --from=composer_builder /app/vendor ./vendor
COPY . .
COPY --from=node_builder /app/public/build ./public/build

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
