# manage.dornogovi.gov.mn — deploy

Сервер: mcloud.gov.mn / `manage-dornogovi`, Ubuntu 24.04.2 LTS, 8 vCPU / 16 GB / 100 GB
Серверийн хаяг, хэрэглэгчийн нэрийг НДТ-ийн консолоос харна (энд бичихгүй).
Нэвтрэлт: SSH түлхүүр (mcloud → SSH Түлхүүр).

> Сервер нь private хаягтай — зөвхөн төрийн сүлжээ/НДТ-ийн VPN дотроос хандана.

## 1. Орчин бэлдэх (нэг удаа)

```bash
scp -r deploy $SERVER_USER@$SERVER_IP:~/
ssh $SERVER_USER@$SERVER_IP
sudo bash ~/deploy/provision.sh          # DB нууц үг эхлэхэд хэвлэгдэнэ
```

## 2. Кодыг хуулах

Локал дээр (vendor, node_modules, .env-гүйгээр):

```bash
tar --exclude=node_modules --exclude=vendor --exclude=.env --exclude=.git \
    --exclude=storage/logs --exclude=public/build \
    -czf manage.tar.gz -C /c/xampp/htdocs manage.dornogovi.gov.mn
scp manage.tar.gz $SERVER_USER@$SERVER_IP:~/
ssh $SERVER_USER@$SERVER_IP 'sudo tar -xzf ~/manage.tar.gz -C /var/www/'
```

## 3. .env

```bash
sudo cp /var/www/manage.dornogovi.gov.mn/deploy/env.production /var/www/manage.dornogovi.gov.mn/.env
sudo nano /var/www/manage.dornogovi.gov.mn/.env    # DB_PASSWORD-г provision.sh хэвлэсэн нууц үгээр бөглө
```

## 4. Байрлуулах / шинэчлэх

```bash
sudo bash /var/www/manage.dornogovi.gov.mn/deploy/release.sh
```

## 5. HTTPS

Домайн дотоод IP-тэй тул Let's Encrypt-ийн HTTP-01 сорил гаднаас хүрэхгүй. Сонголтууд:
- Байгууллагын/НДТ-ийн гэрчилгээг `/etc/ssl/` дотор байрлуулж nginx-д `listen 443 ssl` блок нэмэх
- Эсвэл DNS-01 сорилоор certbot ашиглах (домайны DNS удирдлага хэрэгтэй)

Гэрчилгээ суусны дараа `.env` дэх `APP_URL`-ыг `https://…` болгож `php artisan config:cache` дахин ажиллуулна.

## Анхаарах
- Browser extension-ий `manifest.json` / `background.js` дотор байгаа localhost хаягуудыг
  production домайн руу солих шаардлагатай.
- `SESSION_SECURE_COOKIE=true` — HTTPS ажиллаж эхлэх хүртэл `false` байлга, эсхүл нэвтрэлт ажиллахгүй.
