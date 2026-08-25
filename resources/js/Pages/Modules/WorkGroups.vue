<script setup>
import { reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    groups: Array,
    canManage: Boolean,
});

const groupForm = useForm({ name: '', description: '' });
const taskDrafts = reactive({});

const draft = (groupId) => {
    if (!taskDrafts[groupId]) {
        taskDrafts[groupId] = { title: '', owner: '', due_on: '' };
    }
    return taskDrafts[groupId];
};

const createGroup = () => {
    groupForm.post(route('work-groups.store'), {
        preserveScroll: true,
        onSuccess: () => groupForm.reset(),
    });
};

const addTask = (groupId) => {
    const data = draft(groupId);
    router.post(route('work-groups.tasks.store', groupId), { ...data }, {
        preserveScroll: true,
        onSuccess: () => {
            data.title = '';
            data.owner = '';
            data.due_on = '';
        },
    });
};

const bumpProgress = (task, value) => {
    router.patch(route('work-groups.tasks.update', task.id), { progress: value }, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout title="Ажлын хэсэг">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-brand-navy-900">Ажлын хэсэг</h2>
                <p class="mt-1 text-sm text-brand-navy-500">Хяналт, үүрэг даалгаврын бүтэцтэй адил хэсэг.</p>
            </div>

            <form v-if="canManage" class="flex flex-wrap gap-2 rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm" @submit.prevent="createGroup">
                <input v-model="groupForm.name" required placeholder="Ажлын хэсгийн нэр" class="min-w-[220px] flex-1 rounded-md border-brand-navy-200 text-sm" />
                <input v-model="groupForm.description" placeholder="Тайлбар" class="min-w-[220px] flex-1 rounded-md border-brand-navy-200 text-sm" />
                <button class="rounded-lg bg-brand-orange-500 px-4 py-2 text-sm font-medium text-white">Үүсгэх</button>
            </form>

            <div v-for="group in groups" :key="group.id" class="rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-brand-navy-900">{{ group.name }}</h3>
                        <p class="text-xs text-brand-navy-400">{{ group.description || '—' }} · {{ group.department || 'Хэлтэсгүй' }}</p>
                    </div>
                    <div class="text-sm font-semibold text-brand-navy-800">{{ group.progress }}%</div>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-brand-navy-100">
                    <div class="h-full rounded-full bg-brand-navy-600" :style="{ width: group.progress + '%' }" />
                </div>

                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs text-brand-navy-500">
                        <tr>
                            <th class="py-1">Үүрэг</th>
                            <th class="py-1">Хариуцагч</th>
                            <th class="py-1">Явц</th>
                            <th class="py-1">Огноо</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in group.tasks" :key="task.id" class="border-t border-brand-navy-50">
                            <td class="py-2 text-brand-navy-800">{{ task.title }}</td>
                            <td class="py-2 text-brand-navy-500">{{ task.owner || '—' }}</td>
                            <td class="py-2">
                                <input
                                    v-if="canManage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-16 rounded border-brand-navy-200 text-sm"
                                    :value="task.progress"
                                    @change="bumpProgress(task, Number($event.target.value))"
                                />
                                <span v-else>{{ task.progress }}%</span>
                            </td>
                            <td class="py-2 text-brand-navy-400">{{ task.due_on || '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <form v-if="canManage" class="mt-3 flex flex-wrap gap-2" @submit.prevent="addTask(group.id)">
                    <input v-model="draft(group.id).title" required placeholder="Шинэ үүрэг" class="rounded-md border-brand-navy-200 text-sm" />
                    <input v-model="draft(group.id).owner" placeholder="Хариуцагч" class="rounded-md border-brand-navy-200 text-sm" />
                    <input v-model="draft(group.id).due_on" type="date" class="rounded-md border-brand-navy-200 text-sm" />
                    <button class="rounded-lg bg-brand-navy-700 px-3 py-1.5 text-sm text-white">Нэмэх</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
