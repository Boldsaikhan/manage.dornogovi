import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

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

// Утасны хөтчөөр л ашиглана — хуучин PWA service worker-ийг салгана.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.getRegistrations().then((regs) => {
            regs.forEach((reg) => reg.unregister());
        }).catch(() => {});

        if (window.caches?.keys) {
            caches.keys().then((keys) => {
                keys.forEach((key) => caches.delete(key));
            }).catch(() => {});
        }
    });
}
