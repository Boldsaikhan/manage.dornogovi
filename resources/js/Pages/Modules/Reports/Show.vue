<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';
import ReportSectionTabs from '@/Components/Reports/ReportSectionTabs.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import SheetCell from '@/Components/SheetCell.vue';
import { findReportSection } from '@/utils/reportsNavigation';
import { useReportSubnav } from '@/composables/useReportSubnav';

const props = defineProps({
    title: String,
    period: String,
    report: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    departments: { type: Array, default: () => [] },
    departmentColumn: { type: String, default: 'department' },
});

/* ---------- Мөрийн засвар ---------- */

const savingCell = ref(null);

const cellKey = (row, col) => `${row._index}:${col}`;

const isDepartmentColumn = (key) => key === props.departmentColumn;

/** Нэг нүдийг хадгална. Хэлтэс бол сонгосон хэлтсийн id явна. */
const toggleSpan = (row) => {
    saveCell(row, '_span', row._span ? '0' : '1');
};

const saveCell = (row, column, value) => {
    if (! props.canEdit || row?._index == null) return;

    const key = cellKey(row, column);
    savingCell.value = key;

    const payload = isDepartmentColumn(column)
        ? { column, department_id: value === '' || value == null ? null : Number(value) }
        : { column, value: value ?? '' };

    router.patch(route('reports.rows.update', [props.report.key, row._index]), payload, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            if (savingCell.value === key) savingCell.value = null;
        },
    });
};

const hasColumns = computed(() => (props.report.columns?.length ?? 0) > 0);

const hasRows = computed(() => (props.report.rows?.length ?? 0) > 0);

const activeSectionKey = computed(() => props.report.section_key || null);

const sectionNavItems = computed(() => {
    const section = findReportSection(props.navigation, activeSectionKey.value);

    return section?.children ?? [];
});

const defaultColumnWidths = {
    no: '2.75rem',
    policy_unit: '8rem',
    year: '3.5rem',
    clause: '5rem',
    goal: '9rem',
    activity: '11rem',
    measure: '11rem',
    period: '4.25rem',
    source: '3.25rem',
    budget: '4.5rem',
    indicator: '7rem',
    unit: '3.25rem',
    baseline: '3.25rem',
    target: '3.75rem',
    progress: '4rem',
    percent: '3.25rem',
    frequency: '4.5rem',
    report_to: '5rem',
    department: '4rem',
    agency: '7rem',
    decision_no: '3.5rem',
    clause_no: '3.5rem',
    decision_title: '12rem',
    clause_text: '12rem',
    half_year: '10rem',
    evaluation: '4rem',
    note: '8rem',
};

const remToPx = (value) => {
    const match = /^([\d.]+)rem$/.exec(value);

    return match ? Number(match[1]) * 16 : 80;
};

const columnWidth = (col) => col.width || defaultColumnWidths[col.key] || '5rem';

/**
 * Хүснэгт дэлгэцээс нарийн байвал илүү зайг энэ багана шингээнэ.
 * Бусад багана өргөнөө хадгална тул зүүн талд наалдсан «д/д» зөв байрлана.
 */
const flexColumnKey = computed(() => {
    const keys = (props.report.columns ?? []).map((col) => col.key);

    for (const candidate of ['activity', 'measure', 'goal', 'clause_text', 'note']) {
        if (keys.includes(candidate)) return candidate;
    }

    return keys.at(-1) ?? null;
});

const tableMinWidth = computed(() => props.report.columns.reduce(
    (sum, col) => sum + remToPx(columnWidth(col)),
    0,
));

const isPinnedColumn = (key) => key === 'no';

const isCenteredColumn = (key) => ['no', 'percent', 'count', 'unit', 'baseline', 'target', 'progress'].includes(key);

const nowrapKeys = new Set(['no', 'percent', 'unit']);

const cellClass = (key) => ({
    'text-center': isCenteredColumn(key) && nowrapKeys.has(key),
    'whitespace-pre-line break-words text-xs leading-snug': ! nowrapKeys.has(key),
    'whitespace-nowrap text-xs': nowrapKeys.has(key),
});

