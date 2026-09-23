# Dockerfile — links curtos de ir.niucursos.com.br
# Gerado automaticamente pelo AG Link Tracker.
# Use junto com r.php e .htaccess desta pasta (deploy via Dokploy/Docker).
# Nao e preciso editar NADA aqui.

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

COPY r.php /var/www/html/r.php
COPY .htaccess /var/www/html/.htaccess

RUN chown -R www-data:www-data /var/www/html
