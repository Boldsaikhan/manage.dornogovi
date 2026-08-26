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
    auth_type: 'password',
    username: '',
    password: '',
    note: '',
    remember_device: false,
});

const isDan = computed(() => form.auth_type === 'dan');

// Регистрийн дугаар — 2 кирилл үсэг + 8 орон.
const registerValid = computed(() => /^[А-ЯЁҮӨ]{2}\d{8}$/.test(form.username.trim().toUpperCase()));

const onRegisterInput = (event) => {
    form.username = event.target.value.toUpperCase().replace(/\s+/g, '');
};

const openSave = (system, thenLaunch = false) => {
    activeSystem.value = system;
    launchAfterSave.value = thenLaunch;
    form.reset();
    form.clearErrors();
    form.system_id = system.id;
    // ДАН-аар нэвтэрдэг системд ДАН-ыг анхнаас сонгож өгнө.
    form.auth_type = system.auth_type ?? (system.supports_dan ? 'dan' : 'password');
    form.remember_device = !! system.remember_device;
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
        <template #header>Холбосон системүүд</template>

        <div class="ui-page">
            <div
                v-if="page.props.flash.success"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
            >
                {{ page.props.flash.success }}
            </div>


            <div class="grid gap-4 sm:grid-cols-3">
                <div class="ui-stat">
                    <div class="ui-stat-label">Нийт систем</div>
                    <div class="ui-stat-value">{{ stats.total }}</div>
                </div>
                <div class="ui-stat">
                    <div class="ui-stat-label">Мэдээлэл хадгалсан</div>
                    <div class="ui-stat-value text-brand-orange-600">{{ stats.saved }}</div>
                </div>
                <div class="ui-stat">
                    <div class="ui-stat-label">Сангийн төлөв</div>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="text-sm font-bold"
                            :class="vaultUnlocked ? 'text-emerald-700' : 'text-brand-navy-800'"
                        >
                            {{ vaultUnlocked ? 'Нээлттэй' : 'Түгжээтэй' }}
                        </span>
                        <button
                            v-if="vaultUnlocked"
                            class="ui-btn-ghost !px-2 !py-1 text-xs"
                            @click="router.post(route('vault.lock'), {}, { preserveScroll: true })"
                        >
                            Түгжих
                        </button>
                    </div>
                </div>
            </div>

            <input
                v-model="search"
                type="search"
                placeholder="Систем хайх…"
                class="ui-input max-w-md"
            />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="system in filteredSystems"
                    :key="system.id"
                    class="ui-card flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-panel"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-navy-50 text-brand-navy-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-bold text-brand-navy-800">{{ system.name }}</h3>
                            <span class="mt-1 inline-block rounded-full bg-brand-navy-50 px-2.5 py-0.5 text-[11px] font-semibold text-brand-navy-600">
                                {{ system.category }}
                            </span>
                        </div>
                        <span
                            v-if="system.has_credential"
                            class="rounded-full bg-brand-orange-100 px-2.5 py-0.5 text-[11px] font-semibold text-brand-orange-700"
                        >
                            Хадгалсан
                        </span>
                        <span
                            v-else-if="!system.requires_login"
                            class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700"
                        >
                            Нээлттэй
                        </span>
                    </div>

                    <p class="mt-3 flex-1 text-sm leading-relaxed text-slate-500">{{ system.description }}</p>

                    <p v-if="system.last_used_at" class="mt-2 text-xs text-slate-400">
                        Сүүлд: {{ system.last_used_at }}
                    </p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button class="ui-btn-primary !py-2" @click="launch(system)">
                            {{ !system.requires_login ? 'Нээх' : system.has_credential ? 'Нэвтрэх' : 'Мэдээлэл нэмж нэвтрэх' }}
                        </button>

                        <Link
                            v-if="system.is_embeddable"
                            :href="route('systems.show', system.id)"
                            class="ui-btn-ghost !py-2"
                        >
                            Дотор нээх
                        </Link>
                        <a
                            v-else
                            :href="system.entry_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ui-btn-ghost !py-2"
                        >
                            Зүгээр нээх
                        </a>

                        <button
                            v-if="system.has_credential"
                            class="ui-btn-ghost !py-2"
                            @click="openSave(system)"
                        >
                            Засах
                        </button>
                        <button
                            v-if="system.has_credential"
                            class="ui-btn-danger !border-0 !px-3 !py-2"
                            @click="removeCredential(system)"
                        >
                            Устгах
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="filteredSystems.length === 0" class="py-12 text-center text-sm text-slate-400">
                Илэрц олдсонгүй.
            </p>
        </div>

        <Modal :show="saveModal" @close="saveModal = false">
            <form class="p-6" @submit.prevent="submitSave">
                <h2 class="ui-title text-base">{{ activeSystem?.name }} — нэвтрэх мэдээлэл</h2>
                <p class="ui-subtitle">Мэдээлэл шифрлэгдэн хадгалагдана. Зөвхөн та өөрөө нээж чадна.</p>

                <!-- ДАН-аар нэвтэрдэг системд нэвтрэлтийн төрлийг сонгоно. -->
                <div v-if="activeSystem?.supports_dan" class="mt-5 flex gap-2 rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="! isDan ? 'bg-white text-brand-navy-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="form.auth_type = 'password'"
                    >
                        Нэр, нууц үгээр
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="isDan ? 'bg-white text-brand-navy-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="form.auth_type = 'dan'"
                    >
                        ДАН-аар нэвтрэх
                    </button>
                </div>

                <p v-if="isDan" class="mt-3 rounded-xl bg-brand-navy-50 px-3 py-2 text-xs leading-relaxed text-brand-navy-700">
                    ДАН-ы «Нэг удаагийн код» хэсэгт регистр, нууц үгийг автоматаар бөглөж,
                    баталгаажуулах код утас руу илгээх хүртэлх алхмыг гүйцэтгэнэ.
                    <strong>Кодыг та өөрөө оруулна</strong> — үүнийг тойрох боломжгүй.
                </p>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="ui-label">{{ isDan ? 'Регистрийн дугаар' : 'Нэвтрэх нэр' }}</label>
                        <input
                            :value="form.username"
                            type="text"
                            autocomplete="off"
                            class="ui-input"
                            :class="isDan ? 'font-mono uppercase tracking-wide' : ''"
                            :placeholder="isDan ? 'УХ98010112' : ''"
                            :maxlength="isDan ? 10 : 255"
                            @input="isDan ? onRegisterInput($event) : (form.username = $event.target.value)"
                        />
                        <p v-if="isDan && form.username && ! registerValid" class="mt-1 text-xs text-amber-600">
                            2 кирилл үсэг + 8 оронтой байна (жишээ: УХ98010112).
                        </p>
                        <InputError :message="form.errors.username" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">{{ isDan ? 'ДАН нууц үг' : 'Нууц үг' }}</label>
                        <input v-model="form.password" type="password" autocomplete="new-password" class="ui-input" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Тэмдэглэл (заавал биш)</label>
                        <textarea v-model="form.note" rows="2" class="ui-input" />
                        <InputError :message="form.errors.note" class="mt-1" />
                    </div>
                    <label class="flex items-start gap-2 rounded-xl border border-slate-200 px-3 py-2">
                        <input v-model="form.remember_device" type="checkbox" class="mt-0.5 rounded border-slate-300" />
                        <span class="text-xs leading-relaxed text-slate-600">
                            <span class="font-medium text-slate-700">Энэ төхөөрөмжид санах</span> —
                            дараа удаа сангаа нээлгүй шууд нэвтэрнэ.
                            Мэдээлэл тухайн компьютерт үлдэх тул зөвхөн өөрийн төхөөрөмждөө ашиглана уу.
                        </span>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="ui-btn-ghost" @click="saveModal = false">Болих</button>
                    <button type="submit" :disabled="form.processing" class="ui-btn-primary">
                        {{ launchAfterSave ? 'Хадгалаад нэвтрэх' : 'Хадгалах' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="unlockModal" @close="unlockModal = false">
            <form class="p-6" @submit.prevent="submitUnlock">
                <h2 class="ui-title text-base">Нэвтрэх мэдээллийн санг нээх</h2>
                <p class="ui-subtitle">
                    Хадгалсан нууц үгсээ задлахын тулд өөрийгөө баталгаажуулна уу.
                    Нэг удаа нээвэл 2 цагийн турш дахин асуухгүй.
                </p>

                <label class="ui-label mt-5">Энэ платформ руу нэвтрэх нууц үг</label>
                <p class="mb-2 text-xs text-slate-400">
                    {{ page.props.auth.user.email }} — бусад системийн нууц үг биш.
                </p>
                <input
                    v-model="accountPassword"
                    type="password"
                    autocomplete="current-password"
                    class="ui-input"
                />
                <p v-if="unlockError" class="mt-1 text-sm text-red-500">{{ unlockError }}</p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="ui-btn-ghost" @click="unlockModal = false">Болих</button>
                    <button type="submit" :disabled="unlocking" class="ui-btn-primary">Нээх</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
