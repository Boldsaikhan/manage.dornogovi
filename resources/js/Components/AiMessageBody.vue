<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    content: { type: String, default: '' },
    meta: { type: Object, default: null },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const briefing = computed(() => props.meta?.briefing ?? null);
const isBriefing = computed(() => (briefing.value?.items?.length ?? 0) > 0);

const displayContent = computed(() => {
    if (isBriefing.value) {
        return '';
    }

    return String(props.content ?? '')
        .replace(/\n*Эх сурвалж:\s*.+$/su, '')
        .trimEnd();
});

const itemLinks = computed(() => {
    if (isBriefing.value) {
        return [];
    }

    return (props.meta?.links ?? []).filter((l) => l?.href && l?.label);
});

const sourceLinks = computed(() => (props.meta?.sources ?? []).filter(
    (s) => s?.type === 'module' && (s.href || s.route) && s.label,
));

const resolveHref = (item) => {
    if (item?.href) return item.href;
    if (item?.route && typeof route === 'function') {
        try {
            return route(item.route, item.params ?? {});
        } catch {
            return '#';
        }
    }

    return '#';
};

const linkClass = computed(() => (props.compact
    ? 'text-brand-navy-700 underline decoration-brand-navy-300 underline-offset-2 hover:text-brand-navy-900'
    : 'font-medium text-brand-navy-700 underline decoration-brand-navy-300 underline-offset-2 hover:text-brand-navy-900'));
</script>

<template>
    <div class="space-y-2">
        <!-- Нэгтгэл: мөр бүр дарагдах холбоос -->
        <template v-if="isBriefing">
            <p class="font-semibold text-slate-800">{{ briefing.title }}</p>
            <ul class="space-y-1.5">
                <li
                    v-for="(item, i) in briefing.items"
                    :key="i"
                    class="flex gap-2"
                >
                    <span
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="{
                            'bg-red-500': item.tone === 'warn',
                            'bg-amber-500': item.tone === 'info',
                            'bg-emerald-500': item.tone === 'ok',
                        }"
                    />
                    <Link
                        v-if="item.href || item.route"
                        :href="resolveHref(item)"
                        class="text-left"
                        :class="linkClass"
                    >
                        {{ item.label }}
                    </Link>
                    <span v-else>{{ item.label }}</span>
                </li>
            </ul>
        </template>

        <div v-else-if="displayContent" class="whitespace-pre-wrap">{{ displayContent }}</div>

        <!-- Хайлтын мөрүүд — шууд цэс рүү -->
        <div v-if="itemLinks.length" class="space-y-1 border-t border-slate-200/80 pt-2">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Шилжих</p>
            <div class="flex flex-col gap-1">
                <Link
                    v-for="(link, i) in itemLinks"
                    :key="`${link.href}-${i}`"
                    :href="resolveHref(link)"
                    class="inline-flex items-center gap-1.5 text-sm"
                    :class="linkClass"
                >
                    <svg class="h-3.5 w-3.5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="line-clamp-2">{{ link.label }}</span>
                </Link>
            </div>
        </div>

        <!-- Эх сурвалж — модулийн цэс -->
        <div
            v-if="sourceLinks.length"
            class="flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-slate-200/60 pt-2"
        >
            <span class="text-[11px] font-semibold text-slate-400">Эх сурвалж:</span>
            <Link
                v-for="(src, i) in sourceLinks"
                :key="`${src.module}-${i}`"
                :href="resolveHref(src)"
                class="rounded-full bg-brand-navy-50 px-2.5 py-0.5 text-xs font-semibold text-brand-navy-700 ring-1 ring-brand-navy-100 transition hover:bg-brand-navy-100"
            >
                {{ src.label }}
            </Link>
        </div>

        <div
            v-if="meta?.requires_confirmation && meta?.action"
            class="mt-1 flex flex-wrap gap-2"
        >
            <button
                type="button"
                class="rounded-lg bg-brand-orange-500 px-3 py-1.5 text-xs font-semibold text-white"
                @click="emit('confirm', meta.action)"
            >
                Баталгаажуулах
            </button>
            <span class="self-center text-xs text-slate-500">AI боловсруулсан — хүний баталгаажуулалт шаардлагатай</span>
        </div>
    </div>
</template>
