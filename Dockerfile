FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        opcache \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${GID}" app \
    && useradd \
        --uid "${UID}" \
        --gid "${GID}" \
        --create-home \
        --shell /bin/bash \
        app \
    && mkdir -p /var/www /home/app/.composer/cache \
    && chown -R app:app /var/www /home/app

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www

USER app

CMD ["php-fpm", "-F"]
