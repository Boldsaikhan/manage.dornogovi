/**
 * Manage Dornogovi — Windows ширээний програм.
 *
 * Нэг цонхонд өөрийн табын мөртэй: эхний таб нь систем, «Холбосон
 * системүүд» дээр дарахад тус бүр шинэ таб болж нээгдэнэ. Нэвтрэх
 * мэдээллийг зорилтот сайтын маягтад автоматаар бөглөнө — мэдээлэл нь
 * зөвхөн програмын санах ойд байх бөгөөд дискэнд бичигдэхгүй.
 */

const {
    app,
    BaseWindow,
    WebContentsView,
    Menu,
    ipcMain,
    nativeTheme,
    shell,
    session,
} = require('electron');
const fs = require('node:fs');
const path = require('node:path');
const { checkForUpdates } = require('./updater');

/**
 * Богино бүртгэл — нэвтрэлт болоогүй үед шалтгааныг олоход хэрэгтэй.
 *
 * Зөвхөн хост, үйлдлийн нэрийг бичнэ — нэр, нууц үг хэзээ ч бичигдэхгүй.
 * Байршил: %APPDATA%\Manage Dornogovi\manage-desktop.log
 */
const logLine = (message) => {
    try {
        fs.appendFileSync(
            path.join(app.getPath('userData'), 'manage-desktop.log'),
            `${new Date().toISOString()} ${message}\n`,
        );
    } catch {
        // Бичиж чадсангүй — ажиллагаанд нөлөөлөхгүй.
    }
};

const SITE_URL = process.env.MD_SITE_URL || 'https://manage.dornogovi.gov.mn';
const SITE_HOST = new URL(SITE_URL).host;

/** Табын мөрийн өндөр (px). */
const TAB_BAR_HEIGHT = 44;

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
        // 'dan' бол ДАН-ы маягтыг өөр дараалалаар бөглөнө.
        mode: String(payload.mode ?? 'password'),
        // Аль хэдийн нэвтэрсэн бол энэ хаяг руу үргэлжилнэ.
        home: String(payload.home ?? ''),
        source: payload.remember ? 'remembered' : 'pending',
        at: Date.now(),
    });

    return true;
};

/**
 * Хостод тохирох түлхүүрийг олно.
 *
 * Зарим систем нэвтрэх хуудас руугаа өөр дэд домэйнээр шилждэг тул
 * яг таарахгүй бол «www.»-г хасаад, эцэст нь үндсэн домэйнээр нь тааруулна.
 */
const matchHost = (host) => {
    const clean = String(host || '').toLowerCase();

    if (clean === '') {
        return null;
    }

    if (credentials.has(clean)) {
        return clean;
    }

    const base = (value) => value.replace(/^www\./, '').split('.').slice(-2).join('.');
    const wanted = base(clean);

    for (const key of credentials.keys()) {
        if (base(key) === wanted) {
            return key;
        }
    }

    return null;
};

const takeCredential = (host) => {
    const key = matchHost(host);

    if (key === null) {
        return null;
    }

    const entry = credentials.get(key);

    if (Date.now() - entry.at > CREDENTIAL_TTL_MS) {
        credentials.delete(key);

        return null;
    }

    /*
     * Устгахгүй — нэвтрэх хуудас руу дахин шилжих, SPA дахин ачаалах
     * тохиолдолд мэдээлэл алга болчихдог байв. Маягт амжилттай
     * бөглөгдмөгц `md:used`-ээр, эс бөгөөс хугацаа дуусахад устана.
     */
    return {
        username: entry.username,
        password: entry.password,
        mode: entry.mode,
        home: entry.home,
        source: entry.source,
    };
};

const isSiteUrl = (url) => {
    try {
        return new URL(url).host === SITE_HOST;
    } catch {
        return false;
    }
};

/** @type {import('electron').BaseWindow|null} */
let win = null;

/** @type {import('electron').WebContentsView|null} */
let tabBar = null;

/** @type {Array<{id: number, view: import('electron').WebContentsView, title: string, home: boolean}>} */
const tabs = [];

let activeId = null;
let nextId = 1;

const contentArea = () => {
    const { width, height } = win.getContentBounds();

    return {
        x: 0,
        y: TAB_BAR_HEIGHT,
        width,
        height: Math.max(0, height - TAB_BAR_HEIGHT),
    };
};

const layout = () => {
    if (! win || win.isDestroyed()) {
        return;
    }

    const { width } = win.getContentBounds();

    tabBar.setBounds({ x: 0, y: 0, width, height: TAB_BAR_HEIGHT });

    const area = contentArea();

    for (const tab of tabs) {
        tab.view.setBounds(area);
    }
};

const activeTab = () => tabs.find((tab) => tab.id === activeId) ?? null;

/** Таб нь ухарч чадах эсэх (хуучин Electron дээр ч ажиллана). */
const canGoBack = (webContents) => {
    try {
        return webContents.navigationHistory.canGoBack();
    } catch {
        return false;
    }
};

