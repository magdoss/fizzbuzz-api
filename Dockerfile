# syntax=docker/dockerfile:1
FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer

FROM php:8.4.25-fpm-alpine3.24@sha256:b7d9b8c58641dec579abcc5882766742c05cc92acf7b43b9f7f4b575241b1900 AS base
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.11.15/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && adduser -D -H -u 10001 -s /sbin/nologin app
COPY docker/php/app.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
WORKDIR /app
ENV APP_ENV=prod

FROM base AS dev
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/php/dev.ini $PHP_INI_DIR/conf.d/zz-dev.ini
ENV APP_ENV=dev

FROM composer AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM base AS prod
COPY --chown=app:app . .
COPY --chown=app:app --from=vendor /app/vendor vendor
RUN install -d -o app -g app var
USER 10001:10001
RUN php bin/console cache:warmup
CMD ["php-fpm"]
