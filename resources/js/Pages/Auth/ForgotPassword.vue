<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import StateEmblem from '@/Components/StateEmblem.vue';
import OrnamentMark from '@/Components/OrnamentMark.vue';

const props = defineProps({
    status: {
        type: String,
    },
    /** Утасны сувгийн алхам: phone → code → password. */
    phoneState: {
        type: Object,
        default: () => ({ step: 'phone', phone: null, channel: null }),
    },
});

/** Утсаар сэргээх нь үндсэн арга — и-мэйл нь нөөц суваг. */
const channel = ref('phone');

const step = computed(() => props.phoneState?.step ?? 'phone');

// Код илгээгдсэн бол утасны суваг руу өөрөө буцаж ирнэ.
watch(step, (value) => {
    if (value !== 'phone') {
        channel.value = 'phone';
    }
}, { immediate: true });

const phoneForm = useForm({ phone: '' });
const codeForm = useForm({ code: '' });
const passwordForm = useForm({ password: '', password_confirmation: '' });
const emailForm = useForm({ email: '' });

const sendCode = () => {
    phoneForm.post(route('password.phone.send'), { preserveScroll: true });
};

const confirmCode = () => {
    codeForm.post(route('password.phone.confirm'), {
        preserveScroll: true,
        onSuccess: () => codeForm.reset('code'),
    });
};

const savePassword = () => {
    passwordForm.post(route('password.phone.update'), { preserveScroll: true });
};

const startOver = () => {
    router.post(route('password.phone.cancel'), {}, { preserveScroll: true });
};

const submitEmail = () => {
    emailForm.post(route('password.email'), {
        onSuccess: () => emailForm.reset('email'),
    });
};

/**
 * verify.mn нь баталгаажуулалтаа өөрөө мэдэгдэж болно (callback).
 * Тиймээс кодын алхам дээр байхад төлөвийг тогтмол асууна.
 */
let poller = null;

const stopPolling = () => {
    if (poller) {
        clearInterval(poller);
        poller = null;
    }
};

const startPolling = () => {
    stopPolling();

    poller = setInterval(async () => {
        try {
            const { data } = await axios.get(route('password.phone.status'));

            if (data?.verified) {
                stopPolling();
                router.reload({ only: ['phoneState'] });
            }
        } catch {
            // Сүлжээ тасарвал дараагийн оролдлогод дахин шалгана.
        }
    }, 4000);
};

watch(step, (value) => (value === 'code' ? startPolling() : stopPolling()), { immediate: true });

onMounted(() => {
    if (step.value === 'code') startPolling();
});

onBeforeUnmount(stopPolling);

const maskedPhone = computed(() => {
    const phone = props.phoneState?.phone;

    return phone ? `${phone.slice(0, 4)}••••` : '';
});
</script>

