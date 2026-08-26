<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { assertBiometric, isWebAuthnSupported } from '@/utils/webauthn';
import { isMobileDevice } from '@/utils/mobileClient';

const LOCK_KEY = 'md_app_locked';

const page = usePage();

const password = ref('');
const busy = ref(false);
const error = ref('');
const hint = ref('');
const showPasswordFallback = ref(false);
const offline = ref(typeof navigator !== 'undefined' ? ! navigator.onLine : false);
const webauthnOk = ref(typeof window !== 'undefined' && isWebAuthnSupported());
const clientLocked = ref(false);

let locking = false;

const lock = computed(() => page.props.appLock ?? {
    locked: false,
    mode: null,
    hasWebAuthn: false,
});

const userId = computed(() => page.props.auth?.user?.id ?? null);

const storageKey = () => `${LOCK_KEY}:${userId.value || 0}`;

/** Гар утас + нэвтэрсэн — зөвхөн дэлгэц алга болоход түгжинэ */
const shouldGuard = () => (
    !! page.props.auth?.user
    && isMobileDevice()
);

const hasWebAuthn = computed(() => !! lock.value.hasWebAuthn && webauthnOk.value);

/** Цэс шилжилт серверийн session lock-ийг биш, зөвхөн клиент түгжээг харуулна.
 *  Серверийн lock зөвхөн нэвтрэхэд / дэлгэц алга болоход тавигдана.
 */
const showLock = computed(() => shouldGuard() && (clientLocked.value || !! lock.value.locked));

const isBiometricOnly = computed(() => (
    hasWebAuthn.value
    && (lock.value.mode === 'biometric' || clientLocked.value)
    && ! showPasswordFallback.value
));

const needsPassword = computed(() => ! isBiometricOnly.value || showPasswordFallback.value);

const title = computed(() => (
    isBiometricOnly.value && ! showPasswordFallback.value
        ? 'Хуруу / нүүрээр баталгаажуулна уу'
        : 'Дахин нэвтрэх'
));

const subtitle = computed(() => {
    if (offline.value) {
        return 'Сүлжээгүй үед апп түгжигдсэн байна. Интернэт холбогдсоны дараа нээнэ үү.';
    }

    return isBiometricOnly.value && ! showPasswordFallback.value
        ? 'Дэлгэцээс гарсан тул хуруу эсвэл нүүрээр дахин баталгаажуулна.'
        : 'Дэлгэцээс гарсан тул нууц үгээр дахин нээнэ үү.';
});

const readClientLock = () => {
    try {
        return localStorage.getItem(storageKey()) === '1';
    } catch {
        return false;
    }
};

