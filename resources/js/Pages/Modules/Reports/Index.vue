<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';
import ReportsDashboard from '@/Components/Reports/ReportsDashboard.vue';

defineProps({
    title: String,
    subtitle: String,
    navigation: { type: Array, default: () => [] },
    dashboard: { type: Object, default: () => ({}) },
    sources: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout :title="title">
        <div class="ui-page">
            <div class="mb-4">
                <h2 class="ui-title">{{ title }}</h2>
                <p v-if="subtitle" class="ui-subtitle">{{ subtitle }}</p>
            </div>

            <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <aside class="ui-card max-h-[calc(100dvh-10rem)] overflow-y-auto p-3 lg:sticky lg:top-24 lg:self-start">
                    <p class="mb-2 px-2 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        Тайлангийн бүтэц
                    </p>
                    <nav>
                        <ReportNavTree :items="navigation" />
                    </nav>
                </aside>

                <section class="space-y-4">
                    <div class="ui-card-pad">
                        <ReportsDashboard :dashboard="dashboard" />
                    </div>

                    <div class="ui-card-pad">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Эх сурвалж файл</p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="(source, index) in sources"
                                :key="index"
                                class="rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2.5"
                            >
                                <p class="text-sm font-medium text-slate-800">{{ source.file }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ source.role }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
