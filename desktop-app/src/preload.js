/**
 * Бүх табын preload.
 *
 * Нэг таб нь системээс эхлээд холбосон систем рүү шилждэг тул preload-ыг
 * хуудас бүрт нь ачаалж, тухайн хостоос хамаарч ажиллана:
 *
 *   - системийн хуудас → `window.manageDesktop` гүүрийг нээнэ,
 *   - бусад хост       → хадгалагдсан нэвтрэх мэдээллээр маягтыг бөглөнө.
 */

const { contextBridge, ipcRenderer } = require('electron');
const { runAutofill } = require('./autofill.js');
const { runDanAutofill } = require('./dan-autofill.js');

/** Үндсэн процессоос дамжуулсан системийн хост. */
const siteHost = (process.argv.find((arg) => arg.startsWith('--md-site-host=')) || '')
    .replace('--md-site-host=', '');

const onSite = () => siteHost !== '' && location.host === siteHost;

contextBridge.exposeInMainWorld('manageDesktop', {
    version: process.argv.find((arg) => arg.startsWith('--md-version='))?.replace('--md-version=', '') || '1.0.0',

    /** @param {{host: string, username: string, password: string, remember?: boolean}} payload */
    autoLogin: (payload) => ipcRenderer.invoke('md:store', payload),

    openExternal: (url) => ipcRenderer.invoke('md:external', url),
});

window.addEventListener('DOMContentLoaded', async () => {
    if (onSite()) {
        // Хуудас нь програм дотор ажиллаж байгаагаа мэдэхийн тулд.
        document.documentElement.dataset.mdDesktop = '1';

        return;
    }

    try {
        const credential = await ipcRenderer.invoke('md:take', location.host);

        if (credential) {
            // Бөглөгдсөн хойно нь санах ойгоос устгана.
            const options = { onFilled: () => ipcRenderer.invoke('md:used', location.host) };

            if (credential.mode === 'dan') {
                runDanAutofill(credential, {
                    ...options,
                    // Бодит товчлуурын үйлдэл — нэг тэмдэгтийн нүдэнд хэрэгтэй.
                    typeKeys: (text) => ipcRenderer.invoke('md:type', text),
                    log: (message) => ipcRenderer.invoke('md:log', message),
                });
            } else {
                runAutofill(credential, options);
            }
        }
    } catch {
        // Мэдээлэл алга — хэрэглэгч гараар нэвтэрнэ.
    }
});

/*
 * Нөөц зам: preload дуудагдахаас өмнө ачаалсан хуудас postMessage-ээр
 * илгээвэл мөн хүлээж авна.
 */
window.addEventListener('message', (event) => {
    if (event.source !== window || event.data?.type !== 'md-autologin') {
        return;
    }

    ipcRenderer.invoke('md:store', event.data).then(() => {
        window.postMessage({ type: 'md-autologin-ready' }, window.location.origin);
    });
});
