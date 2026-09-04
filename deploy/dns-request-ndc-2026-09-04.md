# NDC рүү илгээх хүсэлт — 2026-09-04

## ✉️ БОГИНО ХУВИЛБАР — хуулж илгээх

**Хэнд:** `admin@ndc.gov.mn` · **Хуулбар:** `it@dornogovi.gov.mn`
**Мөн:** mcloud.gov.mn → «Холбоо барих»

**Сэдэв:**

```
manage.dornogovi.gov.mn — Skytel DNS дээр NXDOMAIN хэвээр (үргэлжлэл)
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь аймгийн ЗДТГ. Өмнөх хүсэлтийн үргэлжлэл.

Танай засварын дараа Univision дээр асуудал арилсан, баярлалаа.
Гэвч Skytel дээр хэвээр байна:

  Google 8.8.8.8         → 202.37.109.67   OK
  Cloudflare 1.1.1.1     → 202.37.109.67   OK
  Univision 59.153.112.2 → 202.37.109.67   OK
  Skytel 103.206.153.188 → NXDOMAIN        АЛДАА

Сайт өөрөө хэвийн: https://manage.dornogovi.gov.mn/login → 200

dornogovi.gov.mn бүсийн SOA minimum (сөрөг кэш) TTL = 86400 буюу 24 цаг.
Тиймээс authoritative серверүүдийн аль нэг нь зөрүүтэй байвал ISP-ийн кэш
өдөр бүр дахин «хордоно».

ХҮСЭЛТ

1. ns.gov.mn, ns1, ns3, ns4 дөрвүүлэн ижил хариу өгч байгааг шалгана уу:
     dig +short A manage.dornogovi.gov.mn @ns.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns4.gov.mn
   Дөрвүүлэнд 202.37.109.67 гарах ёстой.

2. SOA-ийн minimum TTL-ийг 86400 → 300 болгож бууруулна уу. Ингэвэл
   цаашид ийм алдаа 24 цаг биш 5 минутад засарна.

3. Кэш дуустал хүлээхгүйн тулд ШИНЭ нэр нэмж өгнө үү:
     erp.dornogovi.gov.mn.  IN  A  202.37.109.67   TTL 300
   Урьд асуугдаж байгаагүй нэр тул бүх ISP дээр шууд ажиллана. Бид энэ
   нэрийг nginx болон гэрчилгээндээ нэмнэ.

Холбоо барих: it@dornogovi.gov.mn

Баярлалаа.
```

---

## Дэлгэрэнгүй хувилбар

**Хаана:** `admin@ndc.gov.mn` (SOA-д бүртгэлтэй хариуцагч) · mcloud.gov.mn → «Холбоо барих»
**Хуулбар:** `it@dornogovi.gov.mn`

> ⚠️ ISP (Skytel, MobiCom) руу хандах шаардлагагүй. Бүсийг NDC эзэмшдэг тул
> засварыг тэд хийнэ. Доорх 3-р хүсэлт нь ISP-ийн кэш дуустал хүлээхгүйгээр
> шууд ажиллах зам.

---

## Сэдэв

```
manage.dornogovi.gov.mn — Skytel DNS дээр NXDOMAIN, бүсийн сөрөг TTL 24 цаг
```

## Агуулга

