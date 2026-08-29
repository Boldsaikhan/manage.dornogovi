
---

# mcloud.gov.mn дэмжлэгийн формд тавих (2026-08-28)

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
