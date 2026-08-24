#!/usr/bin/env bash
#
# CloudPanel руу БҮРЭН АВТОМАТ шилжилт — нэг команд.
#
#   sudo bash /opt/manage-dornogovi/deploy/cloudpanel/install-all.sh
#
# Хийх зүйлс: нөөцлөх -> хуучин стек устгах -> CloudPanel суулгах ->
#             админ/сайт/DB үүсгэх -> өгөгдөл сэргээх -> cron сэргээх
#
# ⚠️ УРЬДЧИЛЖ mcloud консол дээр SNAPSHOT АВСАН БАЙХ ЁСТОЙ.
#    Буруудвал буцаах өөр арга байхгүй.
#
# Хувьсагчид (заавал биш):
#   ADMIN_EMAIL=...        CloudPanel админы и-мэйл (анхдагч: it@dornogovi.gov.mn)
#   EXPECTED_SHA256=...    суулгагчийн hash-ыг хатуу шалгах
#   ASSUME_YES=1           баталгаажуулалт асуухгүй
set -euo pipefail

APP_DOMAIN="manage.dornogovi.gov.mn"
SRC_DIR="/opt/manage-dornogovi"
OLD_WEB_ROOT="/var/www/${APP_DOMAIN}"
DB_NAME="manage_dornogovi"
DB_USER="manage_user"
SITE_USER="manage"
CP_ADMIN="admin"
ADMIN_EMAIL="${ADMIN_EMAIL:-it@dornogovi.gov.mn}"
SECRETS="/root/.manage_dornogovi_secrets"
NEW_SECRETS="/root/.cloudpanel_secrets"

log(){ echo -e "\n\033[1;36m==> $*\033[0m"; }
die(){ echo -e "\n\033[1;31mЗОГСЛОО: $*\033[0m" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "sudo-гээр ажиллуулна уу"
[ -f /etc/os-release ] && . /etc/os-release
[ "${VERSION_ID:-}" = "24.04" ] || echo "АНХААР: Ubuntu 24.04 биш байна (${VERSION_ID:-?}) — үргэлжлүүлж байна"

# Нууц үг үүсгэгч (CloudPanel: 8+ тэмдэгт, том/жижиг үсэг, тоо, тусгай тэмдэгт)
genpass(){ echo "$(openssl rand -base64 18 | tr -dc 'A-Za-z0-9' | cut -c1-14)aA1!"; }

if [ "${ASSUME_YES:-0}" != "1" ]; then
    echo ""
    echo "========================================================================="
    echo " Дараах зүйлс УСТАНА: одоогийн nginx, MySQL (өгөгдлийн сан), php8.3-fpm,"
    echo " /var/www/manage.dornogovi.gov.mn.  Систем 20-40 минут ажиллахгүй."
    echo " Өгөгдлийг нөөцөлж буцаан сэргээнэ, гэхдээ snapshot байхгүй бол эрсдэлтэй."
    echo "========================================================================="
    echo ""
    echo " Консол кирилл дэмжихгүй бол латинаар YES гэж бичиж болно."
    read -rp "mcloud дээр snapshot АВСАН уу? Тийм бол 'ТИЙМ' эсвэл 'YES' гэж бич: " ok
    ok="$(printf '%s' "${ok}" | tr '[:lower:]' '[:upper:]')"
    case "${ok}" in
        "ТИЙМ"|YES|Y|TIIM|TIIM.) ;;
        *) die "Эхлээд snapshot аваарай. (эсвэл ASSUME_YES=1 хувьсагчтай ажиллуул)" ;;
    esac
fi

################################################################# 1. НӨӨЦЛӨХ
log "1/7 Нөөцлөж байна"
STAMP="$(date '+%Y%m%d-%H%M%S')"
BK="/root/manage-backup-${STAMP}"
mkdir -p "${BK}"

if command -v mysqldump >/dev/null && systemctl is-active --quiet mysql; then
    mysqldump --single-transaction --routines --triggers "${DB_NAME}" > "${BK}/${DB_NAME}.sql"
    echo "    DB: $(wc -c < "${BK}/${DB_NAME}.sql") байт"
else
    echo "    АНХААР: MySQL ажиллахгүй байна — DB нөөцлөгдсөнгүй"
fi

cp "${OLD_WEB_ROOT}/.env" "${BK}/env"      2>/dev/null || echo "    (.env олдсонгүй)"
cp "${SECRETS}"           "${BK}/secrets"  2>/dev/null || true
tar -czf "${BK}/storage-app.tar.gz" -C "${OLD_WEB_ROOT}" storage/app 2>/dev/null || true

tar -czf "${BK}.tar.gz" -C /root "$(basename "${BK}")"
rm -rf "${BK}"
chmod 600 "${BK}.tar.gz"
echo "    Архив: ${BK}.tar.gz ($(du -h "${BK}.tar.gz" | cut -f1))"

# Ажлын хуулбарыг задалж үлдээнэ (сэргээхэд хэрэгтэй)
WORK="$(mktemp -d)"
tar -xzf "${BK}.tar.gz" -C "${WORK}"
BKDIR="$(find "${WORK}" -maxdepth 1 -type d -name 'manage-backup-*')"

