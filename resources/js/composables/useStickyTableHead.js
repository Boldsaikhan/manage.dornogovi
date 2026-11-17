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
 * мөр бүрийн `top`-ыг өмнөх мөрүүдийн offsetHeight-ээр нэмж тавина.
 *
 * getBoundingClientRect ашиглахгүй — sticky идэвхтэй үед бүх мөр ижил
 * байрлал руу «наалдаж» хэмжилт эвдэрч, толгой нэгтгэгдсэн мэт харагддаг.
 *
 * @param {() => (Element|null|undefined)} getRoot Хүснэгтийг агуулсан элемент
 */
export function useStickyTableHead(getRoot) {
    let resizeObserver = null;

    const sync = () => {
        const root = getRoot();
        const head = root?.querySelector?.('thead');

        if (! head || ! head.rows.length) {
            return;
        }

        const dark = document.documentElement.classList.contains('dark');
        let cumulative = 0;

        Array.from(head.rows).forEach((row, index) => {
            const top = cumulative;
            const isFilter = row.classList.contains('ui-filters');
            const isLast = index === head.rows.length - 1;

            Array.from(row.cells).forEach((cell) => {
                const isActions = cell.classList.contains('ui-sticky-actions');
                const isNo = cell.classList.contains('ui-sticky-no');
                /*
                 * Зүүн № / баруун үйлдэл — бусад толгойноос дээгүүр.
                 * Дээд мөр доод мөрөөсөө өндөр z-index-тэй (давхцахад
                 * дээд гарчиг доод дээрээ биш, зөв давхаргална).
                 */
                const baseZ = isNo || isActions ? 19 : 16;

                cell.style.setProperty('position', 'sticky');
                cell.style.setProperty('top', `${top}px`);
                cell.style.setProperty('z-index', String(baseZ - index));
                /*
                 * Тооцоолсон өнгөнд бүү найд — sticky table cell зарим
                 * хөтөчид transparent гэж ирдэг. Бүрэн hex + important.
                 */
                cell.style.setProperty(
                    'background-color',
                    resolveBackground(cell, row, dark),
                    'important',
                );
                cell.style.setProperty('background-clip', 'border-box');

                if (isLast || isFilter) {
                    cell.style.setProperty(
                        'box-shadow',
                        dark
                            ? 'inset -1px 0 0 rgb(71 85 105), inset 0 -2px 0 rgb(100 116 139), 0 4px 6px -4px rgb(0 0 0 / 0.4)'
                            : 'inset -1px 0 0 #bfdbfe, inset 0 -2px 0 #93c5fd, 0 4px 6px -4px rgb(15 23 42 / 0.12)',
                    );
                }
            });

            // TR-ийн бодит өндөр — sticky байрлалаас хамаарахгүй.
            cumulative += Math.max(row.offsetHeight, 1);
        });
    };

    const schedule = () => nextTick(() => requestAnimationFrame(sync));

    const observe = () => {
        resizeObserver?.disconnect();
        resizeObserver = null;

        const head = getRoot()?.querySelector?.('thead');

        if (! head || typeof ResizeObserver === 'undefined') {
            return;
        }

        resizeObserver = new ResizeObserver(() => sync());
        resizeObserver.observe(head);
        Array.from(head.rows).forEach((row) => resizeObserver.observe(row));
    };

    onMounted(() => {
        schedule();
        nextTick(observe);
        // Фонт хожуу ачаалагдвал толгойн өндөр өөрчлөгддөг тул дахин хэмжинэ.
        setTimeout(() => {
            sync();
            observe();
        }, 400);
        window.addEventListener('resize', sync);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('resize', sync);
        resizeObserver?.disconnect();
        resizeObserver = null;
    });

    return { sync, schedule };
}
