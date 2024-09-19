FROM composer:2.7.9
FROM php:8.3.11-cli-alpine AS base

FROM base AS builder
RUN apk update && apk add git
RUN apk add --update linux-headers
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-3.3.2 \
    && docker-php-ext-enable xdebug
COPY ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY ./docker/php/error_reporting.ini /usr/local/etc/php/conf.d/error_reporting.ini
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY ./ /mozart/
WORKDIR /mozart/
RUN composer install

FROM builder AS packager
RUN rm -rf vendor
RUN composer install --no-dev -o

FROM base AS application
RUN mkdir project
WORKDIR /project/
COPY --from=packager /mozart/ /mozart/
COPY ./bin/ /mozart/bin/
COPY ./src/ /mozart/src/
