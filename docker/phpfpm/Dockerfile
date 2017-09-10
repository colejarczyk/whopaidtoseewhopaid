FROM php:fpm
RUN apt-get update && apt-get install -y libicu-dev && docker-php-ext-install intl
RUN usermod --non-unique --uid 1000 www-data
CMD chown -R www-data:www-data /var/www/html && php-fpm
