<script setup>
import { computed, ref } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    systems: Array,
    ai: Object,
    menus: { type: Array, default: () => [] },
});

const page = usePage();

const modal = ref(false);
const editing = ref(null);

const form = useForm({
    name: '',
    url: '',
    login_url: '',
    login_method: 'manual',
    login_form_action: '',
    login_username_field: '',
    login_password_field: '',
    is_active: true,
    requires_login: true,
    is_internal: false,
});

const aiForm = useForm({
    enabled: props.ai?.enabled ?? true,
    display_name: props.ai?.display_name ?? 'Manage AI',
    provider: props.ai?.provider ?? 'local',
    openai_model: props.ai?.openai_model ?? 'gpt-4o-mini',
    daily_question_limit: props.ai?.daily_question_limit ?? 30,
    openai_api_key: '',
    clear_api_key: false,
});

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
const openEdit = (system) => {
    editing.value = system;
    form.clearErrors();
    form.name = system.name;
    form.url = system.url;
    form.login_url = system.login_url ?? '';
    form.login_method = system.login_method;
    form.login_form_action = system.login_form_action ?? '';
    form.login_username_field = system.login_username_field ?? '';
    form.login_password_field = system.login_password_field ?? '';
    form.is_active = system.is_active;
    form.requires_login = system.requires_login;
    form.is_internal = system.is_internal;
    modal.value = true;
};

const submit = () => {
    form.patch(route('admin.systems.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => (modal.value = false),
    });
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

                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn-primary" :disabled="aiForm.processing">
                        Тохиргоо хадгалах
                    </button>
                </div>
            </form>
        </section>

        <p class="mb-6 max-w-3xl text-sm text-brand-navy-400">
            Систем бүрийн нэвтрэх аргыг энд тохируулна. <strong>Шууд илгээх</strong> горим нь
            хэрэглэгчийн нэр, нууц үгийг нуугдмал маягтаар тухайн системийн нэвтрэх хаяг руу
            илгээж, нэг товшилтоор нэвтрүүлнэ.
        </p>

        <div class="overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-brand-navy-50 text-left text-brand-navy-700">
                    <tr>
                        <th class="px-5 py-2 font-medium">Систем</th>
                        <th class="px-5 py-2 font-medium">Нэвтрэх арга</th>
                        <th class="px-5 py-2 font-medium">Дотор нээгдэх</th>
                        <th class="w-40 px-5 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(system, i) in systems"
                        :key="system.id"
                        :class="i % 2 === 1 ? 'bg-brand-navy-50' : 'bg-white'"
                    >
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
                        <td class="px-5 py-3 text-right">
                            <button class="text-brand-navy-600 hover:underline" @click="openEdit(system)">
                                Тохируулах
                            </button>
                            <button class="ml-3 text-brand-navy-400 hover:underline" @click="checkEmbed(system)">
                                Дахин шалгах
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="modal" max-width="xl" @close="modal = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-base font-semibold text-brand-navy-900">
                    {{ editing?.name }} — тохиргоо
                </h2>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэр</label>
                        <input
                            v-model="form.name"
                            type="text"
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
                                class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                            />
                            <InputError :message="form.errors.url" class="mt-1" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэвтрэх хуудас</label>
                            <input
                                v-model="form.login_url"
                                type="url"
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
                        <div class="rounded-lg border border-brand-navy-100 bg-brand-navy-50 p-3 space-y-3">
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
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm" @click="modal = false">Болих</button>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white">Хадгалах</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
