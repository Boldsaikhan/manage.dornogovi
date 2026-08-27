import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// APP_URL http/https зөрсөн үед iPhone дээр POST өөр origin руу явж cookie илгээгдэхгүй.
if (typeof window.Ziggy === 'object' && window.Ziggy) {
    window.Ziggy.url = window.location.origin;
}

const cookieFlags = window.location.protocol === 'https:'
    ? 'path=/; max-age=31536000; SameSite=Lax; Secure'
    : 'path=/; max-age=31536000; SameSite=Lax';

const isStandalonePwa = () => (
    window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true
);

if (isStandalonePwa()) {
    document.cookie = `pwa_standalone=1; ${cookieFlags}`;
    window.axios.defaults.headers.common['X-PWA-Standalone'] = '1';
} else if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent ?? '')) {
    document.cookie = `mobile_client=1; ${cookieFlags}`;
}
