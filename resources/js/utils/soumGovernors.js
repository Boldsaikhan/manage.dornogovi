/**
 * Утасны жагсаалтаас бүлгээр сонгох (Засаг дарга, ЗДТГ, агентлаг…).
 */

export const SOUM_GOVERNORS_LABEL = 'Сумдын Засаг дарга нар';
export const SOUM_ZDTG_HEADS_LABEL = 'Сумдын ЗДТГ-ын дарга нар';
export const AGENCY_HEADS_LABEL = 'Агентлагын дарга нар';

const normalize = (text) => String(text ?? '').toLowerCase().replace(/\s+/g, ' ').trim();

const isDeputyOrActing = (t) => (
    t.includes('орлогч')
    || t.includes('түр орлон')
    || t.includes('үүрэг гүйцэтгэгч')
);

/** Зөвхөн сумын Засаг дарга — баг/орлогч оруулахгүй. */
export const isSoumGovernorTitle = (text) => {
    const t = normalize(text);
    if (! t) return false;
    if (t.includes('баг') || t.includes('хороо') || t.includes('аймгийн') || isDeputyOrActing(t)) {
        return false;
    }

    return t === 'засаг дарга' || t === 'сумын засаг дарга';
};

/** Сумын ЗДТГ / тамгын газрын дарга. */
export const isSoumZdtgHeadTitle = (hint, org = '') => {
    const t = normalize(hint);
    const o = normalize(org);
    if (! t) return false;
    if (isSoumGovernorTitle(t) || isDeputyOrActing(t)) return false;
    if (t.includes('баг') || t.includes('хороо') || t.includes('хэлтэс') || t.includes('алба')) {
        return false;
    }

    if (
        t.includes('здтг')
        || (t.includes('тамгын') && t.includes('дарга'))
    ) {
        return t.includes('дарга');
    }

    // Байгууллага ЗДТГ/тамга, албан тушаал нь «дарга» / «газрын дарга»
    const orgIsZdtg = o.includes('здтг') || o.includes('тамгын газар') || o.includes('засаг даргын тамгын');
    if (orgIsZdtg && (t === 'дарга' || t === 'газрын дарга' || t === 'тамгын газрын дарга')) {
        return true;
    }

    return false;
};

/** Агентлагийн тэргүүн дарга. */
export const isAgencyHeadTitle = (text) => {
    const t = normalize(text);
    if (! t) return false;
    if (isDeputyOrActing(t)) return false;
    if (t.includes('хэлтэс') || t.includes('алба') || t.includes('тасаг') || t.includes('баг') || t.includes('хороо')) {
        return false;
    }

    return t === 'дарга'
        || t === 'газрын дарга'
        || t === 'агентлагийн дарга'
        || t === 'ерөнхий захирал'
        || t === 'захирал';
};

/**
 * @typedef {{ key: string, label: string, category: string, match: Function, onePerOrg?: boolean }} PersonGroupDef
 */

/** @type {PersonGroupDef[]} */
export const PERSON_GROUPS = [
    {
        key: 'soum_governors',
        label: SOUM_GOVERNORS_LABEL,
        category: 'sum',
        onePerOrg: true,
        match: (opt) => isSoumGovernorTitle(opt.hint),
    },
    {
        key: 'soum_zdtg_heads',
        label: SOUM_ZDTG_HEADS_LABEL,
        category: 'sum',
        onePerOrg: true,
        match: (opt) => isSoumZdtgHeadTitle(opt.hint, opt.org),
    },
    {
        key: 'agency_heads',
        label: AGENCY_HEADS_LABEL,
        category: 'agentlag',
        onePerOrg: true,
        match: (opt) => isAgencyHeadTitle(opt.hint),
    },
];

export const GROUP_LABELS = PERSON_GROUPS.map((g) => g.label);

const filterByDef = (people, def) => {
    if (! Array.isArray(people) || ! people.length) return [];

    const seenOrgs = new Set();
    const result = [];

    for (const opt of people) {
        if ((opt.category || '') !== def.category) continue;
        if (! def.match(opt)) continue;

        const name = String(opt.value ?? opt.label ?? '').trim();
        if (! name) continue;

        if (def.onePerOrg) {
            const orgKey = normalize(opt.org) || name;
            if (seenOrgs.has(orgKey)) continue;
            seenOrgs.add(orgKey);
        }

        result.push(opt);
    }

    return result;
};

export const membersOfGroup = (people, groupKeyOrLabel) => {
    const def = PERSON_GROUPS.find((g) => g.key === groupKeyOrLabel || g.label === groupKeyOrLabel);
    if (! def) return [];

    return filterByDef(people, def);
};

export const namesOfGroup = (people, groupKeyOrLabel) => (
    membersOfGroup(people, groupKeyOrLabel).map((o) => String(o.value ?? o.label ?? '')).filter(Boolean)
);

/** UI-д харуулах бүлгүүд (ангиллын шүүлт идэвхтэй үед). */
export const visiblePersonGroups = (people, categoryOn = {}) => (
    PERSON_GROUPS
        .map((def) => {
            const members = filterByDef(people, def);

            return {
                ...def,
                members,
                names: members.map((o) => String(o.value ?? o.label ?? '')).filter(Boolean),
            };
        })
        .filter((g) => g.names.length > 0 && categoryOn[g.category] !== false)
);

// —— Хуучин API (нийцтэй байлгах) ——

export const filterSoumGovernors = (people) => membersOfGroup(people, 'soum_governors');
export const soumGovernorNames = (people) => namesOfGroup(people, 'soum_governors');

/** Хадгалсан утгыг нэрийн жагсаалт болгоно (бүлгийн нэрийг задлана). */
export const expandPersonNames = (value, people) => {
    const parts = String(value ?? '')
        .split(/[/;,|]+/)
        .map((s) => s.trim())
        .filter(Boolean);

    const expanded = [];
    for (const part of parts) {
        if (GROUP_LABELS.includes(part)) {
            expanded.push(...namesOfGroup(people, part));
        } else {
            expanded.push(part);
        }
    }

    return [...new Set(expanded)];
};

/** Сонгосон нэрсийг хадгалах хэлбэрт оруулна (бүлгийг нэгтгэнэ). */
export const serializePersonNames = (names, people) => {
    let list = (Array.isArray(names) ? names : [])
        .map((n) => String(n ?? '').trim())
        .filter(Boolean);

    // Том бүлгээс эхлэн шахаж хадгална.
    const ordered = [...PERSON_GROUPS].sort(
        (a, b) => namesOfGroup(people, b.key).length - namesOfGroup(people, a.key).length,
    );

    for (const def of ordered) {
        const govs = namesOfGroup(people, def.key);
        if (! govs.length) continue;

        const set = new Set(list);
        if (! govs.every((n) => set.has(n))) continue;

        const govSet = new Set(govs);
        const extras = list.filter((n) => ! govSet.has(n) && n !== def.label);
        list = [def.label, ...extras];
    }

    return list.join('/');
};

/** Хүснэгтэнд харуулах текст. */
export const formatPersonNamesDisplay = (value, people) => {
    const parts = String(value ?? '')
        .split(/[/;,|]+/)
        .map((s) => s.trim())
        .filter(Boolean);

    if (! parts.length) return '';

    // serialize хийгээд дахин задлаад харуулна.
    const serialized = serializePersonNames(expandPersonNames(value, people), people);
    if (! serialized) return parts.join(' / ');

    return serialized.split('/').map((s) => s.trim()).filter(Boolean).join(' / ');
};
