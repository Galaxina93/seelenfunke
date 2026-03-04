FROM php:8.4-apache

# 1. System-Abhängigkeiten für die Bildbearbeitung (GD) installieren
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. GD-Erweiterung konfigurieren (mit JPEG & WebP Support) und installieren
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

# 3. Installiere den nötigen MySQL-Treiber, PCNTL und EXIF für Laravel
RUN docker-php-ext-install pdo pdo_mysql pcntl exif

# 4. Aktiviere mod_rewrite für die Laravel-Routen
RUN a2enmod rewrite

# 5. Ändere das Hauptverzeichnis (DocumentRoot) auf den /public Ordner
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
