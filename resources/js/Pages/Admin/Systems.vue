<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import PushSubscribe from '@/Components/PushSubscribe.vue';

const props = defineProps({
    systems: Array,
    employees: { type: Array, default: () => [] },
    ai: Object,
    menus: { type: Array, default: () => [] },
    aiModules: { type: Array, default: () => [] },
});

const page = usePage();

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

const menuEnabled = ref(
    Object.fromEntries((props.menus ?? []).map((m) => [m.key, m.enabled !== false])),
);

const menusByGroup = computed(() => {
    const map = {};
    for (const item of props.menus ?? []) {
        if (!map[item.group]) {
            map[item.group] = { label: item.group_label, items: [] };
        }
        map[item.group].items.push(item);
    }
    return Object.values(map);
});

const menuForm = useForm({ enabled: menuEnabled.value });

const saveMenus = () => {
    menuForm.enabled = { ...menuEnabled.value };
    menuForm.patch(route('admin.menu-settings.update'), { preserveScroll: true });
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
    viewerSearch.value = '';
};

// ── Системийг харах албан хаагчид ──
const viewerSearch = ref('');

const filteredEmployees = computed(() => {
    const q = viewerSearch.value.trim().toLowerCase();

    if (! q) {
        return props.employees;
    }

    return props.employees.filter((e) => (e.name ?? '').toLowerCase().includes(q)
        || (e.position ?? '').toLowerCase().includes(q));
});

const toggleViewer = (id) => {
    const list = form.viewer_ids;

    form.viewer_ids = list.includes(id) ? list.filter((x) => x !== id) : [...list, id];
};

