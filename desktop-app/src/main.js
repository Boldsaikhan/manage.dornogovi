/**
 * Manage Dornogovi — Windows ширээний програм.
 *
 * Системийг тусдаа цонхонд ачаалж, «Холбосон системүүд» дээр дарахад
 * нэвтрэх мэдээллийг зорилтот сайтын маягтад автоматаар бөглөнө.
 * Мэдээлэл нь зөвхөн програмын санах ойд байх бөгөөд дискэнд бичигдэхгүй.
 */

const { app, BrowserWindow, ipcMain, shell, session } = require('electron');
const path = require('node:path');

const SITE_URL = process.env.MD_SITE_URL || 'https://manage.dornogovi.gov.mn';
const SITE_HOST = new URL(SITE_URL).host;

/**
 * Хост → нэвтрэх мэдээлэл.
 *
 * Зөвхөн санах ойд, програм хаагдахад устана.
 *
 * @type {Map<string, {username: string, password: string, source: string, at: number}>}
 */
const credentials = new Map();

/** Мэдээлэл 10 минутын дараа хүчингүй болно. */
const CREDENTIAL_TTL_MS = 10 * 60 * 1000;

const rememberCredential = (payload) => {
    const host = String(payload?.host || '').trim();

    if (host === '' || ! payload?.username) {
        return false;
    }

    credentials.set(host, {
        username: String(payload.username),
        password: String(payload.password ?? ''),
        source: payload.remember ? 'remembered' : 'pending',
        at: Date.now(),
    });

    return true;
};

const takeCredential = (host) => {
    const entry = credentials.get(host);

    if (! entry) {
        return null;
    }

    if (Date.now() - entry.at > CREDENTIAL_TTL_MS) {
        credentials.delete(host);

        return null;
    }

    // Нэг удаа л өгнө — хуудас дахин ачаалагдвал давтан илгээхгүй.
    credentials.delete(host);

    return { username: entry.username, password: entry.password, source: entry.source };
};

const isSiteUrl = (url) => {
    try {
        return new URL(url).host === SITE_HOST;
    } catch {
        return false;
    }
};

/** Зорилтот систем — нэвтрэх маягтыг бөглөх preload-той цонх. */
const openTarget = (url) => {
    const win = new BrowserWindow({
        width: 1280,
        height: 860,
        autoHideMenuBar: true,
        webPreferences: {
            preload: path.join(__dirname, 'preload-target.js'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    win.loadURL(url);

    return win;
};

const createMainWindow = () => {
    const win = new BrowserWindow({
        width: 1440,
        height: 900,
        minWidth: 1024,
        minHeight: 640,
        autoHideMenuBar: true,
        title: 'Manage Dornogovi',
        webPreferences: {
            preload: path.join(__dirname, 'preload-app.js'),
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    win.loadURL(SITE_URL);

    /*
     * Холбосон систем рүү шилжих бүрд програмын дотор нээнэ — тэгж байж
     * нэвтрэх маягтыг бөглөх preload ажиллана. Бусад холбоос гадаад
     * хөтчид нээгдэнэ.
     */
    win.webContents.setWindowOpenHandler(({ url }) => {
        if (isSiteUrl(url)) {
            return { action: 'allow' };
        }

        openTarget(url);

        return { action: 'deny' };
    });

    win.webContents.on('will-navigate', (event, url) => {
        if (isSiteUrl(url)) {
            return;
        }

        event.preventDefault();
        openTarget(url);
    });

    return win;
};

// Нэг хувь нь л ажиллана — товчлол дахин дарахад байгаа цонх нь гарч ирнэ.
if (! app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        const [win] = BrowserWindow.getAllWindows();

        if (win) {
            if (win.isMinimized()) {
                win.restore();
            }

            win.focus();
        }
    });

    app.whenReady().then(() => {
        // Сайтын өөрийн preload-оос бусдад хандах эрх өгөхгүй.
        session.defaultSession.setPermissionRequestHandler((webContents, permission, callback) => {
            callback(['clipboard-read', 'clipboard-sanitized-write'].includes(permission));
        });

        createMainWindow();

        app.on('activate', () => {
            if (BrowserWindow.getAllWindows().length === 0) {
                createMainWindow();
            }
        });
    });

    app.on('window-all-closed', () => {
        if (process.platform !== 'darwin') {
            app.quit();
        }
    });
}

// Платформоос ирсэн нэвтрэх мэдээллийг хадгална.
ipcMain.handle('md:store', (event, payload) => {
    // Зөвхөн системийн хуудас мэдээлэл өгч чадна.
    if (! isSiteUrl(event.sender.getURL())) {
        return false;
    }

    return rememberCredential(payload);
});

// Зорилтот сайтын preload мэдээллээ авна.
ipcMain.handle('md:take', (event, host) => takeCredential(String(host || '')));

// Гадаад холбоосыг системийн хөтчөөр нээнэ.
ipcMain.handle('md:external', (event, url) => shell.openExternal(String(url || '')));
