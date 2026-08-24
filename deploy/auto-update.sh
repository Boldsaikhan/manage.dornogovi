#!/usr/bin/env bash
#
# manage.dornogovi.gov.mn — автомат шинэчлэлт (cron-оор 2 мин тутам).
# GitHub-д шинэ commit байвал л татаж, дахин build хийнэ. root-оор ажиллана.
#
# Cron суулгах (нэг удаа):
#   echo '*/2 * * * * root /opt/manage-dornogovi/deploy/auto-update.sh >> /var/log/manage-deploy.log 2>&1' | sudo tee /etc/cron.d/manage-deploy
#
# ⚠️ Энэ скрипт seeder-ийг ДАХИН АЖИЛЛУУЛДАГГҮЙ — өгөгдлийн сан дахь хэрэглэгчийн
# засварыг (үүрэг чиглэлийн хэрэгжилт, хэлтэс гэх мэт) хадгална.
set -euo pipefail

SRC_DIR="/opt/manage-dornogovi"
BRANCH="main"
LOCK="/tmp/manage-deploy.lock"

# Веб root болон эзэмшигч нь тохиргооны файлаас уншигдана.
# CloudPanel руу шилжсэн үед /etc/manage-dornogovi.conf дотор дараах утгууд бичигдэнэ:
#   WEB_ROOT=/home/<site-user>/htdocs/manage.dornogovi.gov.mn
#   WEB_USER=<site-user>
#   PHP_FPM=php8.3-fpm
[ -f /etc/manage-dornogovi.conf ] && . /etc/manage-dornogovi.conf

WEB_ROOT="${WEB_ROOT:-/var/www/manage.dornogovi.gov.mn}"
WEB_USER="${WEB_USER:-www-data}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"

# Зэрэг ажиллахаас сэргийлнэ (өмнөх ажиллаж дуусаагүй бол чимээгүй гарна)
exec 9>"${LOCK}"
flock -n 9 || exit 0

git config --global --add safe.directory "${SRC_DIR}" 2>/dev/null || true

cd "${SRC_DIR}"
git fetch --quiet origin "${BRANCH}"

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse "origin/${BRANCH}")"

if [ "${LOCAL}" = "${REMOTE}" ]; then
    exit 0   # шинэ өөрчлөлт алга
fi

echo "==> $(date '+%F %T') шинэчлэл: ${LOCAL:0:7} -> ${REMOTE:0:7}"
git reset --hard --quiet "origin/${BRANCH}"

rsync -a --delete \
    --exclude '.git' --exclude '.github' --exclude 'node_modules' \
    --exclude '.env' --exclude 'storage/logs/*' --exclude 'public/build' \
    "${SRC_DIR}/" "${WEB_ROOT}/"

cd "${WEB_ROOT}"

composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent
npm run build --silent

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# rsync болон artisan-ыг root-оор ажиллуулсан тул файлууд root эзэмшилтэй үлддэг.
# PHP-FPM нь ${WEB_USER}-ээр ажилладаг тул storage/cache-д бичиж чадахгүй болж
# 500 алдаа өгнө. Тиймээс БҮХ веб root-ыг буцааж эзэмшүүлнэ.
chown -R "${WEB_USER}:${WEB_USER}" "${WEB_ROOT}"
find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \;

systemctl reload "${PHP_FPM}" 2>/dev/null || true
echo "==> $(date '+%F %T') дууслаа."
