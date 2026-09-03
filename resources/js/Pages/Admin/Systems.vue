<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import PushSubscribe from '@/Components/PushSubscribe.vue';
import EmployeePicker from '@/Components/EmployeePicker.vue';

const props = defineProps({
    systems: Array,
    employees: { type: Array, default: () => [] },
    ai: Object,
    menus: { type: Array, default: () => [] },
    menuGroups: { type: Array, default: () => [] },
    aiModules: { type: Array, default: () => [] },
});

const page = usePage();

const SETTINGS_TABS = [
    { id: 'menus', label: 'Цэс нээх / хаах' },
    { id: 'ai', label: 'Manage AI' },
    { id: 'push', label: 'Push мэдэгдэл' },
    { id: 'systems', label: 'Гадны системүүд' },
];

const readSettingsTab = () => {
    const allowed = SETTINGS_TABS.map((t) => t.id);
    try {
        const fromUrl = new URL(window.location.href).searchParams.get('tab');
        if (allowed.includes(fromUrl)) {
            return fromUrl;
        }
    } catch {
        // ignore
    }
    try {
        const stored = sessionStorage.getItem('admin_settings_tab');
        if (allowed.includes(stored)) {
            return stored;
        }
    } catch {
        // ignore
    }

    return 'menus';
};

const activeTab = ref(readSettingsTab());

watch(() => page.url, () => {
    const next = readSettingsTab();
    if (next && next !== activeTab.value) {
        activeTab.value = next;
        try {
            sessionStorage.setItem('admin_settings_tab', next);
        } catch {
            // ignore
        }
    }
});

