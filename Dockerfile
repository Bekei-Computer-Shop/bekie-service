### Stage 3: Final PHP-FPM runtime
FROM php:8.2-fpm
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libonig-dev libxml2-dev \
        libpq-dev libicu-dev zlib1g-dev ca-certificates \
    && docker-php-ext-install \
        pdo pdo_pgsql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Copy full app first so artisan exists
COPY . .
COPY --from=composer_builder /app/vendor ./vendor
COPY --from=node_builder /app/public/build ./public/build

# Now it's safe to run artisan-dependent scripts
COPY --from=composer/composer:2 /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev \
    && rm /usr/bin/composer

RUN chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 755 storage bootstrap/cache public

EXPOSE 9000
CMD ["php-fpm"]
