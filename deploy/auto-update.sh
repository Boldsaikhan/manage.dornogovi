#!/usr/bin/env bash
#
# Автомат шинэчлэлт (cron-оор 2 мин тутам).
#
# Production: GitHub `main` — хэрэглэгчид ашиглаж буй сайт.
# Demo:       GitHub `demo` — хөгжүүлэлт. CONF_FILE=/etc/manage-dornogovi-demo.conf
#
# ⚠️ Seeder-ийг ДАХИН АЖИЛЛУУЛДАГГҮЙ.
set -euo pipefail

SRC_DIR="${SRC_DIR:-/opt/manage-dornogovi}"
BRANCH="${DEPLOY_BRANCH:-main}"
LOCK="${DEPLOY_LOCK:-/tmp/manage-deploy.lock}"
RUN_COPY="${DEPLOY_RUN_COPY:-/tmp/manage-deploy-run.sh}"
CONF_FILE="${CONF_FILE:-/etc/manage-dornogovi.conf}"

# Веб root болон эзэмшигч нь тохиргооны файлаас уншигдана.
#   WEB_ROOT, WEB_USER, PHP_FPM, DEPLOY_BRANCH, SRC_DIR, DEPLOY_LOCK
[ -f "${CONF_FILE}" ] && . "${CONF_FILE}"

SRC_DIR="${SRC_DIR:-/opt/manage-dornogovi}"
BRANCH="${DEPLOY_BRANCH:-${BRANCH:-main}}"
LOCK="${DEPLOY_LOCK:-${LOCK:-/tmp/manage-deploy.lock}}"
RUN_COPY="${DEPLOY_RUN_COPY:-${RUN_COPY:-/tmp/manage-deploy-run.sh}}"
WEB_ROOT="${WEB_ROOT:-/var/www/manage.dornogovi.gov.mn}"
WEB_USER="${WEB_USER:-www-data}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"

# Зэрэг ажиллахаас сэргийлнэ (өмнөх ажиллаж дуусаагүй бол чимээгүй гарна)
exec 9>"${LOCK}"
flock -n 9 || exit 0

# git reset --hard нь ЭНЭ скриптийг өөрийг нь дарж бичдэг тул ажиллаж байх зуур
# эх файл нь солигдож, bash завсраас нь буруу уншиж мэднэ. Тиймээс хуулбараасаа
# ажиллана (flock-ийн fd 9 exec хийсний дараа ч хэвээр үлдэнэ).
if [ -z "${DEPLOY_REEXEC:-}" ]; then
    cp -f "$0" "${RUN_COPY}"
    chmod +x "${RUN_COPY}"
    DEPLOY_REEXEC=1 exec "${RUN_COPY}" "$@"
fi

# Cron-ийн PATH ихэвчлэн богино байдаг.
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"

git config --global --add safe.directory "${SRC_DIR}" 2>/dev/null || true

cd "${SRC_DIR}"
git fetch --quiet origin "${BRANCH}"

LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse "origin/${BRANCH}")"

# DNS кэш: git шинэчлэл байхгүй үед ч 2 мин тутам цэвэрлэж дахин асууна.
DNS_REFRESH="${SRC_DIR}/deploy/dns-refresh.sh"
if [ -f "${DNS_REFRESH}" ]; then
    bash "${DNS_REFRESH}" >> /var/log/manage-dns-refresh.log 2>&1 || true
    # лог хэтэрвэл сүүлийн 400 мөрийг үлдээнэ
    if [ -f /var/log/manage-dns-refresh.log ]; then
        lines="$(wc -l < /var/log/manage-dns-refresh.log | tr -d ' ')"
        if [ "${lines:-0}" -gt 800 ]; then
            tail -n 400 /var/log/manage-dns-refresh.log > /var/log/manage-dns-refresh.log.tmp \
                && mv /var/log/manage-dns-refresh.log.tmp /var/log/manage-dns-refresh.log
        fi
    fi
fi

if [ "${LOCAL}" = "${REMOTE}" ]; then
    exit 0   # шинэ өөрчлөлт алга
fi

# rsync/composer/npm root-оор ажилладаг тул ямар ч алдаа гарсан ч эзэмшигчийг
# ${WEB_USER} руу буцаана — эс бөгөөс PHP-FPM storage-д бичиж чадахгүй, 500 өгнө.
fix_ownership() {
    if [ -d "${WEB_ROOT}" ]; then
        chown -R "${WEB_USER}:${WEB_USER}" "${WEB_ROOT}" 2>/dev/null || true
        find "${WEB_ROOT}/storage" "${WEB_ROOT}/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true
    fi
}
maintenance_off() {
    (cd "${WEB_ROOT}" 2>/dev/null && php artisan up >/dev/null 2>&1) || true
}

# Алдаа гарсан ч эзэмшигчийг буцааж, засварын горимоос гаргана.
trap 'fix_ownership; maintenance_off' EXIT

echo "==> $(date '+%F %T') [${BRANCH}] шинэчлэл: ${LOCAL:0:7} -> ${REMOTE:0:7}"
git checkout -q "${BRANCH}" 2>/dev/null || git checkout -q -B "${BRANCH}" "origin/${BRANCH}"
git reset --hard --quiet "origin/${BRANCH}"

# АСУУДЛААС СЭРГИЙЛЭХ: composer/npm буюу build нь 1-3 минут үргэлжилдэг тул
# тэдгээрийг сайтыг амьд байхад нь эхлээд эх хавтастаа бэлтгэнэ.
# Засварын горим зөвхөн rsync + migrate хугацаанд (хэдэн секунд) асна.
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --include=dev
npm run build

# Шинэчлэлтийн үед 500 биш, засварын хуудас харагдана.
(cd "${WEB_ROOT}" 2>/dev/null && php artisan down --retry=15 >/dev/null 2>&1) || true

# ⚠️ storage/ болон bootstrap/cache-ийг ХЭЗЭЭ Ч синк хийхгүй:
#   - storage/app дотор хэрэглэгчийн оруулсан файлууд байна (rsync --delete устгачихна),
#   - storage/framework, bootstrap/cache нь root эзэмшилтэй болбол PHP-FPM бичиж чадахгүй
#     (compiled view бичих гэж tempnam уначихаад бүх хуудас 500 өгдөг).
rsync -a --delete \
    --exclude '.git' --exclude '.github' --exclude 'node_modules' \
    --exclude '.env' --exclude 'storage' --exclude 'bootstrap/cache' \
    --exclude 'public/storage' \
    "${SRC_DIR}/" "${WEB_ROOT}/"

# rsync root-оор бичдэг тул эзэмшигчийг тэр дор нь буцаана.
fix_ownership

cd "${WEB_ROOT}"

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

fix_ownership
systemctl reload "${PHP_FPM}" 2>/dev/null || true
maintenance_off
echo "==> $(date '+%F %T') [${BRANCH}] дууслаа."
