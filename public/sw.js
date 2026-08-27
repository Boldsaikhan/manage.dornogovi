/**
 * Хөнгөн service worker — PWA суулгалт.
 *
 * Хуудсуудыг ЗӨВХӨН сүлжээнээс авна. Сүлжээ тасарсан үед л офлайн харуулна.
 * Build файлууд: сүлжээ эхлээд, дараа нь кэш (шинэ deploy эвдэрэхгүй).
 */

const VERSION = 'v10-20260827-ios-csrf';
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
<script>
setTimeout(function(){ location.reload(); }, 5000);
</script>
</div></body></html>`;

const MAINT_HTML = `<!DOCTYPE html><html lang="mn"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Шинэчлэлт хийгдэж байна</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
font-family:Manrope,Arial,sans-serif;background:#f1f5f9;color:#15335d;padding:1.5rem;text-align:center}
.card{background:#fff;border-radius:1rem;padding:2rem;box-shadow:0 6px 24px rgba(15,23,42,.12);max-width:22rem}
h1{font-size:1.05rem;margin:0 0 .5rem}p{font-size:.875rem;color:#64748b;margin:0 0 1rem;line-height:1.45}
button{background:#1c55a5;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.2rem;font:inherit;cursor:pointer}</style>
</head><body><div class="card"><h1>Систем шинэчлэгдэж байна</h1>
<p>Хэдэн секундын дараа бэлэн болно. Хуудас өөрөө сэргэнэ.</p>
<button onclick="location.reload()">Дахин оролдох</button>
<script>setTimeout(function(){location.reload()},8000)<\/script>
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
    // АНХААР: navigate хүсэлтэд дагасан (redirected) хариуг буцаавал Chrome
    // ERR_FAILED өгдөг. Төрийн системд байнгын шилжилт (/ → /login) байдаг тул
    // redirect: 'manual' ашиглаж, шилжилтийг хөтөчтөө өөрт нь дагуулна.
    const load = () => fetch(request, {
        credentials: 'same-origin',
        redirect: 'manual',
        cache: 'no-store',
    });

    try {
        const response = await load();

        // Шилжилт (opaqueredirect) — шууд буцаана, хөтөч дагана.
        if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
            return response;
        }

        // Deploy явагдаж байгаа үе (503) — нэг удаа дахин оролдоод,
        // болохгүй бол ойлгомжтой хуудас үзүүлнэ (ERR_FAILED гаргахгүй).
        if (response.status === 503) {
            await new Promise((r) => setTimeout(r, 1500));

            try {
                const retry = await load();

                if (retry.status !== 503) {
                    return retry;
                }
            } catch {
                // доорх хуудсаар хариулна
            }

            return new Response(MAINT_HTML, {
                headers: { 'Content-Type': 'text/html; charset=utf-8' },
                status: 503,
            });
        }

        return response;
    } catch {
        // DNS/сүлжээ түр доголдлыг давахын тулд хэд хэдэн удаа дахин оролдоно.
        const url = new URL(request.url);
        const retries = [450, 1200, 2200];

        for (const delay of retries) {
            await new Promise((r) => setTimeout(r, delay));
            try {
                const retry = await fetch(url.pathname + url.search, {
                    credentials: 'same-origin',
                    redirect: 'follow',
                    cache: 'no-store',
                    headers: { Accept: 'text/html' },
                });

                if (retry) {
                    return retry;
                }
            } catch {
                // дараагийн оролдлого руу шилжинэ
            }
        }

        return (await caches.match('/offline')) || new Response(OFFLINE_HTML, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
            status: 503,
        });
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

    // Хуудас нээхэд SW бүү зуучил. iOS/WebKit дээр SW-ийн fetch-ийн
    // Set-Cookie cookie jar-д орохгүй → CSRF тасарч 419 PAGE EXPIRED гарна.
    // Офлайн үед л өөрийн хуудсыг харуулна.
    if (request.mode === 'navigate') {
        if (navigator.onLine) {
            return;
        }

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

/** Push мэдэгдэл — албан хаагчид холбоотой мэдээлэл */
self.addEventListener('push', (event) => {
    let data = {
        title: 'Дорноговь',
        body: 'Шинэ мэдэгдэл байна.',
        url: '/dept-dashboard',
        tag: 'manage-dornogovi',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
    };

    try {
        if (event.data) {
            data = { ...data, ...event.data.json() };
        }
    } catch {
        try {
            data.body = event.data?.text() || data.body;
        } catch {
            // ignore
        }
    }

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(data.title || 'Дорноговь', {
                body: data.body || '',
                icon: data.icon || '/icons/icon-192.png',
                badge: data.badge || '/icons/icon-192.png',
                tag: data.tag || 'manage-dornogovi',
                data: { url: data.url || '/dept-dashboard' },
                renotify: true,
            }),
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
                const payload = {
                    title: data.title,
                    body: data.body,
                    url: data.url || '/dept-dashboard',
                    at: new Date().toISOString(),
                };
                clients.forEach((client) => {
                    client.postMessage({ type: 'PUSH_NOTIFICATION', payload });
                });
            }),
        ]),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/dept-dashboard';
    const abs = new URL(target, self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate?.(abs);

                    return client.focus();
                }
            }

            if (self.clients.openWindow) {
                return self.clients.openWindow(abs);
            }

            return undefined;
        }),
    );
});
