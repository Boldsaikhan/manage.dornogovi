/**
 * 14 сумын Засаг дарга нарыг нэг бүлгээр сонгох / харуулах.
 */

export const SOUM_GOVERNORS_LABEL = 'Сумдын Засаг дарга нар';

/** Зөвхөн сумын Засаг дарга — баг/орлогч/түр орлонг оруулахгүй. */
export const isSoumGovernorTitle = (text) => {
    const t = String(text ?? '').toLowerCase().replace(/\s+/g, ' ').trim();
    if (! t) return false;

    if (
        t.includes('баг')
        || t.includes('хороо')
        || t.includes('орлогч')
        || t.includes('түр орлон')
        || t.includes('үүрэг гүйцэтгэгч')
        || t.includes('аймгийн')
    ) {
        return false;
    }

    return t === 'засаг дарга' || t === 'сумын засаг дарга';
};

/**
 * Утасны жагсаалтаас сумын Засаг дарга нарыг гаргана (сум бүрт нэг).
 * @param {Array<{value?: string, label?: string, hint?: string, org?: string, category?: string}>} people
 */
export const filterSoumGovernors = (people) => {
    if (! Array.isArray(people) || ! people.length) return [];

    const seenOrgs = new Set();
    const result = [];

    for (const opt of people) {
        if ((opt.category || '') !== 'sum') continue;
        if (! isSoumGovernorTitle(opt.hint)) continue;

        const name = String(opt.value ?? opt.label ?? '').trim();
        if (! name) continue;

        const orgKey = String(opt.org ?? '')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim() || name;

        if (seenOrgs.has(orgKey)) continue;
        seenOrgs.add(orgKey);
        result.push(opt);
    }

    return result;
};

export const soumGovernorNames = (people) => (
    filterSoumGovernors(people).map((o) => String(o.value ?? o.label ?? '')).filter(Boolean)
);

/** Хадгалсан утгыг нэрийн жагсаалт болгоно (бүлгийн нэрийг задлана). */
export const expandPersonNames = (value, people) => {
    const parts = String(value ?? '')
        .split(/[/;,|]+/)
        .map((s) => s.trim())
        .filter(Boolean);

    if (! parts.includes(SOUM_GOVERNORS_LABEL)) {
        return parts;
    }

    const govs = soumGovernorNames(people);
    const extras = parts.filter((n) => n !== SOUM_GOVERNORS_LABEL);

    return [...new Set([...govs, ...extras])];
};

/** Сонгосон нэрсийг хадгалах хэлбэрт оруулна (бүлгийг нэгтгэнэ). */
export const serializePersonNames = (names, people) => {
    const list = (Array.isArray(names) ? names : [])
        .map((n) => String(n ?? '').trim())
        .filter(Boolean);

    const govs = soumGovernorNames(people);
    if (! govs.length) {
        return list.join('/');
    }

    const set = new Set(list);
    const hasAll = govs.every((n) => set.has(n));
    if (! hasAll) {
        return list.join('/');
    }

    const govSet = new Set(govs);
    const extras = list.filter((n) => ! govSet.has(n) && n !== SOUM_GOVERNORS_LABEL);

    return [SOUM_GOVERNORS_LABEL, ...extras].join('/');
};

/** Хүснэгтэнд харуулах текст. */
export const formatPersonNamesDisplay = (value, people) => {
    const parts = String(value ?? '')
        .split(/[/;,|]+/)
        .map((s) => s.trim())
        .filter(Boolean);

    if (! parts.length) return '';

    if (parts.length === 1 && parts[0] === SOUM_GOVERNORS_LABEL) {
        return SOUM_GOVERNORS_LABEL;
    }

    const govs = soumGovernorNames(people);
    if (! govs.length) return parts.join(' / ');

    const set = new Set(parts);
    const hasAll = govs.every((n) => set.has(n))
        || parts.includes(SOUM_GOVERNORS_LABEL);

    if (! hasAll) return parts.join(' / ');

    const govSet = new Set(govs);
    const extras = parts.filter((n) => ! govSet.has(n) && n !== SOUM_GOVERNORS_LABEL);
    if (! extras.length) return SOUM_GOVERNORS_LABEL;

    return [SOUM_GOVERNORS_LABEL, ...extras].join(' / ');
};
