
---

# mcloud.gov.mn дэмжлэгийн формд тавих (2026-08-26 оройн байдал)

Зам: mcloud.gov.mn → **«Холбоо барих»**. Давхар: `admin@ndc.gov.mn`

**Сэдэв:**

```
ЯАРАЛТАЙ: manage.dornogovi.gov.mn — NS зөрүү / NXDOMAIN (сертификатын DNS-01-ийн дараа)
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь ЗДТГ / it@dornogovi.gov.mn
Сервер: manage-dornogovi — нийтийн IP 202.37.109.67 (HTTPS ажиллаж байна)

АСУУДАЛ
---------
Let's Encrypt сертификат суулгахын тулд DNS TXT (_acme-challenge) нэмсний
дараагаас хэрэглэгчдэд маш ихээр:

  • Chrome: ERR_NAME_NOT_RESOLVED
  • iPhone Safari: server can't be found

гарч байна. Сертификат/nginx өөрөө буруу биш — IP-гээр HTTPS 302 өгч байна.
Асуудал нь DNS нэрийн шийдэлт.

НОТЛОХ ТУРШИЛТ (ижил цагт)
--------------------------
Google DNS (8.8.8.8):
  manage.dornogovi.gov.mn A → 202.37.109.67   (OK)

Cloudflare (1.1.1.1):
  manage.dornogovi.gov.mn A → 202.37.109.67   (OK)

Univision ISP DNS (ns3.univision.mn / 59.153.112.2):
  manage.dornogovi.gov.mn → Non-existent domain (NXDOMAIN)   ← АЛДАА

Google DoH заримдаа ns4 (103.43.117.102)-аас NXDOMAIN авч байна.
Parent SOA: dornogovi.gov.mn serial 2022112701 (хуучин)
Тусад бүс SOA: a.misconfigured.dns.server.invalid (буруу)

Иймээс Монголын ISP DNS ашигласан Mac/iPhone ихэнхдээ унана,
8.8.8.8 ашигласан компьютер заримдаа нээгдэнэ.

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

Шалгах:
  dig +short A manage.dornogovi.gov.mn @ns.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns4.gov.mn
  → дөрвүүлэнд 202.37.109.67

Яаралтай шийдвэрлэж өгнө үү. Баярлалаа.
it@dornogovi.gov.mn
```
