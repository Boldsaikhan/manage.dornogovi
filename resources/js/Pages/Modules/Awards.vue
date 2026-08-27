<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    tab: { type: String, default: 'state_high' },
    tabs: { type: Array, default: () => [] },
    subtype: { type: String, default: '' },
    subtypes: { type: Array, default: () => [] },
    year: { type: Number, default: null },
    years: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    categories: { type: Object, default: () => ({}) },
    allSubtypes: { type: Object, default: () => ({}) },
    categorySubtypes: { type: Object, default: () => ({}) },
});

const drafts = reactive({});

const numberFields = new Set([
    'age', 'years_in_country', 'years_in_sector', 'total_years', 'position_years',
]);

const draftFields = computed(() => {
    const fields = props.columns
        .filter((col) => ! col.readonly && col.field)
        .map((col) => col.field);

    return [...new Set(fields)];
});

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    props.rows.forEach((row) => {
        drafts[row.id] = Object.fromEntries(
            draftFields.value.map((field) => [field, row[field] ?? '']),
        );
    });
};

watch(() => [props.rows, props.columns], syncDrafts, { immediate: true, deep: true });

const queryParams = (overrides = {}) => {
    const params = {
        tab: props.tab,
        ...(props.subtype ? { subtype: props.subtype } : {}),
        ...(props.year ? { year: props.year } : {}),
        ...overrides,
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key];
        }
    });

    return params;
};

const switchTab = (value) => {
    router.get(route('awards.index'), { tab: value }, { preserveState: false, preserveScroll: true });
};

const switchSubtype = (value) => {
    router.get(route('awards.index'), queryParams({ subtype: value || undefined }), {
        preserveState: false,
        preserveScroll: true,
    });
};

const switchYear = (value) => {
    const year = value === '' || value === 'all' ? undefined : Number(value);
    router.get(route('awards.index'), queryParams({ year }), {
        preserveState: false,
        preserveScroll: true,
    });
};

const exportUrl = computed(() => route('awards.export', queryParams()));

const defaultSubtype = () => {
    if (props.subtype) {
        return props.subtype;
    }

    const list = props.categorySubtypes[props.tab] || [];

    return list[0] || '';
};

const addRow = () => {
    const payload = {
        category: props.tab,
        year: props.year || new Date().getFullYear(),
    };

    const subtype = defaultSubtype();
    if (subtype) {
        payload.subtype = subtype;
    }

    useForm(payload).post(route('awards.store'), { preserveScroll: true });
};