const sendState = () => {
    if (! tabBar || tabBar.webContents.isDestroyed()) {
        return;
    }

    const current = activeTab();

    tabBar.webContents.send('tabs:state', {
        tabs: tabs.map((tab) => ({
            id: tab.id,
            title: tab.title,
            active: tab.id === activeId,
            closable: ! tab.home,
            icon: tab.icon,
        })),
        canGoBack: current ? canGoBack(current.view.webContents) : false,
    });
};

const titleFor = (tab) => (tab.home ? 'Manage Dornogovi' : `${tab.title} — Manage Dornogovi`);

const activate = (id) => {
    const tab = tabs.find((item) => item.id === id);

    if (! tab) {
        return;
    }

    activeId = id;

    for (const item of tabs) {
        item.view.setVisible(item.id === id);
    }

    tab.view.webContents.focus();
    win.setTitle(titleFor(tab));
    sendState();
};

const closeTab = (id) => {
    const index = tabs.findIndex((tab) => tab.id === id);

    if (index < 0 || tabs[index].home) {
        return;
    }

    const [tab] = tabs.splice(index, 1);

    win.contentView.removeChildView(tab.view);
    tab.view.webContents.close();

    if (activeId === id) {
        activate((tabs[index] ?? tabs[index - 1] ?? tabs[0]).id);
    } else {
        sendState();
    }
};

/** Табын доторх хуудсыг програмын дүрмээр хянана. */
const wire = (tab) => {
    const { webContents } = tab.view;

    webContents.on('page-title-updated', (event, title) => {
        event.preventDefault();
        tab.title = String(title || '').trim() || 'Шинэ таб';

        if (tab.id === activeId) {
            win.setTitle(titleFor(tab));
        }

        sendState();
    });

    webContents.on('page-favicon-updated', (event, icons) => {
        tab.icon = Array.isArray(icons) && icons.length > 0 ? icons[0] : null;
        sendState();
    });

    // Шинэ хаяг руу шилжихэд хуучин icon үлдэхгүй.
    webContents.on('did-start-navigation', (event, url, isInPlace, isMainFrame) => {
        if (isMainFrame && ! isInPlace) {
            tab.icon = null;
        }
    });

    webContents.on('did-navigate', sendState);
    webContents.on('did-navigate-in-page', sendState);

    // Шинэ цонх нээх оролдлогыг таб болгоно.
    webContents.setWindowOpenHandler(({ url }) => {
        openInTab(url);

        return { action: 'deny' };
    });

    /*
     * Системийн табаас гадуур шилжих гэвэл шинэ таб нээнэ — тэгж байж
     * нэвтрэх маягтыг бөглөх preload ажиллана.
     */
    if (tab.home) {
        webContents.on('will-navigate', (event, url) => {
            if (isSiteUrl(url)) {
                return;
            }

            event.preventDefault();
            openInTab(url);
        });
    }

    // Ctrl+W — таб хаах, Ctrl+Tab — дараагийн таб.
    webContents.on('before-input-event', (event, input) => {
        if (input.type !== 'keyDown' || ! input.control) {
            return;
        }

        const key = String(input.key).toLowerCase();

        if (key === 'w') {
            event.preventDefault();
            closeTab(tab.id);
        } else if (key === 'tab') {
            event.preventDefault();
            const index = tabs.findIndex((item) => item.id === activeId);
            activate(tabs[(index + 1) % tabs.length].id);
        }
    });
};

const createTab = (url, { home = false } = {}) => {
    /*
     * Таб нь системээс эхлээд холбосон систем рүү шилжиж болох тул preload
     * нь нэг л файл — хуудас бүр дээр хостоосоо хамаарч ажиллана.
     */
    const view = new WebContentsView({
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            additionalArguments: [`--md-site-host=${SITE_HOST}`, `--md-version=${app.getVersion()}`],
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: false,
        },
    });

    const tab = {
        id: nextId++,
        view,
        title: home ? 'Самбар' : 'Ачаалж байна…',
        home,
        icon: null,
        // Аль холбоосоор нээгдсэн — дахин дарвал ижил таб руугаа шилжинэ.
        openedWith: url,
    };

    tabs.push(tab);
    win.contentView.addChildView(view);
    view.setBounds(contentArea());
    view.setVisible(false);
    wire(tab);
    view.webContents.loadURL(url);
    activate(tab.id);

    return tab;
};

/** Нэг хостод нэг л таб — байвал түүн рүүгээ шилжинэ. */
const openInTab = (url) => {
    if (! win || win.isDestroyed()) {
        return;
    }

    let host = '';

    try {
        host = new URL(url).host;
    } catch {
        shell.openExternal(url);

        return;
    }

    const existing = tabs.find((tab) => {
        if (tab.home) {
            return false;
        }

        if (tab.openedWith === url) {
            return true;
        }

        try {
            return new URL(tab.view.webContents.getURL()).host === host;
        } catch {
            return false;
        }
    });

    if (existing) {
        existing.view.webContents.loadURL(url);
        activate(existing.id);

        return;
    }

    createTab(url);
};