########################################################## 2. ХУУЧИН СТЕК УСТГАХ
log "2/7 Хуучин nginx / MySQL / PHP-г устгаж байна"
rm -f /etc/cron.d/manage-deploy
systemctl stop nginx mysql php8.3-fpm 2>/dev/null || true
export DEBIAN_FRONTEND=noninteractive
apt-get purge -y 'nginx*' 'mysql-server*' 'mysql-client*' mysql-common 'php8.3*' >/dev/null 2>&1 || true
apt-get autoremove -y >/dev/null 2>&1 || true
rm -rf /etc/nginx /etc/php /var/lib/mysql /etc/mysql "${OLD_WEB_ROOT}"
# /opt/manage-dornogovi (git эх код) ХЭВЭЭР үлдэнэ

################################################### 3. CLOUDPANEL СУУЛГАХ
log "3/7 CloudPanel суулгаж байна (10-20 минут)"
apt-get update -y >/dev/null
apt-get install -y curl wget sudo openssl >/dev/null

cd /tmp && rm -f install.sh
curl -fsS https://installer.cloudpanel.io/ce/v2/install.sh -o install.sh || die "суулгагч татагдсангүй"
GOT="$(sha256sum install.sh | awk '{print $1}')"
echo "    Суулгагчийн SHA256: ${GOT}"
if [ -n "${EXPECTED_SHA256:-}" ]; then
    [ "${GOT}" = "${EXPECTED_SHA256}" ] || die "SHA256 таарсангүй (хүлээсэн: ${EXPECTED_SHA256})"
    echo "    ✅ hash баталгаажлаа"
else
    echo "    (hash хатуу шалгаагүй — HTTPS-ээр татсан. Шалгах бол EXPECTED_SHA256=... өгнө)"
fi

DB_ENGINE=MARIADB_11.4 bash install.sh || die "CloudPanel суулгалт амжилтгүй — snapshot-оос буцаа"
command -v clpctl >/dev/null || die "clpctl олдсонгүй — суулгалт бүрэн болоогүй"

############################################ 4. АДМИН / САЙТ / DB ҮҮСГЭХ
log "4/7 Админ, сайт, өгөгдлийн сан үүсгэж байна"
CP_PASS="$(genpass)"; SITE_PASS="$(genpass)"; DB_PASS="$(genpass)"

clpctl user:add --userName="${CP_ADMIN}" --email="${ADMIN_EMAIL}" \
    --firstName="Dornogovi" --lastName="Admin" \
    --password="${CP_PASS}" --role="admin" --timezone="Asia/Ulaanbaatar" \
    --status="1" 2>/dev/null || echo "    (админ аль хэдийн байна)"

# Laravel-ийн vhost загвар байвал ашиглана, үгүй бол Generic + root засна
if ! clpctl site:add:php --domainName="${APP_DOMAIN}" --phpVersion="8.3" \
        --vhostTemplate="Laravel" --siteUser="${SITE_USER}" \
        --siteUserPassword="${SITE_PASS}" 2>/dev/null; then
    echo "    Laravel загвар алга — Generic-ээр үүсгэж root-ыг гараар зална"
    clpctl site:add:php --domainName="${APP_DOMAIN}" --phpVersion="8.3" \
        --vhostTemplate="Generic" --siteUser="${SITE_USER}" \
        --siteUserPassword="${SITE_PASS}" || die "сайт үүсгэж чадсангүй"
    VHOST="/etc/nginx/sites-enabled/${APP_DOMAIN}.conf"
    [ -f "${VHOST}" ] && sed -i "s#htdocs/${APP_DOMAIN};#htdocs/${APP_DOMAIN}/public;#g" "${VHOST}"
fi

clpctl db:add --domainName="${APP_DOMAIN}" --databaseName="${DB_NAME}" \
    --databaseUserName="${DB_USER}" --databaseUserPassword="${DB_PASS}" \
    || die "өгөгдлийн сан үүсгэж чадсангүй"

WEB_ROOT="/home/${SITE_USER}/htdocs/${APP_DOMAIN}"
[ -d "${WEB_ROOT}" ] || die "веб root олдсонгүй: ${WEB_ROOT}"

########################################################## 5. КОД БАЙРЛУУЛАХ
log "5/7 Код байрлуулж, өгөгдөл сэргээж байна"
apt-get install -y -qq rsync git >/dev/null
git config --global --add safe.directory "${SRC_DIR}" 2>/dev/null || true
git -C "${SRC_DIR}" fetch --quiet origin main && git -C "${SRC_DIR}" reset --hard --quiet origin/main

rsync -a --delete --exclude '.git' --exclude '.github' --exclude 'node_modules' \
    --exclude '.env' --exclude 'storage/logs/*' "${SRC_DIR}/" "${WEB_ROOT}/"

if [ -f "${BKDIR}/env" ]; then
    cp "${BKDIR}/env" "${WEB_ROOT}/.env"
