

---

# mcloud.gov.mn дэмжлэгийн формд тавих богино хувилбар

Зам: mcloud.gov.mn → баруун дээд булангийн **«Холбоо барих»**.

**Сэдэв:**

```
manage.dornogovi.gov.mn — ns4.gov.mn дээр A бичлэг дутуу (NXDOMAIN)
```

**Агуулга:**

```
Сайн байна уу.

Дорноговь аймгийн ЗДТГ. Төслийн хэрэглэгч: it@dornogovi.gov.mn
Сервэр: manage-dornogovi (10.52.1.67 / нийтийн 202.37.109.67)

АСУУДАЛ: manage.dornogovi.gov.mn хаягаар хандахад хэрэглэгчдийн
ойролцоогоор 4н 1 нь ERR_NAME_NOT_RESOLVED алдаа авч байна.

ШАЛТГААН: gov.mn бүсийн 4 нэрийн сервэрээс ns4.gov.mn (103.43.117.102)
тус бичлэгийг мэдэхгүй байна:

  dig @ns.gov.mn   manage.dornogovi.gov.mn A  -> NOERROR  202.37.109.67
  dig @ns1.gov.mn  manage.dornogovi.gov.mn A  -> NOERROR  202.37.109.67
  dig @ns3.gov.mn  manage.dornogovi.gov.mn A  -> NOERROR  202.37.109.67
  dig @ns4.gov.mn  manage.dornogovi.gov.mn A  -> NXDOMAIN

ns4.gov.mn дээрх dornogovi.gov.mn бүсийн SOA serial нь 2022112701 буюу
хуучин хэвээр байна. Мөн manage.dornogovi.gov.mn нь тусдаа бүс болж
үүссэн бөгөөд SOA нь a.misconfigured.dns.server.invalid байна.

ХҮСЭЛТ:
1. manage.dornogovi.gov.mn A = 202.37.109.67 бичлэгийг ns4.gov.mn руу тарааж,
   бүх нэрийн сервэрт ижил болгож өгнө үү.
2. Аль бол тусдаа бүс биш, dornogovi.gov.mn бүсийн доторх A бичлэг байлгаж,
   бүсийн serial-ыг шинэчлэнэ үү.
3. A бичлэгийн TTL-ийг 60 → 3600 сек болгоно уу.
4. Бүсийн SOA minimum (negative TTL) 86400 → 300 сек болгоно уу.

Тус системийг аймгийн 100 гаруй албан хаагч өдөр тутам ашиглаж байгаа тул
яаралтай шийдвэрлэж өгнө үү. Баярлалаа.

Холбоо барих: it@dornogovi.gov.mn
```

**Давхар илгээх:** admin@ndc.gov.mn (дээрхийг хуулж и-мэйлээр)
