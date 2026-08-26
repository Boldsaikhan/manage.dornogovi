
---

# mcloud.gov.mn дэмжлэгийн формд тавих (шинэчилсэн)

Зам: mcloud.gov.mn → баруун дээд булангийн **«Холбоо барих»**.  
Давхар илгээх: `admin@ndc.gov.mn`

**Сэдэв:**

```
ЯАРАЛТАЙ: manage.dornogovi.gov.mn DNS бүс буруу — iPhone/Safari «server can't be found»
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь аймгийн ЗДТГ. Холбоо: it@dornogovi.gov.mn
Сервер: manage-dornogovi (дотоод 10.52.1.67 / нийтийн 202.37.109.67)

АСУУДАЛ
---------
manage.dornogovi.gov.mn хаягаар хандахад зарим хэрэглэгч (ялангуяа iPhone Safari,
гар утасны сүлжээ) «Safari can't open the page because the server can't be found»
эсвэл Chrome ERR_NAME_NOT_RESOLVED алдаа авч байна.
Сервер болон HTTPS өөрөө ажиллаж байгаа (A resolve хийгдсэн үед 302 → /login).

ШАЛТГААН (одоогийн байдал)
--------------------------
1) manage.dornogovi.gov.mn тусдаа DNS бүс болж үүссэн бөгөөд SOA буруу:
   primary = a.misconfigured.dns.server.invalid
   serial  = 2026082401

2) A бичлэг зарим resolver дээр гарч байна:
   manage.dornogovi.gov.mn A = 202.37.109.67
   гэхдээ TTL = 60 сек (хэт богино) — NS түр хариулахгүй үед утасны DNS шууд унадаг.

3) NS (ns.gov.mn / ns1 / ns3 / ns4) шууд dig хийхэд timeout давтагдаж байна.
   Өмнө нь ns4 дээр NXDOMAIN байсан түүхтэй.

ХҮСЭЛТ (зөвлөмжтэй дараалал)
----------------------------
Аль болох энгийн, тогтвортой шийдэл:

1. manage.dornogovi.gov.mn-ийг ТУСДАА бүс биш болгож,
   dornogovi.gov.mn бүсийн доторх энгийн A бичлэг болгоно уу.

2. Бүх нэрийн серверт (ns / ns1 / ns3 / ns4) ижил бичлэг тараана уу:

   | Нэр                         | Төрөл | Утга           | TTL  |
   |-----------------------------|-------|----------------|------|
   | manage.dornogovi.gov.mn     | A     | 202.37.109.67  | 3600 |

3. Буруу SOA (a.misconfigured.dns.server.invalid)-ийг устгана / засана уу.
   Тусад бүс үлдээх бол primary-г жинхэнэ NS нэрээр (жишээ: ns.gov.mn) сольж,
   serial шинэчилнэ үү.

4. Negative cache (SOA minimum) хэт өндөр бол 300 сек болгоно уу.

Шалгах тушаал (зассаны дараа):
  dig +short A manage.dornogovi.gov.mn @ns.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
  dig +short A manage.dornogovi.gov.mn @ns4.gov.mn
  dig +short SOA manage.dornogovi.gov.mn @ns.gov.mn

Дөрвүүлэнд ижил «202.37.109.67» гарч, SOA-д misconfigured гэсэн үг байхгүй
байх ёстой.

Аймгийн 100+ албан хаагч өдөр тутам ашигладаг тул яаралтай шийдвэрлэж өгнө үү.
Баярлалаа.

Холбоо барих: it@dornogovi.gov.mn
```
