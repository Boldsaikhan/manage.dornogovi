/**
 * Өргөтгөлийг нэг хавтас болгож татна (бүх файл).
 * Chrome/Edge: хавтас сонгоод бичнэ.
 * Бусад: ZIP (дотор нь manage-dornogovi-extension хавтас) татна.
 */
export async function downloadExtensionLoose() {
    const folder = 'manage-dornogovi-extension';

    // 1) Хавтас сонгох цонхыг ХАМГИЙН ТҮРҮҮНД нээнэ.
    //
    //    showDirectoryPicker нь «хэрэглэгчийн шинэхэн товшилт» шаарддаг. Өмнө нь
    //    сервер рүү хүсэлт илгээгээд дараа нь дууддаг байсан тул товшилтын эрх
    //    дуусаж, цонх нээгдэхгүй үргэлж ZIP руу унадаг байв.
    let root = null;

    if (typeof window.showDirectoryPicker === 'function') {
        try {
            root = await window.showDirectoryPicker({
                mode: 'readwrite',
                id: 'manage-dornogovi-extension',
                startIn: 'downloads',
            });
        } catch (e) {
            if (e?.name === 'AbortError') {
                throw e;
            }

            root = null;
        }
    }

    const { data } = await window.axios.get(route('extension.download'));
    const files = data?.files || {};

    if (! Object.keys(files).length) {
        throw new Error('Өргөтгөлийн файл олдсонгүй.');
    }

    // 2) Сонгосон байрлалд бүх файлыг нэг хавтас болгож бичнэ.
    if (root) {
        try {
            const dir = await root.getDirectoryHandle(data?.folder || folder, { create: true });

            for (const [rel, meta] of Object.entries(files)) {
                await writeNestedFile(dir, rel, toBlob(meta));
            }

            return { method: 'folder', folder: data?.folder || folder, count: Object.keys(files).length };
        } catch (e) {
            if (e?.name === 'AbortError') {
                throw e;
            }
            // Бичиж чадсангүй — ZIP руу шилжинэ.
        }
    }

    // 3) Найдвартай fallback — нэг ZIP (дотор бүхэл хавтас)
    await downloadZipFallback();

    return { method: 'zip', folder: data?.folder || folder, count: Object.keys(files).length };
}

async function downloadZipFallback() {
    const response = await window.axios.get(route('extension.download.zip'), {
        responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'application/zip' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'manage-dornogovi-extension.zip';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

function toBlob(meta) {
    if (meta.encoding === 'base64') {
        const bin = atob(meta.content);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i += 1) {
            bytes[i] = bin.charCodeAt(i);
        }

        return new Blob([bytes]);
    }

    return new Blob([meta.content], { type: 'text/plain;charset=utf-8' });
}

async function writeNestedFile(rootHandle, relativePath, blob) {
    const parts = relativePath.split('/').filter(Boolean);
    let dir = rootHandle;

    for (let i = 0; i < parts.length - 1; i += 1) {
        dir = await dir.getDirectoryHandle(parts[i], { create: true });
    }

    const fileHandle = await dir.getFileHandle(parts[parts.length - 1], { create: true });
    const writable = await fileHandle.createWritable();
    await writable.write(blob);
    await writable.close();
}
