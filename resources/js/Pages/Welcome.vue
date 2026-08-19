<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    systems: {
        type: Array,
        default: () => [],
    },
});

const iconPaths = {
    wallet: 'M3 7a2 2 0 012-2h11a2 2 0 012 2v1M3 7v10a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2H5a2 2 0 01-2-2zM17 13h.01',
    building: 'M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M4 21h16M16 9h2a2 2 0 012 2v10M8 7h2M8 11h2M8 15h2',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    mail: 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l9 6 9-6',
    chart: 'M4 20V10M10 20V4M16 20v-6M22 20H2',
    clipboard: 'M9 4h6a1 1 0 011 1v1H8V5a1 1 0 011-1zM8 6H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-2M9 12h6M9 16h4',
    lock: 'M5 11h14a1 1 0 011 1v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8a1 1 0 011-1zM8 11V7a4 4 0 018 0v4',
    bolt: 'M13 2L4.5 13.5H11l-1 8.5 8.5-11.5H12l1-8.5z',
    shield: 'M12 3l7.5 3v5.5c0 4.5-3.1 8.4-7.5 9.5-4.4-1.1-7.5-5-7.5-9.5V6L12 3z',
    grid: 'M4 5a1 1 0 011-1h5v6H4V5zM14 4h5a1 1 0 011 1v5h-6V4zM4 14h6v6H5a1 1 0 01-1-1v-5zM14 14h6v5a1 1 0 01-1 1h-5v-6z',
};

const features = [
    {
        icon: 'grid',
        title: 'Нэг цонхны хандалт',
        text: 'Ажилдаа хэрэглэдэг төрийн бүх системүүд нэг дор жагсаана. Хаяг хайх, хавчуургаа ухах шаардлагагүй.',
    },
    {
        icon: 'lock',
        title: 'Шифрлэгдсэн нэвтрэх мэдээлэл',
        text: 'Хэрэглэгч бүрийн нэр, нууц үг тусад нь шифрлэгдэж хадгална. Зөвхөн эзэмшигч нь өөрийн нууц үгээ баталгаажуулж задлана.',
    },
    {
        icon: 'bolt',
        title: 'Хурдан нэвтрэлт',
        text: 'Мэдээллийн санг нээсний дараа систем дээр дарахад нэвтрэх маягт автоматаар бөглөгдөнө.',
    },
    {
        icon: 'shield',
        title: 'Байгууллагын хяналт',
        text: 'Админ системүүдийн жагсаалт, нэвтрэх тохиргоог төвлөрсөн байдлаар удирдана.',
    },
];
</script>

<template>
    <Head title="Нүүр" />

    <div class="min-h-screen bg-brand-navy-50 font-sans text-brand-navy-700">
        <!-- Толгой -->
        <header class="sticky top-0 z-40 border-b border-brand-navy-100 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-navy-600 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths.grid" />
                        </svg>
                    </span>
                    <div class="leading-tight">
                        <div class="text-sm font-semibold text-brand-navy-900">Дорноговь аймаг</div>
                        <div class="text-xs text-brand-navy-400">Системүүдийн нэгдсэн портал</div>
                    </div>
                </div>

                <nav v-if="canLogin" class="flex items-center gap-2">
                    <Link
                        :href="route('login')"
                        class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-brand-orange-600"
                    >
                        Нэвтрэх
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 transition hover:bg-brand-navy-50"
                    >
                        Бүртгүүлэх
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Танилцуулга -->
        <section class="border-b border-brand-navy-100 bg-white">
            <div class="mx-auto grid max-w-6xl gap-10 px-6 py-16 lg:grid-cols-2 lg:items-center">
                <div>
                    <span class="inline-block rounded-full bg-brand-orange-100 px-3 py-0.5 text-xs font-medium text-brand-orange-700">
                        Дорноговь аймгийн Засаг даргын Тамгын газар
                    </span>
                    <h1 class="mt-4 text-3xl font-bold leading-tight text-brand-navy-900 sm:text-4xl">
                        Төрийн системүүд рүү<br class="hidden sm:block" />
                        нэг цэгээс нэвтрэх
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-brand-navy-400">
                        Албан хаагч бүр өдөр тутам ашигладаг системүүдээ энд бүртгэж, нэвтрэх мэдээллээ
                        шифрлэгдсэн хэлбэрээр хадгална. Дахин дахин нууц үг сануулах, хаяг хайх шаардлагагүй.
                    </p>

                    <div v-if="canLogin" class="mt-8 flex flex-wrap items-center gap-3">
                        <Link
                            :href="route('login')"
                            class="rounded-md bg-brand-orange-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-orange-600"
                        >
                            Системд нэвтрэх
                        </Link>
                        <a
                            href="#systems"
                            class="rounded-md border border-brand-navy-200 px-5 py-2.5 text-sm font-medium text-brand-navy-700 transition hover:bg-brand-navy-50"
                        >
                            Холбогдсон системүүд
                        </a>
                    </div>
                </div>

                <!-- Системийн жагсаалтын товч харагдац -->
                <div class="rounded-xl border border-brand-navy-100 bg-brand-navy-50 p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-brand-navy-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths.lock" />
                        </svg>
                        Хамгаалагдсан хандалт
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="system in systems.slice(0, 5)"
                            :key="system.id"
                            class="flex items-center gap-3 rounded-lg border border-brand-navy-100 bg-white px-4 py-3"
                        >
                            <svg class="h-5 w-5 shrink-0 text-brand-navy-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                            </svg>
                            <span class="truncate text-sm font-medium text-brand-navy-800">{{ system.name }}</span>
                            <span class="ml-auto shrink-0 rounded-full bg-brand-navy-100 px-2 py-0.5 text-xs text-brand-navy-600">
                                {{ system.category }}
                            </span>
                        </div>
                        <p v-if="!systems.length" class="py-6 text-center text-sm text-brand-navy-400">
                            Систем бүртгэгдээгүй байна.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Боломжууд -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <h2 class="text-xl font-semibold text-brand-navy-900">Юу хийж чадах вэ</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-navy-50 text-brand-navy-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths[feature.icon]" />
                        </svg>
                    </span>
                    <h3 class="mt-4 font-semibold text-brand-navy-800">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-brand-navy-400">{{ feature.text }}</p>
                </div>
            </div>
        </section>

        <!-- Системүүд -->
        <section id="systems" class="border-t border-brand-navy-100 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-16">
                <h2 class="text-xl font-semibold text-brand-navy-900">Холбогдсон системүүд</h2>
                <p class="mt-2 text-sm text-brand-navy-400">
                    Нэвтэрсний дараа доорх системүүд рүү шууд хандах боломжтой.
                </p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="system in systems"
                        :key="system.id"
                        class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm transition hover:border-brand-orange-500"
                    >
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-navy-50 text-brand-navy-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-semibold text-brand-navy-800">{{ system.name }}</h3>
                                <span class="mt-1 inline-block rounded-full bg-brand-navy-100 px-2 py-0.5 text-xs text-brand-navy-600">
                                    {{ system.category }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-brand-navy-400">{{ system.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Хөл -->
        <footer class="border-t border-brand-navy-100 bg-brand-navy-800">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-sm text-brand-navy-300 sm:flex-row sm:items-center sm:justify-between">
                <span>Дорноговь аймгийн Засаг даргын Тамгын газар</span>
                <span class="text-brand-navy-400">Дотоод хэрэглээний систем</span>
            </div>
        </footer>
    </div>
</template>
