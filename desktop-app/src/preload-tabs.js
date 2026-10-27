/**
 * Табын мөрийн preload.
 *
 * Табын жагсаалтыг үндсэн процессоос авч, дарсан үйлдлийг буцаан дамжуулна.
 */

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('mdTabs', {
    onState: (callback) => {
        ipcRenderer.on('tabs:state', (event, state) => callback(state));
    },
    activate: (id) => ipcRenderer.send('tabs:activate', id),
    close: (id) => ipcRenderer.send('tabs:close', id),
    back: () => ipcRenderer.send('tabs:back'),
    forward: () => ipcRenderer.send('tabs:forward'),
    reload: () => ipcRenderer.send('tabs:reload'),
    checkUpdates: () => ipcRenderer.send('tabs:update'),
    onNotice: (callback) => {
        ipcRenderer.on('tabs:notice', (event, text) => callback(text));
    },
    version: () => ipcRenderer.invoke('tabs:version'),
});
