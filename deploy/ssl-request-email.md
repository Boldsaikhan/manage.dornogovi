# НДТ-д илгээх и-мэйл — SSL сертификатыг DNS-ээр баталгаажуулах

> **Байдал (2026-08-26):** НДТ «Манай байгууллага SSL үйлчилгээ үзүүлдэггүй» гэж
> хариу өгсөн. Тиймээс сертификатыг **бид өөрсдөө үнэгүй авна (Let's Encrypt)** —
> НДТ-ээс зөвхөн **DNS-ийн нэг TXT бичлэг** нэмж өгөхийг хүсэх шаардлагатай.
> Энэ нь SSL үйлчилгээ биш, DNS засварын хүсэлт (өмнө A бичлэгийг ингэж зассан).

**Хүлээн авагч:** admin@ndc.gov.mn
**Хуулбар (CC):** it@dornogovi.gov.mn

**Гарчиг:**
```
manage.dornogovi.gov.mn — DNS-ийн TXT бичлэг нэмүүлэх хүсэлт
```

---

## Захидлын агуулга

Эрхэм хүндэт Үндэсний дата төвийн хамт олон,

SSL үйлчилгээ үзүүлэх боломжгүй тухай хариуг хүлээн авлаа, баярлалаа.

Тус тохиолдолд бид сертификатыг өөрсдөө (Let's Encrypt-ийн үнэгүй үйлчилгээгээр)
авах боломжтой бөгөөд танайхаас зөвхөн **DNS-ийн нэг TXT бичлэг нэмж өгөхийг**
хүсэж байна. Домэйн эзэмшлээ баталгаажуулах зориулалттай бөгөөд өөр
үйлчилгээ шаардахгүй.

**Хүсэлт 1 — TXT бичлэг нэмэх:**

| Бичлэгийн нэр | Төрөл | Утга | TTL |
|---|---|---|---|
| `_acme-challenge.manage.dornogovi.gov.mn` | TXT | `avpjczuC2EH4B700fW7yAu-P8v0zZREDmDH2YMyBAs0` | 300 |

Бичлэг нэмэгдсэн тухай мэдэгдэл өгвөл бид баталгаажуулалтыг ажиллуулж,
сертификатаа суулгана. Дараа нь уг TXT бичлэгийг устгаж болно.

**Хүсэлт 2 — цаашид автоматжуулах (сонголтоор):**

Let's Encrypt-ийн сертификат 90 хоног хүчинтэй тул шинэчлэх бүрд TXT утга
өөрчлөгддөг. Дараах хоёр аргын аль нэгийг зөвшөөрвөл цаашид танайхад дахин
хүсэлт гаргах шаардлагагүй, бүрэн автоматаар шинэчлэгдэх болно:

- **А. Дэд домэйн шилжүүлэх (delegate):** `_acme-challenge.manage.dornogovi.gov.mn`
  нэрийг манай DNS сервер рүү `NS` эсвэл `CNAME` бичлэгээр чиглүүлж өгөх;
- **Б. Firewall:** `202.37.109.67` хаягийн **TCP 80** портыг интернэтээс нээх
  (443 аль хэдийн нээлттэй). Ингэвэл баталгаажуулалт DNS-гүйгээр, автоматаар
  явагдана.

Аль нь танай журамд нийцэхийг зөвлөж өгвөл баярлах болно.

Хүндэтгэсэн,
Дорноговь аймгийн ЗДТГ

---

## Туршилтын дүн (2026-08-26)

Гурван ACME аргаас **зөвхөн DNS-01 боломжтой** нь батлагдлаа:

| Арга | Порт | Үр дүн |
|---|---|---|
| HTTP-01 | 80 | ❌ `Timeout during connect` — гаднаас хаалттай |
| TLS-ALPN-01 | 443 | ❌ `Timeout during connect` — гаднаас мөн хаалттай |
| DNS-01 | — | ✅ Боломжтой (TXT бичлэг нэмэхэд л хангалттай) |

> **Анхаарах:** Let's Encrypt-ийн сервер (гадаад) 443 порт руу ч холбогдож
> чадсангүй. Өөрөөр хэлбэл систем нь **дэлхийн интернэтээс хандагдахгүй**,
> зөвхөн дотоодын/төрийн сүлжээнээс ажиллаж байна. Хэрэв албан хаагчид
> гадаад томилолтоос хандах шаардлагатай бол firewall-ийн тохиргоог
> НДТ-тэй ярилцах хэрэгтэй.

---

## Дотоод тэмдэглэл (илгээхгүй)

### TXT утга

- **Авсан огноо:** 2026-08-26 (Let's Encrypt-ийн захиалга ~7 хоног хүчинтэй)
- **Хугацаа дуусвал шинэ утга авах:**

```bash
sudo /root/.acme.sh/acme.sh --issue --dns -d manage.dornogovi.gov.mn \
  --server letsencrypt --yes-I-know-dns-manual-mode-enough-go-ahead-please
# → гарах «TXT value»-г НДТ-д илгээнэ
```

### TXT бичлэг нэмэгдсэний дараа

```bash
# 1. Бичлэг тархсан эсэхийг шалгах
dig +short TXT _acme-challenge.manage.dornogovi.gov.mn @8.8.8.8

# 2. Сертификатыг гаргаж авах
sudo /root/.acme.sh/acme.sh --renew --dns -d manage.dornogovi.gov.mn \
  --server letsencrypt --yes-I-know-dns-manual-mode-enough-go-ahead-please

# 3. CloudPanel-д суулгах (файлууд /root/.acme.sh/manage.dornogovi.gov.mn_ecc/ дотор)
sudo clpctl site:install:certificate \
  --domainName=manage.dornogovi.gov.mn \
  --privateKey=/root/.acme.sh/manage.dornogovi.gov.mn_ecc/manage.dornogovi.gov.mn.key \
  --certificate=/root/.acme.sh/manage.dornogovi.gov.mn_ecc/manage.dornogovi.gov.mn.cer \
  --certificateChain=/root/.acme.sh/manage.dornogovi.gov.mn_ecc/ca.cer

# 4. Шалгах — issuer нь Let's Encrypt байх ёстой
openssl s_client -connect manage.dornogovi.gov.mn:443 \
  -servername manage.dornogovi.gov.mn </dev/null 2>/dev/null |
  openssl x509 -noout -issuer -subject -dates
```

### Хэрэв «Хүсэлт 2 Б» (порт 80) зөвшөөрөгдвөл

```bash
sudo clpctl lets-encrypt:install:certificate --domainName=manage.dornogovi.gov.mn
```
— цаашид 90 хоног тутам автоматаар шинэчлэгдэнэ, гар ажиллагаа шаардахгүй.

### Түр арга — дотоод компьютеруудад анхааруулгыг арилгах

Хариу хүлээх хугацаанд одоогийн self-signed сертификатыг байгууллагын
компьютеруудад «Итгэмжлэгдсэн үндсэн гэрчилгээ» болгон Group Policy-оор
тараавал албан хаагчдад «Not secure» харагдахгүй болно:

```bash
# Сертификатыг татах
scp manage-dornogovi:/etc/nginx/ssl-certificates/manage.dornogovi.gov.mn.crt .
```

GPO → Computer Configuration → Policies → Windows Settings → Security Settings →
Public Key Policies → **Trusted Root Certification Authorities** → Import.

> Гаднаас (гэр, томилолт) хандах үед анхааруулга хэвээр үлдэнэ тул энэ нь
> зөвхөн түр арга. Үндсэн шийдэл нь дээрх Let's Encrypt зам.
