/**
 * Нэвтрэх мэдээллийг зөвхөн богино хугацаанд, session санах ойд хадгална.
 * Browser хаагдахад автоматаар устана, диск дээр бичигдэхгүй.
 */

const TTL_MS = 90_000;

const key = (host) => `pending:${host}`;

// Мэдээлэл хадгалуулж чадах платформын хаягууд (production + локал хөгжүүлэлт).
const ALLOWED_ORIGINS = [
    'https://manage.dornogovi.gov.mn/',
    'http://localhost/manage.dornogovi.gov.mn/',
];

const isTrustedSender = (url) =>
    typeof url === 'string' && ALLOWED_ORIGINS.some((origin) => url.startsWith(origin));

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    // Зөвхөн өөрийн платформын хуудас мэдээлэл хадгалуулж чадна.
    if (message?.type === 'store') {
        if (!isTrustedSender(sender.url)) {
            sendResponse({ ok: false });

            return true;
        }

        chrome.storage.session
            .set({
                [key(message.host)]: {
                    username: message.username,
                    password: message.password,
                    expiresAt: Date.now() + TTL_MS,
                },
            })
            .then(() => sendResponse({ ok: true }));

        return true;
    }

    if (message?.type === 'take') {
        const k = key(message.host);

        chrome.storage.session.get(k).then((data) => {
            const entry = data[k];
            // Нэг удаа ашиглаад шууд устгана.
            chrome.storage.session.remove(k);

            if (!entry || entry.expiresAt < Date.now()) {
                sendResponse(null);

                return;
            }

            sendResponse({ username: entry.username, password: entry.password });
        });

        return true;
    }

    return false;
});
