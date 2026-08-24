# manage.dornogovi.gov.mn — deploy

Сервер: mcloud.gov.mn / `manage-dornogovi` (instance-00005750), Ubuntu 24.04.2 LTS,
8 vCPU / 16 GB / 100 GB.

| Зүйл | Утга |
|---|---|
| SSH | `ndc-user@10.52.1.67`, түлхүүр `~/.ssh/manage_dornogovi` |
| Эх код (git) | `/opt/manage-dornogovi` |
| Веб root | `/var/www/manage.dornogovi.gov.mn` |
| Нууц үгс | `/root/.manage_dornogovi_secrets` (DB_PASS, ADMIN_PASSWORD) |
| Cron | `/etc/cron.d/manage-deploy` — 2 мин тутам шинэчлэлт шалгана |
| Лог | `/var/log/manage-deploy.log` |
| Репо | <https://github.com/Boldsaikhan/manage.dornogovi> |

> Сервер private хаягтай — зөвхөн НДТ-ийн VPN дотроос хандана.
> Гаднаас нээх талаар доорх 5-р хэсгийг үзнэ үү.

Локал дээрээс:

```bash
ssh manage-dornogovi        # ~/.ssh/config дотор тохируулсан
```

## 1. Анхны суулгалт (нэг удаа)

mcloud-ийн **вэб консол** дээр нэг мөр буулгана:

```bash
curl -fsSL https://raw.githubusercontent.com/Boldsaikhan/manage.dornogovi/main/deploy/server-setup.sh | sudo bash
```

[server-setup.sh](server-setup.sh) нь idempotent — дахин ажиллуулж болно. Багц суулгах,
DB үүсгэх, кодыг татах, `.env` бэлдэх, build хийх, nginx тохируулах бүхнийг хийгээд
эцэст нь **админы нэвтрэх нэр, нууц үгийг хэвлэнэ**.

Seeder зөвхөн анхны удаад ажиллана — дахин суулгахад хэрэглэгчийн оруулсан өгөгдөл устахгүй.

## 2. Автомат шинэчлэлт (cron)

GitHub-ийн `main` салбарт push хийхэд **2 минутын дотор** сервер өөрөө шинэчлэгдэнэ.

```bash
# нэг удаа суулгах
echo '*/2 * * * * root /opt/manage-dornogovi/deploy/auto-update.sh >> /var/log/manage-deploy.log 2>&1'   | sudo tee /etc/cron.d/manage-deploy

# ажиллаж байгааг харах
sudo tail -f /var/log/manage-deploy.log
```

[auto-update.sh](auto-update.sh) нь шинэ commit байхад л ажиллана, seeder-ийг дахин
ажиллуулдаггүй, `flock`-оор давхар ажиллахаас сэргийлдэг.

## 3. Гараар шинэчлэх

```bash
sudo /opt/manage-dornogovi/deploy/auto-update.sh
```

## 4. Удирдлагын панел (CloudPanel)

Сервэрийг вэб интерфейсээр удирдах бол [cloudpanel/README.md](cloudpanel/README.md)-г
үзнэ үү. CloudPanel нь өөрийн nginx/MariaDB/PHP суулгадаг тул энэ нь энгийн
суулгалт биш, **нүүлгэн шилжүүлэлт** — snapshot заавал шаардлагатай.

## 5. Хуучин скриптүүд

`provision.sh` болон `release.sh` нь `server-setup.sh` гарахаас өмнөх гараар
байрлуулах арга. Одоо хэрэглэхгүй, лавлагаанд үлдээв.

## 6. Гаднаас (интернэтээс) хандах боломж нээх

Домайн одоогоор дотоод хаяг (`10.52.1.67`) руу заасан тул интернэтээс хандах
боломжгүй. Нээхийн тулд эхлээд **хоёр хүсэлт** батлагдах ёстой — төслүүдийг
[REQUESTS.md](REQUESTS.md)-ээс аваарай:

1. **НДТ** — `manage-dornogovi` сервэрт нийтийн (public) IP хуваарилах, 80/443 порт нээх
2. **gov.mn DNS админ** — `manage.dornogovi.gov.mn` A бичлэгийг тэр нийтийн IP руу солих

Хоёулаа биелсний дараа сервер дээр:

```bash
sudo CERTBOT_EMAIL=<админы и-мэйл> bash /var/www/manage.dornogovi.gov.mn/deploy/go-public.sh
```

`go-public.sh` дараах бүхнийг автоматаар хийнэ:

- DNS үнэхээр нийтийн хаяг руу зассан эсэхийг шалгана (эс бөгөөс зогсоно)
- UFW галт хана — зөвхөн 22, 80, 443 нээж, бусдыг хаана
- Let's Encrypt-ийн үнэгүй SSL гэрчилгээ + автомат сунгалт (`certbot.timer`)
- HTTP → HTTPS албадан чиглүүлэлт, HSTS болон аюулгүйн толгойнууд
- fail2ban — нууц үг таах халдлагаас хамгаална
- `.env`-д `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`

### Дараа нь заавал хийх

- Админы нууц үгийг хүчтэй нууц үгээр солих (`AdminUserSeeder`-ийн анхны нууц үг ил байна)
- <https://www.ssllabs.com/ssltest/> дээр шалгах — **A** зэрэг байх ёстой
- Хэрэглэгчдэд 2 хүчин зүйлт баталгаажуулалт нэвтрүүлэхийг судлах
- Сервэрийн лог, нөөцлөлтийг тогтмол хянах

### Хэрэв гаднаас хандах шаардлагагүй бол

Хамгийн аюулгүй хувилбар нь одоогийн байдал — НДТ-ийн VPN-ээр дамжин хандах.
Дотоод сүлжээний өөр компьютерээс хандах бол [add-host-client.ps1](add-host-client.ps1)-ийг
тэр компьютер дээр админ эрхээр ажиллуулна.

## Анхаарах
- Browser extension нь `https://manage.dornogovi.gov.mn` болон локал `http://localhost/...`
  хоёуланг нь дэмждэг болсон. Домайн өөрчлөгдвөл `manifest.json`-ы `host_permissions`,
  `content_scripts` болон `background.js`-ийн `ALLOWED_ORIGINS`-ыг зэрэг засна.
- `SESSION_SECURE_COOKIE=true` — HTTPS ажиллаж эхлэх хүртэл `false` байлга, эсхүл нэвтрэлт
  ажиллахгүй. `go-public.sh` үүнийг автоматаар `true` болгоно.
