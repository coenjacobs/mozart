# Default version used for production images (release workflow)
# CI testing overrides this with build args to test multiple versions
# Update this to latest PHP version when making a new Mozart release
ARG PHP_VERSION=8.5
FROM composer:2.9.5 AS composer
FROM php:${PHP_VERSION}-cli-alpine AS base

# Builder stage: for CI testing (no Xdebug, faster builds)
FROM base AS builder
RUN apk update && apk add git
COPY ./docker/php/error_reporting.ini /usr/local/etc/php/conf.d/error_reporting.ini
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY ./ /mozart/
WORKDIR /mozart/
RUN composer install

# Develop stage: extends builder with Xdebug for local development
FROM builder AS develop
RUN apk add --update linux-headers
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug-3.5.1 \
    && docker-php-ext-enable xdebug
COPY ./docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

FROM builder AS packager
RUN rm -rf vendor
RUN composer install --no-dev -o

FROM base AS application
# Add metadata labels
ARG VERSION
ARG BUILD_DATE
ARG VCS_REF
ARG SOURCE_URL
LABEL org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.source="${SOURCE_URL}" \
      org.opencontainers.image.title="Mozart" \
      org.opencontainers.image.description="Composes all dependencies as a package inside a WordPress plugin" \
      org.opencontainers.image.documentation="${SOURCE_URL}/blob/master/README.md" \
      org.opencontainers.image.licenses="MIT"
RUN mkdir project
WORKDIR /project/
COPY --from=packager /mozart/ /mozart/
