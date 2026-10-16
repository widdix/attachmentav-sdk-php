# Build context: the repository root (see compose.yaml).
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && printf "upload_max_filesize=10M\npost_max_size=12M\n" > /usr/local/etc/php/conf.d/uploads.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The SDK from this repository. Once published, `composer require attachmentav/virus-scan-sdk` is enough.
COPY composer.json /sdk/composer.json
COPY src /sdk/src
COPY config /sdk/config

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer create-project --no-interaction --prefer-dist laravel/laravel . "^12.0" \
    && composer config repositories.attachmentav '{"type": "path", "url": "/sdk", "options": {"symlink": false}}' \
    && composer require --no-interaction "attachmentav/virus-scan-sdk:*@dev"

COPY examples/laravel/app app
COPY examples/laravel/routes routes
COPY examples/laravel/resources resources

# No database required: store sessions in files.
ENV SESSION_DRIVER=file \
    CACHE_STORE=file \
    LOG_CHANNEL=stderr

EXPOSE 8000
# PHP's built-in web server is fine for this example; use nginx/php-fpm or FrankenPHP in production.
# (`php artisan serve` would drop the container's environment variables when a .env file exists.)
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
