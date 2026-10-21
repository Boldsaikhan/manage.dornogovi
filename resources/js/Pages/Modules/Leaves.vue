<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import SheetCell from '@/Components/SheetCell.vue';
import LeaveRulePicker from '@/Components/LeaveRulePicker.vue';
import { useStickyTableHead } from '@/composables/useStickyTableHead';

const props = defineProps({
    activeScope: { type: String, default: 'baiguullaga' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    scopes: { type: Object, default: () => ({}) },
    types: { type: Object, default: () => ({}) },
    signers: { type: Object, default: () => ({}) },
    limits: { type: Array, default: () => [] },
    limitPayOptions: { type: Object, default: () => ({}) },
    limitPeriodOptions: { type: Object, default: () => ({}) },
    rules: { type: Array, default: () => [] },
    paidDaysPerYear: { type: Number, default: 0 },
    hasAuditLog: { type: Boolean, default: false },
});

/*
 * Чөлөө олгох лимит (журмын 5.4) — тохиргооны цонхонд засна.
 *
 * Хуулбарыг нь засаж байгаад «Хадгалах» дарж илгээнэ, болихоор
 * сервер дээрх жагсаалт хэвээр үлдэнэ.
 */
const showLimits = ref(false);
const limitRows = ref([]);
const limitSaving = ref(false);

const payEntries = computed(() => Object.entries(props.limitPayOptions));
const periodEntries = computed(() => Object.entries(props.limitPeriodOptions));

/** Журмын нэрийг кодоор нь олох. */
const ruleLabel = (code) => props.rules.find((r) => r.code === code)?.label || '';

const openLimits = () => {
    limitRows.value = props.limits.map((row) => ({ ...row }));
    showLimits.value = true;
};

const addLimitRow = () => {
    limitRows.value.push({ code: '', label: '', period: 'year', days: 0, pay: 'tsalintai' });
};

const removeLimitRow = (index) => {
    limitRows.value.splice(index, 1);
};

const saveLimits = () => {
    limitSaving.value = true;

    router.patch(route('leaves.limits.update'), { limits: limitRows.value }, {
        preserveScroll: true,
        onSuccess: () => {
            showLimits.value = false;
        },
        onFinish: () => {
            limitSaving.value = false;
        },
    });
};

const resetLimits = () => {
    if (! confirm('Журмын анхны лимит рүү буцаах уу? Хийсэн өөрчлөлт устана.')) {
        return;
    }

    limitSaving.value = true;

    router.patch(route('leaves.limits.update'), { reset: true }, {
        preserveScroll: true,
        onSuccess: () => {
            limitRows.value = props.limits.map((row) => ({ ...row }));
        },
        onFinish: () => {
            limitSaving.value = false;
        },
    });
};

const previewCopies = ref(6);

/*
 * Толгой гурван мөртэй (гарчиг, хугацааны дэд гарчиг, хайлт) тул
 * наалдах байрлалыг бодит өндрөөр нь тооцно.
 */
const registerEl = ref(null);
const { schedule: scheduleStickySync } = useStickyTableHead(() => registerEl.value);
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

/**
 * Засварлах мөрүүд.
 *
 * Бүх хүснэгтийг нэг товчоор нээхийн оронд мөр бүрийг тусад нь
 * засварлана — санамсаргүй өөрчлөх эрсдэл багасна.
 */
const editingRows = ref([]);

const isEditing = (id) => props.canManage && editingRows.value.includes(id);

const toggleRowEdit = (id) => {
    if (! props.canManage) return;

    editingRows.value = isEditing(id)
        ? editingRows.value.filter((value) => value !== id)
        : [...editingRows.value, id];
};

const addingRow = ref(false);

const addRow = () => {
    if (! props.canManage || addingRow.value) return;

    addingRow.value = true;

    const before = props.rows.map((row) => row.id);

    router.post(route('leaves.store'), {
        scope: props.activeScope === 'all' ? 'baiguullaga' : props.activeScope,
    }, {
        preserveScroll: true,
        // Зөвхөн шинээр нэмсэн мөр засварлах горимд нээгдэнэ.
        onSuccess: () => {
            const added = props.rows.find((row) => ! before.includes(row.id));

            if (added) {
                editingRows.value = [added.id];
            }
        },
        onFinish: () => { addingRow.value = false; },
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('leaves.destroy', id), { preserveScroll: true });
};

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

watch(() => props.activeScope, () => {
    selectedIds.value = [];
    editingRows.value = [];
});

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
            route('leaves.logs', { scope: props.activeScope }),
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
 * хадгална.
 */
const DRAFT_FIELDS = ['org_name', 'unit_name', 'person_name', 'type', 'rule_code', 'start_date', 'days', 'reason', 'signer'];

const drafts = reactive({});

const buildDraft = (row) => Object.fromEntries(DRAFT_FIELDS.map((f) => [f, row[f] ?? '']));

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    props.rows.forEach((row) => {
        drafts[row.id] = buildDraft(row);
    });
};

watch(() => props.rows, syncDrafts, { immediate: true });

/** Лимит зөрчсөн үед гарах сануулга. */
const saveError = ref('');

const saveField = (id, field, value) => {
    let next = value;

    if (field === 'days') {
        const n = Number.parseInt(next, 10);
        next = Number.isNaN(n) ? 1 : Math.min(365, Math.max(1, n));
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next;
    }

    const previous = drafts[id] ? drafts[id][field] : '';

    if (drafts[id]) {
        drafts[id][field] = next ?? '';
    }

    saveError.value = '';

    router.patch(
        route('leaves.update', id),
        { [field]: next },
        {
            preserveScroll: true,
            preserveState: true,
            /*
             * Лимит хэтэрвэл сервер хадгалахгүй — хүснэгтэд хуучин утгыг
             * нь буцааж тавьж, шалтгааныг нь харуулна.
             */
            onError: (errors) => {
                if (drafts[id]) {
                    drafts[id][field] = previous;
                }

                saveError.value = Object.values(errors ?? {})[0] || 'Хадгалж чадсангүй.';
            },
        },
    );
};

const slipPrintUrl = (row) => {
    const base = row.slip_url || route('leaves.slip', row.id);
    const params = new URLSearchParams({ copies: String(previewCopies.value) });
    return `${base}?${params.toString()}`;
};

const kindLabel = (key) => ({
    tsalintai: 'цалинтай',
    tsalingui: 'цалингүй',
    eeljiin: 'ээлжийн амралтаас',
}[key] || key);

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
    unit_name: '',
    rule_label: '',
    person_name: '',
    type_label: '',
    start_date: '',
    days: '',
    end_date: '',
    reason: '',
    signer_label: '',
});

