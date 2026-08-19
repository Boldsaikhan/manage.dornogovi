<script setup>
import { ref } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

defineProps({
    systems: Array,
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

        <p class="mb-6 max-w-3xl text-sm text-brand-navy-400">
            Систем бүрийн нэвтрэх аргыг энд тохируулна. <strong>Шууд илгээх</strong> горим нь
            хэрэглэгчийн нэр, нууц үгийг нуугдмал маягтаар тухайн системийн нэвтрэх хаяг руу
            илгээж, нэг товшилтоор нэвтрүүлнэ. Энэ нь сонгодог маягтан нэвтрэлттэй системд
            ажилладаг ба орчин үеийн SPA (ж: Төрийн ERP) дээр ажиллахгүй.
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
                            <option value="manual">Хуулж өгөх — хаягийг нээгээд нэр/нууц үгийг хуулна</option>
                            <option value="form_post">Шууд илгээх — нуугдмал маягтаар нэвтэрнэ</option>
                        </select>
                    </div>

                    <template v-if="form.login_method === 'form_post'">
                        <div class="rounded-lg border border-brand-navy-100 bg-brand-navy-50 p-3">
                            <p class="mb-3 text-xs text-brand-navy-400">
                                Тухайн системийн нэвтрэх хуудсанд F12 → Network дээр нэг удаа нэвтэрч
                                үзээд, хүсэлтийн хаяг болон талбарын нэрийг хуулж авна.
                            </p>

                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-brand-navy-700">Маягтын хаяг (form action)</label>
                                    <input
                                        v-model="form.login_form_action"
                                        type="url"
                                        placeholder="https://example.gov.mn/login"
                                        class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                                    />
                                    <InputError :message="form.errors.login_form_action" class="mt-1" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэрийн талбар</label>
                                        <input
                                            v-model="form.login_username_field"
                                            type="text"
                                            placeholder="username"
                                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                                        />
                                        <InputError :message="form.errors.login_username_field" class="mt-1" />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нууц үгийн талбар</label>
                                        <input
                                            v-model="form.login_password_field"
                                            type="text"
                                            placeholder="password"
                                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                                        />
                                        <InputError :message="form.errors.login_password_field" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-brand-navy-200 text-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        Идэвхтэй
                    </label>

                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input
                            v-model="form.requires_login"
                            type="checkbox"
                            class="rounded border-brand-navy-200 text-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        Нэвтрэх мэдээлэл шаардана
                        <span class="text-xs text-brand-navy-400">(тэмдэглээгүй бол "Нээх" товч гарна)</span>
                    </label>

                    <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                        <input
                            v-model="form.is_internal"
                            type="checkbox"
                            class="rounded border-brand-navy-200 text-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        Дотоод ажил
                        <span class="text-xs text-brand-navy-400">(хажуугийн цэсэнд "Дотоод ажил" бүлэгт харагдана)</span>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="modal = false"
                    >
                        Болих
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600 disabled:opacity-50"
                    >
                        Хадгалах
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
