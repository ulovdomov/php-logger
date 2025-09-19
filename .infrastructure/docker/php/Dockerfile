FROM registry.digitalocean.com/ulovdomov-be/php:8.3-token-xdebug-grpc AS build-local

USER root

RUN apk update --no-interactive

RUN --mount=type=secret,id=composer_token \
    docker-composer-auth

USER www-data

COPY --chown=www-data:www-data . .

ENTRYPOINT ["/bin/sh", "/var/www/html/.infrastructure/docker/php/entrypoint-staging.sh"]
