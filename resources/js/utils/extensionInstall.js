/**
 * Өргөтгөлийг ZIP файл болгож шууд татна (Татаж авсан файлууд хавтсанд).
 * Хавтас сонгох цонх нээдэггүй — энгийн, танил татах явц.
 */
export async function downloadExtensionLoose() {
    const folder = 'manage-dornogovi-extension';

    const response = await window.axios.get(route('extension.download.zip'), {
        responseType: 'blob',
    });

    const blob = new Blob([response.data], { type: 'application/zip' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${folder}.zip`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    return { method: 'zip', folder };
}
