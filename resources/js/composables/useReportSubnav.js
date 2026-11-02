import { ref, watch } from 'vue';

const STORAGE_KEY = 'reports.subnav-open';

/**
 * Дэд тайлангийн цэс. Анхдагчаар хураагдсан — хүснэгт дэлгэцийг дүүргэнэ.
 */
export function useReportSubnav() {
    const open = ref(false);

    if (typeof window !== 'undefined' && window.localStorage.getItem(STORAGE_KEY) === '1') {
        open.value = true;
    }

    watch(open, (value) => {
        window.localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    });

    return open;
}