else
    cp "${WEB_ROOT}/deploy/env.production" "${WEB_ROOT}/.env"
fi

sed -i "s#^APP_URL=.*#APP_URL=http://${APP_DOMAIN}#"              "${WEB_ROOT}/.env"
sed -i "s#^DB_DATABASE=.*#DB_DATABASE=${DB_NAME}#"                "${WEB_ROOT}/.env"
sed -i "s#^DB_USERNAME=.*#DB_USERNAME=${DB_USER}#"                "${WEB_ROOT}/.env"
sed -i "s#^DB_PASSWORD=.*#DB_PASSWORD=${DB_PASS}#"                "${WEB_ROOT}/.env"
sed -i "s#^SESSION_SECURE_COOKIE=.*#SESSION_SECURE_COOKIE=false#" "${WEB_ROOT}/.env"
sed -i "s#^SESSION_DOMAIN=.*#SESSION_DOMAIN=null#"                "${WEB_ROOT}/.env"

if [ -f "${BKDIR}/${DB_NAME}.sql" ]; then
    mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${BKDIR}/${DB_NAME}.sql" \
        && echo "    ✅ өгөгдлийн сан сэргээгдлээ"
fi
[ -f "${BKDIR}/storage-app.tar.gz" ] && tar -xzf "${BKDIR}/storage-app.tar.gz" -C "${WEB_ROOT}" || true
[ -f "${BKDIR}/secrets" ] && { cp "${BKDIR}/secrets" "${SECRETS}"; chmod 600 "${SECRETS}"; }

############################################################## 6. BUILD
log "6/7 Хамаарал ба build"
command -v composer >/dev/null || {
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
}
command -v node >/dev/null || {
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null
    apt-get install -y -qq nodejs >/dev/null
}

cd "${WEB_ROOT}"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent && npm run build --silent

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
TASK_COUNT="$(php artisan tinker --execute='echo App\Models\Task::count();' 2>/dev/null | tail -1 | tr -dc '0-9')"
if [ "${TASK_COUNT:-0}" = "0" ]; then
    php artisan db:seed --class=SystemSeeder --force
    php artisan db:seed --class=TaskSeeder --force
    php artisan db:seed --class=AdminUserSeeder --force
fi
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

chown -R "${SITE_USER}:${SITE_USER}" "${WEB_ROOT}"
find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \;

################################################### 7. CRON СЭРГЭЭХ
log "7/7 Автомат шинэчлэлтийг сэргээж байна"
PHP_FPM="$(systemctl list-units --type=service --plain --no-legend 'php*-fpm.service' | awk '{print $1}' | head -1)"
{
    echo "WEB_ROOT=${WEB_ROOT}"
    echo "WEB_USER=${SITE_USER}"
    echo "PHP_FPM=${PHP_FPM%.service}"
} > /etc/manage-dornogovi.conf

echo '*/2 * * * * root /opt/manage-dornogovi/deploy/auto-update.sh >> /var/log/manage-deploy.log 2>&1' \
    > /etc/cron.d/manage-deploy

systemctl reload nginx 2>/dev/null || true
rm -rf "${WORK}"

IP="$(hostname -I | awk '{print $1}')"

# Нууц үгсийг хадгалах
{
    echo "CLOUDPANEL_URL=https://${IP}:8443"
    echo "CLOUDPANEL_USER=${CP_ADMIN}"
    echo "CLOUDPANEL_PASSWORD=${CP_PASS}"
    echo "SITE_USER=${SITE_USER}"
    echo "SITE_USER_PASSWORD=${SITE_PASS}"
    echo "DB_NAME=${DB_NAME}"
    echo "DB_USER=${DB_USER}"
    echo "DB_PASSWORD=${DB_PASS}"
} > "${NEW_SECRETS}"
chmod 600 "${NEW_SECRETS}"

HTTP="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${APP_DOMAIN}" http://localhost/ || echo 000)"

echo ""
echo "========================================================================="
echo " ДУУСЛАА"
echo "-------------------------------------------------------------------------"
echo " CloudPanel : https://${IP}:8443"
echo "   Нэвтрэх  : ${CP_ADMIN}"
echo "   Нууц үг  : ${CP_PASS}"
echo ""
echo " Апп        : http://${APP_DOMAIN}/   (шалгалт: HTTP ${HTTP})"
echo " Веб root   : ${WEB_ROOT}"
echo ""
echo " Сайтын хэрэглэгч ${SITE_USER} : ${SITE_PASS}"
echo " DB ${DB_USER}                 : ${DB_PASS}"
echo "-------------------------------------------------------------------------"
echo " Нууц үгс   : ${NEW_SECRETS}"
echo " Нөөцлөлт   : ${BK}.tar.gz"
echo " Cron       : 2 мин тутам, идэвхтэй"
echo "========================================================================="
echo ""
echo " ЭДГЭЭР НУУЦ ҮГИЙГ ОДОО ХАДГАЛЖ АВНА УУ."
echo " Аппын админаар нэвтрэх нууц үг өмнөх хэвээр — ${SECRETS}"
echo ""
