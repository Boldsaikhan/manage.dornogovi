#!/usr/bin/env bash
# manage.dornogovi.gov.mn-г ИНТЕРНЭТЭД нээх скрипт.
#
# УРЬДЧИЛСАН НӨХЦӨЛ (энэ 2 зүйл БИЕЛСЭН байх ёстой, эс тэгвээс скрипт зогсоно):
#   1. НДТ серверт public IP хуваарилж, 80/443 портыг нээсэн байх
#   2. gov.mn DNS дээр manage.dornogovi.gov.mn -> тэр public IP руу зассан байх
#
# Ажиллуулах:
#   sudo CERTBOT_EMAIL=admin@dornogovi.gov.mn bash go-public.sh
set -euo pipefail

# БҮТЭЦ (server-setup.sh-ийн үүсгэсэн):
#   /opt/manage-dornogovi              — git эх код (cron эндээс татна)
#   /var/www/manage.dornogovi.gov.mn   — ажиллаж буй веб root (rsync-ээр хуулагдана)
#   SSH: ndc-user@10.52.1.67
#
# ⚠️ Энэ скриптийг ажиллуулсны дараа server-setup.sh-г ДАХИН ажиллуулбал nginx-ийн
#    тохиргоо дарагдаж SSL унтарна. Тэр тохиолдолд go-public.sh-г дахин ажиллуулна
#    (certbot гэрчилгээг хадгалсан байх тул хормын дотор сэргэнэ).

# ⚠️ CloudPanel руу шилжсэн бол ЭНЭ СКРИПТИЙГ БҮҮ АЖИЛЛУУЛ — nginx-ийг шууд заддаг
#    тул CloudPanel-ийн тохиргоог эвднэ. Тэр тохиолдолд SSL-г CloudPanel-ийн UI
#    дотроос (Sites -> SSL/TLS) тохируулна.

APP_DOMAIN="manage.dornogovi.gov.mn"
SRC_DIR="/opt/manage-dornogovi"
APP_DIR="/var/www/${APP_DOMAIN}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

[ "$(id -u)" -eq 0 ] || { echo "root эрхээр ажиллуулна: sudo bash go-public.sh"; exit 1; }
: "${CERTBOT_EMAIL:?CERTBOT_EMAIL тохируулна уу (гэрчилгээ дуусах сануулга ирэх и-мэйл)}"

# ---------------------------------------------------------------- 1. DNS шалгах
echo "==> DNS шалгаж байна"
RESOLVED="$(getent ahostsv4 "${APP_DOMAIN}" | awk 'NR==1{print $1}' || true)"
PUBLIC_IP="$(curl -fsS --max-time 10 https://api.ipify.org || true)"

echo "    ${APP_DOMAIN} -> ${RESOLVED:-<олдсонгүй>}"
echo "    Серверийн гадаад IP -> ${PUBLIC_IP:-<тодорхойлж чадсангүй>}"

case "${RESOLVED}" in
    10.*|192.168.*|172.1[6-9].*|172.2[0-9].*|172.3[01].*|127.*|"")
        echo
        echo "ЗОГСЛОО: домайн дотоод (private) хаяг руу заасан хэвээр байна."
        echo "gov.mn DNS админаар A бичлэгийг public IP руу заалгасны дараа дахин ажиллуулна уу."
        exit 1
        ;;
esac

WWW_DOMAIN="www.${APP_DOMAIN}"
WWW_RESOLVED="$(getent ahostsv4 "${WWW_DOMAIN}" | awk 'NR==1{print $1}' || true)"
echo "    ${WWW_DOMAIN} -> ${WWW_RESOLVED:-<олдсонгүй>}"
if [ -z "${WWW_RESOLVED}" ]; then
    echo
    echo "АНХААР: www DNS байхгүй — утас www оруулвал ERR_NAME_NOT_RESOLVED."
    echo "       deploy/dns-request-email.md-ийн www CNAME/A хүсэлтийг НДТ руу илгээнэ үү."
fi

if [ -n "${PUBLIC_IP}" ] && [ "${RESOLVED}" != "${PUBLIC_IP}" ]; then
    echo
    echo "АНХААР: DNS-ийн хаяг (${RESOLVED}) энэ серверийн гадаад IP (${PUBLIC_IP})-тай таарахгүй байна."
    echo "NAT-ын ард байвал энэ хэвийн. Үргэлжлүүлэх үү? [y/N]"
    read -r ans
    [ "${ans}" = "y" ] || exit 1
fi

# ------------------------------------------------------------- 2. Firewall (ufw)
echo "==> Firewall тохируулж байна"
apt-get install -y ufw >/dev/null
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw default deny incoming
ufw default allow outgoing
ufw --force enable
ufw status verbose

# ------------------------------------------------------- 3. Nginx-ийг шинэчлэх
echo "==> Nginx тохиргоог шинэчилж байна"
cp "${SCRIPT_DIR}/nginx.conf" "/etc/nginx/sites-available/${APP_DOMAIN}"
ln -sf "/etc/nginx/sites-available/${APP_DOMAIN}" "/etc/nginx/sites-enabled/${APP_DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ------------------------------------------------- 4. Let's Encrypt гэрчилгээ
echo "==> SSL гэрчилгээ авч байна (Let's Encrypt)"
apt-get install -y certbot python3-certbot-nginx >/dev/null
CERT_ARGS=(-d "${APP_DOMAIN}")
if [ -n "${WWW_RESOLVED}" ]; then
    CERT_ARGS+=(-d "${WWW_DOMAIN}")
else
    echo "    (www DNS байхгүй тул зөвхөн apex сертификат)"
fi
certbot --nginx "${CERT_ARGS[@]}" \
        --non-interactive --agree-tos -m "${CERTBOT_EMAIL}" \
        --redirect --keep-until-expiring

echo "==> Автомат сунгалт шалгаж байна"
systemctl enable --now certbot.timer
certbot renew --dry-run

# ----------------------------------------------------------------- 5. fail2ban
echo "==> fail2ban (нууц үг таах халдлагаас хамгаалах)"
apt-get install -y fail2ban >/dev/null
cat > /etc/fail2ban/jail.d/manage-dornogovi.conf <<'JAIL'
[sshd]
enabled  = true
maxretry = 5
bantime  = 1h

[nginx-http-auth]
enabled = true

[nginx-bad-request]
enabled  = true
maxretry = 10
bantime  = 1h
JAIL
systemctl enable --now fail2ban
systemctl restart fail2ban

# -------------------------------------------------------------------- 6. .env
echo "==> .env-г HTTPS горимд шилжүүлж байна"
cd "${APP_DIR}"
sed -i "s|^APP_URL=.*|APP_URL=https://${APP_DOMAIN}|"      .env
sed -i "s|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|"                  .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|"                 .env
grep -q '^SESSION_DOMAIN='  .env || echo "SESSION_DOMAIN=${APP_DOMAIN}" >> .env

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;

systemctl reload php8.3-fpm nginx

echo
echo "=============================================="
echo " Дууслаа: https://${APP_DOMAIN}/"
echo "=============================================="
echo
echo "Дараагийн алхмууд:"
echo "  1. Хөтчөөр нээж, түгжээний тэмдэг гарч байгаа эсэхийг шалга"
echo "  2. https://www.ssllabs.com/ssltest/ дээр домайнаа шалга (A зэрэг байх ёстой)"
echo "  3. Админы нууц үгийг ЗААВАЛ хүчтэй нууц үгээр солих"
echo "  4. Browser extension-ий хаягийг production домайн руу шинэчилж дахин суулгах"
