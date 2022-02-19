FROM php:7.4-apache
RUN docker-php-ext-install mysqli
COPY apache2.conf /etc/apache2/