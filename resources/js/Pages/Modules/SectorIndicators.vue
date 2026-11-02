<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    unitId: { type: Number, default: null },
    periods: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    measureUnits: { type: Array, default: () => [] },
});

const SLOT_LABELS = { half: 'хагас жил', year: 'жилийн эцэс' };

/** Нүднүүдийн түр хуулбар — засаж байхад шууд харагдана. */
const drafts = reactive({});

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    props.rows.forEach((row) => {
        drafts[row.id] = {
            title: row.title ?? '',
            measure_unit: row.measure_unit ?? '',
            target: row.target ?? '',
            note: row.note ?? '',
            values: { ...row.values },
        };
    });
};

syncDrafts();
watch(() => props.rows, syncDrafts, { deep: true });

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
    node.style.height = `${node.scrollHeight}px`;
}

/** Нэг мөрийг тусад нь засна. */
const editingId = ref(null);
const structureEditing = ref(false);
const rowEditing = (row) => props.canEdit && editingId.value === row.id;
const structureEditable = computed(() => structureEditing.value && props.canManage);

const totalColumns = computed(() => (
    5
    + valueColumnCount.value
    + (structureEditable.value ? 1 : 0)
    + (props.canEdit ? 1 : 0)
));

const toggleRow = (row) => {
    editingId.value = editingId.value === row.id ? null : row.id;
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
        editingId.value = fresh;
    } else if (editingId.value != null && ! ids.includes(editingId.value)) {
        editingId.value = null;
    }
});

const switchUnit = (id) => {
    router.get(route('indicators.index'), { unit: id }, {
        preserveState: false,
        preserveScroll: true,
    });
};

const addRow = () => {
    router.post(route('indicators.store'), { unit: props.unitId }, { preserveScroll: true });
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
    let value = drafts[row.id]?.[field] ?? '';

    if (field === 'target') {
        value = normalizeMeasure(value, drafts[row.id]?.measure_unit);
        drafts[row.id].target = value;
    }

    if (value === (row[field] ?? '')) return;

    router.patch(route('indicators.update', row.id), { [field]: value }, {
        preserveScroll: true,
        preserveState: true,
    });
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

/* Жилийн баганыг хүснэгтийн толгой дээр шууд засна. */

const periodLabels = reactive({});
const newYear = ref('');

const syncPeriodLabels = () => {
    props.periods.forEach((period) => {
        periodLabels[period.id] = period.label;
    });
};

syncPeriodLabels();
watch(() => props.periods, syncPeriodLabels, { deep: true });

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
        <div class="ui-page ui-page--tight">
            <!-- Табууд — эрх зүйн актын хуудастай нэг эгнээнд -->
            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <span class="pl-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    Хууль тогтоомж
                </span>
                <Link
                    :href="route('legal-acts.index')"
                    class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                >
                    Эрх зүйн акт, тогтоол
                </Link>

                <span class="mx-1 h-5 w-px bg-slate-200" />

                <span class="shrink-0 rounded-xl bg-brand-navy-600 px-3.5 py-2 text-sm font-semibold text-white shadow-md shadow-brand-navy-600/20">
                    Салбарын үзүүлэлт
                    <span class="ml-1 text-xs opacity-70">{{ rows.length }}</span>
                </span>

                <div class="ml-auto flex items-center gap-1.5">
                    <select
                        v-if="canManage && units.length"
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

            <TableScrollViewport v-else>
                <table class="ui-table ui-register__table !w-max min-w-full !table-auto">
                    <thead>
                        <tr>
                            <th rowspan="2" class="w-12 text-center">№</th>
                            <th rowspan="2" class="w-[28rem] min-w-[28rem] whitespace-normal text-left">
                                Үзүүлэлтийн дэлгэрэнгүй мэдээлэл
                            </th>
                            <th rowspan="2" class="w-32 text-center">Хэмжих нэгж</th>
                            <th rowspan="2" class="w-28 text-center">Хүрэх түвшин</th>
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
                            <th rowspan="2" class="min-w-[12rem] text-left">Тэмдэглэл</th>
                            <th v-if="canEdit" rowspan="2" class="w-20" />
                        </tr>
                        <tr>
                            <template v-for="period in periods" :key="'s-' + period.id">
                                <th
                                    v-for="slot in period.slots"
                                    :key="period.id + '-' + slot"
                                    class="w-24 text-center !text-[10px] !font-semibold !normal-case"
                                >
                                    {{ period.split ? SLOT_LABELS[slot] : '&nbsp;' }}
                                </th>
                            </template>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="row in rows" :key="row.id" :class="rowEditing(row) ? 'bg-sky-50/80' : ''">
                            <td class="ui-register__cell--no text-center">{{ row.no }}</td>

                            <td class="align-top whitespace-normal text-left">
                                <textarea
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    :ref="fitTitle"
                                    v-model="drafts[row.id].title"
                                    rows="1"
                                    class="block w-[28rem] max-w-[28rem] resize-none overflow-hidden whitespace-pre-wrap break-words rounded border-0 bg-transparent px-1 py-0.5 text-left text-sm leading-snug focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    placeholder="Үзүүлэлтийн нэр…"
                                    @input="fitTitle"
                                    @blur="saveField(row, 'title')"
                                />
                                <span v-else class="block w-[28rem] max-w-[28rem] whitespace-pre-wrap break-words text-left text-sm leading-snug">{{ row.title }}</span>
                            </td>

                            <td class="text-center">
                                <select
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    v-model="drafts[row.id].measure_unit"
                                    class="w-full rounded border-0 bg-transparent px-0.5 py-0.5 text-center text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @change="saveField(row, 'measure_unit')"
                                >
                                    <option value="">Сонгох</option>
                                    <option v-for="unit in measureUnits" :key="unit.value" :value="unit.value">
                                        {{ unit.value }}
                                    </option>
                                </select>
                                <span v-else class="text-sm">{{ row.measure_unit }}</span>
                            </td>

                            <td class="text-center">
                                <span
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    class="inline-flex w-full items-center justify-center gap-0.5"
                                >
                                    <input
                                        v-model="drafts[row.id].target"
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
                                <span v-else class="text-sm font-semibold tabular-nums">{{ formatMeasure(row.target, row.measure_unit) }}</span>
                            </td>

                            <template v-for="period in periods" :key="'v-' + period.id">
                                <td
                                    v-for="slot in period.slots"
                                    :key="row.id + '-' + period.id + '-' + slot"
                                    class="text-center"
                                >
                                    <span
                                        v-if="rowEditing(row) && drafts[row.id]"
                                        class="inline-flex w-full items-center justify-center gap-0.5"
                                    >
                                        <input
                                            v-model="drafts[row.id].values[cellKey(period, slot)]"
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
                                    </span>
                                    <span v-else class="text-sm tabular-nums">
                                        {{ formatMeasure(row.values[cellKey(period, slot)], row.measure_unit) }}
                                    </span>
                                </td>
                            </template>

                            <td v-if="structureEditable" />

                            <td>
                                <input
                                    v-if="rowEditing(row) && drafts[row.id]"
                                    v-model="drafts[row.id].note"
                                    class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @blur="saveField(row, 'note')"
                                />
                                <span v-else class="text-sm">{{ row.note }}</span>
                            </td>

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

                        <tr v-if="! rows.length">
                            <td :colspan="totalColumns" class="ui-register__empty !py-12 text-center text-slate-400">
                                Одоогоор үзүүлэлт бүртгэгдээгүй.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableScrollViewport>
        </div>
    </AuthenticatedLayout>
</template>
