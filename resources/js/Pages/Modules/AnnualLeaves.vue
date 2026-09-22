<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    activeScope: { type: String, default: 'baiguullaga' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    scopes: { type: Object, default: () => ({}) },
    signers: { type: Object, default: () => ({}) },
    hasAuditLog: { type: Boolean, default: false },
});

const signerEntries = computed(() => Object.entries(props.signers));

const cellClass = 'ui-register__cell';

/** Албан хаагчдын сонголт — утасны жагсаалтын бүх хүн, байгууллагатай нь. */
const peopleOptions = computed(() => props.directory.flatMap((d) => (d.people ?? []).map((p) => ({
    value: p.name,
    label: p.name,
    hint: p.position || '',
    org: d.org_name,
    category: d.category,
}))));

const switchScope = (value) => {
    router.get(route('annual-leaves.index'), { scope: value }, { preserveState: false, preserveScroll: true });
};

const addingRow = ref(false);

const addRow = () => {
    if (! props.canManage || addingRow.value) return;

    addingRow.value = true;

    router.post(route('annual-leaves.store'), {
        scope: props.activeScope === 'all' ? 'baiguullaga' : props.activeScope,
    }, {
        preserveScroll: true,
        onFinish: () => { addingRow.value = false; },
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('annual-leaves.destroy', id), { preserveScroll: true });
};

const noticeUrl = (row) => route('annual-leaves.notice', row.id);

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
    const url = new URL(route('annual-leaves.export'), window.location.origin);
    url.searchParams.set('format', format);
    url.searchParams.set('scope', props.activeScope);

    // Сонгоогүй бол идэвхтэй табын бүх мөрийг татна.
    if (selectedIds.value.length) {
        url.searchParams.set('ids', selectedIds.value.join(','));
    }

    downloadOpen.value = false;
    window.location.href = url.toString();
};

/*
 * Өөрчлөлтийн түүх — хэн, хэзээ, юуг сольсныг харуулна.
 */
const showLogs = ref(false);
const logsBusy = ref(false);
const logRows = ref([]);

const openLogs = async () => {
    showLogs.value = true;
    logsBusy.value = true;

    try {
        const { data } = await window.axios.get(
            route('annual-leaves.logs', { scope: props.activeScope }),
        );
        logRows.value = data.rows ?? [];
    } catch (error) {
        logRows.value = [];
    } finally {
        logsBusy.value = false;
    }
};

const changeList = (changes) => Object.entries(changes ?? {});

const logTone = (action) => ({
    created: 'bg-emerald-50 text-emerald-700',
    imported: 'bg-sky-50 text-sky-700',
    updated: 'bg-amber-50 text-amber-700',
    deleted: 'bg-rose-50 text-rose-700',
}[action] ?? 'bg-slate-100 text-slate-600');

/**
 * Нүд бүрийн засварлах утга — хүснэгтэд шууд бөглөхөд ашиглана.
 *
 * Мөр бүр серверт бодит бичлэгтэй тул «Мөр нэмэх» дарахад шууд хоосон мөр
 * үүсээд, дараа нь нүд бүрийг {@see saveField} горимоор нэг нэгээр нь
 * хадгална. «Байгууллага», «Албан тушаал», «Орлох албан тушаал» —
 * сонгосон нэрнээс, «Дуусах огноо» нь эхлэх огноо, олгох хоногоос дагаж
 * бөглөгддөг тул энд байхгүй.
 */
const DRAFT_FIELDS = [
    'person_name', 'work_years', 'entitled_days', 'start_date', 'signer',
    'substitute_name', 'substitute_phone',
];

const drafts = reactive({});

const buildDraft = (row) => Object.fromEntries(DRAFT_FIELDS.map((f) => [f, row[f] ?? '']));

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    props.rows.forEach((row) => {
        drafts[row.id] = buildDraft(row);
    });
};

watch(() => props.rows, syncDrafts, { immediate: true });

const NUMBER_FIELDS = ['work_years', 'entitled_days'];

const saveField = (id, field, value) => {
    let next = value;

    if (NUMBER_FIELDS.includes(field)) {
        const n = Number.parseInt(next, 10);
        next = Number.isNaN(n) ? null : n;
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next;
    }

    if (drafts[id]) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('annual-leaves.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const emptyMessage = computed(() => {
    const map = {
        all: 'Бүртгэл алга',
        agentlag: 'Агентлагийн бүртгэл алга',
        sum: 'Сумын бүртгэл алга',
        baiguullaga: 'Байгууллагын бүртгэл алга',
    };
    return map[props.activeScope] || 'Бүртгэл алга';
});

