<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    users: Array,
    departments: Array,
    modules: Array,
    roles: Array,
    rolePermissions: Object,
    people: { type: Array, default: () => [] },
    heltesCount: { type: Number, default: 0 },
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

// ── Ролийн загвар ──
const roleTab = ref(props.roles?.[0]?.key ?? 'specialist');
const roleLabel = ref('');
const roleState = reactive({});

const loadRoles = () => {
    props.roles?.forEach((r) => {
        roleState[r.key] = { ...(props.rolePermissions?.[r.key] ?? {}) };
    });
};

loadRoles();

watch(() => props.rolePermissions, loadRoles, { deep: true });

const activeRole = computed(() => props.roles?.find((r) => r.key === roleTab.value) ?? null);

watch(activeRole, (role) => {
    roleLabel.value = role?.label ?? '';
}, { immediate: true });

const setRoleLevel = (roleKey, moduleKey, level) => {
    if (!level) {
        delete roleState[roleKey][moduleKey];
        return;
    }
    roleState[roleKey][moduleKey] = level;
};

const saveRole = () => {
    if (!activeRole.value) return;
    router.patch(route('admin.roles.update', activeRole.value.key), {
        permissions: { ...roleState[activeRole.value.key] },
        label: activeRole.value.is_system ? null : roleLabel.value,
    }, { preserveScroll: true });
};

// ── Шинэ роль ──
const newRole = reactive({ open: false, label: '', copy_from: '' });

const addRole = () => {
    if (!newRole.label.trim()) return;

    router.post(route('admin.roles.store'), {
        label: newRole.label.trim(),
        copy_from: newRole.copy_from || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newRole.open = false;
            newRole.label = '';
            newRole.copy_from = '';
        },
    });
};

const removeRole = () => {
    if (!activeRole.value || activeRole.value.is_system) return;
    if (!confirm(`«${activeRole.value.label}» ролийг устгах уу?`)) return;

    router.delete(route('admin.roles.destroy', activeRole.value.key), {
        preserveScroll: true,
        onSuccess: () => {
            roleTab.value = props.roles?.[0]?.key ?? '';
        },
    });
};

// Тухайн ролийн загварыг сонгосон албан хаагчид хэрэглэнэ.
const applyRoleToUser = (roleKey) => {
    editState.permissions = { ...(roleState[roleKey] ?? {}) };
};

// Ролийн чагтыг асаахад тухайн загварын эрхүүд шууд бөглөгдөнө.
const toggleRole = (role, checked) => {
    editState[role.field] = checked;
    if (checked) {
        applyRoleToUser(role.key);
    }
};

