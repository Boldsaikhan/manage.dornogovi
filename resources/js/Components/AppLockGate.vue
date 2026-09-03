<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';
import {
    clearWebAuthnDeviceHint,
    hasWebAuthnDeviceHint,
    markWebAuthnDevice,
} from '@/utils/pwaClient';
import { assertBiometric, isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';

const LOCK_KEY = 'md_app_locked';
const LAST_ACTIVE_KEY = 'md_last_active';
const HIDE_GRACE_MS = 4000;
const AWAY_LOCK_MS = 1000;

const isPageReload = () => {
    if (typeof performance === 'undefined') {
        return false;
    }

    const nav = performance.getEntriesByType('navigation')[0];

    return nav?.type === 'reload';
};

const page = usePage();

const password = ref('');
const busy = ref(false);
const error = ref('');
const offline = ref(typeof navigator !== 'undefined' ? ! navigator.onLine : false);
const clientLocked = ref(false);
const bioSupported = ref(false);
const bioBusy = ref(false);
const setupBusy = ref(false);
const setupSuccess = ref(false);
const localWebAuthn = ref(false);
const skipBackgroundLock = ref(isPageReload());

let suppressHideUntil = 0;
let hiddenAt = 0;

const lock = computed(() => page.props.appLock ?? {
    locked: false,
    mode: null,
    hasWebAuthn: false,
    idleMinutes: 30,
    reason: null,
});

const idleMs = computed(() => Math.max(1, Number(lock.value.idleMinutes || 30)) * 60 * 1000);

/** Зөвхөн энэ утсан дээр идэвхжүүлсэн үед л биометрик санал болгоно. */
const canBiometric = computed(() => bioSupported.value && localWebAuthn.value);

const canSetupBiometric = computed(() => bioSupported.value && ! localWebAuthn.value && ! offline.value);

const userId = computed(() => page.props.auth?.user?.id ?? null);

const storageKey = () => `${LOCK_KEY}:${userId.value || 0}`;
const lastActiveKey = () => `${LAST_ACTIVE_KEY}:${userId.value || 0}`;

const shouldGuard = () => (
    !! page.props.auth?.user
    && isMobileDevice()
);

const showLock = computed(() => {
    if (! shouldGuard()) {
        return false;
    }

    if (skipBackgroundLock.value && lock.value.reason === 'background') {
        return false;
    }

    return clientLocked.value || !! lock.value.locked;
});

const lockDescription = computed(() => {
    if (offline.value) {
        return 'Сүлжээгүй үед апп түгжигдсэн байна. Интернэт холбогдсоны дараа нээнэ үү.';
    }

    if (lock.value.reason === 'background') {
        return canBiometric.value
            ? 'Аппыг үргэлжлүүлэхийн тулд хуруу / царайгаар баталгаажуулна уу.'
            : 'Аппыг үргэлжлүүлэхийн тулд нууц үгээрээ баталгаажуулна уу.';
    }

    return canBiometric.value
        ? `${lock.value.idleMinutes || 30} минут идэвхгүй болсон тул хуруу / цараайгаар дахин нээнэ үү.`
        : `${lock.value.idleMinutes || 30} минут идэвхгүй болсон тул нууц үгээр дахин нээнэ үү.`;
});

const suppressHideLock = (ms = HIDE_GRACE_MS) => {
    suppressHideUntil = Date.now() + ms;
};

const isHideSuppressed = () => Date.now() < suppressHideUntil;

const recordActivity = () => {
    try {
        localStorage.setItem(lastActiveKey(), String(Date.now()));
    } catch {
        // ignore
    }
};

const minutesIdle = () => {
    const last = Number(localStorage.getItem(lastActiveKey()) || 0);

    if (! last) {
        return 0;
    }

    return Date.now() - last;
};

const idleExpired = () => minutesIdle() >= idleMs.value;

const setClientLock = (on) => {
    clientLocked.value = on;
};

const requestIdleLock = async () => {
    if (! shouldGuard() || ! idleExpired()) {
        return;
    }

    setClientLock(true);

    try {
        await window.axios.post(route('app.lock'), { idle: true });
    } catch {
        // Сервер түгжээгүй байсан ч клиент түгжээг харуулна
    }
};

const requestBackgroundLock = async () => {
    if (! shouldGuard() || showLock.value) {
        return;
    }

    setClientLock(true);

    try {
        await window.axios.post(route('app.lock'), { background: true });
    } catch {
        // Сервер түгжээгүй байсан ч клиент түгжээг харуулна
    }
};

const evaluateLock = async () => {
    if (! shouldGuard()) {
        setClientLock(false);

        return;
    }

    if (! idleExpired()) {
        setClientLock(false);

        return;
    }

    await requestIdleLock();
};

const onActivity = () => {
    if (! shouldGuard() || showLock.value) {
        return;
    }

    recordActivity();
};

const lockAfterReturn = async () => {
    if (! shouldGuard() || showLock.value || isHideSuppressed()) {
        return;
    }

    await requestBackgroundLock();
};

const onVisibilityChange = () => {
    offline.value = ! navigator.onLine;

    if (document.hidden) {
        if (busy.value || bioBusy.value || setupBusy.value || isHideSuppressed()) {
            return;
        }

        hiddenAt = Date.now();

        return;
    }

    if (busy.value || isHideSuppressed()) {
        return;
    }

    if (clientLocked.value || lock.value.locked) {
        hiddenAt = 0;

        return;
    }

    const awayMs = hiddenAt ? Date.now() - hiddenAt : 0;
    hiddenAt = 0;

    if (awayMs >= AWAY_LOCK_MS) {
        lockAfterReturn();

        return;
    }

    if (! idleExpired()) {
        setClientLock(false);
        recordActivity();

        return;
    }

    evaluateLock();
};

const onPageShow = (event) => {
    offline.value = ! navigator.onLine;

    if (busy.value || isHideSuppressed()) {
        return;
    }

    if (event?.persisted) {
        lockAfterReturn();

        return;
    }

    if (clientLocked.value || lock.value.locked) {
        return;
    }

    if (! idleExpired()) {
        setClientLock(false);
        recordActivity();

        return;
    }

    evaluateLock();
};

const onOnline = () => {
    offline.value = false;
};

const onOffline = () => {
    offline.value = true;
};

onMounted(async () => {
    bioSupported.value = isWebAuthnSupported();
    localWebAuthn.value = hasWebAuthnDeviceHint();

    if (shouldGuard()) {
        try {
            localStorage.removeItem(storageKey());
        } catch {
            // ignore
        }

        if (isPageReload()) {
            setClientLock(false);
            try {
                await window.axios.post(route('app.lock.dismiss-reload'));
            } catch {
                // ignore
            }
            skipBackgroundLock.value = false;
            recordActivity();
        } else if (lock.value.locked && lock.value.reason !== 'background') {
            setClientLock(true);
        } else if (idleExpired()) {
            evaluateLock();
        } else {
            setClientLock(false);
            recordActivity();
        }
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pageshow', onPageShow);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    ['touchstart', 'click', 'keydown', 'scroll'].forEach((eventName) => {
        window.addEventListener(eventName, onActivity, { passive: true });
    });
});

onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    window.removeEventListener('pageshow', onPageShow);
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    ['touchstart', 'click', 'keydown', 'scroll'].forEach((eventName) => {
        window.removeEventListener(eventName, onActivity);
    });
});

watch(showLock, (v) => {
    if (v) {
        password.value = '';
        error.value = '';
        setupSuccess.value = false;
    }
});

const clearLockLocal = () => {
    setClientLock(false);
    recordActivity();
};

const syncServerLock = async () => {
    if (lock.value.locked) {
        return;
    }

    const payload = lock.value.reason === 'background' || clientLocked.value
        ? { background: true }
        : { idle: true };

    try {
        await window.axios.post(route('app.lock'), payload);
    } catch {
        // ignore
    }
};

const finishUnlock = async () => {
    password.value = '';
    clearLockLocal();
    suppressHideLock(HIDE_GRACE_MS);
    router.reload({ only: ['appLock', 'vault'] });
};

const handleUnlockError = (e, fallback = 'Түгжээ тайлагдахгүй байна.') => {
    if (e?.response?.status === 419) {
        error.value = 'Холболтын хугацаа дууссан. Хуудас шинэчлэгдэж байна…';
        return;
    }

    if (! navigator.onLine) {
        error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        return;
    }

    error.value = e?.response?.data?.errors?.password?.[0]
        || e?.response?.data?.errors?.webauthn?.[0]
        || e?.response?.data?.message
        || fallback;
};

const unlock = async () => {
    if (busy.value) return;

    offline.value = ! navigator.onLine;
    if (offline.value) {
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
        await syncServerLock();
        await window.axios.post(route('app.unlock.password'), { password: password.value });
        await finishUnlock();
    } catch (e) {
        handleUnlockError(e);
    } finally {
        busy.value = false;
    }
};

/** Энэ утсан дээр хуруу/царай идэвхжүүлэх (түгжээний дэлгэцнээс). */
const setupBiometric = async () => {
    if (setupBusy.value || bioBusy.value || busy.value) return;

    offline.value = ! navigator.onLine;
    if (offline.value) {
        error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        return;
    }

    setupBusy.value = true;
    setupSuccess.value = false;
    error.value = '';
    suppressHideLock(60000);

    try {
        await registerBiometric();
        markWebAuthnDevice();
        localWebAuthn.value = true;
        setupSuccess.value = true;
    } catch (e) {
        const name = e?.name || '';
        if (/NotAllowedError|AbortError/i.test(name)) {
            error.value = 'Үйлдэл цуцлагдлаа. Нууц үгээр нээнэ үү.';
        } else {
            handleUnlockError(e, 'Идэвхжүүлж чадсангүй. Нууц үгээр нээнэ үү.');
        }
        suppressHideLock(HIDE_GRACE_MS);
    } finally {
        setupBusy.value = false;
    }
};

