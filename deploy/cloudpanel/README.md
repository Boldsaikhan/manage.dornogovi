# CloudPanel руу шилжих

CloudPanel нь **өөрийн** nginx, MariaDB, PHP-г суулгадаг тул одоо ажиллаж буй
стектэй зэрэгцэн ажиллах боломжгүй. Тиймээс энэ бол суулгалт биш, **нүүлгэн
шилжүүлэлт** — 3 алхамтай, буцаах боломжтой.

Ажиллах хугацаа: **30-45 минут**. Систем энэ хугацаанд ажиллахгүй.

---

## Урьдчилсан бэлтгэл (алгасаж БОЛОХГҮЙ)

1. НДТ-ийн **VPN-д холбогдоно**
2. mcloud консол → **Snapshot нөөцлөлт** → `manage-dornogovi` сервэрийн snapshot үүсгэ
   — буруудвал буцаах цорын ганц баталгаа

## Алхам 1 — нөөцлөх

```bash
ssh manage-dornogovi
sudo bash /opt/manage-dornogovi/deploy/cloudpanel/01-backup.sh
```

Өгөгдлийн сан, `.env`, нууц үг, байршуулсан файлыг `/root/manage-backup-<огноо>.tar.gz`
болгоно. Архивыг **өөрийн компьютер дээр хуулж авах**:

```bash
scp manage-dornogovi:/root/manage-backup-*.tar.gz .
```

## Алхам 2 — CloudPanel суулгах

```bash
sudo bash /opt/manage-dornogovi/deploy/cloudpanel/02-install-cloudpanel.sh
```

Скрипт хоёр удаа зөвшөөрөл асууна:
- `ТИЙМ` — хуучин nginx/MySQL/PHP устгахыг зөвшөөрөх
- `ok` — татсан суулгагчийн SHA256 нь [CloudPanel-ийн албан ёсны заавар](https://www.cloudpanel.io/docs/v2/getting-started/debian/)
  дээрхтэй таарсныг баталгаажуулах (хатуу бичээгүй — хувилбар бүрт өөрчлөгддөг)

Дуусахад `https://<серверийн-IP>:8443` дээр админ хэрэглэгч үүсгэнэ.

## Алхам 3 — сайтыг сэргээх

CloudPanel дотор **гараар**:

| Хийх зүйл | Утга |
|---|---|
| Sites → Add Site → PHP Site | Domain `manage.dornogovi.gov.mn`, PHP 8.3, Site User `manage` |
| Databases → Add Database | Name `manage_dornogovi`, User `manage_user` |

Хоёр нууц үгээ тэмдэглэж аваад:

```bash
sudo bash /opt/manage-dornogovi/deploy/cloudpanel/03-restore-site.sh
```

Скрипт кодыг байрлуулж, өгөгдлийн санг сэргээж, build хийж, **автомат шинэчлэлтийн
cron-ыг шинэ зам руу заана**.

---

## Буцаах (rollback)

Ямар нэг зүйл буруудвал — mcloud консол → Snapshot нөөцлөлт → сэргээх.
Өөр арга байхгүй, учир нь Алхам 2 хуучин стекийг бүрмөсөн устгадаг.

## Шилжсэний дараа өөрчлөгдөх зүйлс

| | Өмнө | Дараа |
|---|---|---|
| Веб root | `/var/www/manage.dornogovi.gov.mn` | `/home/manage/htdocs/manage.dornogovi.gov.mn` |
| Эзэмшигч | `www-data` | `manage` (сайтын хэрэглэгч) |
| DB | MySQL 8 | MariaDB 11.4 |
| nginx тохиргоо | `/etc/nginx/sites-available/` | CloudPanel-ийн UI дотроос |
| Замын тохиргоо | (хатуу бичсэн) | `/etc/manage-dornogovi.conf` |

`/opt/manage-dornogovi` (git эх код) болон cron-ийн 2 минутын автомат шинэчлэлт
хэвээр ажиллана.

## HTTPS

Шилжсэний дараа SSL-г **CloudPanel-ийн UI дотроос** тохируулна
(Sites → сайт → SSL/TLS), `../go-public.sh` биш. Тэр скрипт нь nginx-ийг шууд
засдаг тул CloudPanel-ийн тохиргоог эвдэнэ.

Public IP гарсны дараа CloudPanel дотроос Let's Encrypt гэрчилгээг товч дараад авна.
