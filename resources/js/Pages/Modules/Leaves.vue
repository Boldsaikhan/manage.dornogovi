<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    activeScope: { type: String, default: 'baiguullaga' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    scopes: { type: Object, default: () => ({}) },
    types: { type: Object, default: () => ({}) },
    signers: { type: Object, default: () => ({}) },
});

const view = ref('table'); // 'table' | 'sheet'
const previewCopies = ref(6);
const actingName = ref('М.МӨНХБАТ');
const cellClass = 'ui-register__cell';

const typeEntries = computed(() => Object.entries(props.types));
const signerEntries = computed(() => Object.entries(props.signers));

/** Байгууллагын нэрсийн сонголт — утасны жагсаалтаас. */
const orgOptions = computed(() => props.directory.map((d) => ({
    value: d.org_name,
    label: d.org_name,
    category: d.category,
})));

/** Албан хаагчдын сонголт — утасны жагсаалтын бүх хүн, байгууллагатай нь. */
const peopleOptions = computed(() => props.directory.flatMap((d) => (d.people ?? []).map((p) => ({
    value: p.name,
    label: p.name,
    hint: p.position || '',
    org: d.org_name,
    category: d.category,
}))));

const unitFromOrg = (name) => {
    const text = String(name || '').trim();
    return text.replace(/\s*хэлт(эс|сийн)$/iu, '').trim();
};

const switchScope = (value) => {
    router.get(route('leaves.index'), { scope: value }, { preserveState: false, preserveScroll: true });
};

const addingRow = ref(false);

const addRow = () => {
    if (! props.canManage || addingRow.value) return;

    addingRow.value = true;

    router.post(route('leaves.store'), {
        scope: props.activeScope === 'all' ? 'baiguullaga' : props.activeScope,
    }, {
        preserveScroll: true,
        onFinish: () => { addingRow.value = false; },
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('leaves.destroy', id), { preserveScroll: true });
};

/**
 * «Засах» горим — товчоор асаана.
 *
 * Унтраалттай үед хүснэгт зөвхөн харагдана: нүдэн дээр санамсаргүй дарж утга
 * өөрчлөгдөхгүй. Асаалттай үед бүх нүд идэвхжиж, мөр устгах товч гарна.
 */
const editMode = ref(false);

const rowEditable = computed(() => props.canManage && editMode.value);

const toggleEditMode = () => {
    editMode.value = ! editMode.value;
};

watch(() => props.activeScope, () => {
    editMode.value = false;
});

/** Мөр сонгож, Excel/Word/PDF-ээр татах. */
const selectedIds = ref([]);

const isSelected = (id) => selectedIds.value.includes(id);

const toggleRow = (id) => {
    selectedIds.value = isSelected(id)
        ? selectedIds.value.filter((value) => value !== id)
        : [...selectedIds.value, id];
};

const allSelected = computed(
    () => visibleRows.value.length > 0 && visibleRows.value.every((row) => isSelected(row.id)),
);

const toggleAll = () => {
    const ids = visibleRows.value.map((row) => row.id);
    selectedIds.value = allSelected.value
        ? selectedIds.value.filter((id) => ! ids.includes(id))
        : [...new Set([...selectedIds.value, ...ids])];
};

watch(() => props.activeScope, () => { selectedIds.value = []; });

const downloadOpen = ref(false);

const downloadFormats = [
    { format: 'xlsx', label: 'Excel (.xlsx)' },
    { format: 'docx', label: 'Word (.docx)' },
    { format: 'pdf', label: 'PDF' },
];

const download = (format) => {
    const url = new URL(route('leaves.export'), window.location.origin);
    url.searchParams.set('format', format);
    url.searchParams.set('scope', props.activeScope);

    // Сонгоогүй бол идэвхтэй табын бүх мөрийг татна.
    if (selectedIds.value.length) {
        url.searchParams.set('ids', selectedIds.value.join(','));
    }

    downloadOpen.value = false;
    window.location.href = url.toString();
};

/**
 * Нүд бүрийн засварлах утга — хүснэгтэд шууд бөглөхөд ашиглана.
 *
 * Мөр бүр серверт бодит бичлэгтэй тул «Мөр нэмэх» дарахад шууд хоосон мөр
 * үүсээд, дараа нь нүд бүрийг {@see saveField} горимоор нэг нэгээр нь
 * хадгална.
 */
const DRAFT_FIELDS = ['org_name', 'person_name', 'type', 'start_date', 'days', 'reason', 'signer'];

const drafts = reactive({});

const buildDraft = (row) => Object.fromEntries(DRAFT_FIELDS.map((f) => [f, row[f] ?? '']));

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    props.rows.forEach((row) => {
        drafts[row.id] = buildDraft(row);
    });
};

