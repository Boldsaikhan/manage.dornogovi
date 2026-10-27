/**
 * Шинэчлэлтийн цонхны preload.
 */

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('mdUpdate', {
    onState: (callback) => {
        ipcRenderer.on('update:state', (event, state) => callback(state));
        // Цонх бэлэн боллоо — эхний төлөвөө авна.
        ipcRenderer.send('update:ready');
    },
    start: () => ipcRenderer.send('update:start'),
    later: () => ipcRenderer.send('update:later'),
    install: () => ipcRenderer.send('update:install'),
});
