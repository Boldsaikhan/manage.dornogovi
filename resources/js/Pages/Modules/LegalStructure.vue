<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    heading: { type: String, default: 'Эрх зүйн акт, тогтоол' },
    structure: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const query = ref('');
const selectedId = ref(props.users.find((user) => ! user.is_admin)?.id ?? props.users[0]?.id ?? null);

const flash = computed(() => page.props.flash?.success ?? null);

const filteredUsers = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (term === '') {
        return props.users;
    }

    return props.users.filter((user) => [user.name, user.position, user.department]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(term)));
});

const selected = computed(() => props.users.find((user) => user.id === selectedId.value) ?? null);

const form = useForm({ permissions: {} });

watch(selected, (user) => {
    form.permissions = { ...(user?.permissions ?? {}) };
    form.clearErrors();
}, { immediate: true });

const save = () => {
    if (! selected.value || selected.value.is_admin || ! props.canManage) {
        return;
    }

    form.patch(route('legal-structure.update', selected.value.id), { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout title="Бүтэц, хандах эрх">
        <div class="ui-page">
            <p v-if="flash" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ flash }}
            </p>

            <section class="ui-card ui-card-pad">
                <h1 class="ui-title">Бүтэц, хандах эрх</h1>
                <p class="ui-subtitle">{{ heading }} бүлгийн цэс, тэдгээрийн хандах эрх.</p>

                <div class="mt-4 rounded-xl border border-slate-200">
                    <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-bold text-brand-navy-800">
                        {{ heading }}
                    </div>
                    <ul>
                        <li
                            v-for="item in structure"
                            :key="item.key"
                            class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 last:border-b-0"
                        >
                            <span class="text-sm font-medium text-slate-800">{{ item.label }}</span>
                            <Link
                                v-if="item.canOpen && item.route"
                                :href="route(item.route)"
                                class="text-xs font-semibold text-brand-navy-600 hover:underline"
                            >
                                Нээх
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-[280px_1fr]">
                <aside class="ui-card flex min-h-0 flex-col">
                    <div class="space-y-2 border-b border-slate-100 px-3 py-3">
                        <div class="px-1 text-sm font-bold text-brand-navy-800">
                            Албан хаагчид
                            <span class="font-medium text-slate-400">{{ filteredUsers.length }}</span>
                        </div>
                        <input v-model="query" type="search" class="ui-input" placeholder="Нэр, албан тушаал, нэгж">
                    </div>
                    <ul class="max-h-[32rem] overflow-y-auto">
                        <li v-for="user in filteredUsers" :key="user.id">
                            <button
                                type="button"
                                class="flex w-full flex-col px-4 py-3 text-left hover:bg-slate-50"
                                :class="user.id === selectedId ? 'bg-brand-navy-50' : ''"
                                @click="selectedId = user.id"
                            >
                                <span class="text-sm font-semibold text-brand-navy-900">{{ user.name }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ user.department || user.position || (user.is_admin ? 'Админ' : '—') }}
                                </span>
                            </button>
                        </li>
                        <li v-if="filteredUsers.length === 0" class="px-4 py-6 text-sm text-slate-400">
                            Илэрц алга.
                        </li>
                    </ul>
                </aside>

                <form v-if="selected" class="ui-card ui-card-pad" @submit.prevent="save">
                    <h2 class="ui-title">{{ selected.name }}</h2>
                    <p class="ui-subtitle">
                        {{ selected.is_admin ? 'Админ хэрэглэгч бүх цэсийг нээнэ.' : 'Гурван цэсийн хандах эрх.' }}
                    </p>

                    <div class="mt-4 space-y-3">
                        <label v-for="item in structure" :key="item.key" class="block">
                            <span class="ui-label">{{ item.label }}</span>
                            <select
                                v-model="form.permissions[item.key]"
                                class="ui-input"
                                :disabled="selected.is_admin || ! canManage"
                            >
                                <option v-for="option in item.options" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-4">
                        <button
                            v-if="canManage && ! selected.is_admin"
                            type="submit"
                            class="ui-btn-primary"
                            :disabled="form.processing"
                        >
                            Хадгалах
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
