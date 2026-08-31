<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { firstReportKeyInSection } from '@/utils/reportsNavigation';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    activeSectionKey: { type: String, default: null },
    /** index = ?section= query, show = first report in section */
    mode: {
        type: String,
        default: 'show',
        validator: (value) => ['index', 'show'].includes(value),
    },
});

const tabHref = (sectionKey) => {
    if (props.mode === 'index') {
        return route('reports.index', { section: sectionKey });
    }

    const reportKey = firstReportKeyInSection(props.sections, sectionKey);

    return reportKey ? route('reports.show', reportKey) : route('reports.index', { section: sectionKey });
};

const tabs = computed(() => props.sections.filter((section) => section.key && section.label));
</script>

<template>
    <div
        class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:rounded-2xl sm:border sm:border-slate-200 sm:bg-white sm:p-1.5 sm:shadow-soft [&::-webkit-scrollbar]:hidden"
        role="tablist"
        aria-label="Тайлангийн хэсэг"
    >
        <Link
            v-for="section in tabs"
            :key="section.key"
            :href="tabHref(section.key)"
            role="tab"
            :aria-selected="activeSectionKey === section.key"
            class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-semibold transition sm:px-4"
            :class="activeSectionKey === section.key
                ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 sm:border-0'"
        >
            <span v-if="section.number" class="mr-1.5 tabular-nums opacity-80">{{ section.number }}.</span>
            {{ section.label }}
        </Link>
    </div>
</template>
