import * as pdfjs from 'pdfjs-dist/build/pdf.mjs';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

import { buildPdfFromJpegs } from '@/Support/jpegPdf.js';

/**
 * pdf.js-ийн worker-ийг ачаална.
 *
 * Сервер «.mjs» өргөтгөлийг «application/octet-stream» гэж илгээдэг бол
 * хөтөч модуль болгон ачаалахаас татгалздаг («Failed to fetch dynamically
 * imported module»). Тиймээс файлыг өөрсдөө татаж, зөв төрөлтэй Blob
 * болгоод өгнө. Татаж чадаагүй бол шууд хаягаар нь оролдоно.
 */
let workerReady = null;

const prepareWorker = () => {
    if (workerReady) {
        return workerReady;
    }

    workerReady = (async () => {
        try {
            const response = await fetch(workerUrl);

            if (! response.ok) {
                throw new Error('worker unavailable');
            }

            const code = await response.text();

            pdfjs.GlobalWorkerOptions.workerSrc = URL.createObjectURL(
                new Blob([code], { type: 'text/javascript' }),
            );
        } catch (e) {
            pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;
        }
    })();

    return workerReady;
};

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

/** A4 хуудасны хэмжээ (цэгээр). */
const A4 = { width: 595, height: 842 };

/** Зураг ачаалах. */
const loadImage = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();

    image.onload = () => {
        URL.revokeObjectURL(url);
        resolve(image);
    };

    image.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Зургийг уншиж чадсангүй.'));
    };

    image.src = url;
});

/**
 * Зургийг (PNG / JPG) PDF болгоно.
 *
 * Зураг нь A4 хуудсанд багтахаар байрлана. Хэмжээ нь хэтэрвэл нягтрал,
 * чанарыг аажмаар бууруулж хязгаарт багтаана.
 *
 * @param {File} file  Хэрэглэгчийн сонгосон зураг.
 * @param {number} maxBytes  Зөвшөөрөх дээд хэмжээ.
 * @returns {Promise<File>} PDF файл.
 */
export async function imageToPdf(file, maxBytes) {
    const image = await loadImage(file);

    const ratio = Math.min(A4.width / image.width, A4.height / image.height, 1);
    const pageWidth = Math.max(1, Math.round(image.width * ratio));
    const pageHeight = Math.max(1, Math.round(image.height * ratio));

    let best = null;

    for (const attempt of [
        { scale: 2, quality: 0.86 },
        { scale: 1.6, quality: 0.76 },
        { scale: 1.3, quality: 0.66 },
        { scale: 1, quality: 0.56 },
        { scale: 0.8, quality: 0.45 },
    ]) {
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(pageWidth * attempt.scale));
        canvas.height = Math.max(1, Math.round(pageHeight * attempt.scale));

        const context = canvas.getContext('2d');
        // PNG-д ил тод хэсэг байж болох тул цагаан дэвсгэр зурна.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        // eslint-disable-next-line no-await-in-loop
        const jpeg = await canvasToJpeg(canvas, attempt.quality);
        // eslint-disable-next-line no-await-in-loop
        const bytes = new Uint8Array(await jpeg.arrayBuffer());

        const blob = buildPdfFromJpegs([{
            bytes,
            width: pageWidth,
            height: pageHeight,
            pixelWidth: canvas.width,
            pixelHeight: canvas.height,
        }]);

        canvas.width = 0;
        canvas.height = 0;

        if (blob.size <= maxBytes) {
            best = blob;
            break;
        }

        if (! best || blob.size < best.size) {
            best = blob;
        }
    }

    if (! best || best.size > maxBytes) {
        const limit = Math.round(maxBytes / (1024 * 1024));

        throw new Error(`Зургийг ${limit}MB хүртэл багасгаж чадсангүй.`);
    }

    const name = (file.name.replace(/\.[^.]+$/, '') || 'decree') + '.pdf';

    return new File([best], name, { type: 'application/pdf', lastModified: Date.now() });
}

/**
 * @param {File} file  Хэрэглэгчийн сонгосон PDF.
 * @param {number} maxBytes  Зөвшөөрөх дээд хэмжээ.
 * @returns {Promise<File>} Хязгаарт багтсан PDF.
 */
export async function compressPdfToLimit(file, maxBytes) {
    if (file.size <= maxBytes) {
        return file;
    }

    await prepareWorker();

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
            const limit = Math.round(maxBytes / (1024 * 1024));

            throw new Error(`Файлыг ${limit}MB хүртэл шахаж чадсангүй. Хуудсыг нь цөөрүүлж оролдоно уу.`);
        }

        const name = (file.name.replace(/\.[^.]+$/, '') || 'decree') + '.pdf';

        return new File([best], name, { type: 'application/pdf', lastModified: Date.now() });
    } finally {
        doc.destroy();
    }
}
