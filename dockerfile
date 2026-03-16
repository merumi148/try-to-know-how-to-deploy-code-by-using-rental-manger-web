FROM php:8.2-apache

COPY . /var/www/html/

RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

WORKDIR /var/www/html/frontend