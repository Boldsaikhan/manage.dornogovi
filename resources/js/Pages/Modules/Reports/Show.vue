<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';
import ReportSectionTabs from '@/Components/Reports/ReportSectionTabs.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import { findReportSection } from '@/utils/reportsNavigation';

const props = defineProps({
    title: String,
    period: String,
    report: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

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

const textWrapKeys = new Set([
    'policy_unit',
    'clause',
    'goal',
    'activity',
    'measure',
    'indicator',
    'report_to',
    'department',
    'agency',
    'document',
    'goal',
    'clause_text',
    'decision_title',
    'action_plan',
    'decision_title',
    'clause_text',
    'half_year',
    'note',
]);

const remToPx = (value) => {
    const match = /^([\d.]+)rem$/.exec(value);

    return match ? Number(match[1]) * 16 : 80;
};

const columnWidth = (col) => col.width || defaultColumnWidths[col.key] || '5rem';

const tableMinWidth = computed(() => props.report.columns.reduce(
    (sum, col) => sum + remToPx(columnWidth(col)),
    0,
));

const isPinnedColumn = (key) => key === 'no';

const isCenteredColumn = (key) => ['no', 'percent', 'count', 'unit', 'baseline', 'target', 'progress'].includes(key);

const cellClass = (key) => ({
    'text-center': isCenteredColumn(key),
    'break-words text-xs leading-snug': textWrapKeys.has(key),
    'whitespace-nowrap text-xs': ! textWrapKeys.has(key),
});
</script>

<template>
    <Head :title="report.label" />

    <AuthenticatedLayout :title="title">
        <div class="ui-page ui-page--reports-table">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('reports.index', { section: activeSectionKey })" class="text-sm text-brand-navy-600 hover:underline">
                        ← Тайлан мэдээлэл
                    </Link>
                    <h2 class="ui-title mt-1">
                        <span v-if="report.number" class="mr-2 text-slate-500">{{ report.number }}</span>
                        {{ report.label }}
                    </h2>
                    <p v-if="report.section_label" class="ui-subtitle">
                        {{ report.section_label }}
                        <span v-if="report.department" class="ml-1 rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700">
                            {{ report.department }}
                        </span>
                        <span v-if="period" class="ml-1 text-slate-400">· {{ period }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="report.progress != null"
                        class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700"
                    >
                        {{ report.progress }}% хэрэгжилт
                    </span>
                    <span
                        v-if="report.measures"
                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700"
                    >
                        {{ report.measures }} арга хэмжээ
                    </span>
                    <span class="rounded-full bg-brand-navy-50 px-3 py-1 text-xs font-medium text-brand-navy-700">
                        {{ report.template_label || report.template }}
                    </span>
                </div>
            </div>

            <div>
                <ReportSectionTabs
                    :sections="navigation"
                    :active-section-key="activeSectionKey"
                    mode="show"
                />
            </div>

            <div class="ui-reports-table-shell grid min-h-0 grid-rows-[minmax(0,11rem)_minmax(0,1fr)] gap-4 lg:grid-cols-[18rem_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)]">
                <aside class="ui-card min-h-0 overflow-y-auto p-3">
                    <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Дэд тайлан
                    </p>
                    <nav v-if="sectionNavItems.length">
                        <ReportNavTree :items="sectionNavItems" :active-key="report.key" />
                    </nav>
                </aside>

                <section class="ui-card flex min-h-0 flex-col overflow-hidden">
                    <div
                        v-if="report.description || report.source_file"
                        class="shrink-0 border-b border-slate-100 px-4 py-3 text-sm text-slate-600"
                    >
                        <p v-if="report.description">{{ report.description }}</p>
                        <p v-if="report.source_file" class="mt-1 text-xs text-slate-500">
                            Эх файл: <strong class="font-medium text-slate-700">{{ report.source_file }}</strong>
                        </p>
                    </div>

                    <TableScrollViewport
                        v-if="hasColumns"
                        fill
                        class="!rounded-none !border-0 !shadow-none"
                        :measure-key="tableMinWidth"
                    >
                        <div
                            class="block max-w-none shrink-0"
                            :style="{ width: `${tableMinWidth}px`, minWidth: `${tableMinWidth}px` }"
                        >
                            <table class="ui-table table-fixed text-xs" :style="{ width: `${tableMinWidth}px`, minWidth: `${tableMinWidth}px` }">
                                <colgroup>
                                    <col
                                        v-for="col in report.columns"
                                        :key="`col-${col.key}`"
                                        :style="{ width: columnWidth(col) }"
                                    />
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th
                                            v-for="col in report.columns"
                                            :key="col.key"
                                            class="sticky top-0 z-20 bg-brand-navy-50 px-2 py-2 text-[11px] leading-tight normal-case tracking-normal"
                                            :class="[
                                                isPinnedColumn(col.key) ? 'sticky left-0 z-30 bg-brand-navy-50 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.12)]' : '',
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
                                        :key="rowIndex"
                                        class="align-top"
                                    >
                                        <td
                                            v-for="col in report.columns"
                                            :key="col.key"
                                            class="px-2 py-1.5 text-slate-700"
                                            :class="[
                                                cellClass(col.key),
                                                isPinnedColumn(col.key) ? 'sticky left-0 z-10 bg-inherit font-semibold text-slate-500 shadow-[2px_0_6px_-2px_rgba(15,23,42,0.08)]' : '',
                                            ]"
                                        >
                                            {{ row[col.key] ?? '' }}
                                        </td>
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
    </AuthenticatedLayout>
</template>
