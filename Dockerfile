FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite sqlite3

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . /var/www/html/

WORKDIR /var/www/html/

RUN composer install

EXPOSE 80

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    php-dev \
    autoconf \
    bison \
    re2c \
    && docker-php-ext-install pdo pdo_sqlite


