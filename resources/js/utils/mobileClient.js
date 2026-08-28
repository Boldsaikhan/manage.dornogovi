/** Гар утас эсэх — desktop PWA-д апп түгжээ хэрэглэхгүй */
export const isMobileDevice = () => {
    if (typeof navigator === 'undefined') return false;

    const ua = navigator.userAgent ?? '';

    if (/iPad/i.test(ua) || (/Macintosh/i.test(ua) && 'ontouchend' in document)) {
        return true;
    }

    return /Android|iPhone|iPod/i.test(ua);
};
