/**
 * Хүснэгтийн толгойн хайлт — хоосон нүд олох.
 *
 * Нүдэнд «—» харагдана тул хайлтад «-», «—» эсвэл «хоосон» гэж бичнэ.
 */

const EMPTY_NEEDLES = new Set(['-', '—', '–', '−', 'хоосон', 'empty']);

export const EMPTY_FILTER_HINT = 'Хоосон талбар: − эсвэл хоосон';

export const EMPTY_FILTER_PLACEHOLDER = 'Хайх (−)';

/** @param {unknown} needle */
export function isEmptyFilterNeedle(needle) {
    const text = String(needle ?? '').trim().toLowerCase();

    return EMPTY_NEEDLES.has(text);
}

/** @param {unknown} value */
export function isBlankCellValue(value) {
    if (value === null || value === undefined) {
        return true;
    }

    const text = String(value).trim();

    if (text === '') {
        return true;
    }

    return text === '-' || text === '—' || text === '–' || text === '−';
}

/**
 * @param {unknown} value
 * @param {unknown} needle
 * @param {{ digits?: boolean, normalize?: (value: unknown) => string }} [options]
 */
export function cellMatchesFilter(value, needle, options = {}) {
    const text = String(needle ?? '').trim();

    if (text === '') {
        return true;
    }

    if (isEmptyFilterNeedle(text)) {
        return isBlankCellValue(value);
    }

    if (isBlankCellValue(value)) {
        return false;
    }

    if (options.digits) {
        const digitsOnly = (raw) => String(raw ?? '').replace(/\D+/g, '');

        return digitsOnly(value).includes(digitsOnly(text));
    }

    const normalize = options.normalize
        ?? ((raw) => String(raw ?? '').toLowerCase());

    return normalize(value).includes(normalize(text));
}
