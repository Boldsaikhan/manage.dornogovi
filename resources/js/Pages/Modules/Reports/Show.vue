<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';

const props = defineProps({
    title: String,
    report: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const templateLabels = {
    policy_tracking: 'Бодлогын баримт — хяналтын хүснэгт',
    memorandum_matrix: 'Санамж бичиг — тойм хүснэгт',
    memorandum_register: 'Санамж бичиг — бүртгэл',
    investment_matrix: 'Хөрөнгө оруулалт — тойм хүснэгт',
    investment_register: 'Хөрөнгө оруулалт — бүртгэл',
};

const templateLabel = computed(() => templateLabels[props.report.template] || props.report.template);

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
                    </p>
                </div>
                <span class="rounded-full bg-brand-navy-50 px-3 py-1 text-xs font-medium text-brand-navy-700">
                    {{ templateLabel }}
                </span>
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
                    <div v-if="report.description" class="border-b border-slate-100 px-4 py-3 text-sm text-slate-600">
                        {{ report.description }}
                    </div>

                    <div v-if="!hasColumns" class="px-4 py-10 text-center">
                        <p class="text-sm font-medium text-slate-700">Хүснэгтийн багана тохируулаагүй байна</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Энэ тайлангийн толгойг дараа нь нэмнэ. Бүтэц бэлэн — мөр өгөгдөл оруулах боломж нэмэгдэнэ.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="ui-table min-w-full">
                            <thead>
                                <tr>
                                    <th
                                        v-for="col in report.columns"
                                        :key="col.key"
                                        class="sticky top-0 bg-brand-navy-50"
                                    >
                                        {{ col.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td :colspan="report.columns.length" class="!py-10 text-center text-sm text-slate-400">
                                        Мэдээлэл байхгүй
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
