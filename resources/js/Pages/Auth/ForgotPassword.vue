<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import StateEmblem from '@/Components/StateEmblem.vue';
import OrnamentMark from '@/Components/OrnamentMark.vue';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'), {
        onSuccess: () => form.reset('email'),
    });
};
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

                <p class="mt-5 text-sm leading-relaxed text-slate-500">
                    Бүртгэлтэй и-мэйл хаягаа оруулна уу. Бид тухайн хаяг руу
                    <strong class="text-brand-navy-700">нэвтрэх нэр болон шинэ түр нууц үг</strong>
                    илгээнэ.
                </p>

                <div
                    v-if="status"
                    class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    {{ status }}
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div>
                        <div
                            class="group relative rounded-xl border-2 bg-white transition focus-within:border-brand-navy-600 focus-within:ring-4 focus-within:ring-brand-navy-600/10"
                            :class="form.errors.email ? 'border-red-400' : 'border-slate-200'"
                        >
                            <span
                                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-navy-600"
                            >
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
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                placeholder="ner@dornogovi.gov.mn"
                                required
                                autofocus
                                class="w-full border-0 bg-transparent pb-2.5 pl-12 pr-4 pt-6 text-base text-slate-800 placeholder-slate-300 focus:outline-none focus:ring-0"
                            />
                        </div>

                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-navy-600/25 transition hover:bg-brand-navy-700 focus:outline-none focus:ring-4 focus:ring-brand-navy-600/30 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Илгээж байна…' : 'Нэвтрэх мэдээлэл илгээх' }}
                    </button>
                </form>

                <p class="mt-4 text-center text-xs leading-relaxed text-slate-400">
                    Аюулгүй байдлын үүднээс хуучин нууц үг тань хүчингүй болж,
                    шинэ түр нууц үг үүснэ. Нэвтэрсний дараа өөрийн нууц үгээ солино уу.
                </p>

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
