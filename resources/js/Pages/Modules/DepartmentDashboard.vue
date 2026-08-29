<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppExtensionManager from '@/Components/AppExtensionManager.vue';
import Modal from '@/Components/Modal.vue';

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
const detailTask = ref(null);

const progressBarClass = (p) => {
    const n = Number(p) || 0;
    if (n >= 100) return 'bg-emerald-500';
    if (n >= 50) return 'bg-brand-navy-600';
    if (n > 0) return 'bg-amber-500';
    return 'bg-orange-400';
};

const progressTextClass = (p) => {
    const n = Number(p) || 0;
    if (n >= 100) return 'text-emerald-700';
    if (n >= 50) return 'text-brand-navy-700';
    if (n > 0) return 'text-amber-700';
    return 'text-orange-600';
};

const progressLabel = (p) => {
    const n = Number(p) || 0;
    if (n >= 100) return 'Дууссан';
    if (n > 0) return 'Явж буй';
    return 'Эхлээгүй';
};

const openTaskDetail = (task) => {
    detailTask.value = task;
};

const closeTaskDetail = () => {
    detailTask.value = null;
};

const taskModuleHref = (task) => {
    if (task?.kind) {
        return route('tasks.index', { kind: task.kind });
    }

    return route('tasks.index');
};

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
        value: props.stats.task_open ?? 0,
        suffix: 'нээлттэй',
        panel: true,
        route: 'tasks.index',
        title: 'Үүрэг даалгавар',
        cardClass: 'border-brand-navy-200 bg-gradient-to-br from-brand-navy-50 to-slate-50 hover:border-brand-navy-300',
        labelClass: 'text-brand-navy-700',
        valueClass: 'text-brand-navy-900',
        dotClass: 'bg-brand-navy-600',
        selectedRing: 'ring-2 ring-brand-navy-400 ring-offset-2',
    },
]);

const activeCard = computed(() => statCards.value.find((card) => card.key === selectedKey.value) ?? null);

