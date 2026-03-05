# 0. Das Base-Image (WICHTIG! Ohne das crasht der Build)
FROM php:8.4-apache

# 1. System-Abhängigkeiten installieren (inklusive default-mysql-client & libzip-dev)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    default-mysql-client \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. GD konfigurieren und installieren
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

# 3. MySQL, PCNTL, EXIF und NEU: zip
RUN docker-php-ext-install pdo pdo_mysql pcntl exif zip

# 4. Redis Extension via PECL installieren und aktivieren
RUN pecl install redis && docker-php-ext-enable redis

# 5. Apache Mods & DocumentRoot
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
