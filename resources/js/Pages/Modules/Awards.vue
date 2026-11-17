<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SheetCell from '@/Components/SheetCell.vue';
import { useStickyTableHead } from '@/composables/useStickyTableHead';
import { parseMongolianRegister } from '@/utils/mongolianRegister';

const props = defineProps({
    tab: { type: String, default: 'state_high' },
    tabs: { type: Array, default: () => [] },
    subtype: { type: String, default: '' },
    subtypes: { type: Array, default: () => [] },
    year: { type: Number, default: null },
    yearAll: { type: Boolean, default: false },
    years: { type: Array, default: () => [] },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 100 },
    totalMatched: { type: Number, default: 0 },
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
const registerEl = ref(null);
const { schedule: scheduleStickySync } = useStickyTableHead(() => registerEl.value);
/** Хүснэгтийг дэлгэц дүүрэн харах (Esc дарж хаана). */
const tableExpanded = ref(false);
const showTypes = ref(false);
const newTypeName = ref('');
const typeSearch = ref('');
const savingType = ref(false);
const typeNameDrafts = reactive({});
const undoStack = ref([]);
const redoStack = ref([]);
const stepping = ref(false);
const importInput = ref(null);
const importBusy = ref(false);
const importData = ref(null);

/**
 * Засварлах мөрүүд.
 *
 * Бүх хүснэгтийг нэг дор нээхийн оронд мөр бүрийг тусад нь
 * засварлана — санамсаргүй өөрчлөх эрсдэл багасна.
 */
const editingRows = ref([]);
const addingRow = ref(false);

const isEditing = (id) => props.canManage && editingRows.value.includes(id);

const ensureDraft = (id) => {
    if (drafts[id]) {
        return;
    }

    const row = props.rows.find((item) => item.id === id);

    drafts[id] = Object.fromEntries(
        draftFields.value.map((field) => [field, row?.[field] ?? '']),
    );
};

const toggleRowEdit = (id) => {
    if (! props.canManage) {
        return;
    }

    if (isEditing(id)) {
        editingRows.value = editingRows.value.filter((value) => value !== id);
        delete drafts[id];

        return;
    }

    ensureDraft(id);
    editingRows.value = [...editingRows.value, id];
};

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

const syncTypeNameDrafts = () => {
    Object.keys(typeNameDrafts).forEach((key) => delete typeNameDrafts[key]);

    props.awardTypeRows.forEach((item) => {
        typeNameDrafts[item.id] = item.name;
    });
};

// Draft-ыг бүх мөрт үүсгэхгүй — зөвхөн засах горимд (гацаанаас сэргийлнэ).
watch(() => props.rows, () => {
    Object.keys(drafts).forEach((key) => {
        if (! editingRows.value.includes(Number(key)) && ! editingRows.value.includes(key)) {
            delete drafts[key];
        }
    });
});
watch(() => props.columns, syncFilters, { immediate: true, deep: true });
watch(() => props.awardTypeRows, syncTypeNameDrafts, { immediate: true, deep: true });
watch(() => [props.tab, props.subtype, props.year, props.yearAll, props.page], () => {
    undoStack.value = [];
    redoStack.value = [];
    editingRows.value = [];
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    clearFilters();
    scheduleStickySync();
});

watch(() => props.columns, () => scheduleStickySync(), { deep: true });

const queryParams = (overrides = {}) => {
    const params = {
        tab: props.tab,
        ...(props.subtype ? { subtype: props.subtype } : {}),
        ...(props.yearAll ? { year: 'all' } : (props.year ? { year: props.year } : {})),
        ...(props.yearAll && props.page > 1 ? { page: props.page } : {}),
        ...overrides,
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key];
        }
    });

    return params;
};

const totalPages = computed(() => (
    props.yearAll ? Math.max(1, Math.ceil(props.totalMatched / 100)) : 1
));

