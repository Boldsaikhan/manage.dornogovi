# DNS / гадна хандалт — одоогийн байдал

| Зүйл | Утга |
|---|---|
| Байх ёстой A | `manage.dornogovi.gov.mn` → **202.37.109.67** |
| Сервер дотоод | `10.52.1.67` |
| DNS удирдагч | `ns.gov.mn` / `ns1` / `ns3` / `ns4.gov.mn` |
| Хариуцагч | **mcloud.gov.mn → Холбоо барих**, `admin@ndc.gov.mn` |

> **2026-08-30 — асуудал даамжирсан:** өмнө зөвхөн `www` унадаг байсан бол
> одоо **үндсэн нэр өөрөө** Монголын ISP-үүд дээр `NXDOMAIN` болсон.
> `8.8.8.8` / `1.1.1.1` зөв атал Univision / Skytel / MobiCom-ийн resolver
> «байхгүй» гэж хариулна → Chrome `DNS_PROBE_FINISHED_NXDOMAIN`.
> Энэ нь **сайтын Laravel код ч биш, сертификат ч биш** — gov.mn DNS бүс.
> НДТ л засна.

### Одоогийн байдал (2026-08-30, энэ компьютерээс хэмжсэн)

| DNS | Үр дүн |
|---|---|
| `8.8.8.8` (Google) | A → `202.37.109.67` **OK** |
| `1.1.1.1` (Cloudflare) | A → `202.37.109.67` **OK** |
| `59.153.112.2` (Univision) | **NXDOMAIN** |
| `103.206.153.188` (Skytel) | **NXDOMAIN** |
| `2405:5700:2:5::4` (Skytel IPv6) | **NXDOMAIN** |
| Харьцуулалт: `dornogovi.gov.mn` | ижил resolver дээр `103.87.69.216` OK |
| Харьцуулалт: `www.gov.mn` | ижил resolver дээр OK |

### Яагаад ингэж байгаа вэ — үндсэн шалтгаан

`manage.dornogovi.gov.mn` нь эцэг бүсийн энгийн A бичлэг **биш**, өөрийн
NS болон SOA-тай **тусдаа delegated бүс** болж карвчлагдсан байна:

```
manage.dornogovi.gov.mn.  NS   ns.gov.mn / ns1 / ns3 / ns4.gov.mn
manage.dornogovi.gov.mn.  A    202.37.109.67   (TTL ердөө 60 сек)
manage.dornogovi.gov.mn.  SOA  a.misconfigured.dns.server.invalid.
                               serial 2026082401  minimum 3600
```

1. SOA-гийн primary (MNAME) нь **`a.misconfigured.dns.server.invalid`** —
   байхгүй нэр. Secondary DNS нь primary-г яг эндээс олдог тул
   NOTIFY / AXFR зон дамжуулалт ажиллах боломжгүй → зарим NS энэ бүсийг
   хүлээж аваагүй.
2. Бүсийг аваагүй NS нь хуучин эцэг бүс `dornogovi.gov.mn`-ээс хариулна.
   Тэнд `manage` бичлэг ч, delegation ч алга (serial `2022112701`, 2022
   оноос хойш өөрчлөгдөөгүй) → **authoritative NXDOMAIN**, эцэг бүсийн
   negative TTL `86400` (24 цаг) тул нэг удаа кэшлэгдвэл бүтэн хоног үлдэнэ.
3. Эцэг бүсийн serial `2022112701` нь **2022 оноос хойш өөрчлөгдөөгүй**.
   Дэд бүс рүү заасан delegation (NS) бичлэгийг эцэг бүсэд нэмэхэд serial
   өсгөөгүй бол secondary DNS-үүд түүнийг **аваагүй** байна — энэ нь 2-р
   цэгийн шалтгаан.

Тиймээс resolver аль NS-т оногдсоноос хамаарч заримд нь ажиллаад, заримд
нь «байхгүй» гэж гардаг.

**Цаг хугацааны холбоо:** дэд бүсийн serial `2026082401` = 2026-08-24,
сертификат `2026-08-26`. Нэрийг нийтэд гаргах ажлын хүрээнд 08-24-нд дэд
бүсийг үүсгэсэн, нэр шийдэгдэж эхэлсний дараа 08-26-нд сертификат авсан.

> **Сертификат энэ эвдрэлд оролцоогүй.** HTTP-01
> (`.well-known/acme-challenge`) аргаар олгогдсон тул DNS-д TXT бичлэг
> шаардаагүй — `_acme-challenge` нэр одоо ч байхгүй (NXDOMAIN).
> Сертификатыг дахин суулгаснаар DNS засагдахгүй.