const selectTab = (id) => {
    activeTab.value = id;
    try {
        sessionStorage.setItem('admin_settings_tab', id);
    } catch {
        // ignore
    }
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', id);
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`);
    } catch {
        // ignore
    }
};

const settingsTabs = computed(() => SETTINGS_TABS.map((tab) => (
    tab.id === 'ai'
        ? { ...tab, label: props.ai?.display_name || 'Manage AI' }
        : tab
)));

/** Цэсэнд харагдах системүүд — ▲▼ энэ жагсаалтын дарааллыг хадгална. */
const orderedSystems = ref(
    [...(props.systems ?? [])].filter((s) => ! s.is_internal),
);
const otherSystems = ref(
    [...(props.systems ?? [])].filter((s) => s.is_internal),
);
const reorderSaving = ref(false);

watch(
    () => props.systems,
    (list) => {
        const all = [...(list ?? [])];
        orderedSystems.value = all.filter((s) => ! s.is_internal);
        otherSystems.value = all.filter((s) => s.is_internal);
    },
);

const saveOrder = (list) => {
    reorderSaving.value = true;
    router.patch(
        route('admin.systems.reorder'),
        { ids: list.map((s) => s.id) },
        {
            preserveScroll: true,
            onFinish: () => {
                reorderSaving.value = false;
            },
        },
    );
};

const moveSystem = (index, direction) => {
    const next = index + direction;
    if (next < 0 || next >= orderedSystems.value.length || reorderSaving.value) {
        return;
    }

    const list = [...orderedSystems.value];
    const [row] = list.splice(index, 1);
    list.splice(next, 0, row);
    orderedSystems.value = list;
    saveOrder(list);
};

const modal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    url: '',
    login_url: '',
    login_method: 'manual',
    supports_dan: false,
    dan_login_url: '',
    login_form_action: '',
    login_username_field: '',
    login_password_field: '',
    is_active: true,
    requires_login: true,
    is_internal: false,
    viewer_ids: [],
});

const aiForm = useForm({
    enabled: props.ai?.enabled ?? true,
    display_name: props.ai?.display_name ?? 'Manage AI',
    provider: props.ai?.provider ?? 'local',
    openai_model: props.ai?.openai_model ?? 'gpt-4o-mini',
    daily_question_limit: props.ai?.daily_question_limit ?? 30,
    openai_api_key: '',
    clear_api_key: false,
    // Цэс бүрд AI ямар эрхтэй байхыг тохируулна.
    module_access: Object.fromEntries((props.aiModules ?? []).map((m) => [m.key, m.level ?? 'read'])),
});

const accessLevels = [
    { value: 'none', label: 'Хаалттай' },
    { value: 'read', label: 'Зөвхөн харах' },
    { value: 'write', label: 'Харах + бүртгэл үүсгэх' },
];

const aiModulesByGroup = computed(() => {
    const map = {};
    for (const item of props.aiModules ?? []) {
        if (!map[item.group]) map[item.group] = { label: item.group, items: [] };
        map[item.group].items.push(item);
    }

    return Object.values(map);
});

const setAllAccess = (level) => {
    (props.aiModules ?? []).forEach((m) => {
        aiForm.module_access[m.key] = level;
    });
};

const cloneMenuGroups = (groups) => (groups ?? []).map((group) => ({
    ...group,
    items: [...(group.items ?? [])],
}));

const enabledFromGroups = (groups) => Object.fromEntries(
    (groups ?? []).flatMap((group) => (
        (group.items ?? []).map((item) => [item.key, item.enabled !== false])
    )),
);

const menuEnabled = ref(enabledFromGroups(props.menuGroups));
const menuGroups = ref(cloneMenuGroups(props.menuGroups));
const menuSaving = ref(false);

watch(
    () => props.menuGroups,
    (groups) => {
        menuGroups.value = cloneMenuGroups(groups);
        menuEnabled.value = enabledFromGroups(groups);
    },
);

const moveMenuGroup = (groupIndex, direction) => {
    const next = groupIndex + direction;
    if (next < 0 || next >= menuGroups.value.length) {
        return;
    }

    const list = [...menuGroups.value];
    const [row] = list.splice(groupIndex, 1);
    list.splice(next, 0, row);
    menuGroups.value = list;
};

const moveMenuItem = (groupIndex, itemIndex, direction) => {
    const group = menuGroups.value[groupIndex];
    if (!group) {
        return;
    }

    const next = itemIndex + direction;
    if (next < 0 || next >= group.items.length) {
        return;
    }

    const items = [...group.items];
    const [row] = items.splice(itemIndex, 1);
    items.splice(next, 0, row);
    menuGroups.value = menuGroups.value.map((entry, index) => (
        index === groupIndex ? { ...entry, items } : entry
    ));
};

const menuForm = useForm({ enabled: {} });

const saveMenus = () => {
    menuSaving.value = true;
    menuForm.clearErrors();

    router.patch(route('admin.menu-settings.update'), {
        enabled: { ...menuEnabled.value },
        group_order: menuGroups.value.map((group) => group.key),
        item_order: menuGroups.value.flatMap((group) => group.items.map((item) => item.key)),
    }, {
        preserveScroll: true,
        onError: (errors) => {
            Object.assign(menuForm.errors, errors);
        },
        onFinish: () => {
            menuSaving.value = false;
        },
    });
};

const resetForm = () => {
    form.clearErrors();
    form.name = '';
    form.url = '';
    form.login_url = '';
    form.login_method = 'manual';
    form.supports_dan = false;
    form.dan_login_url = '';
    form.login_form_action = '';
    form.login_username_field = '';
    form.login_password_field = '';
    form.is_active = true;
    form.requires_login = true;
    form.is_internal = false;
    form.viewer_ids = [];
};

const viewerNames = (system) => {
    const ids = system.viewer_ids ?? [];

    if (ids.length === 0) {
        return 'Цэсэнд харагдахгүй';
    }

    return props.employees
        .filter((e) => ids.includes(e.id))
        .map((e) => e.name)
        .join(', ');
};

const connectSearch = ref('');
const viewerDrafts = ref({});
const viewersSavingId = ref(null);
const selectedSystemId = ref(null);

const readSelectedSystemId = () => {
    try {
        const raw = new URL(window.location.href).searchParams.get('system');
        const id = Number(raw);

        return Number.isFinite(id) && id > 0 ? id : null;
    } catch {
        return null;
    }
};

selectedSystemId.value = readSelectedSystemId();

watch(() => page.url, () => {
    const id = readSelectedSystemId();
    if (id !== selectedSystemId.value) {
        selectedSystemId.value = id;
    }
});

const setSelectedSystemId = (id) => {
    selectedSystemId.value = id;
    try {
        const url = new URL(window.location.href);
        if (id) {
            url.searchParams.set('system', String(id));
        } else {
            url.searchParams.delete('system');
        }
        url.searchParams.set('tab', 'systems');
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`);
        sessionStorage.setItem('admin_settings_tab', 'systems');
    } catch {
        // ignore
    }
};

