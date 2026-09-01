<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import QRCode from 'qrcode';
import StateEmblem from '@/Components/StateEmblem.vue';
import OrnamentMark from '@/Components/OrnamentMark.vue';
import QrScanButton from '@/Components/QrScanButton.vue';
import { isMobileDevice } from '@/utils/mobileClient';
import {
    hasWebAuthnDeviceHint,
    isStandalonePwa,
    markWebAuthnDevice,
} from '@/utils/pwaClient';
import { isWebAuthnSupported, loginWithBiometric } from '@/utils/webauthn';
import MobileAppInstall from '@/Components/MobileAppInstall.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

/** 'phone' — үндсэн арга, 'email' — нөөц арга */
const mode = ref('phone');
const onPhone = ref(false);
const qrScanner = ref(null);
const showPassword = ref(false);

/* ---------------- Хуруу / царайгаар нэвтрэх (WebAuthn) ---------------- */
const bioSupported = ref(false);
const bioBusy = ref(false);
const bioError = ref('');
const autoBioTried = ref(false);
const preferBiometric = ref(false);

/** Утсан дээр, HTTPS-тэй, хөтөч дэмждэг үед л товчийг үзүүлнэ. */
const canBiometric = computed(() => onPhone.value && bioSupported.value);

const shouldAutoBiometric = computed(() => (
    canBiometric.value
    && (isStandalonePwa() || hasWebAuthnDeviceHint())
));

const loginBiometric = async () => {
    if (bioBusy.value) return;

    bioBusy.value = true;
    bioError.value = '';

    try {
        const data = await loginWithBiometric();
        markWebAuthnDevice();
        window.location.href = data?.redirect || '/';
    } catch (e) {
        const name = e?.name || '';
        const msg = e?.response?.data?.errors?.webauthn?.[0]
            || e?.response?.data?.message
            || e?.message
            || '';

        if (/NotAllowedError|AbortError/i.test(name)) {
            bioError.value = 'Үйлдэл цуцлагдлаа.';
            preferBiometric.value = false;
        } else if (e?.response?.status === 422) {
            bioError.value = msg
                || 'Энэ төхөөрөмж бүртгэгдээгүй байна. Эхлээд нууц үгээрээ нэвтэрч, Профайл хэсгээс идэвхжүүлнэ үү.';
            preferBiometric.value = false;
        } else {
            bioError.value = msg || 'Нэвтэрч чадсангүй. Нууц үгээрээ орно уу.';
            preferBiometric.value = false;
        }
    } finally {
        bioBusy.value = false;
    }
};

/** public/images/building.jpg байхгүй бол градиент дэвсгэрээр орлуулна */
const buildingMissing = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const isPhone = computed(() => mode.value === 'phone');

/** Утасны дугаарыг "8923 9655" хэлбэрээр харуулна (хадгалахдаа цифр л явна). */
const phoneDisplay = computed({
    get: () => {
        const digits = form.login.replace(/\D/g, '').slice(0, 8);

        return digits.length > 4
            ? `${digits.slice(0, 4)} ${digits.slice(4)}`
            : digits;
    },
    set: (value) => {
        form.login = value.replace(/\D/g, '').slice(0, 8);
    },
});

const switchMode = () => {
    mode.value = isPhone.value ? 'email' : 'phone';
    form.login = '';
    form.clearErrors();
};

/* ---------------- QR кодоор нэвтрэх ---------------- */

const isQr = computed(() => mode.value === 'qr');

const qrImage = ref('');
const qrSeconds = ref(0);
const qrState = ref('idle');   // idle | loading | waiting | approved | expired | rejected | error

let pollTimer = null;
let tickTimer = null;

const stopQrTimers = () => {
    clearInterval(pollTimer);
    clearInterval(tickTimer);
    pollTimer = null;
    tickTimer = null;
};

const startQr = async () => {
    stopQrTimers();
    qrState.value = 'loading';
    qrImage.value = '';

    try {
        const { data } = await window.axios.post(route('login.qr.create'));

        // QR-ыг тус тусын браузерт шууд зурна — серверт зураг үүсгэхгүй.
        qrImage.value = await QRCode.toDataURL(data.url, {
            width: 512,
            margin: 1,
            errorCorrectionLevel: 'M',
            color: { dark: '#1e3a5f', light: '#ffffff' },
        });

        qrSeconds.value = data.expires_in;
        qrState.value = 'waiting';

        tickTimer = setInterval(() => {
            if (--qrSeconds.value <= 0) {
                stopQrTimers();
                qrState.value = 'expired';
            }
        }, 1000);

        pollTimer = setInterval(() => pollQr(data.token), 2000);
    } catch (e) {
        qrState.value = 'error';
    }
};

