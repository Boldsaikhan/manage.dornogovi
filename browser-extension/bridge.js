/**
 * Manage Dornogovi платформын хуудсанд ажиллана.
 *
 * Зөвхөн «өргөтгөл суусан» гэсэн тэмдэг тавина. Нэвтрэх мэдээлэл нь хуудаснаас
 * шууд өргөтгөл рүү (chrome.runtime.sendMessage, externally_connectable) явдаг
 * тул энэ скриптээр дамжихгүй — өөр өргөтгөл, скрипт харах боломжгүй.
 *
 * Хуучин хувилбартай нийцүүлэх нөөц зам ч үлдээв: хэрэв хуудас шууд илгээж
 * чадаагүй бол postMessage-ээр дамжуулна.
 */

document.documentElement.dataset.mdExtension = '1';
document.documentElement.dataset.mdExtensionId = chrome.runtime.id;

/**
 * Платформ дээр бүртгэсэн системийн хаягуудыг өргөтгөлд мэдэгдэнэ.
 *
 * Хуудас нь `data-md-system-hosts` шинжид таслалаар тусгаарлан бичдэг.
 */
const syncSystemHosts = () => {
    const raw = document.documentElement.dataset.mdSystemHosts;

    if (! raw) return;

    const hosts = raw.split(',').map((host) => host.trim()).filter(Boolean);

    if (! hosts.length) return;

    chrome.runtime.sendMessage({ type: 'syncHosts', hosts }, () => void chrome.runtime.lastError);
};

// Inertia нь хуудас солигдоход шинжийг шинэчилдэг тул ажиглана.
new MutationObserver(syncSystemHosts).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-md-system-hosts'],
});

document.addEventListener('DOMContentLoaded', syncSystemHosts);
syncSystemHosts();

window.addEventListener('message', (event) => {
    // Зөвхөн энэ хуудас өөрөө илгээсэн мессежийг хүлээн авна.
    if (event.source !== window || event.origin !== window.location.origin) {
        return;
    }

    const data = event.data;

    if (data?.type !== 'md-autologin' || ! data.host || ! data.username || ! data.password) {
        return;
    }

    chrome.runtime.sendMessage(
        {
            type: 'store',
            host: data.host,
            mode: data.mode ?? 'password',
            remember: !! data.remember,
            username: data.username,
            password: data.password,
        },
        () => {
            window.postMessage({ type: 'md-autologin-ready' }, window.location.origin);
        },
    );
});
