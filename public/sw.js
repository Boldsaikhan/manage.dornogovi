/**
 * Хөнгөн service worker — PWA суулгалт.
 *
 * Хуудсуудыг ЗӨВХӨН сүлжээнээс авна. Сүлжээ тасарсан үед л офлайн харуулна.
 * Build файлууд: сүлжээ эхлээд, дараа нь кэш (шинэ deploy эвдэрэхгүй).
 */

const VERSION = 'v4-20260826-lock';
const SHELL_CACHE = `shell-${VERSION}`;
const ASSET_CACHE = `assets-${VERSION}`;

const OFFLINE_HTML = `<!DOCTYPE html><html lang="mn"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Сүлжээгүй байна</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
font-family:Manrope,Arial,sans-serif;background:#f1f5f9;color:#15335d;padding:1.5rem;text-align:center}
.card{background:#fff;border-radius:1rem;padding:2rem;box-shadow:0 6px 24px rgba(15,23,42,.12);max-width:22rem}
h1{font-size:1.05rem;margin:0 0 .5rem}p{font-size:.875rem;color:#64748b;margin:0 0 1rem;line-height:1.45}
button{background:#1c55a5;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.2rem;font:inherit;cursor:pointer;margin:.25rem}
.ghost{background:#e2e8f0;color:#15335d}</style>
</head><body><div class="card"><h1>Сүлжээгүй байна</h1>
<p>Интернэт холболтоо шалгаад дахин оролдоно уу.<br>
Хэрэв холболт байгаа бол доорх товчоор кэш цэвэрлээд дахин нээнэ үү.</p>
<button onclick="location.reload()">Дахин оролдох</button>
<button class="ghost" onclick="navigator.serviceWorker.getRegistrations().then(r=>Promise.all(r.map(x=>x.unregister()))).then(()=>caches.keys().then(k=>Promise.all(k.map(caches.delete.bind(caches)))).then(()=>location.href='/'))">Кэш цэвэрлэж нээх</button>
</div></body></html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.put(
            '/offline',
            new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } }),
        )).then(() => self.skipWaiting()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
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

/** Navigate: сүлжээ → дахин оролдох → офлайн */
async function networkFirstPage(request) {
    try {
        return await fetch(request, { credentials: 'same-origin', redirect: 'follow', cache: 'no-store' });
    } catch {
        // Богино завсарлагатай дахин оролдоно (утсан дээр түр зуурын тасалдал)
        try {
            await new Promise((r) => setTimeout(r, 400));
            const url = new URL(request.url);
            return await fetch(url.pathname + url.search, {
                credentials: 'same-origin',
                redirect: 'follow',
                cache: 'no-store',
                headers: { Accept: 'text/html' },
            });
        } catch {
            return (await caches.match('/offline')) || new Response(OFFLINE_HTML, {
                headers: { 'Content-Type': 'text/html; charset=utf-8' },
                status: 503,
            });
        }
    }
}

/** Статик: сүлжээ эхлээд, амжилтгүй бол кэш */
async function networkFirstAsset(request) {
    try {
        const response = await fetch(request, { credentials: 'same-origin' });
        if (response.ok) {
            const copy = response.clone();
            caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy)).catch(() => {});
        }

        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        throw new Error('asset unavailable');
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    let url;
    try {
        url = new URL(request.url);
    } catch {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    // API / Inertia XHR — SW бүү саад бол
    if (request.headers.get('X-Inertia') || request.headers.get('X-Requested-With') === 'XMLHttpRequest') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));

        return;
    }

    if (/\/(build|images|icons)\//.test(url.pathname)) {
        event.respondWith(
            networkFirstAsset(request).catch(
                () => new Response('', { status: 504, statusText: 'Gateway Timeout' }),
            ),
        );
    }
});
