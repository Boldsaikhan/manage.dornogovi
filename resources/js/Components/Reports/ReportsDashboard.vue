<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    dashboard: { type: Object, required: true },
});

const circumference = 2 * Math.PI * 52;

const donut = (value) => {
    const pct = Math.max(0, Math.min(100, Number(value) || 0));
    const filled = (pct / 100) * circumference;

    return {
        pct,
        dash: `${filled} ${circumference}`,
    };
};

const progressStroke = (value) => {
    const pct = Number(value);
    if (Number.isNaN(pct)) {
        return '#94a3b8';
    }
    if (pct >= 80) {
        return '#16a34a';
    }
    if (pct >= 50) {
        return '#2563eb';
    }
    if (pct > 0) {
        return '#ea580c';
    }

    return '#94a3b8';
};

const progressTextClass = (value) => {
    const pct = Number(value);
    if (Number.isNaN(pct)) {
        return 'text-slate-400';
    }
    if (pct >= 80) {
        return 'text-green-600';
    }
    if (pct >= 50) {
        return 'text-brand-navy-700';
    }

    return 'text-orange-600';
};

const sectionLink = (sectionKey) => {
    const map = {
        state_policy: 'state_policy.cabinet_decree',
        local_policy: 'local_policy.annual_plan',
        contracts: 'contracts.ministry_gbxn',
        target_programs: 'target_programs.medical_education',
        memorandum: 'memorandum.summary',
        investment: 'investment.summary',
    };

    return route('reports.show', map[sectionKey] || sectionKey);
};

const kpiDisplay = computed(() => props.dashboard.kpis ?? []);
const sections = computed(() => props.dashboard.sections ?? []);
const departments = computed(() => props.dashboard.departments ?? []);
const assignments = computed(() => props.dashboard.official_assignments ?? []);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Нэгдсэн үзүүлэлт</p>
                <p v-if="dashboard.period" class="mt-0.5 text-sm text-slate-600">{{ dashboard.period }}</p>
            </div>
            <p v-if="dashboard.as_of" class="text-xs text-slate-500">
                Тойм: {{ dashboard.as_of }} · {{ dashboard.report_count }} тайлан · {{ dashboard.source_count }} эх файл
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="kpi in kpiDisplay"
                :key="kpi.key"
                class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50/80 p-4"
            >
                <p class="text-xs font-medium text-slate-500">{{ kpi.label }}</p>
                <p class="mt-1 text-2xl font-bold text-brand-navy-900">
                    {{ kpi.value }}<span v-if="kpi.unit" class="ml-1 text-base font-semibold text-slate-500">{{ kpi.unit }}</span>
                </p>
                <p v-if="kpi.hint" class="mt-1 text-xs text-slate-500">{{ kpi.hint }}</p>
            </div>
        </div>

        <section class="rounded-2xl border border-slate-200 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Хэсгээр</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="section in sections"
                    :key="section.key"
                    :href="sectionLink(section.key)"
                    class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-brand-navy-400 hover:shadow-soft"
                >
                    <div class="relative h-14 w-14 shrink-0">
                        <svg viewBox="0 0 130 130" class="h-14 w-14 -rotate-90">
                            <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="18" />
                            <circle
                                v-if="section.progress != null"
                                cx="65"
                                cy="65"
                                r="52"
                                fill="none"
                                :stroke="progressStroke(section.progress)"
                                stroke-width="18"
                                stroke-linecap="round"
                                :stroke-dasharray="donut(section.progress).dash"
                            />
                        </svg>
                        <span
                            class="absolute inset-0 flex items-center justify-center text-xs font-bold"
                            :class="section.progress != null ? progressTextClass(section.progress) : 'text-slate-400'"
                        >
                            {{ section.progress != null ? `${section.progress}%` : '—' }}
                        </span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">
                            <span class="mr-1 text-slate-400">{{ section.number }}.</span>
                            {{ section.label }}
                        </p>
                        <p v-if="section.note" class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ section.note }}</p>
                    </div>
                </Link>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 p-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Албан даалгавар (2.7)</p>
                <div class="space-y-2">
                    <Link
                        v-for="item in assignments"
                        :key="item.key"
                        :href="route('reports.show', item.key)"
                        class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2.5 transition hover:border-brand-navy-300 hover:bg-white"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                <span class="mr-1.5 rounded bg-brand-navy-100 px-1.5 py-0.5 text-[10px] font-bold text-brand-navy-700">{{ item.number }}</span>
                                {{ item.label }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ item.department }} · {{ item.measures }} арга хэмжээ
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-bold tabular-nums"
                            :class="item.progress != null ? progressTextClass(item.progress) : 'text-slate-400'"
                        >
                            {{ item.progress != null ? `${item.progress}%` : '—' }}
                        </span>
                    </Link>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 p-4">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">Хэлтэсээр</p>
                <p class="mb-3 text-xs text-slate-500">АЖХТ — хэрэгжилтийн дундаж</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div
                        v-for="dept in departments"
                        :key="dept.code"
                        class="rounded-xl border border-slate-100 bg-slate-50/70 px-2.5 py-2 text-center"
                    >
                        <p class="text-xs font-semibold text-slate-700">{{ dept.label }}</p>
                        <p
                            class="mt-1 text-lg font-bold tabular-nums"
                            :class="dept.progress != null ? progressTextClass(dept.progress) : 'text-slate-400'"
                        >
                            {{ dept.progress != null ? `${dept.progress}%` : '—' }}
                        </p>
                        <p v-if="dept.tasks" class="text-[10px] text-slate-500">{{ dept.tasks }} үүрэг</p>
                        <p v-else-if="dept.note" class="text-[10px] text-orange-600">{{ dept.note }}</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
