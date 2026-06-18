ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-fpm-bookworm AS base

ARG COMPOSER_VERSION=2.8.4

RUN usermod -u 1001 www-data && groupmod -g 1001 www-data

RUN apt-get update && apt-get install -y nginx python3 supervisor \
     git zip bzip2 libpng-dev libpng-dev libicu-dev \
     vim libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
     libpq-dev libxpm-dev libvpx-dev libbz2-dev zlib1g-dev libzip-dev gnumeric \
    && curl -sS https://getcomposer.org/installer | php -- --version="${COMPOSER_VERSION}" --install-dir=/usr/local/bin --filename=composer \
    && docker-php-ext-install intl bcmath mbstring bz2 gd zip pgsql pdo_pgsql pcntl sockets \
    && docker-php-ext-enable intl pcntl \
    && (pecl install apcu && docker-php-ext-enable apcu || echo "APCu skipped: no PECL release compatible with this PHP yet (optional opportunistic cache; Symfony falls back to filesystem)") \
    && apt-get remove -y git \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN apt-get update \
    && apt-get install -y gnupg apt-transport-https ca-certificates \
    && curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | bash \
    && apt-get install -y speedtest \
    && speedtest --version \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

EXPOSE 9000

FROM base AS dev

ARG INSTALL_XDEBUG=false

RUN apt-get update && apt-get install -y nginx

RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    pecl install xdebug \
    && docker-php-ext-enable xdebug \
;fi

EXPOSE 8080

ENTRYPOINT php-fpm -D && nginx -g 'daemon off;'

FROM base AS prod

ARG APP_VERSION=prod
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    NETPULSE_VERSION=${APP_VERSION}

WORKDIR /var/www

COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zz-netpulse.ini
COPY .docker/php/php-fpm.prod.conf /usr/local/etc/php-fpm.d/www.conf
COPY .docker/php/supervisord.conf /etc/supervisor/supervisord.conf
COPY .docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint \
    && rm -f /etc/nginx/sites-enabled/default

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

COPY . .
RUN cp .env.dist .env \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && php bin/console assets:install public --no-interaction \
    && php bin/console cache:clear \
    && mkdir -p var \
    && chown -R www-data:www-data var public

EXPOSE 8080
ENTRYPOINT ["docker-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]