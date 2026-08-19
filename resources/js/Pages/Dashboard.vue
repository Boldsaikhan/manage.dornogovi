<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    systems: Array,
    stats: Object,
    focus: Number,
});

const page = usePage();

const iconPaths = {
    wallet: 'M3 7a2 2 0 012-2h11a2 2 0 012 2v1M3 7v10a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2H5a2 2 0 01-2-2zM17 13h.01',
    building: 'M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M4 21h16M16 9h2a2 2 0 012 2v10M8 7h2M8 11h2M8 15h2',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    mail: 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l9 6 9-6',
    chart: 'M4 20V10M10 20V4M16 20v-6M22 20H2',
    clipboard: 'M9 4h6a1 1 0 011 1v1H8V5a1 1 0 011-1zM8 6H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-2M9 12h6M9 16h4',
};

const search = ref('');

const filteredSystems = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.systems;
    return props.systems.filter(
        (s) =>
            s.name.toLowerCase().includes(q) ||
            (s.category ?? '').toLowerCase().includes(q) ||
            (s.description ?? '').toLowerCase().includes(q),
    );
});

const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

/* ---------- Нэвтрэх мэдээлэл хадгалах ---------- */

const saveModal = ref(false);
const activeSystem = ref(null);
const launchAfterSave = ref(false);

const form = useForm({
    system_id: null,
    username: '',
    password: '',
    note: '',
});

const openSave = (system, thenLaunch = false) => {
    activeSystem.value = system;
    launchAfterSave.value = thenLaunch;
    form.reset();
    form.clearErrors();
    form.system_id = system.id;
    saveModal.value = true;
};

const submitSave = () => {
    form.post(route('credentials.store'), {
        preserveScroll: true,
        onSuccess: () => {
            saveModal.value = false;
            form.reset();
            if (launchAfterSave.value) {
                launch(activeSystem.value);
            }
        },
    });
};

const removeCredential = (system) => {
    if (!confirm(`"${system.name}" системийн нэвтрэх мэдээллийг устгах уу?`)) return;
    router.delete(route('credentials.destroy', system.id), { preserveScroll: true });
};

/* ---------- Санг нээх ---------- */

const unlockModal = ref(false);
const accountPassword = ref('');
const unlockError = ref('');
const unlocking = ref(false);
const pendingSystem = ref(null);

const openUnlock = (system) => {
    pendingSystem.value = system;
    accountPassword.value = '';
    unlockError.value = '';
    unlockModal.value = true;
};

const submitUnlock = async () => {
    unlocking.value = true;
    unlockError.value = '';
    try {
        await window.axios.post(route('vault.unlock'), {
            account_password: accountPassword.value,
        });
        unlockModal.value = false;
        accountPassword.value = '';
        const target = pendingSystem.value;
        // Хуваалцсан vault.unlocked төлөвийг шинэчилнэ.
        router.reload({
            only: ['vault'],
            onSuccess: () => target && openLaunchWindow(target),
        });
    } catch (e) {
        unlockError.value =
            e.response?.data?.errors?.account_password?.[0] ??
            'Нээх боломжгүй байна. Дахин оролдоно уу.';
    } finally {
        unlocking.value = false;
    }
};

/* ---------- Нэвтрэх ---------- */

const openLaunchWindow = (system) => {
    window.open(route('systems.launch', system.id), '_blank', 'noopener');
};

const launch = (system) => {
    // Нээлттэй систем (нэвтрэх мэдээлэл шаардахгүй) — vault-ийн урсгалыг алгасаж
    // шууд нээнэ.
    if (!system.requires_login) {
        window.open(system.entry_url, '_blank', 'noopener');

        return;
    }

    if (!system.has_credential) {
        openSave(system, true);

        return;
    }

    if (!vaultUnlocked.value) {
        openUnlock(system);

        return;
    }

    openLaunchWindow(system);
};

onMounted(() => {
    if (!props.focus) return;
    const system = props.systems.find((s) => s.id === props.focus);
    if (system) launch(system);
});
</script>

