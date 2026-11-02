import { ref, watch } from 'vue';

const STORAGE_KEY = 'reports.subnav-open';

const open = ref(false);
let booted = false;

function boot() {
    if (booted || typeof window === 'undefined') {
        return;
    }

    booted = true;
    open.value = window.localStorage.getItem(STORAGE_KEY) === '1';

    watch(open, (value) => {
        window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    });
}

/**
 * Дэд тайлангийн цэс. Анхдагчаар хураагдсан — хүснэгт дэлгэцийг дүүргэнэ.
 * Ангилал сонгоход нээгдэнэ.
 */
export function useReportSubnav() {
    boot();

    return open;
}

export function openReportSubnav() {
    boot();
    open.value = true;
}
