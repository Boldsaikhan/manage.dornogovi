---

# mcloud.gov.mn дэмжлэгийн формд тавих (2026-08-30) — ХАМГИЙН СҮҮЛИЙН

> Энэ хувилбарыг илгээнэ үү. Доорх 08-28-ны захиа архив.
> Ялгаа: өмнө нь зөвхөн `www` унадаг байсан бол одоо **үндсэн нэр өөрөө**
> дотоодын ISP-үүд дээр NXDOMAIN болсон.

Зам: mcloud.gov.mn → **«Холбоо барих»**. Давхар: `admin@ndc.gov.mn`

**Сэдэв:**

```
ЯАРАЛТАЙ: manage.dornogovi.gov.mn — тусдаа DNS бүс тасарч, Монголын ISP-ууд NXDOMAIN өгч байна
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь ЗДТГ / it@dornogovi.gov.mn
Сервер: manage-dornogovi — нийтийн IP 202.37.109.67
Сервер, nginx, SSL сертификат БҮРЭН АЖИЛЛАЖ БАЙНА (доор нотолсон).

АСУУДАЛ
---------
manage.dornogovi.gov.mn нэрийг Монголын интернэт үйлчилгээ эрхлэгчдийн
DNS сервэрүүд «байхгүй» (NXDOMAIN) гэж хариулж байна. Иймээс дотоодын
хэрэглэгчид сайт руу ОГТ орж чадахгүй — Chrome «DNS_PROBE_FINISHED_NXDOMAIN».

Google (8.8.8.8) болон Cloudflare (1.1.1.1) дээр ижил нэр ЗӨВ шийдэгдэж
байгаа нь бүсэд бичлэг БАЙГААГ, харин бүх NS дээр тархаагүйг харуулж байна.

НОТЛОХ ТУРШИЛТ (2026-08-30)
---------------------------
nslookup manage.dornogovi.gov.mn 8.8.8.8            → 202.37.109.67   OK
nslookup manage.dornogovi.gov.mn 1.1.1.1            → 202.37.109.67   OK

nslookup manage.dornogovi.gov.mn 59.153.112.2       → NXDOMAIN  (Univision)
nslookup manage.dornogovi.gov.mn 103.206.153.188    → NXDOMAIN  (Skytel)
nslookup manage.dornogovi.gov.mn 2405:5700:2:5::4   → NXDOMAIN  (Skytel IPv6)

Харьцуулалт — нэг л resolver дээр:
  dornogovi.gov.mn        → 103.87.69.216   OK   (эцэг бүс шийдэгдэж байна)
  www.gov.mn              → 103.85.184.238  OK
  manage.dornogovi.gov.mn → NXDOMAIN             (зөвхөн энэ дэд нэр унасан)

ҮНДСЭН ШАЛТГААН — ТУСДАА БҮС ТАСАРЧ ҮЛДСЭН
-------------------------------------------
manage.dornogovi.gov.mn нь эцэг бүсийн энгийн A бичлэг БИШ, өөрийн
NS болон SOA-тай ТУСДАА delegated бүс болж карвчлагдсан байна:

  manage.dornogovi.gov.mn.  NS   ns.gov.mn / ns1 / ns3 / ns4.gov.mn
  manage.dornogovi.gov.mn.  A    202.37.109.67   (TTL зөвхөн 60 сек)
  manage.dornogovi.gov.mn.  SOA  a.misconfigured.dns.server.invalid.
                                 hostmaster.manage.dornogovi.gov.mn.
                                 serial 2026082401  minimum 3600

Хоёр ноцтой зөрчил:

1) SOA-гийн primary (MNAME) = «a.misconfigured.dns.server.invalid» —
   БАЙХГҮЙ нэр. Secondary DNS нь primary-г яг эндээс олдог тул
   NOTIFY / AXFR зон дамжуулалт ажиллах боломжгүй. Иймээс зарим NS
   энэ бүсийг ХҮЛЭЭЖ АВААГҮЙ.

2) Бүсийг аваагүй NS нь хуучин эцэг бүс dornogovi.gov.mn-ээс хариулна.
   Тэр бүсэд manage гэсэн бичлэг ч, delegation ч алга (serial 2022112701,
   2022 оноос хойш өөрчлөгдөөгүй) → AUTHORITATIVE NXDOMAIN.
   Эцэг бүсийн negative TTL нь 86400 (24 цаг) тул resolver нэг удаа
   «байхгүй» гэж кэшэлбэл бүтэн хоног хадгална.

Тиймээс resolver аль NS-т оногдсоноос хамаарч заримд нь ажиллаж,
заримд нь «байхгүй» гэж гарч байна.

ЦАГ ХУГАЦААНЫ ХОЛБОО
---------------------
Тусдаа бүсийн serial 2026082401 = 2026-08-24, сертификат 2026-08-26.
Өөрөөр хэлбэл manage.dornogovi.gov.mn нэрийг нийтэд гаргах ажлын хүрээнд
энэ дэд бүсийг 08-24-нд үүсгэсэн бөгөөд нэр шийдэгдэж эхэлсний дараа
08-26-нд сертификат олгогдсон.

Сертификат нь HTTP-01 (.well-known/acme-challenge) аргаар олгогдсон —
DNS-д ямар ч TXT бичлэг ШААРДАГДААГҮЙ (_acme-challenge одоо ч байхгүй).
Тиймээс сертификат энэ эвдрэлд огт оролцоогүй; дэд бүсийн тохиргоо
дутуу хийгдсэн нь шалтгаан.

ХҮСЭЛТ
------
1) ХАМГИЙН ЭНГИЙН БӨГӨӨД ХҮССЭН ШИЙДЭЛ: manage.dornogovi.gov.mn-ийг
   тусдаа бүс биш, эцэг dornogovi.gov.mn бүсийн ДОТОР энгийн бичлэг
   болгоно уу:

   manage.dornogovi.gov.mn.      IN  A      202.37.109.67   TTL 3600
   www.manage.dornogovi.gov.mn.  IN  CNAME  manage.dornogovi.gov.mn.

   ⚠ ДАРААЛАЛ ЧУХАЛ — эсрэгээр хийвэл сайт БҮРЭН унана:
     а) эхлээд эцэг бүсэд дээрх бичлэгүүдийг нэмж, serial-ыг өсгөнө;
     б) 4 NS дээр ижил хариу өгч байгааг шалгана;
     в) ДАРАА нь л тусдаа дэд бүс болон түүний delegation-ыг устгана.
   Одоо нэр шийдэгдэж байгаа нь ЗӨВХӨН тэр дэд бүсийн ачаар тул түүнийг
   эхэлж устгавал сайт бүх resolver дээр алга болно.

2) Хэрэв тусдаа бүсээр үлдээх шаардлагатай бол:
   • SOA primary (MNAME)-ыг «a.misconfigured.dns.server.invalid»-аас
     жинхэнэ нэр (ns.gov.mn) болгож ЗААВАЛ засна уу.
   • ns.gov.mn / ns1 / ns3 / ns4 ДӨРВҮҮЛЭН энэ бүсийг ачаалж,
     ижил хариу өгч байгааг баталгаажуулна уу.
   • NOTIFY / AXFR ажиллаж байгаа эсэхийг шалгана уу.

3) A бичлэгийн TTL одоо ердөө 60 сек — 3600 болговол ачаалал буурна.

4) Эцэг dornogovi.gov.mn бүсийн SOA serial 2022112701 — 2022 оноос хойш
   өөрчлөгдөөгүй. Дэд бүс рүү заасан delegation (NS) бичлэгийг эцэг бүсэд
   нэмэхэд serial өсгөөгүй бол secondary DNS-үүд түүнийг АВААГҮЙ байна.
   Эцэг бүсийн serial-ыг заавал өсгөнө үү.

5) БАТАЛГААЖУУЛАХ ХҮСЭЛТ: өмнө нь manage.dornogovi.gov.mn нэр серверийн
   ДОТООД хаяг 10.52.1.67 руу заадаг байсан. Нийтийн resolver-ууд дээр
   тэр бичлэг одоо харагдахгүй байгаа боловч бид ns.gov.mn / ns1 / ns3 /
   ns4 рүү шууд query явуулах боломжгүй тул баталгаажуулж чадахгүй байна.

   Дөрвүүлэн NS дээр 10.52.1.67 гэсэн хуучин A бичлэг үлдээгүй эсэхийг
   шалгаж, үлдсэн бол устгана уу. 10.x.x.x бол дотоод (RFC1918) хаяг —
   нийтийн DNS-д байвал интернэтээс хандах боломжгүй болно.

СЕРВЭР ТАЛ ЗӨВ ГЭДГИЙН НОТОЛГОО (2026-08-30)
---------------------------------------------
curl --resolve manage.dornogovi.gov.mn:443:202.37.109.67 https://manage.dornogovi.gov.mn/
  → HTTP 302 (хэвийн)

Сертификат: Let's Encrypt, CN = manage.dornogovi.gov.mn
            хүчинтэй 2026-08-26 → 2026-11-24
Өөрөөр хэлбэл IP-гээр шууд хандахад сайт болон HTTPS бүрэн ажиллаж байна.
Зөвхөн нэр шийдэлт (DNS) л саатуулж байна.

ШАЛГАХ (засварын дараа)
------------------------
  dig +short A manage.dornogovi.gov.mn @ns.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns4.gov.mn
  → дөрвүүлэн ЗӨВХӨН 202.37.109.67 (10.52.1.67 ГАРАХ ЁСГҮЙ)

  dig +short SOA manage.dornogovi.gov.mn @ns.gov.mn
  → «a.misconfigured.dns.server.invalid» ГАРАХГҮЙ байх
    (эсвэл тусдаа бүс устсан бол SOA нь dornogovi.gov.mn-ийнх байна)

Яаралтай шийдвэрлэж өгнө үү. Баярлалаа.
it@dornogovi.gov.mn
```

