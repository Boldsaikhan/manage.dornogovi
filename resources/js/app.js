import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { isMobileDevice } from './utils/mobileClient';
import {
    bindInstallPrompt,
    isStandalonePwa,
    markPwaInstalled,
    registerServiceWorker,
} from './utils/pwaClient';

const appName = 'manage дотоод систем';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

router.on('invalid', (event) => {
    if (event.detail?.response?.status !== 419) {
        return;
    }

    event.preventDefault();

    const last = Number(sessionStorage.getItem('csrf_reload_at') || 0);
    if (Date.now() - last < 8000) {
        return;
    }

    sessionStorage.setItem('csrf_reload_at', String(Date.now()));
    window.location.reload();
});

// Утсан дээр PWA service worker — push, offline, апп суулгалт.
if (isStandalonePwa()) {
    markPwaInstalled();
}

if (isMobileDevice() && 'serviceWorker' in navigator) {
    bindInstallPrompt();
    window.addEventListener('load', () => {
        registerServiceWorker().catch(() => {});
    });
}
