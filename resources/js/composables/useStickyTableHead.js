import { nextTick, onBeforeUnmount, onMounted } from 'vue';

/** brand-navy-50 — sticky үед мөр нэвт харагдахгүй байх ёстой. */
const HEAD_BG = '#f3f7fc';
const FILTER_BG = '#ffffff';
const HEAD_BG_DARK = '#1e293b'; // slate-800
const FILTER_BG_DARK = '#1e293b';

const GROUP_BG = {
    'ui-register__head-group--issued': '#f0f9ff', // sky-50
    'ui-register__head-group--numbers': '#ecfdf5', // emerald-50
    'ui-register__head-group--void': '#fffbeb', // amber-50
};

const GROUP_BG_DARK = {
    'ui-register__head-group--issued': '#082f49', // sky-950
    'ui-register__head-group--numbers': '#022c22', // emerald-950
    'ui-register__head-group--void': '#451a03', // amber-950-ish
};

const resolveBackground = (cell, row, dark) => {
    if (row.classList.contains('ui-filters')) {
        return dark ? FILTER_BG_DARK : FILTER_BG;
    }

    const groups = dark ? GROUP_BG_DARK : GROUP_BG;

    for (const [className, color] of Object.entries(groups)) {
        if (cell.classList.contains(className)) {
            return color;
        }
    }

    return dark ? HEAD_BG_DARK : HEAD_BG;
};

/**
 * Олон мөрт хүснэгтийн толгойг наалдуулна.
 *
 * `thead` дотор 2-3 мөр (гарчиг, дэд гарчиг, хайлтын мөр) байдаг тул
 * мөр бүрийн `top`-ыг дээд мөрүүдийн бодит өндрөөр тооцож тавина. CSS
 * дээр тогтмол утга бичвэл фонт, зай өөрчлөгдөхөд толгой эвдэрдэг.
 *
 * @param {() => (Element|null|undefined)} getRoot Хүснэгтийг агуулсан элемент
 */
export function useStickyTableHead(getRoot) {
    const sync = () => {
        const head = getRoot()?.querySelector('thead');

        if (! head || ! head.rows.length) {
            return;
        }

        const dark = document.documentElement.classList.contains('dark');

        /*
         * Байрлалыг эхний мөрийн ирмэгээс хэмжинэ — өндрүүдийг нэмж бодвол
         * rowspan-тай нүд завсар үүсгэж, доогуурх мөр нэвт харагддаг.
         */
        const base = head.rows[0].getBoundingClientRect().top;

        Array.from(head.rows).forEach((row, index) => {
            // 1 пикселээр давхарлана — хүрээ/subpixel-ээс үүсэх завсрыг арилгана.
            const top = row.getBoundingClientRect().top - base;
            const offset = index === 0 ? 0 : Math.max(0, Math.round(top) - index);

            Array.from(row.cells).forEach((cell) => {
                const isActions = cell.classList.contains('ui-sticky-actions');

                cell.style.position = 'sticky';
                cell.style.top = `${offset}px`;
                /*
                 * 20-оос доош — толгойн самбар (header, z-20) дээр нь байх
                 * ёстой. Үйлдлийн багана нь мөрөөсөө дээгүүр наалдана.
                 */
                cell.style.zIndex = String((isActions ? 18 : 16) - index);
                /*
                 * Тооцоолсон өнгөнд бүү найд — sticky table cell зарим
                 * хөтөчид transparent гэж ирдэг. Бүрэн hex өнгө шахана.
                 */
                cell.style.backgroundColor = resolveBackground(cell, row, dark);
                cell.style.backgroundClip = 'border-box';
            });
        });
    };

    const schedule = () => nextTick(() => requestAnimationFrame(sync));

    onMounted(() => {
        schedule();
        // Фонт хожуу ачаалагдвал толгойн өндөр өөрчлөгддөг тул дахин хэмжинэ.
        setTimeout(sync, 400);
        window.addEventListener('resize', sync);
    });

    onBeforeUnmount(() => window.removeEventListener('resize', sync));

    return { sync, schedule };
}
