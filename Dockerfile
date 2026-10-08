FROM php:8.2-apache

RUN echo "===== USING MY DOCKERFILE ====="

RUN docker-php-ext-install pdo_mysql

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html