const viewerNames = (system) => {
    const ids = system.viewer_ids ?? [];

    if (ids.length === 0) {
        return 'Бүгд харна';
    }

    return props.employees
        .filter((e) => ids.includes(e.id))
        .map((e) => e.name)
        .join(', ');
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
    viewerSearch.value = '';
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
            v-if="page.props.flash.success"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700"
        >
            {{ page.props.flash.success }}
        </div>

        <section class="mb-8 rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-brand-navy-900">Цэс нээх / хаах</h2>
                <p class="mt-1 max-w-2xl text-sm text-brand-navy-400">
                    Хаасан цэс бүх хэрэглэгчид харагдахгүй, шууд хаягаар ч нээгдэхгүй.
                    Дахин нээхийн тулд эндээс асаана.
                </p>
            </div>

            <form class="space-y-5" @submit.prevent="saveMenus">
                <div
                    v-for="group in menusByGroup"
                    :key="group.label"
                    class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                >
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ group.label }}
                    </h3>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="item in group.items"
                            :key="item.key"
                            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm"
                        >
                            <span class="font-medium text-slate-800">{{ item.label }}</span>
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

                <button type="submit" class="ui-btn-primary" :disabled="menuForm.processing">
                    Цэсийн тохиргоо хадгалах
                </button>
            </form>
        </section>

        <section class="mb-8 rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
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
                                «Хаалттай» бол AI тухайн цэсийн мэдээллийг огт үзэхгүй. «Харах + бүртгэл үүсгэх»
                                сонговол шинэ бүртгэл үүсгэхийг зөвшөөрнө (хэрэглэгчийн эрхээс давахгүй).
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

                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn-primary" :disabled="aiForm.processing">
                        Тохиргоо хадгалах
                    </button>
                </div>
            </form>
        </section>

        <section class="mb-8 rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-brand-navy-900">Push мэдэгдэл</h2>
                <p class="mt-1 max-w-2xl text-sm text-brand-navy-400">
                    Чөлөө, үүрэг, томилолт зэрэг холбоотой мэдээллийг төхөөрөмж рүү илгээнэ.
                    Асаасны дараа толгойн <b>хонх</b> icon дээр тоогоор харагдана.
                </p>
            </div>
            <PushSubscribe class="max-w-xl" />
        </section>

        <section class="mb-6 rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-brand-navy-900">Холбосон системүүд</h2>
                    <p class="mt-1 max-w-3xl text-sm text-brand-navy-400">
                        Зүүн талын ▲▼ товчоор <strong>хажуугийн цэсийн «Холбосон системүүд»</strong>
                        хэсгийн дарааллыг өөрчилнө. Энэ жагсаалтын №1 дээрх систем цэсэнд эхэнд гарна.
                    </p>
                </div>
                <button type="button" class="ui-btn-primary shrink-0" @click="openCreate">
                    + Систем бүртгэх
                </button>
            </div>
        </section>

        <div class="mb-8 overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-brand-navy-50 text-left text-brand-navy-700">
                    <tr>
                        <th class="w-16 px-3 py-2 text-center font-medium" title="Цэсийн дараалал">№</th>
                        <th class="px-5 py-2 font-medium">Систем</th>
                        <th class="px-5 py-2 font-medium">Нэвтрэх арга</th>
                        <th class="px-5 py-2 font-medium">Дотор нээгдэх</th>
                        <th class="px-5 py-2 font-medium">Харах албан хаагч</th>
                        <th class="w-52 px-5 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!orderedSystems.length">
                        <td colspan="6" class="px-5 py-10 text-center text-sm text-slate-400">
                            Систем бүртгэгдээгүй байна. «Систем бүртгэх» дарж нэмнэ үү.
                        </td>
                    </tr>
                    <tr
                        v-for="(system, i) in orderedSystems"
                        :key="system.id"
                        :class="i % 2 === 1 ? 'bg-brand-navy-50' : 'bg-white'"
                    >
                        <td class="px-2 py-2 text-center align-middle">
                            <div class="inline-flex flex-col items-center gap-0.5">
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 hover:text-brand-navy-800 disabled:cursor-not-allowed disabled:opacity-30"
                                    :disabled="i === 0 || reorderSaving"
                                    title="Дээш"
                                    aria-label="Дээш"
                                    @click="moveSystem(i, -1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                    </svg>
                                </button>
                                <span class="text-xs font-semibold tabular-nums text-brand-navy-600">{{ i + 1 }}</span>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-brand-navy-500 hover:bg-brand-navy-100 hover:text-brand-navy-800 disabled:cursor-not-allowed disabled:opacity-30"
                                    :disabled="i === orderedSystems.length - 1 || reorderSaving"
                                    title="Доош"
                                    aria-label="Доош"
                                    @click="moveSystem(i, 1)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <div class="font-medium text-brand-navy-800">{{ system.name }}</div>
                            <div class="text-xs text-brand-navy-300">{{ system.login_url ?? system.url }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <span
                                v-if="system.can_auto_submit"
                                class="rounded-full bg-brand-orange-100 px-2 py-0.5 text-xs text-brand-orange-700"
                            >
                                Шууд илгээх
                            </span>
                            <span
                                v-else-if="system.login_method === 'form_post'"
                                class="rounded-full bg-yellow-50 px-2 py-0.5 text-xs text-yellow-700"
                            >
                                Тохиргоо дутуу
                            </span>
                            <span v-else class="rounded-full bg-brand-navy-100 px-2 py-0.5 text-xs text-brand-navy-600">
                                Хуулж өгөх
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="system.is_embeddable
                                    ? 'bg-green-50 text-green-700'
                                    : 'bg-brand-navy-100 text-brand-navy-600'"
                            >
                                {{ system.is_embeddable === null ? 'Шалгаагүй' : (system.is_embeddable ? 'Тийм' : 'Үгүй') }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="(system.viewer_ids?.length ?? 0) > 0
                                    ? 'bg-brand-orange-100 text-brand-orange-700'
                                    : 'bg-brand-navy-100 text-brand-navy-600'"
                            >
                                {{ (system.viewer_ids?.length ?? 0) > 0 ? `${system.viewer_ids.length} албан хаагч` : 'Бүгд' }}
                            </span>
                            <div v-if="(system.viewer_ids?.length ?? 0) > 0" class="mt-1 max-w-xs truncate text-xs text-brand-navy-300" :title="viewerNames(system)">
                                {{ viewerNames(system) }}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button type="button" class="text-brand-navy-600 hover:underline" @click="openEdit(system)">
                                Тохируулах
                            </button>
                            <button type="button" class="ml-3 text-brand-navy-400 hover:underline" @click="checkEmbed(system)">
                                Дахин шалгах
                            </button>
                            <button type="button" class="ml-3 text-rose-600 hover:underline" @click="removeSystem(system)">
                                Устгах
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="otherSystems.length" class="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 bg-slate-50 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-700">Дотоод системүүд</h3>
                <p class="text-xs text-slate-500">Цэсийн «Холбосон системүүд»-д гарахгүй.</p>
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
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-brand-navy-800">Харах албан хаагчид</h3>
                                <p class="mt-0.5 text-xs text-brand-navy-400">
                                    Хэнийг ч сонгоогүй бол бүх албан хаагчдад харагдана. Сонговол зөвхөн тэдний цэсэнд гарна.
                                </p>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-brand-navy-600 ring-1 ring-brand-navy-100">
                                {{ form.viewer_ids.length ? form.viewer_ids.length + ' сонгогдсон' : 'Бүгд харна' }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <input
                                v-model="viewerSearch"
                                type="search"
                                placeholder="Нэрээр хайх…"
                                class="min-w-0 flex-1 rounded-md border border-brand-navy-200 px-3 py-1.5 text-sm"
                            />
                            <button
                                type="button"
                                class="rounded-md border border-brand-navy-200 px-3 py-1.5 text-xs"
                                @click="form.viewer_ids = []"
                            >
                                Цэвэрлэх (бүгд харна)
                            </button>
                        </div>

                        <div class="mt-3 max-h-56 overflow-y-auto rounded-lg border border-brand-navy-100 bg-white">
                            <p v-if="! filteredEmployees.length" class="px-3 py-4 text-center text-xs text-slate-400">
                                Илэрц олдсонгүй.
                            </p>
                            <label
                                v-for="e in filteredEmployees"
                                :key="e.id"
                                class="flex cursor-pointer items-center gap-2 border-b border-slate-50 px-3 py-2 text-sm last:border-0 hover:bg-brand-navy-50"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-brand-navy-200 text-brand-orange-500"
                                    :checked="form.viewer_ids.includes(e.id)"
                                    @change="toggleViewer(e.id)"
                                />
                                <span class="font-medium text-brand-navy-800">{{ e.name }}</span>
                                <span v-if="e.position" class="truncate text-xs text-brand-navy-300">{{ e.position }}</span>
                            </label>
                        </div>
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
    </AuthenticatedLayout>
</template>
