FROM nginx:latest

RUN apt-get update && apt-get install -y vim

COPY ./nginx.conf /etc/nginx/
COPY ./whopaidtoseewhopaid.localhost.conf /etc/nginx/conf.d/

WORKDIR /var/www/html

RUN usermod --non-unique --uid 1000 www-data

CMD chown -R www-data:www-data /var/www/html && /usr/sbin/nginx -g "daemon off;"
