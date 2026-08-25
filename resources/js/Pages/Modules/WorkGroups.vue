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
        <div class="ui-page">
            <div>
                <h2 class="ui-title">Ажлын хэсэг</h2>
                <p class="ui-subtitle">Хяналт, үүрэг даалгаврын бүтэцтэй адил хэсэг.</p>
            </div>

            <form v-if="canManage" class="ui-card flex flex-wrap gap-2 p-4" @submit.prevent="createGroup">
                <input v-model="groupForm.name" required placeholder="Ажлын хэсгийн нэр" class="ui-input min-w-[220px] flex-1" />
                <input v-model="groupForm.description" placeholder="Тайлбар" class="ui-input min-w-[220px] flex-1" />
                <button class="ui-btn-accent">Үүсгэх</button>
            </form>

            <div v-for="group in groups" :key="group.id" class="ui-card-pad">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-bold text-brand-navy-800">{{ group.name }}</h3>
                        <p class="text-xs text-slate-400">{{ group.description || '—' }} · {{ group.department || 'Хэлтэсгүй' }}</p>
                    </div>
                    <div class="text-sm font-bold text-brand-navy-700">{{ group.progress }}%</div>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-brand-navy-600" :style="{ width: group.progress + '%' }" />
                </div>

                <table class="mt-4 min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="py-1">Үүрэг</th>
                            <th class="py-1">Хариуцагч</th>
                            <th class="py-1">Явц</th>
                            <th class="py-1">Огноо</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in group.tasks" :key="task.id" class="border-t border-slate-100">
                            <td class="py-2.5 font-medium text-slate-800">{{ task.title }}</td>
                            <td class="py-2.5 text-slate-500">{{ task.owner || '—' }}</td>
                            <td class="py-2.5">
                                <input
                                    v-if="canManage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="ui-input !w-16 !px-2 !py-1"
                                    :value="task.progress"
                                    @change="bumpProgress(task, Number($event.target.value))"
                                />
                                <span v-else>{{ task.progress }}%</span>
                            </td>
                            <td class="py-2.5 text-slate-400">{{ task.due_on || '—' }}</td>
                        </tr>
                    </tbody>
                </table>

                <form v-if="canManage" class="mt-4 flex flex-wrap gap-2" @submit.prevent="addTask(group.id)">
                    <input v-model="draft(group.id).title" required placeholder="Шинэ үүрэг" class="ui-input !w-auto min-w-[10rem]" />
                    <input v-model="draft(group.id).owner" placeholder="Хариуцагч" class="ui-input !w-auto min-w-[8rem]" />
                    <input v-model="draft(group.id).due_on" type="date" class="ui-input !w-auto" />
                    <button class="ui-btn-primary !py-2">Нэмэх</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