const createMainWindow = () => {
    win = new BaseWindow({
        width: 1440,
        height: 900,
        minWidth: 1024,
        minHeight: 640,
        backgroundColor: '#164789',
        title: 'Manage Dornogovi',
        /*
         * Цагаан хүрээ биш — табын мөр нь цонхны толгой болно.
         * Windows-ийн багасгах/хаах товчнуудыг цэнхэр дэвсгэр дээр зурна.
         */
        titleBarStyle: 'hidden',
        titleBarOverlay: {
            color: '#164789',
            symbolColor: '#e4eef8',
            height: TAB_BAR_HEIGHT,
        },
    });

    tabBar = new WebContentsView({
        webPreferences: {
            preload: path.join(__dirname, 'preload-tabs.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    win.contentView.addChildView(tabBar);
    tabBar.webContents.loadFile(path.join(__dirname, 'tabs.html'));
    tabBar.webContents.on('did-finish-load', sendState);

    win.on('resize', layout);
    win.on('closed', () => {
        win = null;
        tabs.length = 0;
    });

    layout();
    createTab(SITE_URL, { home: true });

    return win;
};

// Нэг хувь нь л ажиллана — товчлол дахин дарахад байгаа цонх нь гарч ирнэ.
if (! app.requestSingleInstanceLock()) {
    app.quit();
} else {
    app.on('second-instance', () => {
        if (win && ! win.isDestroyed()) {
            if (win.isMinimized()) {
                win.restore();
            }

            win.focus();
        }
    });

    app.whenReady().then(() => {
        // Цонхны хүрээ цайвар — систем нь бараан байсан ч.
        nativeTheme.themeSource = 'light';

        // Хөтчийн цэс хэрэггүй — табын мөр нь хангалттай.
        Menu.setApplicationMenu(null);

        // Сайтын өөрийн preload-оос бусдад хандах эрх өгөхгүй.
        session.defaultSession.setPermissionRequestHandler((webContents, permission, callback) => {
            callback(['clipboard-read', 'clipboard-sanitized-write'].includes(permission));
        });

        createMainWindow();

        // Эхлэхэд нэг удаа, дараа нь 6 цаг тутамд шинэчлэлтээ шалгана.
        setTimeout(() => checkForUpdates(SITE_URL, { parent: win }), 8000);
        setInterval(() => checkForUpdates(SITE_URL, { parent: win }), 6 * 60 * 60 * 1000);

        app.on('activate', () => {
            if (! win || win.isDestroyed()) {
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
        logLine('store: татгалзав (системийн хуудас биш)');

        return false;
    }

    const stored = rememberCredential(payload);

    logLine(`store: ${payload?.host || '?'} → ${stored ? 'хадгаллаа' : 'дутуу мэдээлэл'}`);

    return stored;
});

// Зорилтот сайтын preload мэдээллээ авна.
ipcMain.handle('md:take', (event, host) => {
    const credential = takeCredential(String(host || ''));

    logLine(`take: ${host} → ${credential ? 'өглөө' : 'мэдээлэл алга'}`);

    return credential;
});

// Маягт бөглөгдлөө — мэдээллийг санах ойгоос устгана.
ipcMain.handle('md:used', (event, host) => {
    const key = matchHost(String(host || ''));

    if (key !== null) {
        credentials.delete(key);
    }

    logLine(`used: ${host}`);

    return true;
});

// Гадаад холбоосыг системийн хөтчөөр нээнэ.
ipcMain.handle('md:external', (event, url) => shell.openExternal(String(url || '')));

/*
 * Жинхэнэ товчлуурын үйлдэл илгээнэ.
 *
 * Зарим маягтын нэг тэмдэгтийн нүд (регистрийн үсэг) нь зөвхөн бодит
 * гарын оролтыг хүлээн авдаг тул DOM-оор утга оноох нь ажиллахгүй.
 */
ipcMain.handle('md:type', (event, text) => {
    const chars = [...String(text ?? '')].slice(0, 32);

    for (const ch of chars) {
        event.sender.sendInputEvent({ type: 'keyDown', keyCode: ch });
        event.sender.sendInputEvent({ type: 'char', keyCode: ch });
        event.sender.sendInputEvent({ type: 'keyUp', keyCode: ch });
    }

    return true;
});

// Оношлох бүртгэл — зөвхөн маягтын бүтэц, нууц мэдээлэл бичигдэхгүй.
ipcMain.handle('md:log', (event, message) => {
    logLine('dan: ' + String(message ?? '').slice(0, 2000));

    return true;
});

// Табын мөрийн үйлдлүүд.
ipcMain.on('tabs:activate', (event, id) => activate(Number(id)));
ipcMain.on('tabs:close', (event, id) => closeTab(Number(id)));
ipcMain.on('tabs:back', () => {
    const tab = activeTab();

    if (tab && canGoBack(tab.view.webContents)) {
        tab.view.webContents.navigationHistory.goBack();
    }
});
ipcMain.on('tabs:reload', () => activeTab()?.view.webContents.reload());
ipcMain.on('tabs:update', () => checkForUpdates(SITE_URL, { silent: false, parent: win }));
ipcMain.handle('tabs:version', () => app.getVersion());
