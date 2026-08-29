#!/usr/bin/env bash
#
# manage.dornogovi.gov.mn — серверийн нэг удаагийн бүрэн тохиргоо.
# mcloud-ийн ВЭБ КОНСОЛД дараах нэг мөрийг буулгаж ажиллуулна:
#
#   curl -fsSL https://raw.githubusercontent.com/Boldsaikhan/manage.dornogovi/main/deploy/server-setup.sh | sudo bash
#
# Idempotent — дахин ажиллуулж болно. Ubuntu 24.04-т зориулсан (серверт хараахан ажиллуулж туршаагүй).
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/Boldsaikhan/manage.dornogovi.git}"
BRANCH="${BRANCH:-main}"
APP_DOMAIN="${APP_DOMAIN:-manage.dornogovi.gov.mn}"
SRC_DIR="/opt/manage-dornogovi"
WEB_ROOT="/var/www/${APP_DOMAIN}"
DB_NAME="manage_dornogovi"
DB_USER="manage_user"
PASS_FILE="/root/.manage_dornogovi_secrets"

log(){ echo -e "\n==> $*"; }

if [ "$(id -u)" -ne 0 ]; then
    echo "sudo-гээр ажиллуулна уу: curl … | sudo bash" >&2
    exit 1
fi

log "1/8 Багцууд"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y -qq
apt-get install -y -qq nginx mysql-server git curl unzip rsync \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl

log "2/8 Composer, Node.js 20"
if ! command -v composer >/dev/null; then
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null
    apt-get install -y -qq nodejs
fi

log "3/8 Эх код (${REPO_URL} @ ${BRANCH})"
if [ -d "${SRC_DIR}/.git" ]; then
    git -C "${SRC_DIR}" fetch --quiet origin "${BRANCH}"
    git -C "${SRC_DIR}" reset --hard --quiet "origin/${BRANCH}"
else
    git clone --quiet --branch "${BRANCH}" "${REPO_URL}" "${SRC_DIR}"
fi

log "4/8 Өгөгдлийн сан"
systemctl enable --now mysql >/dev/null 2>&1 || true
if [ -f "${PASS_FILE}" ]; then
    # shellcheck disable=SC1090
    . "${PASS_FILE}"
else
    DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
    ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)"
    printf 'DB_PASS=%s\nADMIN_PASSWORD=%s\n' "${DB_PASS}" "${ADMIN_PASSWORD}" > "${PASS_FILE}"
    chmod 600 "${PASS_FILE}"
fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

log "5/8 Кодыг байрлуулах"
mkdir -p "${WEB_ROOT}"
rsync -a --delete \
    --exclude '.git' --exclude '.github' --exclude 'node_modules' \
    --exclude '.env' --exclude 'storage/logs/*' \
    "${SRC_DIR}/" "${WEB_ROOT}/"

cd "${WEB_ROOT}"

if [ ! -f .env ]; then
    cp deploy/env.production .env
    sed -i "s#^APP_URL=.*#APP_URL=http://${APP_DOMAIN}#" .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
    # HTTPS тохируулах хүртэл secure cookie унтраалттай, эс бөгөөс нэвтрэлт ажиллахгүй
    sed -i "s/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=false/" .env
    sed -i "s/^SESSION_DOMAIN=.*/SESSION_DOMAIN=null/" .env
    printf 'ADMIN_PASSWORD=%s\n' "${ADMIN_PASSWORD}" >> .env
fi

log "6/8 Хамаарал ба build"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent
npm run build --silent

log "7/8 Laravel"
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
# Seeder-ийг зөвхөн АНХНЫ удаад — дахин ажиллуулахад хэрэглэгчийн засвар
# (үүрэг чиглэлийн хэрэгжилт, хэлтэс, нэвтрэх мэдээлэл) устахаас сэргийлнэ.
TASK_COUNT="$(php artisan tinker --execute='echo App\Models\Task::count();' 2>/dev/null | tail -1 | tr -dc '0-9')"
if [ "${TASK_COUNT:-0}" = "0" ]; then
    php artisan db:seed --class=SystemSeeder --force
    php artisan db:seed --class=TaskSeeder --force
    php artisan db:seed --class=AdminUserSeeder --force
else
    echo "    (өгөгдөл аль хэдийн байгаа тул seeder алгасав — ${TASK_COUNT} ажил)"
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

chown -R www-data:www-data "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache"
find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \;

log "8/8 Nginx"
cp deploy/nginx.conf "/etc/nginx/sites-available/${APP_DOMAIN}"
sed -i "s#/var/www/manage.dornogovi.gov.mn#${WEB_ROOT}#g" \
    "/etc/nginx/sites-available/${APP_DOMAIN}"
ln -sf "/etc/nginx/sites-available/${APP_DOMAIN}" "/etc/nginx/sites-enabled/${APP_DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now php8.3-fpm nginx >/dev/null 2>&1 || true
systemctl reload nginx

IP="$(hostname -I | awk '{print $1}')"
echo
echo "======================================================="
echo " ДУУСЛАА"
echo "-------------------------------------------------------"
echo " Хаяг      : http://${APP_DOMAIN}/   (эсвэл http://${IP}/)"
echo " Нэвтрэх   : $(grep -m1 '^ADMIN_EMAIL' .env | cut -d= -f2 || echo 'admin@dornogovi.gov.mn')"
echo " Нууц үг   : ${ADMIN_PASSWORD}"
echo " Нууц үгс  : ${PASS_FILE} дотор хадгалагдсан"
echo "-------------------------------------------------------"
curl -s -o /dev/null -w " Шалгалт   : localhost -> HTTP %{http_code}\n" http://localhost/ || true
echo " Ажил      : $(php artisan tinker --execute='echo App\Models\Task::count();' 2>/dev/null | tail -1) үүрэг, чиглэл сийрүүлэгдсэн"
echo "======================================================="
echo
echo "Шинэчлэх бол дахин ижил коммандыг ажиллуулна."
