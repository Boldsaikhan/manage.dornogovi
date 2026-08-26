<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';
import { isMobileDevice } from '@/utils/mobileClient';

const page = usePage();

const visible = ref(false);
const busy = ref(false);
const error = ref('');
const tip = ref('');
const webauthnOk = ref(typeof window !== 'undefined' && isWebAuthnSupported());

const hasWebAuthn = computed(() => !! page.props.appLock?.hasWebAuthn);
const isLoggedIn = computed(() => !! page.props.auth?.user);

const evaluate = () => {
    if (! isLoggedIn.value || ! webauthnOk.value || hasWebAuthn.value || ! isMobileDevice()) {
        visible.value = false;
        return;
    }

    try {
        if (sessionStorage.getItem('biometric_setup_skip') === '1') {
            visible.value = false;
            return;
        }
    } catch {
        // ignore
    }

    visible.value = true;
};

onMounted(evaluate);

const activate = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    tip.value = 'Гарч ирсэн цонхонд «Continue / Үргэлжлүүлэх» дарна уу.';

    try {
        await registerBiometric();
        tip.value = '';
        visible.value = false;
        await window.axios.post(route('app.lock'));
        router.reload({ only: ['appLock', 'auth'] });
    } catch (e) {
        const name = String(e?.name || '');
        const msg = e?.response?.data?.message || e?.message || 'Идэвхжүүлэлт амжилтгүй.';
        const blob = `${name} ${msg}`;

        if (/NotAllowedError|AbortError|цуцла/i.test(blob)) {
            error.value = 'Бүртгэл цуцлагдлаа эсвэл таслагдлаа.';
            tip.value = 'Дахин дарж, «Create a passkey» цонхонд Continue дарна уу. «More options» биш.';
        } else if (/InvalidStateError|already registered|exclude/i.test(blob)) {
            error.value = 'Энэ төхөөрөмж өмнө бүртгэгдсэн байж магадгүй.';
            tip.value = 'Профайл → Хуруу/нүүр хэсгээс шалгана уу.';
            router.reload({ only: ['appLock'] });
        } else if (/NotSupportedError|SecurityError/i.test(blob)) {
            error.value = 'Энэ браузер биометрикийг дэмжихгүй эсвэл HTTPS биш байна.';
            tip.value = 'Chrome-оор https://manage.dornogovi.gov.mn хаягаар нээнэ үү.';
        } else {
            error.value = msg;
            tip.value = 'Дахин оролдоод, утасны хуруу/нүүр түгжээ идэвхтэй эсэхийг шалгана уу.';
        }
    } finally {
        busy.value = false;
    }
};

const dismissForNow = () => {
    visible.value = false;
    try {
        sessionStorage.setItem('biometric_setup_skip', '1');
    } catch {
        // ignore
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
                «Идэвхжүүлэх» дараад гарч ирэх цонхонд
                <b class="text-slate-700">Continue</b>
                дарна. Ингэснээр дараагийн удаа хуруу/нүүр асууна.
            </p>

            <p v-if="error" class="mt-4 text-center text-sm text-red-600">{{ error }}</p>
            <p v-if="tip" class="mt-2 text-center text-xs text-slate-500">{{ tip }}</p>

            <button
                type="button"
                class="ui-btn-primary mt-6 w-full"
                :disabled="busy"
                @click="activate"
            >
                {{ busy ? 'Хүлээнэ үү…' : 'Хуруу / нүүр идэвхжүүлэх' }}
            </button>

            <button
                type="button"
                class="mt-2 w-full text-center text-xs font-medium text-slate-500 hover:text-slate-700"
                :disabled="busy"
                @click="dismissForNow"
            >
                Дараа идэвхжүүлнэ
            </button>
        </div>
    </div>
</template>
