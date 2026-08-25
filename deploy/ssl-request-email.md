# НДТ-д илгээх и-мэйл — SSL сертификат олгуулах хүсэлт

**Хүлээн авагч:** admin@ndc.gov.mn
**Хуулбар (CC):** it@dornogovi.gov.mn

**Гарчиг:**
```
manage.dornogovi.gov.mn — SSL сертификат олгуулах хүсэлт (CSR хавсаргав)
```

---

## Захидлын агуулга

Эрхэм хүндэт Үндэсний дата төвийн хамт олон,

Дорноговь аймгийн Засаг даргын Тамгын газраас mcloud.gov.mn үүлэн үйлчилгээнд
байрлуулсан `manage.dornogovi.gov.mn` домэйнд итгэмжлэгдсэн SSL сертификат
олгож өгөхийг хүсэж байна.

**Одоогийн байдал:** тус систем нь өөрөө гарын үсэг зурсан (self-signed)
сертификаттай тул хэрэглэгч бүрт «Not secure / Аюулгүй биш» анхааруулга гарч,
албан хаагчид уг анхааруулгыг алгасаж ороход хүрч байна. Энэ нь сүлжээний
дундах халдлага (MITM)-ын эрсдэл үүсгэж, албан хаагчдын нэвтрэх мэдээлэл
алдагдах магадлалтай.

**Хүсэлт:** дараах CSR (Certificate Signing Request)-ийн дагуу серверийн
сертификат олгож өгнө үү.

| Талбар | Утга |
|---|---|
| Домэйн (CN) | `manage.dornogovi.gov.mn` |
| SAN | DNS: `manage.dornogovi.gov.mn` |
| Байгууллага (O) | Dornogovi Aimag Governors Office Administration |
| Нэгж (OU) | Information Technology |
| Байршил | Sainshand, Dornogovi, MN |
| Түлхүүр | RSA 2048 бит |
| Хэшийн алгоритм | SHA-256 |
| Вэб сервер | nginx (CloudPanel) |
| Сервер | mcloud.gov.mn дэх `manage-dornogovi`, 202.37.109.67 |

**Бидэнд буцаан ирүүлэх зүйл:**

1. Серверийн сертификат — `.crt` (PEM формат)
2. Завсрын гэрчилгээний сүлжээ (intermediate chain) — байгаа бол тусад нь

> Хувийн түлхүүр (private key) нь сервер дээрээ хадгалагдсан бөгөөд хэнд ч
> дамжуулагдахгүй. Тиймээс бидэнд зөвхөн дээрх хоёр файл хүрэлцэнэ.

**Хүчинтэй хугацаа болон шинэчлэлт:** сертификатын хүчинтэй хугацаа болон
дуусахаас өмнө шинэчлэх журмыг мөн зааж өгнө үү.

