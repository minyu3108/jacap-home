#!/bin/sh
set -e

mkdir -p /data/uploads /data/sessions

# uploads：第一次把原本的內容（含 .htaccess）搬進 Volume，再改成連結
if [ -d /var/www/html/uploads ] && [ ! -L /var/www/html/uploads ]; then
  cp -an /var/www/html/uploads/. /data/uploads/ 2>/dev/null || true
  rm -rf /var/www/html/uploads
fi
ln -sfn /data/uploads /var/www/html/uploads

# 登入 session 也保留，重新部署不會被登出
if [ -d /var/www/html/inc/sessions ] && [ ! -L /var/www/html/inc/sessions ]; then
  rm -rf /var/www/html/inc/sessions
fi
ln -sfn /data/sessions /var/www/html/inc/sessions

# config.php：安裝時會寫進 /data/config.php，之後每次部署都接回來
ln -sfn /data/config.php /var/www/html/inc/config.php

chown -R www-data:www-data /data

# Railway 的 PORT
sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
