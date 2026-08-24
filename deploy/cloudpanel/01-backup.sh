#!/usr/bin/env bash
#
# CloudPanel суулгахаас ӨМНӨ заавал ажиллуулна.
# Өгөгдлийн сан, .env, нууц үг, кодыг бүгдийг нэг архивт нөөцөлнө.
#
#   sudo bash 01-backup.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "sudo-гээр ажиллуулна уу"; exit 1; }

STAMP="$(date '+%Y%m%d-%H%M%S')"
BK="/root/manage-backup-${STAMP}"
WEB_ROOT="/var/www/manage.dornogovi.gov.mn"
DB_NAME="manage_dornogovi"

mkdir -p "${BK}"

echo "==> 1/4 Өгөгдлийн сан"
mysqldump --single-transaction --routines --triggers "${DB_NAME}" > "${BK}/${DB_NAME}.sql"
echo "    $(wc -c < "${BK}/${DB_NAME}.sql") байт"

echo "==> 2/4 Тохиргоо ба нууц үгс"
cp "${WEB_ROOT}/.env"                  "${BK}/env" 2>/dev/null || echo "    (.env олдсонгүй)"
cp /root/.manage_dornogovi_secrets     "${BK}/secrets" 2>/dev/null || echo "    (secrets олдсонгүй)"
cp /etc/cron.d/manage-deploy           "${BK}/cron-manage-deploy" 2>/dev/null || true
cp -r /etc/nginx/sites-available       "${BK}/nginx-sites-available" 2>/dev/null || true

echo "==> 3/4 Хэрэглэгчийн байршуулсан файлууд"
tar -czf "${BK}/storage-app.tar.gz" -C "${WEB_ROOT}" storage/app 2>/dev/null || true

echo "==> 4/4 Архивлаж байна"
tar -czf "${BK}.tar.gz" -C /root "$(basename "${BK}")"
rm -rf "${BK}"
chmod 600 "${BK}.tar.gz"

echo
echo "======================================================="
echo " НӨӨЦЛӨЛТ БЭЛЭН"
echo " Файл : ${BK}.tar.gz  ($(du -h "${BK}.tar.gz" | cut -f1))"
echo "======================================================="
echo
echo "ЗААВАЛ хийх 2 зүйл:"
echo "  1. mcloud консол дээр 'Snapshot нөөцлөлт' үүсгэ — буцаах цорын ганц баталгаа"
echo "  2. Энэ архивыг өөрийн компьютер дээрээ хуулж ав:"
echo "       scp manage-dornogovi:${BK}.tar.gz ."
echo "     (эсвэл: sudo cp ${BK}.tar.gz /home/ndc-user/ && sudo chown ndc-user /home/ndc-user/$(basename "${BK}").tar.gz)"
echo
echo "Энэ хоёрыг хийсний дараа л 02-install-cloudpanel.sh ажиллуулна."
