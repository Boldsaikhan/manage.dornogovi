<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';

const LOCK_KEY = 'md_app_locked';
const LAST_ACTIVE_KEY = 'md_last_active';
const HIDE_GRACE_MS = 4000;

const page = usePage();

const password = ref('');
const busy = ref(false);
const error = ref('');
const offline = ref(typeof navigator !== 'undefined' ? ! navigator.onLine : false);
const clientLocked = ref(false);

let suppressHideUntil = 0;

const lock = computed(() => page.props.appLock ?? {
    locked: false,
    mode: null,
    idleMinutes: 30,
});

const idleMs = computed(() => Math.max(1, Number(lock.value.idleMinutes || 30)) * 60 * 1000);

const userId = computed(() => page.props.auth?.user?.id ?? null);

const storageKey = () => `${LOCK_KEY}:${userId.value || 0}`;
const lastActiveKey = () => `${LAST_ACTIVE_KEY}:${userId.value || 0}`;

const shouldGuard = () => (
    !! page.props.auth?.user
    && isMobileDevice()
);

const showLock = computed(() => shouldGuard() && (clientLocked.value || !! lock.value.locked));

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

const onVisibilityChange = () => {
    offline.value = ! navigator.onLine;

    if (document.hidden) {
        recordActivity();

        return;
    }

    if (busy.value || isHideSuppressed()) {
        return;
    }

    if (! idleExpired()) {
        setClientLock(false);
        recordActivity();

        return;
    }

    evaluateLock();
};

const onPageShow = () => {
    offline.value = ! navigator.onLine;

    if (busy.value || isHideSuppressed()) {
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

onMounted(() => {
    if (shouldGuard()) {
        if (idleExpired()) {
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
    recordActivity();
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
        if (! lock.value.locked) {
            suppressHideLock(HIDE_GRACE_MS);
            try {
                await window.axios.post(route('app.lock'), { idle: true });
            } catch {
                // ignore
            }
        }

        await window.axios.post(route('app.unlock'), { password: password.value });
        password.value = '';
        clearLockLocal();
        suppressHideLock(HIDE_GRACE_MS);
        router.reload({ only: ['appLock', 'vault'] });
    } catch (e) {
        if (! navigator.onLine) {
            error.value = 'Сүлжээгүй байна. Холбогдсоны дараа дахин оролдоно уу.';
        } else {
            error.value = e?.response?.data?.errors?.password?.[0]
                || e?.response?.data?.message
                || 'Түгжээ тайлагдахгүй байна.';
        }
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
                {{ offline
                    ? 'Сүлжээгүй үед апп түгжигдсэн байна. Интернэт холбогдсоны дараа нээнэ үү.'
                    : `${lock.idleMinutes || 30} минут идэвхгүй болсон тул нууц үгээр дахин нээнэ үү.` }}
            </p>

            <div
                v-if="offline"
                class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs text-amber-800"
            >
                Офлайн горимд апп нээгдэхгүй. Сүлжээ холбогдсоны дараа «Дахин оролдох» дарна.
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="unlock">
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
                    <span v-else>Нээх</span>
                </button>
            </form>

            <p class="mt-4 text-center text-[11px] text-slate-400">
                {{ page.props.auth?.user?.name }} · {{ page.props.auth?.user?.email || page.props.auth?.user?.phone }}
            </p>
        </div>
    </div>
</template>
