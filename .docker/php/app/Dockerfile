FROM php:8.5-fpm

# Build Tools

RUN apt-get update \
 && apt-get install -y --no-install-recommends --no-install-suggests \
    $PHPIZE_DEPS \
    bison \
    libtool \
    # Version control for Composer packages
    git \
    # HTTP client library
    curl \
    # Required for GD extension (image processing)
    libfreetype-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    # Required for mbstring extension (multibyte strings)
    libonig-dev \
    # # Required for XML extensions
    # libxml2-dev \
    # Required for ZIP extension
    libzip-dev \
    # Archive utilities for package management
    zip \
    unzip \
    libmemcached-dev \
    libssl-dev \
    zlib1g-dev \
;

# RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
#     && docker-php-ext-install -j$(nproc) gd \
#     && docker-php-ext-install mysqli pdo pdo_mysql \
#     ;

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/


RUN install-php-extensions \
    gd \
    mysqli \
    pdo_mysql \
    igbinary \
    intl \
    xdebug \
    imagick \
    timezonedb \
    redis \
;

COPY --from=composer/composer:2.10-bin /composer /usr/bin/composer;

# WP-CLI - https://hub.docker.com/_/wordpress/tags?page=1&name=cli
COPY --from=wordpress:cli-php8.5 /usr/local/bin/wp /usr/local/bin/wp

# COPY --from=ghcr.io/php/pie:bin /pie /usr/bin/pie;

## Application dependencies
# RUN pie install xdebug/xdebug;
# RUN pie install imagick/imagick;
# RUN pie install phpredis/phpredis;
# RUN pie install pecl/timezonedb;

## Copy configs.
COPY ./.docker/php/conf.d/*.ini /usr/local/etc/php/conf.d/
