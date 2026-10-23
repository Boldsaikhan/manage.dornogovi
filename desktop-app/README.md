# Manage Dornogovi — Windows ширээний програм

Системийг тусдаа цонхонд ачаалж, «Холбосон системүүд» дээр дарахад
нэвтрэх мэдээллийг зорилтот сайтын маягтад автоматаар бөглөнө.

## Бүтэц

| Файл | Үүрэг |
|---|---|
| `src/main.js` | Үндсэн процесс — цонх нээх, мэдээллийг санах ойд барих |
| `src/preload-app.js` | Системийн хуудсанд `window.manageDesktop` гүүр өгнө |
| `src/preload-target.js` | Холбосон системийн цонхонд маягт бөглөнө |
| `src/autofill.js` | Маягт олох, бөглөх, илгээх логик |

## Суулгац бэлтгэх

```bash
cd desktop-app
npm install
npm run dist
```

`dist/Manage-Dornogovi-Setup.exe` үүснэ. Түүнийг
`storage/app/desktop/` хавтас руу хуулахад систем нь
«Програм татах» товчоор тараана.

## Аюулгүй байдал

- Нэвтрэх мэдээлэл **зөвхөн санах ойд** байна, дискэнд бичигдэхгүй.
- 10 минутын дараа, эсвэл нэг удаа ашигласны дараа устана.
- Зөвхөн `manage.dornogovi.gov.mn` хуудас мэдээлэл дамжуулж чадна.
- Зорилтот сайтын цонхонд ямар ч API нээгдэхгүй — зөвхөн DOM дээр бөглөнө.

## Хөгжүүлэх

```bash
npm start                      # production сервер рүү холбогдоно
MD_SITE_URL=http://localhost npm start
```

## Windows дээр бүтээхэд гарч болох алдаа

electron-builder нь кодын гэрийн баталгааны хэрэгслээ 7z-ээс задлахдаа
macOS-ийн symlink үүсгэж чаддаггүй (`A required privilege is not held`).
Шийдэл — тэр хавтсыг `darwin`-гүйгээр гараар задлах:

```bash
C="$LOCALAPPDATA/electron-builder/Cache/winCodeSign"
./node_modules/7zip-bin/win/x64/7za.exe x -snld -y '-xr!darwin' \
    "$C/<татсан>.7z" "-o$C/winCodeSign-2.6.0"
npm run dist
```
