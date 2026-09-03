<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import VaultUnlockForm from '@/Components/VaultUnlockForm.vue';

const props = defineProps({
    system: Object,
    target: String,
});

const page = usePage();
const loading = ref(true);
const frameKey = ref(0);
const showPassword = ref(false);

const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

const form = useForm({
    system_id: props.system.id,
    auth_type: props.system.auth_type || 'password',
    username: '',
    password: '',
    remember_device: !! props.system.remember_device,
});

const isDan = computed(() => form.auth_type === 'dan');

watch(
    () => props.system.id,
    () => {
        form.system_id = props.system.id;
        form.auth_type = props.system.auth_type || 'password';
        form.remember_device = !! props.system.remember_device;
        form.reset('username', 'password');
        form.clearErrors();
        loading.value = true;
        frameKey.value++;
    },
);

const canLaunch = computed(() => (
    props.system.requires_login
    && props.system.has_credential
    && vaultUnlocked.value
));

const launchUrl = computed(() => route('systems.launch', props.system.id));

// Хажуугийн цэснээс нэвтрэхийг оролдоод сан түгжээтэй байсан тохиолдол —
// нээмэгц шууд үргэлжлүүлнэ (ижил табд, тиймээс popup хаагдахгүй).
const pendingLaunch = ref(
    Number(page.props.flash?.launch_after_unlock ?? 0) === Number(props.system.id),
);

watch(vaultUnlocked, (unlocked) => {
    if (unlocked && pendingLaunch.value) {
        pendingLaunch.value = false;
        window.location.href = launchUrl.value;
    }
});

const openUrl = computed(() => (canLaunch.value ? launchUrl.value : props.target));

const reload = () => {
    loading.value = true;
    frameKey.value++;
};

const saveCredential = () => {
    form.post(route('credentials.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('username', 'password'),
    });
};

