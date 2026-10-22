# Image de développement local (docker-compose.yml) : même pile que la
# production (Apache + PHP 8.3 + extensions requises par l'API Laravel).
# Version épinglée : 8.3.36 corrige php2shell (CVE-2026-109618 / CVE-2026-109619).
FROM php:8.3.36-apache

# Mitigation php2shell : la progression d'upload en session est le vecteur connu.
RUN echo "session.upload_progress.enabled=Off" > /usr/local/etc/php/conf.d/zz-php2shell.ini

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
