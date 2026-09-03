/** PWA — утсан дээр апп суулгах, standalone илрүүлэх */

const PWA_INSTALLED_KEY = 'md_pwa_installed';
const WEBAUTHN_DEVICE_KEY = 'md_webauthn_device';
const BIO_SETUP_DISMISSED_KEY = 'md_bio_setup_dismissed';

export const isStandalonePwa = () => (
    typeof window !== 'undefined'
    && (
        window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true
    )
);

export const isIos = () => {
    if (typeof navigator === 'undefined') return false;
    const ua = navigator.userAgent ?? '';

    return /iPhone|iPad|iPod/i.test(ua)
        || (/Macintosh/i.test(ua) && 'ontouchend' in document);
};

export const isAndroid = () => (
    typeof navigator !== 'undefined' && /Android/i.test(navigator.userAgent ?? '')
);

export const markPwaInstalled = () => {
    try {
        localStorage.setItem(PWA_INSTALLED_KEY, '1');
    } catch {
        // ignore
    }
};

export const isPwaInstalledHint = () => {
    if (isStandalonePwa()) {
        return true;
    }

    try {
        return localStorage.getItem(PWA_INSTALLED_KEY) === '1';
    } catch {
        return false;
    }
};

export const markWebAuthnDevice = () => {
    try {
        localStorage.setItem(WEBAUTHN_DEVICE_KEY, '1');
    } catch {
        // ignore
    }
};

export const hasWebAuthnDeviceHint = () => {
    try {
        return localStorage.getItem(WEBAUTHN_DEVICE_KEY) === '1';
    } catch {
        return false;
    }
};

export const clearWebAuthnDeviceHint = () => {
    try {
        localStorage.removeItem(WEBAUTHN_DEVICE_KEY);
    } catch {
        // ignore
    }
};

export const isBioSetupDismissed = () => {
    try {
        return localStorage.getItem(BIO_SETUP_DISMISSED_KEY) === '1';
    } catch {
        return false;
    }
};

export const dismissBioSetup = () => {
    try {
        localStorage.setItem(BIO_SETUP_DISMISSED_KEY, '1');
    } catch {
        // ignore
    }
};

let deferredInstallPrompt = null;

export const bindInstallPrompt = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
    });

    window.addEventListener('appinstalled', () => {
        markPwaInstalled();
        deferredInstallPrompt = null;
    });
};

export const canPromptInstall = () => !! deferredInstallPrompt;

export const promptInstall = async () => {
    if (! deferredInstallPrompt) {
        return { outcome: 'unavailable' };
    }

    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;

    if (outcome === 'accepted') {
        markPwaInstalled();
    }

    return { outcome };
};

let swReloadBound = false;
let swReloaded = false;

export const registerServiceWorker = async () => {
    if (! ('serviceWorker' in navigator)) {
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

        // Шинэ service worker удирдлага авбал нэг удаа сэргээнэ.
        if (! swReloadBound) {
            swReloadBound = true;

            navigator.serviceWorker.addEventListener('controllerchange', () => {
                if (swReloaded) {
                    return;
                }

                swReloaded = true;
                window.location.reload();
            });
        }

        // Апп руу буцаж ирэх бүрд шинэчлэлт байгаа эсэхийг шалгана.
        document.addEventListener('visibilitychange', () => {
            if (! document.hidden) {
                registration.update().catch(() => {});
            }
        });

        return registration;
    } catch {
        return null;
    }
};
