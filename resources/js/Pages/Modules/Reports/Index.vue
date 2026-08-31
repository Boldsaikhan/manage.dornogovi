<script setup>
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';

defineProps({
    title: String,
    subtitle: String,
    navigation: { type: Array, default: () => [] },
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

                <section class="ui-card-pad space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Тайлан сонгоно уу</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Зүүн цэснээс хэсэг, дэд хэсгийг сонгоод тайлангийн хүснэгтийг нээнэ.
                            Хүснэгтийн баганыг тайлан бүрт дараа нь тохируулна.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="section in navigation"
                            :key="section.key"
                            class="rounded-xl border border-slate-200 bg-slate-50/70 p-3"
                        >
                            <p class="text-xs font-semibold text-brand-navy-700">
                                <span v-if="section.number" class="mr-1">{{ section.number }}.</span>
                                {{ section.label }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ section.children?.length || 0 }} дэд тайлан
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                        Эх сурвалж: <strong>ХШ_үүрэг даалгавар_ЭЦЭС.docx</strong>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
