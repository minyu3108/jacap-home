FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

CMD ["bash", "-c", "echo '=== mods-enabled ==='; ls -l /etc/apache2/mods-enabled | grep -i mpm; echo '=== LoadModule mpm ==='; grep -rn -i 'LoadModule.*mpm' /etc/apache2/; sed -i \"s/Listen 80/Listen ${PORT:-80}/\" /etc/apache2/ports.conf; sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf; exec apache2-foreground"]
