# syntax=docker/dockerfile:1.6

FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache         curl         libpng         libpng-dev         icu-dev         libzip-dev         zip         unzip         oniguruma-dev         bash         mysql-client

RUN docker-php-ext-install         intl         pdo         pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --prefer-dist --no-dev --no-scripts --no-progress

COPY . .

RUN composer dump-autoload --optimize

CMD ["php-fpm"]
