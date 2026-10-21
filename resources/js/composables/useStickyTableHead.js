import { nextTick, onBeforeUnmount, onMounted } from 'vue';

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

        if (! head) {
            return;
        }

        let top = 0;

        Array.from(head.rows).forEach((row, index) => {
            // 1 пикселээр давхарлана — хүрээ хуваалцсанаас үүсэх завсрыг арилгана.
            const offset = index === 0 ? 0 : Math.max(0, Math.round(top) - index);

            Array.from(row.cells).forEach((cell) => {
                cell.style.position = 'sticky';
                cell.style.top = `${offset}px`;
                /*
                 * 20-оос доош — толгойн самбар (header, z-20) дээр нь байх
                 * ёстой. Үгүй бол мэдэгдэл, хэрэглэгчийн цэс зэрэг нь
                 * наалдсан толгойн ард нуугдана.
                 */
                cell.style.zIndex = String(14 - index);
            });

            top += row.getBoundingClientRect().height;
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
