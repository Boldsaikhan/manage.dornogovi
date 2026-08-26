import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const isStandalonePwa = () => (
    window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true
);

if (isStandalonePwa()) {
    document.cookie = 'pwa_standalone=1; path=/; max-age=31536000; SameSite=Lax';
    window.axios.defaults.headers.common['X-PWA-Standalone'] = '1';
}
