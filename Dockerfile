FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_pgsql pcntl zip \
    && pecl channel-update pecl.php.net \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN useradd -u 1000 -m pavel

WORKDIR /var/www/html

COPY --chown=pavel:pavel src/ .

RUN chmod +x artisan

USER pavel

RUN if [ -f composer.json ]; then composer install --no-interaction --optimize-autoloader --no-dev; fi
