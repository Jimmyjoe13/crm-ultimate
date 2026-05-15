FROM php:8.3-cli-alpine

RUN apk add --no-cache bash git icu-dev libpq-dev linux-headers unzip zip \
    && docker-php-ext-install intl pcntl pdo_pgsql sockets

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
