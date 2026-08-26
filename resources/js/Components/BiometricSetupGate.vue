<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';

const page = usePage();

const visible = ref(false);
const busy = ref(false);
const error = ref('');
const webauthnOk = ref(typeof window !== 'undefined' && isWebAuthnSupported());

const hasWebAuthn = computed(() => !! page.props.appLock?.hasWebAuthn);
const isLoggedIn = computed(() => !! page.props.auth?.user);

const isStandaloneApp = () => (
    window.matchMedia('(display-mode: standalone)').matches
    || window.matchMedia('(display-mode: fullscreen)').matches
    || window.navigator.standalone === true
);

const isMobileDevice = () => /Android|iPhone|iPad|iPod/i.test(navigator.userAgent ?? '');

/** Утас / суулгасан PWA дээр биометрик заавал идэвхжүүлнэ */
const shouldPromptSetup = () => (
    isLoggedIn.value
    && webauthnOk.value
    && ! hasWebAuthn.value
    && (isMobileDevice() || isStandaloneApp())
);

const evaluate = () => {
    visible.value = shouldPromptSetup();
};

onMounted(() => {
    evaluate();
});

const activate = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';

    try {
        await registerBiometric();
        // Бүртгэл амжилттай — биометрик баталгаажуулалт асууна
        await window.axios.post(route('app.lock'));
        visible.value = false;
        router.reload({ only: ['appLock', 'auth'] });
    } catch (e) {
        const msg = e?.response?.data?.message || e?.message || 'Идэвхжүүлэлт амжилтгүй.';
        if (/NotAllowedError|AbortError/i.test(String(e?.name) + msg)) {
            error.value = 'Хуруу / нүүрээр баталгаажуулалт цуцлагдлаа. Дахин оролдоно уу.';
        } else {
            error.value = msg;
        }
    } finally {
        busy.value = false;
    }
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
                Хуруу / нүүрээр нэвтрэх
            </h2>
            <p class="mt-2 text-center text-sm text-slate-500">
                Энэ утсанд Fingerprint эсвэл Face ID идэвхжүүлбэл дараагийн удаа нэвтрэх бүрт автоматаар асууна.
            </p>

            <p v-if="error" class="mt-4 text-center text-sm text-red-600">{{ error }}</p>

            <button
                type="button"
                class="ui-btn-primary mt-6 w-full"
                :disabled="busy"
                @click="activate"
            >
                {{ busy ? 'Идэвхжүүлж байна…' : 'Хуруу / нүүр идэвхжүүлэх' }}
            </button>

            <p class="mt-3 text-center text-[11px] text-slate-400">
                HTTPS холболт шаардлагатай. Нэг удаа идэвхжүүлсний дараа нэвтрэх бүрт асууна.
            </p>
        </div>
    </div>
</template>
