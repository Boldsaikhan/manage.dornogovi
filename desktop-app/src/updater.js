/**
 * Шинэчлэлт шалгах, татах, суулгах.
 *
 * Сервер дээрх /desktop/version нь байршуулсан суулгацын хувилбарыг
 * хэлнэ. Суусан хувилбараас шинэ бол програмын голд цонх гарч ирнэ:
 * татах явц нь мөрөөр харагдаж, «Дууссан» дарахад програм хаагдан
 * шинэ хувилбар суугаад дахин нээгдэнэ.
 *
 * Админ «хүчээр» гэж тохируулсан бол «Дараа» сонголт гарахгүй.
 */

const { app, BrowserWindow, ipcMain, net } = require('electron');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

/** Хувилбарыг тоогоор нь харьцуулна: 1.10.0 > 1.9.3. */
const compareVersions = (left, right) => {
    const a = String(left).split('.').map((part) => parseInt(part, 10) || 0);
    const b = String(right).split('.').map((part) => parseInt(part, 10) || 0);

    for (let i = 0; i < Math.max(a.length, b.length); i += 1) {
        const diff = (a[i] ?? 0) - (b[i] ?? 0);

        if (diff !== 0) {
            return diff > 0 ? 1 : -1;
        }
    }

    return 0;
};

const fetchJson = (url) => new Promise((resolve, reject) => {
    const request = net.request({ url, method: 'GET' });
    const chunks = [];

    request.on('response', (response) => {
        response.on('data', (chunk) => chunks.push(chunk));
        response.on('end', () => {
            if (response.statusCode !== 200) {
                reject(new Error(`Сервер ${response.statusCode} гэж хариулав.`));

                return;
            }

            try {
                resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
            } catch (error) {
                reject(error);
            }
        });
    });

    request.on('error', reject);
    request.end();
});

/** Татах явцыг мэдэгдэж байж суулгацыг хадгална. */
const downloadTo = (url, target, onProgress) => new Promise((resolve, reject) => {
    // Сешний cookie-г хамт илгээнэ — татах хаяг нэвтэрсэн хэрэглэгчид зориулагдсан.
    const request = net.request({ url, method: 'GET', useSessionCookies: true });

    request.on('response', (response) => {
        if (response.statusCode !== 200) {
            reject(new Error(`Суулгацыг татаж чадсангүй (${response.statusCode}).`));

            return;
        }

        const total = Number(response.headers['content-length'] ?? 0) || 0;
        const file = fs.createWriteStream(target);
        let done = 0;

        response.on('data', (chunk) => {
            done += chunk.length;
            file.write(chunk);
            onProgress(done, total);
        });

        response.on('end', () => file.end(() => resolve(target)));
        response.on('error', reject);
    });

    request.on('error', reject);
    request.end();
});

/**
 * Суулгацыг програмаа хаасны ДАРАА ажиллуулна.
 *
 * Шууд ажиллуулбал хуучин програм ажилласан хэвээр байх тул суулгагч
 * «Another program is currently using this file» гэж зогсдог.
 */
const launchInstallerAfterExit = (installer) => {
    const shellExe = process.env.COMSPEC || 'cmd.exe';

    /*
     * /S-гүйгээр ажиллуулна — нэг даралтын суулгац өөрийн явцын цонхоо
     * харуулж, дарж суулгах шаардлагагүй, дуусмагц програмыг нээдэг.
     * (/S үед юу ч харагдахгүй тул хэрэглэгч гацсан мэт боддог.)
     */
    spawn(
        shellExe,
        ['/c', `timeout /t 3 /nobreak >nul & start "" "${installer}" --force-run`],
        { detached: true, stdio: 'ignore', windowsHide: true },
    ).unref();
};

/** @type {import('electron').BrowserWindow|null} */
let updateWin = null;

/** Одоо санал болгож буй шинэчлэлт. */
let pending = null;

let wired = false;

/** Суулгац ажиллуулах гэж байгаа — цонх хаагдахыг зөвшөөрнө. */
let installing = false;

const send = (state) => {
    if (updateWin && ! updateWin.isDestroyed()) {
        updateWin.webContents.send('update:state', state);
    }
};

const mb = (bytes) => (bytes / 1024 / 1024).toFixed(1);

