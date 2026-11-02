<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';
import ReportSectionTabs from '@/Components/Reports/ReportSectionTabs.vue';
import ReportsDashboard from '@/Components/Reports/ReportsDashboard.vue';
import { findReportSection } from '@/utils/reportsNavigation';

const props = defineProps({
    title: String,
    subtitle: String,
    navigation: { type: Array, default: () => [] },
    activeSection: { type: String, default: null },
    dashboard: { type: Object, default: () => ({}) },
    sources: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const activeSectionKey = computed(() => props.activeSection || props.navigation[0]?.key || null);

const sectionNavItems = computed(() => {
    const section = findReportSection(props.navigation, activeSectionKey.value);

    return section?.children ?? [];
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout :title="title">
        <div class="ui-page ui-page--reports-table">
            <div>
                <ReportSectionTabs
                    :sections="navigation"
                    :active-section-key="activeSectionKey"
                    mode="index"
                />
            </div>

            <div class="grid min-h-0 flex-1 gap-2 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <aside class="ui-card min-h-0 overflow-y-auto p-3">
                    <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Дэд тайлан
                    </p>
                    <nav v-if="sectionNavItems.length">
                        <ReportNavTree :items="sectionNavItems" />
                    </nav>
                    <p v-else class="px-2 py-4 text-sm text-slate-400">Тайлан байхгүй</p>

                    <p class="mb-1 mt-4 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Эх сурвалж
                    </p>
                    <ul class="space-y-1 px-2">
                        <li v-for="(source, index) in sources" :key="index">
                            <p class="truncate text-[11px] font-medium text-slate-700" :title="source.file">{{ source.file }}</p>
                            <p class="truncate text-[10px] text-slate-400">{{ source.role }}</p>
                        </li>
                    </ul>
                </aside>

                <section class="ui-card flex min-h-0 flex-col overflow-hidden p-3">
                    <ReportsDashboard class="min-h-0 flex-1" />
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
