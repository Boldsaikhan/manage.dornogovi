/**
 * Холбосон системийн цонхонд ажиллах preload.
 *
 * Платформоос дамжуулсан нэвтрэх мэдээллийг аваад маягтыг бөглөнө.
 * Хуудсанд ямар ч API нээхгүй — зөвхөн DOM дээр ажиллана.
 */

const { ipcRenderer } = require('electron');
const { runAutofill } = require('./autofill.js');

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const credential = await ipcRenderer.invoke('md:take', location.host);

        if (credential) {
            runAutofill(credential);
        }
    } catch {
        // Мэдээлэл алга — хэрэглэгч гараар нэвтэрнэ.
    }
});
