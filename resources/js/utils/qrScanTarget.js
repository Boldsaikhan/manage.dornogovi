/**
 * Компьютерийн нэвтрэх / сан нээх QR-аас баталгаажуулах хаяг гаргана.
 *
 * @param {string} raw
 * @returns {string|null} Inertia-д зочлох path (эсвэл бүрэн URL)
 */
export function qrScanTarget(raw) {
    const text = String(raw ?? '').trim();

    if (text === '') {
        return null;
    }

    try {
        const url = new URL(text, window.location.origin);
        const match = url.pathname.match(/\/qr\/([^/?#]+)/);

        if (match) {
            return typeof route === 'function'
                ? route('login.qr.show', decodeURIComponent(match[1]))
                : `/qr/${decodeURIComponent(match[1])}`;
        }
    } catch {
        // URL биш
    }

    if (/^[A-Za-z0-9]{32,128}$/.test(text)) {
        return typeof route === 'function'
            ? route('login.qr.show', text)
            : `/qr/${text}`;
    }

    return null;
}
