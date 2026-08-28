<script setup>
import { computed, reactive, ref, toRaw, watch } from 'vue';
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
    const errors = page.props.errors ?? {};
    const errorText = Object.values(errors)
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(Boolean)
        .join(' ');
    if (errorText) {
        return { type: 'warning', text: errorText };
    }
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

const selectedId = ref(null);
const selected = computed(() => props.users.find((u) => u.id === selectedId.value) || null);
const userSearch = ref('');

const queryParam = (name) => {
    const href = page.url || '';
    const qIndex = href.indexOf('?');
    if (qIndex === -1) {
        return '';
    }

    return new URLSearchParams(href.slice(qIndex)).get(name) || '';
};

const initialTab = queryParam('tab');
/** employee | templates | create */
const panelMode = ref(['employee', 'templates', 'create'].includes(initialTab) ? initialTab : 'employee');
/** Албан хаагчид оноож буй ролийн түлхүүр */
const selectedRoleKey = ref('');

const departmentName = (id) => {
    if (! id) {
        return 'Хэлтэсгүй';
    }

    return props.departments.find((d) => d.id === id)?.name || 'Хэлтэсгүй';
};

const filteredUsers = computed(() => {
    const q = userSearch.value.trim().toLocaleLowerCase('mn');

    if (! q) {
        return props.users;
    }

    return props.users.filter((u) => {
        const haystack = [u.name, u.email, u.phone, u.position, u.department]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('mn');

        return haystack.includes(q);
    });
});

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
    if (!selected.value) {
        selectedRoleKey.value = '';
        return;
    }
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
    selectedRoleKey.value = detectRoleKey(selected.value);
};

watch(() => props.users, () => {
    loadSelected();
}, { deep: true });

const selectUser = (id) => {
    selectedId.value = id;
    panelMode.value = 'employee';
    loadSelected();
};

// ── Ролийн загвар ──
const initialRole = queryParam('role');
const roleTab = ref(
    (initialRole && props.roles?.some((r) => r.key === initialRole) ? initialRole : null)
        || props.roles?.[0]?.key
        || 'specialist',
);
const roleLabel = ref('');
const roleState = reactive({});
const rolesDirty = ref(false);

const loadRoles = (force = false) => {
    if (rolesDirty.value && ! force) {
        return;
    }
    props.roles?.forEach((r) => {
        const next = { ...(props.rolePermissions?.[r.key] ?? {}) };
        props.modules?.forEach((m) => {
            if (! Object.prototype.hasOwnProperty.call(next, m.key)) {
                next[m.key] = '';
            }
        });
        roleState[r.key] = next;
    });
};

loadRoles(true);

watch(() => props.rolePermissions, () => loadRoles(), { deep: true });

const activeRole = computed(() => props.roles?.find((r) => r.key === roleTab.value) ?? null);

watch(activeRole, (role) => {
    roleLabel.value = role?.label ?? '';
}, { immediate: true });

const cleanPermissions = (raw = {}) => Object.fromEntries(
    Object.entries(raw || {}).filter(([, level]) => Boolean(level) && level !== '__none__'),
);

const saving = ref(false);

const saveRole = () => {
    if (!activeRole.value || saving.value) return;
    saving.value = true;
    const payload = {
        permissions: cleanPermissions({ ...(toRaw(roleState[activeRole.value.key]) || {}) }),
    };
    if (! activeRole.value.is_system) {
        payload.label = roleLabel.value;
    }
    router.patch(route('admin.roles.update', { role: activeRole.value.key }), payload, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => { rolesDirty.value = false; },
        onFinish: () => { saving.value = false; },
    });
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

const permissionsMatch = (a = {}, b = {}) => {
    const keys = new Set([...Object.keys(a), ...Object.keys(b)]);

    for (const key of keys) {
        if ((a[key] || '') !== (b[key] || '')) {
            return false;
        }
    }

    return true;
};

