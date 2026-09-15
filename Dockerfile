FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install mysqli curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-security.conf /etc/apache2/conf-available/drw-security.conf
RUN a2enconf drw-security \
    && printf 'display_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/zz-production.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . ./
COPY --from=dependencies /app/vendor ./vendor

RUN chown -R www-data:www-data /var/www/html/images

EXPOSE 80
