FROM php:8.3-fpm-alpine

# SQLite je v PHP přes pdo_sqlite — nainstalujeme rozšíření
RUN apk add --no-cache sqlite-libs sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

WORKDIR /var/www/html

# data adresář musí být zapisovatelný pro SQLite soubor
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

CMD ["php-fpm"]