```
Сайн байна уу.

Дорноговь аймгийн ЗДТГ — it@dornogovi.gov.mn
Сервер: 202.37.109.67 (nginx, HTTPS хэвийн — https://manage.dornogovi.gov.mn/login → 200)

ХЭМЖИЛТ (2026-09-04)

  Resolver                     manage.dornogovi.gov.mn
  ---------------------------  -----------------------
  Google 8.8.8.8               202.37.109.67   OK
  Cloudflare 1.1.1.1           202.37.109.67   OK
  Univision 59.153.112.2       202.37.109.67   OK
  Skytel 103.206.153.188       NXDOMAIN        АЛДАА
  MobiCom 202.179.0.10         хариу ирсэнгүй

  www.manage.dornogovi.gov.mn — мөн ижил (Skytel дээр NXDOMAIN).

БҮСИЙН ТӨЛӨВ

  dornogovi.gov.mn SOA:
    primary      ns.gov.mn
    responsible  admin.ndc.gov.mn
    serial       2026090101
    minimum TTL  86400   ← сөрөг кэшийн хугацаа = 24 цаг

  NS: ns.gov.mn, ns1.gov.mn

ДҮГНЭЛТ
Бүсийн бичлэг зөв, олон улсын resolver-ууд зөв өгч байна. Гэвч сөрөг кэшийн
хугацаа 24 цаг тул нэг л удаа NXDOMAIN хариу авсан resolver түүнийгээ бүтэн
хоног барина. Хэрэв authoritative серверүүдийн аль нэг нь бүсээ бүрэн авч
чадаагүй бол тэр серверээс NXDOMAIN гарч, ISP-ийн кэш 24 цаг тутам дахин
«хордож», төгсгөлгүй давтагдана.

ХҮСЭЛТ

1) Authoritative бүх сервер ижил хариу өгч байгааг шалгаж, синкийг
   баталгаажуулна уу:

     dig +short A manage.dornogovi.gov.mn @ns.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns1.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns3.gov.mn
     dig +short A manage.dornogovi.gov.mn @ns4.gov.mn

   Дөрвүүлэнд 202.37.109.67 гарах ёстой. Аль нэгэнд NXDOMAIN байвал тэр
   серверийн зөрүү нь ISP-ийн кэшийг дахин дахин хордуулж байна.

2) dornogovi.gov.mn бүсийн SOA-ийн minimum (сөрөг кэшийн) TTL-ийг
   86400-аас 300 болгож бууруулна уу. Ингэснээр цаашид ийм алдаа гарсан ч
   24 цаг биш 5 минутын дараа өөрөө засарна.

     $TTL 300
     dornogovi.gov.mn. IN SOA ns.gov.mn. admin.ndc.gov.mn. (
         2026090402  ; serial (нэмэгдүүлнэ)
         86400       ; refresh
         7200        ; retry
         3600000     ; expire
         300 )       ; minimum  ← 86400-аас 300 болгоно

3) ЯАРАЛТАЙ ЗАМ — ISP-ийн кэш дуустал хүлээхгүйн тулд ШИНЭ нэр нэмж
   өгнө үү. Урьд нь хэзээ ч асуугдаж байгаагүй нэр дээр сөрөг кэш байхгүй
   тул бүх ISP дээр тэр даруй ажиллана:

     erp.dornogovi.gov.mn.  IN  A  202.37.109.67   ; TTL 300

   Бид энэ нэрийг nginx болон гэрчилгээндээ нэмж, албан хаагчдыг түр
   энэ хаягаар оруулна.

Баярлалаа.
```

---

## Засварын дараа шалгах

Серверээс:

```bash
bash deploy/dns-check.sh manage.dornogovi.gov.mn 202.37.109.67
```

Гарах кодууд: `0` бүрэн OK · `1` apex алдаа · `2` www NXDOMAIN · `3` ISP NXDOMAIN.

Локал компьютерээс (PowerShell):

```powershell
foreach ($s in '8.8.8.8','1.1.1.1','59.153.112.2','103.206.153.188','202.179.0.10') {
    try { "$s → " + (Resolve-DnsName manage.dornogovi.gov.mn -Type A -Server $s -DnsOnly -EA Stop |
        Where-Object QueryType -eq 'A' | ForEach-Object IPAddress) }
    catch { "$s → " + $_.Exception.Message }
}
```

## Хэрэглэгчдэд түр зөвлөмж

Skytel-ийн интернэт дээр `DNS_PROBE_FINISHED_NXDOMAIN` гарвал төхөөрөмжийн
DNS-ийг `8.8.8.8` / `1.1.1.1` болгоход шууд ажиллана. Гар утсанд:
Wi-Fi тохиргоо → тухайн сүлжээ → IP тохиргоо → Static → DNS 8.8.8.8.
