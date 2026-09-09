import * as pdfjs from 'pdfjs-dist/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

import { buildPdfFromJpegs } from '@/Support/jpegPdf.js';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

/**
 * Хэмжээ хэтэрсэн PDF-ийг хязгаарт багтаана.
 *
 * Хуудас бүрийг зурган болгон буулгаж (raster), JPEG-р шахаад PDF-ээ дахин
 * угсарна. Нягтрал, чанарыг аажмаар бууруулж хязгаарт багтах хүртэл оролдоно.
 * Хязгаарт багтаж байгаа файлыг хөндөхгүй — эх хувиараа хадгалагдана.
 */

/** Оролдлогууд: нягтрал (dpi-тэй дүйцэх scale) ба JPEG чанар. */
const ATTEMPTS = [
    { scale: 1.6, quality: 0.72 },
    { scale: 1.3, quality: 0.62 },
    { scale: 1.1, quality: 0.52 },
    { scale: 0.9, quality: 0.45 },
    { scale: 0.7, quality: 0.38 },
];

const canvasToJpeg = (canvas, quality) => new Promise((resolve, reject) => {
    canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error('Хуудсыг зурган болгож чадсангүй.'))),
        'image/jpeg',
        quality,
    );
});

const rasterize = async (doc, scale, quality) => {
    const pages = [];

    for (let number = 1; number <= doc.numPages; number += 1) {
        // eslint-disable-next-line no-await-in-loop
        const page = await doc.getPage(number);
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale });

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.floor(viewport.width));
        canvas.height = Math.max(1, Math.floor(viewport.height));

        const context = canvas.getContext('2d');
        // JPEG-д ил тод байдал байхгүй тул цаасны цагаан дэвсгэрийг эхэлж зурна.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);

        // eslint-disable-next-line no-await-in-loop
        await page.render({ canvasContext: context, viewport }).promise;

        // eslint-disable-next-line no-await-in-loop
        const blob = await canvasToJpeg(canvas, quality);
        // eslint-disable-next-line no-await-in-loop
        const bytes = new Uint8Array(await blob.arrayBuffer());

        pages.push({
            bytes,
            width: base.width,
            height: base.height,
            pixelWidth: canvas.width,
            pixelHeight: canvas.height,
        });

        canvas.width = 0;
        canvas.height = 0;
        page.cleanup();
    }

    return buildPdfFromJpegs(pages);
};

/**
 * @param {File} file  Хэрэглэгчийн сонгосон PDF.
 * @param {number} maxBytes  Зөвшөөрөх дээд хэмжээ.
 * @returns {Promise<File>} Хязгаарт багтсан PDF.
 */
export async function compressPdfToLimit(file, maxBytes) {
    if (file.size <= maxBytes) {
        return file;
    }

    const data = new Uint8Array(await file.arrayBuffer());
    const doc = await pdfjs.getDocument({ data, isEvalSupported: false }).promise;

    try {
        let best = null;

        for (const attempt of ATTEMPTS) {
            // eslint-disable-next-line no-await-in-loop
            const blob = await rasterize(doc, attempt.scale, attempt.quality);

            if (blob.size <= maxBytes) {
                best = blob;
                break;
            }

            if (! best || blob.size < best.size) {
                best = blob;
            }
        }

        if (! best || best.size > maxBytes) {
            throw new Error('Файлыг 2MB хүртэл шахаж чадсангүй. Хуудсыг нь цөөрүүлж оролдоно уу.');
        }

        const name = (file.name.replace(/\.[^.]+$/, '') || 'decree') + '.pdf';

        return new File([best], name, { type: 'application/pdf', lastModified: Date.now() });
    } finally {
        doc.destroy();
    }
}
