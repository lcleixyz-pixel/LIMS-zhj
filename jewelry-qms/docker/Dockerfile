FROM composer:2.8 AS vendor

WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist

FROM composer:2.8

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

RUN docker-php-ext-install pdo_mysql

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor /app/vendor

COPY docker/entrypoint.sh /usr/local/bin/jewelry-qms-entrypoint
RUN chmod +x /usr/local/bin/jewelry-qms-entrypoint

ENTRYPOINT ["jewelry-qms-entrypoint"]
CMD ["php", "think", "run", "-H", "0.0.0.0", "-p", "8000"]