watch(() => props.rows, syncDrafts, { immediate: true });

const saveField = (id, field, value) => {
    let next = value;

    if (field === 'days') {
        const n = Number.parseInt(next, 10);
        next = Number.isNaN(n) ? 1 : Math.min(365, Math.max(1, n));
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next;
    }

    if (drafts[id]) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('leaves.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const slipPrintUrl = (row) => {
    const base = row.slip_url || route('leaves.slip', row.id);
    const params = new URLSearchParams({
        copies: String(previewCopies.value),
        signer: row.signer || 'acting',
        name: actingName.value,
    });
    return `${base}?${params.toString()}`;
};

/** Жагсаалтыг A4 хуудас бүрт 6 ширхэгээр хуваана. */
const pages = computed(() => {
    const size = 6;
    const chunks = [];
    for (let i = 0; i < props.rows.length; i += size) {
        const slice = props.rows.slice(i, i + size);
        while (slice.length < size && props.rows.length > 0) {
            // Сүүлийн хуудсыг бүрэн дүүргэхгүй — зөвхөн бодит мөрүүд
            break;
        }
        chunks.push(slice);
    }
    return chunks;
});

const kindLabel = (key) => ({
    tsalintai: 'цалинтай',
    tsalingui: 'цалингүй',
    eeljiin: 'ээлжийн амралтаас',
}[key] || key);

const emptyMessage = computed(() => {
    const map = {
        all: 'Бүртгэл алга',
        udirdlaga: 'Аймгийн удирдлагын бүртгэл алга',
        agentlag: 'Агентлагийн бүртгэл алга',
        sum: 'Сумын бүртгэл алга',
        baiguullaga: 'Байгууллагын бүртгэл алга',
    };
    return map[props.activeScope] || 'Бүртгэл алга';
});

const registerTitle = computed(() => {
    const label = props.tabs.find((t) => t.value === props.activeScope)?.label;
    return label ? `${label} — чөлөөний бүртгэл` : 'Чөлөөний бүртгэл';
});

/**
 * Багана тус бүрийн хайлт.
 *
 * Хүснэгтийн толгойн доор жижиг талбар гарч, бичсэн үгээр нь мөрүүдийг
 * шүүнэ. Хэд хэдэн баганад зэрэг бичвэл бүгдэд нь тохирсон мөр л үлдэнэ.
 */
const filters = reactive({
    org_name: '',
    person_name: '',
    type_label: '',
    start_date: '',
    days: '',
    end_date: '',
    reason: '',
    signer_label: '',
});

const hasFilters = computed(() => Object.values(filters).some((v) => String(v).trim() !== ''));

const clearFilters = () => {
    Object.keys(filters).forEach((key) => (filters[key] = ''));
};

watch(() => props.activeScope, () => clearFilters());

const searchKey = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/ө/g, 'о')
    .replace(/ү/g, 'у')
    .replace(/ё/g, 'е')
    .replace(/й/g, 'и');

const DATE_FIELDS = ['start_date', 'end_date'];

const digitsOnly = (value) => String(value ?? '').replace(/\D+/g, '');

/**
 * Мөрийн дугаар — шинэ мөр үргэлж дээд талд, хамгийн том дугаартай орно.
 *
 * Сервер шинэ мөрийг эхэнд нь буцаадаг тул (id-гаар буурахаар эрэмбэлсэн)
 * дугаарыг эсрэгээр нь тооцоод, хуучин мөрүүдийн дугаар өөрчлөгдөхгүй.
 */
const rowsWithSignerLabel = computed(() => props.rows.map((row, index) => ({
    ...row,
    seq: props.rows.length - index,
    signer_label: props.signers[row.signer] || '',
})));

