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

// «gov.mn» мэтийн 2 түвшний өргөтгөлүүд — эдгээрт нэг түвшин илүү авна.
const TWO_LEVEL_SUFFIXES = ['gov.mn', 'org.mn', 'edu.mn', 'com.mn', 'net.mn'];

/**
 * Хостын үндсэн домэйн.
 *
 * Нэвтрэх хуудас өөр дэд домэйн руу шилждэг (erp.e-mongolia.mn → auth.e-mongolia.mn)
 * тул мэдээллийг үндсэн домэйн дотор нь тааруулна. `unelgee.gov.mn` ба
 * `shilen.gov.mn` хоорондоо холилдохгүйн тулд gov.mn-д 3 түвшин авна.
 */
const baseDomain = (host) => {
    // «mail.gov.mn:9071» — порт нь домэйны хэсэг биш.
    const parts = String(host || '').toLowerCase().split(':')[0].split('.').filter(Boolean);

    if (parts.length <= 2) {
        return parts.join('.');
    }

    const lastTwo = parts.slice(-2).join('.');

    return TWO_LEVEL_SUFFIXES.includes(lastTwo)
        ? parts.slice(-3).join('.')
        : lastTwo;
};

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
    const wanted = mode || 'password';
    const matchesMode = (entry) => !! entry && (entry.mode ?? 'password') === wanted;
    const now = Date.now();
    const base = baseDomain(host);

    const session = await chrome.storage.session.get(null);
    const exactKey = key(host);

    let chosenKey = null;
    let fresh = null;

    if (session[exactKey] && session[exactKey].expiresAt >= now && matchesMode(session[exactKey])) {
        chosenKey = exactKey;
        fresh = session[exactKey];
    } else {
        // Нэвтрэх хуудас өөр дэд домэйн руу шилжсэн байж болно.
        for (const [candidateKey, candidate] of Object.entries(session)) {
            if (! candidateKey.startsWith('pending:')) continue;
            if (! candidate || candidate.expiresAt < now || ! matchesMode(candidate)) continue;
            if (baseDomain(candidateKey.slice('pending:'.length)) !== base) continue;

            chosenKey = candidateKey;
            fresh = candidate;
            break;
        }

        // ДАН: систем бүр өөрийн хаягтай ч нэвтрэлт нь dan.gov.mn дээр болдог.
        // Тиймээс ДАН-ы мэдээллийг хостоос үл хамааран авна — энэ скрипт зөвхөн
        // ДАН-ы хуудсанд ажилладаг тул өөр сайтад алдагдахгүй.
        if (! fresh && wanted === 'dan') {
            for (const [candidateKey, candidate] of Object.entries(session)) {
                if (! candidateKey.startsWith('pending:')) continue;
                if (! candidate || candidate.expiresAt < now || ! matchesMode(candidate)) continue;

                chosenKey = candidateKey;
                fresh = candidate;
                break;
            }
        }
    }

    // Хугацаа нь дууссан үлдэгдлийг цэвэрлэнэ.
    const stale = Object.entries(session)
        .filter(([k2, v]) => k2.startsWith('pending:') && (! v || v.expiresAt < now))
        .map(([k2]) => k2);

    if (stale.length) {
        await chrome.storage.session.remove(stale);
    }

    // Ашигласан мэдээллийг л устгана — өөр горимынхыг хөндөхгүй.
    if (chosenKey) {
        await chrome.storage.session.remove(chosenKey);
    }

    let remembered = null;

    if (! fresh) {
        const local = await chrome.storage.local.get(null);
        const exactDevice = local[deviceKey(host)];

        if (matchesMode(exactDevice)) {
            remembered = exactDevice;
        } else {
            for (const [candidateKey, candidate] of Object.entries(local)) {
                if (! candidateKey.startsWith('device:')) continue;
                if (! matchesMode(candidate)) continue;
                if (wanted !== 'dan' && baseDomain(candidateKey.slice('device:'.length)) !== base) continue;

                remembered = candidate;
                break;
            }
        }
    }

    const result = fresh ?? remembered;

    if (! result) return null;

    return {
        mode: result.mode ?? 'password',
        // pending = платформоос шинээр дарсан, device = төхөөрөмжид санасан.
        source: fresh ? 'pending' : 'device',
        username: result.username,
        password: result.password,
    };
};


