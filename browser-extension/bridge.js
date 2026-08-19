/**
 * Manage Dornogovi платформын нэвтрэх хуудсанд ажиллана.
 *
 * Хуудас өргөтгөл суусан эсэхийг мэдэхийн тулд тэмдэг тавьж, дараа нь хуудсаас ирсэн
 * нэвтрэх мэдээллийг хүлээж авч, зорилтот системд зориулж түр хадгална.
 */

document.documentElement.dataset.mdExtension = '1';

window.addEventListener('message', (event) => {
    // Зөвхөн энэ хуудас өөрөө илгээсэн мессежийг хүлээн авна.
    if (event.source !== window || event.origin !== window.location.origin) {
        return;
    }

    const data = event.data;

    if (data?.type !== 'md-autologin' || !data.host || !data.username || !data.password) {
        return;
    }

    chrome.runtime.sendMessage(
        {
            type: 'store',
            host: data.host,
            username: data.username,
            password: data.password,
        },
        () => {
            window.postMessage({ type: 'md-autologin-ready' }, window.location.origin);
        },
    );
});
