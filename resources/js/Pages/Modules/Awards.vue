<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

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

/** АЗД, бусад — багана багтаах (төрөл табаар шүүгдэнэ) */
const fitsViewport = computed(() => (
    props.tab === 'governor_honor'
    || props.tab === 'governor_leading'
    || props.tab === 'other'
));

/** table-layout: fixed-д баганын хувь */
const fitColPercents = computed(() => {
    if (props.tab === 'other') {
        return ['3%', '9%', '7%', '7%', '8%', '9%', '14%', '5%', '6%', '9%', '11%', '8%'];
    }

    // governor_honor, governor_leading — 10 багана
    return ['3%', '8%', '8%', '9%', '11%', '18%', '6%', '8%', '10%', '13%'];
});
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
                    <a
                        :href="exportUrl"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-soft hover:border-brand-navy-300"
                    >
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
                    class="rounded-xl px-3.5 py-2.5 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                <div v-if="subtypes.length" class="flex flex-wrap gap-2">
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
                </div>

                <label class="ml-auto flex items-center gap-2 text-sm text-slate-600">
                    <span>Он:</span>
                    <select
                        class="ui-input w-28 py-1.5"
                        :value="year ?? 'all'"
                        @change="switchYear($event.target.value)"
                    >
                        <option value="all">Бүгд</option>
                        <option
                            v-for="y in years"
                            :key="y"
                            :value="y"
                        >
                            {{ y }}
                        </option>
                        <option
                            v-if="year && !years.includes(year)"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </label>
            </div>

            <div
                class="award-sheet rounded-xl border border-slate-300 bg-white shadow-soft"
                :class="fitsViewport ? '' : 'overflow-x-auto'"
            >
                <table
                    class="w-full border-collapse text-[11px] leading-snug text-slate-900"
                    :class="fitsViewport ? 'award-sheet-fit table-fixed' : 'min-w-[1500px]'"
                >
                    <colgroup>
                        <col
                            v-for="(col, index) in columns"
                            :key="`col-${col.key}`"
                            :style="fitsViewport
                                ? { width: fitColPercents[index] }
                                : (col.width ? { width: col.width } : undefined)"
                        />
                        <col v-if="canManage" :style="{ width: fitsViewport ? '2.5rem' : '3rem' }" />
                    </colgroup>
                    <thead>
                        <tr class="bg-brand-navy-50 text-brand-navy-800">
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="border border-slate-300 px-1.5 py-2 text-center align-bottom font-semibold normal-case tracking-normal"
                            >
                                <template v-if="col.lines?.length">
                                    <span
                                        v-for="(line, idx) in col.lines"
                                        :key="idx"
                                        class="block leading-tight"
                                    >{{ line }}</span>
                                </template>
                                <span v-else class="block leading-tight">{{ col.label }}</span>
                            </th>
                            <th
                                v-if="canManage"
                                class="border border-slate-300 px-1 py-2 text-center font-semibold normal-case"
                            />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="columns.length + (canManage ? 1 : 0)"
                                class="border border-slate-300 px-3 py-8 text-center text-sm text-slate-500"
                            >
                                <template v-if="canManage">
                                    Бүртгэл алга — «Шинэ нэмэх» дарж мөр нэмнэ.
                                </template>
                                <template v-else>
                                    {{ pageTitle }} бүртгэл алга.
                                </template>
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50 focus-within:bg-sky-50"
                        >
                            <td
                                v-for="col in columns"
                                :key="`${row.id}-${col.key}`"
                                class="border border-slate-300 p-0 align-top"
                            >
                                <div
                                    v-if="col.readonly || col.key === 'no'"
                                    class="px-1.5 py-1.5 text-center font-medium"
                                >
                                    {{ row.no }}
                                </div>

                                <template v-else-if="isEditable(col) && drafts[row.id]">
                                    <select
                                        v-else-if="col.input === 'gender'"
                                        v-model="drafts[row.id][fieldFor(col)]"
                                        class="ui-table-input text-center"
                                        @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                    >
                                        <option value="" />
                                        <option value="эр">эр</option>
                                        <option value="эм">эм</option>
                                    </select>

                                    <input
                                        v-else-if="col.input === 'date'"
                                        v-model="drafts[row.id][fieldFor(col)]"
                                        type="date"
                                        class="ui-table-input"
                                        @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                    />

                                    <input
                                        v-else-if="col.input === 'number'"
                                        v-model="drafts[row.id][fieldFor(col)]"
                                        type="number"
                                        min="0"
                                        class="ui-table-input text-center"
                                        @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                    />

                                    <textarea
                                        v-else-if="col.multiline"
                                        v-model="drafts[row.id][fieldFor(col)]"
                                        rows="2"
                                        class="ui-table-input-2"
                                        @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                    />

                                    <input
                                        v-else
                                        v-model="drafts[row.id][fieldFor(col)]"
                                        type="text"
                                        class="ui-table-input"
                                        @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                    />
                                </template>

                                <div
                                    v-else
                                    class="px-1.5 py-1.5"
                                    :class="col.key === 'no' ? 'text-center' : ''"
                                >
                                    <span
                                        class="ui-clamp-2"
                                        :title="String(cellValue(row, col.key))"
                                    >{{ cellValue(row, col.key) }}</span>
                                </div>
                            </td>

                            <td v-if="canManage" class="border border-slate-300 px-1 py-1 text-center align-top">
                                <button
                                    type="button"
                                    class="rounded px-1.5 py-0.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                                    title="Устгах"
                                    @click="destroyRow(row.id)"
                                >
                                    ×
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.award-sheet th {
    min-height: 3.5rem;
}

.award-sheet td {
    min-height: 2.35rem;
}

.award-sheet-fit th,
.award-sheet-fit td {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.award-sheet-fit th {
    min-height: 3rem;
    padding-top: 0.375rem;
    padding-bottom: 0.375rem;
}

.award-sheet-fit .ui-table-input,
.award-sheet-fit .ui-table-input-2 {
    font-size: 0.6875rem;
}
</style>
