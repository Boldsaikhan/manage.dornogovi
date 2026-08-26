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
