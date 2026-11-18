/**
 * Хүснэгтийн нүд (SheetCell, РД, огноо) — мөр хаахаас өмнө
 * blur timer амжихгүй үед өөрчлөлтийг алдахгүйн тулд flush хийнэ.
 */

const flushers = new Set();

/** @param {() => void} fn */
export function registerSheetEditorFlusher(fn) {
    flushers.add(fn);

    return () => {
        flushers.delete(fn);
    };
}

export function flushSheetEditors() {
    [...flushers].forEach((fn) => {
        try {
            fn();
        } catch {
            // Нэг нүд алдаатай байсан ч бусдыг үргэлжлүүлнэ.
        }
    });
}
