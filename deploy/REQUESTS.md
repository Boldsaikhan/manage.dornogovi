# DNS / гадна хандалт — одоогийн байдал

| Зүйл | Утга |
|---|---|
| Байх ёстой A | `manage.dornogovi.gov.mn` → **202.37.109.67** |
| Сервер дотоод | `10.52.1.67` |
| DNS удирдагч | `ns.gov.mn` / `ns1` / `ns3` / `ns4.gov.mn` |
| Хариуцагч | **mcloud.gov.mn → Холбоо барих**, `admin@ndc.gov.mn` |

> A бичлэг зарим resolver дээр зөв (`202.37.109.67`) гарч байгаа ч бүс
> **буруу тохируулагдсан** тул iPhone Safari / гар утасны сүлжээнд
> «server can't be found» / `ERR_NAME_NOT_RESOLVED` давтагдана.
> Энэ нь **сайтын Laravel код биш** — gov.mn DNS бүс.

### Гол алдаанууд
1. Тусад бүс: SOA primary = `a.misconfigured.dns.server.invalid`
2. NS-үүд шууд dig хийхэд timeout / өмнө нь ns4 NXDOMAIN
3. A TTL = 60 сек — NS доголдвол утасны DNS маш хурдан унана

### Зөв тохиргоо (НДТ хийнэ)
`dornogovi.gov.mn` бүсийн дотор (тусад бүс биш):

| Нэр | Төрөл | Утга | TTL |
|---|---|---|---|
| `manage.dornogovi.gov.mn` | A | `202.37.109.67` | `3600` |

Бүх 4 NS дээр ижил байх. SOA-д `misconfigured` үлдэхгүй.

**Илгээх текст:** [dns-request-email.md](dns-request-email.md)

**Шалгах (сервер дээр):**

```bash
bash /var/www/manage.dornogovi.gov.mn/deploy/dns-check.sh
# эсвэл
bash /opt/manage-dornogovi/deploy/dns-check.sh
```

---

## Сервер дээр хийх тохиргоо (DNS зөв болсны дараа)

CloudPanel / SSH:

```bash
# 1) DNS бүх NS дээр ижил эсэх
bash /opt/manage-dornogovi/deploy/dns-check.sh

# 2) .env
# APP_URL=https://manage.dornogovi.gov.mn
# SESSION_SECURE_COOKIE=true
# APP_DEBUG=false

# 3) SSL (CloudPanel)
# Sites → manage.dornogovi.gov.mn → SSL/TLS → New Let's Encrypt Certificate
# (go-public.sh-ийг CloudPanel дээр БҮҮ ажиллуул — nginx эвдэнэ)

# 4) Галын хана: 22, 80, 443 нээлттэй эсэх
sudo ufw status

# 5) Кэш / config
cd /home/manage/htdocs/manage.dornogovi.gov.mn   # CloudPanel зам
# эсвэл /var/www/manage.dornogovi.gov.mn
php artisan config:cache
php artisan route:cache
```

Хэрэглэгчийн утас дээр: Safari History/Website Data цэвэрлээд дахин нээнэ.

---

## Локал hosts (хөгжүүлэлт)

`C:\Windows\System32\drivers\etc\hosts` дотор
`127.0.0.1 manage.dornogovi.gov.mn` байвал production DNS-ийг хаадаг —
устгах эсвэл `#` тавина.