<template>
    <Head title="Системүүд" />

    <AuthenticatedLayout>
        <template #header>Системүүд</template>

        <div
            v-if="page.props.flash.success"
            class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700"
        >
            {{ page.props.flash.success }}
        </div>

        <!-- Тойм -->
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
                <div class="text-xs font-medium text-brand-navy-400">Нийт систем</div>
                <div class="mt-1 text-2xl font-semibold text-brand-navy-900">{{ stats.total }}</div>
            </div>
            <div class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
                <div class="text-xs font-medium text-brand-navy-400">Мэдээлэл хадгалсан</div>
                <div class="mt-1 text-2xl font-semibold text-brand-orange-600">{{ stats.saved }}</div>
            </div>
            <div class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm">
                <div class="text-xs font-medium text-brand-navy-400">Сангийн төлөв</div>
                <div class="mt-1 flex items-center gap-2">
                    <span
                        class="text-sm font-semibold"
                        :class="vaultUnlocked ? 'text-green-700' : 'text-brand-navy-700'"
                    >
                        {{ vaultUnlocked ? 'Нээлттэй' : 'Түгжээтэй' }}
                    </span>
                    <button
                        v-if="vaultUnlocked"
                        class="rounded-md border border-brand-navy-200 px-2 py-0.5 text-xs text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="router.post(route('vault.lock'), {}, { preserveScroll: true })"
                    >
                        Түгжих
                    </button>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <input
                v-model="search"
                type="search"
                placeholder="Систем хайх…"
                class="w-full max-w-sm rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
            />
        </div>

        <!-- Системийн картууд -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="system in filteredSystems"
                :key="system.id"
                class="flex flex-col rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-navy-50 text-brand-navy-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold text-brand-navy-800">{{ system.name }}</h3>
                        <span class="mt-1 inline-block rounded-full bg-brand-navy-100 px-2 py-0.5 text-xs text-brand-navy-600">
                            {{ system.category }}
                        </span>
                    </div>
                    <span
                        v-if="system.has_credential"
                        class="rounded-full bg-brand-orange-100 px-2 py-0.5 text-xs text-brand-orange-700"
                    >
                        Хадгалсан
                    </span>
                    <span
                        v-else-if="!system.requires_login"
                        class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700"
                    >
                        Нээлттэй
                    </span>
                </div>

                <p class="mt-3 flex-1 text-sm text-brand-navy-400">{{ system.description }}</p>

                <p v-if="system.last_used_at" class="mt-2 text-xs text-brand-navy-300">
                    Сүүлд: {{ system.last_used_at }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        class="rounded-md bg-brand-orange-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600"
                        @click="launch(system)"
                    >
                        {{ !system.requires_login ? 'Нээх' : system.has_credential ? 'Нэвтрэх' : 'Мэдээлэл нэмж нэвтрэх' }}
                    </button>

                    <Link
                        v-if="system.is_embeddable"
                        :href="route('systems.show', system.id)"
                        class="rounded-md border border-brand-navy-200 px-3 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                    >
                        Дотор нээх
                    </Link>
                    <a
                        v-else
                        :href="system.entry_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-md border border-brand-navy-200 px-3 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                    >
                        Зүгээр нээх
                    </a>

                    <button
                        v-if="system.has_credential"
                        class="rounded-md border border-brand-navy-200 px-3 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="openSave(system)"
                    >
                        Засах
                    </button>
                    <button
                        v-if="system.has_credential"
                        class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-500 hover:bg-red-50"
                        @click="removeCredential(system)"
                    >
                        Устгах
                    </button>
                </div>
            </div>
        </div>

        <p v-if="filteredSystems.length === 0" class="py-10 text-center text-sm text-brand-navy-400">
            Илэрц олдсонгүй.
        </p>

        <!-- Нэвтрэх мэдээлэл хадгалах -->
        <Modal :show="saveModal" @close="saveModal = false">
            <form class="p-6" @submit.prevent="submitSave">
                <h2 class="text-base font-semibold text-brand-navy-900">
                    {{ activeSystem?.name }} — нэвтрэх мэдээлэл
                </h2>
                <p class="mt-1 text-sm text-brand-navy-400">
                    Мэдээлэл шифрлэгдэн хадгалагдана. Зөвхөн та өөрөө нээж чадна.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэвтрэх нэр</label>
                        <input
                            v-model="form.username"
                            type="text"
                            autocomplete="off"
                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        <InputError :message="form.errors.username" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нууц үг</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Тэмдэглэл (заавал биш)</label>
                        <textarea
                            v-model="form.note"
                            rows="2"
                            class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                        />
                        <InputError :message="form.errors.note" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="saveModal = false"
                    >
                        Болих
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600 disabled:opacity-50"
                    >
                        {{ launchAfterSave ? 'Хадгалаад нэвтрэх' : 'Хадгалах' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Санг нээх -->
        <Modal :show="unlockModal" @close="unlockModal = false">
            <form class="p-6" @submit.prevent="submitUnlock">
                <h2 class="text-base font-semibold text-brand-navy-900">Нэвтрэх мэдээллийн санг нээх</h2>
                <p class="mt-1 text-sm text-brand-navy-400">
                    Хадгалсан нууц үгсээ задлахын тулд өөрийгөө баталгаажуулна уу.
                    Нэг удаа нээвэл 2 цагийн турш дахин асуухгүй.
                </p>

                <label class="mt-4 block text-xs font-medium text-brand-navy-700">
                    Энэ платформ руу нэвтрэх нууц үг
                </label>
                <p class="mb-1 text-xs text-brand-navy-300">
                    {{ page.props.auth.user.email }} — бусад системийн нууц үг биш.
                </p>
                <input
                    v-model="accountPassword"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                />
                <p v-if="unlockError" class="mt-1 text-sm text-red-500">{{ unlockError }}</p>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="unlockModal = false"
                    >
                        Болих
                    </button>
                    <button
                        type="submit"
                        :disabled="unlocking"
                        class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600 disabled:opacity-50"
                    >
                        Нээх
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
