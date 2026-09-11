# manage.dornogovi.gov.mn

Дорноговь аймгийн дотоод систем. Laravel 11 + Inertia + Vue 3 + Tailwind.

## Дизайны нэгдсэн дүрэм (заавал)

**Шинэ цэс, хуудас, бүрэлдэхүүн нэмэхдээ өөр дизайн зохиохгүй.** Бүх UI-г
`resources/css/app.css`-д тодорхойлсон үндсэн системээс авна. Цэс болгон өөр
харагдвал энэ дүрэм зөрчигдсөн гэсэн үг.

Байгаа хэрэгслээ эхлээд хайгаарай — шинэ Tailwind анги цувуулан бичихийн
өмнө `app.css`-ээс тохирох `.ui-*` анги байгаа эсэхийг шалгана.

| Хэрэгцээ | Ашиглах |
|---|---|
| Хуудасны байрлал | `.ui-page` (хэсгүүдийн хооронд зай) |
| Хайрцаг, самбар | `.ui-card`, `.ui-card-pad` |
| Гарчиг | `.ui-title`, `.ui-subtitle` |
| Талбарын нэр, оролт | `.ui-label`, `.ui-input` |
| Товч | `.ui-btn-primary` (үндсэн), `.ui-btn-accent` (улбар шар — гол үйлдэл), `.ui-btn-ghost` (хоёрдогч), `.ui-btn-danger` (устгах) |
| Зөвхөн icon-той товч | `.ui-icon-btn`, `.ui-icon-btn--danger` |
| Энгийн хүснэгт | `.ui-table-wrap` + `.ui-table` |
| **Бүртгэлийн хүснэгт** | `.ui-register` + `.ui-register__table` — толгой наалдах, багана тус бүрийн хайлт (`.ui-register__filters`), мөрийн ээлжилсэн өнгө, `.ui-register__cell--no`, `.ui-register__empty` бүгд бэлэн |
| Хүснэгтийн гүйлгэх хүрээ | `TableScrollViewport` бүрэлдэхүүн (наалдсан толгой, тусдаа хэвтээ зурвас) |
| Цонх | `Modal` бүрэлдэхүүн. `max-width` нь зөвхөн `sm lg xl 2xl 4xl 5xl 6xl 7xl` утгыг мэднэ — бусдыг өгвөл өргөн хязгаарлагдахгүй |
| Нэг мөрөнд багтаах / хоёр мөр | `.ui-clamp-1`, `.ui-clamp-2` |

Өнгө: `brand-navy-*` (үндсэн), `brand-orange-*` (онцлох), `slate-*` (саарал).
Тэдгээрийг `tailwind.config.js`-ээс өөрчилнө — hex кодыг шууд бичихгүй.

Бүртгэлийн хүснэгтийн бүтэн жишээг [Decrees.vue](resources/js/Pages/Modules/Decrees.vue)
болон [ResourceIndex.vue](resources/js/Pages/Modules/ResourceIndex.vue)-ээс
хараарай — шинэ бүртгэлийн хуудсыг тэднээс хуулж эхлэх нь зөв.

## Хөгжүүлэлтийн урсгал

Өөрчлөлт хийсний дараа заавал:

```bash
php artisan test
npx vite build
git commit          # тайлбар нь монголоор
git push origin demo
bash deploy/promote-to-production.sh
```

`demo` = хөгжүүлэлт, `main` = production. Сервер дээр cron 2 минут тутамд
`deploy/auto-update.sh` ажиллаж татна.

## Анхаарах

- Систем **2026-09-09-нээс жинхэнэ ашиглалтад** байгаа. Өгөгдөл устгах
  migration, эрх, дугаарлалт, гуравдагч үйлчилгээ хөндөх өөрчлөлт хийхээс
  өмнө сануулж зөвшөөрөл авна.
- `APP_KEY`-г хэзээ ч солихгүй — `user_credentials` түүгээр шифрлэгдсэн.
