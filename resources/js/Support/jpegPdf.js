/**
 * JPEG зургуудаас PDF файл угсарна.
 *
 * Гадны сан ашиглахгүй: JPEG-ийг PDF дотор DCTDecode урсгалаар шууд байрлуулна.
 * Хуудас бүр нэг зурагтай, зураг нь хуудсаа бүтэн дүүргэнэ.
 */

const encoder = new TextEncoder();

const ascii = (text) => encoder.encode(text);

/**
 * @param {Array<{bytes: Uint8Array, width: number, height: number}>} pages
 *        Хуудас бүрийн JPEG өгөгдөл, цэгийн (point) хэмжээ.
 * @returns {Blob} PDF файл.
 */
export function buildPdfFromJpegs(pages) {
    if (! pages.length) {
        throw new Error('Хуудас алга.');
    }

    const chunks = [];
    let length = 0;

    const push = (data) => {
        const bytes = typeof data === 'string' ? ascii(data) : data;
        chunks.push(bytes);
        length += bytes.length;
    };

    // 1 — каталог, 2 — хуудасны жагсаалт, дараа нь хуудас тус бүрд 3 объект.
    const objectCount = 2 + pages.length * 3;
    const offsets = new Array(objectCount + 1).fill(0);

    const beginObject = (number) => {
        offsets[number] = length;
        push(`${number} 0 obj\n`);
    };

    push('%PDF-1.4\n%\xE2\xE3\xCF\xD3\n');

    const pageNumber = (i) => 3 + i * 3;

    beginObject(1);
    push('<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');

    beginObject(2);
    const kids = pages.map((_, i) => `${pageNumber(i)} 0 R`).join(' ');
    push(`<< /Type /Pages /Kids [${kids}] /Count ${pages.length} >>\nendobj\n`);

    pages.forEach((page, i) => {
        const pageObj = pageNumber(i);
        const contentObj = pageObj + 1;
        const imageObj = pageObj + 2;
        const width = Math.max(1, Math.round(page.width));
        const height = Math.max(1, Math.round(page.height));

        beginObject(pageObj);
        push(
            `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${width} ${height}] `
            + `/Resources << /XObject << /Im0 ${imageObj} 0 R >> >> /Contents ${contentObj} 0 R >>\nendobj\n`,
        );

        const stream = `q\n${width} 0 0 ${height} 0 0 cm\n/Im0 Do\nQ\n`;
        beginObject(contentObj);
        push(`<< /Length ${stream.length} >>\nstream\n${stream}endstream\nendobj\n`);

        beginObject(imageObj);
        push(
            `<< /Type /XObject /Subtype /Image /Width ${page.pixelWidth} /Height ${page.pixelHeight} `
            + `/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${page.bytes.length} >>\nstream\n`,
        );
        push(page.bytes);
        push('\nendstream\nendobj\n');
    });

    const xrefOffset = length;
    push(`xref\n0 ${objectCount + 1}\n`);
    push('0000000000 65535 f \n');
    for (let i = 1; i <= objectCount; i += 1) {
        push(`${String(offsets[i]).padStart(10, '0')} 00000 n \n`);
    }
    push(`trailer\n<< /Size ${objectCount + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`);

    return new Blob(chunks, { type: 'application/pdf' });
}
