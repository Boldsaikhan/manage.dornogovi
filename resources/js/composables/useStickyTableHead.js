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

        /*
         * Мөр бүрийн байрлалыг эхний мөрийн дээд ирмэгээс хэмжинэ.
         *
         * Өндрүүдийг нэмж бодвол rowspan-тай нүд (жишээ нь «Албан хаагч»)
         * мөрийн өндрийг гуйвуулж, толгойн мөрүүдийн хооронд завсар үүсч
         * доогуурх мөр нэвт харагддаг.
         */
        const base = head.rows[0].getBoundingClientRect().top;

        Array.from(head.rows).forEach((row, index) => {
            // 2 пикселээр давхарлана — мөр хоорондын завсраар бие харагдахыг хаана.
            const top = row.getBoundingClientRect().top - base;
            const offset = index === 0 ? 0 : Math.max(0, Math.round(top) - (index * 2));

            Array.from(row.cells).forEach((cell) => {
                cell.style.position = 'sticky';
                cell.style.top = `${offset}px`;
                /*
                 * 20-оос доош — толгойн самбар (header, z-20) дээр нь байх
                 * ёстой. Үгүй бол мэдэгдэл, хэрэглэгчийн цэс зэрэг нь
                 * наалдсан толгойн ард нуугдана.
                 */
                cell.style.zIndex = String(16 - index);
                cell.style.isolation = 'isolate';

                /*
                 * Sticky үед дэвсгэр тунгалаг/алга болбол доогуурх мөр
                 * нэвт харагдана — тооцоолсон өнгийг шууд шахаж бэхжүүлнэ.
                 */
                const computed = window.getComputedStyle(cell).backgroundColor;
                const opaque = computed
                    && computed !== 'transparent'
                    && ! computed.startsWith('rgba(0, 0, 0, 0)')
                    && computed !== 'rgba(0,0,0,0)';

                cell.style.backgroundColor = opaque
                    ? computed
                    : (row.classList.contains('ui-filters') ? '#ffffff' : '#eff6ff');
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
