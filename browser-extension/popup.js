/**
 * Өргөтгөлийн товч дээр дарахад гарах тохиргооны цонх.
 */

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

refresh();
setInterval(refresh, 1000);