Хэрэв танай журамд сертификат олгох боломжгүй бол дараах хоёр хувилбарын аль
нэгийг зөвшөөрч өгвөл бид өөрсдөө үнэгүй сертификат (Let's Encrypt) суулгах
боломжтой:

- **A.** `202.37.109.67` хаягийн **TCP 80** портыг интернэтээс нээх (443 аль
  хэдийн нээлттэй). Ингэснээр сертификат 90 хоног тутам автоматаар
  шинэчлэгдэнэ.
- **B.** `_acme-challenge.manage.dornogovi.gov.mn` TXT бичлэгийг бидний өгсөн
  утгаар нэмэх, эсвэл уг дэд домэйныг бидний DNS рүү delegate хийх.

Хүндэтгэсэн,
Дорноговь аймгийн ЗДТГ

---

## Хавсралт — CSR

Сервер дээр `/root/ssl/manage.dornogovi.gov.mn.csr` хаягт хадгалагдсан.
И-мэйлд файлаар хавсаргах, эсвэл доорх текстийг шууд хуулж илгээж болно:

```
-----BEGIN CERTIFICATE REQUEST-----
MIIDLTCCAhUCAQAwgbIxCzAJBgNVBAYTAk1OMRIwEAYDVQQIDAlEb3Jub2dvdmkx
EjAQBgNVBAcMCVNhaW5zaGFuZDE4MDYGA1UECgwvRG9ybm9nb3ZpIEFpbWFnIEdv
dmVybm9ycyBPZmZpY2UgQWRtaW5pc3RyYXRpb24xHzAdBgNVBAsMFkluZm9ybWF0
aW9uIFRlY2hub2xvZ3kxIDAeBgNVBAMMF21hbmFnZS5kb3Jub2dvdmkuZ292Lm1u
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAsBAVA9g+zNxPsMVTJInU
eGDbR+gNpyYOsnkT3pl5nE+PfyYXxW0MWH3Khg8DyGTtIS5iIE73fvVACxj73VrL
i7SKUE7JgFh+OXi2DI1vwPjI1sJzQrDutnZxKIpEW989FuHH0NXVVUZ52cBOhtOd
DjDZIJKNpTUI2UFs9BO5CTtwO+ymMP2tK3o6Ky68o//KLVyfsY1p9+j4x1cQoHEQ
5tsR196TGVtFQPX2EodDK3lq+qcjNkKomzx/fg0HKot9gglqttaDJFehMJaZrjU0
e6rqob3MhbSKCPoU4yByPBbHYaF5FU1G3f6l87kPD3Ds0K1fLUIg6Ovii9eofeMG
ZQIDAQABoDUwMwYJKoZIhvcNAQkOMSYwJDAiBgNVHREEGzAZghdtYW5hZ2UuZG9y
bm9nb3ZpLmdvdi5tbjANBgkqhkiG9w0BAQsFAAOCAQEAEv37N/dpm37miCp6kAda
udMM67RBhvwKbhc5PYbYoEgWFx825+oVQTS91V/SzW8AiFP+wCgHKL6o9bFXQIU/
RnxbvLOsAbFGWRgvUjhl+NcFJldY3NnoExo2Ip4nzxq+Vk05L6oie/HlKYcMRlw6
EWRyvq2HfN8mb2OqgkVZ1zyg5KUDpf/mhgn8eE0n9T7y3sZoHvfIswb3tHAItSiV
4Tbhg598jw6g7KDbJqBrxZXa48wVYJtoCudZsF2LyABIP0qOizKjlnEkgUkhMfxf
iRuNOOCBMlXcJFZxNSySZZjGar1/oZR8fB2csWi62EPsoWGjFsjm1ve4jDPYLRWY
qg==
-----END CERTIFICATE REQUEST-----
```

---

## Дотоод тэмдэглэл (илгээхгүй)

**Сервер дээрх файлууд** (`/root/ssl/`, зөвхөн root уншина):

| Файл | Тайлбар |
|---|---|
| `manage.dornogovi.gov.mn.key` | Хувийн түлхүүр — **хэнд ч илгээхгүй, git-д оруулахгүй** |
| `manage.dornogovi.gov.mn.csr` | Хүсэлтийн файл — НДТ рүү илгээнэ |
| `openssl.cnf` | CSR үүсгэсэн тохиргоо (дахин үүсгэх шаардлагатай бол) |

**Сертификат ирсний дараа суулгах:**

```bash
# 1. Ирсэн файлуудыг сервер рүү хуулна
#    manage.crt (серверийн сертификат), chain.crt (завсрын, байвал)

# 2. Түлхүүртэй тохирч байгааг шалгана — гарах хоёр hash ижил байх ёстой
openssl x509 -noout -modulus -in /root/ssl/manage.crt | openssl md5
openssl rsa  -noout -modulus -in /root/ssl/manage.dornogovi.gov.mn.key | openssl md5

# 3. CloudPanel-аар суулгана
sudo clpctl site:install:certificate \
  --domainName=manage.dornogovi.gov.mn \
  --privateKey=/root/ssl/manage.dornogovi.gov.mn.key \
  --certificate=/root/ssl/manage.crt \
  --certificateChain=/root/ssl/chain.crt

# 4. Шалгах
openssl s_client -connect manage.dornogovi.gov.mn:443 -servername manage.dornogovi.gov.mn </dev/null 2>/dev/null | openssl x509 -noout -issuer -subject -dates
```

**Дуусах хугацааг санах:** сертификат дуусахаас 30 хоногийн өмнө шинэчлэх
хүсэлтийг НДТ рүү дахин илгээнэ (Let's Encrypt шиг автомат биш).
