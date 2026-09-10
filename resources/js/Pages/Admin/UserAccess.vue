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
    passwordForm.reset();
    passwordForm.clearErrors();
    showPassword.value = false;
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

/**
 * Ролийн эрхийн төлөвийг бэлдэнэ.
 *
 * Төлөв нь бэлдэгдээгүй байхад хүснэгт зурагдвал алдаа өгч, тухайн роль
 * сонгогдохгүй болдог тул хаанаас ч дуудаж болохоор тусад нь гаргав.
 */
const ensureRoleState = (key) => {
    if (! key) return null;

    if (! roleState[key]) {
        roleState[key] = {};
    }

    const next = roleState[key];

    props.modules?.forEach((m) => {
        if (! Object.prototype.hasOwnProperty.call(next, m.key)) {
            next[m.key] = '';
        }
    });

    return next;
};

const loadRoles = (force = false) => {
    props.roles?.forEach((r) => {
        // Хэрэглэгчийн засварлаж байгаа ролийг дарж бичихгүй, харин
        // шинээр нэмэгдсэн ролийг заавал бэлдэнэ (үгүй бол хоосон хадгалагдана).
        if (! force && rolesDirty.value && roleState[r.key]) {
            return;
        }

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

// Шинэ роль нэмэгдмэгц түүний төлөвийг бэлдэнэ (эрх нь хоосон байсан ч).
watch(() => props.roles, (roles) => (roles ?? []).forEach((r) => ensureRoleState(r.key)), { deep: true });

const activeRole = computed(() => props.roles?.find((r) => r.key === roleTab.value) ?? null);

watch(activeRole, (role) => {
    roleLabel.value = role?.label ?? '';
    // Шинээр үүсгэсэн ролийн төлөв дутуу байвал энд бэлдэгдэнэ.
    ensureRoleState(role?.key);
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

    const known = new Set((props.roles ?? []).map((r) => r.key));

    router.post(route('admin.roles.store'), {
        label: newRole.label.trim(),
        copy_from: newRole.copy_from || null,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            newRole.open = false;
            newRole.label = '';
            newRole.copy_from = '';

            // Шинэ роль руу шууд шилжинэ — эрхээ тэр дор нь тохируулна.
            const added = (page.props.roles ?? []).find((r) => ! known.has(r.key));

            if (added) {
                rolesDirty.value = false;
                roleTab.value = added.key;
            }
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

    // Хадгалсан роль байвал түүнийг шууд авна (эрхгүй роль ч танигдана).
    if (user.role_key && (props.roles ?? []).some((r) => r.key === user.role_key)) {
        return user.role_key;
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
    editState.permissions = { ...cleanPermissions(ensureRoleState(roleKey) ?? {}) };

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
/**
 * Сонгосон албан хаагчид одоо хүчинтэй байгаа эрхүүд.
 *
 * Роль болон хэрэглэгч дээр тусад нь өгсөн эрхийг нэгтгэсэн бодит хандалт —
 * ролийн загварт юу тохируулсныг энд шалгаж болно.
 */
const levelText = (level) => ({
    view: 'Харах (бүгд)',
    edit: 'Оруулах (бүгд)',
    manage: 'Удирдах (бүгд)',
    view_own: 'Харах (хамааралтай)',
    edit_own: 'Оруулах (хамааралтай)',
    manage_own: 'Удирдах (хамааралтай)',
    closed: 'Хаалттай',
}[level] ?? level);

const effectiveList = computed(() => {
    const permissions = selected.value?.permissions ?? {};

    return (props.modules ?? [])
        .filter((m) => permissions[m.key])
        .map((m) => ({
            key: m.key,
            label: m.label,
            parent: m.parent,
            level: levelText(permissions[m.key]),
        }));
});

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
    // Дэд мөр: хоосон = дээд мөрийг дагана, «closed» = үл хамааран хаана.
    const options = module.parent
        ? [
            { value: '', label: 'Дээд мөрийг дагах' },
            { value: 'closed', label: 'Хаалттай' },
        ]
        : [{ value: '', label: 'Хаалттай' }];

    if (module.own_scope) {
        const labels = {
            view_own: 'Харах (хамааралтай)',
            edit_own: 'Оруулах (хамааралтай)',
            manage_own: 'Удирдах (хамааралтай)',
        };
        const levels = Array.isArray(module.own_levels) && module.own_levels.length
            ? module.own_levels
            : ['view_own', 'edit_own', 'manage_own'];

        levels.forEach((value) => {
            if (labels[value]) {
                options.push({ value, label: labels[value] });
            }
        });
    }

    options.push(
        { value: 'view', label: 'Харах (бүгд)' },
        { value: 'edit', label: 'Оруулах (бүгд)' },
        { value: 'manage', label: 'Удирдах (бүгд)' },
    );

    return options;
};

/**
 * Нэвтрэх нууц үг шинэчлэх — ролийн хадгалалтаас тусдаа хүсэлт.
 *
 * Ингэснээр эрх хадгалахад нууц үг санамсаргүй солигдохгүй.
 */
const passwordForm = useForm({ password: '', notify: true });
const showPassword = ref(false);

/** Уншихад ойлгомжтой түр нууц үг — андуурч болзошгүй тэмдэгтгүй. */
const suggestPassword = () => {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const pool = upper + lower + digits;
    const pick = (set) => set[Math.floor(Math.random() * set.length)];

    const chars = [pick(upper), pick(lower), pick(digits)];
    while (chars.length < 10) chars.push(pick(pool));

    for (let i = chars.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }

    passwordForm.password = chars.join('');
    showPassword.value = true;
};

/** Утасны жагсаалтын дугаартай зөрж байгаа бүртгэлүүд. */
const mismatchedLogins = computed(
    () => props.users.filter((u) => u.directory_phone && ! u.login_matches_directory),
);

/** Нэвтрэх нэрийг утасны жагсаалтын дугаараар солино. */
const syncingLogin = ref(false);

const syncLogin = () => {
    if (! selected.value || syncingLogin.value) return;

    syncingLogin.value = true;
    router.patch(route('admin.users.login', selected.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { syncingLogin.value = false; },
    });
};

const syncAllLogins = () => {
    if (! confirm('Бүх бүртгэлийн нэвтрэх нэрийг утасны жагсаалтын дугаараар шинэчлэх үү?')) return;

    router.post(route('admin.users.sync-logins'), {}, { preserveScroll: true });
};

const savePassword = () => {
    if (! selected.value) return;

    passwordForm.patch(route('admin.users.password', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset('password');
            showPassword.value = false;
        },
    });
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
        role_key: selectedRoleKey.value || null,
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
                    <div
                        v-if="mismatchedLogins.length"
                        class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-2 text-[11px] leading-relaxed text-rose-700"
                    >
                        {{ mismatchedLogins.length }} бүртгэлийн нэвтрэх нэр утасны жагсаалтын дугаартай зөрж байна.
                        <button
                            type="button"
                            class="mt-1 block font-semibold underline underline-offset-2 hover:text-rose-900"
                            @click="syncAllLogins"
                        >
                            Бүгдийг жагсаалтаар тулгах →
                        </button>
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
                            <span
                                v-if="selected.is_root_admin"
                                class="rounded-full bg-emerald-50 px-2.5 py-0.5 font-semibold text-emerald-700"
                            >
                                Үндсэн супер админ
                            </span>
                        </p>
                        <p v-if="selected.is_root_admin" class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                            Энэ бол системийн үндсэн супер админ. Супер админ эрх нь хасагдахгүй,
                            нэвтрэх нэр нь өөрчлөгдөхгүй, устгагдахгүй — бүх эрх санамсаргүй
                            хасагдсан ч системд эргэж орох арга үлдэнэ. Нууц үгийг нь доороос солино.
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

                    <!-- Одоо хүчинтэй эрх — ролиос болон хувь хүнээс нийлсэн дүн -->
                    <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                        <p class="text-sm font-semibold text-brand-navy-800">
                            Одоо хүчинтэй эрх
                            <span class="ml-1 text-xs font-normal text-slate-400">
                                ({{ effectiveList.length }} модуль)
                            </span>
                        </p>
                        <p v-if="! effectiveList.length" class="text-xs text-slate-500">
                            Эрх алга. Роль сонгоод «Хадгалах» дарна уу, эсвэл «Ролийн загвар» табаас
                            тухайн ролийн эрхийг тохируулна уу.
                        </p>
                        <ul v-else class="grid gap-x-4 gap-y-1 sm:grid-cols-2">
                            <li
                                v-for="item in effectiveList"
                                :key="'eff-' + item.key"
                                class="flex items-center justify-between gap-2 text-xs"
                                :class="item.parent ? 'pl-3 text-slate-500' : 'text-slate-700'"
                            >
                                <span class="truncate">
                                    <span v-if="item.parent" class="mr-1 text-slate-300">└</span>{{ item.label }}
                                </span>
                                <span class="shrink-0 font-medium text-brand-navy-700">{{ item.level }}</span>
                            </li>
                        </ul>
                    </div>

                    <button class="ui-btn-primary" :disabled="saving">Хадгалах</button>

                    <!-- Нууц үг — ролийн хадгалалтаас тусдаа -->
                    <div class="space-y-2 rounded-xl border border-amber-200 bg-amber-50/50 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-brand-navy-800">Нэвтрэх нэр, нууц үг</p>
                            <span class="text-xs text-slate-500">
                                Нэвтрэх нэр: <b class="text-brand-navy-700">{{ selected.phone || selected.email }}</b>
                            </span>
                        </div>

                        <!-- Нэвтрэх нэр нь утасны жагсаалтын дугаар байх ёстой -->
                        <div
                            v-if="selected.directory_phone && ! selected.login_matches_directory"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2"
                        >
                            <p class="text-xs text-rose-700">
                                Утасны жагсаалтад
                                <b>{{ selected.directory_phone }}</b>
                                гэж бүртгэлтэй байна — нэвтрэх нэр нь энэ дугаар байх ёстой.
                            </p>
                            <button
                                type="button"
                                class="ui-btn-ghost !py-1 !text-xs"
                                :disabled="syncingLogin"
                                @click="syncLogin"
                            >
                                {{ syncingLogin ? 'Солиж байна…' : 'Жагсаалтын дугаараар солих' }}
                            </button>
                        </div>
                        <p
                            v-else-if="! selected.directory_phone"
                            class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
                        >
                            Энэ албан хаагч утасны жагсаалтад олдсонгүй. Нэвтрэх нэр нь жагсаалтад
                            бүртгэлтэй дугаар байх ёстой тул эхлээд «Утасны жагсаалт»-д бүртгэнэ үү.
                        </p>
                        <p v-else class="text-xs text-emerald-700">
                            Нэвтрэх нэр нь утасны жагсаалтын дугаартай таарч байна.
                        </p>

                        <p class="text-xs text-slate-500">
                            Хуучин нууц үгийг харах боломжгүй (шифрлэгдсэн). Мартсан бол шинээр тавьж өгнө.
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative min-w-[12rem] flex-1">
                                <input
                                    v-model="passwordForm.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="new-password"
                                    placeholder="Шинэ нууц үг (8-аас дээш тэмдэгт)"
                                    class="ui-input pr-10"
                                />
                                <button
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-brand-navy-700"
                                    :title="showPassword ? 'Нуух' : 'Харах'"
                                    :aria-label="showPassword ? 'Нуух' : 'Харах'"
                                    @click="showPassword = ! showPassword"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path v-if="! showPassword" stroke-linecap="round" d="M4 20L20 4" />
                                    </svg>
                                </button>
                            </div>
                            <button type="button" class="ui-btn-ghost" @click="suggestPassword">
                                Санамсаргүй үүсгэх
                            </button>
                            <button
                                type="button"
                                class="ui-btn-accent"
                                :disabled="passwordForm.processing || passwordForm.password.length < 8"
                                @click="savePassword"
                            >
                                {{ passwordForm.processing ? 'Хадгалж байна…' : 'Нууц үг шинэчлэх' }}
                            </button>
                        </div>

                        <label
                            v-if="selected.phone"
                            class="flex items-center gap-2 text-xs font-medium text-slate-600"
                        >
                            <input
                                v-model="passwordForm.notify"
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                            />
                            Шинэ нууц үгийг {{ selected.phone }} дугаар руу SMS-ээр илгээх
                        </label>

                        <p v-if="passwordForm.errors.password" class="text-xs text-rose-600">
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>
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
                                <tr
                                    v-for="m in (roleState[activeRole.key] ? modules : [])"
                                    :key="'role-' + m.key"
                                    :class="m.parent ? 'bg-slate-50/60' : ''"
                                >
                                    <td :class="m.parent ? 'pl-8 text-sm text-slate-600' : ''">
                                        <span v-if="m.parent" class="mr-1 text-slate-300">└</span>{{ m.label }}
                                        <span v-if="m.parent" class="ml-1 text-[10px] text-slate-400">
                                            (тохируулаагүй бол дээд мөрийг дагана)
                                        </span>
                                    </td>
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
