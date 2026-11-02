<script setup>
defineProps({
    visual: { type: Object, default: null },
});

const areaClass = {
    meta: 'top-[7%] right-[8%] left-[8%]',
    body: 'top-[28%] right-[8%] left-[8%] min-h-[38%]',
    sign: 'bottom-[16%] right-[8%] w-[46%]',
    review: 'bottom-[6%] left-[8%] w-[42%]',
};
</script>

<template>
    <div v-if="visual && visual.type && visual.type !== 'none'" class="space-y-4">
        <div v-if="visual.type === 'sheet'" class="grid items-start gap-4 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <div class="mx-auto w-full max-w-[16rem]">
                <div class="relative aspect-[210/297] rounded-sm border border-slate-300 bg-white shadow-sm">
                    <div
                        v-for="mark in visual.marks || []"
                        :key="mark.n"
                        class="absolute rounded border border-brand-navy-200 bg-brand-navy-50/80 px-1.5 py-1 text-[10px] leading-tight text-brand-navy-900"
                        :class="areaClass[mark.area] || 'top-2 left-2'"
                    >
                        <span class="mr-1 inline-flex h-4 w-4 items-center justify-center rounded-full bg-brand-orange-500 text-[10px] font-semibold text-white">{{ mark.n }}</span>
                        {{ mark.label }}
                    </div>
                </div>
                <p v-if="visual.caption" class="mt-2 text-center text-[11px] text-slate-500">{{ visual.caption }}</p>
            </div>
            <ol class="space-y-2">
                <li v-for="mark in visual.marks || []" :key="`l-${mark.n}`" class="ui-card flex gap-3 p-3">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-orange-500 text-xs font-semibold text-white">{{ mark.n }}</span>
                    <div>
                        <p class="text-sm font-medium text-slate-800">{{ mark.label }}</p>
                        <p class="text-xs text-slate-500">Заалт {{ mark.clause }}</p>
                    </div>
                </li>
            </ol>
        </div>

        <div v-else-if="visual.type === 'flow'" class="space-y-5">
            <section v-for="(group, gi) in visual.groups || []" :key="gi">
                <h3 class="ui-subtitle">{{ group.title }} <span class="font-normal text-slate-400">{{ group.clause }}</span></h3>
                <ol class="mt-3 grid gap-2 md:grid-cols-5">
                    <li v-for="step in group.steps || []" :key="`${gi}-${step.n}`" class="ui-card p-3">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-navy-600 text-xs font-semibold text-white">{{ step.n }}</span>
                        <p class="mt-2 text-sm font-medium text-slate-800">{{ step.title }}</p>
                        <p class="mt-1 text-xs leading-snug text-slate-500">{{ step.text }}</p>
                        <p class="mt-1 text-[11px] text-brand-navy-600">{{ step.clause }}</p>
                    </li>
                </ol>
            </section>
        </div>

        <div v-else-if="visual.type === 'units'" class="flex flex-wrap items-end gap-6">
            <div v-for="item in visual.items || []" :key="item.name" class="text-center">
                <div
                    class="mx-auto border border-brand-navy-300 bg-brand-navy-50"
                    :style="{ width: `${item.w / 6}px`, height: `${item.h / 6}px` }"
                />
                <p class="mt-2 text-sm font-semibold text-slate-800">{{ item.name }}</p>
                <p class="text-xs text-slate-600">{{ item.unit }}</p>
                <p class="text-[11px] text-slate-400">{{ item.w }}×{{ item.h }} мм · {{ item.clause }}</p>
            </div>
        </div>

        <ol v-else-if="visual.type === 'parts'" class="grid gap-2 md:grid-cols-2">
            <li v-for="item in visual.items || []" :key="item.n" class="ui-card flex gap-3 p-3">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-orange-500 text-xs font-semibold text-white">{{ item.n }}</span>
                <div>
                    <p class="text-sm font-medium text-slate-800">{{ item.title }}</p>
                    <p class="mt-1 text-xs leading-snug text-slate-600">{{ item.text }}</p>
                    <p class="mt-1 text-[11px] text-brand-navy-600">{{ item.clause }}</p>
                </div>
            </li>
        </ol>

        <div v-else-if="visual.type === 'schedule'" class="grid gap-2 md:grid-cols-2">
            <article v-for="item in visual.items || []" :key="item.clause + item.when" class="ui-card p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-orange-600">{{ item.when }}</p>
                <p class="mt-1 text-sm text-slate-800">{{ item.what }}</p>
                <p class="mt-1 text-[11px] text-slate-400">{{ item.clause }}</p>
            </article>
        </div>
    </div>
</template>