---

# АРХИВ — mcloud.gov.mn дэмжлэгийн формд тавих (2026-08-28, www-д зориулсан)

Зам: mcloud.gov.mn → **«Холбоо барих»**. Давхар: `admin@ndc.gov.mn`

**Сэдэв:**

```
ЯАРАЛТАЙ: manage.dornogovi.gov.mn — ns4.gov.mn NXDOMAIN (8.8.8.8 / 1.1.1.1 кэш хордож байна)
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь ЗДТГ / it@dornogovi.gov.mn
Сервер: manage-dornogovi — нийтийн IP 202.37.109.67 (HTTPS ажиллаж байна)

АСУУДАЛ
---------
manage.dornogovi.gov.mn нэр ns.gov.mn / ns1 / ns3 дээр зөв,
харин ns4.gov.mn дээр AUTHORITATIVE NXDOMAIN буцааж байна.

Иймээс Google (8.8.8.8) болон Cloudflare (1.1.1.1) заримдаа зөв,
заримдаа «байхгүй» гэж кэшэлдэг. Гар утас (Brave/Chrome)
DNS_PROBE_POSSIBLE / This site can’t be reached гэж харуулна.
Сервер, nginx, SSL буруу биш.

НОТЛОХ ТУРШИЛТ (2026-08-28, сервер дээрээс, authoritative)
----------------------------------------------------------
dig A manage.dornogovi.gov.mn @ns.gov.mn   → 202.37.109.67   (OK)
dig A manage.dornogovi.gov.mn @ns1.gov.mn  → 202.37.109.67   (OK)
dig A manage.dornogovi.gov.mn @ns3.gov.mn  → 202.37.109.67   (OK)
dig A manage.dornogovi.gov.mn @ns4.gov.mn  → NXDOMAIN + AA
     SOA dornogovi.gov.mn serial 2022112701

Google 8.8.8.8:     A → 202.37.109.67  (одоогоор OK, TTL 60 сек)
Cloudflare 1.1.1.1: A → 202.37.109.67  (одоогоор OK)
Тусад бүс SOA: a.misconfigured.dns.server.invalid (буруу)

ns4 NXDOMAIN-ийг 8.8.8.8/1.1.1.1 кэшэлбэл бүх хэрэглэгч унана.

ХҮСЭЛТ
------
1) manage.dornogovi.gov.mn-ийг тусад бүс биш, dornogovi.gov.mn бүсийн
   энгийн A бичлэг болгоно уу:

   manage.dornogovi.gov.mn.  IN  A  202.37.109.67   TTL 3600

2) ns.gov.mn, ns1.gov.mn, ns3.gov.mn, ns4.gov.mn ДӨРВҮҮЛЭНД
   ижил A бичлэг байлгана уу. ns4 дээр NXDOMAIN үлдэх ёсгүй.

3) a.misconfigured.dns.server.invalid SOA-г устгана / засана уу.
   Parent бүсийн serial-ыг шинэчилнэ үү (одоо 2022112701).

4) Сертификатын _acme-challenge TXT үлдсэн бол устгаж болно
   (сертификат аль хэдийн суусан).

5) www.manage.dornogovi.gov.mn — одоо NXDOMAIN (утас Chrome
   «www.manage… server IP could not be found» ERR_NAME_NOT_RESOLVED).
   Дараах аль нэгийг нэмнэ үү:

   www.manage.dornogovi.gov.mn.  IN  CNAME  manage.dornogovi.gov.mn.
   эсвэл
   www.manage.dornogovi.gov.mn.  IN  A  202.37.109.67

   Сервер nginx www → apex redirect тохируулсан (deploy/nginx.conf).
   DNS нэмсний дараа: sudo certbot --nginx -d manage.dornogovi.gov.mn
   -d www.manage.dornogovi.gov.mn --expand

Шалгах (дөрвүүлэнд ижил байх):
  dig +short A manage.dornogovi.gov.mn @ns.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns4.gov.mn
  → 202.37.109.67

  dig +short A manage.dornogovi.gov.mn @8.8.8.8
  dig +short A manage.dornogovi.gov.mn @1.1.1.1
  → 202.37.109.67

  dig +short A www.manage.dornogovi.gov.mn @8.8.8.8
  dig +short CNAME www.manage.dornogovi.gov.mn @8.8.8.8
  → 202.37.109.67 эсвэл manage.dornogovi.gov.mn

Яаралтай шийдвэрлэж өгнө үү. Баярлалаа.
it@dornogovi.gov.mn
```