const toggleSystem = (system) => {
    setSelectedSystemId(selectedSystemId.value === system.id ? null : system.id);
};

const moveListedSystem = (system, direction) => {
    const index = orderedSystems.value.findIndex((row) => row.id === system.id);
    if (index === -1) {
        return;
    }
    moveSystem(index, direction);
};

const refreshSystemStaff = () => {
    router.reload({ only: ['employees', 'systems'], preserveScroll: true });
};

watch(
    () => props.systems,
    (list) => {
        const next = {};
        for (const s of list ?? []) {
            next[s.id] = [...(s.viewer_ids ?? [])];
        }
        viewerDrafts.value = next;
    },
    { immediate: true },
);

const connectableSystems = computed(() => {
    const q = connectSearch.value.trim().toLowerCase();
    const list = orderedSystems.value;

    if (! q) {
        return list;
    }

    return list.filter((s) => (s.name ?? '').toLowerCase().includes(q)
        || (s.url ?? '').toLowerCase().includes(q)
        || (s.login_url ?? '').toLowerCase().includes(q));
});

const saveViewers = (system) => {
    viewersSavingId.value = system.id;
    router.patch(
        route('admin.systems.viewers', system.id),
        { viewer_ids: viewerDrafts.value[system.id] ?? [] },
        {
            preserveScroll: true,
            onFinish: () => {
                viewersSavingId.value = null;
            },
        },
    );
};

const viewersModal = ref(false);
const viewersTarget = ref(null);
const viewersModalIds = ref([]);

const openViewers = (system) => {
    viewersTarget.value = system;
    viewersModalIds.value = [...(system.viewer_ids ?? [])];
    viewersModal.value = true;
};

const saveViewersModal = () => {
    if (! viewersTarget.value) {
        return;
    }

    viewersSavingId.value = viewersTarget.value.id;
    router.patch(
        route('admin.systems.viewers', viewersTarget.value.id),
        { viewer_ids: viewersModalIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                viewersModal.value = false;
            },
            onFinish: () => {
                viewersSavingId.value = null;
            },
        },
    );
};

const openCreate = () => {
    editing.value = null;
    resetForm();
    modal.value = true;
};

const openEdit = (system) => {
    editing.value = system;
    form.clearErrors();
    form.name = system.name;
    form.url = system.url;
    form.login_url = system.login_url ?? '';
    form.login_method = system.login_method;
    form.supports_dan = !! system.supports_dan;
    form.dan_login_url = system.dan_login_url ?? '';
    form.login_form_action = system.login_form_action ?? '';
    form.login_username_field = system.login_username_field ?? '';
    form.login_password_field = system.login_password_field ?? '';
    form.is_active = system.is_active;
    form.requires_login = system.requires_login;
    form.is_internal = system.is_internal;
    form.viewer_ids = [...(system.viewer_ids ?? [])];
    modal.value = true;
};

const submit = () => {
    if (editing.value) {
        form.patch(route('admin.systems.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => (modal.value = false),
        });

        return;
    }

    form.post(route('admin.systems.store'), {
        preserveScroll: true,
        onSuccess: () => {
            modal.value = false;
            resetForm();
        },
    });
};

const removeSystem = (system) => {
    if (! confirm(`«${system.name}» системийг устгах уу?`)) {
        return;
    }

    router.delete(route('admin.systems.destroy', system.id), { preserveScroll: true });
};

const checkEmbed = (system) => {
    router.post(route('admin.systems.check-embed', system.id), {}, { preserveScroll: true });
};

const detecting = ref(null);

/**
 * Нэвтрэх маягтыг автоматаар таниулна.
 *
 * Амжилттай бол form_post горим тохируулагдаж, өргөтгөлгүй — гар утсан дээр ч
 * нэг товшилтоор нэвтэрдэг болно.
 */
const detectLoginForm = (system) => {
    if (detecting.value) return;

    detecting.value = system.id;

    router.post(route('admin.systems.detect-login', system.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            detecting.value = null;
        },
    });
};

