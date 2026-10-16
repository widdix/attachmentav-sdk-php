# Build context: the repository root (see compose.yaml).
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && printf "upload_max_filesize=10M\npost_max_size=12M\ndisplay_errors=Off\nlog_errors=On\n" > /usr/local/etc/php/conf.d/uploads.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The SDK from this repository. Once published, `composer require attachmentav/virus-scan-sdk` is enough.
COPY composer.json /sdk/composer.json
COPY src /sdk/src
COPY config /sdk/config

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer create-project --no-interaction --prefer-dist symfony/skeleton . \
    && composer config repositories.attachmentav '{"type": "path", "url": "/sdk", "options": {"symlink": false}}' \
    && composer config allow-plugins.php-http/discovery true \
    && composer require --no-interaction symfony/twig-bundle symfony/security-csrf symfony/http-client nyholm/psr7 \
        "attachmentav/virus-scan-sdk:*@dev"

COPY examples/symfony/src src
COPY examples/symfony/templates templates
COPY examples/symfony/config config

ENV APP_ENV=prod
RUN composer dump-env prod && php bin/console cache:clear

EXPOSE 8000
# PHP's built-in web server is fine for this example; use nginx/php-fpm or FrankenPHP in production.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]
