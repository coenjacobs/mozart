FROM composer:2.0.8

FROM php:8.0.1-cli-alpine AS base

FROM base as builder
RUN apk update && apk add git
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY ./composer.json /mozart/
WORKDIR /mozart/
RUN composer install --no-dev -o

FROM base AS application
RUN mkdir project
WORKDIR /project/
COPY --from=builder /mozart/ /mozart/
COPY ./bin/ /mozart/bin/
COPY ./src/ /mozart/src/