/* ---------------- Платформ дээр бүртгэсэн системүүд ----------------
 *
 * Manifest-д хаяг бүрийг гараар бичих шаардлагагүй: платформ өөрийн жагсаалтыг
 * илгээж, өргөтгөл зөвшөөрөгдсөн хаягуудад autofill скриптийг өөрөө бүртгэнэ.
 */

const HOSTS_KEY = 'systemHosts';
const DYNAMIC_SCRIPT_ID = 'md-autofill-dynamic';

const originOf = (host) => `https://${host}/*`;

const savedHosts = async () => {
    const { [HOSTS_KEY]: hosts } = await chrome.storage.local.get(HOSTS_KEY);

    return Array.isArray(hosts) ? hosts : [];
};

/** Зөвшөөрөгдсөн хаягуудад л скрипт бүртгэнэ (бусдад нь бүртгэл амжилтгүй болно). */
const grantedHosts = async (hosts) => {
    const checks = await Promise.all(hosts.map(async (host) => {
        try {
            return await chrome.permissions.contains({ origins: [originOf(host)] }) ? host : null;
        } catch {
            return null;
        }
    }));

    return checks.filter(Boolean);
};

const ensureContentScripts = async () => {
    const hosts = await savedHosts();
    const granted = await grantedHosts(hosts);
    const matches = granted.map(originOf);

    try {
        const registered = await chrome.scripting.getRegisteredContentScripts({ ids: [DYNAMIC_SCRIPT_ID] });

        if (! matches.length) {
            if (registered.length) {
                await chrome.scripting.unregisterContentScripts({ ids: [DYNAMIC_SCRIPT_ID] });
            }

            return { registered: 0 };
        }

        const definition = {
            id: DYNAMIC_SCRIPT_ID,
            js: ['autofill.js'],
            matches,
            runAt: 'document_idle',
            allFrames: false,
            persistAcrossSessions: true,
        };

        if (registered.length) {
            await chrome.scripting.updateContentScripts([definition]);
        } else {
            await chrome.scripting.registerContentScripts([definition]);
        }

        return { registered: matches.length };
    } catch (e) {
        return { registered: 0, error: String(e) };
    }
};

/** Платформоос ирсэн жагсаалтыг хадгална. */
const syncHosts = async (hosts) => {
    const clean = [...new Set(
        (Array.isArray(hosts) ? hosts : [])
            .map((host) => String(host || '').trim().toLowerCase())
            .filter((host) => /^[a-z0-9.-]+\.[a-z]{2,}$/.test(host)),
    )];

    await chrome.storage.local.set({ [HOSTS_KEY]: clean });

    const result = await ensureContentScripts();

    return { ok: true, hosts: clean, ...result };
};

/** Popup-д: хаяг бүр зөвшөөрөгдсөн эсэх. */
const hostStatus = async () => {
    const hosts = await savedHosts();
    const granted = await grantedHosts(hosts);

    return {
        hosts: hosts.map((host) => ({ host, granted: granted.includes(host) })),
        origins: hosts.map(originOf),
    };
};

chrome.runtime.onStartup?.addListener(() => ensureContentScripts());
chrome.runtime.onInstalled?.addListener(() => ensureContentScripts());
chrome.permissions.onAdded?.addListener(() => ensureContentScripts());
chrome.permissions.onRemoved?.addListener(() => ensureContentScripts());

const handle = (message, sender, sendResponse, trusted) => {
    if (message?.type === 'store') {
        if (! trusted) {
            sendResponse({ ok: false, reason: 'untrusted' });

            return true;
        }

        store(message).then(sendResponse);

        return true;
    }

    // Платформ өөрийн бүртгэсэн системийн хаягуудыг илгээнэ.
    if (message?.type === 'syncHosts') {
        if (! trusted) {
            sendResponse({ ok: false, reason: 'untrusted' });

            return true;
        }

        syncHosts(message.hosts).then(sendResponse);

        return true;
    }

    if (message?.type === 'hostStatus') {
        hostStatus().then(sendResponse);

        return true;
    }

    if (message?.type === 'refreshScripts') {
        ensureContentScripts().then(sendResponse);

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