const registerTitle = computed(() => {
    const label = props.tabs.find((t) => t.value === props.activeScope)?.label;
    return label ? `${label} — ээлжийн амралтын бүртгэл` : 'Ээлжийн амралтын бүртгэл';
});

/**
 * Багана тус бүрийн хайлт.
 *
 * Хүснэгтийн толгойн доор жижиг талбар гарч, бичсэн үгээр нь мөрүүдийг
 * шүүнэ. Хэд хэдэн баганад зэрэг бичвэл бүгдэд нь тохирсон мөр л үлдэнэ.
 */
const filters = reactive({
    registered_on: '',
    org_name: '',
    position: '',
    person_name: '',
    work_years: '',
    entitled_days: '',
    start_date: '',
    end_date: '',
    signer: '',
    substitute_position: '',
    substitute_name: '',
    substitute_phone: '',
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

const DATE_FIELDS = ['registered_on', 'start_date', 'end_date'];

const digitsOnly = (value) => String(value ?? '').replace(/\D+/g, '');

/**
 * Мөрийн дугаар — шинэ мөр үргэлж дээд талд, хамгийн том дугаартай орно.
 *
 * Сервер шинэ мөрийг эхэнд нь буцаадаг тул (id-гаар буурахаар эрэмбэлсэн)
 * дугаарыг эсрэгээр нь тооцоод, хуучин мөрүүдийн дугаар өөрчлөгдөхгүй.
 */
const rowsWithSeq = computed(() => props.rows.map((row, index) => ({
    ...row,
    seq: props.rows.length - index,
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
    hasFilters.value ? rowsWithSeq.value.filter(matchesFilters) : rowsWithSeq.value
));
</script>

<template>
    <AuthenticatedLayout title="Ээлжийн амралт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Ээлжийн амралт</h2>
                    <p class="ui-subtitle">
                        Албан хаагчдын ээлжийн амралтын хоног, огноо, эзгүй хугацаанд орлох хүнийг бүртгэнэ.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="hasAuditLog"
                        type="button"
                        class="ui-btn-ghost whitespace-nowrap"
                        title="Хэн, хэзээ, юуг өөрчилснийг харах"
                        @click="openLogs"
                    >
                        Түүх
                    </button>
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

            <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-slate-500">
                {{ emptyMessage }}
            </div>

            <!-- Бүртгэлийн хүснэгт — .ui-register стандарт систем -->
            <TableScrollViewport v-else max-height="min(72vh, calc(100dvh - 11rem))">
                <div class="ui-register">
                <div class="ui-register__banner">{{ registerTitle }}</div>
                <table class="ui-register__table min-w-[113rem]">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 3rem" />
                        <col style="width: 7rem" />
                        <col style="width: 14rem" />
                        <col style="width: 10rem" />
                        <col style="width: 10rem" />
                        <col style="width: 6rem" />
                        <col style="width: 7rem" />
                        <col style="width: 6rem" />
                        <col style="width: 6rem" />
                        <col style="width: 10rem" />
                        <col style="width: 10rem" />
                        <col style="width: 10rem" />
                        <col style="width: 7rem" />
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
                            <th rowspan="2">Бүртгэсэн<br>огноо</th>
                            <th rowspan="2">Байгууллага</th>
                            <th rowspan="2">Албан тушаал</th>
                            <th rowspan="2">Овог, нэр</th>
                            <th rowspan="2">Улсад<br>ажилласан жил</th>
                            <th rowspan="2">Ээлжийн амралт<br>олгох хоног</th>
                            <th colspan="2" class="ui-register__head-group--issued">Ээлжийн амралтын</th>
                            <th rowspan="2">Зөвшөөрсөн</th>
                            <th colspan="3" class="ui-register__head-group--numbers">Эзгүй хугацаанд орлох албан тушаалтан</th>
                            <th rowspan="2" />
                        </tr>
                        <tr>
                            <th>Эхлэх огноо</th>
                            <th>Дуусах огноо</th>
                            <th>Албан тушаал</th>
                            <th>Овог, нэр</th>
                            <th>Утасны дугаар</th>
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
                            <th><input v-model="filters.registered_on" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.org_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.position" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.person_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.work_years" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.entitled_days" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.start_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.end_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.signer" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.substitute_position" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.substitute_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.substitute_phone" type="search" placeholder="Хайх" /></th>
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
                            <td class="px-1.5 py-1.5 text-center text-xs tabular-nums text-slate-600">
                                {{ row.registered_on || '—' }}
                            </td>
                            <td class="px-1.5 py-1.5 text-center text-xs text-slate-600">
                                <span class="ui-clamp-2">{{ row.org_name || '—' }}</span>
                            </td>
                            <td class="px-1.5 py-1.5 text-center text-xs text-slate-600">
                                <span class="ui-clamp-2">{{ row.position || '—' }}</span>
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
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].work_years"
                                    type="number"
                                    align="center"
                                    :editable="rowEditable"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'work_years', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].entitled_days"
                                    type="number"
                                    align="center"
                                    :editable="rowEditable"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'entitled_days', v)"
                                />
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
                            <td class="px-1.5 py-1.5 text-center text-xs tabular-nums text-slate-600">
                                {{ row.end_date || '—' }}
                            </td>
                            <td class="px-1.5 py-1.5">
                                <select
                                    v-if="rowEditable && drafts[row.id]"
                                    v-model="drafts[row.id].signer"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'signer', drafts[row.id].signer)"
                                >
                                    <option value="">— сонгох —</option>
                                    <option v-for="[value, label] in signerEntries" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <span v-else class="ui-clamp-2 px-1.5 py-1.5 text-xs text-slate-600">{{ row.signer || '—' }}</span>
                            </td>
                            <td class="px-1.5 py-1.5 text-center text-xs text-slate-600">
                                <span class="ui-clamp-2">{{ row.substitute_position || '—' }}</span>
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].substitute_name"
                                    :options="peopleOptions"
                                    :editable="rowEditable"
                                    empty-label=""
                                    placeholder="Овог нэр…"
                                    @commit="(v) => saveField(row.id, 'substitute_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].substitute_phone"
                                    align="center"
                                    :editable="rowEditable"
                                    empty-label=""
                                    placeholder="Утас…"
                                    @commit="(v) => saveField(row.id, 'substitute_phone', v)"
                                />
                            </td>
                            <td class="text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <a
                                        :href="noticeUrl(row)"
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
                            <td colspan="15" class="ui-register__empty">
                                <template v-if="hasFilters">Хайлтад тохирох бүртгэл олдсонгүй.</template>
                                <template v-else>{{ emptyMessage }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>
        </div>

        <!-- Өөрчлөлтийн түүх -->
        <Modal :show="showLogs" max-width="4xl" @close="showLogs = false">
            <div class="p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Өөрчлөлтийн түүх</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ registerTitle }} — сүүлийн 200 бичлэг.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="showLogs = false">✕</button>
                </div>

                <p v-if="logsBusy" class="py-8 text-center text-sm text-slate-400">Уншиж байна…</p>
                <p v-else-if="! logRows.length" class="py-8 text-center text-sm text-slate-400">
                    Одоогоор бичлэг алга. Энэ түүх нь шинэчлэлт хийгдсэнээс хойших өөрчлөлтийг харуулна.
                </p>
                <div v-else class="max-h-[65vh] overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-2 py-1.5 font-semibold">Огноо</th>
                                <th class="px-2 py-1.5 font-semibold">Үйлдэл</th>
                                <th class="px-2 py-1.5 font-semibold">Мөр</th>
                                <th class="px-2 py-1.5 font-semibold">Юу өөрчлөгдсөн</th>
                                <th class="px-2 py-1.5 font-semibold">Хэн</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in logRows" :key="item.id" class="border-t border-slate-100 align-top">
                                <td class="whitespace-nowrap px-2 py-1.5 tabular-nums text-slate-500">{{ item.at }}</td>
                                <td class="px-2 py-1.5">
                                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="logTone(item.action)">
                                        {{ item.action_label }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-slate-700">{{ item.label || '—' }}</td>
                                <td class="px-2 py-1.5 text-slate-600">
                                    <span v-if="item.summary">{{ item.summary }}</span>
                                    <ul v-else-if="changeList(item.changes).length" class="space-y-0.5">
                                        <li v-for="[field, change] in changeList(item.changes)" :key="field">
                                            <span class="font-medium text-slate-700">{{ field }}:</span>
                                            <span class="text-slate-400 line-through">{{ change.from || '—' }}</span>
                                            →
                                            <span class="text-brand-navy-700">{{ change.to || '—' }}</span>
                                        </li>
                                    </ul>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-slate-700">{{ item.user }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