const hasFilters = computed(() => Object.values(filters).some((v) => String(v).trim() !== ''));

// Мөр нэмэгдэх, таб солигдох, шүүхэд толгойн өндөр өөрчлөгдөж болно.
watch(() => [props.rows.length, props.activeScope, hasFilters.value], scheduleStickySync);

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
    rule_label: ruleLabel(row.rule_code),
})));

const matchesFilters = (row) => Object.entries(filters).every(([field, needle]) => {
    const text = String(needle).trim();

    if (text === '') return true;

    if (DATE_FIELDS.includes(field)) {
        return digitsOnly(row[field]).includes(digitsOnly(text));
    }

    return searchKey(row[field]).includes(searchKey(text));
});

/**
 * Эхлэх огноогоор эрэмбэлэх.
 *
 * Толгой дээр дарах бүрд: буурах → өсөх → эрэмбэлэхгүй (бүртгэсэн
 * дарааллаар) гэж сэлгэнэ.
 */
const sortDir = ref(null); // null | 'desc' | 'asc'

const toggleStartSort = () => {
    sortDir.value = sortDir.value === null
        ? 'desc'
        : (sortDir.value === 'desc' ? 'asc' : null);
};

const visibleRows = computed(() => {
    const list = hasFilters.value
        ? rowsWithSignerLabel.value.filter(matchesFilters)
        : rowsWithSignerLabel.value;

    if (! sortDir.value) {
        return list;
    }

    const factor = sortDir.value === 'asc' ? 1 : -1;

    // Огноогүй мөрийг үргэлж төгсгөлд нь үлдээнэ.
    return [...list].sort((a, b) => {
        const left = a.start_date || '';
        const right = b.start_date || '';

        if (left === right) return 0;
        if (! left) return 1;
        if (! right) return -1;

        return left < right ? -factor : factor;
    });
});
</script>

