import { ref, watch } from 'vue';

const STORAGE_KEY = 'manage-theme';

const systemPrefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

/** 'light' | 'dark' | 'system' — app.blade.php-ийн inline script-тэй ижил логик. */
const mode = ref(localStorage.getItem(STORAGE_KEY) || 'system');

const applyMode = (value) => {
    const dark = value === 'dark' || (value === 'system' && systemPrefersDark());
    document.documentElement.classList.toggle('dark', dark);
};

applyMode(mode.value);

watch(mode, (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Хадгалж чадсангүй — зөвхөн энэ сешнд хэрэгжинэ.
    }
    applyMode(value);
});

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (mode.value === 'system') {
        applyMode('system');
    }
});

/** Цонх (компонент) бүрээс нэг л реактив төлөвийг хуваалцана. */
export function useTheme() {
    const cycle = () => {
        mode.value = mode.value === 'light' ? 'dark' : mode.value === 'dark' ? 'system' : 'light';
    };

    return { mode, cycle };
}
