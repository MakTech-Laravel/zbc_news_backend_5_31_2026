FROM php:8.4-fpm

COPY ./docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install dependencies
RUN apt-get update && apt-get install -y \
    nano nginx git unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    libjpeg62-turbo-dev libfreetype6-dev supervisor \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN mkdir -p storage/framework/{views,sessions,cache} storage/logs bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --no-scripts \
    && npm install && npm run build \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]