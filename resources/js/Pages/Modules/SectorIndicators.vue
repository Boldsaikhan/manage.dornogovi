<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import { useStickyTableHead } from '@/composables/useStickyTableHead';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    unitId: { type: Number, default: null },
    categories: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    canPickUnit: { type: Boolean, default: false },
    measureUnits: { type: Array, default: () => [] },
});

const SLOT_LABELS = { half: 'хагас жил', year: 'жилийн эцэс' };

const registerEl = ref(null);
const { schedule: scheduleStickySync } = useStickyTableHead(() => registerEl.value);

/** Нүднүүдийн түр хуулбар — засаж байхад шууд харагдана. */
const drafts = reactive({});

const draftFromRow = (row) => ({
    title: row.title ?? '',
    category_id: row.category_id ?? '',
    measure_unit: row.measure_unit ?? '',
    target: row.target ?? '',
    values: { ...(row.values ?? {}) },
});

/** Засаж буй мөрийн draft-ийг бүү устга — олон нүд зэрэг оруулахад утга алдагдахгүй. */
const syncDrafts = () => {
    const open = new Set(editingIds.value.map((id) => Number(id)));
    const liveIds = new Set(props.rows.map((row) => Number(row.id)));

    Object.keys(drafts).forEach((key) => {
        const id = Number(key);

        if (! liveIds.has(id)) {
            delete drafts[key];
        }
    });

    props.rows.forEach((row) => {
        const id = Number(row.id);

        if (open.has(id) && drafts[id]) {
            return;
        }

        drafts[id] = draftFromRow(row);
    });
};

/** Толгойн доод мөрөнд хэдэн нүд гарах вэ. */
const valueColumnCount = computed(
    () => props.periods.reduce((sum, p) => sum + p.slots.length, 0),
);

function fitTitle(el) {
    const node = el?.target ?? el;

    if (! node || node.nodeType !== 1) {
        return;
    }

    node.style.height = 'auto';
    node.style.height = `${Math.max(node.scrollHeight, 40)}px`;
}

/** Олон мөрийг зэрэг засварлана — зөвхөн харандаа товчоор. */
const editingIds = ref([]);
const structureEditing = ref(false);
const rowEditing = (row) => props.canEdit && editingIds.value.map(Number).includes(Number(row.id));
const structureEditable = computed(() => structureEditing.value && props.canManage);
const titleTimers = {};

syncDrafts();
watch(() => props.rows, syncDrafts, { deep: true });

const focusCell = async (selector) => {
    await nextTick();
    await nextTick();
    const el = typeof selector === 'string'
        ? document.querySelector(selector)
        : selector;
    el?.focus?.();

    // Бүх текстийг select хийхгүй — бичихэд бэлэн курсор тавина.
    if (el && typeof el.setSelectionRange === 'function') {
        const len = String(el.value ?? '').length;
        el.setSelectionRange(len, len);
    }
};

/** Харандаа товчоор мөр засах горимд нээнэ (нүд дээр дарж нээхгүй). */
const beginEditAt = (row, cellSelector = null) => {
    if (! props.canEdit) {
        return;
    }

    const id = Number(row.id);

    if (! editingIds.value.map(Number).includes(id)) {
        editingIds.value = [...editingIds.value, id];
    }

    if (! drafts[id]) {
        drafts[id] = draftFromRow(row);
    }

    if (cellSelector) {
        focusCell(cellSelector);
    }
};

const totalColumns = computed(() => (
    4
    + valueColumnCount.value
    + 1 // Нийт
    + (structureEditable.value ? 1 : 0)
    + (props.canEdit ? 1 : 0)
));

/** Багануудын өргөн нийлээд 100% — хүснэгт дэлгэцээс халина. */
const columnWidths = computed(() => {
    const fixed = 4 + 12 + 9 + 8
        + (structureEditable.value ? 8 : 0)
        + (props.canEdit ? 7 : 0);
    const pool = Math.max(0, 100 - fixed);
    const count = valueColumnCount.value;
    const titleShare = count ? Math.max(24, pool * 0.38) : pool;
    const valueShare = count ? (pool - titleShare) / count : 0;

    return {
        title: `${titleShare}%`,
        value: `${valueShare}%`,
    };
});

/**
 * Мөрийн нийт — жил бүрийн эцсийн утгыг нэмнэ.
 * Хагас жил/жилийн эцэстэй баганад жилийн эцсийг авна; хоосон бол хагас жил.
 */
