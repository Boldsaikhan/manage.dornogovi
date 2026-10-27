/**
 * Шинэчлэлт шалгах, татах, суулгах.
 *
 * Сервер дээрх /desktop/version нь байршуулсан суулгацын хувилбарыг
 * хэлнэ. Суусан хувилбараас шинэ бол хэрэглэгчээс асууна — админ
 * «хүчээр» гэж тохируулсан бол «Дараа» гэж сонгох боломжгүй.
 */

const { app, dialog, net, shell } = require('electron');
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

const downloadTo = (url, target) => new Promise((resolve, reject) => {
    // Сешний cookie-г хамт илгээнэ — татах хаяг нэвтэрсэн хэрэглэгчид зориулагдсан.
    const request = net.request({ url, method: 'GET', useSessionCookies: true });

    request.on('response', (response) => {
        if (response.statusCode !== 200) {
            reject(new Error(`Суулгацыг татаж чадсангүй (${response.statusCode}).`));

            return;
        }

        const file = fs.createWriteStream(target);

        response.on('data', (chunk) => file.write(chunk));
        response.on('end', () => file.end(() => resolve(target)));
        response.on('error', reject);
    });

    request.on('error', reject);
    request.end();
});

/**
 * Шинэчлэлт байгаа эсэхийг шалгаж, зөвшөөрвөл суулгана.
 *
 * @param {string} siteUrl
 * @param {{silent?: boolean}} options  silent — шинэчлэлт байхгүй бол дуугарахгүй
 */
async function checkForUpdates(siteUrl, { silent = true } = {}) {
    let manifest;

    try {
        manifest = await fetchJson(new URL('/desktop/version', siteUrl).toString());
    } catch (error) {
        if (! silent) {
            dialog.showMessageBox({
                type: 'warning',
                title: 'Шинэчлэлт',
                message: 'Шинэчлэлтийг шалгаж чадсангүй.',
                detail: error.message,
                buttons: ['Хаах'],
            });
        }

        return false;
    }

    const latest = String(manifest?.version || '').trim();
    const current = app.getVersion();

    if (! manifest?.available || latest === '' || compareVersions(latest, current) <= 0) {
        if (! silent) {
            dialog.showMessageBox({
                type: 'info',
                title: 'Шинэчлэлт',
                message: 'Та хамгийн сүүлийн хувилбарыг ашиглаж байна.',
                detail: `Хувилбар ${current}`,
                buttons: ['Хаах'],
            });
        }

        return false;
    }

    const force = Boolean(manifest.force);
    const buttons = force ? ['Одоо шинэчлэх'] : ['Одоо шинэчлэх', 'Дараа'];

    const { response } = await dialog.showMessageBox({
        type: 'info',
        title: 'Шинэчлэлт гарлаа',
        message: `Шинэ хувилбар бэлэн боллоо (${current} → ${latest}).`,
        detail: force
            ? `${manifest.notes || ''}\n\nЭнэ шинэчлэлтийг заавал хийнэ.`.trim()
            : (manifest.notes || 'Одоо шинэчлэх үү, эсвэл дараа хийх үү?'),
        buttons,
        defaultId: 0,
        cancelId: force ? -1 : 1,
        noLink: true,
    });

    if (! force && response !== 0) {
        return false;
    }

    const target = path.join(app.getPath('temp'), `Manage-Dornogovi-Setup-${latest}.exe`);

    try {
        await downloadTo(new URL(manifest.url, siteUrl).toString(), target);
    } catch (error) {
        await dialog.showMessageBox({
            type: 'error',
            title: 'Шинэчлэлт',
            message: 'Суулгацыг татаж чадсангүй.',
            detail: error.message,
            buttons: ['Хаах'],
        });

        return false;
    }

    await dialog.showMessageBox({
        type: 'info',
        title: 'Шинэчлэлт',
        message: 'Суулгац бэлэн боллоо.',
        detail: 'Програм хаагдаж, шинэ хувилбар суусны дараа дахин нээгдэнэ.',
        buttons: ['Үргэлжлүүлэх'],
        noLink: true,
    });

    shell.openPath(target);

    // Суулгагч файлыг солих боломжтой байхын тулд програмаа хаана.
    setTimeout(() => app.quit(), 500);

    return true;
}

module.exports = { checkForUpdates, compareVersions };