const closeWindow = () => {
    if (updateWin && ! updateWin.isDestroyed()) {
        updateWin.destroy();
    }

    updateWin = null;
};

const startDownload = async () => {
    if (! pending) {
        return;
    }

    const target = path.join(app.getPath('temp'), `Manage-Dornogovi-Setup-${pending.latest}.exe`);

    try {
        await downloadTo(pending.url, target, (done, total) => {
            send({
                kind: 'progress',
                percent: total ? Math.min(100, Math.round((done / total) * 100)) : 0,
                doneMb: mb(done),
                totalMb: total ? mb(total) : null,
                total,
            });
        });
    } catch (error) {
        send({ kind: 'error', message: error.message });

        return;
    }

    pending.file = target;
    send({ kind: 'ready' });
};

const wire = () => {
    if (wired) {
        return;
    }

    wired = true;

    ipcMain.on('update:ready', () => {
        if (pending) {
            send({ kind: 'offer', ...pending });
        }
    });

    ipcMain.on('update:start', startDownload);
    ipcMain.on('update:later', closeWindow);

    ipcMain.on('update:install', () => {
        if (! pending?.file) {
            return;
        }

        installing = true;
        launchInstallerAfterExit(pending.file);

        // Хаагдахаас өмнө «суулгаж байна» төлвийг харуулна.
        send({ kind: 'installing' });

        setTimeout(() => {
            closeWindow();
            app.quit();

            // Ямар нэг цонх хаагдахгүй бол хүчээр гарна.
            setTimeout(() => app.exit(0), 1200);
        }, 1200);
    });
};

const openWindow = (parent) => {
    wire();
    closeWindow();

    updateWin = new BrowserWindow({
        width: 460,
        height: 300,
        parent: parent ?? undefined,
        modal: Boolean(parent),
        resizable: false,
        minimizable: false,
        maximizable: false,
        fullscreenable: false,
        backgroundColor: '#ffffff',
        title: 'Шинэчлэлт',
        webPreferences: {
            preload: path.join(__dirname, 'preload-update.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    updateWin.setMenuBarVisibility(false);
    updateWin.loadFile(path.join(__dirname, 'update.html'));

    /*
     * Хүчээр шинэчлэх үед цонхыг хаалгахгүй. Гэхдээ closable: false нь
     * app.quit()-ийг бас зогсоодог тул суулгах үед зөвшөөрнө — эс бөгөөс
     * програм хаагдахгүй, суулгац хуучин файлуудыг солиж чадахгүй байв.
     */
    updateWin.on('close', (event) => {
        if (pending?.force && ! installing) {
            event.preventDefault();
        }
    });

    updateWin.on('closed', () => {
        updateWin = null;
    });
};

/**
 * Шинэчлэлт байгаа эсэхийг шалгаж, байвал цонхыг нээнэ.
 *
 * @param {string} siteUrl
 * @param {{silent?: boolean, parent?: import('electron').BaseWindow|null}} options
 */
async function checkForUpdates(siteUrl, { silent = true, parent = null, notify = null } = {}) {
    // Тусдаа цонх биш, програмын дотор богино мэдэгдэл харуулна.
    const say = (text) => {
        if (! silent && typeof notify === 'function') {
            notify(text);
        }
    };
    let manifest;

    try {
        manifest = await fetchJson(new URL('/desktop/version', siteUrl).toString());
    } catch (error) {
        say('Шинэчлэлтийг шалгаж чадсангүй.');

        return false;
    }

    const latest = String(manifest?.version || '').trim();
    const current = app.getVersion();

    if (! manifest?.available || latest === '' || compareVersions(latest, current) <= 0) {
        say(`Хамгийн сүүлийн хувилбар (v${current}) ажиллаж байна.`);

        return false;
    }

    // Нээлттэй цонх байвал дахин нээхгүй.
    if (updateWin && ! updateWin.isDestroyed()) {
        updateWin.focus();

        return true;
    }

    pending = {
        latest,
        current,
        notes: String(manifest.notes || ''),
        force: Boolean(manifest.force),
        url: new URL(manifest.url, siteUrl).toString(),
        file: null,
    };

    openWindow(parent);

    return true;
}

module.exports = { checkForUpdates, compareVersions };
