/**
 * Өргөтгөлийн файлуудыг ZIP биш, задгай хавтас/файл болгож хадгална.
 * Chrome/Edge: хавтас сонгоод бүх файлыг бичнэ.
 */
export async function downloadExtensionLoose() {
    const { data } = await window.axios.get(route('extension.download'));
    const folder = data?.folder || 'manage-dornogovi-extension';
    const files = data?.files || {};

    if (! Object.keys(files).length) {
        throw new Error('Өргөтгөлийн файл олдсонгүй.');
    }

    if (typeof window.showDirectoryPicker === 'function') {
        const root = await window.showDirectoryPicker({ mode: 'readwrite' });
        const dir = await root.getDirectoryHandle(folder, { create: true });

        for (const [rel, meta] of Object.entries(files)) {
            await writeNestedFile(dir, rel, toBlob(meta));
        }

        return { method: 'folder', folder };
    }

    // Хавтас сонгох боломжгүй хөтөч — файл бүрийг тусад нь татна.
    for (const [rel, meta] of Object.entries(files)) {
        const blob = toBlob(meta);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${folder}__${rel.replace(/\//g, '__')}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        await new Promise((r) => setTimeout(r, 180));
    }

    return { method: 'files', folder };
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
