#!/usr/bin/env bash
# Кодыг байрлуулж/шинэчилнэ. Сервер дээр ажиллуулна: sudo bash release.sh
set -euo pipefail

APP_DOMAIN="manage.dornogovi.gov.mn"
APP_DIR="/var/www/${APP_DOMAIN}"

cd "${APP_DIR}"

echo "==> Composer (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Frontend build"
npm ci
npm run build

echo "==> Laravel"
[ -f .env ] || { echo ".env байхгүй байна — deploy/env.production жишээг хуулж бөглө"; exit 1; }
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

echo "==> Эрх"
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;

systemctl reload php8.3-fpm nginx
echo "==> Дууслаа: http://${APP_DOMAIN}/"
