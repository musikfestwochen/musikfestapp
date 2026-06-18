# Dev-only Dockerfile for running Composer scripts and tests in Linux
# PHP version comes from composer.json (^8.4) and CI (8.4)
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-cli

# Install system dependencies (incl. build tools for PECL via $PHPIZE_DEPS)
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       git \
       unzip \
       libzip-dev \
       zlib1g-dev \
       sqlite3 \
       libsqlite3-dev \
       aspell \
       aspell-en \
       curl \
       gnupg \
       ca-certificates \
       $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js (major version configurable via NODE_MAJOR build-arg)
ARG NODE_MAJOR=22
RUN set -eux; \
    curl -fsSL https://deb.nodesource.com/setup_${NODE_MAJOR}.x | bash -; \
    apt-get update; \
    apt-get install -y --no-install-recommends nodejs; \
    node -v && npm -v; \
    rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure zip \
    && docker-php-ext-install -j"$(nproc)" zip pcntl pdo pdo_sqlite pdo_mysql

# Install PCOV for coverage
RUN pecl install pcov \
    && docker-php-ext-enable pcov

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Working directory and environment
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer

RUN printf "pcov.enabled=1\npcov.directory=/var/www/html\npcov.exclude=\"~vendor~\"\n" > /usr/local/etc/php/conf.d/zz-pcov.ini

# Show quick info on build (non-fatal)
RUN php -v && php --ri pcov || true
