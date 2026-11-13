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

const { app, BrowserWindow, ipcMain, net, shell } = require('electron');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

/**
 * Шинэчлэлтийн явцыг файлд тэмдэглэнэ.
 *
 * Шинэчлэлт бүтэхгүй бол хэрэглэгчийн машин дээр юу болсныг
 * харах цорын ганц мөр — %APPDATA%/Manage Dornogovi/update.log.
 */
const note = (message) => {
    try {
        const line = `${new Date().toISOString()}  ${message}
`;

        fs.appendFileSync(path.join(app.getPath('userData'), 'update.log'), line);
    } catch (error) {
        // Бичиж чадахгүй бол чимээгүй өнгөрнө.
    }
};

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
    /*
     * Сешний cookie-г хамт илгээнэ — татах хаяг нэвтэрсэн хэрэглэгчид
     * зориулагдсан. Нэвтрэлт дууссан бол сервер /login руу шилжүүлдэг
     * тул redirect-ийг дагахгүй: эс бөгөөс нэвтрэх хуудсын HTML-ийг
     * «суулгац» болгон татаж аваад эцэст нь ойлгомжгүй алдаа өгдөг.
     */
    const request = net.request({ url, method: 'GET', useSessionCookies: true, redirect: 'error' });

    request.on('redirect', () => {
        request.abort();
        reject(new Error('Нэвтрэлт дууссан байна. Системд дахин нэвтэрч орсны дараа оролдоно уу.'));
    });

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

        response.on('end', () => file.end(() => {
            if (total && done !== total) {
                reject(new Error('Татаж дуусаагүй байна. Дахин оролдоно уу.'));

                return;
            }

            resolve(target);
        }));
        response.on('error', reject);
    });

    request.on('error', reject);
    request.end();
});

/**
 * Суулгацыг програм ҮНЭХЭЭР хаагдсаны дараа ажиллуулна.
 *
 * Хуучин програм ажиллаж байхад суулгагч файлуудыг солиж чаддаггүй
 * («Another program is currently using this file») тул хуучин
 * хувилбар хэвээр үлддэг.
 *
 * ⚠️ Урьд нь `timeout /t 3` гэж хүлээдэг байв. Гэтэл энэ процессыг
 *    stdio: 'ignore'-оор асаадаг тул `timeout` нь «Input redirection
 *    is not supported» гээд 0.04 секундэд шууд гардаг — хүлээлт
 *    огт болдоггүй байсан. Иймд цагаар бус, програмын PID алга
 *    болохыг хүлээнэ.
 */
const launchInstallerAfterExit = (installer) => {
    // PowerShell-ийн мөрөнд ' нь '' болж орно.
    const quote = (value) => `'${String(value).replace(/'/g, "''")}'`;

    const script = [
        // Өөрийн процесс хаагдахыг хүлээнэ (аль хэдийн хаагдсан бол алдаа өгнө).
        `try { Wait-Process -Id ${process.pid} -Timeout 60 -ErrorAction Stop } catch { }`,
        // Файлын бариул бүрэн сулартал бага зэрэг зай өгнө.
        'Start-Sleep -Milliseconds 1200',
        /*
         * /S-гүйгээр ажиллуулна — нэг даралтын суулгац өөрийн явцын
         * цонхоо харуулж, дуусмагц програмыг дахин нээнэ.
         */
        `Start-Process -FilePath ${quote(installer)} -ArgumentList '--force-run'`,
    ].join('; ');

    const child = spawn(
        'powershell.exe',
        ['-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-Command', script],
        { detached: true, stdio: 'ignore', windowsHide: true },
    );

    child.on('error', (error) => note(`суулгагчийг асааж чадсангүй: ${error.message}`));
    child.unref();

    note(`суулгагч дараалалд оров: ${installer}`);
};

/** @type {import('electron').BrowserWindow|null} */
let updateWin = null;

/** Шинэчлэлтийн цонхыг нээсэн үндсэн цонх. */
let parentWin = null;

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

/**
 * Алдааг харуулна.
 *
 * Алдаа гарсан үед цонхыг хаах боломжтой болгоно — «хүчээр шинэчлэх»
 * тохиргоотой үед хэрэглэгч гацаанд ордог байв.
 */
const fail = (message) => {
    if (pending) {
        pending.force = false;
    }

    send({ kind: 'error', message });
};

/**
 * Үндсэн цонхыг эргүүлж идэвхжүүлнэ.
 *
 * ⚠️ Windows дээр modal хүү цонхыг destroy() хийхэд эцэг цонх
 *    идэвхгүй (disabled) хэвээр үлддэг — дарахад зөвхөн «биб» дуу
 *    гарч, програм огт хариу өгөхгүй болдог байв. Иймд цонхыг
 *    modal-гүй нээж, хаах бүрт эцгийг нь шалгаж идэвхжүүлнэ.
 */
const releaseParent = () => {
    if (! parentWin || parentWin.isDestroyed()) {
        parentWin = null;

        return;
    }

    try {
        if (typeof parentWin.setEnabled === 'function') {
            parentWin.setEnabled(true);
        }

        parentWin.focus();
    } catch (error) {
        // Цонх хаагдаж байх үед алдаа өгч болно — алгасна.
    }
};

const closeWindow = () => {
    if (updateWin && ! updateWin.isDestroyed()) {
        updateWin.destroy();
    }

    updateWin = null;
    releaseParent();
};

const startDownload = async () => {
    if (! pending) {
        return;
    }

    note(`татаж эхлэв: ${pending.url}`);

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
        note(`татахад алдаа: ${error.message}`);
        fail(error.message);

        return;
    }

    // Суулгац бүтэн эсэхийг шалгана — тасарсан файл чимээгүй унадаг.
    try {
        const { size } = fs.statSync(target);
        const head = Buffer.alloc(2);
        const fd = fs.openSync(target, 'r');

        fs.readSync(fd, head, 0, 2, 0);
        fs.closeSync(fd);

        if (head.toString('latin1') !== 'MZ' || size < 1024 * 1024) {
            throw new Error('Татсан файл суулгац биш байна.');
        }

        if (pending.size && size !== pending.size) {
            throw new Error('Татсан файлын хэмжээ таарахгүй байна.');
        }
    } catch (error) {
        note(`шалгалт амжилтгүй: ${error.message}`);
        fail(error.message);

        return;
    }

    note(`татаж дууслаа: ${target}`);
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

    // Автомат шинэчлэлт бүтэхгүй бол хөтчөөр татаж, гараар суулгана.
    ipcMain.on('update:browser', () => {
        if (pending?.url) {
            note('хөтчөөр татахаар нээв');
            shell.openExternal(pending.url);
        }
    });

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

    parentWin = parent ?? null;

    /*
     * modal: true БИШ — Windows дээр modal цонх эцэг цонхоо идэвхгүй
     * болгодог ба хаагдахад нь үргэлж буцааж идэвхжүүлдэггүй. Оронд нь
     * үргэлж дээр харагдана: хэрэглэгч анзаарахгүй өнгөрөхгүй, гэхдээ
     * програм хэзээ ч гацахгүй.
     */
    updateWin = new BrowserWindow({
        width: 460,
        height: 300,
        parent: parent ?? undefined,
        modal: false,
        alwaysOnTop: true,
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
        releaseParent();
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
        size: Number(manifest.size) || 0,
        file: null,
    };

    openWindow(parent);

    return true;
}

module.exports = { checkForUpdates, compareVersions };
