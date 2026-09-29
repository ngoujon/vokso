# Image de développement local (docker-compose.yml) : même pile que la
# production (Apache + PHP 8.3 + extensions requises par l'API Laravel).
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libwebp-dev \
        libjpeg-dev \
        unzip \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install pdo_mysql zip intl gd bcmath \
    && a2enmod rewrite headers expires deflate \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