<template>
    <AuthenticatedLayout title="Чөлөөний бүртгэл">
        <div class="ui-page">
            <!-- Лимит зөрчсөн үеийн сануулга -->
            <div
                v-if="saveError"
                class="flex items-start justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800"
            >
                <span>{{ saveError }}</span>
                <button type="button" class="shrink-0 text-rose-500 hover:text-rose-700" @click="saveError = ''">✕</button>
            </div>

            <!-- Таб ба үйлдлүүд нэг мөрөнд -->
            <div class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <nav class="flex min-w-0 flex-1 gap-1.5 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold transition"
                    :class="activeScope === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchScope(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
                </nav>

                <div class="flex shrink-0 items-center gap-1 border-l border-slate-200 pl-2">
                    <!-- Хэвлэхэд нэг хуудсанд багтах тоо -->
                    <label class="flex items-center gap-1 pr-1" title="Нэг A4 хуудсанд багтаах хуудасны тоо">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8V4h10v4M7 18H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 15h10v5H7z" />
                        </svg>
                        <select v-model.number="previewCopies" class="ui-input w-14 !py-1 !px-1 text-xs">
                            <option :value="1">1</option>
                            <option :value="2">2</option>
                            <option :value="4">4</option>
                            <option :value="6">6</option>
                        </select>
                    </label>

                    <button
                        type="button"
                        class="ui-icon-btn"
                        title="Чөлөөний тохиргоо — журмын дагуух лимит"
                        aria-label="Чөлөөний тохиргоо"
                        @click="openLimits"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0" />
                            <circle cx="16" cy="6" r="2" />
                            <circle cx="10" cy="12" r="2" />
                            <circle cx="18" cy="18" r="2" />
                        </svg>
                    </button>

                    <button
                        v-if="hasAuditLog"
                        type="button"
                        class="ui-icon-btn"
                        title="Түүх — хэн, хэзээ, юуг өөрчилсөн"
                        aria-label="Түүх"
                        @click="openLogs"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2M3.5 12a8.5 8.5 0 1 0 2.2-5.7M3 4v4h4" />
                        </svg>
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            class="ui-icon-btn relative"
                            :title="selectedIds.length ? 'Сонгосон мөрийг татах' : 'Энэ табын бүх бүртгэлийг татах'"
                            aria-label="Татах"
                            @click="downloadOpen = ! downloadOpen"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1" />
                            </svg>
                            <span
                                v-if="selectedIds.length"
                                class="absolute -right-1 -top-1 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand-navy-600 px-1 text-[10px] font-bold text-white"
                            >
                                {{ selectedIds.length }}
                            </span>
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
                        class="ui-icon-btn bg-brand-orange-500 text-white hover:bg-brand-orange-600"
                        :disabled="addingRow"
                        :title="addingRow ? 'Нэмж байна…' : 'Шинэ мөр нэмэх'"
                        aria-label="Нэмэх"
                        @click="addRow"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                </div>
            </div>

            <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-slate-500">
                {{ emptyMessage }}
            </div>

            <!-- Бүртгэлийн хүснэгт — .ui-register стандарт систем -->
            <TableScrollViewport v-else max-height="min(72vh, calc(100dvh - 11rem))">
                <div ref="registerEl" class="ui-register">
                <table class="ui-register__table ui-register--pin-actions min-w-[88rem]">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 3rem" />
                        <col style="width: 13rem" />
                        <col style="width: 12rem" />
                        <col style="width: 10rem" />
                        <col style="width: 8rem" />
                        <col style="width: 14rem" />
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
                            <th rowspan="2">Байгууллага</th>
                            <th rowspan="2">Хэлтэс</th>
                            <th rowspan="2">Албан хаагч</th>
                            <th rowspan="2">Төрөл</th>
                            <th rowspan="2">Журам</th>
                            <th colspan="3" class="ui-register__head-group--issued">Чөлөөний хугацаа</th>
                            <th rowspan="2">Үндэслэл</th>
                            <th rowspan="2">Орлон гарын<br>үсэг зурсан</th>
                            <th rowspan="2" class="ui-sticky-actions" />
                        </tr>
                        <tr>
                            <th class="p-0">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-1 px-2 py-1.5 transition hover:text-brand-navy-700"
                                    :title="sortDir === 'desc'
                                        ? 'Шинээс хуучин руу — дарж эргүүлнэ'
                                        : (sortDir === 'asc' ? 'Хуучнаас шинэ рүү — дарж болино' : 'Эхлэх огноогоор эрэмбэлэх')"
                                    @click="toggleStartSort"
                                >
                                    Эхлэх
                                    <svg
                                        class="h-3 w-3 shrink-0 transition"
                                        :class="sortDir ? 'text-brand-navy-600' : 'text-slate-300'"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.2"
                                        viewBox="0 0 24 24"
                                    >
                                        <path v-if="sortDir === 'asc'" d="m6 15 6-6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                                        <path v-else-if="sortDir === 'desc'" d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                                        <path v-else d="M8 9l4-4 4 4M8 15l4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </th>
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
                            <th><input v-model="filters.unit_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.person_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.type_label" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.rule_label" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.start_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.days" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.end_date" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.reason" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.signer_label" type="search" placeholder="Хайх" /></th>
                            <th class="ui-sticky-actions" />
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
                            <!-- Байгууллага, хэлтэс нь албан хаагчаас хамаарна. -->
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].org_name"
                                    :options="orgOptions"
                                    :editable="isEditing(row.id)"
                                    empty-label=""
                                    placeholder="Байгууллага…"
                                    @commit="(v) => saveField(row.id, 'org_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].unit_name"
                                    :editable="isEditing(row.id)"
                                    empty-label=""
                                    placeholder="Хэлтэс…"
                                    @commit="(v) => saveField(row.id, 'unit_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :options="peopleOptions"
                                    :editable="isEditing(row.id)"
                                    empty-label=""
                                    placeholder="Овог нэр…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td class="px-1.5 py-1.5">
                                <!--
                                    Жилийн цалинтай хоног дууссан: «Цалинтай» сонголт
                                    идэвхгүй, «Цалингүй» эсвэл «Ээлжийн амралтаас»-аас сонгоно.
                                -->
                                <select
                                    v-if="(row.paid_blocked || row.limit_exceeded) && isEditing(row.id) && drafts[row.id]"
                                    v-model="drafts[row.id].type"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    title="Сонгосон журмын заалтын хоног дууссан тул цалинтай байх боломжгүй."
                                    @change="saveField(row.id, 'type', drafts[row.id].type)"
                                >
                                    <option
                                        v-for="[value, label] in typeEntries"
                                        :key="value"
                                        :value="value"
                                        :disabled="value === 'tsalintai'"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <span
                                    v-else-if="row.paid_blocked || row.limit_exceeded"
                                    class="ui-clamp-2 text-[11px]"
                                    title="Сонгосон журмын заалтын хоног дууссан тул цалинтай байх боломжгүй."
                                >
                                    {{ row.type_label }}
                                </span>
                                <!-- Журам цалингийн төлвөө заасан бол гараар солихгүй. -->
                                <span
                                    v-else-if="row.type_locked"
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-700"
                                    title="Сонгосон журмын заалт цалингийн төлвийг нь тогтоосон."
                                >
                                    {{ row.type_label }}
                                    <svg class="h-3 w-3 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M7 11V8a5 5 0 0110 0v3" stroke-linecap="round" />
                                        <rect x="5" y="11" width="14" height="9" rx="2" />
                                    </svg>
                                </span>
                                <select
                                    v-else-if="isEditing(row.id) && drafts[row.id]"
                                    v-model="drafts[row.id].type"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'type', drafts[row.id].type)"
                                >
                                    <option v-for="[value, label] in typeEntries" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <span v-else class="ui-clamp-2">{{ row.type_label }}</span>
                            </td>
                            <!-- Журмын заалт — хүснэгтээр сонгоно. -->
                            <td class="px-1.5 py-1.5">
                                <LeaveRulePicker
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].rule_code"
                                    :rules="rules"
                                    :usage="row.rule_usage || {}"
                                    :blocked="row.blocked_rules || []"
                                    :row-days="row.days || 0"
                                    :editable="isEditing(row.id)"
                                    @commit="(v) => saveField(row.id, 'rule_code', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].start_date"
                                    type="date"
                                    align="center"
                                    :editable="isEditing(row.id)"
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
                                    :editable="isEditing(row.id)"
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
                                    :editable="isEditing(row.id)"
                                    empty-label=""
                                    placeholder="Үндэслэл…"
                                    @commit="(v) => saveField(row.id, 'reason', v)"
                                />
                            </td>
                            <td class="px-1.5 py-1.5">
                                <select
                                    v-if="isEditing(row.id) && drafts[row.id]"
                                    v-model="drafts[row.id].signer"
                                    class="w-full border-0 bg-transparent text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'signer', drafts[row.id].signer)"
                                >
                                    <option value="">— сонгох —</option>
                                    <option v-for="[value, label] in signerEntries" :key="value" :value="value">{{ label }}</option>
                                </select>
                                <span v-else class="ui-clamp-2">{{ row.signer_label }}</span>
                            </td>
                            <td class="ui-sticky-actions text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <!-- Мөрийг тусад нь засварлана. -->
                                    <button
                                        v-if="canManage"
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
                                        v-if="isEditing(row.id)"
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

        </div>

        <!-- Өөрчлөлтийн түүх -->
        <!-- Чөлөө олгох лимитийн тохиргоо (журмын 5.4) -->
        <Modal :show="showLimits" max-width="5xl" @close="showLimits = false">
            <div class="ui-card-pad space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="ui-title text-base">Чөлөө олгох лимит</h3>
                        <p class="ui-subtitle">
                            Хөдөлмөрийн дотоод журмын 5.4-р заалт. Журам шинэчлэгдвэл энд засна.
                        </p>
                    </div>
                    <button type="button" class="ui-btn-ghost" @click="showLimits = false">Хаах</button>
                </div>

                <div class="ui-table-wrap max-h-[60vh] overflow-y-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-20">№</th>
                                <th>Чөлөөний төрөл</th>
                                <th class="w-36">Давтамж</th>
                                <th class="w-28">Хоног</th>
                                <th class="w-32">Цалин</th>
                                <th v-if="canManage" class="w-12" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in limitRows" :key="'limit-' + index">
                                <td>
                                    <input
                                        v-model="row.code"
                                        type="text"
                                        class="ui-input w-full !py-1 text-sm"
                                        :disabled="! canManage"
                                        placeholder="5.4.x"
                                    />
                                </td>
                                <td>
                                    <textarea
                                        v-model="row.label"
                                        rows="2"
                                        class="ui-input w-full !py-1 text-sm"
                                        :disabled="! canManage"
                                        placeholder="Чөлөөний төрөл"
                                    />
                                </td>
                                <td>
                                    <select
                                        v-model="row.period"
                                        class="ui-input w-full !py-1 text-sm"
                                        :disabled="! canManage"
                                    >
                                        <option v-for="[value, label] in periodEntries" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </td>
                                <td>
                                    <input
                                        v-model.number="row.days"
                                        type="number"
                                        min="0"
                                        max="365"
                                        class="ui-input w-full !py-1 text-sm"
                                        :disabled="! canManage"
                                        title="0 = хоног заагаагүй"
                                    />
                                </td>
                                <td>
                                    <select
                                        v-model="row.pay"
                                        class="ui-input w-full !py-1 text-sm"
                                        :disabled="! canManage"
                                    >
                                        <option v-for="[value, label] in payEntries" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                </td>
                                <td v-if="canManage" class="text-center">
                                    <button
                                        type="button"
                                        class="ui-icon-btn ui-icon-btn--danger"
                                        title="Мөр устгах"
                                        @click="removeLimitRow(index)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path d="M6 7h12M9 7V5h6v2m-7 0 1 12h6l1-12" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="! limitRows.length">
                                <td :colspan="canManage ? 6 : 5" class="!py-10 text-center text-slate-400">
                                    Лимит тохируулаагүй байна.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="canManage" class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-ghost" @click="addLimitRow">+ Мөр нэмэх</button>
                        <button type="button" class="ui-btn-ghost" :disabled="limitSaving" @click="resetLimits">
                            Журмын анхны жагсаалт
                        </button>
                    </div>
                    <button type="button" class="ui-btn-accent" :disabled="limitSaving" @click="saveLimits">
                        {{ limitSaving ? 'Хадгалж байна…' : 'Хадгалах' }}
                    </button>
                </div>
            </div>
        </Modal>

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
