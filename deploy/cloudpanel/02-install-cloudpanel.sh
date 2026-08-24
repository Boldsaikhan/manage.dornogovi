#!/usr/bin/env bash
#
# CloudPanel CE v2-г суулгана (Ubuntu 24.04).
#
# ⚠️ ЭНЭ СКРИПТ ОДООГИЙН nginx, MySQL, PHP-Г УСТГАНА.
#    CloudPanel өөрийн хувилбаруудыг суулгадаг тул зэрэгцэн ажиллах боломжгүй.
#    01-backup.sh ажиллуулж, mcloud дээр snapshot авсны ДАРАА л ажиллуулна.
#
#   sudo bash 02-install-cloudpanel.sh
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "sudo-гээр ажиллуулна уу"; exit 1; }

# ------------------------------------------------- Нөөцлөлт байгаа эсэхийг шалгах
BACKUP="$(ls -t /root/manage-backup-*.tar.gz 2>/dev/null | head -1 || true)"
if [ -z "${BACKUP}" ]; then
    echo "ЗОГСЛОО: /root дотор нөөцлөлт олдсонгүй."
    echo "Эхлээд ажиллуул: sudo bash 01-backup.sh"
    exit 1
fi
echo "Нөөцлөлт: ${BACKUP} ($(du -h "${BACKUP}" | cut -f1), $(date -r "${BACKUP}" '+%F %T'))"

cat <<'WARN'

=========================================================================
 АНХААРУУЛГА
-------------------------------------------------------------------------
 Дараах зүйлс УСТАНА:
   • одоогийн nginx болон түүний бүх тохиргоо
   • MySQL сервер ба өгөгдлийн сан (нөөцлөлтөөс сэргээнэ)
   • php8.3-fpm ба түүний тохиргоо
   • /var/www/manage.dornogovi.gov.mn веб root

 Систем 15-30 минут ажиллахгүй байна.
 Буруудвал mcloud snapshot-оос буцаана.
=========================================================================

WARN
read -rp "Үргэлжлүүлэх бол 'ТИЙМ' гэж бичнэ үү: " ok
[ "${ok}" = "ТИЙМ" ] || { echo "Цуцаллаа."; exit 1; }

# ------------------------------------------------------ 1. Cron-ыг зогсоох
echo "==> 1/4 Автомат шинэчлэлтийг түр зогсоож байна"
rm -f /etc/cron.d/manage-deploy

# ------------------------------------------------- 2. Хуучин стекийг арилгах
echo "==> 2/4 Хуучин nginx / MySQL / PHP-г устгаж байна"
systemctl stop nginx mysql php8.3-fpm 2>/dev/null || true
export DEBIAN_FRONTEND=noninteractive
apt-get purge -y 'nginx*' 'mysql-server*' 'mysql-client*' 'mysql-common' 'php8.3*' 2>/dev/null || true
apt-get autoremove -y
rm -rf /etc/nginx /etc/php /var/lib/mysql /etc/mysql
rm -rf /var/www/manage.dornogovi.gov.mn
# /opt/manage-dornogovi (git эх код) ХЭВЭЭР үлдэнэ — 03-restore-site.sh эндээс байрлуулна

# --------------------------------------------- 3. CloudPanel суулгах бэлтгэл
echo "==> 3/4 CloudPanel татаж байна"
apt-get update -y
apt-get install -y curl wget sudo

cd /tmp
rm -f install.sh
curl -sS https://installer.cloudpanel.io/ce/v2/install.sh -o install.sh

echo
echo "Татсан суулгагчийн SHA256:"
sha256sum install.sh
echo
echo "Дээрх утгыг https://www.cloudpanel.io/docs/v2/getting-started/debian/"
echo "хуудсан дээрх утгатай ЗААВАЛ тулгаж шалгана уу."
read -rp "Таарч байвал 'ok' гэж бич: " chk
[ "${chk}" = "ok" ] || { echo "Цуцаллаа. Сервер одоогоор веб сервергүй байна — snapshot-оос буцаа."; exit 1; }

# ------------------------------------------------------ 4. Суулгах
echo "==> 4/4 CloudPanel суулгаж байна (10-20 минут)"
DB_ENGINE=MARIADB_11.4 bash install.sh

IP="$(hostname -I | awk '{print $1}')"
cat <<INFO

=========================================================================
 CLOUDPANEL СУУЛГАГДЛАА
-------------------------------------------------------------------------
 Хаяг : https://${IP}:8443
        (өөрөө гарын үсэг зурсан гэрчилгээ — 'Advanced → Proceed')
        VPN-д холбогдсон байх шаардлагатай.

 Эхний удаа нэвтрэхэд админ хэрэглэгч үүсгэнэ — нууц үгээ хадгал.
=========================================================================

 ДАРААГИЙН АЛХАМ — CloudPanel дотор гараар:

   1. Sites → Add Site → "Create a PHP Site"
        Domain Name  : manage.dornogovi.gov.mn
        PHP Version  : 8.3
        Site User    : manage
        (нууц үгээ тэмдэглэж ав)

   2. Databases → Add Database
        Database Name : manage_dornogovi
        User Name     : manage_user
        (нууц үгээ тэмдэглэж ав)

   3. Дараа нь консол дээр:
        sudo bash /opt/manage-dornogovi/deploy/cloudpanel/03-restore-site.sh

INFO
