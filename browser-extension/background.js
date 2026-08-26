/**
 * Нэвтрэх мэдээллийг зөвхөн богино хугацаанд, session санах ойд хадгална.
 * Browser хаагдахад автоматаар устана, диск дээр бичигдэхгүй.
 *
 * Мэдээллийг платформын хуудас нь `chrome.runtime.sendMessage(EXT_ID, …)`-ээр
 * шууд өргөтгөл рүү илгээдэг тул хуудсан дээрх бусад скрипт, өргөтгөл харах
 * боломжгүй (өмнөх postMessage хувилбар нь зөвхөн нөөц зам болж үлдсэн).
 */

const TTL_MS = 90_000;

const key = (host) => `pending:${host}`;

// Төхөөрөмжийн санах (chrome.storage.local) — браузер хаагдсан ч үлдэнэ.
const deviceKey = (host) => `device:${host}`;

// Мэдээлэл хадгалуулж чадах платформын хаягууд (production + локал хөгжүүлэлт).
const ALLOWED_ORIGINS = [
    'https://manage.dornogovi.gov.mn/',
    'http://manage.dornogovi.gov.mn/',
    'http://localhost/manage.dornogovi.gov.mn/',
];

const isTrustedSender = (url) =>
    typeof url === 'string' && ALLOWED_ORIGINS.some((origin) => url.startsWith(origin));

/** Автоматаар бөглөх эсэх — хэрэглэгч popup-аас унтраах боломжтой. */
const isEnabled = async () => {
    const { autofillEnabled } = await chrome.storage.local.get('autofillEnabled');

    return autofillEnabled !== false;
};

const store = async (message) => {
    if (! (await isEnabled())) {
        return { ok: false, reason: 'disabled' };
    }

    const entry = {
        mode: message.mode ?? 'password',
        username: message.username,
        password: message.password,
    };

    await chrome.storage.session.set({
        [key(message.host)]: { ...entry, expiresAt: Date.now() + TTL_MS },
    });

    // «Энэ төхөөрөмжийг санах» — дараа удаа платформыг дамжахгүй шууд нэвтэрнэ.
    if (message.remember) {
        await chrome.storage.local.set({ [deviceKey(message.host)]: entry });
    } else {
        await chrome.storage.local.remove(deviceKey(message.host));
    }

    return { ok: true };
};

const take = async (host, mode) => {
    const k = key(host);
    const data = await chrome.storage.session.get(k);
    const entry = data[k];

    // Нэг удаа ашиглаад шууд устгана.
    await chrome.storage.session.remove(k);

    const fresh = entry && entry.expiresAt >= Date.now() ? entry : null;

    // Платформаас ирсэн мэдээлэл байхгүй бол төхөөрөмжид санасаныг авна.
    const remembered = fresh
        ? null
        : (await chrome.storage.local.get(deviceKey(host)))[deviceKey(host)] ?? null;

    const result = fresh ?? remembered;

    if (! result) return null;

    // Хүссэн горимтой таарахгүй бол өгөхгүй (dan — password).
    if (mode && (result.mode ?? 'password') !== mode) return null;

    return { mode: result.mode ?? 'password', username: result.username, password: result.password };
};

const handle = (message, sender, sendResponse, trusted) => {
    if (message?.type === 'store') {
        if (! trusted) {
            sendResponse({ ok: false, reason: 'untrusted' });

            return true;
        }

        store(message).then(sendResponse);

        return true;
    }

    if (message?.type === 'take') {
        take(message.host, message.mode).then(sendResponse);

        return true;
    }

    // popup-д зориулсан төлөв
    if (message?.type === 'status') {
        Promise.all([chrome.storage.session.get(null), isEnabled()]).then(([session, enabled]) => {
            const pending = Object.entries(session)
                .filter(([k, v]) => k.startsWith('pending:') && v?.expiresAt > Date.now())
                .map(([k, v]) => ({
                    host: k.replace('pending:', ''),
                    secondsLeft: Math.max(0, Math.round((v.expiresAt - Date.now()) / 1000)),
                }));

            sendResponse({ enabled, pending });
        });

        return true;
    }

    if (message?.type === 'setEnabled') {
        chrome.storage.local.set({ autofillEnabled: !! message.value })
            .then(() => sendResponse({ ok: true }));

        return true;
    }

    // Хэрэглэгчийн хүсэлтээр өргөтгөл өөрөө өөрийгөө устгана.
    if (message?.type === 'uninstall') {
        if (! trusted) {
            sendResponse({ ok: false, reason: 'untrusted' });

            return true;
        }

        chrome.management.uninstallSelf({ showConfirmDialog: true })
            .then(() => sendResponse({ ok: true }))
            .catch(() => sendResponse({ ok: false }));

        return true;
    }

    if (message?.type === 'clear') {
        chrome.storage.session.clear().then(() => sendResponse({ ok: true }));

        return true;
    }

    // Төхөөрөмжид санасан бүх мэдээллийг устгана.
    if (message?.type === 'forgetDevice') {
        chrome.storage.local.get(null).then((all) => {
            const keys = Object.keys(all).filter((k) => k.startsWith('device:'));

            chrome.storage.local.remove(keys).then(() => sendResponse({ ok: true, removed: keys.length }));
        });

        return true;
    }

    return false;
};

// Өргөтгөлийн дотоод (content script, popup) мессежүүд
chrome.runtime.onMessage.addListener((message, sender, sendResponse) =>
    handle(message, sender, sendResponse, isTrustedSender(sender.url) || sender.id === chrome.runtime.id));

// Платформын хуудаснаас шууд ирэх мессеж (externally_connectable)
chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) =>
    handle(message, sender, sendResponse, isTrustedSender(sender.url ?? sender.origin)));
