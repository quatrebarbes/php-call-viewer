FROM php:8.2.11-fpm-alpine

COPY ./app /opt/app
COPY ./bin /opt/bin
COPY ./composer.json /opt
COPY ./composer.lock /opt
COPY ./index.php /opt
COPY ./entrypoint.sh /opt

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN apk add --no-cache git curl jq

WORKDIR /opt
RUN composer install

USER 1001

ENTRYPOINT ["/opt/entrypoint.sh"]
