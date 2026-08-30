<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppExtensionManager from '@/Components/AppExtensionManager.vue';

const props = defineProps({
    department: Object,
    isAdmin: Boolean,
    stats: Object,
    recentLeaves: Array,
    recentAssignments: Array,
    recentPlans: Array,
    workGroups: Array,
    recentTasks: { type: Array, default: () => [] },
});

const selectedKey = ref(null);

const statCards = computed(() => [
    {
        key: 'leaves',
        label: 'Хүлээгдэж буй чөлөө',
        value: props.stats.pending_leaves,
        panel: true,
        route: 'leaves.index',
        title: 'Сүүлийн чөлөө',
        cardClass: 'border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 hover:border-amber-300',
        labelClass: 'text-amber-700',
        valueClass: 'text-amber-900',
        dotClass: 'bg-amber-500',
        selectedRing: 'ring-2 ring-amber-400 ring-offset-2',
    },
    {
        key: 'assignments',
        label: 'Идэвхтэй томилолт',
        value: props.stats.active_assignments,
        panel: true,
        route: 'assignments.index',
        title: 'Сүүлийн томилолт',
        cardClass: 'border-sky-200 bg-gradient-to-br from-sky-50 to-blue-50 hover:border-sky-300',
        labelClass: 'text-sky-700',
        valueClass: 'text-sky-900',
        dotClass: 'bg-sky-500',
        selectedRing: 'ring-2 ring-sky-400 ring-offset-2',
    },
    {
        key: 'plans',
        label: 'Идэвхтэй төлөвлөгөө',
        value: props.stats.active_plans,
        panel: true,
        route: 'plans.index',
        title: 'Сүүлийн төлөвлөгөө',
        cardClass: 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 hover:border-emerald-300',
        labelClass: 'text-emerald-700',
        valueClass: 'text-emerald-900',
        dotClass: 'bg-emerald-500',
        selectedRing: 'ring-2 ring-emerald-400 ring-offset-2',
    },
    {
        key: 'work_groups',
        label: 'Ажлын хэсэг',
        value: props.stats.work_groups,
        panel: true,
        route: 'work-groups.index',
        title: 'Ажлын хэсгийн явц',
        cardClass: 'border-violet-200 bg-gradient-to-br from-violet-50 to-purple-50 hover:border-violet-300',
        labelClass: 'text-violet-700',
        valueClass: 'text-violet-900',
        dotClass: 'bg-violet-500',
        selectedRing: 'ring-2 ring-violet-400 ring-offset-2',
    },
    {
        key: 'tasks',
        label: 'Үүрэг даалгавар',
        value: props.stats.open_tasks,
        panel: true,
        route: 'tasks.index',
        title: 'Үүрэг даалгаврын явц',
        cardClass: 'border-rose-200 bg-gradient-to-br from-rose-50 to-pink-50 hover:border-rose-300',
        labelClass: 'text-rose-700',
        valueClass: 'text-rose-900',
        dotClass: 'bg-rose-500',
        selectedRing: 'ring-2 ring-rose-400 ring-offset-2',
    },
]);

const activeCard = computed(() => statCards.value.find((card) => card.key === selectedKey.value) ?? null);

function selectCard(card) {
    if (! card.panel) {
        return;
    }

    selectedKey.value = selectedKey.value === card.key ? null : card.key;
}
</script>