const detectRoleKey = (user) => {
    if (! user) {
        return '';
    }

    for (const role of props.roles ?? []) {
        if (role.field && user[role.field]) {
            return role.key;
        }
    }

    for (const role of (props.roles ?? []).filter((r) => ! r.field)) {
        if (permissionsMatch(user.permissions, roleState[role.key] ?? {})) {
            return role.key;
        }
    }

    return '';
};

// Тухайн ролийн загварыг сонгосон албан хаагчид хэрэглэнэ.
const applyRoleToUser = (roleKey) => {
    selectedRoleKey.value = roleKey;
    editState.permissions = { ...cleanPermissions(roleState[roleKey]) };

    editState.is_admin = false;
    editState.is_department_head = false;
    editState.is_specialist = false;

    const role = props.roles?.find((r) => r.key === roleKey);
    if (role?.field) {
        editState[role.field] = true;
    }
};

const applyRoleToSelectedAndSave = () => {
    if (! selected.value || ! activeRole.value || saving.value) return;
    applyRoleToUser(activeRole.value.key);
    panelMode.value = 'employee';
    saveUser();
};

/** Сонгосон албан хаагчийн одоогийн роль — хадгалагдсан төлөв. */
const selectedRoleLabel = computed(() => {
    if (! selected.value) {
        return '';
    }

    const key = detectRoleKey(selected.value);
    if (key) {
        return props.roles?.find((r) => r.key === key)?.label ?? '';
    }

    return 'Роль тохируулаагүй';
});

const userRoleLabels = (user) => {
    const key = detectRoleKey(user);
    if (key) {
        const label = props.roles?.find((r) => r.key === key)?.label;
        return label ? [label] : ['Гараар'];
    }

    if (user.is_admin) {
        return ['Супер админ'];
    }
    if (user.is_department_head) {
        return ['Хэлтсийн дарга'];
    }
    if (user.is_specialist) {
        return ['Мэргэжилтэн'];
    }

    return ['Рольгүй'];
};

const roleSummary = (roleKey) => {
    const entries = Object.entries(roleState[roleKey] ?? {})
        .filter(([key, level]) => Boolean(level) && props.modules.some((m) => m.key === key));

    if (entries.length === 0) {
        return 'Бүх модуль хаалттай';
    }

    const manage = entries.filter(([, l]) => l === 'manage' || l === 'manage_own').length;
    const edit = entries.filter(([, l]) => l === 'edit' || l === 'edit_own').length;

    return entries.length + ' модуль нээлттэй · ' + edit + ' оруулах · ' + manage + ' удирдах';
};

const levelOptions = (module) => {
    const options = [{ value: '', label: 'Хаалттай' }];

    if (module.own_scope) {
        options.push(
            { value: 'view_own', label: 'Харах (хамааралтай)' },
            { value: 'edit_own', label: 'Оруулах (хамааралтай)' },
            { value: 'manage_own', label: 'Удирдах (хамааралтай)' },
        );
    }

    options.push(
        { value: 'view', label: 'Харах (бүгд)' },
        { value: 'edit', label: 'Оруулах (бүгд)' },
        { value: 'manage', label: 'Удирдах (бүгд)' },
    );

    return options;
};

const saveUser = () => {
    if (!selected.value || saving.value) return;
    saving.value = true;
    router.patch(route('admin.users.update', selected.value.id), {
        name: selected.value.name,
        email: selected.value.email,
        phone: selected.value.phone || '',
        department_id: selected.value.department_id || '',
        position: selected.value.position || '',
        is_admin: editState.is_admin,
        is_department_head: editState.is_department_head,
        is_specialist: editState.is_specialist,
        permissions: cleanPermissions(editState.permissions),
    }, {
        preserveScroll: true,
        onSuccess: () => loadSelected(),
        onFinish: () => { saving.value = false; },
    });
};

