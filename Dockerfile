FROM php:8.2.11-fpm-alpine
WORKDIR /opt

RUN  apk update \
  && apk upgrade \
  && apk add ca-certificates \
  && update-ca-certificates \
  && apk add --update coreutils && rm -rf /var/cache/apk/*   \ 
  && apk add --update openjdk11 tzdata curl unzip bash \
  && apk add --no-cache msttcorefonts-installer fontconfig graphviz \
  && apk add --no-cache nss \
  && rm -rf /var/cache/apk/*
RUN update-ms-fonts

COPY ./app /opt/app
COPY ./bin /opt/bin
COPY ./composer.json /opt
COPY ./composer.lock /opt
COPY ./index.php /opt
COPY ./entrypoint.sh /opt

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN apk add --no-cache git curl jq bash

RUN composer install

RUN mkdir /opt/.tmp
RUN chown 1001:1001 /opt/.tmp
RUN chmod -R 700 /opt/.tmp

RUN mkdir /opt/.uml
RUN chown 1001:1001 /opt/.uml
RUN chmod -R 700 /opt/.uml

USER 1001

ENTRYPOINT ["/opt/entrypoint.sh"]
