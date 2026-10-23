/**
 * Системийн хуудсанд өгөх гүүр.
 *
 * `launch` хуудас нэвтрэх мэдээллийг энд дамжуулж, програм нь зорилтот
 * сайтын маягтад бөглөнө. Хуудсан дээрх бусад скрипт энэ мэдээллийг
 * харах боломжгүй.
 */

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('manageDesktop', {
    version: process.env.npm_package_version || '1.0.0',

    /** @param {{host: string, username: string, password: string, remember?: boolean}} payload */
    autoLogin: (payload) => ipcRenderer.invoke('md:store', payload),

    openExternal: (url) => ipcRenderer.invoke('md:external', url),
});

// Хуудас нь програм дотор ажиллаж байгаагаа мэдэхийн тулд.
window.addEventListener('DOMContentLoaded', () => {
    document.documentElement.dataset.mdDesktop = '1';
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
