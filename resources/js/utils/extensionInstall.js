/**
 * Өргөтгөлийг НЭГ файл болгож татна (ZIP).
 * Задаалбал manage-dornogovi-extension хавтас гарна.
 * Файл бүрийг тусад нь татахгүй — хөтөч олон удаа татах цонх гаргадаг.
 */
export function downloadExtensionLoose() {
    const url = route('extension.download.zip');
    const a = document.createElement('a');
    a.href = url;
    a.download = 'manage-dornogovi-extension.zip';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();

    return { method: 'zip', folder: 'manage-dornogovi-extension' };
}
