FROM registry.digitalocean.com/ulovdomov-be/php:8.3-token-xdebug-grpc AS build-local

COPY --chown=www-data:www-data . .

ENTRYPOINT ["/bin/sh", "/var/www/html/.infrastructure/docker/php/entrypoint-staging.sh"]