const saveField = (id, field, value) => {
    let next = value;

    if (numberFields.has(field)) {
        if (next === '' || next === null || next === undefined) {
            next = null;
        } else {
            const n = Number.parseInt(String(next), 10);
            next = Number.isNaN(n) ? null : n;
        }
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next.trim();
    }

    if (drafts[id] && Object.prototype.hasOwnProperty.call(drafts[id], field)) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('awards.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const destroyRow = (id) => {
    if (! confirm('Устгах уу?')) {
        return;
    }

    router.delete(route('awards.destroy', id), { preserveScroll: true });
};

const cellValue = (row, key) => {
    const value = row[key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
};

const fieldFor = (col) => col.field || col.key;

const isEditable = (col) => props.canManage && ! col.readonly && col.field;

const pageTitle = computed(() => props.categories[props.tab] || 'Шагнал');

const activeSubtypeLabel = computed(() => {
    if (! props.subtype) {
        return 'Бүгд';
    }

    return props.subtypes.find((item) => item.value === props.subtype)?.label || props.subtype;
});

const fitsViewport = computed(() => (
    props.tab === 'governor_honor'
    || props.tab === 'governor_leading'
    || props.tab === 'other'
));

const fitColPercents = computed(() => {
    if (props.tab === 'other') {
        return ['3%', '9%', '7%', '7%', '8%', '9%', '14%', '5%', '6%', '9%', '11%', '8%'];
    }

    return ['3%', '8%', '8%', '9%', '11%', '18%', '6%', '8%', '10%', '13%'];
});

const colAlign = (col) => {
    if (col.key === 'no' || col.input === 'number' || col.input === 'gender' || col.input === 'date') {
        return 'center';
    }

    return 'left';
};
</script>

<template>
    <AuthenticatedLayout title="Шагнал">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Шагнал</h2>
                    <p class="ui-subtitle">
                        Төрийн дээд, аймгийн Засаг даргын болон бусад шагналын бүртгэл.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="exportUrl" class="ui-btn-ghost">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Excel татах
                    </a>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="addRow"
                    >
                        Шинэ нэмэх
                    </button>
                </div>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <div v-if="subtypes.length || years.length" class="flex flex-wrap items-center gap-2">
                <template v-if="subtypes.length">
                    <button
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="!subtype
                            ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                        @click="switchSubtype('')"
                    >
                        Бүгд
                    </button>
                    <button
                        v-for="item in subtypes"
                        :key="item.value"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="subtype === item.value
                            ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                        @click="switchSubtype(item.value)"
                    >
                        {{ item.label }}
                    </button>
                </template>

                <label v-if="years.length" class="ml-auto flex items-center gap-2 text-sm text-slate-600">
                    <span>Он</span>
                    <select
                        class="ui-input w-28 py-1.5"
                        :value="year ?? 'all'"
                        @change="switchYear($event.target.value)"
                    >
                        <option value="all">Бүгд</option>
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        <option v-if="year && !years.includes(year)" :value="year">{{ year }}</option>
                    </select>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <span>
                    {{ pageTitle }}
                    <template v-if="subtypes.length"> · {{ activeSubtypeLabel }}</template>
                    <template v-if="year"> · {{ year }} он</template>
                </span>
                <span>{{ rows.length }} мөр</span>
            </div>

            <div class="ui-card max-h-[min(70vh,calc(100dvh-12rem))] overflow-auto overscroll-contain">
                <table
                    class="ui-table table-fixed"
                    :class="fitsViewport ? 'w-full' : 'min-w-[1500px]'"
                >
                    <colgroup>
                        <col
                            v-for="(col, index) in columns"
                            :key="`col-${col.key}`"
                            :style="fitsViewport
                                ? { width: fitColPercents[index] }
                                : (col.width ? { width: col.width } : undefined)"
                        />
                        <col v-if="canManage" style="width: 48px" />
                    </colgroup>

                    <thead>
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="sticky top-0 z-20 bg-brand-navy-50"
                                :class="colAlign(col) === 'center' ? 'text-center' : ''"
                            >
                                <template v-if="col.lines?.length">
                                    <span
                                        v-for="(line, idx) in col.lines"
                                        :key="idx"
                                        class="block leading-tight"
                                    >{{ line }}</span>
                                </template>
                                <template v-else>{{ col.label }}</template>
                            </th>
                            <th v-if="canManage" class="sticky top-0 z-20 bg-brand-navy-50 text-center" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="columns.length + (canManage ? 1 : 0)"
                                class="!py-14 text-center text-slate-400"
                            >
                                <template v-if="canManage">
                                    Одоогоор мөр алга. «Шинэ нэмэх» дарж эхлүүлнэ үү.
                                </template>
                                <template v-else>
                                    {{ pageTitle }} бүртгэл одоогоор хоосон байна.
                                </template>
                            </td>
                        </tr>

                        <tr v-for="row in rows" :key="row.id">
                            <td
                                v-for="col in columns"
                                :key="`${row.id}-${col.key}`"
                                :class="[
                                    isEditable(col) && col.input !== 'gender' ? 'ui-sheet-td' : '',
                                    colAlign(col) === 'center' ? 'text-center' : '',
                                    col.key === 'no' ? 'font-semibold text-slate-500' : '',
                                ]"
                            >
                                <template v-if="col.readonly || col.key === 'no'">
                                    {{ row.no }}
                                </template>

                                <select
                                    v-else-if="isEditable(col) && drafts[row.id] && col.input === 'gender'"
                                    v-model="drafts[row.id][fieldFor(col)]"
                                    class="ui-table-input text-center"
                                    @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                >
                                    <option value="" />
                                    <option value="эр">эр</option>
                                    <option value="эм">эм</option>
                                </select>

                                <SheetCell
                                    v-else-if="isEditable(col) && drafts[row.id]"
                                    v-model="drafts[row.id][fieldFor(col)]"
                                    :multiline="!!col.multiline"
                                    :type="col.input === 'number' || col.input === 'date' ? col.input : 'text'"
                                    :align="colAlign(col)"
                                    :editable="true"
                                    @commit="(v) => saveField(row.id, fieldFor(col), v)"
                                />

                                <span
                                    v-else
                                    class="ui-clamp-2"
                                    :title="String(cellValue(row, col.key))"
                                >{{ cellValue(row, col.key) }}</span>
                            </td>

                            <td v-if="canManage" class="text-center align-middle">
                                <button
                                    type="button"
                                    class="ui-icon-btn"
                                    title="Устгах"
                                    aria-label="Устгах"
                                    @click="destroyRow(row.id)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