const goToPage = (page) => {
    const next = Math.min(Math.max(1, Number(page) || 1), totalPages.value);

    router.get(route('awards.index'), queryParams({ year: 'all', page: next > 1 ? next : undefined }), {
        preserveState: false,
        preserveScroll: true,
    });
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
    if (value === '' || value === 'all') {
        router.get(route('awards.index'), queryParams({ year: 'all', page: undefined }), {
            preserveState: false,
            preserveScroll: true,
        });

        return;
    }

    router.get(route('awards.index'), queryParams({ year: Number(value), page: undefined }), {
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
    if (! props.canManage || addingRow.value) {
        return;
    }

    addingRow.value = true;

    const before = props.rows.map((row) => row.id);
    const payload = {
        category: props.tab,
        year: props.year || new Date().getFullYear(),
    };

    const subtype = defaultSubtype();
    if (subtype) {
        payload.subtype = subtype;
    }

    useForm(payload).post(route('awards.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const added = props.rows.find((row) => ! before.includes(row.id));

            if (added) {
                ensureDraft(added.id);
                editingRows.value = [added.id];
            }
        },
        onFinish: () => {
            addingRow.value = false;
        },
    });
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
    if (event.key === 'Escape' && tableExpanded.value) {
        tableExpanded.value = false;

        return;
    }

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

const toggleTableExpanded = () => {
    tableExpanded.value = ! tableExpanded.value;
    scheduleStickySync();
};

watch(tableExpanded, () => scheduleStickySync());

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const destroyRow = (id) => {
    if (! confirm('Устгах уу?')) {
        return;
    }

    editingRows.value = editingRows.value.filter((value) => value !== id);
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

const toggleAwardTypeActive = (item) => {
    if (savingType.value) {
        return;
    }

    savingType.value = true;

    useForm({ is_active: ! item.is_active }).patch(route('awards.types.update', item.id), {
        preserveScroll: true,
        onFinish: () => {
            savingType.value = false;
        },
    });
};

const visibleAwardTypes = computed(() => {
    const needle = typeSearch.value.trim().toLowerCase();

    if (! needle) {
        return props.awardTypeRows;
    }

    return props.awardTypeRows.filter((item) => {
        const name = String(typeNameDrafts[item.id] ?? item.name ?? '').toLowerCase();

        return name.includes(needle);
    });
});

const cellValue = (row, key) => {
    const value = drafts[row.id]?.[key] ?? row[key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
};

const fieldFor = (col) => col.field || col.key;

const isEditable = (col, rowId) => (
    props.canManage
    && ! col.readonly
    && col.field
    && isEditing(rowId)
);

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
        <div class="ui-page ui-page--awards-table">
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
                            :value="yearAll ? 'all' : (year ?? 'all')"
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
                        type="button"
                        class="ui-icon-btn"
                        :title="tableExpanded ? 'Хэвийн хэмжээнд буцах (Esc)' : 'Хүснэгтийг дэлгэц дүүрэн харах'"
                        :aria-label="tableExpanded ? 'Хаах' : 'Дэлгэц дүүрэн'"
                        @click="toggleTableExpanded"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path
                                v-if="tableExpanded"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 9L5 5m0 0h4M5 5v4m10 0l4-4m0 0h-4m4 0v4M9 15l-4 4m0 0h4m-4 0v-4m10 0l4 4m0 0h-4m4 0v-4"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 9V5a1 1 0 011-1h4M20 9V5a1 1 0 00-1-1h-4M4 15v4a1 1 0 001 1h4M20 15v4a1 1 0 01-1 1h-4"
                            />
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

            <div
                class="ui-awards-table-shell"
                :class="tableExpanded ? 'ui-table-overlay' : ''"
            >
                <div
                    v-if="tableExpanded"
                    class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-brand-navy-900">{{ pageTitle }}</p>
                        <p class="text-xs text-slate-500">
                            {{ yearAll ? 'Бүх он' : `${year} он` }}
                            · {{ totalMatched || rows.length }} мөр
                            · Esc дарж хаана
                        </p>
                    </div>
                    <button type="button" class="ui-btn-ghost" @click="tableExpanded = false">
                        Хаах
                    </button>
                </div>

            <div class="ui-card flex min-h-0 flex-1 flex-col overflow-hidden">
                <div class="min-h-0 flex-1 overflow-auto overscroll-contain">
                <div ref="registerEl" class="ui-register">
                <table
                    class="ui-register__table ui-register--pin-actions"
                    :class="fitsViewport ? 'w-full' : 'min-w-[1800px]'"
                >
                    <colgroup>
                        <col
                            v-for="(col, index) in columns"
                            :key="`col-${col.key}`"
                            :style="fitsViewport
                                ? { width: fitColPercents[index] }
                                : (col.width ? { width: col.width } : undefined)"
                        />
                        <!-- Засах + устгах icon багтах өргөн. -->
                        <col v-if="canManage" style="width: 6rem" />
                    </colgroup>

                    <thead>
                        <tr>
                            <th
                                v-for="cell in topHeaderCells"
                                :key="cell.key"
                                class="bg-brand-navy-50 text-center"
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
                                class="ui-sticky-actions bg-brand-navy-50 text-center"
                                :rowspan="hasGroupedHeader ? 2 : 1"
                            />
                        </tr>
                        <tr v-if="hasGroupedHeader">
                            <th
                                v-for="col in subHeaderColumns"
                                :key="`sub-${col.key}`"
                                class="bg-brand-navy-50 text-center text-xs"
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
                            <th v-if="canManage" class="ui-sticky-actions" />
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

                        <tr v-for="(row, rowIndex) in visibleRows" :key="row.id">
                            <td
                                v-for="col in columns"
                                :key="`${row.id}-${col.key}`"
                                :class="[
                                    isEditable(col, row.id) && col.input !== 'gender' ? 'ui-sheet-td' : '',
                                    colAlign(col) === 'center' ? 'text-center' : '',
                                    col.key === 'no' ? 'font-semibold text-slate-500' : '',
                                    col.nowrap ? 'whitespace-nowrap' : '',
                                ]"
                            >
                                <template v-if="col.key === 'no'">
                                    {{ hasFilters ? visibleRows.length - rowIndex : row.no }}
                                </template>

                                <span
                                    v-else-if="col.readonly"
                                    class="block text-center text-xs tabular-nums text-slate-600"
                                    :title="col.hint || undefined"
                                >
                                    {{ cellValue(row, col.key) }}
                                </span>

                                <SheetCell
                                    v-else-if="isEditable(col, row.id) && drafts[row.id] && col.input === 'award_type'"
                                    v-model="drafts[row.id][fieldFor(col)]"
                                    :options="awardTypes"
                                    :editable="true"
                                    :nowrap="!!col.nowrap"
                                    placeholder="Шагнал сонгох"
                                    @commit="(v) => saveField(row.id, fieldFor(col), v)"
                                />

                                <SheetCell
                                    v-else-if="isEditable(col, row.id) && drafts[row.id]"
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

                            <td v-if="canManage" class="ui-sticky-actions text-center align-middle">
                                <div class="flex items-center justify-center gap-0.5">
                                    <button
                                        type="button"
                                        class="ui-icon-btn"
                                        :class="isEditing(row.id) ? 'bg-brand-navy-600 text-white' : ''"
                                        :title="isEditing(row.id) ? 'Засварыг дуусгах' : 'Энэ мөрийг засах'"
                                        :aria-label="isEditing(row.id) ? 'Засварыг дуусгах' : 'Энэ мөрийг засах'"
                                        @click="toggleRowEdit(row.id)"
                                    >
                                        <svg v-if="isEditing(row.id)" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10a2.1 2.1 0 1 0-3-3L5 17v3z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        title="Устгах"
                                        aria-label="Устгах"
                                        @click="destroyRow(row.id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                </div>

                <div
                    v-if="yearAll && totalMatched > 100"
                    class="ui-awards-pager flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs text-slate-600"
                >
                    <p>
                        Нийт <span class="font-semibold text-slate-800">{{ totalMatched }}</span> мөр —
                        хуудас {{ page }}/{{ totalPages }} (100 мөр). Оноор шүүвэл илүү хурдан.
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="ui-icon-btn"
                            :disabled="page <= 1"
                            title="Өмнөх хуудас"
                            aria-label="Өмнөх хуудас"
                            @click="goToPage(page - 1)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="ui-icon-btn"
                            :disabled="page >= totalPages"
                            title="Дараах хуудас"
                            aria-label="Дараах хуудас"
                            @click="goToPage(page + 1)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
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

        <Modal :show="showTypes" max-width="2xl" @close="showTypes = false">
            <div class="ui-card-pad space-y-3">
                <div>
                    <h2 class="ui-title">Шагналын төрөл</h2>
                    <p class="ui-subtitle">Хүснэгтээс сонгох жагсаалтыг энд нэмнэ. Идэвхгүй төрөл сонголтод гарахгүй.</p>
                </div>

                <form class="flex gap-2" @submit.prevent="addAwardType">
                    <input
                        v-model="newTypeName"
                        type="text"
                        class="ui-input flex-1 !py-1.5 text-sm"
                        placeholder="Жишээ: Сүхбаатарын одон"
                        maxlength="255"
                    />
                    <button type="submit" class="ui-btn-accent shrink-0" :disabled="savingType || !newTypeName.trim()">
                        Нэмэх
                    </button>
                </form>

                <div class="flex items-center gap-2">
                    <input
                        v-model="typeSearch"
                        type="search"
                        class="ui-input flex-1 !py-1.5 text-sm"
                        placeholder="Төрлөөс хайх…"
                    />
                    <span class="shrink-0 text-xs text-slate-500">
                        {{ visibleAwardTypes.length }}/{{ awardTypeRows.length }}
                    </span>
                </div>

                <div class="max-h-[min(70vh,32rem)] overflow-auto rounded-xl border border-slate-200">
                    <table class="ui-table w-full text-xs">
                        <thead>
                            <tr>
                                <th class="sticky top-0 z-10 bg-brand-navy-50 !py-1.5 text-left">Нэр</th>
                                <th class="sticky top-0 z-10 w-24 bg-brand-navy-50 !py-1.5 text-center">Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!awardTypeRows.length">
                                <td colspan="2" class="!py-6 text-center text-slate-400">Төрөл алга</td>
                            </tr>
                            <tr v-else-if="!visibleAwardTypes.length">
                                <td colspan="2" class="!py-6 text-center text-slate-400">Хайлтад тохирох төрөл олдсонгүй</td>
                            </tr>
                            <tr
                                v-for="item in visibleAwardTypes"
                                :key="item.id"
                                :class="item.is_active ? '' : 'bg-slate-50 opacity-70'"
                            >
                                <td class="!py-1">
                                    <input
                                        v-model="typeNameDrafts[item.id]"
                                        type="text"
                                        class="ui-input w-full !py-1 text-xs"
                                        :disabled="item.in_use || savingType"
                                        :title="item.in_use
                                            ? 'Шагналын бүртгэлд ашиглагдаж байгаа тул нэр солих боломжгүй'
                                            : 'Нэрийг засаад Enter эсвэл гадна дарна'"
                                        maxlength="255"
                                        @keydown.enter.prevent="renameAwardType(item)"
                                        @blur="renameAwardType(item)"
                                    />
                                </td>
                                <td class="!py-1 text-center">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <button
                                            type="button"
                                            class="ui-icon-btn !h-7 !w-7"
                                            :class="item.is_active ? '' : 'text-amber-600'"
                                            :disabled="savingType"
                                            :title="item.is_active ? 'Идэвхгүй болгох' : 'Идэвхжүүлэх'"
                                            :aria-label="item.is_active ? 'Идэвхгүй болгох' : 'Идэвхжүүлэх'"
                                            @click="toggleAwardTypeActive(item)"
                                        >
                                            <svg v-if="item.is_active" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 6 12 6s9.5 6 9.5 6-3 6-9.5 6S2.5 12 2.5 12z" />
                                            </svg>
                                            <svg v-else class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.5 10.7a3 3 0 004.1 4.1M9.9 5.6C10.6 5.2 11.3 5 12 5c6.5 0 9.5 7 9.5 7a18 18 0 01-4.2 4.6M6.1 6.2A18.4 18.4 0 002.5 12S5.5 19 12 19c1.1 0 2.1-.2 3.1-.5" />
                                            </svg>
                                        </button>
                                        <button
                                            type="button"
                                            class="ui-icon-btn ui-icon-btn--danger !h-7 !w-7"
                                            :disabled="item.in_use"
                                            :title="item.in_use
                                                ? 'Шагналын бүртгэлд ашиглагдаж байгаа тул устгах боломжгүй'
                                                : 'Устгах'"
                                            :aria-label="item.in_use ? 'Устгах боломжгүй' : 'Устгах'"
                                            @click="removeAwardType(item)"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z" />
                                            </svg>
                                        </button>
                                    </div>
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
