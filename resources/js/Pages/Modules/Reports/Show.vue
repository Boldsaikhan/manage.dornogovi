<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';

const props = defineProps({
    title: String,
    period: String,
    report: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const hasColumns = computed(() => (props.report.columns?.length ?? 0) > 0);
</script>

<template>
    <Head :title="report.label" />

    <AuthenticatedLayout :title="title">
        <div class="ui-page">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('reports.index')" class="text-sm text-brand-navy-600 hover:underline">
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

            <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <aside class="ui-card max-h-[calc(100dvh-10rem)] overflow-y-auto p-3 lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Тайлангийн бүтэц
                    </p>
                    <nav>
                        <ReportNavTree :items="navigation" :active-key="report.key" />
                    </nav>
                </aside>

                <section class="ui-card overflow-hidden">
                    <div
                        v-if="report.description || report.source_file"
                        class="border-b border-slate-100 px-4 py-3 text-sm text-slate-600"
                    >
                        <p v-if="report.description">{{ report.description }}</p>
                        <p v-if="report.source_file" class="mt-1 text-xs text-slate-500">
                            Эх файл: <strong class="font-medium text-slate-700">{{ report.source_file }}</strong>
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table min-w-full">
                            <thead>
                                <tr>
                                    <th
                                        v-for="col in report.columns"
                                        :key="col.key"
                                        class="sticky top-0 bg-brand-navy-50 whitespace-nowrap"
                                        :style="col.width ? { minWidth: col.width } : undefined"
                                    >
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td :colspan="report.columns.length || 1" class="!py-10 text-center text-sm text-slate-400">
                                        Мэдээлэл оруулаагүй — хүснэгтийн бүтэц бэлэн
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="!hasColumns" class="border-t border-slate-100 px-4 py-6 text-center text-xs text-slate-500">
                        Template тохируулаагүй байна.
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
