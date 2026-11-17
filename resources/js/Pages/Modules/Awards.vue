<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
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
const filters = reactive({});
const showTypes = ref(false);
const newTypeName = ref('');
const savingType = ref(false);
const typeNameDrafts = reactive({});
const undoStack = ref([]);
const redoStack = ref([]);
const stepping = ref(false);
const importInput = ref(null);
const importBusy = ref(false);
const importData = ref(null);

const numberFields = new Set([
    'age', 'years_in_country', 'years_in_sector', 'total_years', 'position_years',
]);

const syncFilters = () => {
    const next = {};

    props.columns.forEach((col) => {
        next[col.key] = filters[col.key] ?? '';
    });

    Object.keys(filters).forEach((key) => delete filters[key]);
    Object.assign(filters, next);
};

const clearFilters = () => {
    Object.keys(filters).forEach((key) => {
        filters[key] = '';
    });
};

const hasFilters = computed(() => Object.values(filters).some((value) => String(value).trim() !== ''));

const matchesFilters = (row) => props.columns.every((col) => {
    const needle = String(filters[col.key] ?? '').trim().toLowerCase();

    if (! needle) {
        return true;
    }

    const field = fieldFor(col);
    const raw = drafts[row.id]?.[field] ?? row[col.key] ?? row[field] ?? '';

    return String(raw ?? '').toLowerCase().includes(needle);
});

const visibleRows = computed(() => (
    hasFilters.value ? props.rows.filter(matchesFilters) : props.rows
));

const hasGroupedHeader = computed(() => props.columns.some((col) => col.group || col.rowspan === 2));

const topHeaderCells = computed(() => {
    if (! hasGroupedHeader.value) {
        return props.columns.map((col) => ({
            key: col.key,
            label: col.label,
            lines: col.lines,
            colspan: 1,
            rowspan: 1,
            group: false,
        }));
    }

    const cells = [];
    let index = 0;

    while (index < props.columns.length) {
        const col = props.columns[index];

        if (col.rowspan === 2 || ! col.group) {
            cells.push({
                key: col.key,
                label: col.label,
                lines: col.lines,
                colspan: 1,
                rowspan: 2,
                group: false,
            });
            index += 1;
            continue;
        }

        const group = col.group;
        let span = 0;

        while (index + span < props.columns.length && props.columns[index + span].group === group) {
            span += 1;
        }

        cells.push({
            key: `group-${group}`,
            label: group,
            colspan: span,
            rowspan: 1,
            group: true,
        });
        index += span;
    }

    return cells;
});

const subHeaderColumns = computed(() => (
    hasGroupedHeader.value
        ? props.columns.filter((col) => col.group)
        : []
));

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

const syncTypeNameDrafts = () => {
    Object.keys(typeNameDrafts).forEach((key) => delete typeNameDrafts[key]);

    props.awardTypeRows.forEach((item) => {
        typeNameDrafts[item.id] = item.name;
    });
};

watch(() => [props.rows, props.columns], syncDrafts, { immediate: true, deep: true });
watch(() => props.columns, syncFilters, { immediate: true, deep: true });
watch(() => props.awardTypeRows, syncTypeNameDrafts, { immediate: true, deep: true });
watch(() => [props.tab, props.subtype, props.year], () => {
    undoStack.value = [];
    redoStack.value = [];
    clearFilters();
});

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

const normalizeFieldValue = (field, value) => {
    let next = value;

    if (numberFields.has(field)) {
        if (next === '' || next === null || next === undefined) {
            return null;
        }

        const n = Number.parseInt(String(next), 10);

        return Number.isNaN(n) ? null : n;
    }

    if (typeof next === 'string') {
        next = next.trim();

        return next === '' ? null : next;
    }

    return next ?? null;
};

const snapshotField = (id, field) => {
    const raw = drafts[id]?.[field] ?? props.rows.find((row) => row.id === id)?.[field] ?? null;

    return normalizeFieldValue(field, raw);
};

const applyDraftPayload = (id, payload) => {
    if (! drafts[id]) {
        return;
    }

    Object.entries(payload).forEach(([field, value]) => {
        if (Object.prototype.hasOwnProperty.call(drafts[id], field)) {
            drafts[id][field] = value ?? '';
        }
    });
};