const roleSummary = (roleKey) => {
    const entries = Object.entries(roleState[roleKey] ?? {})
        .filter(([key]) => props.modules.some((m) => m.key === key));

    if (entries.length === 0) {
        return 'Бүх модуль хаалттай';
    }

    const manage = entries.filter(([, l]) => l === 'manage').length;

    return entries.length + ' модуль нээлттэй · ' + manage + ' удирдах';
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

const provisioning = ref(false);

const provisionHeltes = () => {
    if (props.heltesCount < 1) {
        return;
    }

    const ok = confirm(
        'Хэлтэс ангиллын ' + props.heltesCount + ' албан хаагчид нэвтрэх эрх өгөх үү?\n\n'
        + 'Нэвтрэх нэр: гар утас\n'
        + 'Нууц үг: утасны сүүлийн 4 орон + латин нэр\n'
        + 'Жишээ: 99178904 / 8904Nomin',
    );

    if (! ok) {
        return;
    }

    provisioning.value = true;
    router.post(route('admin.users.provision-heltes'), {}, {
        preserveScroll: true,
        onFinish: () => {
            provisioning.value = false;
        },
    });
};

/** Утасны жагсаалтаас сонгоход нэр, утас, албан тушаал, хэлтэс бөглөнө. */
const pickFromDirectory = (value) => {
    const person = props.people.find((p) => p.value === value);
    if (! person) {
        createForm.name = value || '';
        return;
    }

    createForm.name = person.full_name || person.label || value;
    createForm.phone = person.phone || '';
    createForm.position = person.position || '';

    const org = String(person.org || '').toLowerCase();
    if (org) {
        const dept = props.departments.find((d) => {
            const name = String(d.name || '').toLowerCase();
            return name && (org === name || org.includes(name) || name.includes(org));
        });
        createForm.department_id = dept?.id ?? '';
    }
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
                <section class="ui-card-pad space-y-3">
                    <h3 class="ui-title text-base">Хэлтсийн албан хаагчид</h3>
                    <p class="text-xs text-slate-500">
                        Утасны жагсаалтын «Хэлтэс»-т бүртгэлтэй
                        <b>{{ heltesCount }}</b> хүнд нэвтрэх эрх өгнө.
                        Нэвтрэх нэр — гар утас. Нууц үг — утасны сүүлийн 4 орон + латин нэр
                        (жишээ: А.Номин, 99178904 → <b>8904Nomin</b>).
                    </p>
                    <button
                        type="button"
                        class="ui-btn-accent"
                        :disabled="provisioning || heltesCount < 1"
                        @click="provisionHeltes"
                    >
                        {{ provisioning ? 'Өгч байна…' : 'Хэлтэст бүгдэд эрх өгөх' }}
                    </button>
                </section>

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
                    <div class="space-y-2">
                        <div class="flex flex-wrap gap-4 text-sm font-medium text-slate-700">
                            <label v-for="r in roles.filter((x) => x.field)" :key="r.key" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="rounded text-brand-navy-600"
                                    :checked="editState[r.field]"
                                    @change="toggleRole(r, $event.target.checked)"
                                />
                                {{ r.label }}
                            </label>
                        </div>
                        <p class="text-xs text-slate-500">
                            Ролийг сонгоход доорх «Ролийн загвар»-т заасан эрхүүд автоматаар бөглөгдөнө. Дараа нь модуль тус бүрд гараар засаж болно.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="r in roles"
                                :key="'apply-' + r.key"
                                type="button"
                                class="ui-btn-ghost !py-1 text-xs"
                                @click="applyRoleToUser(r.key)"
                            >
                                {{ r.label }} загвар хэрэглэх
                            </button>
                        </div>
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

                <section class="ui-card-pad space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="ui-title text-base">Ролийн загвар</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Түвшин тус бүрд ямар модульд ямар эрхтэй байхыг урьдчилан тодорхойлно.
                            </p>
                        </div>
                        <div class="ui-pill-row">
                            <button
                                v-for="r in roles"
                                :key="'tab-' + r.key"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                :class="roleTab === r.key ? 'bg-brand-navy-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                @click="roleTab = r.key"
                            >
                                {{ r.label }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-dashed border-brand-navy-300 px-3 py-1.5 text-xs font-semibold text-brand-navy-600 transition hover:bg-brand-navy-50"
                                @click="newRole.open = ! newRole.open"
                            >
                                + Роль нэмэх
                            </button>
                        </div>
                    </div>

                    <div v-if="newRole.open" class="rounded-xl border border-brand-navy-100 bg-slate-50/70 p-3">
                        <div class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <input
                                v-model="newRole.label"
                                class="ui-input"
                                placeholder="Ролийн нэр (жишээ: Архивч)"
                                @keyup.enter="addRole"
                            />
                            <select v-model="newRole.copy_from" class="ui-input">
                                <option value="">Хоосоноос эхлэх</option>
                                <option v-for="r in roles" :key="`copy-${r.key}`" :value="r.key">
                                    «{{ r.label }}»-г хуулах
                                </option>
                            </select>
                            <button type="button" class="ui-btn-accent whitespace-nowrap" @click="addRole">Нэмэх</button>
                        </div>
                    </div>

                    <div v-if="activeRole && ! activeRole.is_system" class="flex flex-wrap items-center gap-2">
                        <label class="text-xs font-medium text-slate-500">Ролийн нэр</label>
                        <input v-model="roleLabel" class="ui-input max-w-xs !py-1.5 text-sm" />
                    </div>

                    <p v-if="activeRole" class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        <b>{{ activeRole.label }}</b> — {{ roleSummary(activeRole.key) }}
                    </p>

                    <div v-if="activeRole" class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Модуль</th>
                                    <th class="w-40">Эрх</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in modules" :key="'role-' + m.key">
                                    <td>{{ m.label }}</td>
                                    <td>
                                        <select
                                            class="ui-input !py-1.5"
                                            :value="roleState[activeRole.key]?.[m.key] || ''"
                                            @change="setRoleLevel(activeRole.key, m.key, $event.target.value)"
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

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-primary" @click="saveRole">Загвар хадгалах</button>
                        <button
                            v-if="selected && activeRole"
                            type="button"
                            class="ui-btn-ghost"
                            @click="applyRoleToUser(activeRole.key)"
                        >
                            «{{ selected.name }}»-д хэрэглэх
                        </button>
                        <button
                            v-if="activeRole && ! activeRole.is_system"
                            type="button"
                            class="ui-btn-danger"
                            @click="removeRole"
                        >
                            Ролийг устгах
                        </button>
                    </div>
                </section>

                <form class="ui-card space-y-3 border-dashed p-5" @submit.prevent="createUser">
                    <h3 class="ui-title text-base">Шинэ албан хаагч</h3>
                    <p class="text-xs text-slate-500">
                        Утасны жагсаалтад бүртгэлтэй албан хаагчийг сонгоод нэвтрэх эрх өгнө.
                    </p>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50/60 p-2">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Албан хаагч (утасны жагсаалт)</label>
                            <SheetCell
                                v-model="createForm.name"
                                :editable="true"
                                :options="people"
                                placeholder="Нэрээр хайж сонгох…"
                                @commit="pickFromDirectory"
                            />
                        </div>
                        <input
                            v-model="createForm.phone"
                            placeholder="Утас (жагсаалтаас автоматаар)"
                            class="ui-input"
                        />
                        <input v-model="createForm.position" placeholder="Албан тушаал" class="ui-input" />
                        <input v-model="createForm.email" type="email" required placeholder="И-мэйл" class="ui-input" />
                        <input v-model="createForm.password" type="password" required placeholder="Нууц үг" class="ui-input" />
                    </div>
                    <p v-if="createForm.errors.name" class="text-xs text-rose-600">{{ createForm.errors.name }}</p>
                    <p v-if="createForm.errors.phone" class="text-xs text-rose-600">{{ createForm.errors.phone }}</p>
                    <p v-if="createForm.errors.email" class="text-xs text-rose-600">{{ createForm.errors.email }}</p>
                    <button class="ui-btn-accent" :disabled="createForm.processing || !createForm.name">Нэмэх</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