const createUser = () => {
    createForm.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            panelMode.value = 'employee';
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
        <div class="flex min-h-0 flex-col gap-4 lg:h-[calc(100dvh-9rem)]">
        <div v-if="notice" class="shrink-0 rounded-xl border px-4 py-3 text-sm shadow-sm" :class="noticeClass">
            {{ notice.text }}
        </div>

        <div class="grid min-h-0 flex-1 gap-4 overflow-hidden lg:grid-cols-[280px_1fr]">
            <aside class="ui-card flex max-h-[42vh] min-h-0 flex-col overflow-hidden lg:max-h-none">
                <div class="shrink-0 space-y-2 border-b border-slate-100 px-3 py-3">
                    <div class="px-1 text-sm font-bold text-brand-navy-800">
                        Албан хаагчид
                        <span class="ml-1 font-medium text-slate-400">
                            {{ userSearch.trim() ? `${filteredUsers.length}/${users.length}` : users.length }}
                        </span>
                    </div>
                    <input
                        v-model="userSearch"
                        type="search"
                        class="ui-input !py-2 text-sm"
                        placeholder="Нэр, и-мэйл, утсаар хайх…"
                        autocomplete="off"
                    />
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                    <p v-if="!filteredUsers.length" class="px-4 py-8 text-center text-sm text-slate-400">
                        Тохирох албан хаагч алга.
                    </p>
                    <button
                        v-for="u in filteredUsers"
                        :key="u.id"
                        type="button"
                        class="flex w-full flex-col border-b border-slate-50 px-4 py-3 text-left text-sm transition hover:bg-brand-navy-50"
                        :class="selectedId === u.id ? 'bg-brand-navy-50' : ''"
                        @click="selectUser(u.id)"
                    >
                        <span class="font-semibold text-brand-navy-800">{{ u.name }}</span>
                        <span class="text-xs text-slate-400">{{ u.email }} · {{ u.phone || 'утасгүй' }}</span>
                        <span class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="label in userRoleLabels(u)"
                                :key="u.id + '-' + label"
                                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                            >
                                {{ label }}
                            </span>
                        </span>
                    </button>
                </div>
            </aside>

            <div class="min-h-0 space-y-4 overflow-y-auto overscroll-contain">
                <div class="ui-pill-row shrink-0">
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="panelMode === 'employee' ? 'bg-brand-navy-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="panelMode = 'employee'"
                    >
                        Албан хаагчийн эрх
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="panelMode === 'templates' ? 'bg-brand-navy-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="panelMode = 'templates'"
                    >
                        Ролийн загвар
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="panelMode === 'create' ? 'bg-brand-navy-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="panelMode = 'create'"
                    >
                        Шинэ албан хаагч
                    </button>
                </div>

                <template v-if="panelMode === 'employee'">
                <div
                    v-if="!selected"
                    class="ui-card-pad flex min-h-[12rem] flex-col items-center justify-center gap-2 text-center"
                >
                    <p class="text-sm font-semibold text-brand-navy-800">Албан хаагч сонгоно уу</p>
                    <p class="max-w-sm text-xs text-slate-500">
                        Зүүн жагсаалтаас албан хаагч дээр дарж роль тохируулна.
                    </p>
                </div>

                <form v-else class="ui-card-pad space-y-4" @submit.prevent="saveUser">
                    <div>
                        <h3 class="ui-title text-base">{{ selected.name }}</h3>
                        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                            <span>Одоогийн роль:</span>
                            <span class="rounded-full bg-brand-navy-50 px-2.5 py-0.5 font-semibold text-brand-navy-700">
                                {{ selectedRoleLabel }}
                            </span>
                        </p>
                    </div>

                    <dl class="grid gap-3 rounded-xl border border-slate-100 bg-slate-50/50 p-3 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Нэр</dt>
                            <dd class="mt-0.5 font-medium text-brand-navy-800">{{ selected.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">И-мэйл</dt>
                            <dd class="mt-0.5 font-medium text-brand-navy-800">{{ selected.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Утас</dt>
                            <dd class="mt-0.5 font-medium text-brand-navy-800">{{ selected.phone || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-400">Албан тушаал</dt>
                            <dd class="mt-0.5 font-medium text-brand-navy-800">{{ selected.position || '—' }}</dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="text-xs font-medium text-slate-400">Хэлтэс</dt>
                            <dd class="mt-0.5 font-medium text-brand-navy-800">{{ departmentName(selected.department_id) }}</dd>
                        </div>
                    </dl>

                    <div class="space-y-2 rounded-xl border border-brand-navy-100 bg-slate-50/60 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-brand-navy-800">Роль сонгох</p>
                            <button
                                type="button"
                                class="text-xs font-semibold text-brand-navy-600 underline-offset-2 hover:underline"
                                @click="panelMode = 'templates'"
                            >
                                Загвар засварлах →
                            </button>
                        </div>
                        <p class="text-xs text-slate-500">
                            Роль сонгоход тухайн загварын модуль эрх автоматаар оноогдоно. Хадгалах товч дарж баталгаажуулна.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="r in roles"
                                :key="'apply-' + r.key"
                                type="button"
                                class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                :class="selectedRoleKey === r.key
                                    ? 'bg-brand-navy-600 text-white shadow-sm'
                                    : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-brand-navy-50 hover:text-brand-navy-800'"
                                @click="applyRoleToUser(r.key)"
                            >
                                {{ r.label }}
                            </button>
                        </div>
                        <p v-if="selectedRoleKey" class="text-xs text-slate-500">
                            Сонгосон: <b>{{ roles.find((r) => r.key === selectedRoleKey)?.label }}</b>
                            — {{ roleSummary(selectedRoleKey) }}
                        </p>
                    </div>

                    <button class="ui-btn-primary" :disabled="saving">Хадгалах</button>
                </form>
                </template>

                <form
                    v-else-if="panelMode === 'create'"
                    class="ui-card-pad space-y-4"
                    @submit.prevent="createUser"
                >
                    <div>
                        <h3 class="ui-title text-base">Шинэ албан хаагч</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Утасны жагсаалтад бүртгэлтэй албан хаагчийг сонгоод нэвтрэх эрх өгнө.
                        </p>
                    </div>
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
                        <select v-model="createForm.department_id" class="ui-input md:col-span-2">
                            <option value="">Хэлтэсгүй</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <p v-if="createForm.errors.name" class="text-xs text-rose-600">{{ createForm.errors.name }}</p>
                    <p v-if="createForm.errors.phone" class="text-xs text-rose-600">{{ createForm.errors.phone }}</p>
                    <p v-if="createForm.errors.email" class="text-xs text-rose-600">{{ createForm.errors.email }}</p>
                    <button class="ui-btn-accent" :disabled="createForm.processing || !createForm.name">Нэмэх</button>
                </form>

                <section v-else class="ui-card-pad space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="ui-title text-base">Ролийн загвар</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Түвшин тус бүрд ямар модульд ямар эрхтэй байхыг урьдчилан тодорхойлно.
                                Загварыг хадгалсны дараа «Албан хаагчийн эрх» табаас хэрэглэнэ.
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
                                            v-model="roleState[activeRole.key][m.key]"
                                            @change="rolesDirty = true"
                                        >
                                            <option
                                                v-for="option in levelOptions(m)"
                                                :key="'role-' + option.value + option.label"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-primary" :disabled="saving" @click="saveRole">Загвар хадгалах</button>
                        <button
                            v-if="selected && activeRole"
                            type="button"
                            class="ui-btn-ghost"
                            :disabled="saving"
                            @click="applyRoleToSelectedAndSave"
                        >
                            «{{ selected.name }}»-д хэрэглээд буцах
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
            </div>
        </div>
        </div>
    </AuthenticatedLayout>
</template>