<template>
    <Head title="Нэвтрэх мэдээлэл сэргээх" />

    <div class="min-h-screen bg-slate-100 p-3 sm:p-6 lg:p-8">
        <div class="mx-auto flex min-h-[calc(100vh-1.5rem)] max-w-md items-center justify-center">
            <div class="w-full rounded-3xl bg-white px-6 py-10 shadow-xl shadow-slate-300/40 sm:px-10">
                <div class="text-center">
                    <StateEmblem class="mx-auto h-16 w-16" />

                    <h1 class="mt-4 text-base font-bold leading-snug tracking-tight text-brand-navy-700">
                        НЭВТРЭХ МЭДЭЭЛЭЛ СЭРГЭЭХ
                    </h1>

                    <div class="mt-4 flex items-center gap-3">
                        <span class="h-px flex-1 bg-slate-200"></span>
                        <OrnamentMark class="h-3 w-8 text-brand-orange-500" />
                        <span class="h-px flex-1 bg-slate-200"></span>
                    </div>
                </div>

                <!-- Суваг сонгох -->
                <div v-if="step === 'phone'" class="mt-6 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="channel === 'phone' ? 'bg-white text-brand-navy-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="channel = 'phone'"
                    >
                        Утсаар
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                        :class="channel === 'email' ? 'bg-white text-brand-navy-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                        @click="channel = 'email'"
                    >
                        И-мэйлээр
                    </button>
                </div>

                <div
                    v-if="status"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    {{ status }}
                </div>

                <!-- 1. Утасны дугаар -->
                <template v-if="channel === 'phone' && step === 'phone'">
                    <p class="mt-5 text-sm leading-relaxed text-slate-500">
                        Бүртгэлтэй утасны дугаараа оруулна уу. Бид тухайн дугаар руу
                        <strong class="text-brand-navy-700">нэг удаагийн баталгаажуулах код</strong>
                        илгээнэ.
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="sendCode">
                        <div>
                            <div
                                class="group relative rounded-xl border-2 bg-white transition focus-within:border-brand-navy-600 focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                                :class="phoneForm.errors.phone ? 'border-red-400' : 'border-slate-200'"
                            >
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-navy-600">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293a.75.75 0 01-.87.265 12.035 12.035 0 01-7.143-7.143.75.75 0 01.265-.87l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102A1.125 1.125 0 005.872 2.25H4.5A2.25 2.25 0 002.25 4.5v2.25z"
                                        />
                                    </svg>
                                </span>

                                <label for="phone" class="absolute left-12 top-2 text-xs font-medium text-slate-400">
                                    Утасны дугаар
                                </label>

                                <input
                                    id="phone"
                                    v-model="phoneForm.phone"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="99112233"
                                    required
                                    autofocus
                                    class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-4 pt-6 text-base text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-0"
                                />
                            </div>

                            <p v-if="phoneForm.errors.phone" class="mt-1.5 text-sm text-red-600">
                                {{ phoneForm.errors.phone }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="phoneForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ phoneForm.processing ? 'Илгээж байна…' : 'Код илгээх' }}
                        </button>
                    </form>
                </template>

                <!-- 2. Код -->
                <template v-else-if="channel === 'phone' && step === 'code'">
                    <p class="mt-5 text-sm leading-relaxed text-slate-500">
                        <strong class="text-brand-navy-700">{{ maskedPhone }}</strong> дугаар руу илгээсэн
                        баталгаажуулах кодыг оруулна уу.
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="confirmCode">
                        <div>
                            <label for="code" class="mb-1.5 block text-xs font-medium text-slate-400">
                                Баталгаажуулах код
                            </label>
                            <input
                                id="code"
                                v-model="codeForm.code"
                                type="text"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                placeholder="000000"
                                required
                                autofocus
                                class="w-full rounded-xl border-2 bg-white px-4 py-3.5 text-center text-2xl font-semibold tracking-[0.4em] text-slate-800 placeholder-slate-200 transition focus:border-brand-navy-600 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                                :class="codeForm.errors.code ? 'border-red-400' : 'border-slate-200'"
                            />

                            <p v-if="codeForm.errors.code" class="mt-1.5 text-sm text-red-600">
                                {{ codeForm.errors.code }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="codeForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ codeForm.processing ? 'Шалгаж байна…' : 'Баталгаажуулах' }}
                        </button>
                    </form>

                    <button
                        type="button"
                        class="mt-4 w-full text-center text-sm font-medium text-brand-navy-500 hover:text-brand-navy-600 hover:underline"
                        @click="startOver"
                    >
                        Өөр дугаар оруулах
                    </button>
                </template>

                <!-- 3. Шинэ нууц үг -->
                <template v-else-if="channel === 'phone' && step === 'password'">
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        Дугаар баталгаажлаа. Шинэ нууц үгээ тавина уу.
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="savePassword">
                        <div>
                            <label for="password" class="mb-1.5 block text-xs font-medium text-slate-400">
                                Шинэ нууц үг
                            </label>
                            <input
                                id="password"
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                required
                                autofocus
                                class="w-full rounded-xl border-2 bg-white px-4 py-3 text-base text-slate-800 transition focus:border-brand-navy-600 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                                :class="passwordForm.errors.password ? 'border-red-400' : 'border-slate-200'"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1.5 text-sm text-red-600">
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-1.5 block text-xs font-medium text-slate-400">
                                Нууц үгээ давтах
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-base text-slate-800 transition focus:border-brand-navy-600 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/10"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ passwordForm.processing ? 'Хадгалж байна…' : 'Нууц үг солих' }}
                        </button>
                    </form>
                </template>

                <!-- Нөөц суваг — и-мэйл -->
                <template v-else>
                    <p class="mt-5 text-sm leading-relaxed text-slate-500">
                        Бүртгэлтэй и-мэйл хаягаа оруулна уу. Бид тухайн хаяг руу
                        <strong class="text-brand-navy-700">нэвтрэх нэр болон шинэ түр нууц үг</strong>
                        илгээнэ.
                    </p>

                    <form class="mt-5 space-y-4" @submit.prevent="submitEmail">
                        <div>
                            <div
                                class="group relative rounded-xl border-2 bg-white transition focus-within:border-brand-navy-600 focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                                :class="emailForm.errors.email ? 'border-red-400' : 'border-slate-200'"
                            >
                                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-navy-600">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"
                                        />
                                    </svg>
                                </span>

                                <label for="email" class="absolute left-12 top-2 text-xs font-medium text-slate-400">
                                    И-мэйл хаяг
                                </label>

                                <input
                                    id="email"
                                    v-model="emailForm.email"
                                    type="email"
                                    autocomplete="username"
                                    placeholder="ner@dornogovi.gov.mn"
                                    required
                                    class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-4 pt-6 text-base text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-0"
                                />
                            </div>

                            <p v-if="emailForm.errors.email" class="mt-1.5 text-sm text-red-600">
                                {{ emailForm.errors.email }}
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="emailForm.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ emailForm.processing ? 'Илгээж байна…' : 'Нэвтрэх мэдээлэл илгээх' }}
                        </button>
                    </form>

                    <p class="mt-4 text-center text-xs leading-relaxed text-slate-400">
                        Аюулгүй байдлын үүднээс хуучин нууц үг тань хүчингүй болж,
                        шинэ түр нууц үг үүснэ. Нэвтэрсний дараа өөрийн нууц үгээ солино уу.
                    </p>
                </template>

                <div class="mt-6 text-center">
                    <Link
                        :href="route('login')"
                        class="text-sm font-medium text-brand-navy-500 hover:text-brand-navy-600 hover:underline"
                    >
                        ← Нэвтрэх хуудас руу буцах
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