const matchesFilters = (row) => Object.entries(filters).every(([field, needle]) => {
    const text = String(needle).trim();

    if (text === '') return true;

    if (DATE_FIELDS.includes(field)) {
        return digitsOnly(row[field]).includes(digitsOnly(text));
    }

    return searchKey(row[field]).includes(searchKey(text));
});

const visibleRows = computed(() => (
    hasFilters.value ? rowsWithSignerLabel.value.filter(matchesFilters) : rowsWithSignerLabel.value
));
</script>

<template>
    <AuthenticatedLayout title="Чөлөөний бүртгэл">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Чөлөөний бүртгэл</h2>
                    <p class="ui-subtitle">
                        Албан хаагчдын чөлөөний хуудсыг загварын дагуу бөглөж, A4 цаасанд 6 ширхэгээр харна.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <span>Хэвлэх:</span>
                        <select v-model.number="previewCopies" class="ui-input w-20 py-1.5">
                            <option :value="1">1</option>
                            <option :value="2">2</option>
                            <option :value="4">4</option>
                            <option :value="6">6</option>
                        </select>
                    </label>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-ghost whitespace-nowrap"
                        :class="editMode ? 'ring-2 ring-inset ring-brand-navy-500/40' : ''"
                        @click="toggleEditMode"
                    >
                        {{ editMode ? 'Засварыг дуусгах' : 'Засах' }}
                    </button>
                    <div class="relative">
                        <button
                            type="button"
                            class="ui-btn-ghost whitespace-nowrap"
                            :title="selectedIds.length ? 'Сонгосон мөрийг татах' : 'Энэ табын бүх бүртгэлийг татах'"
                            @click="downloadOpen = ! downloadOpen"
                        >
                            {{ selectedIds.length ? `Татах (${selectedIds.length})` : 'Татах' }}
                        </button>
                        <!-- Гадна дарахад цэс хаагдана. -->
                        <div v-if="downloadOpen" class="fixed inset-0 z-40" @click="downloadOpen = false" />
                        <div
                            v-if="downloadOpen"
                            class="absolute right-0 z-50 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <p class="px-3 py-1.5 text-[11px] text-slate-400">
                                {{ selectedIds.length ? `${selectedIds.length} сонгосон мөр` : 'Бүх бүртгэл' }}
                            </p>
                            <button
                                v-for="option in downloadFormats"
                                :key="option.format"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                                @click="download(option.format)"
                            >
                                {{ option.label }}
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        :disabled="addingRow"
                        @click="addRow"
                    >
                        {{ addingRow ? 'Нэмж байна…' : 'Шинэ нэмэх' }}
                    </button>
                </div>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="activeScope === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchScope(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                    :class="view === 'table'
                        ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                    @click="view = 'table'"
                >
                    Хүснэгт
                </button>
                <button
                    type="button"
                    class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                    :class="view === 'sheet'
                        ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                    @click="view = 'sheet'"
                >
                    Хуудасны харагдац
                </button>
            </div>

            <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-slate-500">
                {{ emptyMessage }}
            </div>

            <!-- Бүртгэлийн хүснэгт — .ui-register стандарт систем -->
            <TableScrollViewport v-else-if="view === 'table'" max-height="min(72vh, calc(100dvh - 11rem))">
                <div class="ui-register">
                <div class="ui-register__banner">{{ registerTitle }}</div>
                <table class="ui-register__table min-w-[88rem]">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 3rem" />
                        <col style="width: 18rem" />
                        <col style="width: 10rem" />
                        <col style="width: 8rem" />
                        <col style="width: 6rem" />
                        <col style="width: 5rem" />
                        <col style="width: 6rem" />
                        <col style="width: 14rem" />
                        <col style="width: 10rem" />
                        <col style="width: 4rem" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2">
                                <input
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-500"
                                    :checked="allSelected"
                                    title="Бүгдийг сонгох"
                                    @change="toggleAll"
                                />
                            </th>
                            <th rowspan="2">Д/д</th>
                            <th rowspan="2">Байгууллага /<br>хэлтэс</th>
                            <th rowspan="2">Албан хаагч</th>
                            <th rowspan="2">Төрөл</th>
                            <th colspan="3" class="ui-register__head-group--issued">Чөлөөний хугацаа</th>
                            <th rowspan="2">Үндэслэл</th>
                            <th rowspan="2">Орлон гарын<br>үсэг зурсан</th>
                            <th rowspan="2" />
                        </tr>
                        <tr>
                            <th>Эхлэх</th>
                            <th>Хоног</th>
                            <th>Дуусах</th>
                        </tr>
                        <tr class="ui-filters">
                            <th />
                            <th>
                                <button
                                    v-if="hasFilters"
                                    type="button"
                                    class="w-full text-[10px] font-semibold text-brand-orange-600 hover:underline"
                                    title="Хайлтыг цэвэрлэх"
                                    @click="clearFilters"
                                >
                                    Цэвэрлэх
                                </button>
                                <span v-else class="text-[10px] text-slate-300">Хайх</span>
                            </th>
                            <th><input v-model="filters.org_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.person_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.type_label" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.start_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.days" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.end_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.reason" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.signer_label" type="search" placeholder="Хайх" /></th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in visibleRows" :key="row.id">
                            <td class="text-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-500"
                                    :checked="isSelected(row.id)"
                                    @change="toggleRow(row.id)"
                                />
                            </td>
                            <td class="ui-register__cell--no">{{ row.seq }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].org_name"
                                    :options="orgOptions"
                                    :editable="rowEditable"
                                    empty-label=""
                                    placeholder="Байгууллага…"
                                    @commit="(v) => saveField(row.id, 'org_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :options="peopleOptions"
                                    :editable="rowEditable"
                                    empty-label=""
                                    placeholder="Овог нэр…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td class="px-1.5 py-1.5">
                                <select
                                    v-if="rowEditable && drafts[row.id]"
                                    v-model="drafts[row.id].type"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'type', drafts[row.id].type)"
                                >
                                    <option v-for="[value, label] in typeEntries" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <span v-else class="ui-clamp-2">{{ row.type_label }}</span>
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].start_date"
                                    type="date"
                                    align="center"
                                    :editable="rowEditable"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'start_date', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].days"
                                    type="number"
                                    align="center"
                                    :editable="rowEditable"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'days', v)"
                                />
                            </td>
                            <td class="leave-table__date px-1.5 py-1.5 text-center">{{ row.end_date || '—' }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].reason"
                                    multiline
                                    :editable="rowEditable"
                                    empty-label=""
                                    placeholder="Үндэслэл…"
                                    @commit="(v) => saveField(row.id, 'reason', v)"
                                />
                            </td>
                            <td class="px-1.5 py-1.5">
                                <select
                                    v-if="rowEditable && drafts[row.id]"
                                    v-model="drafts[row.id].signer"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'signer', drafts[row.id].signer)"
                                >
                                    <option v-for="[value, label] in signerEntries" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <span v-else class="ui-clamp-2">{{ row.signer_label }}</span>
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a
                                        :href="slipPrintUrl(row)"
                                        target="_blank"
                                        class="ui-icon-btn"
                                        title="Хэвлэх"
                                        aria-label="Хэвлэх"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8V4h10v4M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 15h10v5H7z" />
                                        </svg>
                                    </a>
                                    <button
                                        v-if="rowEditable"
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        title="Устгах"
                                        aria-label="Устгах"
                                        @click="destroyRow(row.id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-9 0 1 12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-12M10 11v6M14 11v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!visibleRows.length">
                            <td colspan="11" class="ui-register__empty">
                                <template v-if="hasFilters">Хайлтад тохирох бүртгэл олдсонгүй.</template>
                                <template v-else>{{ emptyMessage }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>

            <div v-else class="space-y-8">
                <section
                    v-for="(pageRows, pageIndex) in pages"
                    :key="pageIndex"
                    class="leave-a4 mx-auto overflow-hidden bg-white shadow-soft"
                >
                    <div class="leave-a4-grid">
                        <article
                            v-for="row in pageRows"
                            :key="row.id"
                            class="leave-slip group relative"
                        >
                            <h3 class="leave-slip-title">ЧӨЛӨӨНИЙ ХУУДАС</h3>
                            <p class="leave-slip-no">
                                № <span class="leave-blank">{{ row.slip_number || row.id }}</span>
                            </p>
                            <p class="leave-slip-body">
                                Аймгийн ЗДТГ-ын
                                <span class="leave-blank">{{ row.unit || unitFromOrg(row.org_name) }}</span>
                                хэлтсийн мэргэжилтэн
                                <span class="leave-blank">{{ row.person_name }}</span>
                                нь
                                <span class="leave-blank">{{ row.reason || '………………' }}</span>
                                үндэслэлээр
                                {{ row.year || '____' }} оны
                                <span class="leave-blank">{{ row.month || '…' }}</span>
                                сарын
                                <span class="leave-blank">{{ row.day || '…' }}</span>-ны өдрөөс
                                ажлын
                                <span class="leave-blank">{{ row.days || '…' }}</span>
                                өдрийн чөлөө /
                                <span :class="{ 'underline decoration-black': row.type === 'tsalintai' }">{{ kindLabel('tsalintai') }}</span>,
                                <span :class="{ 'underline decoration-black': row.type === 'tsalingui' }">{{ kindLabel('tsalingui') }}</span>,
                                <span :class="{ 'underline decoration-black': row.type === 'eeljiin' }">{{ kindLabel('eeljiin') }}</span>
                                / олгов.
                            </p>
                            <div class="leave-slip-sign">
                                <span class="leave-sign-title">
                                    <template v-if="row.signer === 'head'">Хэлтсийн дарга</template>
                                    <template v-else>Даргын албан үүргийг түр орлон гүйцэтгэгч</template>
                                </span>
                                <span class="leave-sign-name">
                                    <template v-if="row.signer === 'head'">/ &nbsp;&nbsp;&nbsp;&nbsp; /</template>
                                    <template v-else>{{ actingName }}</template>
                                </span>
                            </div>

                            <div class="absolute right-1 top-1 flex gap-1 opacity-0 transition group-hover:opacity-100">
                                <a
                                    :href="slipPrintUrl(row)"
                                    target="_blank"
                                    class="rounded bg-brand-navy-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                >
                                    Хэвлэх
                                </a>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="rounded bg-rose-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                    @click="destroyRow(row.id)"
                                >
                                    Устгах
                                </button>
                            </div>
                        </article>
                    </div>
                    <p class="border-t border-slate-100 px-3 py-1.5 text-center text-[11px] text-slate-400">
                        A4 · хуудас {{ pageIndex + 1 }} · {{ pageRows.length }}/6
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.leave-table__date {
    font-variant-numeric: tabular-nums;
}

/* A4 харьцаа — дэлгэц дээр 6 ширхэг (2×3) багтана. */
.leave-a4 {
    width: min(100%, 210mm);
    aspect-ratio: 210 / 297;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
}

.leave-a4-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: repeat(3, 1fr);
    gap: 2.5mm 3mm;
    padding: 8mm 7mm 4mm;
    min-height: 0;
}