const tableExpanded = ref(false);
const subnavOpen = useReportSubnav();
const editMode = ref(false);
const downloadOpen = ref(false);
const pdfOpen = ref(false);

watch(() => props.report.key, () => {
    pdfOpen.value = false;
    closeImport();
});

const togglePdf = () => {
    pdfOpen.value = ! pdfOpen.value;
    if (pdfOpen.value) {
        tableExpanded.value = false;
        downloadOpen.value = false;
    }
};

const tableEditing = computed(() => props.canEdit && editMode.value);

const downloadFormats = [
    { format: 'xlsx', label: 'Excel (.xlsx)' },
    { format: 'docx', label: 'Word (.docx)' },
    { format: 'pdf', label: 'PDF' },
];

const download = (format) => {
    downloadOpen.value = false;
    window.location.href = route('reports.export', { report: props.report.key, format });
};

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);

const importInput = ref(null);
const importBusy = ref(false);
const importData = ref(null);

const pickImportFile = () => importInput.value?.click();

const onImportFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (! file) return;

    importBusy.value = true;

    try {
        const form = new FormData();
        form.append('file', file);

        const { data } = await window.axios.post(
            route('reports.import.preview', props.report.key),
            form,
        );

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
    if (! importData.value) return;

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

const fileSample = computed(() => (importData.value?.rows ?? []).slice(0, 8));

const fileColumnCount = computed(() => {
    const headers = importData.value?.headers ?? [];
    const widest = (importData.value?.rows ?? []).reduce(
        (max, row) => Math.max(max, row?.length ?? 0),
        0,
    );

    return Math.max(headers.length, widest, 1);
});

const fieldForHeader = (index) => {
    if (! importData.value) return '';

    const found = Object.entries(importData.value.mapping).find(([, value]) => (
        value !== null && value !== '' && Number(value) === Number(index)
    ));

    return found ? found[0] : '';
};

const assignFileColumn = (index, field) => {
    if (! importData.value) return;

    const mapping = importData.value.mapping;

    Object.keys(mapping).forEach((key) => {
        if (mapping[key] !== null && mapping[key] !== '' && Number(mapping[key]) === Number(index)) {
            mapping[key] = null;
        }
    });

    if (field) {
        mapping[field] = Number(index);
    }

    remapImport();
};

const closeImport = () => {
    importData.value = null;
};

const confirmImport = () => {
    if (! importData.value || importBusy.value || ! importData.value.total) return;

    importBusy.value = true;

    router.post(route('reports.import.store', props.report.key), {
        entries: importData.value.entries,
    }, {
        preserveScroll: true,
        onSuccess: () => closeImport(),
        onFinish: () => {
            importBusy.value = false;
        },
    });
};

const closeExpanded = () => {
    tableExpanded.value = false;
};

const onKeydown = (event) => {
    if (event.key !== 'Escape') return;
    if (pdfOpen.value) {
        pdfOpen.value = false;
        return;
    }
    if (tableExpanded.value) {
        closeExpanded();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head :title="report.label" />

    <AuthenticatedLayout :title="title">
        <div class="ui-page ui-page--reports-table">
            <div>
                <ReportSectionTabs
                    :sections="navigation"
                    :active-section-key="activeSectionKey"
                    mode="show"
                >
                    <template #trailing>
                        <button
                            type="button"
                            class="ui-icon-btn"
                            :class="subnavOpen ? 'bg-brand-navy-600 text-white' : ''"
                            :title="subnavOpen ? 'Дэд тайланг хураах' : 'Дэд тайлан'"
                            :aria-label="subnavOpen ? 'Дэд тайланг хураах' : 'Дэд тайлан'"
                            :aria-pressed="subnavOpen"
                            @click="subnavOpen = ! subnavOpen"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 12h10M4 19h16" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l3 3-3 3" />
                            </svg>
                        </button>
                        <Link
                            :href="route('reports.index')"
                            class="ui-icon-btn"
                            title="Бүх үзүүлэлт"
                            aria-label="Бүх үзүүлэлт"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5M4 19h16" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16v-4M12 16V8M16 16v-6" />
                            </svg>
                        </Link>
                        <button
                            v-if="report.pdf_url"
                            type="button"
                            class="ui-icon-btn"
                            :class="pdfOpen ? 'bg-brand-navy-600 text-white' : ''"
                            :title="pdfOpen ? 'Хүснэгт харах' : 'Гэрээ харах'"
                            :aria-label="pdfOpen ? 'Хүснэгт харах' : 'Гэрээ харах'"
                            :aria-pressed="pdfOpen"
                            @click="togglePdf"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M8 13h8M8 17h6" />
                            </svg>
                        </button>
                        <button
                            v-if="canEdit && hasColumns"
                            type="button"
                            class="ui-icon-btn"
                            :disabled="importBusy"
                            title="Excel, Word, PDF файлаас оруулах"
                            aria-label="Файлаас оруулах"
                            @click="pickImportFile"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1" />
                            </svg>
                        </button>
                        <button
                            v-if="canEdit && hasColumns"
                            type="button"
                            class="ui-icon-btn"
                            :class="editMode ? 'bg-brand-navy-600 text-white' : ''"
                            :title="editMode ? 'Засварыг дуусгах' : 'Хүснэгтийг засах'"
                            :aria-label="editMode ? 'Засварыг дуусгах' : 'Засах'"
                            :aria-pressed="editMode"
                            @click="editMode = ! editMode"
                        >
                            <svg v-if="editMode" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4l10-10a2.1 2.1 0 1 0-3-3L5 17v3z" />
                            </svg>
                        </button>
                        <div v-if="hasColumns" class="relative">
                            <button
                                type="button"
                                class="ui-icon-btn"
                                title="Excel, Word, PDF-ээр татах"
                                aria-label="Татах"
                                :aria-expanded="downloadOpen"
                                @click="downloadOpen = ! downloadOpen"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0-4-4m4 4 4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1" />
                                </svg>
                            </button>
                            <div v-if="downloadOpen" class="fixed inset-0 z-40" @click="downloadOpen = false" />
                            <div
                                v-if="downloadOpen"
                                class="absolute right-0 z-50 mt-1 w-44 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <p class="px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                    Татах
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
                            v-if="hasColumns"
                            type="button"
                            class="ui-icon-btn"
                            :title="tableExpanded ? 'Хаах' : 'Дэлгэц дүүрэн'"
                            :aria-label="tableExpanded ? 'Хаах' : 'Дэлгэц дүүрэн'"
                            @click="tableExpanded = ! tableExpanded"
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
                                    d="M8 3H4v4M16 3h4v4M4 16v4h4M20 16v4h-4"
                                />
                            </svg>
                        </button>
                    </template>
                </ReportSectionTabs>
            </div>

            <div
                class="ui-reports-table-shell grid min-h-0 gap-4"
                :class="subnavOpen
                    ? 'grid-rows-[minmax(0,11rem)_minmax(0,1fr)] lg:grid-cols-[18rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]'
                    : 'grid-rows-[minmax(0,1fr)]'"
            >
                <aside v-if="subnavOpen" class="ui-card min-h-0 overflow-y-auto p-3">
                    <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Дэд тайлан
                    </p>
                    <nav v-if="sectionNavItems.length">
                        <ReportNavTree :items="sectionNavItems" :active-key="report.key" />
                    </nav>
                </aside>

                <section
                    class="flex min-h-0 flex-col overflow-hidden"
                    :class="tableExpanded ? 'ui-reports-table-overlay' : 'ui-card'"
                >
                    <p v-if="flashSuccess && ! tableExpanded" class="shrink-0 border-b border-emerald-100 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
                        {{ flashSuccess }}
                    </p>
                    <iframe
                        v-if="report.pdf_url && pdfOpen && ! tableExpanded"
                        :src="report.pdf_url"
                        class="min-h-0 w-full flex-1 border-0 bg-white"
                        :title="report.label"
                    />

                    <div
                        v-if="report.description || report.source_file || tableExpanded"
                        class="shrink-0 border-b border-slate-100 px-4 py-3 text-sm text-slate-600"
                    >
                        <p v-if="report.description">{{ report.description }}</p>
                        <p v-if="report.source_file" class="mt-1 text-xs text-slate-500">
                            Эх файл: <strong class="font-medium text-slate-700">{{ report.source_file }}</strong>
                        </p>
                        <p v-if="tableExpanded" class="mt-1 text-xs font-semibold text-brand-navy-700">
                            {{ report.number ? report.number + ' · ' : '' }}{{ report.label }}
                        </p>
                    </div>

                    <TableScrollViewport
                        v-if="hasColumns && ! pdfOpen"
                        fill
                        class="!rounded-none !border-0 !shadow-none"
                        :measure-key="tableMinWidth"
                    >
                        <!-- Багана цөөн үед дэлгэцээ дүүргэж, олон үед хэвтээ гүйнэ. -->
                        <div
                            class="block w-full max-w-none shrink-0"
                            :style="{ minWidth: `${tableMinWidth}px` }"
                        >
                            <table class="ui-table table-fixed w-full text-xs" :style="{ minWidth: `${tableMinWidth}px` }">
                                <colgroup>
                                    <col
                                        v-for="col in report.columns"
                                        :key="`col-${col.key}`"
                                        :style="col.key === flexColumnKey ? undefined : { width: columnWidth(col) }"
                                    />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th
                                            v-for="col in report.columns"
                                            :key="col.key"
                                            class="sticky top-0 z-10 bg-brand-navy-50 px-2 py-2 text-[11px] leading-tight normal-case tracking-normal"
                                            :class="[
                                                isPinnedColumn(col.key) ? 'sticky left-0 z-[11] bg-brand-navy-50 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.12)]' : '',
                                                isCenteredColumn(col.key) ? 'text-center' : 'text-left',
                                            ]"
                                        >
                                            {{ col.label }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!hasRows">
                                        <td :colspan="report.columns.length || 1" class="!py-10 text-center text-sm text-slate-400">
                                            Мэдээлэл оруулаагүй — хүснэгтийн бүтэц бэлэн
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="(row, rowIndex) in report.rows"
                                        :key="row._index ?? rowIndex"
                                        class="align-top"
                                    >
                                        <template v-if="row._span">
                                            <td
                                                :colspan="report.columns.length"
                                                class="bg-brand-navy-50 px-2 py-1.5 text-xs font-semibold leading-snug text-brand-navy-900"
                                            >
                                                <div class="flex items-start gap-2">
                                                    <button
                                                        v-if="tableEditing"
                                                        type="button"
                                                        class="ui-icon-btn mt-0.5"
                                                        title="Нэгтгэлийг салгах"
                                                        aria-label="Нэгтгэлийг салгах"
                                                        @click="toggleSpan(row)"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" d="M4 8h16M4 16h16M12 4v16" />
                                                        </svg>
                                                    </button>
                                                    <span class="min-w-0 flex-1 whitespace-pre-line">{{ row._span_text }}</span>
                                                </div>
                                            </td>
                                        </template>
                                        <template v-else>
                                        <td
                                            v-for="col in report.columns"
                                            :key="col.key"
                                            class="overflow-hidden px-2 py-1.5 text-slate-700"
                                            :class="[
                                                cellClass(col.key),
                                                isPinnedColumn(col.key) ? 'sticky left-0 z-10 bg-inherit font-semibold text-slate-500 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.08)]' : '',
                                            ]"
                                        >
                                            <button
                                                v-if="tableEditing && col.key === 'no'"
                                                type="button"
                                                class="ui-icon-btn mb-1"
                                                title="Мөр нэгтгэх"
                                                aria-label="Мөр нэгтгэх"
                                                @click="toggleSpan(row)"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                                                </svg>
                                            </button>
                                            <!-- Хэлтэс — жинхэнэ хэлтсээс сонгоно (харагдах хүрээг тогтооно) -->
                                            <select
                                                v-if="tableEditing && isDepartmentColumn(col.key)"
                                                class="w-full rounded-md border-slate-200 bg-white py-0.5 text-[11px] text-slate-700"
                                                :value="row.department_id ?? ''"
                                                :disabled="savingCell === cellKey(row, col.key)"
                                                @change="saveCell(row, col.key, $event.target.value)"
                                            >
                                                <option value="">— сонгоогүй —</option>
                                                <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                                                    {{ dept.name }}
                                                </option>
                                            </select>

                                            <SheetCell
                                                v-else-if="tableEditing"
                                                :model-value="row[col.key] ?? ''"
                                                multiline
                                                :clamp="false"
                                                :editable="true"
                                                @commit="(v) => saveCell(row, col.key, v)"
                                            />

                                            <template v-else>{{ row[col.key] ?? '' }}</template>
                                        </td>
                                        </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </TableScrollViewport>

                    <div v-if="!hasColumns" class="border-t border-slate-100 px-4 py-6 text-center text-xs text-slate-500">
                        Template тохируулаагүй байна.
                    </div>
                </section>
            </div>
        </div>

        <input
            ref="importInput"
            type="file"
            accept=".xlsx,.xlsm,.docx,.docm,.pdf"
            class="hidden"
            @change="onImportFile"
        />

        <Modal :show="!! importData" max-width="7xl" @close="closeImport">
            <div v-if="importData" class="p-5">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Файлаас оруулах</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            <b>{{ importData.file_name }}</b> — {{ importData.total }} мөр.
                            Хүснэгтийн толгойг энэ тайлангийн баганатай тааруулаад оруулна.
                            Одоо байгаа мөр хэвээр, шинэ мөр араас нь нэмэгдэнэ.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closeImport">✕</button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[720px] text-left text-xs">
                        <thead>
                            <tr class="bg-brand-navy-50">
                                <th
                                    v-for="index in fileColumnCount"
                                    :key="'map-' + (index - 1)"
                                    class="min-w-[9rem] px-2 py-2 align-bottom font-normal"
                                >
                                    <select
                                        class="ui-input !py-1.5 text-xs"
                                        :value="fieldForHeader(index - 1)"
                                        @change="assignFileColumn(index - 1, $event.target.value)"
                                    >
                                        <option value="">— оруулахгүй —</option>
                                        <option
                                            v-for="(label, field) in importData.fields"
                                            :key="'opt-' + (index - 1) + '-' + field"
                                            :value="field"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                </th>
                            </tr>
                            <tr class="bg-slate-50 text-slate-500">
                                <th
                                    v-for="index in fileColumnCount"
                                    :key="'fh-' + (index - 1)"
                                    class="px-2 py-1.5 font-semibold"
                                >
                                    {{ importData.headers[index - 1] || '(нэргүй багана)' }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in fileSample" :key="'fr-' + i" class="border-t border-slate-100">
                                <td
                                    v-for="index in fileColumnCount"
                                    :key="'fc-' + i + '-' + (index - 1)"
                                    class="px-2 py-1.5 align-top text-slate-700"
                                >
                                    {{ row[index - 1] ?? '' }}
                                </td>
                            </tr>
                            <tr v-if="! fileSample.length">
                                <td :colspan="fileColumnCount" class="px-2 py-6 text-center text-slate-400">
                                    Хүснэгтийн мөр олдсонгүй
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    Эхний {{ fileSample.length }} мөрийг үзүүлэв. Багана бүрийн дээрх жагсаалтаас энэ тайлангийн аль баганад оруулахаа сонгоно. Нэг удаад хамгийн ихдээ 400 мөр орно.
                </p>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeImport">Болих</button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="importBusy || ! importData.total"
                        @click="confirmImport"
                    >
                        {{ importBusy ? 'Оруулж байна…' : `Оруулах (${importData.total})` }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
