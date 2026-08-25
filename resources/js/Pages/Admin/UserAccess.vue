<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    users: Array,
    departments: Array,
    modules: Array,
});

const page = usePage();

const notice = computed(() => {
    const flash = page.props.flash ?? {};
    if (flash.success) {
        return { type: 'success', text: flash.success };
    }
    if (flash.warning) {
        return { type: 'warning', text: flash.warning };
    }
    if (flash.info) {
        return { type: 'info', text: flash.info };
    }

    return null;
});

const noticeClass = computed(() => ({
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    info: 'border-sky-200 bg-sky-50 text-sky-800',
}[notice.value?.type] ?? ''));

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
    is_specialist: false,
});

const editState = reactive({
    name: '',
    email: '',
    phone: '',
    department_id: '',
    position: '',
    is_admin: false,
    is_department_head: false,
    is_specialist: false,
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
    editState.is_specialist = selected.value.is_specialist;
    editState.password = '';
    editState.permissions = { ...selected.value.permissions };
};

loadSelected();

watch(() => props.users, () => {
    loadSelected();
}, { deep: true });

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
    router.patch(route('admin.users.update', selected.value.id), { ...editState }, {
        preserveScroll: true,
        onSuccess: () => loadSelected(),
    });
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
        <div v-if="notice" class="mb-4 rounded-xl border px-4 py-3 text-sm shadow-sm" :class="noticeClass">
            {{ notice.text }}
        </div>

        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <div class="ui-card overflow-hidden">
                <div class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-brand-navy-800">Албан хаагчид</div>
                <button
                    v-for="u in users"
                    :key="u.id"
                    type="button"
                    class="flex w-full flex-col border-b border-slate-50 px-4 py-3 text-left text-sm transition hover:bg-brand-navy-50"
                    :class="selectedId === u.id ? 'bg-brand-navy-50' : ''"
                    @click="selectUser(u.id)"
                >
                    <span class="font-semibold text-brand-navy-800">{{ u.name }}</span>
                    <span class="text-xs text-slate-400">{{ u.email }} · {{ u.phone || 'утасгүй' }}</span>
                </button>
            </div>

            <div class="space-y-4">
                <form v-if="selected" class="ui-card-pad space-y-4" @submit.prevent="saveUser">
                    <h3 class="ui-title text-base">Эрх тохируулах — {{ selected.name }}</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="editState.name" class="ui-input" placeholder="Нэр" required />
                        <input v-model="editState.email" type="email" class="ui-input" placeholder="И-мэйл" required />
                        <input v-model="editState.phone" class="ui-input" placeholder="Утас" />
                        <input v-model="editState.position" class="ui-input" placeholder="Албан тушаал" />
                        <select v-model="editState.department_id" class="ui-input">
                            <option value="">Хэлтэсгүй</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                        <input v-model="editState.password" type="password" class="ui-input" placeholder="Шинэ нууц үг (заавал биш)" />
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm font-medium text-slate-700">
                        <label class="flex items-center gap-2"><input v-model="editState.is_admin" type="checkbox" class="rounded text-brand-navy-600" /> Супер админ</label>
                        <label class="flex items-center gap-2"><input v-model="editState.is_department_head" type="checkbox" class="rounded text-brand-navy-600" /> Хэлтсийн дарга</label>
                        <label class="flex items-center gap-2"><input v-model="editState.is_specialist" type="checkbox" class="rounded text-brand-navy-600" /> Мэргэжилтэн</label>
                    </div>

                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Модуль</th>
                                    <th>Эрх</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in modules" :key="m.key">
                                    <td>{{ m.label }}</td>
                                    <td>
                                        <select
                                            class="ui-input !py-1.5"
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
                    <button class="ui-btn-primary">Хадгалах</button>
                </form>

                <form class="ui-card space-y-3 border-dashed p-5" @submit.prevent="createUser">
                    <h3 class="ui-title text-base">Шинэ албан хаагч</h3>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="createForm.name" required placeholder="Нэр" class="ui-input" />
                        <input v-model="createForm.email" type="email" required placeholder="И-мэйл" class="ui-input" />
                        <input v-model="createForm.phone" placeholder="Утас" class="ui-input" />
                        <input v-model="createForm.password" type="password" required placeholder="Нууц үг" class="ui-input" />
                    </div>
                    <button class="ui-btn-accent">Нэмэх</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
