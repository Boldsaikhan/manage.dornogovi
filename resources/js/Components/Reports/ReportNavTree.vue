<script setup>
import { Link } from '@inertiajs/vue3';
import ReportNavTree from '@/Components/Reports/ReportNavTree.vue';

defineOptions({ name: 'ReportNavTree' });

defineProps({
    items: { type: Array, default: () => [] },
    activeKey: { type: String, default: null },
    depth: { type: Number, default: 0 },
});
</script>

<template>
    <ul class="space-y-0.5" :class="depth ? 'ml-3 border-l border-slate-200 pl-2' : ''">
        <li v-for="item in items" :key="item.key || item.label">
            <Link
                v-if="item.key"
                :href="route('reports.show', item.key)"
                class="block rounded-lg px-2.5 py-2 text-sm transition"
                :class="activeKey === item.key
                    ? 'bg-brand-navy-600 font-semibold text-white'
                    : 'text-slate-700 hover:bg-slate-100'"
            >
                <span v-if="item.number" class="mr-1.5 tabular-nums text-[11px] opacity-80">{{ item.number }}</span>
                <span>{{ item.label }}</span>
                <span
                    v-if="item.department"
                    class="ml-1 rounded px-1 py-0.5 text-[10px] font-medium uppercase"
                    :class="activeKey === item.key ? 'bg-white/20 text-white/90' : 'bg-slate-200 text-slate-600'"
                >
                    {{ item.department }}
                </span>
            </Link>
            <p
                v-else
                class="px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400"
            >
                {{ item.label }}
            </p>

            <ReportNavTree
                v-if="item.children?.length"
                :items="item.children"
                :active-key="activeKey"
                :depth="depth + 1"
            />
        </li>
    </ul>
</template>