const rowTotal = (row) => {
    const values = (rowEditing(row) && drafts[row.id]?.values)
        ? drafts[row.id].values
        : (row.values || {});

    let total = 0;
    let has = false;

    props.periods.forEach((period) => {
        const yearRaw = values[cellKey(period, 'year')];
        const halfRaw = period.slots.includes('half') ? values[cellKey(period, 'half')] : '';
        const raw = (yearRaw !== undefined && yearRaw !== null && String(yearRaw).trim() !== '')
            ? yearRaw
            : halfRaw;
        const text = String(raw ?? '').trim();

        if (text === '' || text === '—') return;

        const cleaned = text.replace(/[\s\u00a0]/g, '').replace(',', '.').replace(/[^\d.-]/g, '');
        const number = Number(cleaned);

        if (cleaned === '' || Number.isNaN(number)) return;

        total += number;
        has = true;
    });

    if (! has) return null;

    const kind = unitOf(row.measure_unit || drafts[row.id]?.measure_unit)?.kind;

    if (kind === 'integer' || kind === 'money') return String(Math.round(total));

    if (kind === 'percent') {
        const fixed = total.toFixed(1);

        return fixed.replace(/\.0$/, '');
    }

    const fixed = total.toFixed(2);

    return fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
};

const flushRow = (row) => {
    const id = Number(row.id);

    if (titleTimers[id]) {
        clearTimeout(titleTimers[id]);
        delete titleTimers[id];
    }

    saveField(row, 'title');
    saveField(row, 'measure_unit');
    saveField(row, 'target');
};

const toggleRow = (row) => {
    const id = Number(row.id);

    if (editingIds.value.map(Number).includes(id)) {
        flushRow(row);
        editingIds.value = editingIds.value.filter((item) => Number(item) !== id);
    } else {
        beginEditAt(row, `[data-cell='title-${id}']`);
    }
};

const seenRowIds = new Set();
let rowsPrimed = false;

watch(() => props.rows.map((row) => row.id).join(','), () => {
    const ids = props.rows.map((row) => row.id);

    if (! rowsPrimed) {
        ids.forEach((id) => seenRowIds.add(id));
        rowsPrimed = true;

        return;
    }

    const fresh = ids.find((id) => ! seenRowIds.has(id));

    ids.forEach((id) => seenRowIds.add(id));

    if (fresh != null) {
        if (! editingIds.value.includes(fresh)) {
            editingIds.value = [...editingIds.value, fresh];
        }
    }

    editingIds.value = editingIds.value.filter((id) => ids.includes(id));
});

const groups = computed(() => {
    let no = 0;
    const numbered = (rows) => rows.map((row) => ({ ...row, no: ++no }));
    const result = [];
    const loose = props.rows.filter((row) => ! row.category_id);

    if (loose.length || props.categories.length === 0) {
        result.push({
            key: 'none',
            id: null,
            name: 'Ангилалгүй',
            showHeader: props.categories.length > 0 && loose.length > 0,
            rows: numbered(loose),
        });
    }

    props.categories.forEach((category) => {
        result.push({
            key: `c-${category.id}`,
            id: category.id,
            name: category.name,
            showHeader: true,
            rows: numbered(props.rows.filter((row) => row.category_id === category.id)),
        });
    });

    return result;
});

const switchUnit = (id) => {
    router.get(route('indicators.index'), { unit: id }, {
        preserveState: false,
        preserveScroll: true,
    });
};

const addRow = (categoryId = null) => {
    router.post(route('indicators.store'), {
        unit: props.unitId,
        ...(categoryId ? { category_id: categoryId } : {}),
    }, { preserveScroll: true });
};

const newCategory = ref('');

const addCategory = () => {
    const name = newCategory.value.trim();

    if (! name) return;

    router.post(route('indicators.categories.store'), {
        unit: props.unitId,
        name,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newCategory.value = '';
        },
    });
};

const removeCategory = (group) => {
    if (! group.id || ! confirm(`«${group.name}» ангиллыг устгах уу? Үзүүлэлтүүд үлдэнэ.`)) return;

    router.delete(route('indicators.categories.destroy', group.id), { preserveScroll: true });
};

