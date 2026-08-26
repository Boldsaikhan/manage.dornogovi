<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { assertBiometric, isWebAuthnSupported } from '@/utils/webauthn';
import { isMobileDevice } from '@/utils/mobileClient';

const page = usePage();

const password = ref('');
const busy = ref(false);
const error = ref('');
const hint = ref('');
const showPasswordFallback = ref(false);
const webauthnOk = ref(typeof window !== 'undefined' && isWebAuthnSupported());

/** Энэ удаагийн «харагдах» циклд биометрик баталгаажсан эсэх */
let unlockedThisVisibleCycle = false;
let locking = false;

const lock = computed(() => page.props.appLock ?? {
    locked: false,
    mode: null,
    hasWebAuthn: false,
});

const locked = computed(() => !! lock.value.locked);
const isBiometricOnly = computed(() => lock.value.mode === 'biometric');
const needsPassword = computed(() => ! isBiometricOnly.value);
const hasWebAuthn = computed(() => !! lock.value.hasWebAuthn && webauthnOk.value);

const title = computed(() => (
    isBiometricOnly.value
        ? 'Хуруу / нүүрээр баталгаажуулна уу'
        : 'Дахин нэвтрэх'
));

const subtitle = computed(() => (
    isBiometricOnly.value
        ? 'Апп нээх бүрт хурууны хээ эсвэл царайгаар баталгаажуулна.'
        : 'Апп-аас гараад буцаж орсон тул нэвтрэх нууц үг болон биометрикийг асууна.'
));

/** Зөвхөн гар утсанд биометрик түгжээ */
const shouldGuard = () => (
    !! page.props.auth?.user
    && !! lock.value.hasWebAuthn
    && isMobileDevice()
);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/** Далдлахад сүлжээ тасарсан ч түгжих — sendBeacon ашиглана */
const requestLockBeacon = () => {
    if (! shouldGuard()) return;

    const body = new FormData();
    const token = csrfToken();
    if (token) body.append('_token', token);

    const url = route('app.lock');
    if (navigator.sendBeacon?.(url, body)) return;

    // sendBeacon боломжгүй бол синхрон fetch (pagehide-д)
    fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => {});
};

const engageLock = async () => {
    if (locking || ! shouldGuard() || unlockedThisVisibleCycle) return;

    locking = true;
    try {
        await window.axios.post(route('app.lock'));
        router.reload({ only: ['appLock', 'vault'] });
    } catch {
        // Дахин оролдоно
    } finally {
        locking = false;
    }
};

const onAppHidden = () => {
    unlockedThisVisibleCycle = false;
    requestLockBeacon();
};

const onAppVisible = () => {
    if (document.hidden || ! shouldGuard()) return;

    // Апп бүр дахин нээгдэхэд биометрик асууна
    if (! unlockedThisVisibleCycle) {
        engageLock();
    }
};

const promptBiometricIfLocked = () => {
    if (! locked.value || busy.value) return;
    if (! isBiometricOnly.value || ! hasWebAuthn.value) return;

    setTimeout(() => {
        if (locked.value && ! busy.value && ! unlockedThisVisibleCycle) {
            unlock();
        }
    }, 400);
};

onMounted(() => {
    document.addEventListener('visibilitychange', onAppVisible);
    window.addEventListener('pagehide', onAppHidden);
    window.addEventListener('pageshow', onAppVisible);

    onAppVisible();
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onAppVisible);
    window.removeEventListener('pagehide', onAppHidden);
    window.removeEventListener('pageshow', onAppVisible);
});

watch(locked, (v) => {
    if (v) {
        password.value = '';
        error.value = '';
        hint.value = '';
        showPasswordFallback.value = false;
        promptBiometricIfLocked();
    }
}, { immediate: true });

const unlock = async () => {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    hint.value = '';

    try {
        const payload = {};

        if (needsPassword.value) {
            if (! password.value) {
                error.value = 'Нууц үгээ оруулна уу.';
                return;
            }
            payload.password = password.value;
        }

        if (hasWebAuthn.value) {
            try {
                payload.assertion = await assertBiometric();
                payload.require_biometric = true;
            } catch (e) {
                const name = e?.name || '';
                if (/NotAllowedError|AbortError/i.test(name + String(e?.message))) {
                    error.value = 'Биометрик цуцлагдлаа.';
                    showPasswordFallback.value = true;
                    return;
                }
                showPasswordFallback.value = true;
                error.value = e?.response?.data?.message
                    || e?.message
                    || 'Биометрик амжилтгүй. Нууц үгээр үргэлжлүүлнэ үү.';
                return;
            }
        } else {
            payload.require_biometric = false;
        }

        await window.axios.post(route('app.unlock'), payload);
        password.value = '';
        unlockedThisVisibleCycle = true;
        router.reload({ only: ['appLock', 'vault'] });
    } catch (e) {
        error.value = e?.response?.data?.errors?.password?.[0]
            || e?.response?.data?.errors?.webauthn?.[0]
            || e?.response?.data?.message
            || 'Түгжээ тайлагдахгүй байна.';
        if (e?.response?.data?.errors?.webauthn) {
            showPasswordFallback.value = true;
        }
    } finally {
        busy.value = false;
    }
};

const unlockPasswordOnly = async () => {
    if (busy.value) return;
    if (! password.value) {
        error.value = 'Нууц үгээ оруулна уу.';
        return;
    }

    busy.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post(route('app.unlock.password'), {
            password: password.value,
        });
        hint.value = data?.hint || '';
        password.value = '';
        unlockedThisVisibleCycle = true;
        router.reload({ only: ['appLock', 'vault'] });
    } catch (e) {
        error.value = e?.response?.data?.errors?.password?.[0]
            || e?.response?.data?.message
            || 'Нууц үг буруу байна.';
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <div
        v-if="locked && shouldGuard()"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-brand-navy-950/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="app-lock-title"
    >
        <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-navy-50 text-brand-navy-700">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                </svg>
            </div>

            <h2 id="app-lock-title" class="mt-4 text-center text-lg font-bold text-brand-navy-900">
                {{ title }}
            </h2>
            <p class="mt-1 text-center text-sm text-slate-500">
                {{ subtitle }}
            </p>

            <form class="mt-6 space-y-4" @submit.prevent="unlock">
                <div v-if="needsPassword || showPasswordFallback">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Нууц үг</label>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        class="ui-input"
                        placeholder="Нэвтрэх нууц үг"
                    />
                </div>

                <p v-if="error" class="text-center text-sm text-red-600">{{ error }}</p>
                <p v-if="hint" class="text-center text-sm text-amber-700">{{ hint }}</p>

                <button
                    type="submit"
                    class="ui-btn-primary w-full"
                    :disabled="busy"
                >
                    <span v-if="busy">Шалгаж байна…</span>
                    <span v-else-if="hasWebAuthn && !showPasswordFallback">
                        {{ needsPassword ? 'Нууц үг + хуруу / нүүр' : 'Хуруу / нүүрээр нээх' }}
                    </span>
                    <span v-else>Нээх</span>
                </button>

                <button
                    v-if="showPasswordFallback && hasWebAuthn"
                    type="button"
                    class="ui-btn-ghost w-full"
                    :disabled="busy"
                    @click="unlockPasswordOnly"
                >
                    Зөвхөн нууц үгээр үргэлжлүүлэх
                </button>
            </form>

            <p class="mt-4 text-center text-[11px] text-slate-400">
                {{ page.props.auth?.user?.name }} · {{ page.props.auth?.user?.email || page.props.auth?.user?.phone }}
            </p>
        </div>
    </div>
</template>
