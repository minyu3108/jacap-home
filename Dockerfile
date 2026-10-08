FROM php:8.2-apache

# PHP 擴充套件：pdo_mysql / mysqli 連資料庫，gd 處理圖片縮小
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

# Apache：只保留 mpm_prefork（修正 AH00534），啟用 rewrite / headers，讓 .htaccess 生效
RUN a2dismod mpm_event mpm_worker || true \
    && rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 上傳大小限制（預設只有 2MB，說明書提到影片最大 50MB）
RUN { \
      echo "upload_max_filesize = 64M"; \
      echo "post_max_size = 64M"; \
      echo "memory_limit = 256M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# 網站檔案
COPY . /var/www/html/
RUN rm -f /var/www/html/Dockerfile /var/www/html/start.sh \
    && chown -R www-data:www-data /var/www/html

# 啟動腳本：把 uploads、sessions、config.php 接到 Volume（/data），並設定 PORT
COPY start.sh /start.sh
RUN sed -i 's/\r$//' /start.sh && chmod +x /start.sh

CMD ["sh", "/start.sh"]
