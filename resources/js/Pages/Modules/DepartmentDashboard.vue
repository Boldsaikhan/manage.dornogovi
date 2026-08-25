<script setup>
import { Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    department: Object,
    isAdmin: Boolean,
    stats: Object,
    recentLeaves: Array,
    recentAssignments: Array,
    workGroups: Array,
});
</script>

<template>
    <AuthenticatedLayout title="Хэлтсийн самбар">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-navy-900">
                    {{ department?.name || (isAdmin ? 'Бүх хэлтэс (админ)' : 'Хэлтэс сонгоогүй') }}
                </h2>
                <p class="text-sm text-brand-navy-500">Хэлтэс бүрийн товч үзүүлэлт, явц.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="card in [
                    { label: 'Хүлээгдэж буй чөлөө', value: stats.pending_leaves },
                    { label: 'Идэвхтэй томилолт', value: stats.active_assignments },
                    { label: 'Идэвхтэй төлөвлөгөө', value: stats.active_plans },
                    { label: 'Ажлын хэсэг', value: stats.work_groups },
                    { label: 'Үүргийн дундаж', value: stats.task_avg + '%' },
                ]" :key="card.label" class="rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm">
                    <div class="text-xs text-brand-navy-400">{{ card.label }}</div>
                    <div class="mt-1 text-2xl font-semibold text-brand-navy-900">{{ card.value }}</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold text-brand-navy-800">Сүүлийн чөлөө</h3>
                        <Link :href="route('leaves.index')" class="text-xs text-brand-orange-600">Бүгд</Link>
                    </div>
                    <ul class="space-y-2 text-sm">
                        <li v-for="row in recentLeaves" :key="row.id" class="flex justify-between gap-2 border-b border-brand-navy-50 pb-2">
                            <span>{{ row.user?.name || '—' }} · {{ row.type }}</span>
                            <span class="text-brand-navy-400">{{ row.status }}</span>
                        </li>
                        <li v-if="!recentLeaves.length" class="text-brand-navy-400">Бүртгэл алга</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="font-semibold text-brand-navy-800">Ажлын хэсгийн явц</h3>
                        <Link :href="route('work-groups.index')" class="text-xs text-brand-orange-600">Бүгд</Link>
                    </div>
                    <ul class="space-y-3 text-sm">
                        <li v-for="g in workGroups" :key="g.id">
                            <div class="flex justify-between"><span>{{ g.name }}</span><span>{{ g.progress }}%</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-brand-navy-100"><div class="h-full rounded-full bg-brand-navy-600" :style="{ width: g.progress + '%' }" /></div>
                        </li>
                        <li v-if="!workGroups.length" class="text-brand-navy-400">Бүртгэл алга</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
