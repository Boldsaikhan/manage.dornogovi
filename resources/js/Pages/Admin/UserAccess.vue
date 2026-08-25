<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    users: Array,
    departments: Array,
    modules: Array,
});

const selectedId = ref(props.users[0]?.id ?? null);
const selected = computed(() => props.users.find((u) => u.id === selectedId.value) || null);

const createForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    department_id: '',
    position: '',
    is_admin: false,
    is_department_head: false,
});

const editState = reactive({
    name: '',
    email: '',
    phone: '',
    department_id: '',
    position: '',
    is_admin: false,
    is_department_head: false,
    password: '',
    permissions: {},
});

const loadSelected = () => {
    if (!selected.value) return;
    editState.name = selected.value.name;
    editState.email = selected.value.email;
    editState.phone = selected.value.phone || '';
    editState.department_id = selected.value.department_id || '';
    editState.position = selected.value.position || '';
    editState.is_admin = selected.value.is_admin;
    editState.is_department_head = selected.value.is_department_head;
    editState.password = '';
    editState.permissions = { ...selected.value.permissions };
};

loadSelected();

const selectUser = (id) => {
    selectedId.value = id;
    loadSelected();
};

const setLevel = (key, level) => {
    if (!level) {
        delete editState.permissions[key];
        return;
    }
    editState.permissions[key] = level;
};

const saveUser = () => {
    if (!selected.value) return;
    router.patch(route('admin.users.update', selected.value.id), { ...editState }, { preserveScroll: true });
};

const createUser = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
};
</script>

<template>
    <AuthenticatedLayout title="Хандах эрх">
        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <div class="rounded-xl border border-brand-navy-100 bg-white shadow-sm">
                <div class="border-b border-brand-navy-100 px-3 py-2 text-sm font-semibold text-brand-navy-800">Албан хаагчид</div>
                <button
                    v-for="u in users"
                    :key="u.id"
                    type="button"
                    class="flex w-full flex-col border-b border-brand-navy-50 px-3 py-2 text-left text-sm hover:bg-brand-navy-50"
                    :class="selectedId === u.id ? 'bg-brand-orange-50' : ''"
                    @click="selectUser(u.id)"
                >
                    <span class="font-medium text-brand-navy-900">{{ u.name }}</span>
                    <span class="text-xs text-brand-navy-400">{{ u.email }} · {{ u.phone || 'утасгүй' }}</span>
                </button>
            </div>

            <div class="space-y-4">
                <form v-if="selected" class="space-y-3 rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm" @submit.prevent="saveUser">
                    <h3 class="font-semibold text-brand-navy-900">Эрх тохируулах — {{ selected.name }}</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="editState.name" class="rounded-md border-brand-navy-200 text-sm" placeholder="Нэр" required />
                        <input v-model="editState.email" type="email" class="rounded-md border-brand-navy-200 text-sm" placeholder="И-мэйл" required />
                        <input v-model="editState.phone" class="rounded-md border-brand-navy-200 text-sm" placeholder="Утас" />
                        <input v-model="editState.position" class="rounded-md border-brand-navy-200 text-sm" placeholder="Албан тушаал" />
                        <select v-model="editState.department_id" class="rounded-md border-brand-navy-200 text-sm">
                            <option value="">Хэлтэсгүй</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <input v-model="editState.password" type="password" class="rounded-md border-brand-navy-200 text-sm" placeholder="Шинэ нууц үг (заавал биш)" />
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="editState.is_admin" type="checkbox" class="rounded text-brand-orange-500" /> Супер админ</label>
                        <label class="flex items-center gap-2"><input v-model="editState.is_department_head" type="checkbox" class="rounded text-brand-orange-500" /> Хэлтсийн дарга</label>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-brand-navy-100">
                        <table class="min-w-full text-sm">
                            <thead class="bg-brand-navy-50 text-left text-xs">
                                <tr>
                                    <th class="px-3 py-2">Модуль</th>
                                    <th class="px-3 py-2">Эрх</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in modules" :key="m.key" class="border-t border-brand-navy-50">
                                    <td class="px-3 py-2">{{ m.label }}</td>
                                    <td class="px-3 py-2">
                                        <select
                                            class="rounded-md border-brand-navy-200 text-sm"
                                            :value="editState.permissions[m.key] || ''"
                                            @change="setLevel(m.key, $event.target.value)"
                                        >
                                            <option value="">Хаалттай</option>
                                            <option value="view">Харах</option>
                                            <option value="manage">Удирдах</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button class="rounded-lg bg-brand-navy-700 px-4 py-2 text-sm text-white">Хадгалах</button>
                </form>

                <form class="space-y-3 rounded-xl border border-dashed border-brand-navy-200 bg-white p-4" @submit.prevent="createUser">
                    <h3 class="font-semibold text-brand-navy-900">Шинэ албан хаагч</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="createForm.name" required placeholder="Нэр" class="rounded-md border-brand-navy-200 text-sm" />
                        <input v-model="createForm.email" type="email" required placeholder="И-мэйл" class="rounded-md border-brand-navy-200 text-sm" />
                        <input v-model="createForm.phone" placeholder="Утас" class="rounded-md border-brand-navy-200 text-sm" />
                        <input v-model="createForm.password" type="password" required placeholder="Нууц үг" class="rounded-md border-brand-navy-200 text-sm" />
                    </div>
                    <button class="rounded-lg bg-brand-orange-500 px-4 py-2 text-sm text-white">Нэмэх</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
