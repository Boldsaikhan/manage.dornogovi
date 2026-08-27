#!/usr/bin/env bash
#
# Ижил сервер дээр demo сайт үүсгэнэ (production өгөгдөлд хүрэхгүй).
# GitHub `demo` салбарыг 2 мин тутам татна.
#
#   sudo bash deploy/setup-demo-site.sh
#
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/Boldsaikhan/manage.dornogovi.git}"
SRC_DIR="/opt/manage-dornogovi-demo"
WEB_ROOT="/var/www/demo.manage.dornogovi.gov.mn"
APP_DOMAIN="demo.manage.dornogovi.gov.mn"
DB_NAME="manage_dornogovi_demo"
DB_USER="manage_demo"
CONF_FILE="/etc/manage-dornogovi-demo.conf"
PASS_FILE="/root/.manage_dornogovi_demo_secrets"
WEB_USER="${WEB_USER:-www-data}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"

if [ "$(id -u)" -ne 0 ]; then
    echo "root-оор ажиллуулна уу: sudo bash $0"
    exit 1
fi

echo "==> Demo git clone (${SRC_DIR})"
if [ -d "${SRC_DIR}/.git" ]; then
    git -C "${SRC_DIR}" fetch --quiet origin demo
    git -C "${SRC_DIR}" checkout -q demo
    git -C "${SRC_DIR}" reset --hard --quiet origin/demo
else
    git clone --quiet --branch demo "${REPO_URL}" "${SRC_DIR}"
fi

echo "==> Demo өгөгдлийн сан"
if [ ! -f "${PASS_FILE}" ]; then
    DB_PASS="$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | head -c 20)"
    echo "DB_PASS=${DB_PASS}" > "${PASS_FILE}"
    chmod 600 "${PASS_FILE}"
else
    # shellcheck disable=SC1090
    . "${PASS_FILE}"
fi

mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "==> Веб root"
mkdir -p "${WEB_ROOT}"
rsync -a --delete \
    --exclude '.git' --exclude 'node_modules' --exclude '.env' \
    --exclude 'storage' --exclude 'bootstrap/cache' --exclude 'public/storage' \
    "${SRC_DIR}/" "${WEB_ROOT}/"
mkdir -p "${WEB_ROOT}/storage/framework/"{cache,sessions,views} "${WEB_ROOT}/bootstrap/cache"

if [ ! -f "${WEB_ROOT}/.env" ]; then
    cp "${SRC_DIR}/deploy/env.demo" "${WEB_ROOT}/.env"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" "${WEB_ROOT}/.env"
    (cd "${WEB_ROOT}" && php artisan key:generate --force)
fi

cd "${SRC_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --include=dev
npm run build
rsync -a --delete \
    --exclude '.git' --exclude 'node_modules' --exclude '.env' \
    --exclude 'storage' --exclude 'bootstrap/cache' --exclude 'public/storage' \
    "${SRC_DIR}/" "${WEB_ROOT}/"

cd "${WEB_ROOT}"
php artisan migrate --force
php artisan db:seed --force || true
php artisan storage:link || true
php artisan config:cache

chown -R "${WEB_USER}:${WEB_USER}" "${WEB_ROOT}"
find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \;

echo "==> Nginx"
cp "${SRC_DIR}/deploy/nginx-demo.conf" /etc/nginx/sites-available/demo.manage.dornogovi.gov.mn
ln -sfn /etc/nginx/sites-available/demo.manage.dornogovi.gov.mn /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

cat > "${CONF_FILE}" <<CONF
SRC_DIR=${SRC_DIR}
DEPLOY_BRANCH=demo
WEB_ROOT=${WEB_ROOT}
WEB_USER=${WEB_USER}
PHP_FPM=${PHP_FPM}
DEPLOY_LOCK=/tmp/manage-demo-deploy.lock
DEPLOY_RUN_COPY=/tmp/manage-demo-deploy-run.sh
CONF_FILE=${CONF_FILE}
CONF

echo '*/2 * * * * root CONF_FILE=/etc/manage-dornogovi-demo.conf /opt/manage-dornogovi-demo/deploy/auto-update.sh >> /var/log/manage-demo-deploy.log 2>&1' \
    | tee /etc/cron.d/manage-demo-deploy
chmod 644 /etc/cron.d/manage-demo-deploy

echo
echo "==> Demo бэлэн: http://${APP_DOMAIN}"
echo "    Өгөгдлийн сан: ${DB_NAME} (production-оос тусдаа)"
echo "    DNS: ${APP_DOMAIN} → энэ серверийн IP"
echo "    Лог: /var/log/manage-demo-deploy.log"
