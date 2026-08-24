<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StateEmblem from '@/Components/StateEmblem.vue';
import SoyomboMark from '@/Components/SoyomboMark.vue';
import OrnamentMark from '@/Components/OrnamentMark.vue';

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
const showPassword = ref(false);

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const isPhone = computed(() => mode.value === 'phone');

/** Утасны дугаарыг "9911 1234" хэлбэрээр харуулна (хадгалахдаа цифр л явна). */
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

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const features = [
    {
        title: 'Аюулгүй байдал',
        text: 'Өндөр түвшний нууцлал, мэдээллийн хамгаалалт',
        icon: 'shield',
    },
    {
        title: 'Хурдан, хүртээмжтэй',
        text: 'Хаанаас ч, хэзээ ч хандах боломжтой',
        icon: 'clock',
    },
    {
        title: 'Нэгдсэн систем',
        text: 'Бүх үйл ажиллагааг нэгдсэн системд',
        icon: 'grid',
    },
    {
        title: 'Хамтын ажиллагаа',
        text: 'Хэлтэс хоорондын уялдаа холбоог сайжруулна',
        icon: 'users',
    },
];
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

                    <h2
                        class="mt-6 text-sm font-bold tracking-wide text-brand-navy-700"
                    >
                        НЭВТРЭХ
                    </h2>

                    <div
                        v-if="status"
                        class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        {{ status }}
                    </div>

                    <form class="mt-4 space-y-4" @submit.prevent="submit">
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
                                    placeholder="9911 1234"
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
                                class="group relative rounded-xl border-2 bg-slate-50 transition focus-within:border-brand-navy-600 focus-within:bg-white focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                                :class="
                                    form.errors.password
                                        ? 'border-red-400'
                                        : 'border-transparent'
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

                    <!-- Бусад арга -->
                    <div class="mt-6 flex items-center gap-3">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <span class="text-xs text-slate-400"
                            >Бусад аргаар нэвтрэх</span
                        >
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>

                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-slate-200 px-5 py-3 text-sm font-medium text-slate-700 transition hover:border-brand-navy-300 hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
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

                    <!-- Нууцлалын мэдэгдэл -->
                    <div
                        class="mt-6 flex gap-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200/70"
                    >
                        <svg
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-brand-navy-500"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.746 3.746 0 0121 12z"
                            />
                        </svg>
                        <div>
                            <p class="text-xs font-bold text-brand-navy-700">
                                АЮУЛГҮЙ, НУУЦЛАЛТАЙ
                            </p>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500">
                                Таны мэдээлэл нь Монгол Улсын төрийн мэдээллийн
                                аюулгүй байдлын стандартад нийцүүлэн хамгаалагдана.
                            </p>
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs text-slate-400">
                        © {{ new Date().getFullYear() }} Дорноговь аймгийн ЗДТГ.
                        Бүх эрх хуулиар хамгаалагдана.
                    </p>
                </div>
            </div>

            <!-- ============================ Баруун тал — танилцуулга ============================ -->
            <div
                class="relative hidden overflow-hidden rounded-3xl bg-gradient-to-b from-brand-navy-600 via-brand-navy-700 to-brand-navy-900 lg:flex lg:flex-col"
            >
                <!-- гоёл чимэглэлийн хээ -->
                <div
                    class="pointer-events-none absolute inset-0 opacity-[0.07]"
                    aria-hidden="true"
                >
                    <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern
                                id="khee"
                                width="56"
                                height="56"
                                patternUnits="userSpaceOnUse"
                            >
                                <path
                                    d="M8 8h40v40H8z M16 16h24v24H16z"
                                    fill="none"
                                    stroke="white"
                                    stroke-width="2"
                                />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#khee)" />
                    </svg>
                </div>

                <!-- Уриа -->
                <div
                    class="relative flex flex-1 flex-col items-center justify-center px-10 pt-14 text-center"
                >
                    <SoyomboMark class="h-16 w-16 text-brand-orange-300" />

                    <h2
                        class="mt-6 text-2xl font-bold leading-snug tracking-tight text-white xl:text-3xl"
                    >
                        ТӨРИЙН ҮЙЛЧИЛГЭЭГ<br />ТАНЫГ ТӨЛӨӨ
                    </h2>

                    <div class="mt-5 flex items-center gap-3">
                        <span class="h-px w-16 bg-white/25"></span>
                        <OrnamentMark class="h-3 w-8 text-brand-orange-300" />
                        <span class="h-px w-16 bg-white/25"></span>
                    </div>

                    <p
                        class="mt-4 text-xs font-medium tracking-[0.2em] text-white/70"
                    >
                        НЭГДСЭН · ХУРДАН · ХЯЛБАР · АЮУЛГҮЙ
                    </p>
                </div>

                <!-- Онцлогууд -->
                <div class="relative px-6 pb-6">
                    <div
                        class="grid grid-cols-2 gap-3 rounded-2xl bg-white/10 p-5 backdrop-blur-sm xl:grid-cols-4"
                    >
                        <div
                            v-for="feature in features"
                            :key="feature.title"
                            class="text-center"
                        >
                            <div
                                class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 text-white"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                >
                                    <path
                                        v-if="feature.icon === 'shield'"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751A11.959 11.959 0 0112 2.714z"
                                    />
                                    <path
                                        v-else-if="feature.icon === 'clock'"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                    <path
                                        v-else-if="feature.icon === 'grid'"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"
                                    />
                                    <path
                                        v-else
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"
                                    />
                                </svg>
                            </div>

                            <p
                                class="mt-2.5 text-[11px] font-bold uppercase tracking-wide text-white"
                            >
                                {{ feature.title }}
                            </p>
                            <p class="mt-1 text-[11px] leading-snug text-white/65">
                                {{ feature.text }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