const pollQr = async (token) => {
    try {
        const { data } = await window.axios.get(route('login.qr.status', token));

        if (data.status === 'approved') {
            stopQrTimers();
            qrState.value = 'approved';
            router.visit(data.redirect);

            return;
        }

        if (data.status === 'expired' || data.status === 'rejected') {
            stopQrTimers();
            qrState.value = data.status;
        }
    } catch (e) {
        // Түр алдаа — дараагийн асуултаар үргэлжлүүлнэ.
    }
};

const openQr = () => {
    mode.value = 'qr';
    form.clearErrors();
    startQr();
};

const closeQr = () => {
    stopQrTimers();
    mode.value = 'phone';
};

const qrCountdown = computed(() => {
    const m = Math.floor(qrSeconds.value / 60);
    const sec = qrSeconds.value % 60;

    return `${m}:${String(sec).padStart(2, '0')}`;
});

onMounted(() => {
    onPhone.value = isMobileDevice();
    bioSupported.value = isWebAuthnSupported();
    preferBiometric.value = shouldAutoBiometric.value;

    if (shouldAutoBiometric.value && ! autoBioTried.value) {
        autoBioTried.value = true;
        window.setTimeout(() => {
            loginBiometric();
        }, 350);
    }
});

onBeforeUnmount(stopQrTimers);

