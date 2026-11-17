<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SheetCell from '@/Components/SheetCell.vue';
import { parseMongolianRegister } from '@/utils/mongolianRegister';

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
    awardTypes: { type: Array, default: () => [] },
    awardTypeRows: { type: Array, default: () => [] },
});

const drafts = reactive({});
const showTypes = ref(false);
const newTypeName = ref('');
const savingType = ref(false);

const numberFields = new Set([
    'age', 'years_in_country', 'years_in_sector', 'total_years', 'position_years',
]);

const draftFields = computed(() => {
    const fields = props.columns
        .filter((col) => ! col.readonly && col.field)
        .map((col) => col.field);

    // РД-аас бөглөгдөх талбаруудыг draft-д бас хадгална.
    return [...new Set([...fields, 'age', 'gender', 'register_no'])];
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

    const payload = { [field]: next };

    if (field === 'register_no') {
        const row = props.rows.find((item) => item.id === id);
        const asOfYear = Number(row?.year || props.year || new Date().getFullYear());
        const parsed = parseMongolianRegister(next, asOfYear);

        if (parsed) {
            payload.register_no = parsed.register;
            payload.age = parsed.age;
            payload.gender = parsed.gender;

            if (drafts[id]) {
                drafts[id].register_no = parsed.register;
                drafts[id].age = parsed.age;
                drafts[id].gender = parsed.gender;
            }
        } else if (drafts[id]) {
            drafts[id][field] = next ?? '';
        }
    } else if (drafts[id] && Object.prototype.hasOwnProperty.call(drafts[id], field)) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('awards.update', id),
        payload,
        { preserveScroll: true, preserveState: true },
    );
};

const destroyRow = (id) => {
    if (! confirm('Устгах уу?')) {
        return;
    }

    router.delete(route('awards.destroy', id), { preserveScroll: true });
};

const addAwardType = () => {
    const name = newTypeName.value.trim();

    if (! name || savingType.value) {
        return;
    }

    savingType.value = true;

    useForm({ name }).post(route('awards.types.store'), {
        preserveScroll: true,
        onFinish: () => {
            savingType.value = false;
            newTypeName.value = '';
        },
    });
};

const removeAwardType = (id) => {
    if (! confirm('Энэ шагналын төрлийг устгах уу?')) {
        return;
    }

    router.delete(route('awards.types.destroy', id), { preserveScroll: true });
};

const cellValue = (row, key) => {
    const value = drafts[row.id]?.[key] ?? row[key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
};

const fieldFor = (col) => col.field || col.key;

const isEditable = (col) => props.canManage && ! col.readonly && col.field;

const pageTitle = computed(() => props.categories[props.tab] || 'Шагнал');

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
            <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <nav class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
                </nav>

                <div class="flex shrink-0 items-center gap-1 border-l border-slate-200 pl-2">
                    <label
                        v-if="years.length"
                        class="relative inline-flex items-center"
                        :title="year ? `${year} он` : 'Он сонгох'"
                    >
                        <span class="pointer-events-none absolute left-2 text-slate-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3M16 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z" />
                            </svg>
                        </span>
                        <select
                            class="ui-input !h-9 w-[5.75rem] appearance-none !py-1.5 pl-8 pr-2 text-xs font-semibold"
                            :value="year ?? 'all'"
                            aria-label="Он сонгох"
                            @change="switchYear($event.target.value)"
                        >
                            <option value="all">Бүгд</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            <option v-if="year && !years.includes(year)" :value="year">{{ year }}</option>
                        </select>
                    </label>

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn"
                        title="Шагналын төрөл"
                        aria-label="Шагналын төрөл"
                        @click="showTypes = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l2.2 4.5 5 .7-3.6 3.5.9 5L12 14.8 7.5 16.7l.9-5L4.8 8.2l5-.7L12 3z" />
                        </svg>
                    </button>

                    <a :href="exportUrl" class="ui-icon-btn" title="Excel татах" aria-label="Excel татах">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1" />
                        </svg>
                    </a>

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn bg-brand-orange-500 text-white hover:bg-brand-orange-600"
                        title="Шинэ мөр нэмэх"
                        aria-label="Нэмэх"
                        @click="addRow"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="subtypes.length" class="flex flex-wrap items-center gap-2">
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
                                class="sticky top-0 z-10 bg-brand-navy-50"
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
                            <th v-if="canManage" class="sticky top-0 z-10 bg-brand-navy-50 text-center" />
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
                                <template v-if="col.key === 'no'">
                                    {{ row.no }}
                                </template>

                                <span
                                    v-else-if="col.readonly"
                                    class="block text-center text-xs tabular-nums text-slate-600"
                                    :title="col.hint || undefined"
                                >
                                    {{ cellValue(row, col.key) }}
                                </span>

                                <SheetCell
                                    v-else-if="isEditable(col) && drafts[row.id] && col.input === 'award_type'"
                                    v-model="drafts[row.id][fieldFor(col)]"
                                    :options="awardTypes"
                                    :editable="true"
                                    placeholder="Шагнал сонгох"
                                    @commit="(v) => saveField(row.id, fieldFor(col), v)"
                                />

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

        <Modal :show="showTypes" max-width="lg" @close="showTypes = false">
            <div class="ui-card-pad space-y-4">
                <div>
                    <h2 class="ui-title">Шагналын төрөл</h2>
                    <p class="ui-subtitle">Хүснэгтээс сонгох жагсаалтыг энд нэмнэ.</p>
                </div>

                <form class="flex gap-2" @submit.prevent="addAwardType">
                    <input
                        v-model="newTypeName"
                        type="text"
                        class="ui-input flex-1"
                        placeholder="Жишээ: Сүхбаатарын одон"
                        maxlength="255"
                    />
                    <button type="submit" class="ui-btn-accent" :disabled="savingType || !newTypeName.trim()">
                        Нэмэх
                    </button>
                </form>

                <div class="max-h-80 overflow-auto rounded-xl border border-slate-200">
                    <table class="ui-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Нэр</th>
                                <th class="w-14 text-center" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!awardTypeRows.length">
                                <td colspan="2" class="!py-8 text-center text-slate-400">Төрөл алга</td>
                            </tr>
                            <tr v-for="item in awardTypeRows" :key="item.id">
                                <td>{{ item.name }}</td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        title="Устгах"
                                        @click="removeAwardType(item.id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="ui-btn-ghost" @click="showTypes = false">Хаах</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
