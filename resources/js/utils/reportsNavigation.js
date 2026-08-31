/**
 * @param {Array<{ key?: string, children?: Array }>} items
 * @returns {string|null}
 */
export function firstReportKeyFromItems(items) {
    for (const item of items ?? []) {
        if (item.key) {
            return item.key;
        }

        const nested = firstReportKeyFromItems(item.children);
        if (nested) {
            return nested;
        }
    }

    return null;
}

/**
 * @param {Array<{ key: string, children?: Array }>} sections
 * @param {string} sectionKey
 * @returns {string|null}
 */
export function firstReportKeyInSection(sections, sectionKey) {
    const section = sections.find((item) => item.key === sectionKey);

    return section ? firstReportKeyFromItems(section.children) : null;
}

/**
 * @param {Array<{ key: string, children?: Array }>} sections
 * @param {string|null} sectionKey
 * @returns {{ key: string, children?: Array }|null}
 */
export function findReportSection(sections, sectionKey) {
    if (! sectionKey) {
        return null;
    }

    return sections.find((item) => item.key === sectionKey) ?? null;
}