const removeCredential = () => {
    if (! window.confirm('Хадгалсан нэвтрэх мэдээллийг устгах уу?')) {
        return;
    }

    router.delete(route('credentials.destroy', props.system.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="system.name" />

    <AuthenticatedLayout>
        <template #header>{{ system.name }}</template>

        <div class="space-y-4">
            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.info"
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800"
            >
                {{ page.props.flash.info }}
            </div>
            <div
                v-if="page.props.flash?.warning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ page.props.flash.warning }}
            </div>

            <section
                v-if="system.requires_login"
                class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            >
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-brand-navy-900">Нэвтрэх нэр, нууц үг</h2>
                        <p class="mt-1 max-w-xl text-sm text-slate-500">
                            Энэ системд өөрийн нэвтрэх мэдээллээ нэг удаа хадгална.
                            Дараа нь «Нэвтрэх» дарж шууд орно.
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="system.has_credential
                            ? 'bg-emerald-50 text-emerald-700'
                            : 'bg-slate-100 text-slate-600'"
                    >
                        {{ system.has_credential ? 'Хадгалсан' : 'Хадгалаагүй' }}
                    </span>
                </div>

                <div v-if="canLaunch" class="mb-5">
                    <a
                        :href="launchUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-md bg-brand-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-orange-600"
                    >
                        {{ system.name }} руу нэвтрэх
                    </a>
                    <p class="mt-2 text-xs text-slate-500">
                        Сан нээлттэй байна. Шинэ табд нэвтрэх хуудас нээгдэнэ.
                    </p>
                </div>

                <form class="grid max-w-xl gap-3 sm:grid-cols-2" @submit.prevent="saveCredential">
                    <div v-if="system.supports_dan" class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">Нэвтрэх арга</label>
                        <select v-model="form.auth_type" class="ui-input">
                            <option value="password">Нэвтрэх нэр / нууц үг</option>
                            <option value="dan">ДАН-аар нэвтрэх</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">
                            {{ isDan ? 'Регистрийн дугаар' : 'Нэвтрэх нэр' }}
                        </label>
                        <input
                            v-model="form.username"
                            type="text"
                            autocomplete="username"
                            required
                            class="ui-input"
                            :placeholder="isDan ? 'Жишээ: УХ98010112' : 'И-мэйл, утас эсвэл нэвтрэх нэр'"
                        />
                        <InputError :message="form.errors.username" class="mt-1" />
                        <InputError :message="form.errors.auth_type" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-brand-navy-700">
                            {{ isDan ? 'ДАН нууц үг' : 'Нууц үг' }}
                        </label>
                        <div class="flex gap-2">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                required
                                class="ui-input flex-1"
                                :placeholder="system.has_credential ? 'Шинэчлэх бол дахин оруулна' : 'Нууц үг'"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-md border border-slate-200 px-3 text-xs text-slate-600 hover:bg-slate-50"
                                @click="showPassword = ! showPassword"
                            >
                                {{ showPassword ? 'Нуух' : 'Харах' }}
                            </button>
                        </div>
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-2">
                        <input
                            v-model="form.remember_device"
                            type="checkbox"
                            class="rounded border-slate-300 text-brand-navy-600"
                        />
                        Энэ төхөөрөмжийг санах (өргөтгөл)
                    </label>

                    <div class="flex flex-wrap gap-2 sm:col-span-2">
                        <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Хадгалж байна…' : (system.has_credential ? 'Шинэчлэх' : 'Хадгалах') }}
                        </button>
                        <button
                            v-if="system.has_credential"
                            type="button"
                            class="rounded-md border border-rose-200 px-4 py-1.5 text-sm text-rose-700 hover:bg-rose-50"
                            @click="removeCredential"
                        >
                            Устгах
                        </button>
                    </div>
                </form>

                <div
                    v-if="! canLaunch"
                    class="mt-5 border-t border-slate-100 pt-4"
                >
                    <template v-if="! system.has_credential">
                        <p class="text-sm text-slate-500">
                            Нэвтрэх нэр, нууц үгээ хадгалсны дараа «Нэвтрэх» товч гарна.
                        </p>
                    </template>
                    <template v-else-if="! vaultUnlocked">
                        <p class="mb-3 text-sm text-slate-600">
                            {{ pendingLaunch
                                ? 'Сан түгжээтэй байна. Нээвэл ' + system.name + ' рүү шууд үргэлжилнэ.'
                                : 'Хадгалсан мэдээллээр нэвтрэхийн тулд сангаа нээнэ үү.' }}
                        </p>
                        <VaultUnlockForm />
                    </template>
                </div>
            </section>

            <!-- Дотор нээгддэг систем -->
            <div
                v-if="system.is_embeddable"
                class="overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm"
            >
                <div class="flex items-center gap-3 border-b border-brand-navy-100 px-4 py-2">
                    <span class="truncate text-xs text-brand-navy-400">{{ target }}</span>
                    <button
                        class="ml-auto shrink-0 rounded-md border border-brand-navy-200 px-2.5 py-1 text-xs text-brand-navy-700 hover:bg-brand-navy-50"
                        @click="reload"
                    >
                        Дахин ачаалах
                    </button>
                    <a
                        :href="openUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="shrink-0 rounded-md border border-brand-navy-200 px-2.5 py-1 text-xs text-brand-navy-700 hover:bg-brand-navy-50"
                    >
                        Шинэ табд нээх
                    </a>
                </div>

                <div class="relative" style="height: calc(100vh - 10rem); min-height: 30rem">
                    <div
                        v-if="loading"
                        class="absolute inset-0 flex items-center justify-center bg-white text-sm text-brand-navy-400"
                    >
                        Ачаалж байна…
                    </div>
                    <iframe
                        :key="frameKey"
                        :src="target"
                        class="h-full w-full"
                        referrerpolicy="no-referrer-when-downgrade"
                        @load="loading = false"
                    />
                </div>
            </div>

            <!-- Дотор нээгдэхийг сервер нь хориглосон -->
            <div v-else class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-yellow-50 text-yellow-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-brand-navy-800">
                            Энэ системийг дотор нь нээх боломжгүй
                        </h3>
                        <p class="mt-1 text-sm text-brand-navy-400">
                            {{ system.name }}-ийн сервер өөр сайт дотор ачаалагдахыг хориглосон байна.
                            {{ system.requires_login
                                ? 'Дээрх нэвтрэх товчоор шинэ табд орно.'
                                : 'Шинэ табд нээж болно.' }}
                        </p>
                        <p v-if="system.embed_blocked_by" class="mt-2 font-mono text-xs text-brand-navy-300">
                            {{ system.embed_blocked_by }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a
                                :href="openUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600"
                            >
                                Шинэ табд нээх
                            </a>
                            <Link
                                :href="route('dept.dashboard')"
                                class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                            >
                                Самбар руу буцах
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
