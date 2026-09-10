# ---- dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# ---- runtime ----
FROM php:8.4-cli-alpine
RUN apk add --no-cache libpq-dev icu-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql pgsql intl opcache \
 && apk del $PHPIZE_DEPS
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
# Migrate on start so a fresh database is usable, then serve.
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
