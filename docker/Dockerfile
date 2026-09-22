# syntax=docker/dockerfile:1
#
# Образ магазина (ТЗ §17): PHP 8.4-FPM с модулями из §17.1, код, зависимости без dev и
# собранные стили. Собирается на сервере скриптом docker/deploy.sh. Стадия app — PHP для
# сайта, очередей и планировщика, стадия web — nginx со статикой из public.

FROM php:8.4-fpm-alpine AS php

# Модуль redis — архивом нужной версии: pecl.php.net с сервера отвечает через раз, поэтому
# попытки повторяются при любой ошибке, а запасной источник — тот же релиз на GitHub.
ARG PHPREDIS_VERSION=6.3.0

RUN apk add --no-cache icu-libs icu-data-full libzip libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev \
        libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-source extract \
    && mkdir -p /usr/src/php/ext/redis \
    && { curl -fsSL --connect-timeout 20 --retry 5 --retry-all-errors --retry-delay 3 -o /tmp/phpredis.tgz \
            "https://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz" \
        || curl -fsSL --connect-timeout 20 --retry 5 --retry-all-errors --retry-delay 3 -o /tmp/phpredis.tgz \
            "https://codeload.github.com/phpredis/phpredis/tar.gz/refs/tags/${PHPREDIS_VERSION}"; } \
    && tar -xzf /tmp/phpredis.tgz -C /usr/src/php/ext/redis --strip-components=1 \
    && rm /tmp/phpredis.tgz \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql redis zip \
    && docker-php-source delete \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-gastrosnab.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-gastrosnab.conf

WORKDIR /var/www/html


# Зависимости и опубликованные файлы Filament. Кэш на время сборки — в памяти: базы здесь нет.
FROM php AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    CACHE_STORE=array

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize


# Стили и скрипты витрины. Tailwind читает шаблоны пагинации из vendor.
FROM node:24-alpine AS assets

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=vendor /var/www/html ./
RUN npm run build


FROM php AS app

LABEL project=gastrosnab

COPY --from=vendor /var/www/html ./
COPY --from=assets /var/www/html/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/gastrosnab-entrypoint

RUN chmod +x /usr/local/bin/gastrosnab-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["gastrosnab-entrypoint"]
CMD ["php-fpm"]


FROM nginx:1.27-alpine AS web

LABEL project=gastrosnab

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/maintenance.html /usr/share/nginx/html/maintenance.html
COPY --from=app /var/www/html/public /var/www/html/public