/** Хуруу / царайгаар түгжээ тайлах. Амжилтгүй бол нууц үгийн талбар хэвээр үлдэнэ. */
const unlockBiometric = async ({ skipSetupCheck = false } = {}) => {
    if ((! skipSetupCheck && ! canBiometric.value) || bioBusy.value || busy.value) return;
    if (! skipSetupCheck && setupBusy.value) return;

    offline.value = ! navigator.onLine;
    if (offline.value) {
        error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        return;
    }

    bioBusy.value = true;
    error.value = '';

    // Биометрик цонх нээгдэхэд апп нуугдсан гэж тооцогдож дахин түгжихээс сэргийлнэ.
    suppressHideLock(30000);

    try {
        await syncServerLock();

        const assertion = await assertBiometric();

        await window.axios.post(route('app.unlock'), { assertion });
        markWebAuthnDevice();
        localWebAuthn.value = true;
        await finishUnlock();
    } catch (e) {
        const name = e?.name || '';

        if (/NotAllowedError|AbortError/i.test(name)) {
            clearWebAuthnDeviceHint();
            localWebAuthn.value = false;
            error.value = 'Энэ утсанд хуруу/царай бүртгэгдээгүй. «Идэвхжүүлэх» эсвэл нууц үгээр нээнэ үү.';
        } else {
            handleUnlockError(e, 'Баталгаажуулж чадсангүй. Нууц үгээрээ нээнэ үү.');
        }

        suppressHideLock(HIDE_GRACE_MS);
    } finally {
        bioBusy.value = false;
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
                Дахин нэвтрэх
            </h2>
            <p class="mt-1 text-center text-sm text-slate-500">
                {{ lockDescription }}
            </p>

            <div
                v-if="offline"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs text-amber-800"
            >
                Офлайн горимд апп нээгдэхгүй. Сүлжээ холбогдсоны дараа «Дахин оролдох» дарна.
            </div>

            <!-- Хуруу / цараай — үндсэн арга тул дээд талд байрлана. -->
            <p
                v-if="setupBusy"
                class="mt-6 text-center text-xs leading-relaxed text-brand-navy-700"
            >
                Google цонх гарвал <strong>Continue</strong> дарж passkey үүсгэнэ үү.
            </p>

            <p
                v-if="setupSuccess"
                class="mt-6 rounded-xl bg-emerald-50 px-3 py-2 text-center text-xs text-emerald-800"
            >
                Идэвхжлээ. Доор «Хуруу / цараайгаар нээх» дарна уу.
            </p>

            <button
                v-if="canBiometric && !offline"
                type="button"
                class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-navy-700 disabled:opacity-60"
                :disabled="bioBusy || busy || setupBusy"
                @click="unlockBiometric"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.567 3-3.5S13.657 4 12 4 9 5.567 9 7.5 10.343 11 12 11z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20c.8-3.2 2.9-5 5.5-5s4.7 1.8 5.5 5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8.5c-.8.6-1.3 1.6-1.3 2.7 0 2.3 1.6 3.8 3.3 4.3M17 8.5c.8.6 1.3 1.6 1.3 2.7 0 2.3-1.6 3.8-3.3 4.3" />
                </svg>
                {{ bioBusy ? 'Хүлээж байна…' : 'Хуруу / цараайгаар нээх' }}
            </button>

            <button
                v-if="canSetupBiometric"
                type="button"
                class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-brand-navy-200 bg-brand-navy-50 px-5 py-3 text-sm font-semibold text-brand-navy-700 transition hover:border-brand-navy-300 hover:bg-brand-navy-100 disabled:opacity-60"
                :disabled="setupBusy || bioBusy || busy"
                @click="setupBiometric"
            >
                {{ setupBusy ? 'Passkey үүсгэж байна…' : 'Хуруу / цараай идэвхжүүлэх' }}
            </button>

            <div v-if="(canBiometric || canSetupBiometric) && !offline" class="mt-5 flex items-center gap-3">
                <span class="h-px flex-1 bg-slate-200"></span>
                <span class="text-xs text-slate-400">эсвэл нэвтрэх нэр, нууц үгээр</span>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="unlock">
                <div v-if="!offline">
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

                <button
                    type="submit"
                    class="ui-btn-primary w-full"
                    :disabled="busy"
                >
                    <span v-if="busy">Шалгаж байна…</span>
                    <span v-else-if="offline">Дахин оролдох</span>
                    <span v-else>Нэвтрэх нэр, нууц үгээр нэвтрэх</span>
                </button>
            </form>

            <p class="mt-4 text-center text-[11px] text-slate-400">
                {{ page.props.auth?.user?.name }} · {{ page.props.auth?.user?.email || page.props.auth?.user?.phone }}
            </p>
        </div>
    </div>
</template>
