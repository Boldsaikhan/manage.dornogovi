/**
 * Огноо — он · сар · өдөр дараалал (YYYY-MM-DD хадгална, YYYY.MM.DD харуулна).
 */

/** @returns {string|null} YYYY-MM-DD */
export const parseYmd = (value) => {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const raw = String(value).trim();

    // ISO datetime → огноо
    const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) {
        return validYmd(iso[1], iso[2], iso[3]);
    }

    // YYYY.MM.DD / YYYY/MM/DD / YYYY-M-D
    const dotted = raw.match(/^(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})$/);
    if (dotted) {
        return validYmd(dotted[1], dotted[2], dotted[3]);
    }

    // Зөвхөн цифр: YYYYMMDD
    const digits = raw.replace(/\D+/g, '');
    if (digits.length === 8) {
        return validYmd(digits.slice(0, 4), digits.slice(4, 6), digits.slice(6, 8));
    }

    return null;
};

/** Харуулах: YYYY.MM.DD */
export const formatYmdDisplay = (value) => {
    const ymd = parseYmd(value);

    return ymd ? ymd.replace(/-/g, '.') : '';
};

/** Бичих маск руу: цифрүүдийг YYYY.MM.DD болгоно. */
export const maskYmdInput = (raw) => {
    const digits = String(raw ?? '').replace(/\D+/g, '').slice(0, 8);

    if (digits.length <= 4) {
        return digits;
    }

    if (digits.length <= 6) {
        return `${digits.slice(0, 4)}.${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}.${digits.slice(4, 6)}.${digits.slice(6)}`;
};

const pad2 = (n) => String(n).padStart(2, '0');

const validYmd = (y, m, d) => {
    const year = Number(y);
    const month = Number(m);
    const day = Number(d);

    if (! Number.isFinite(year) || year < 1900 || year > 2100) {
        return null;
    }

    if (month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    const dt = new Date(year, month - 1, day);

    if (
        dt.getFullYear() !== year
        || dt.getMonth() !== month - 1
        || dt.getDate() !== day
    ) {
        return null;
    }

    return `${year}-${pad2(month)}-${pad2(day)}`;
};

export const daysInMonth = (year, month) => {
    if (! year || ! month) {
        return 31;
    }

    return new Date(year, month, 0).getDate();
};

export const joinYmdParts = (year, month, day) => {
    if (! year || ! month || ! day) {
        return null;
    }

    return validYmd(year, month, day);
};

export const splitYmdParts = (value) => {
    const ymd = parseYmd(value);

    if (! ymd) {
        return { year: '', month: '', day: '' };
    }

    const [year, month, day] = ymd.split('-');

    return { year, month, day };
};
