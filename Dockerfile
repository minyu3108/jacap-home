FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 診斷：build log 會印出目前載入的 MPM 與設定來源
RUN echo "=== mods-enabled ===" && ls -l /etc/apache2/mods-enabled | grep -i mpm; \
    echo "=== grep mpm ===" && grep -rn -i "mpm" /etc/apache2 --include=*.conf --include=*.load | grep -i loadmodule; \
    echo "=== apache -M ===" && apache2ctl -M 2>&1 | grep -i mpm

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway 的 PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf
ENV PORT=80
