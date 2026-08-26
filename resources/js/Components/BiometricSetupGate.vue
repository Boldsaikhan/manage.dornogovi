<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';
import { isMobileDevice } from '@/utils/mobileClient';

const SKIP_KEY = 'biometric_setup_done';

const page = usePage();

const visible = ref(false);
const busy = ref(false);
const error = ref('');
const tip = ref('');
const webauthnOk = ref(typeof window !== 'undefined' && isWebAuthnSupported());

const hasWebAuthn = computed(() => !! page.props.appLock?.hasWebAuthn);
const isLoggedIn = computed(() => !! page.props.auth?.user);
const userId = computed(() => page.props.auth?.user?.id ?? null);

const skipKey = () => `${SKIP_KEY}:${userId.value || 0}`;

const isSkipped = () => {
    try {
        return localStorage.getItem(skipKey()) === '1';
    } catch {
        return false;
    }
};

const markDone = () => {
    try {
        localStorage.setItem(skipKey(), '1');
    } catch {
        // ignore
    }
};

const evaluate = () => {
    if (! isLoggedIn.value || ! webauthnOk.value || ! isMobileDevice()) {
        visible.value = false;
        return;
    }

    // Аль хэдийн бүртгэгдсэн эсвэл хэрэглэгч хаасан бол дахин бүү асуу
    if (hasWebAuthn.value || isSkipped()) {
        visible.value = false;
        if (hasWebAuthn.value) markDone();
        return;
    }

    visible.value = true;
};

onMounted(evaluate);
watch([hasWebAuthn, isLoggedIn, userId], evaluate);

const activate = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    tip.value = 'Гарч ирвэл хуруу эсвэл нүүрээ уншуулна уу.';

    try {
        await registerBiometric();
        tip.value = '';
        markDone();
        visible.value = false;
        router.reload({ only: ['appLock', 'auth'] });
    } catch (e) {
        const name = String(e?.name || '');
        const msg = e?.response?.data?.message || e?.message || 'Идэвхжүүлэлт амжилтгүй.';
        const blob = `${name} ${msg}`;

        if (/NotAllowedError|AbortError|цуцла/i.test(blob)) {
            error.value = 'Үйлдэл цуцлагдлаа.';
            tip.value = 'Дахин дарж, утасны хуруу/нүүрээр баталгаажуулна уу.';
        } else if (/InvalidStateError|already registered|exclude/i.test(blob)) {
            markDone();
            error.value = 'Аль хэдийн бүртгэгдсэн байна.';
            tip.value = '';
            visible.value = false;
            router.reload({ only: ['appLock'] });
        } else if (/NotSupportedError|SecurityError/i.test(blob)) {
            error.value = 'Энэ төхөөрөмж дэмжихгүй эсвэл HTTPS биш.';
            tip.value = 'Chrome-оор https://manage.dornogovi.gov.mn нээнэ үү.';
        } else {
            error.value = msg;
            tip.value = 'Дахин оролдоно уу.';
        }
    } finally {
        busy.value = false;
    }
};

const dismissForNow = () => {
    markDone();
    visible.value = false;
};
</script>

<template>
    <div
        v-if="visible"
        class="fixed inset-0 z-[190] flex items-end justify-center bg-brand-navy-950/70 p-4 backdrop-blur-sm sm:items-center"
        role="dialog"
        aria-modal="true"
    >
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-navy-50 text-brand-navy-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.567 3-3.5S13.657 4 12 4 9 5.567 9 7.5 10.343 11 12 11z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20c.8-3.2 2.9-5 5.5-5s4.7 1.8 5.5 5" />
                </svg>
            </div>

            <h2 class="mt-4 text-center text-lg font-bold text-brand-navy-900">
                Хуруу / нүүр идэвхжүүлэх
            </h2>
            <p class="mt-2 text-center text-sm text-slate-500">
                Нэг удаа идэвхжүүлбэл дараагийн удаа нэвтрэхэд автоматаар асууна.
                «Create a passkey» гарвал <b class="text-slate-700">Continue</b> дараад хуруугаа уншуулна.
            </p>

            <p v-if="error" class="mt-4 text-center text-sm text-red-600">{{ error }}</p>
            <p v-if="tip" class="mt-2 text-center text-xs text-slate-500">{{ tip }}</p>

            <button
                type="button"
                class="ui-btn-primary mt-6 w-full"
                :disabled="busy"
                @click="activate"
            >
                {{ busy ? 'Хүлээнэ үү…' : 'Одоо идэвхжүүлэх' }}
            </button>

            <button
                type="button"
                class="mt-3 w-full text-center text-sm font-medium text-slate-500 hover:text-slate-800"
                :disabled="busy"
                @click="dismissForNow"
            >
                Дахиж бүү асуу
            </button>
        </div>
    </div>
</template>