<template>
    <AuthenticatedLayout title="Албан хаагчийн самбар">
        <div class="ui-page">
            <div>
                <h2 class="ui-title">
                    {{ department?.name || (isAdmin ? 'Бүх хэлтэс (админ)' : 'Хэлтэс сонгоогүй') }}
                </h2>
                <p class="ui-subtitle">Албан хаагчийн товч үзүүлэлт, явц.</p>
            </div>

            <AppExtensionManager notify-only />

            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                <button
                    v-for="card in statCards"
                    :key="card.key"
                    type="button"
                    class="relative flex flex-col gap-1 rounded-xl border p-4 text-left transition"
                    :class="[
                        card.cardClass,
                        card.panel ? 'cursor-pointer' : 'cursor-default',
                        selectedKey === card.key ? card.selectedRing : '',
                    ]"
                    @click="selectCard(card)"
                >
                    <span
                        class="absolute right-3 top-3 h-2 w-2 rounded-full"
                        :class="card.dotClass"
                    />
                    <div class="text-xs font-semibold" :class="card.labelClass">{{ card.label }}</div>
                    <div class="text-2xl font-bold tracking-tight" :class="card.valueClass">
                        {{ card.value }}
                    </div>
                    <div
                        v-if="card.panel"
                        class="mt-1 text-[11px] font-medium"
                        :class="card.labelClass"
                    >
                        {{ selectedKey === card.key ? 'Дэлгэрэнгүй хаах' : 'Дэлгэрэнгүй харах' }}
                    </div>
                </button>
            </div>

            <div
                v-if="activeCard"
                class="ui-card-pad"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-bold text-brand-navy-800">{{ activeCard.title }}</h3>
                    <Link
                        :href="route(activeCard.route)"
                        class="text-xs font-semibold text-brand-navy-600 hover:underline"
                    >
                        Бүгд
                    </Link>
                </div>

                <ul v-if="activeCard.key === 'leaves'" class="space-y-2 text-sm">
                    <li
                        v-for="row in recentLeaves"
                        :key="row.id"
                        class="flex justify-between gap-2 border-b border-slate-100 pb-2"
                    >
                        <span class="text-slate-700">{{ row.user?.name || '—' }} · {{ row.type }}</span>
                        <span class="text-slate-400">{{ row.status }}</span>
                    </li>
                    <li v-if="!recentLeaves.length" class="text-slate-400">Бүртгэл алга</li>
                </ul>

                <ul v-else-if="activeCard.key === 'assignments'" class="space-y-2 text-sm">
                    <li
                        v-for="row in recentAssignments"
                        :key="row.id"
                        class="flex justify-between gap-2 border-b border-slate-100 pb-2"
                    >
                        <span class="text-slate-700">
                            {{ row.user?.name || '—' }} · {{ row.destination || '—' }}
                        </span>
                        <span class="text-slate-400">{{ row.status }}</span>
                    </li>
                    <li v-if="!recentAssignments.length" class="text-slate-400">Бүртгэл алга</li>
                </ul>

                <ul v-else-if="activeCard.key === 'plans'" class="space-y-2 text-sm">
                    <li
                        v-for="row in recentPlans"
                        :key="row.id"
                        class="flex justify-between gap-2 border-b border-slate-100 pb-2"
                    >
                        <span class="text-slate-700">
                            {{ row.title || '—' }}
                            <span v-if="row.year" class="text-slate-400">· {{ row.year }}</span>
                        </span>
                        <span class="text-slate-400">{{ row.status }}</span>
                    </li>
                    <li v-if="!recentPlans.length" class="text-slate-400">Бүртгэл алга</li>
                </ul>

                <ul v-else-if="activeCard.key === 'work_groups'" class="space-y-3 text-sm">
                    <li v-for="g in workGroups" :key="g.id">
                        <div class="flex justify-between font-medium text-slate-700">
                            <span>{{ g.name }}</span>
                            <span>{{ g.progress }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-violet-600"
                                :style="{ width: g.progress + '%' }"
                            />
                        </div>
                    </li>
                    <li v-if="!workGroups.length" class="text-slate-400">Бүртгэл алга</li>
                </ul>

                <ul v-else-if="activeCard.key === 'tasks'" class="space-y-3 text-sm">
                    <li v-for="t in recentTasks" :key="t.id">
                        <div class="flex justify-between gap-3 font-medium text-slate-700">
                            <span class="min-w-0 flex-1 truncate">{{ t.text }}</span>
                            <span class="shrink-0">{{ t.progress }}%</span>
                        </div>
                        <div v-if="t.source" class="text-xs text-slate-400">{{ t.source }}</div>
                        <div class="mt-1.5 h-2 rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-rose-600"
                                :style="{ width: t.progress + '%' }"
                            />
                        </div>
                    </li>
                    <li v-if="!recentTasks.length" class="text-slate-400">Бүртгэл алга</li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
