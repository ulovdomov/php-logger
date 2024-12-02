FROM php:8.3-fpm-alpine3.19 AS base

ARG COMPOSER_TOKEN

RUN apk --update --no-cache add \
  bzip2 \
  bzip2-dev \
  curl-dev \
  libxml2-dev \
  libxslt-dev \
  postgresql-dev \
  libzip-dev \
  vim \
  make \
  fcgi \
  supervisor \
  icu-dev \
  git \
  openssh-client \
  # bugfix
  && sed -i 's/$this-_/$this->_/g' /usr/local/lib/php/OS/Guess.php \
  && docker-php-ext-enable opcache \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j "$(nproc)" bcmath bz2 mysqli pdo_mysql zip intl \
  && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY .infrastructure/docker/php/php.ini /usr/local/etc/php/conf.d/custom_php.ini

WORKDIR /var/www/html

COPY . .

USER root

RUN chown -R www-data:www-data .

FROM base as build-dev

USER www-data

RUN mkdir -p .composer

RUN echo "{\"github-oauth\": {\"github.com\": \"${COMPOSER_TOKEN}\"}}" > .composer/auth.json

RUN mkdir -p src temp log

RUN chmod -R 0777 log temp

ENTRYPOINT ["/bin/sh", "/var/www/html/.infrastructure/docker/php/entrypoint-staging.sh"]
