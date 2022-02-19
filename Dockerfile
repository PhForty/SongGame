FROM php:7.4-apache
RUN docker-php-ext-install mysqli
RUN a2enmod rewrite && service apache2 restart
COPY apache2.conf /etc/apache2/