const saveCategory = (row) => {
    const raw = drafts[row.id]?.category_id;
    const next = raw === '' || raw == null ? null : Number(raw);

    if (next === (row.category_id ?? null)) return;

    router.patch(route('indicators.update', row.id), { category_id: next }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const removeRow = (row) => {
    if (! confirm('Энэ мөрийг устгах уу?')) return;

    router.delete(route('indicators.destroy', row.id), { preserveScroll: true });
};

const unitOf = (unit) => props.measureUnits.find((item) => item.value === unit) ?? null;

const normalizeMeasure = (raw, unit) => {
    const text = String(raw ?? '').trim();
    const kind = unitOf(unit)?.kind;

    if (text === '' || ! kind) return text;

    const cleaned = text.replace(/[\s\u00a0]/g, '').replace(',', '.').replace(/[^\d.-]/g, '');
    const number = Number(cleaned);

    if (cleaned === '' || Number.isNaN(number)) return '';

    if (kind === 'integer' || kind === 'money') return String(Math.round(number));

    const decimals = kind === 'percent' ? 1 : 2;
    const fixed = number.toFixed(decimals);

    return fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
};

const formatMeasure = (raw, unit) => {
    const text = String(raw ?? '').trim();

    if (text === '') return '';

    const option = unitOf(unit);

    if (! option) return text;

    const number = Number(text);

    if (Number.isNaN(number)) return text;

    if (option.kind === 'percent') return `${text}%`;

    if (option.kind === 'money' || option.kind === 'integer') {
        return new Intl.NumberFormat('mn-MN', { maximumFractionDigits: 0 }).format(number);
    }

    return text;
};

const saveField = (row, field) => {
    const id = Number(row.id);
    let value = drafts[id]?.[field] ?? '';

    if (field === 'target') {
        value = normalizeMeasure(value, drafts[id]?.measure_unit);
        if (drafts[id]) drafts[id].target = value;
    }

    if (field === 'title') {
        value = String(value ?? '').trim();
        if (drafts[id]) drafts[id].title = value;
    }

    const current = String(row[field] ?? '').trim();
    const next = value === '' || value == null ? '' : String(value);

    if (next === current) return;

    // UI-г шууд шинэчилнэ — хариу иртэл хуучин утга харагдахгүй.
    row[field] = next === '' ? null : next;

    router.patch(route('indicators.update', id), { [field]: next === '' ? null : next }, {
        preserveScroll: true,
        preserveState: true,
        only: ['rows'],
        onError: () => {
            row[field] = current === '' ? null : current;
            if (drafts[id]) drafts[id][field] = current;
        },
    });
};

const onTitleInput = (row) => {
    const id = Number(row.id);
    const el = document.querySelector(`[data-cell='title-${id}']`);

    if (el) fitTitle(el);

    if (titleTimers[id]) clearTimeout(titleTimers[id]);

    titleTimers[id] = setTimeout(() => {
        saveField(row, 'title');
        delete titleTimers[id];
    }, 450);
};

const cellKey = (period, slot) => `${period.id}:${slot}`;

const saveValue = (row, period, slot) => {
    const key = cellKey(period, slot);
    const value = normalizeMeasure(drafts[row.id]?.values?.[key] ?? '', drafts[row.id]?.measure_unit);

    if (drafts[row.id]) {
        drafts[row.id].values[key] = value;
    }

    if (value === (row.values?.[key] ?? '')) return;

    router.patch(route('indicators.value', row.id), {
        period_id: period.id,
        slot,
        value,
    }, { preserveScroll: true, preserveState: true });
};

/* ── Нүдний задаргаа ─────────────────────────────────────────────── */

const breakdown = reactive({
    open: false,
    loading: false,
    saving: false,
    error: '',
    indicatorId: null,
    title: '',
    measureUnit: '',
    periodId: null,
    periodLabel: '',
    slot: 'year',
    value: null,
    canEdit: false,
    items: [],
});

const breakdownSum = computed(() => {
    const unit = breakdown.measureUnit;
    let total = 0;
    let has = false;

    breakdown.items.forEach((item) => {
        const normalized = normalizeMeasure(item.amount, unit);

        if (normalized === '') return;

        const number = Number(normalized);

        if (Number.isNaN(number)) return;

        total += number;
        has = true;
    });

    if (! has) return '';

    return normalizeMeasure(String(total), unit);
});

const breakdownHeading = computed(() => {
    const slot = SLOT_LABELS[breakdown.slot] ?? breakdown.slot;
    const period = breakdown.periodLabel || '';

    return [period, slot].filter(Boolean).join(' · ');
});

const openBreakdown = async (row, period, slot) => {
    breakdown.open = true;
    breakdown.loading = true;
    breakdown.saving = false;
    breakdown.error = '';
    breakdown.indicatorId = row.id;
    breakdown.title = row.title || 'Үзүүлэлт';
    breakdown.measureUnit = row.measure_unit || '';
    breakdown.periodId = period.id;
    breakdown.periodLabel = period.label;
    breakdown.slot = slot;
    breakdown.value = row.values?.[cellKey(period, slot)] ?? null;
    breakdown.canEdit = props.canEdit;
    breakdown.items = [];

    try {
        const { data } = await window.axios.get(route('indicators.breakdown', row.id), {
            params: { period_id: period.id, slot },
        });

        breakdown.title = data.title || breakdown.title;
        breakdown.measureUnit = data.measure_unit || breakdown.measureUnit;
        breakdown.value = data.value;
        breakdown.canEdit = Boolean(data.can_edit);
        breakdown.items = (data.items || []).map((item) => ({
            label: item.label ?? '',
            amount: item.amount ?? '',
            note: item.note ?? '',
        }));

        if (breakdown.canEdit && breakdown.items.length === 0) {
            breakdown.items.push({ label: '', amount: '', note: '' });
        }
    } catch (error) {
        breakdown.error = error?.response?.data?.message || 'Задаргаа ачаалж чадсангүй.';
    } finally {
        breakdown.loading = false;
    }
};

const closeBreakdown = () => {
    breakdown.open = false;
};

const addBreakdownItem = () => {
    breakdown.items.push({ label: '', amount: '', note: '' });
};

const removeBreakdownItem = (index) => {
    breakdown.items.splice(index, 1);

    if (breakdown.canEdit && breakdown.items.length === 0) {
        breakdown.items.push({ label: '', amount: '', note: '' });
    }
};

const breakdownErrorMessage = (error) => {
    const data = error?.response?.data;

    if (! data) return 'Хадгалж чадсангүй. Сүлжээг шалгана уу.';

    if (typeof data.message === 'string' && data.message) return data.message;

    if (data.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat().find(Boolean);

        if (first) return String(first);
    }

    return `Хадгалж чадсангүй (${error?.response?.status || '?'}).`;
};

const saveBreakdown = async () => {
    if (! breakdown.canEdit || ! breakdown.indicatorId) return;

    breakdown.saving = true;
    breakdown.error = '';

    const payload = {
        period_id: breakdown.periodId,
        slot: breakdown.slot,
        items: breakdown.items.map((item) => ({
            label: item.label,
            amount: item.amount,
            note: item.note,
        })),
    };

    try {
        // POST — зарим сервер PUT-ыг хаадаг.
        const { data } = await window.axios.post(
            route('indicators.breakdown.sync', breakdown.indicatorId),
            payload,
        );

        breakdown.value = data.value;
        breakdown.items = (data.items || []).map((item) => ({
            label: item.label ?? '',
            amount: item.amount ?? '',
            note: item.note ?? '',
        }));

        if (breakdown.canEdit && breakdown.items.length === 0) {
            breakdown.items.push({ label: '', amount: '', note: '' });
        }

        // Нүдний утгыг шууд шинэчилнэ.
        const key = `${breakdown.periodId}:${breakdown.slot}`;
        const row = props.rows.find((item) => Number(item.id) === Number(breakdown.indicatorId));

        if (row) {
            row.values = { ...(row.values || {}), [key]: data.value ?? '' };
            row.breakdowns = {
                ...(row.breakdowns || {}),
                [key]: Number(data.breakdown_count || 0),
            };
        }

        if (drafts[breakdown.indicatorId]) {
            drafts[breakdown.indicatorId].values[key] = data.value ?? '';
        }

        await router.reload({ only: ['rows'], preserveScroll: true, preserveState: true });
        breakdown.open = false;
    } catch (error) {
        breakdown.error = breakdownErrorMessage(error);
    } finally {
        breakdown.saving = false;
    }
};

const onValueCellClick = (row, period, slot) => {
    if (rowEditing(row)) return;

    openBreakdown(row, period, slot);
};

/* Жилийн баганыг хүснэгтийн толгой дээр шууд засна. */

const periodLabels = reactive({});
const newYear = ref('');

const syncPeriodLabels = () => {
    props.periods.forEach((period) => {
        periodLabels[period.id] = period.label;
    });
};

syncPeriodLabels();
watch(() => props.periods, () => {
    syncPeriodLabels();
    scheduleStickySync();
}, { deep: true });
watch(structureEditable, () => scheduleStickySync());

const addPeriod = () => {
    const label = newYear.value.trim();

    if (! label) return;

    const year = Number.parseInt(label, 10);

    router.post(route('indicators.periods.store'), {
        label,
        year: Number.isNaN(year) ? null : year,
        split: false,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newYear.value = '';
        },
    });
};

const savePeriodLabel = (period) => {
    const label = String(periodLabels[period.id] ?? '').trim();

    if (label === '' || label === period.label) {
        periodLabels[period.id] = period.label;

        return;
    }

    const year = Number.parseInt(label, 10);

    router.patch(route('indicators.periods.update', period.id), {
        label,
        ...(Number.isNaN(year) ? {} : { year }),
    }, { preserveScroll: true, preserveState: true });
};

const toggleSplit = (period) => {
    router.patch(route('indicators.periods.update', period.id), {
        split: ! period.split,
    }, { preserveScroll: true });
};

const removePeriod = (period) => {
    if (! confirm(`«${period.label}» баганыг устгах уу? Тухайн баганад бичсэн бүх утга хамт устана.`)) {
        return;
    }

    router.delete(route('indicators.periods.destroy', period.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Салбарын үзүүлэлт" />

    <AuthenticatedLayout title="Салбарын үзүүлэлт">
        <div class="ui-page ui-page--indicators-table">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <form
                    v-if="canEdit"
                    class="flex min-w-0 flex-1 items-center gap-1.5"
                    @submit.prevent="addCategory"
                >
                    <input
                        v-model="newCategory"
                        class="ui-input !max-w-xs !py-1.5 text-sm"
                        maxlength="120"
                        placeholder="Шинэ ангиллын нэр"
                    />
                    <button type="submit" class="ui-btn-ghost" :disabled="! newCategory.trim()">
                        Ангилал нэмэх
                    </button>
                </form>
                <div class="ml-auto flex items-center gap-1.5">
                    <select
                        v-if="canPickUnit && units.length"
                        class="ui-input !w-auto !py-1.5 text-sm"
                        :value="unitId"
                        @change="switchUnit($event.target.value)"
                    >
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn"
                        :class="structureEditing ? 'bg-brand-navy-600 text-white' : ''"
                        :title="structureEditing ? 'Жилийн баганын засварыг дуусгах' : 'Жилийн багана засах'"
                        :aria-label="structureEditing ? 'Жилийн баганын засварыг дуусгах' : 'Жилийн багана засах'"
                        :aria-pressed="structureEditing"
                        @click="structureEditing = ! structureEditing"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                        </svg>
                    </button>

                    <button
                        v-if="canEdit"
                        type="button"
                        class="ui-icon-btn border-transparent bg-brand-orange-500 text-white hover:bg-brand-orange-600 hover:text-white"
                        title="Үзүүлэлт нэмэх"
                        aria-label="Үзүүлэлт нэмэх"
                        @click="addRow"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="! periods.length && ! structureEditable" class="ui-card-pad text-center">
                <p class="text-sm font-semibold text-slate-700">Жилийн багана алга.</p>
                <p class="mt-1 text-xs text-slate-500">
                    Админ жилийн баганыг тохируулсны дараа мэдээлэл оруулна.
                </p>
            </div>

            <div v-else class="ui-indicators-table-shell">
            <TableScrollViewport fill>
                <div ref="registerEl" class="ui-register">
                <table class="ui-register__table w-full">
                    <colgroup>
                        <col style="width: 4%" />
                        <col :style="{ width: columnWidths.title }" />
                        <col style="width: 12%" />
                        <col style="width: 9%" />
                        <col
                            v-for="index in valueColumnCount"
                            :key="'col-' + index"
                            :style="{ width: columnWidths.value }"
                        />
                        <col style="width: 8%" />
                        <col v-if="structureEditable" style="width: 8%" />
                        <col v-if="canEdit" style="width: 7%" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2" class="text-center">№</th>
                            <th rowspan="2" class="whitespace-normal text-left">
                                Үзүүлэлтийн дэлгэрэнгүй мэдээлэл
                            </th>
                            <th rowspan="2" class="whitespace-normal text-center">Хэмжих нэгж</th>
                            <th rowspan="2" class="whitespace-normal text-center">Хүрэх түвшин</th>
                            <th
                                v-for="period in periods"
                                :key="'p-' + period.id"
                                :colspan="period.slots.length"
                                class="text-center align-bottom"
                            >
                                <input
                                    v-if="structureEditable"
                                    v-model="periodLabels[period.id]"
                                    class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm font-semibold focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @blur="savePeriodLabel(period)"
                                    @keyup.enter="$event.target.blur()"
                                />
                                <span v-else>{{ period.label }}</span>
                                <div v-if="structureEditable" class="mt-0.5 flex items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                        :class="period.split
                                            ? 'bg-brand-navy-600 text-white'
                                            : 'text-slate-500 hover:bg-white'"
                                        :title="period.split ? 'Нэг нүд болгох' : 'Хагас жил, жилийн эцэс гэж хоёр нүд болгох'"
                                        @click="toggleSplit(period)"
                                    >
                                        Хагас
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded px-1 py-0.5 text-[10px] font-semibold text-red-600 hover:bg-red-50"
                                        title="Баганыг устгах"
                                        @click="removePeriod(period)"
                                    >
                                        Устгах
                                    </button>
                                </div>
                            </th>
                            <th
                                rowspan="2"
                                class="whitespace-normal text-center font-semibold text-brand-navy-800"
                            >
                                Нийт
                            </th>
                            <th v-if="structureEditable" rowspan="2" class="w-28 align-bottom">
                                <input
                                    v-model="newYear"
                                    class="w-full rounded border border-dashed border-slate-300 bg-white px-1 py-0.5 text-center text-sm focus:border-brand-navy-400 focus:ring-1 focus:ring-brand-navy-400"
                                    placeholder="2026"
                                    title="Шинэ жилийн багана"
                                    @keyup.enter="addPeriod"
                                />
                                <button
                                    type="button"
                                    class="mt-1 w-full text-[10px] font-semibold text-brand-navy-700 hover:underline"
                                    @click="addPeriod"
                                >
                                    Жил нэмэх
                                </button>
                            </th>
                            <th v-if="canEdit" rowspan="2" />
                        </tr>
                        <tr>
                            <template v-for="period in periods" :key="'s-' + period.id">
                                <th
                                    v-for="slot in period.slots"
                                    :key="period.id + '-' + slot"
                                    class="whitespace-normal text-center !text-[10px] !font-semibold !normal-case !leading-tight"
                                >
                                    {{ period.split ? SLOT_LABELS[slot] : '&nbsp;' }}
                                </th>
                            </template>
                        </tr>
                    </thead>

                    <tbody>
                        <template v-for="group in groups" :key="group.key">
                        <tr v-if="group.showHeader">
                            <td :colspan="totalColumns" class="bg-brand-navy-50 !py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-brand-navy-900">{{ group.name }}</span>
                                    <span v-if="canEdit && group.id" class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="ui-icon-btn"
                                            title="Энэ ангилалд үзүүлэлт нэмэх"
                                            aria-label="Энэ ангилалд үзүүлэлт нэмэх"
                                            @click="addRow(group.id)"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="ui-icon-btn ui-icon-btn--danger"
                                            title="Ангиллыг устгах"
                                            aria-label="Ангиллыг устгах"
                                            @click="removeCategory(group)"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" />
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-for="row in group.rows" :key="row.id" :class="rowEditing(row) ? 'bg-sky-50/80' : ''">
                            <td class="ui-register__cell--no text-center">{{ row.no }}</td>

                            <td
                                class="relative align-top whitespace-normal text-left"
                                :class="rowEditing(row) ? 'z-20' : ''"
                            >
                                <textarea
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    :key="'title-edit-' + row.id"
                                    v-model="drafts[row.id].title"
                                    :data-cell="`title-${row.id}`"
                                    rows="2"
                                    class="relative z-20 block w-full min-h-[2.75rem] resize-y rounded-lg border-2 border-brand-navy-400 bg-white px-2 py-1.5 text-left text-sm leading-snug text-slate-800 shadow-sm outline-none focus:border-brand-navy-600 focus:ring-2 focus:ring-brand-navy-600/20"
                                    placeholder="Үзүүлэлтийн нэр…"
                                    autocomplete="off"
                                    @focus="fitTitle($event)"
                                    @input="onTitleInput(row)"
                                    @blur="saveField(row, 'title')"
                                    @mousedown.stop
                                    @click.stop
                                    @keydown.stop
                                />
                                <select
                                    v-if="rowEditing(row) && drafts[row.id] && categories.length"
                                    v-model="drafts[row.id].category_id"
                                    class="ui-input relative z-20 mt-1 w-full !py-1 text-xs text-slate-600"
                                    @change="saveCategory(row)"
                                    @mousedown.stop
                                    @click.stop
                                >
                                    <option value="">Ангилалгүй</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">
                                        {{ category.name }}
                                    </option>
                                </select>
                                <span
                                    v-if="! rowEditing(row) || ! drafts[row.id]"
                                    class="block whitespace-pre-wrap break-words text-left text-sm leading-snug"
                                >{{ row.title || '—' }}</span>
                            </td>

                            <td class="text-center">
                                <select
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    v-model="drafts[row.id].measure_unit"
                                    :data-cell="`unit-${row.id}`"
                                    class="w-full rounded border-0 bg-transparent px-0.5 py-0.5 text-center text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @change="saveField(row, 'measure_unit')"
                                    @click.stop
                                >
                                    <option value="">Сонгох</option>
                                    <option v-for="unit in measureUnits" :key="unit.value" :value="unit.value">
                                        {{ unit.value }}
                                    </option>
                                </select>
                                <span v-else class="text-sm">{{ row.measure_unit || '—' }}</span>
                            </td>

                            <td class="text-center">
                                <span
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    class="inline-flex w-full items-center justify-center gap-0.5"
                                    @click.stop
                                >
                                    <input
                                        v-model="drafts[row.id].target"
                                        :data-cell="`target-${row.id}`"
                                        :disabled="! drafts[row.id].measure_unit"
                                        :inputmode="unitOf(drafts[row.id].measure_unit)?.kind === 'integer' ? 'numeric' : 'decimal'"
                                        class="w-full min-w-0 rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm font-semibold tabular-nums focus:bg-white focus:ring-1 focus:ring-brand-navy-400 disabled:cursor-not-allowed disabled:text-slate-300"
                                        :placeholder="drafts[row.id].measure_unit ? unitOf(drafts[row.id].measure_unit)?.suffix : 'нэгж'"
                                        :title="drafts[row.id].measure_unit ? 'Хүрэх түвшин' : 'Эхлээд хэмжих нэгж сонгоно'"
                                        @blur="saveField(row, 'target')"
                                    />
                                    <span
                                        v-if="unitOf(drafts[row.id].measure_unit)?.suffix"
                                        class="shrink-0 text-[11px] text-slate-400"
                                    >{{ unitOf(drafts[row.id].measure_unit).suffix }}</span>
                                </span>
                                <span v-else class="text-sm font-semibold tabular-nums">{{ formatMeasure(row.target, row.measure_unit) || '—' }}</span>
                            </td>

                            <template v-for="period in periods" :key="'v-' + period.id">
                                <td
                                    v-for="slot in period.slots"
                                    :key="row.id + '-' + period.id + '-' + slot"
                                    class="text-center"
                                    :class="! rowEditing(row) ? 'cursor-pointer hover:bg-sky-50/60' : ''"
                                    :title="rowEditing(row) ? '' : 'Задаргаа харах'"
                                    @click="onValueCellClick(row, period, slot)"
                                >
                                    <span
                                        v-if="rowEditing(row) && drafts[row.id]"
                                        class="inline-flex w-full items-center justify-center gap-0.5"
                                        @click.stop
                                    >
                                        <input
                                            v-model="drafts[row.id].values[cellKey(period, slot)]"
                                            :data-cell="`val-${row.id}-${period.id}-${slot}`"
                                            :disabled="! drafts[row.id].measure_unit"
                                            :inputmode="unitOf(drafts[row.id].measure_unit)?.kind === 'integer' ? 'numeric' : 'decimal'"
                                            class="w-full min-w-0 rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm tabular-nums focus:bg-white focus:ring-1 focus:ring-brand-navy-400 disabled:cursor-not-allowed disabled:text-slate-300"
                                            :title="drafts[row.id].measure_unit ? '' : 'Эхлээд хэмжих нэгж сонгоно'"
                                            @blur="saveValue(row, period, slot)"
                                        />
                                        <span
                                            v-if="drafts[row.id].values[cellKey(period, slot)] && unitOf(drafts[row.id].measure_unit)?.suffix"
                                            class="shrink-0 text-[11px] text-slate-400"
                                        >{{ unitOf(drafts[row.id].measure_unit).suffix }}</span>
                                        <button
                                            type="button"
                                            class="ui-icon-btn !h-6 !w-6 shrink-0"
                                            title="Задаргаа"
                                            aria-label="Задаргаа"
                                            @click="openBreakdown(row, period, slot)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h7" />
                                            </svg>
                                        </button>
                                    </span>
                                    <span
                                        v-else
                                        class="text-sm tabular-nums"
                                        :class="(row.values[cellKey(period, slot)] || row.breakdowns?.[cellKey(period, slot)])
                                            ? 'font-medium text-brand-navy-700 underline decoration-dotted underline-offset-2'
                                            : 'text-slate-400'"
                                    >
                                        {{ formatMeasure(row.values[cellKey(period, slot)], row.measure_unit) || '—' }}
                                    </span>
                                </td>
                            </template>

                            <td class="bg-brand-navy-50/40 text-center">
                                <span
                                    class="text-sm font-semibold tabular-nums text-brand-navy-900"
                                    :class="rowTotal(row) == null ? 'text-slate-400' : ''"
                                    :title="rowTotal(row) == null ? 'Тоон утга алга' : 'Жил бүрийн эцсийн нийлбэр'"
                                >
                                    {{ formatMeasure(rowTotal(row), row.measure_unit || drafts[row.id]?.measure_unit) || '—' }}
                                </span>
                            </td>

                            <td v-if="structureEditable" />

                            <td v-if="canEdit" class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        class="ui-icon-btn"
                                        :class="rowEditing(row) ? 'bg-brand-navy-600 text-white' : ''"
                                        :title="rowEditing(row) ? 'Засварыг дуусгах' : 'Мөрийг засах'"
                                        :aria-label="rowEditing(row) ? 'Засварыг дуусгах' : 'Мөрийг засах'"
                                        :aria-pressed="rowEditing(row)"
                                        @click="toggleRow(row)"
                                    >
                                        <svg v-if="rowEditing(row)" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10a2.1 2.1 0 1 0-3-3L5 17v3z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="rowEditing(row)"
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        title="Мөрийг устгах"
                                        aria-label="Устгах"
                                        @click="removeRow(row)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </template>

                        <tr v-if="! rows.length && ! categories.length">
                            <td :colspan="totalColumns" class="ui-register__empty !py-12 text-center text-slate-400">
                                Одоогоор үзүүлэлт бүртгэгдээгүй.
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>
            </div>
        </div>

        <Modal :show="breakdown.open" max-width="4xl" @close="closeBreakdown">
            <div class="ui-card-pad space-y-4">
                <div class="space-y-1">
                    <h2 class="ui-title text-base">Задаргаа</h2>
                    <p class="ui-subtitle !mt-0 text-sm">
                        <span class="font-medium text-slate-700">{{ breakdownHeading }}</span>
                        <span v-if="breakdown.title" class="text-slate-500"> — {{ breakdown.title }}</span>
                    </p>
                </div>

                <div v-if="breakdown.loading" class="py-10 text-center text-sm text-slate-400">
                    Ачаалж байна…
                </div>

                <template v-else>
                    <p v-if="breakdown.error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        {{ breakdown.error }}
                    </p>

                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">№</th>
                                    <th class="w-44">Нэр</th>
                                    <th>Тайлбар</th>
                                    <th class="w-28 text-right">Тоо</th>
                                    <th v-if="breakdown.canEdit" class="w-12" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in breakdown.items" :key="index">
                                    <td class="align-top text-center text-slate-400">{{ index + 1 }}</td>
                                    <td class="align-top">
                                        <input
                                            v-if="breakdown.canEdit"
                                            v-model="item.label"
                                            class="ui-input !py-1.5 text-sm"
                                            maxlength="500"
                                            placeholder="Жишээ: Сайншанд"
                                        />
                                        <span v-else class="text-sm">{{ item.label || '—' }}</span>
                                    </td>
                                    <td class="align-top">
                                        <textarea
                                            v-if="breakdown.canEdit"
                                            v-model="item.note"
                                            rows="2"
                                            class="ui-input !min-h-[2.5rem] w-full resize-y !py-1.5 text-sm leading-snug"
                                            maxlength="2000"
                                            placeholder="Тайлбар бичих…"
                                        />
                                        <span v-else class="block whitespace-pre-wrap text-sm text-slate-600">{{ item.note || '—' }}</span>
                                    </td>
                                    <td class="align-top">
                                        <input
                                            v-if="breakdown.canEdit"
                                            v-model="item.amount"
                                            class="ui-input !py-1.5 text-right text-sm tabular-nums"
                                            :inputmode="unitOf(breakdown.measureUnit)?.kind === 'integer' ? 'numeric' : 'decimal'"
                                            placeholder="0"
                                        />
                                        <span v-else class="block text-right text-sm tabular-nums">
                                            {{ formatMeasure(item.amount, breakdown.measureUnit) || '—' }}
                                        </span>
                                    </td>
                                    <td v-if="breakdown.canEdit" class="align-top text-center">
                                        <button
                                            type="button"
                                            class="ui-icon-btn ui-icon-btn--danger !h-7 !w-7"
                                            title="Мөр хасах"
                                            aria-label="Мөр хасах"
                                            @click="removeBreakdownItem(index)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="! breakdown.items.length">
                                    <td :colspan="breakdown.canEdit ? 5 : 4" class="py-8 text-center text-sm text-slate-400">
                                        Задаргаа байхгүй.
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t border-slate-200 bg-slate-50/80">
                                    <td />
                                    <td colspan="2" class="text-sm font-medium text-slate-600">Нийлбэр / нүдний утга</td>
                                    <td class="text-right text-sm font-semibold tabular-nums text-brand-navy-800">
                                        {{ formatMeasure(breakdownSum || breakdown.value, breakdown.measureUnit) || '—' }}
                                    </td>
                                    <td v-if="breakdown.canEdit" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button
                            v-if="breakdown.canEdit"
                            type="button"
                            class="ui-btn-ghost"
                            @click="addBreakdownItem"
                        >
                            Мөр нэмэх
                        </button>
                        <div class="ml-auto flex items-center gap-2">
                            <button type="button" class="ui-btn-ghost" @click="closeBreakdown">
                                Хаах
                            </button>
                            <button
                                v-if="breakdown.canEdit"
                                type="button"
                                class="ui-btn-primary"
                                :disabled="breakdown.saving"
                                @click="saveBreakdown"
                            >
                                {{ breakdown.saving ? 'Хадгалж байна…' : 'Хадгалах' }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
