FROM php:8.4-apache

# Installiere den nötigen MySQL-Treiber für Laravel
RUN docker-php-ext-install pdo pdo_mysql
RUN docker-php-ext-install pcntl

# Aktiviere mod_rewrite für die Laravel-Routen
RUN a2enmod rewrite

# Ändere das Hauptverzeichnis (DocumentRoot) auf den /public Ordner
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
