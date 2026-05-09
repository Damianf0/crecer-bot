FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    # OCR + PDF tools para el legajo de documentos
    tesseract-ocr tesseract-ocr-spa poppler-utils libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd opcache zip \
    && rm -rf /var/lib/apt/lists/*

# PHP config
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini

# OPcache
COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# PHP-FPM pool optimizado
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
