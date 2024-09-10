FROM composer:2.7.7
FROM php:8.3.9-cli-alpine AS base

FROM base as builder
RUN apk update && apk add git
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY ./ /mozart/
WORKDIR /mozart/
RUN composer install

FROM builder as packager
RUN rm -rf vendor
RUN composer install --no-dev -o

FROM base AS application
RUN mkdir project
WORKDIR /project/
COPY --from=packager /mozart/ /mozart/
COPY ./bin/ /mozart/bin/
COPY ./src/ /mozart/src/