const patchAward = (id, payload, onSaved) => {
    applyDraftPayload(id, payload);

    router.patch(route('awards.update', id), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => onSaved?.(),
    });
};

const samePayload = (left, right) => JSON.stringify(left) === JSON.stringify(right);

const saveField = (id, field, value) => {
    const before = { [field]: snapshotField(id, field) };
    let next = normalizeFieldValue(field, value);
    const after = { [field]: next };

    if (field === 'register_no') {
        before.age = snapshotField(id, 'age');
        before.gender = snapshotField(id, 'gender');

        const row = props.rows.find((item) => item.id === id);
        const asOfYear = Number(row?.year || props.year || new Date().getFullYear());
        const parsed = parseMongolianRegister(next, asOfYear);

        if (parsed) {
            after.register_no = parsed.register;
            after.age = parsed.age;
            after.gender = parsed.gender;
            next = parsed.register;
        } else {
            after.age = before.age;
            after.gender = before.gender;
        }
    }

    if (samePayload(before, after)) {
        applyDraftPayload(id, after);

        return;
    }

    patchAward(id, after, () => {
        if (stepping.value) {
            return;
        }

        undoStack.value = [...undoStack.value, { id, before, after }].slice(-50);
        redoStack.value = [];
    });
};

const canUndo = computed(() => props.canManage && undoStack.value.length > 0);
const canRedo = computed(() => props.canManage && redoStack.value.length > 0);

const undo = () => {
    const entry = undoStack.value.at(-1);

    if (! entry || stepping.value) {
        return;
    }

    stepping.value = true;
    patchAward(entry.id, entry.before, () => {
        undoStack.value = undoStack.value.slice(0, -1);
        redoStack.value = [...redoStack.value, entry];
    });
    setTimeout(() => { stepping.value = false; }, 300);
};

const redo = () => {
    const entry = redoStack.value.at(-1);

    if (! entry || stepping.value) {
        return;
    }

    stepping.value = true;
    patchAward(entry.id, entry.after, () => {
        redoStack.value = redoStack.value.slice(0, -1);
        undoStack.value = [...undoStack.value, entry];
    });
    setTimeout(() => { stepping.value = false; }, 300);
};

const typingInField = (target) => target instanceof HTMLElement
    && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

