FROM php:fpm

RUN apt-get update && apt-get install -y libicu-dev libsqlite3-dev libsqlite3-0 && docker-php-ext-install intl && docker-php-ext-install pdo_sqlite

CMD php-fpm