const submit = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    form.transform((data) => (token ? { ...data, _token: token } : data)).post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Нэвтрэх" />

    <div class="login-page min-h-screen bg-slate-100 p-3 sm:p-6 lg:p-8">
        <div
            class="mx-auto grid min-h-[calc(100vh-1.5rem)] max-w-7xl gap-6 sm:min-h-[calc(100vh-3rem)] lg:min-h-[calc(100vh-4rem)] lg:grid-cols-2"
        >
            <!-- ============================ Зүүн тал — нэвтрэх ============================ -->
            <div
                class="flex items-center justify-center rounded-3xl bg-white px-6 py-10 shadow-xl shadow-slate-300/40 sm:px-10"
            >
                <div class="w-full max-w-sm">
                    <!-- Толгой -->
                    <div class="text-center">
                        <StateEmblem class="mx-auto h-20 w-20" />

                        <h1
                            class="mt-5 text-lg font-bold leading-snug tracking-tight text-brand-navy-700"
                        >
                            ДОРНОГОВЬ АЙМГИЙН<br />ЗАСАГ ДАРГЫН ТАМГЫН ГАЗАР
                        </h1>

                        <p
                            class="mt-2 text-sm font-medium tracking-wide text-slate-500"
                        >
                            ДОТООД НЭГДСЭН СИСТЕМ
                        </p>

                        <div class="mt-5 flex items-center gap-3">
                            <span class="h-px flex-1 bg-slate-200"></span>
                            <OrnamentMark class="h-3 w-8 text-brand-orange-500" />
                            <span class="h-px flex-1 bg-slate-200"></span>
                        </div>
                    </div>

                    <MobileAppInstall v-if="onPhone" />

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-bold tracking-wide text-brand-navy-700">
                            НЭВТРЭХ
                        </h2>
                        <QrScanButton ref="qrScanner" />
                    </div>

                    <div
                        v-if="status"
                        class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <form v-if="! isQr && ! (preferBiometric && bioBusy)" class="mt-4 space-y-4" @submit.prevent="submit">
                        <!-- Нэвтрэх нэр: утас эсвэл и-мэйл -->
                        <div>
                            <div
                                class="group relative rounded-xl border-2 bg-white transition focus-within:border-brand-navy-600 focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                                :class="
                                    form.errors.login
                                        ? 'border-red-400'
                                        : 'border-slate-200'
                                "
                            >
                                <span
                                    class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-navy-600"
                                >
                                    <!-- утас -->
                                    <svg
                                        v-if="isPhone"
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"
                                        />
                                    </svg>
                                    <!-- и-мэйл -->
                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                                        />
                                    </svg>
                                </span>

                                <label
                                    :for="'login'"
                                    class="absolute left-12 top-2 text-xs font-medium text-slate-400"
                                >
                                    {{ isPhone ? 'Утасны дугаар' : 'И-мэйл хаяг' }}
                                </label>

                                <input
                                    v-if="isPhone"
                                    id="login"
                                    v-model="phoneDisplay"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="8923 9655"
                                    required
                                    autofocus
                                    class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-4 pt-6 text-base tracking-wide text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-0"
                                />
                                <input
                                    v-else
                                    id="login"
                                    v-model="form.login"
                                    type="email"
                                    autocomplete="username"
                                    placeholder="admin@dornogovi.gov.mn"
                                    required
                                    autofocus
                                    class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-4 pt-6 text-base text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-0"
                                />
                            </div>

                            <p
                                v-if="form.errors.login"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.login }}
                            </p>
                        </div>

                        <!-- Нууц үг -->
                        <div>
                            <div
                                class="group relative rounded-xl border-2 bg-white transition focus-within:border-brand-navy-600 focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                                :class="
                                    form.errors.password
                                        ? 'border-red-400'
                                        : 'border-slate-200'
                                "
                            >
                                <span
                                    class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-navy-600"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                                        />
                                    </svg>
                                </span>

                                <label
                                    for="password"
                                    class="absolute left-12 top-2 text-xs font-medium text-slate-400"
                                >
                                    Нууц үг
                                </label>

                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-12 pt-6 text-base text-slate-800 focus:outline-none focus:ring-0"
                                />

                                <button
                                    type="button"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-brand-navy-600 focus:outline-none focus:ring-2 focus:ring-brand-navy-600/30"
                                    :aria-label="
                                        showPassword
                                            ? 'Нууц үгийг нуух'
                                            : 'Нууц үгийг харуулах'
                                    "
                                    @click="showPassword = !showPassword"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.774 3.162 10.066 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <p
                                v-if="form.errors.password"
                                class="mt-1.5 text-sm text-red-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <!-- Санах / нууц үг мартсан -->
                        <div class="flex items-center justify-between">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-sm text-slate-600"
                            >
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    name="remember"
                                    class="h-4 w-4 rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                                />
                                Намайг сана
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-sm font-medium text-brand-navy-500 hover:text-brand-navy-600 hover:underline"
                            >
                                Нууц үгээ мартсан уу?
                            </Link>
                        </div>

                        <!-- Нэвтрэх товч -->
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="group flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span>{{
                                form.processing ? 'Нэвтэрч байна…' : 'Нэвтрэх'
                            }}</span>
                            <svg
                                v-if="!form.processing"
                                class="h-4 w-4 transition group-hover:translate-x-1"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"
                                />
                            </svg>
                        </button>
                    </form>

                    <div
                        v-if="! isQr && preferBiometric && bioBusy"
                        class="mt-4 rounded-2xl border border-brand-navy-200 bg-brand-navy-50 px-4 py-8 text-center"
                    >
                        <p class="text-sm font-semibold text-brand-navy-800">Хуруу / цараайгаар нэвтэрч байна…</p>
                        <p class="mt-1 text-xs text-slate-500">Төхөөрөмжийн баталгаажуулалтыг гүйцээж дуусгана уу.</p>
                    </div>

                    <!-- QR кодоор нэвтрэх — зөвхөн товч дарахад, компьютер дээр -->
                    <div v-if="isQr && ! onPhone" class="mt-4">
                        <div class="relative mx-auto flex h-[248px] w-[248px] items-center justify-center rounded-2xl border-2 border-slate-200 bg-white p-3">
                            <img
                                v-if="qrImage && qrState === 'waiting'"
                                :src="qrImage"
                                alt="QR код"
                                class="h-full w-full"
                            />

                            <div v-else-if="qrState === 'loading'" class="text-sm text-slate-400">
                                Үүсгэж байна…
                            </div>

                            <div v-else-if="qrState === 'approved'" class="text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <p class="mt-2 text-sm font-medium text-emerald-700">Нэвтэрч байна…</p>
                            </div>

                            <div v-else class="px-4 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ qrState === 'rejected'
                                        ? 'Хүсэлт цуцлагдсан.'
                                        : (qrState === 'error'
                                            ? 'Холбогдож чадсангүй.'
                                            : 'QR кодын хугацаа дууссан.') }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-3 rounded-lg bg-brand-navy-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-navy-700"
                                    @click="startQr"
                                >
                                    Шинэчлэх
                                </button>
                            </div>
                        </div>

                        <p class="mt-4 text-center text-sm leading-relaxed text-slate-500">
                            Утаснаасаа <strong class="text-brand-navy-700">нэвтэрсэн эрхээрээ</strong> энэ QR кодыг
                            уншуулаад зөвшөөрөхөд энэ компьютер шууд нэвтэрнэ.
                        </p>

                        <p v-if="qrState === 'waiting'" class="mt-2 text-center text-xs text-slate-400">
                            Хүчинтэй хугацаа: {{ qrCountdown }}
                        </p>
                    </div>

                    <!-- Бусад арга -->
                    <div class="mt-6 flex items-center gap-3">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-xs text-slate-400"
                            >Бусад аргаар нэвтрэх</span
                        >
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <!-- Хуруу / царайгаар нэвтрэх — зөвхөн утсан дээр -->
                    <button
                        v-if="canBiometric && ! isQr"
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-brand-navy-200 bg-brand-navy-50 px-5 py-3 text-sm font-semibold text-brand-navy-700 transition hover:border-brand-navy-300 hover:bg-brand-navy-100 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10 disabled:opacity-60"
                        :disabled="bioBusy"
                        @click="loginBiometric"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.567 3-3.5S13.657 4 12 4 9 5.567 9 7.5 10.343 11 12 11z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20c.8-3.2 2.9-5 5.5-5s4.7 1.8 5.5 5" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8.5c-.8.6-1.3 1.6-1.3 2.7 0 2.3 1.6 3.8 3.3 4.3M17 8.5c.8.6 1.3 1.6 1.3 2.7 0 2.3-1.6 3.8-3.3 4.3" />
                        </svg>
                        {{ bioBusy ? 'Хүлээж байна…' : 'Хуруу / царайгаар нэвтрэх' }}
                    </button>

                    <p
                        v-if="bioError && ! isQr"
                        class="mt-2 text-center text-xs leading-relaxed text-red-600"
                    >
                        {{ bioError }}
                    </p>

                    <button
                        v-if="isQr"
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-brand-navy-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                        @click="closeQr"
                    >
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                        </svg>
                        Нууц үгээр нэвтрэх
                    </button>

                    <button
                        v-if="! isQr && onPhone"
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-brand-navy-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                        @click="qrScanner?.open()"
                    >
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5v4.5h-4.5zM15.75 4.5h4.5v4.5h-4.5zM3.75 15h4.5v4.5h-4.5zM15.75 15h1.5v1.5h-1.5zM19.5 15h.75v.75h-.75zM15.75 18.75h1.5v.75h-1.5zM19.5 18h.75v1.5h-.75zM12 3.75v6M12 12.75v7.5M3.75 12h4.5M12 12h8.25" />
                        </svg>
                        QR уншуулах
                    </button>

                    <button
                        v-if="! isQr && ! onPhone"
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-brand-navy-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                        @click="openQr"
                    >
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5v4.5h-4.5zM15.75 4.5h4.5v4.5h-4.5zM3.75 15h4.5v4.5h-4.5zM15.75 15h1.5v1.5h-1.5zM19.5 15h.75v.75h-.75zM15.75 18.75h1.5v.75h-1.5zM19.5 18h.75v1.5h-.75zM12 3.75v6M12 12.75v7.5M3.75 12h4.5M12 12h8.25" />
                        </svg>
                        QR кодоор нэвтрэх
                    </button>

                    <button
                        v-if="! isQr"
                        type="button"
                        class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-brand-navy-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                        @click="switchMode"
                    >
                        <svg
                            class="h-4 w-4 text-slate-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                v-if="isPhone"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"
                            />
                        </svg>
                        {{ isPhone ? 'И-мэйлээр нэвтрэх' : 'Утсаар нэвтрэх' }}
                    </button>

                    <p class="mt-6 text-center text-xs text-slate-400">
                        © {{ new Date().getFullYear() }} Дорноговь аймгийн ЗДТГ.
                        Бүх эрх хуулиар хамгаалагдана.
                    </p>
                </div>
            </div>

            <!-- ============================ Баруун тал — ордон ============================ -->
            <div
                class="relative hidden overflow-hidden rounded-3xl bg-brand-navy-800 lg:flex lg:flex-col"
            >
                <img
                    v-if="!buildingMissing"
                    src="/images/building.jpg"
                    alt="Нутгийн удирдлагын ордон"
                    class="absolute inset-0 h-full w-full object-cover object-center"
                    @error="buildingMissing = true"
                />

                <!-- Зураг байхгүй үед градиент -->
                <div
                    v-if="buildingMissing"
                    class="absolute inset-0 bg-gradient-to-b from-brand-navy-600 via-brand-navy-700 to-brand-navy-900"
                    aria-hidden="true"
                ></div>

                <!-- Доод хэсэгт хөнгөн бүдгэрүүлэлт + уриа -->
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-brand-navy-950/80 via-brand-navy-900/20 to-transparent"
                    aria-hidden="true"
                ></div>

                <div class="relative mt-auto px-8 pb-10 pt-24 text-center">
                    <div class="flex items-center justify-center gap-3">
                        <span class="h-px w-12 bg-white/40"></span>
                        <OrnamentMark class="h-3 w-8 text-brand-orange-300" />
                        <span class="h-px w-12 bg-white/40"></span>
                    </div>
                    <p class="mt-3 text-xs font-medium tracking-[0.2em] text-white/85">
                        НЭГДСЭН · ХУРДАН · ХЯЛБАР · АЮУЛГҮЙ
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
