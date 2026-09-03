/**
 * Өргөтгөлийн товч дээр дарахад гарах тохиргооны цонх.
 */

// Толгойд өргөтгөлийн хувилбар — шинэчлэлт суусан эсэхийг шалгахад хэрэгтэй.
const versionBadge = document.getElementById('version');

if (versionBadge) {
    versionBadge.textContent = `v${chrome.runtime.getManifest().version}`;
}

const enabledInput = document.getElementById('enabled');
const pendingList = document.getElementById('pending');
const pendingEmpty = document.getElementById('pendingEmpty');
const clearButton = document.getElementById('clear');

const render = (status) => {
    enabledInput.checked = status.enabled !== false;

    pendingList.innerHTML = '';
    const items = status.pending ?? [];

    pendingEmpty.style.display = items.length ? 'none' : 'block';

    items.forEach((item) => {
        const li = document.createElement('li');

        const site = document.createElement('span');
        site.className = 'site';
        site.textContent = item.host;

        const badge = document.createElement('span');
        badge.className = 'badge ok';
        badge.textContent = `${item.secondsLeft} сек`;

        li.append(site, badge);
        pendingList.append(li);
    });
};

const refresh = () => {
    chrome.runtime.sendMessage({ type: 'status' }, (status) => {
        if (status) render(status);
    });
};

enabledInput.addEventListener('change', () => {
    chrome.runtime.sendMessage({ type: 'setEnabled', value: enabledInput.checked }, refresh);
});

document.getElementById('uninstall').addEventListener('click', () => {
    chrome.management.uninstallSelf({ showConfirmDialog: true });
});

// Тухайн төхөөрөмжид санасан нэвтрэх мэдээллийг бүрэн устгана.
document.getElementById('forgetDevice').addEventListener('click', (event) => {
    const button = event.currentTarget;

    chrome.runtime.sendMessage({ type: 'forgetDevice' }, (result) => {
        button.textContent = `Устгалаа (${result?.removed ?? 0})`;
        setTimeout(() => (button.textContent = 'Төхөөрөмжид санасныг устгах'), 1800);
        refresh();
    });
});

clearButton.addEventListener('click', () => {
    chrome.runtime.sendMessage({ type: 'clear' }, refresh);
});


/* ---------------- Бүртгэсэн системүүд ---------------- */

const hostList = document.getElementById('systemHosts');
const hostEmpty = document.getElementById('systemHostsEmpty');
const connectButton = document.getElementById('connectHosts');
const connectHint = document.getElementById('connectHint');

let pendingOrigins = [];

const renderHosts = (status) => {
    const items = status?.hosts ?? [];
    const missing = items.filter((item) => ! item.granted);

    hostList.innerHTML = '';
    hostEmpty.style.display = items.length ? 'none' : 'block';

    items.forEach((item) => {
        const li = document.createElement('li');

        const site = document.createElement('span');
        site.className = 'site';
        site.textContent = item.host;

        const badge = document.createElement('span');
        badge.className = item.granted ? 'badge ok' : 'badge muted';
        badge.textContent = item.granted ? 'холбогдсон' : 'зөвшөөрөл хэрэгтэй';

        li.append(site, badge);
        hostList.append(li);
    });

    pendingOrigins = missing.map((item) => `https://${item.host}/*`);

    connectButton.style.display = missing.length ? 'block' : 'none';
    connectHint.textContent = missing.length
        ? `${missing.length} систем зөвшөөрөл хүлээж байна.`
        : 'Бүх систем холбогдсон.';
};

const refreshHosts = () => {
    chrome.runtime.sendMessage({ type: 'hostStatus' }, (status) => {
        if (! chrome.runtime.lastError && status) renderHosts(status);
    });
};

connectButton.addEventListener('click', () => {
    if (! pendingOrigins.length) return;

    // Зөвшөөрөл нь хэрэглэгчийн товшилтоос л асуугддаг тул энд дуудна.
    chrome.permissions.request({ origins: pendingOrigins }, (granted) => {
        connectHint.textContent = granted ? 'Холболоо.' : 'Зөвшөөрөл өгөгдсөнгүй.';
        chrome.runtime.sendMessage({ type: 'refreshScripts' }, refreshHosts);
    });
});

refreshHosts();

refresh();
setInterval(refresh, 1000);
