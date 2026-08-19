#!/usr/bin/env bash
# Ubuntu 24.04 дээр manage.dornogovi.gov.mn-г ажиллуулах орчин бэлдэнэ.
# Ажиллуулах: sudo bash provision.sh
set -euo pipefail

APP_DOMAIN="manage.dornogovi.gov.mn"
APP_DIR="/var/www/${APP_DOMAIN}"
DB_NAME="manage_dornogovi"
DB_USER="manage_user"

echo "==> Багцууд суулгаж байна"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx mysql-server unzip git curl \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

echo "==> Composer"
if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Node.js 20 (vite build хийхэд)"
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

echo "==> Өгөгдлийн сан"
DB_PASS="${DB_PASS:-$(openssl rand -base64 24)}"
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
echo "DB нууц үг: ${DB_PASS}"
echo "${DB_PASS}" > /root/.manage_dornogovi_db_pass
chmod 600 /root/.manage_dornogovi_db_pass

echo "==> Nginx"
install -d "${APP_DIR}"
cp "$(dirname "$0")/nginx.conf" "/etc/nginx/sites-available/${APP_DOMAIN}"
ln -sf "/etc/nginx/sites-available/${APP_DOMAIN}" "/etc/nginx/sites-enabled/${APP_DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

systemctl enable --now php8.3-fpm nginx mysql

echo "==> Дууслаа. Дараа нь release.sh ажиллуулж кодоо байрлуул."