.leave-slip {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    padding: 1mm 0.5mm;
    font-family: Arial, "Times New Roman", sans-serif;
    color: #0f172a;
}

.leave-slip-title {
    margin: 0;
    text-align: center;
    font-size: clamp(8px, 1.05vw, 11px);
    font-weight: 700;
    letter-spacing: 0.02em;
}

.leave-slip-no {
    margin: 0.6mm 0 1.2mm;
    text-align: center;
    font-size: clamp(7px, 0.95vw, 10px);
}

.leave-slip-body {
    margin: 0;
    flex: 1;
    text-align: justify;
    font-size: clamp(6.5px, 0.85vw, 9.5px);
    line-height: 1.45;
}

.leave-blank {
    display: inline;
    border-bottom: 1px dotted #334155;
    padding: 0 1px;
}

.leave-slip-sign {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 2mm;
    margin-top: 1.5mm;
    font-size: clamp(6px, 0.8vw, 9px);
    line-height: 1.2;
    text-transform: uppercase;
}

.leave-sign-title {
    max-width: 62%;
}

.leave-sign-name {
    text-transform: none;
    white-space: nowrap;
}

@media (max-width: 640px) {
    .leave-a4 {
        aspect-ratio: auto;
        min-height: 520px;
    }

    .leave-a4-grid {
        grid-template-columns: 1fr;
        grid-template-rows: none;
        gap: 8px;
    }
}
</style>
