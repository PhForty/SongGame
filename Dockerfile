FROM php:7.4-apache
RUN docker-php-ext-install mysqli
RUN a2enmod rewrite && service apache2 restart
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
RUN echo "DirectoryIndex viewer.php" >> /etc/apache2/apache2.conf
#COPY apache2.conf /etc/apache2/