/**
 * Хөнгөн service worker — апп болгон суулгах (PWA) болон статик файлын кэш.
 *
 * Хуудсуудыг сүлжээнээс авна (мэдээлэл шинэ байх ёстой), сүлжээгүй үед
 * офлайн мэдэгдэл харуулна. Build-ийн файлууд кэшлэгдэнэ.
 */

const VERSION = 'v1';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;

const OFFLINE_HTML = `<!DOCTYPE html><html lang="mn"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Сүлжээгүй байна</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
font-family:Manrope,Arial,sans-serif;background:#f1f5f9;color:#15335d;padding:1.5rem;text-align:center}
.card{background:#fff;border-radius:1rem;padding:2rem;box-shadow:0 6px 24px rgba(15,23,42,.12);max-width:22rem}
h1{font-size:1.05rem;margin:0 0 .5rem}p{font-size:.875rem;color:#64748b;margin:0 0 1rem}
button{background:#1c55a5;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.2rem;font:inherit;cursor:pointer}</style>
</head><body><div class="card"><h1>Сүлжээгүй байна</h1>
<p>Интернэт холболтоо шалгаад дахин оролдоно уу.</p>
<button onclick="location.reload()">Дахин оролдох</button></div></body></html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.put(
            '/offline',
            new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }),
        )).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => ![SHELL_CACHE, ASSET_CACHE].includes(key))
                    .map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // Хуудас — сүлжээнээс, амжилтгүй бол офлайн мэдэгдэл
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline')),
        );

        return;
    }

    // Build-ийн статик файлууд — кэшээс, дараа нь сүлжээнээс
    if (/\/(build|images|icons)\//.test(new URL(request.url).pathname)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));

                return response;
            })),
        );
    }
});