const notice = computed(() => {
    const formErrors = [
        ...Object.values(aiForm.errors ?? {}),
        ...Object.values(menuForm.errors ?? {}),
        ...Object.values(form.errors ?? {}),
    ]
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(Boolean);

    if (formErrors.length) {
        return { type: 'warning', text: String(formErrors[0]) };
    }

    const pageErrors = Object.values(page.props.errors ?? {})
        .flatMap((value) => (Array.isArray(value) ? value : [value]))
        .filter(Boolean);
    if (pageErrors.length) {
        return { type: 'warning', text: pageErrors.join(' ') };
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
}[notice.value?.type] ?? 'border-slate-200 bg-white text-slate-700'));

const saveAi = () => {
    aiForm.patch(route('admin.ai-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            aiForm.openai_api_key = '';
            aiForm.clear_api_key = false;
        },
    });
};
</script>

<template>
    <Head title="Системийн тохиргоо" />

    <AuthenticatedLayout>
        <template #header>Системийн тохиргоо</template>

        <div
            v-if="notice"
            class="sticky top-[4.75rem] z-30 mb-4 rounded-xl border px-4 py-3 text-sm shadow-md"
            :class="noticeClass"
            role="status"
        >
            {{ notice.text }}
        </div>

        <div
            class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200"
            role="tablist"
            aria-label="Системийн тохиргоо"
        >
            <button
                v-for="tab in settingsTabs"
                :key="tab.id"
                type="button"
                role="tab"
                class="-mb-px shrink-0 border-b-2 px-3.5 py-2.5 text-sm font-semibold transition sm:px-4"
                :class="activeTab === tab.id
                    ? 'border-brand-navy-600 text-brand-navy-800'
                    : 'border-transparent text-slate-500 hover:text-brand-navy-700'"
                :aria-selected="activeTab === tab.id"
                @click="selectTab(tab.id)"
            >
                {{ tab.label }}
            </button>
        </div>

        <section
            v-show="activeTab === 'menus'"
            class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            role="tabpanel"
        >
            <div class="mb-4">
                <h2 class="text-base font-semibold text-brand-navy-900">Цэс нээх / хаах</h2>
                <p class="mt-1 max-w-2xl text-sm text-brand-navy-400">
                    Хаасан цэс бүх хэрэглэгчид харагдахгүй, шууд хаягаар ч нээгдэхгүй.
                    ▲▼ товчоор бүлэг болон цэсийн байрлалыг солино. Дараа нь хадгална.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="saveMenus">
                <div
                    v-for="(group, groupIndex) in menuGroups"
                    :key="group.key"
                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="flex flex-col items-center gap-0.5">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="groupIndex === 0"
                                    title="Бүлэг дээш"
                                    @click="moveMenuGroup(groupIndex, -1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="groupIndex === menuGroups.length - 1"
                                    title="Бүлэг доош"
                                    @click="moveMenuGroup(groupIndex, 1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ group.label }}
                            </h3>
                        </div>
                        <span class="text-[11px] font-medium text-slate-400">{{ group.items.length }} цэс</span>
                    </div>

                    <div class="space-y-2">
                        <label
                            v-for="(item, itemIndex) in group.items"
                            :key="item.key"
                            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm sm:px-3 sm:py-2.5"
                        >
                            <div class="flex flex-col items-center gap-0.5">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="itemIndex === 0"
                                    title="Дээш"
                                    @click.prevent="moveMenuItem(groupIndex, itemIndex, -1)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="itemIndex === group.items.length - 1"
                                    title="Доош"
                                    @click.prevent="moveMenuItem(groupIndex, itemIndex, 1)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>

                            <span class="min-w-0 flex-1 font-medium text-slate-800">{{ item.label }}</span>
                            <span class="flex items-center gap-2">
                                <span
                                    class="text-[11px] font-semibold"
                                    :class="menuEnabled[item.key] ? 'text-emerald-600' : 'text-slate-400'"
                                >
                                    {{ menuEnabled[item.key] ? 'Нээлттэй' : 'Хаалттай' }}
                                </span>
                                <input
                                    v-model="menuEnabled[item.key]"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-500"
                                />
                            </span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="ui-btn-primary" :disabled="menuSaving">
                    {{ menuSaving ? 'Хадгалж байна…' : 'Цэсийн тохиргоо хадгалах' }}
                </button>
            </form>
        </section>

        <section
            v-show="activeTab === 'ai'"
            class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            role="tabpanel"
        >
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-brand-navy-900">{{ ai?.display_name || 'Manage AI' }}</h2>
                    <p class="mt-1 max-w-2xl text-sm text-brand-navy-400">
                        Нэр, API түлхүүр, өдрийн лимитийг энд тохируулна. API түлхүүр зөвхөн серверт шифрлэгдэнэ.
                    </p>
                </div>
                <span
                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="ai?.has_api_key ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                    {{ ai?.has_api_key ? `Түлхүүр: ${ai.api_key_hint}` : 'API түлхүүр алга' }}
                </span>
            </div>

            <form class="grid gap-4 md:grid-cols-2" @submit.prevent="saveAi">
                <label class="flex items-center gap-2 text-sm text-brand-navy-700 md:col-span-2">
                    <input
                        v-model="aiForm.enabled"
                        type="checkbox"
                        class="rounded border-brand-navy-200 text-brand-orange-500 focus:ring-brand-orange-500"
                    />
                    {{ aiForm.display_name || 'Manage AI' }}-г идэвхжүүлэх
                </label>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Харуулах нэр</label>
                    <input
                        v-model="aiForm.display_name"
                        type="text"
                        class="ui-input max-w-md"
                        placeholder="Manage AI"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">Цэс, товч, хуудасны гарчигт энэ нэр харагдана.</p>
                    <InputError :message="aiForm.errors.display_name" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Provider</label>
                    <select v-model="aiForm.provider" class="ui-input">
                        <option value="local">Local (tool / системийн өгөгдөл)</option>
                        <option value="openai">OpenAI API</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Модель</label>
                    <input v-model="aiForm.openai_model" type="text" class="ui-input" placeholder="gpt-4o-mini" />
                    <InputError :message="aiForm.errors.openai_model" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">
                        Нэг албан хаагчийн өдрийн асуултын лимит
                    </label>
                    <input
                        v-model.number="aiForm.daily_question_limit"
                        type="number"
                        min="0"
                        max="1000"
                        class="ui-input"
                    />
                    <p class="mt-1 text-xs text-slate-500">0 = хязгааргүй</p>
                    <InputError :message="aiForm.errors.daily_question_limit" class="mt-1" />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">OpenAI API key</label>
                    <input
                        v-model="aiForm.openai_api_key"
                        type="password"
                        autocomplete="new-password"
                        class="ui-input"
                        placeholder="Шинэ түлхүүр оруулах (хоосон бол өөрчлөхгүй)"
                    />
                    <InputError :message="aiForm.errors.openai_api_key" class="mt-1" />
                    <label class="mt-2 flex items-center gap-2 text-xs text-red-600">
                        <input v-model="aiForm.clear_api_key" type="checkbox" class="rounded border-red-200 text-red-600" />
                        Одоогийн түлхүүрийг устгах
                    </label>
                </div>

                <!-- Цэс бүрийн хандалт -->
                <div class="md:col-span-2">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-brand-navy-800">Аль цэс рүү хандах, ямар үйлдэл хийх вэ</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Энэ тохиргоо болон «Хандах эрх»-ийн огтлол дээр AI ажиллана.
                                «Хаалттай» бол AI тухайн цэсийг огт үзэхгүй. «Зөвхөн харах» бол мэдээлэл уншина
                                (хэрэглэгчийн харах эрх, хамааралтай хамрах хүрээнээс хэтрэхгүй).
                                «Харах + бүртгэл үүсгэх» нь хэрэглэгчид оруулах эрх байгаа үед л бүртгэл үүсгэнэ.
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <button
                                v-for="level in accessLevels"
                                :key="level.value"
                                type="button"
                                class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-brand-navy-300"
                                @click="setAllAccess(level.value)"
                            >
                                Бүгд: {{ level.label }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-4 rounded-xl border border-slate-200 p-4">
                        <div v-for="group in aiModulesByGroup" :key="group.label">
                            <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ group.label }}
                            </p>
                            <div class="grid gap-2 md:grid-cols-2">
                                <label
                                    v-for="item in group.items"
                                    :key="item.key"
                                    class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2"
                                >
                                    <span class="truncate text-sm text-slate-700" :title="item.label">{{ item.label }}</span>
                                    <select
                                        v-model="aiForm.module_access[item.key]"
                                        class="ui-input w-48 shrink-0 py-1 text-xs"
                                    >
                                        <option v-for="level in accessLevels" :key="level.value" :value="level.value">
                                            {{ level.label }}
                                        </option>
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 space-y-2">
                    <div
                        v-if="notice"
                        class="rounded-lg border px-3 py-2 text-sm"
                        :class="noticeClass"
                        role="status"
                    >
                        {{ notice.text }}
                    </div>
                    <button type="submit" class="ui-btn-primary" :disabled="aiForm.processing">
                        Тохиргоо хадгалах
                    </button>
                    <p v-if="aiForm.processing" class="text-xs text-slate-500">Хадгалж байна…</p>
                </div>
            </form>
        </section>

        <section
            v-show="activeTab === 'push'"
            class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            role="tabpanel"
        >
            <div class="mb-4">
                <h2 class="text-base font-semibold text-brand-navy-900">Push мэдэгдэл</h2>
                <p class="mt-1 max-w-2xl text-sm text-brand-navy-400">
                    Чөлөө, үүрэг, томилолт зэрэг холбоотой мэдээллийг төхөөрөмж рүү илгээнэ.
                    Асаасны дараа толгойн <b>хонх</b> icon дээр тоогоор харагдана.
                </p>
            </div>
            <PushSubscribe class="max-w-xl" />
        </section>

        <div v-show="activeTab === 'systems'" role="tabpanel" class="space-y-4">
            <section class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-brand-navy-900">Гадны системүүд</h2>
                        <p class="mt-1 max-w-3xl text-sm text-brand-navy-400">
                            Бүртгэлтэй систем дээр дарж албан хаагчдад цэсэнд харуулах тохиргоог нээнэ.
                            Сонгоогүй бол тухайн систем ажилтны «Холбосон системүүд» цэсэнд гарахгүй.
                            Нэвтрэх нэр, нууц үгийг энд биш — цэснээс тухайн системээ нээгээд хадгална.
                        </p>
                    </div>
                    <button type="button" class="ui-btn-primary shrink-0" @click="openCreate">
                        + Систем бүртгэх
                    </button>
                </div>

                <input
                    v-model="connectSearch"
                    type="search"
                    placeholder="Сонгох системийн нэрээр хайх…"
                    class="ui-input mt-4 max-w-lg"
                />

                <p v-if="! connectableSystems.length" class="mt-6 text-sm text-slate-400">
                    {{ connectSearch.trim() ? 'Илэрц олдсонгүй.' : 'Систем бүртгэгдээгүй байна. «Систем бүртгэх» дарж нэмнэ үү.' }}
                </p>

                <div class="mt-4 space-y-2">
                    <div
                        v-for="system in connectableSystems"
                        :key="system.id"
                        class="overflow-hidden rounded-xl border bg-white transition"
                        :class="selectedSystemId === system.id
                            ? 'border-brand-navy-400 shadow-sm ring-1 ring-brand-navy-200'
                            : 'border-slate-200 hover:border-brand-navy-300'"
                    >
                        <div class="flex items-stretch">
                            <div class="flex flex-col items-center justify-center gap-0.5 border-r border-slate-100 px-2 py-2">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="orderedSystems[0]?.id === system.id || reorderSaving"
                                    title="Дээш"
                                    @click.stop="moveListedSystem(system, -1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <span class="text-[11px] font-semibold tabular-nums text-brand-navy-600">
                                    {{ orderedSystems.findIndex((row) => row.id === system.id) + 1 }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 disabled:opacity-30"
                                    :disabled="orderedSystems.at(-1)?.id === system.id || reorderSaving"
                                    title="Доош"
                                    @click.stop="moveListedSystem(system, 1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 px-4 py-3 text-left"
                                @click="toggleSystem(system)"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="font-semibold text-brand-navy-800">{{ system.name }}</div>
                                    <div class="truncate text-xs text-slate-400">{{ system.login_url || system.url }}</div>
                                </div>
                                <span
                                    v-if="system.can_auto_submit"
                                    class="hidden shrink-0 rounded-full bg-brand-orange-100 px-2 py-0.5 text-[11px] text-brand-orange-700 sm:inline"
                                >
                                    Шууд илгээх
                                </span>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="(system.viewer_ids?.length ?? 0) > 0
                                        ? 'bg-brand-orange-100 text-brand-orange-700'
                                        : 'bg-slate-100 text-slate-500'"
                                >
                                    {{ (system.viewer_ids?.length ?? 0) > 0
                                        ? `${system.viewer_ids.length} албан хаагч`
                                        : 'Сонгоогүй' }}
                                </span>
                                <svg
                                    class="h-4 w-4 shrink-0 text-slate-400 transition"
                                    :class="selectedSystemId === system.id ? 'rotate-180' : ''"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <div
                            v-if="selectedSystemId === system.id"
                            class="space-y-4 border-t border-slate-100 bg-slate-50/70 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-brand-navy-800">Цэсэнд харуулах албан хаагчид</h3>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Хайж чагталсан албан хаагчдын хажуугийн цэсэнд энэ систем гарна.
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50"
                                        @click="refreshSystemStaff"
                                    >
                                        Шинэчлэх
                                    </button>
                                    <button
                                        type="button"
                                        class="ui-btn-primary py-1.5 text-xs"
                                        :disabled="viewersSavingId === system.id"
                                        @click="saveViewers(system)"
                                    >
                                        {{ viewersSavingId === system.id ? 'Хадгалж байна…' : 'Тохиргоог хадгалах' }}
                                    </button>
                                </div>
                            </div>
                            <EmployeePicker
                                :employees="employees"
                                :model-value="viewerDrafts[system.id] ?? []"
                                max-height-class="max-h-72"
                                @update:model-value="viewerDrafts[system.id] = $event"
                            />
                            <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-3 text-xs">
                                <button type="button" class="text-brand-navy-700 hover:underline" @click="openEdit(system)">
                                    Системийн тохиргоо
                                </button>
                                <button type="button" class="text-brand-navy-500 hover:underline" @click="checkEmbed(system)">
                                    Дотор нээгдэхийг шалгах
                                </button>
                                <button
                                    type="button"
                                    class="text-emerald-700 hover:underline disabled:opacity-50"
                                    :disabled="detecting === system.id"
                                    title="Нэвтрэх хуудсыг уншиж form_post тохиргоог өөрөө олно — утсан дээр ч шууд нэвтэрнэ"
                                    @click="detectLoginForm(system)"
                                >
                                    {{ detecting === system.id ? 'Таньж байна…' : 'Нэвтрэх маягтыг таних' }}
                                </button>
                                <button type="button" class="text-rose-600 hover:underline" @click="removeSystem(system)">
                                    Устгах
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <div v-if="otherSystems.length" class="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-700">Дотоод системүүд</h3>
                <p class="text-xs text-slate-500">Ажилтны цэсэнд гарахгүй (дотоод).</p>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr
                        v-for="(system, i) in otherSystems"
                        :key="system.id"
                        :class="i % 2 === 1 ? 'bg-slate-50' : 'bg-white'"
                    >
                        <td class="px-5 py-3">
                            <div class="font-medium text-brand-navy-800">{{ system.name }}</div>
                            <div class="text-xs text-brand-navy-300">{{ system.login_url ?? system.url }}</div>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button type="button" class="text-brand-navy-600 hover:underline" @click="openEdit(system)">
                                Тохируулах
                            </button>
                            <button type="button" class="ml-3 text-rose-600 hover:underline" @click="removeSystem(system)">
                                Устгах
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>

        <Modal :show="modal" max-width="xl" @close="modal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-base font-semibold text-brand-navy-900">
                    {{ editing ? `${editing.name} — тохиргоо` : 'Шинэ систем бүртгэх' }}
                </h2>
                <p v-if="! editing" class="mt-1 text-sm text-slate-500">
                    Нэр, хаяг оруулаад бүртгэсний дараа нэвтрэх аргыг нарийвчлан тохируулж болно.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэр</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Жишээ: Төрийн ERP"
                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-brand-navy-700">Үндсэн хаяг</label>
                            <input
                                v-model="form.url"
                                type="url"
                                required
                                placeholder="https://..."
                                class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                            />
                            <InputError :message="form.errors.url" class="mt-1" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэвтрэх хуудас</label>
                            <input
                                v-model="form.login_url"
                                type="url"
                                placeholder="Хоосон бол үндсэн хаяг"
                                class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                            />
                            <InputError :message="form.errors.login_url" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэвтрэх арга</label>
                        <select
                            v-model="form.login_method"
                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                        >
                            <option value="manual">Хуулж өгөх</option>
                            <option value="form_post">Шууд илгээх</option>
                        </select>
                    </div>

                    <template v-if="form.login_method === 'form_post'">
                        <div class="space-y-3 rounded-lg border border-brand-navy-100 bg-brand-navy-50 p-3">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-brand-navy-700">Маягтын хаяг</label>
                                <input v-model="form.login_form_action" type="url" class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm" />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэрийн талбар</label>
                                    <input v-model="form.login_username_field" type="text" class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нууц үгийн талбар</label>
                                    <input v-model="form.login_password_field" type="text" class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ДАН — Үндэсний танилт, нэвтрэлтийн системээр нэвтэрдэг эсэх. -->
                    <div class="rounded-xl border border-brand-navy-100 bg-slate-50/70 p-4">
                        <label class="flex items-center gap-2 text-sm font-medium text-brand-navy-700">
                            <input v-model="form.supports_dan" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500" />
                            ДАН-аар нэвтэрдэг
                        </label>
                        <p class="mt-1 text-xs text-brand-navy-400">
                            Асаахад албан хаагчид нэвтрэх мэдээлэлдээ «ДАН-аар нэвтрэх» сонголтоор регистрээ хадгална.
                        </p>
                        <div v-if="form.supports_dan" class="mt-3">
                            <label class="ui-label">ДАН-ы нэвтрэх хаяг (заавал биш)</label>
                            <input
                                v-model="form.dan_login_url"
                                type="url"
                                class="ui-input"
                                placeholder="https://dan.gov.mn/…"
                            />
                            <p class="mt-1 text-xs text-brand-navy-400">
                                Хоосон бол системийн ердийн нэвтрэх хаягийг хэрэглэнэ.
                            </p>
                            <InputError :message="form.errors.dan_login_url" class="mt-1" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500" />
                        Идэвхтэй
                    </label>
                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input v-model="form.requires_login" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500" />
                        Нэвтрэх мэдээлэл шаардана
                    </label>
                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input v-model="form.is_internal" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500" />
                        Дотоод ажил
                    </label>

                    <!-- Харах албан хаагчид -->
                    <div class="rounded-xl border border-brand-navy-100 bg-slate-50/70 p-4">
                        <div class="mb-3">
                            <h3 class="text-sm font-semibold text-brand-navy-800">Харах албан хаагчид</h3>
                            <p class="mt-0.5 text-xs text-brand-navy-400">
                                Хайж сонгосон албан хаагчдын цэсэнд энэ систем гарна. Хоосон бол цэсэнд харагдахгүй.
                            </p>
                        </div>
                        <EmployeePicker v-model="form.viewer_ids" :employees="employees" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm" @click="modal = false">Болих</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white">
                        {{ editing ? 'Хадгалах' : 'Бүртгэх' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="viewersModal" max-width="lg" @close="viewersModal = false">
            <div class="p-6">
                <h2 class="text-base font-semibold text-brand-navy-900">
                    {{ viewersTarget?.name }} — цэсэнд харуулах
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Албан хаагч хайж сонгоно. Сонгосон хүмүүсийн хажуугийн цэсэнд энэ систем гарна.
                </p>
                <div class="mt-4">
                    <EmployeePicker v-model="viewersModalIds" :employees="employees" max-height-class="max-h-72" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm" @click="viewersModal = false">
                        Болих
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="viewersSavingId === viewersTarget?.id"
                        @click="saveViewersModal"
                    >
                        Хадгалах
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
