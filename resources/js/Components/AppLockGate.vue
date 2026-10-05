<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';

const LOCK_KEY = 'md_app_locked';
const LAST_ACTIVE_KEY = 'md_last_active';

// sessionStorage нь refresh, хуудас хоорондын шилжилтээр устдаггүй ч апп/табыг
// хаахад цэвэрлэгддэг — тиймээс апп шинээр нээгдсэн эсэхийг энэчлэн танина.
const SESSION_KEY = 'md_app_session';
const RELAUNCH_KEY = 'md_relaunch_lock';
const HIDE_GRACE_MS = 4000;
const AWAY_LOCK_MS = 1000;

const isPageReload = () => {
    if (typeof performance === 'undefined') {
        return false;
    }

    const nav = performance.getEntriesByType('navigation')[0];

    return nav?.type === 'reload';
};

/**
 * Аппыг хаагаад дахин нээсэн эсэх.
 *
 * Идэвхгүй байсан хугацаанаас үл хамааран баталгаажуулалт шаардана.
 */
const isColdStart = () => {
    try {
        if (sessionStorage.getItem(SESSION_KEY)) {
            return false;
        }

        sessionStorage.setItem(SESSION_KEY, '1');

        return true;
    } catch {
        return false;
    }
};

const coldStart = isColdStart();

const page = usePage();

const password = ref('');
const busy = ref(false);
const error = ref('');
const offline = ref(typeof navigator !== 'undefined' ? ! navigator.onLine : false);
const clientLocked = ref(false);
const skipBackgroundLock = ref(isPageReload());

// Апп дахин нээгдсэн түгжээ — баталгаажуултал болтлоо арилахгүй
// (refresh хийж тойрох боломжгүй байхын тулд localStorage-д тэмдэглэнэ).
const relaunchLocked = ref(false);

let suppressHideUntil = 0;
let hiddenAt = 0;

const lock = computed(() => page.props.appLock ?? {
    locked: false,
    mode: null,
    idleMinutes: 30,
    reason: null,
});

const idleMs = computed(() => Math.max(1, Number(lock.value.idleMinutes || 30)) * 60 * 1000);

const userId = computed(() => page.props.auth?.user?.id ?? null);

const storageKey = () => `${LOCK_KEY}:${userId.value || 0}`;
const lastActiveKey = () => `${LAST_ACTIVE_KEY}:${userId.value || 0}`;
const relaunchKey = () => `${RELAUNCH_KEY}:${userId.value || 0}`;

const setRelaunchLock = (on) => {
    relaunchLocked.value = on;

    try {
        if (on) {
            localStorage.setItem(relaunchKey(), '1');
        } else {
            localStorage.removeItem(relaunchKey());
        }
    } catch {
        // ignore
    }
};

const hasPendingRelaunchLock = () => {
    try {
        return localStorage.getItem(relaunchKey()) === '1';
    } catch {
        return false;
    }
};

const shouldGuard = () => (
    !! page.props.auth?.user
    && isMobileDevice()
);

const showLock = computed(() => {
    if (! shouldGuard()) {
        return false;
    }

    // Апп дахин нээгдсэн — refresh хийсэн ч арилахгүй.
    if (relaunchLocked.value) {
        return true;
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

    if (relaunchLocked.value) {
        return 'Апп дахин нээгдсэн тул нууц үгээрээ баталгаажуулна уу.';
    }

    if (lock.value.reason === 'background') {
        return 'Аппыг үргэлжлүүлэхийн тулд нууц үгээрээ баталгаажуулна уу.';
    }

    return `${lock.value.idleMinutes || 30} минут идэвхгүй болсон тул нууц үгээр дахин нээнэ үү.`;
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
        if (busy.value || isHideSuppressed()) {
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
    if (shouldGuard()) {
        try {
            localStorage.removeItem(storageKey());
        } catch {
            // ignore
        }

        // Аппыг хаагаад дахин нээсэн — идэвхгүй хугацаанаас үл хамааран асууна.
        if (coldStart || hasPendingRelaunchLock()) {
            setRelaunchLock(true);
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
    }
});

const clearLockLocal = () => {
    setClientLock(false);
    setRelaunchLock(false);
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

/** Алдааг хүнд ойлгомжтой бичвэр болгоно. */
const unlockErrorMessage = (e, fallback = 'Түгжээ тайлагдахгүй байна.') => {
    if (e?.response?.status === 419) {
        return 'Холболтын хугацаа дууссан. Хуудас шинэчлэгдэж байна…';
    }

    if (! navigator.onLine) {
        return 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
    }

    const message = e?.response?.data?.errors?.password?.[0]
        || e?.response?.data?.message;

    if (message) {
        return message;
    }

    // Серверийн тайлбар байхгүй бол хөтчийн алдааны нэрийг хавсаргана —
    // ямар шалтгаанаар болохгүй байгааг хэлж өгөх боломжтой болно.
    return e?.name ? `${fallback} (${e.name})` : fallback;
};

const handleUnlockError = (e, fallback = 'Түгжээ тайлагдахгүй байна.') => {
    error.value = unlockErrorMessage(e, fallback);
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

            <form v-if="!offline" class="mt-5 space-y-4" @submit.prevent="unlock">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Нууц үг</label>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        autofocus
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
                    <span v-else>Нэвтрэх нэр, нууц үгээр нэвтрэх</span>
                </button>
            </form>

            <form v-else class="mt-5 space-y-4" @submit.prevent="unlock">
                <p v-if="error" class="text-center text-sm text-red-600">{{ error }}</p>

                <button
                    type="submit"
                    class="ui-btn-primary w-full"
                    :disabled="busy"
                >
                    Дахин оролдох
                </button>
            </form>

            <p class="mt-4 text-center text-[11px] text-slate-400">
                {{ page.props.auth?.user?.name }} · {{ page.props.auth?.user?.email || page.props.auth?.user?.phone }}
            </p>
        </div>
    </div>
</template>
