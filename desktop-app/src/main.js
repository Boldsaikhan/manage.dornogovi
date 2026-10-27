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
const path = require('node:path');
const { checkForUpdates } = require('./updater');

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
        backgroundColor: '#ffffff',
        title: 'Manage Dornogovi',
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
        setTimeout(() => checkForUpdates(SITE_URL), 8000);
        setInterval(() => checkForUpdates(SITE_URL), 6 * 60 * 60 * 1000);

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
        return false;
    }

    return rememberCredential(payload);
});

// Зорилтот сайтын preload мэдээллээ авна.
ipcMain.handle('md:take', (event, host) => takeCredential(String(host || '')));

// Гадаад холбоосыг системийн хөтчөөр нээнэ.
ipcMain.handle('md:external', (event, url) => shell.openExternal(String(url || '')));

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
ipcMain.on('tabs:update', () => checkForUpdates(SITE_URL, { silent: false }));
ipcMain.handle('tabs:version', () => app.getVersion());
