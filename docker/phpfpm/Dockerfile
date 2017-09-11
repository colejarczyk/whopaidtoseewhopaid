FROM php:fpm

RUN apt-get update && apt-get install -y libicu-dev && docker-php-ext-install intl

CMD php-fpm