const setClientLock = (on) => {
    clientLocked.value = on;
    try {
        if (on) {
            localStorage.setItem(storageKey(), '1');
        } else {
            localStorage.removeItem(storageKey());
        }
    } catch {
        // ignore
    }
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const requestLockBeacon = () => {
    if (! shouldGuard()) return;

    const body = new FormData();
    const token = csrfToken();
    if (token) body.append('_token', token);

    const url = route('app.lock');
    try {
        if (navigator.sendBeacon?.(url, body)) return;
    } catch {
        // ignore
    }

    fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(() => {});
};

/** Дэлгэц/апп алга болоход — сүлжээгүй байсан ч ШУУД түгжинэ */
const onAppHidden = () => {
    if (! shouldGuard()) return;
    if (document.visibilityState === 'visible' && ! document.hidden) return;

    setClientLock(true);
    requestLockBeacon();
};

const onVisibilityChange = () => {
    if (document.hidden) {
        onAppHidden();
        return;
    }

    // Буцаж ирэхэд зөвхөн өмнө түгжсэн бол харуулна — цэс солиход түгжихгүй
    offline.value = ! navigator.onLine;
    if (readClientLock()) {
        clientLocked.value = true;
    }
};

const onPageShow = (event) => {
    if (event.persisted && shouldGuard()) {
        setClientLock(true);
    }
    if (readClientLock()) {
        clientLocked.value = true;
    }
};

const onOnline = () => {
    offline.value = false;
};

const onOffline = () => {
    offline.value = true;
    // Офлайн болсон гээд шууд түгжихгүй — зөвхөн дэлгэц алга болоход
};

onMounted(() => {
    // Хуудас/цэс солиход layout дахин mount болж болно — зөвхөн хадгалсан түгжээг сэргээнэ
    if (shouldGuard() && readClientLock()) {
        clientLocked.value = true;
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pagehide', onAppHidden);
    window.addEventListener('pageshow', onPageShow);
    window.addEventListener('freeze', onAppHidden);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('pagehide', onAppHidden);
    window.removeEventListener('pageshow', onPageShow);
    window.removeEventListener('freeze', onAppHidden);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});

watch(showLock, (v) => {
    if (v) {
        password.value = '';
        error.value = '';
        hint.value = '';
        showPasswordFallback.value = false;
    }
});

const clearLockLocal = () => {
    setClientLock(false);
};

const unlock = async () => {
    if (busy.value) return;

    offline.value = ! navigator.onLine;
    if (offline.value) {
        error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        return;
    }

    busy.value = true;
    error.value = '';
    hint.value = '';

    try {
        const useBiometric = hasWebAuthn.value && ! showPasswordFallback.value;
        const payload = { require_biometric: useBiometric };

        if (! useBiometric) {
            if (! password.value) {
                error.value = 'Нууц үгээ оруулна уу.';
                return;
            }
            payload.password = password.value;
        }

        if (useBiometric) {
            try {
                payload.assertion = await assertBiometric();
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
        }

        // Серверт түгжээ байхгүй бол (зөвхөн клиент) эхлээд тавина
        if (! lock.value.locked) {
            locking = true;
            try {
                await window.axios.post(route('app.lock'));
            } catch {
                // ignore — unlock оролдлого үргэлжилнэ
            } finally {
                locking = false;
            }
        }

        await window.axios.post(route('app.unlock'), payload);
        password.value = '';
        clearLockLocal();
        router.reload({ only: ['appLock', 'vault'] });
    } catch (e) {
        if (! navigator.onLine) {
            error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        } else {
            error.value = e?.response?.data?.errors?.password?.[0]
                || e?.response?.data?.errors?.webauthn?.[0]
                || e?.response?.data?.message
                || 'Түгжээ тайлагдахгүй байна.';
            if (e?.response?.data?.errors?.webauthn) {
                showPasswordFallback.value = true;
            }
        }
    } finally {
        busy.value = false;
    }
};

const unlockPasswordOnly = async () => {
    if (busy.value) return;
    if (! navigator.onLine) {
        error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        return;
    }
    if (! password.value) {
        error.value = 'Нууц үгээ оруулна уу.';
        return;
    }

    busy.value = true;
    error.value = '';
    try {
        if (! lock.value.locked) {
            try {
                await window.axios.post(route('app.lock'));
            } catch {
                // ignore
            }
        }

        const { data } = await window.axios.post(route('app.unlock.password'), {
            password: password.value,
        });
        hint.value = data?.hint || '';
        password.value = '';
        clearLockLocal();
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
        v-if="showLock"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-brand-navy-950/90 p-4 backdrop-blur-md"
        role="dialog"
        aria-modal="true"
        aria-labelledby="app-lock-title"
        @touchmove.prevent
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

            <div
                v-if="offline"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs text-amber-800"
            >
                Офлайн горимд апп нээгдэхгүй. Сүлжээ холбогдсоны дараа «Дахин оролдох» дарна.
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="unlock">
                <div v-if="needsPassword && !offline">
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
                    <span v-else-if="offline">Дахин оролдох</span>
                    <span v-else-if="hasWebAuthn && !showPasswordFallback">
                        Хуруу / нүүрээр нээх
                    </span>
                    <span v-else>Нээх</span>
                </button>

                <button
                    v-if="showPasswordFallback && hasWebAuthn && !offline"
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
