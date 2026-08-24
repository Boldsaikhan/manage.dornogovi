#!/usr/bin/env bash
#
# CloudPanel дээр сайт болон өгөгдлийн сан үүсгэсний ДАРАА ажиллуулна.
# Кодыг байрлуулж, нөөцлөлтөөс өгөгдлийг сэргээж, автомат шинэчлэлтийг сэргээнэ.
#
#   sudo bash 03-restore-site.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "sudo-гээр ажиллуулна уу"; exit 1; }

APP_DOMAIN="manage.dornogovi.gov.mn"
SRC_DIR="/opt/manage-dornogovi"
DB_NAME="manage_dornogovi"
DB_USER="manage_user"

# ------------------------------------------------ 1. Сайтын хэрэглэгчийг олох
echo "==> 1/7 CloudPanel-ийн сайтыг хайж байна"
WEB_ROOT="$(find /home -maxdepth 3 -type d -name "${APP_DOMAIN}" -path '*/htdocs/*' 2>/dev/null | head -1)"
[ -n "${WEB_ROOT}" ] || {
    echo "ЗОГСЛОО: CloudPanel дээр '${APP_DOMAIN}' сайт олдсонгүй."
    echo "Эхлээд Sites → Add Site → PHP Site гэж үүсгэнэ үү."
    exit 1
}
WEB_USER="$(stat -c '%U' "${WEB_ROOT}")"
echo "    Веб root : ${WEB_ROOT}"
echo "    Хэрэглэгч: ${WEB_USER}"

# ------------------------------------------------------- 2. Нөөцлөлт задлах
echo "==> 2/7 Нөөцлөлтийг задалж байна"
BACKUP="$(ls -t /root/manage-backup-*.tar.gz 2>/dev/null | head -1 || true)"
[ -n "${BACKUP}" ] || { echo "ЗОГСЛОО: нөөцлөлт олдсонгүй."; exit 1; }
WORK="$(mktemp -d)"
tar -xzf "${BACKUP}" -C "${WORK}"
BK="$(find "${WORK}" -maxdepth 1 -type d -name 'manage-backup-*')"
echo "    ${BACKUP}"

# ----------------------------------------------------- 3. Кодыг байрлуулах
echo "==> 3/7 Кодыг байрлуулж байна"
apt-get install -y -qq rsync git
[ -d "${SRC_DIR}/.git" ] || git clone --quiet https://github.com/Boldsaikhan/manage.dornogovi.git "${SRC_DIR}"
git -C "${SRC_DIR}" fetch --quiet origin main
git -C "${SRC_DIR}" reset --hard --quiet origin/main

rsync -a --delete \
    --exclude '.git' --exclude '.github' --exclude 'node_modules' \
    --exclude '.env' --exclude 'storage/logs/*' \
    "${SRC_DIR}/" "${WEB_ROOT}/"

# -------------------------------------------------------------- 4. .env
echo "==> 4/7 .env сэргээж байна"
if [ -f "${BK}/env" ]; then
    cp "${BK}/env" "${WEB_ROOT}/.env"
else
    cp "${WEB_ROOT}/deploy/env.production" "${WEB_ROOT}/.env"
fi

echo
echo "CloudPanel дээр өгөгдлийн сан үүсгэхдээ өгсөн нууц үгээ оруулна уу."
read -rsp "  ${DB_USER}-ийн нууц үг: " DB_PASS; echo

sed -i "s#^APP_URL=.*#APP_URL=http://${APP_DOMAIN}#"       "${WEB_ROOT}/.env"
sed -i "s#^DB_DATABASE=.*#DB_DATABASE=${DB_NAME}#"         "${WEB_ROOT}/.env"
sed -i "s#^DB_USERNAME=.*#DB_USERNAME=${DB_USER}#"         "${WEB_ROOT}/.env"
sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASS}#"         "${WEB_ROOT}/.env"
sed -i "s#^SESSION_SECURE_COOKIE=.*#SESSION_SECURE_COOKIE=false#" "${WEB_ROOT}/.env"
sed -i "s#^SESSION_DOMAIN=.*#SESSION_DOMAIN=null#"         "${WEB_ROOT}/.env"

# --------------------------------------------------- 5. Өгөгдлийн сан сэргээх
echo "==> 5/7 Өгөгдлийн санг сэргээж байна"
mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${BK}/${DB_NAME}.sql"
echo "    OK"

[ -f "${BK}/storage-app.tar.gz" ] && tar -xzf "${BK}/storage-app.tar.gz" -C "${WEB_ROOT}"
[ -f "${BK}/secrets" ] && { cp "${BK}/secrets" /root/.manage_dornogovi_secrets; chmod 600 /root/.manage_dornogovi_secrets; }

# ------------------------------------------------------------- 6. Build
echo "==> 6/7 Хамаарал ба build"
command -v composer >/dev/null || {
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
}
command -v node >/dev/null || { curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null; apt-get install -y -qq nodejs; }

cd "${WEB_ROOT}"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent
npm run build --silent

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

chown -R "${WEB_USER}:${WEB_USER}" "${WEB_ROOT}"
find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \;

# ----------------------------------------- 7. Автомат шинэчлэлтийг сэргээх
echo "==> 7/7 Автомат шинэчлэлтийг сэргээж байна"
PHP_FPM="$(systemctl list-units --type=service --plain --no-legend 'php*-fpm.service' | awk '{print $1}' | head -1)"
PHP_FPM="${PHP_FPM:-php8.3-fpm.service}"

cat > /etc/manage-dornogovi.conf <<CONF
# auto-update.sh эндээс замуудаа уншина (CloudPanel-ийн бүтэц)
WEB_ROOT=${WEB_ROOT}
WEB_USER=${WEB_USER}
PHP_FPM=${PHP_FPM%.service}
CONF

echo '*/2 * * * * root /opt/manage-dornogovi/deploy/auto-update.sh >> /var/log/manage-deploy.log 2>&1' \
    > /etc/cron.d/manage-deploy

rm -rf "${WORK}"

IP="$(hostname -I | awk '{print $1}')"
cat <<INFO

=========================================================================
 ШИЛЖИЛТ ДУУСЛАА
-------------------------------------------------------------------------
 Апп        : http://${APP_DOMAIN}/   (эсвэл http://${IP}/)
 CloudPanel : https://${IP}:8443
 Веб root   : ${WEB_ROOT}
 Cron       : /etc/cron.d/manage-deploy (2 мин тутам, идэвхтэй)
 Тохиргоо   : /etc/manage-dornogovi.conf
=========================================================================

 Шалгах:
   curl -s -o /dev/null -w '%{http_code}\n' http://localhost/
   sudo tail -f /var/log/manage-deploy.log

 Админаар нэвтрэх нууц үг нөөцлөлтөөс хэвээр — /root/.manage_dornogovi_secrets

INFO
