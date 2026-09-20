FROM php:8.4-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libpq-dev libzip-dev libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_pgsql pdo_sqlite intl zip mbstring gd bcmath opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && php artisan package:discover \
    && php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/teamvicov.ini
COPY docker/entrypoint.sh /usr/local/bin/teamvicov-entrypoint
RUN chmod +x /usr/local/bin/teamvicov-entrypoint
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'
ENTRYPOINT ["teamvicov-entrypoint"]
CMD ["apache2-foreground"]