function selectCard(card) {
    if (!card.panel) {
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

            <!-- Үүрэг даалгаврын дашборд — үргэлж харагдана -->
            <section class="ui-card-pad space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-brand-navy-800">Үүрэг даалгавар</h3>
                        <p class="text-xs text-slate-500">Мөр дээр дарж дэлгэрэнгүй харна</p>
                    </div>
                    <Link
                        :href="route('tasks.index')"
                        class="text-xs font-semibold text-brand-navy-600 hover:underline"
                    >
                        Бүгдийг харах →
                    </Link>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="text-3xl font-bold tabular-nums" :class="progressTextClass(stats.task_avg)">
                        {{ stats.task_avg }}%
                    </div>
                    <div class="h-2.5 min-w-[8rem] flex-1 overflow-hidden rounded-full bg-slate-200">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="progressBarClass(stats.task_avg)"
                            :style="{ width: Math.min(100, Number(stats.task_avg) || 0) + '%' }"
                        />
                    </div>
                    <div class="flex flex-wrap gap-3 text-xs text-slate-500">
                        <span>Нийт <b class="text-slate-700">{{ stats.task_total ?? 0 }}</b></span>
                        <span>Дууссан <b class="text-emerald-600">{{ stats.task_done ?? 0 }}</b></span>
                        <span>Явж буй <b class="text-amber-600">{{ stats.task_started ?? 0 }}</b></span>
                        <span>Эхлээгүй <b class="text-orange-600">{{ stats.task_pending ?? 0 }}</b></span>
                    </div>
                </div>

                <ul v-if="recentTasks.length" class="divide-y divide-slate-100 rounded-xl border border-slate-100">
                    <li
                        v-for="task in recentTasks"
                        :key="task.id"
                    >
                        <button
                            type="button"
                            class="flex w-full flex-col gap-1.5 px-3 py-2.5 text-left transition hover:bg-brand-navy-50/70 sm:flex-row sm:items-center sm:justify-between"
                            @click="openTaskDetail(task)"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ task.text || '—' }}</p>
                                <p class="truncate text-xs text-slate-400">
                                    <span v-if="task.source">{{ task.source }} · </span>
                                    {{ task.responsible || 'Хариуцагчгүй' }}
                                </p>
                            </div>
                            <div class="flex w-full items-center gap-2 sm:w-44">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div
                                        class="h-full rounded-full"
                                        :class="progressBarClass(task.progress)"
                                        :style="{ width: Math.min(100, task.progress) + '%' }"
                                    />
                                </div>
                                <span class="w-10 text-right text-xs font-semibold tabular-nums" :class="progressTextClass(task.progress)">
                                    {{ task.progress }}%
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                        </button>
                    </li>
                </ul>
                <p v-else class="rounded-xl border border-dashed border-slate-200 bg-slate-50/80 px-4 py-6 text-center text-sm text-slate-400">
                    Танд хамаарах үүрэг даалгавар алга.
                </p>
            </section>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
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
                        <span v-if="card.suffix" class="ml-1 text-xs font-semibold opacity-70">{{ card.suffix }}</span>
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

                <ul v-else-if="activeCard.key === 'tasks'" class="divide-y divide-slate-100 text-sm">
                    <li v-for="task in recentTasks" :key="'panel-' + task.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-1.5 py-2.5 text-left transition hover:bg-slate-50"
                            @click="openTaskDetail(task)"
                        >
                            <div class="flex justify-between gap-2 font-medium text-slate-700">
                                <span class="min-w-0 truncate">{{ task.text || '—' }}</span>
                                <span :class="progressTextClass(task.progress)">{{ task.progress }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full"
                                    :class="progressBarClass(task.progress)"
                                    :style="{ width: Math.min(100, task.progress) + '%' }"
                                />
                            </div>
                        </button>
                    </li>
                    <li v-if="!recentTasks.length" class="py-2 text-slate-400">Бүртгэл алга</li>
                </ul>
            </div>
        </div>

        <Modal :show="!!detailTask" max-width="lg" @close="closeTaskDetail">
            <div v-if="detailTask" class="space-y-4 p-5 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
                            {{ detailTask.source || 'Үүрэг даалгавар' }}
                        </p>
                        <h3 class="mt-1 text-lg font-bold text-brand-navy-900">
                            {{ detailTask.text || '—' }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        title="Хаах"
                        @click="closeTaskDetail"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-3">
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-600">{{ progressLabel(detailTask.progress) }}</span>
                        <span class="font-bold tabular-nums" :class="progressTextClass(detailTask.progress)">
                            {{ detailTask.progress }}%
                        </span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-slate-200">
                        <div
                            class="h-full rounded-full"
                            :class="progressBarClass(detailTask.progress)"
                            :style="{ width: Math.min(100, detailTask.progress) + '%' }"
                        />
                    </div>
                </div>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-100 px-3 py-2.5">
                        <dt class="text-xs font-medium text-slate-400">Хариуцах эзэн</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ detailTask.responsible || '—' }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 px-3 py-2.5">
                        <dt class="text-xs font-medium text-slate-400">Хяналт тавих</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ detailTask.collaborator || '—' }}</dd>
                    </div>
                    <div v-if="detailTask.sector" class="rounded-xl border border-slate-100 px-3 py-2.5">
                        <dt class="text-xs font-medium text-slate-400">Ажлын чиглэл</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ detailTask.sector }}</dd>
                    </div>
                    <div v-if="detailTask.period" class="rounded-xl border border-slate-100 px-3 py-2.5">
                        <dt class="text-xs font-medium text-slate-400">Хугацаа</dt>
                        <dd class="mt-0.5 font-medium text-slate-800">{{ detailTask.period }}</dd>
                    </div>
                    <div class="rounded-xl border border-slate-100 px-3 py-2.5 sm:col-span-2">
                        <dt class="text-xs font-medium text-slate-400">Хэрэгжилт / тэмдэглэл</dt>
                        <dd class="mt-0.5 whitespace-pre-wrap font-medium text-slate-800">
                            {{ detailTask.note || '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeTaskDetail">Хаах</button>
                    <Link
                        :href="taskModuleHref(detailTask)"
                        class="ui-btn-primary"
                        @click="closeTaskDetail"
                    >
                        Модуль руу очих
                    </Link>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