> **Сертификаттай ямар ч холбоогүй.** 2026-08-30-нд шалгахад:
> `curl --resolve manage.dornogovi.gov.mn:443:202.37.109.67 https://…/`
> → **HTTP 302** (хэвийн). Сертификат: Let's Encrypt,
> `CN=manage.dornogovi.gov.mn`, хүчинтэй `2026-08-26 → 2026-11-24`.
> IP-гээр шууд хандахад сайт бүрэн ажиллаж байна.

### Хэрэглэгчийн талын түр арга (DNS засагдтал)

```powershell
# Админ эрхээр — DNS-ийг 8.8.8.8 / 1.1.1.1 болгоод кэш цэвэрлэнэ
powershell -ExecutionPolicy Bypass -File deploy\set-dns-client.ps1

# Буцаах
powershell -ExecutionPolicy Bypass -File deploy\set-dns-client.ps1 -Reset
```

Ажиллахгүй бол `deploy\add-host-client.ps1` (hosts бичлэг) ашиглана.
Chrome-д хуучин кэш үлдвэл `chrome://net-internals/#dns` → *Clear host cache*.

---

### АРХИВ — гол алдаанууд (2026-08-28, сервер дээрээс authoritative)
Сертификат/nginx буруу биш. NS-үүд зөрүүтэй — ns4 бүсийг мэдэхгүй.

| DNS | Үр дүн |
|---|---|
| `ns.gov.mn` / `ns1` / `ns3` | A → `202.37.109.67` (OK) |
| **`ns4.gov.mn`** | **NXDOMAIN + AA** (serial `2022112701`) |
| `8.8.8.8` / `1.1.1.1` | A → `202.37.109.67` (одоогоор OK, TTL 60 сек — ns4 кэш хордож болно) |

1. Тусад бүс: SOA = `a.misconfigured.dns.server.invalid`
2. Parent `dornogovi.gov.mn` serial `2022112701` (хуучин)
3. A TTL = 60 сек

Chrome дээрх «not secure» нь DNS унасан үеийн UI — сертификатын алдаа биш.

### Зөв тохиргоо (НДТ хийнэ)
`dornogovi.gov.mn` бүсийн дотор (тусад бүс биш):

| Нэр | Төрөл | Утга | TTL |
|---|---|---|---|
| `manage.dornogovi.gov.mn` | A | `202.37.109.67` | `3600` |
| `www.manage.dornogovi.gov.mn` | CNAME → `manage.dornogovi.gov.mn` **эсвэл** A | `202.37.109.67` | `3600` |

**Хамгийн зөв нь:** `manage.dornogovi.gov.mn`-ийг тусдаа бүс биш, эцэг
`dornogovi.gov.mn` бүсийн дотор энгийн бичлэг болгох.

> ⚠ **Дараалал чухал.** Одоо нэр шийдэгдэж байгаа нь **зөвхөн тэр дэд бүсийн
> ачаар**. Дэд бүсийг эхэлж устгавал сайт бүх resolver дээр алга болно.
> Зөв дараалал: (1) эцэг бүсэд A/CNAME нэмж serial өсгөх → (2) 4 NS дээр
> ижил хариу өгч байгааг шалгах → (3) дараа нь л дэд бүсийг устгах.

Хэрэв тусдаа бүсээр үлдээх бол SOA-гийн primary-г
`a.misconfigured.dns.server.invalid` → `ns.gov.mn` болгож **заавал** засаж,
4 NS дээр бүс ачаалагдсан эсэхийг шалгана. A бичлэгийн TTL 60 → 3600.

**Утасны `DNS_PROBE_FINISHED_NXDOMAIN` (www):** apex A зөв ч www бичлэг
байхгүй тул Chrome www руу оролдож унана. nginx www→apex redirect DNS-ийн
**дараа** л ажиллана — эхлээд www A/CNAME нэмнэ.

**Илгээх текст:** [dns-request-email.md](dns-request-email.md)

**Шалгах (сервер дээр):**

```bash
bash /var/www/manage.dornogovi.gov.mn/deploy/dns-check.sh
# эсвэл
bash /opt/manage-dornogovi/deploy/dns-check.sh
```

Гарах код: `0` бүрэн OK · `1` apex алдаа · `2` www NXDOMAIN ·
`3` олон улсын resolver OK гэхдээ **дотоодын ISP NXDOMAIN** (одоогийн байдал).

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
