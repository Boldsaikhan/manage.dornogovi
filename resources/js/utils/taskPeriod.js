const DAY_MS = 24 * 60 * 60 * 1000;

export const MONGOLIAN_WEEKDAYS = ['Да', 'Мя', 'Лх', 'Пү', 'Ба', 'Бя', 'Ня'];

export const MONGOLIAN_MONTHS = [
    '1-р сар', '2-р сар', '3-р сар', '4-р сар', '5-р сар', '6-р сар',
    '7-р сар', '8-р сар', '9-р сар', '10-р сар', '11-р сар', '12-р сар',
];

const normalizePeriod = (period) => String(period ?? '')
    .trim()
    .replace(/[–—]/g, '-')
    .replace(/\s+/g, '');

const toDateOnly = (year, month, day) => new Date(year, month - 1, day);

const stripTime = (date) => {
    if (!date || Number.isNaN(date.getTime())) {
        return null;
    }

    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
};

/**
 * @param {string|null|undefined} period
 * @param {number} [referenceYear]
 * @returns {{ start: Date|null, end: Date|null, label: string, unparsed?: boolean }|null}
 */
export function parseTaskPeriod(period, referenceYear = new Date().getFullYear()) {
    const raw = String(period ?? '').trim();
    if (!raw) {
        return null;
    }

    const s = normalizePeriod(raw);

    const isoRange = /^(\d{4}-\d{2}-\d{2})-(\d{4}-\d{2}-\d{2})$/.exec(s);
    if (isoRange) {
        return {
            start: stripTime(new Date(isoRange[1])),
            end: stripTime(new Date(isoRange[2])),
            label: raw,
        };
    }

    const isoSingle = /^(\d{4}-\d{2}-\d{2})$/.exec(s);
    if (isoSingle) {
        const date = stripTime(new Date(isoSingle[1]));

        return { start: date, end: date, label: raw };
    }

    const dotFullRange = /^(\d{1,2})\.(\d{1,2})\.(\d{4})-(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(s);
    if (dotFullRange) {
        const start = toDateOnly(+dotFullRange[3], +dotFullRange[2], +dotFullRange[1]);
        const end = toDateOnly(+dotFullRange[6], +dotFullRange[5], +dotFullRange[4]);

        return { start, end, label: raw };
    }

    const dotFullSingle = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(s);
    if (dotFullSingle) {
        const date = toDateOnly(+dotFullSingle[3], +dotFullSingle[2], +dotFullSingle[1]);

        return { start: date, end: date, label: raw };
    }

    const mdRange = /^(\d{1,2})\.(\d{1,2})-(\d{1,2})\.(\d{1,2})$/.exec(s);
    if (mdRange) {
        let start = toDateOnly(referenceYear, +mdRange[1], +mdRange[2]);
        let end = toDateOnly(referenceYear, +mdRange[3], +mdRange[4]);

        if (end < start) {
            end = toDateOnly(referenceYear + 1, +mdRange[3], +mdRange[4]);
        }

        return { start, end, label: raw };
    }

    const mdSingle = /^(\d{1,2})\.(\d{1,2})$/.exec(s);
    if (mdSingle) {
        const date = toDateOnly(referenceYear, +mdSingle[1], +mdSingle[2]);

        return { start: date, end: date, label: raw };
    }

    return { start: null, end: null, label: raw, unparsed: true };
}

/**
 * @param {Date} date
 * @returns {string}
 */
export function dateKey(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/**
 * @param {{ period?: string|null }} task
 * @param {number} year
 * @param {number} month 1-12
 * @param {number} day
 */
export function taskOverlapsDay(task, year, month, day) {
    const parsed = parseTaskPeriod(task?.period, year);
    if (!parsed || parsed.unparsed || !parsed.start || !parsed.end) {
        return false;
    }

    const target = toDateOnly(year, month, day).getTime();
    const start = parsed.start.getTime();
    const end = parsed.end.getTime();

    return target >= start && target <= end;
}

/**
 * @param {number} year
 * @param {number} month 0-11
 * @returns {Array<{ date: Date, inMonth: boolean, key: string }>}
 */
export function calendarCells(year, month) {
    const first = new Date(year, month, 1);
    const startOffset = (first.getDay() + 6) % 7; // Monday-first
    const cells = [];
    const cursor = new Date(year, month, 1 - startOffset);

    for (let i = 0; i < 42; i += 1) {
        const date = new Date(cursor);
        cells.push({
            date,
            inMonth: date.getMonth() === month,
            key: dateKey(date),
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return cells;
}

/**
 * @param {Array<{ id: number|string, period?: string|null, text?: string|null, no?: number|string }>} tasks
 * @param {number} year
 * @param {number} month 0-11
 */
export function tasksForCalendarMonth(tasks, year, month) {
    const byDay = new Map();
    const unscheduled = [];

    tasks.forEach((task) => {
        const parsed = parseTaskPeriod(task.period, year);
        if (!parsed || parsed.unparsed || !parsed.start || !parsed.end) {
            if (String(task.period ?? '').trim()) {
                unscheduled.push(task);
            }

            return;
        }

        let cursor = stripTime(parsed.start);
        const end = stripTime(parsed.end);
        if (!cursor || !end) {
            return;
        }

        while (cursor.getTime() <= end.getTime()) {
            if (cursor.getMonth() === month && cursor.getFullYear() === year) {
                const key = dateKey(cursor);
                if (!byDay.has(key)) {
                    byDay.set(key, []);
                }
                byDay.get(key).push(task);
            }

            cursor = new Date(cursor.getTime() + DAY_MS);
        }
    });

    byDay.forEach((list) => {
        list.sort((a, b) => String(a.period || '').localeCompare(String(b.period || ''), 'mn')
            || String(a.text || '').localeCompare(String(b.text || ''), 'mn'));
    });

    unscheduled.sort((a, b) => String(a.period || '').localeCompare(String(b.period || ''), 'mn'));

    return { byDay, unscheduled };
}