const onKeydown = (event) => {
    if (! props.canManage || ! (event.ctrlKey || event.metaKey) || typingInField(event.target)) {
        return;
    }

    const key = event.key.toLowerCase();

    if (key === 'z' && ! event.shiftKey) {
        event.preventDefault();
        undo();

        return;
    }

    if (key === 'y' || (key === 'z' && event.shiftKey)) {
        event.preventDefault();
        redo();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

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

const renameAwardType = (item) => {
    if (item.in_use || savingType.value) {
        typeNameDrafts[item.id] = item.name;

        return;
    }

    const name = String(typeNameDrafts[item.id] ?? '').trim();

    if (! name || name === item.name) {
        typeNameDrafts[item.id] = item.name;

        return;
    }

    savingType.value = true;

    useForm({ name }).patch(route('awards.types.update', item.id), {
        preserveScroll: true,
        onFinish: () => {
            savingType.value = false;
        },
        onError: () => {
            typeNameDrafts[item.id] = item.name;
        },
    });
};

const removeAwardType = (item) => {
    if (item.in_use) {
        return;
    }

    if (! confirm('Энэ шагналын төрлийг устгах уу?')) {
        return;
    }

    router.delete(route('awards.types.destroy', item.id), { preserveScroll: true });
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

const groupClass = (label) => {
    if (String(label).includes('Ерөнхий')) {
        return 'ui-register__head-group--issued';
    }

    if (String(label).includes('Сүүлд')) {
        return 'ui-register__head-group--numbers';
    }

    if (String(label).includes('уламжилсан')) {
        return 'ui-register__head-group--void';
    }

    return 'ui-register__head-group--issued';
};

const pickImportFile = () => importInput.value?.click();

const onImportFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (! file) {
        return;
    }

    importBusy.value = true;

    try {
        const form = new FormData();
        form.append('file', file);
        form.append('tab', props.tab);

        const { data } = await window.axios.post(route('awards.import.preview'), form);
        importData.value = { ...data, file_name: file.name };
        remapImport();
    } catch (err) {
        alert(
            err?.response?.data?.errors?.file?.[0]
            || err?.response?.data?.message
            || 'Файлыг уншиж чадсангүй.',
        );
    } finally {
        importBusy.value = false;
    }
};

const remapImport = () => {
    if (! importData.value) {
        return;
    }

    const fields = Object.keys(importData.value.fields ?? {});

    importData.value.entries = (importData.value.rows ?? [])
        .map((row) => {
            const entry = {};

            fields.forEach((field) => {
                const index = importData.value.mapping[field];
                const raw = index === null || index === undefined || index === ''
                    ? ''
                    : (row[index] ?? '');
                entry[field] = String(raw).trim() === '' ? null : String(raw);
            });

            return entry;
        })
        .filter((entry) => fields.some((field) => String(entry[field] ?? '').trim() !== ''));

    importData.value.total = importData.value.entries.length;
};

const closeImport = () => {
    importData.value = null;
};

const confirmImport = () => {
    if (! importData.value || importBusy.value || ! importData.value.total) {
        return;
    }

    importBusy.value = true;

    router.post(route('awards.import.store'), {
        tab: props.tab,
        subtype: props.subtype || null,
        year: props.year || new Date().getFullYear(),
        entries: importData.value.entries,
    }, {
        preserveScroll: true,
        onSuccess: () => closeImport(),
        onFinish: () => { importBusy.value = false; },
    });
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
                        :disabled="! canUndo"
                        :title="canUndo ? 'Сүүлийн үйлдлийг буцаах (Ctrl+Z)' : 'Буцаах үйлдэл алга'"
                        aria-label="Буцаах"
                        @click="undo"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9l5-5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h9a6 6 0 0 1 0 12h-3" />
                        </svg>
                    </button>

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn"
                        :disabled="! canRedo"
                        :title="canRedo ? 'Буцаасан үйлдлийг дахин хийх (Ctrl+Y)' : 'Дахин хийх үйлдэл алга'"
                        aria-label="Дахин хийх"
                        @click="redo"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m15 14 5-5-5-5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 9h-9a6 6 0 0 0 0 12h3" />
                        </svg>
                    </button>

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

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn"
                        :disabled="importBusy"
                        title="Excel файлаас оруулах"
                        aria-label="Файлаас оруулах"
                        @click="pickImportFile"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1" />
                        </svg>
                    </button>
                    <input
                        ref="importInput"
                        type="file"
                        class="hidden"
                        accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        @change="onImportFile"
                    >

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

            <div class="ui-card ui-register max-h-[min(70vh,calc(100dvh-12rem))] overflow-auto overscroll-contain">
                <table
                    class="ui-table ui-register__table"
                    :class="fitsViewport ? 'w-full table-fixed' : 'min-w-[1800px]'"
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
                                v-for="cell in topHeaderCells"
                                :key="cell.key"
                                class="sticky top-0 z-10 bg-brand-navy-50 text-center"
                                :class="cell.group ? groupClass(cell.label) : ''"
                                :colspan="cell.colspan"
                                :rowspan="cell.rowspan"
                            >
                                <template v-if="cell.lines?.length">
                                    <span
                                        v-for="(line, idx) in cell.lines"
                                        :key="idx"
                                        class="block leading-tight"
                                    >{{ line }}</span>
                                </template>
                                <template v-else>{{ cell.label }}</template>
                            </th>
                            <th
                                v-if="canManage"
                                class="sticky top-0 z-10 bg-brand-navy-50 text-center"
                                :rowspan="hasGroupedHeader ? 2 : 1"
                            />
                        </tr>
                        <tr v-if="hasGroupedHeader">
                            <th
                                v-for="col in subHeaderColumns"
                                :key="`sub-${col.key}`"
                                class="sticky z-10 bg-brand-navy-50 text-center text-xs"
                                :style="{ top: '2.5rem' }"
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
                        </tr>
                        <tr class="ui-filters ui-register__filters">
                            <th v-for="col in columns" :key="`filter-${col.key}`">
                                <button
                                    v-if="col.key === 'no' && hasFilters"
                                    type="button"
                                    class="w-full text-[10px] font-semibold text-brand-orange-600 hover:underline"
                                    title="Хайлтыг цэвэрлэх"
                                    @click="clearFilters"
                                >
                                    Цэвэрлэх
                                </button>
                                <input
                                    v-else-if="col.key !== 'no'"
                                    v-model="filters[col.key]"
                                    type="search"
                                    placeholder="Хайх"
                                >
                            </th>
                            <th v-if="canManage" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="columns.length + (canManage ? 1 : 0)"
                                class="ui-register__empty !py-14 text-center text-slate-400"
                            >
                                <template v-if="canManage">
                                    Одоогоор мөр алга. «Шинэ нэмэх» дарж эхлүүлнэ үү.
                                </template>
                                <template v-else>
                                    {{ pageTitle }} бүртгэл одоогоор хоосон байна.
                                </template>
                            </td>
                        </tr>

                        <tr v-else-if="!visibleRows.length">
                            <td
                                :colspan="columns.length + (canManage ? 1 : 0)"
                                class="ui-register__empty px-4 py-3 text-center text-xs text-slate-500"
                            >
                                Хайлтад тохирох мөр олдсонгүй.
                                <button type="button" class="ml-1 font-semibold text-brand-navy-600 hover:underline" @click="clearFilters">
                                    Цэвэрлэх
                                </button>
                            </td>
                        </tr>

                        <tr v-for="row in visibleRows" :key="row.id">
                            <td
                                v-for="col in columns"
                                :key="`${row.id}-${col.key}`"
                                :class="[
                                    isEditable(col) && col.input !== 'gender' ? 'ui-sheet-td' : '',
                                    colAlign(col) === 'center' ? 'text-center' : '',
                                    col.key === 'no' ? 'font-semibold text-slate-500' : '',
                                    col.nowrap ? 'whitespace-nowrap' : '',
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
                                    :nowrap="!!col.nowrap"
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
                                    :nowrap="!!col.nowrap"
                                    :clamp="!col.multiline"
                                    @commit="(v) => saveField(row.id, fieldFor(col), v)"
                                />

                                <span
                                    v-else
                                    :class="col.nowrap ? 'whitespace-nowrap' : 'ui-clamp-2'"
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

        <Modal :show="!!importData" max-width="4xl" @close="closeImport">
            <div v-if="importData" class="ui-card-pad space-y-4">
                <div>
                    <h2 class="ui-title">Excel-ээс оруулах</h2>
                    <p class="ui-subtitle">
                        {{ importData.file_name }} — {{ importData.total }} мөр олдлоо.
                    </p>
                </div>
                <div class="max-h-64 overflow-auto rounded-xl border border-slate-200">
                    <table class="ui-table w-full text-xs">
                        <thead>
                            <tr>
                                <th
                                    v-for="(header, index) in importData.headers"
                                    :key="`ih-${index}`"
                                    class="whitespace-nowrap"
                                >
                                    {{ header || `Багана ${index + 1}` }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, rIndex) in (importData.rows || []).slice(0, 6)" :key="`ir-${rIndex}`">
                                <td
                                    v-for="(cell, cIndex) in row"
                                    :key="`ic-${rIndex}-${cIndex}`"
                                    class="ui-clamp-1"
                                >
                                    {{ cell }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="ui-btn-ghost" @click="closeImport">Болих</button>
                    <button
                        type="button"
                        class="ui-btn-accent"
                        :disabled="importBusy || !importData.total"
                        @click="confirmImport"
                    >
                        {{ importBusy ? 'Оруулж байна…' : `${importData.total} мөр оруулах` }}
                    </button>
                </div>
            </div>
        </Modal>

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
                                <td>
                                    <input
                                        v-model="typeNameDrafts[item.id]"
                                        type="text"
                                        class="ui-input w-full"
                                        :disabled="item.in_use || savingType"
                                        :title="item.in_use
                                            ? 'Шагналын бүртгэлд ашиглагдаж байгаа тул нэр солих боломжгүй'
                                            : 'Нэрийг засаад Enter эсвэл гадна дарна'"
                                        maxlength="255"
                                        @keydown.enter.prevent="renameAwardType(item)"
                                        @blur="renameAwardType(item)"
                                    />
                                </td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        :disabled="item.in_use"
                                        :title="item.in_use
                                            ? 'Шагналын бүртгэлд ашиглагдаж байгаа тул устгах боломжгүй'
                                            : 'Устгах'"
                                        :aria-label="item.in_use ? 'Устгах боломжгүй' : 'Устгах'"
                                        @click="removeAwardType(item)"
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
