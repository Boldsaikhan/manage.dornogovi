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

    await chrome.storage.session.set({
        [key(message.host)]: {
            username: message.username,
            password: message.password,
            expiresAt: Date.now() + TTL_MS,
        },
    });

    return { ok: true };
};

const take = async (host) => {
    const k = key(host);
    const data = await chrome.storage.session.get(k);
    const entry = data[k];

    // Нэг удаа ашиглаад шууд устгана.
    await chrome.storage.session.remove(k);

    if (! entry || entry.expiresAt < Date.now()) {
        return null;
    }

    return { username: entry.username, password: entry.password };
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
        take(message.host).then(sendResponse);

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

    if (message?.type === 'clear') {
        chrome.storage.session.clear().then(() => sendResponse({ ok: true }));

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
