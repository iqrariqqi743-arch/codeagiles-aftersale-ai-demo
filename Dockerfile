
FROM php:8.3-apache

# System packages and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install \
    pdo_mysql \
    mysqli \
    zip \
    intl \
    mbstring \
    bcmath \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite
RUN a2enmod rewrite

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install Laravel dependencies
COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

# Copy application
COPY . .

# Apache should serve Laravel public directory
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri \
    -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Allow Laravel public directory overrides
RUN printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# Laravel writable directories
RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# Composer Laravel discovery
RUN php artisan package:discover --ansi

# EthioDeploy uses PORT, default 3000
ENV PORT=3000

RUN printf '#!/bin/sh\nset -e\nPORT="${PORT:-3000}"\nsed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf\nsed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf\nexec apache2-foreground\n' \
    > /usr/local/bin/start-laravel \
    && chmod +x /usr/local/bin/start-laravel

EXPOSE 3000

CMD ["start-laravel"]
