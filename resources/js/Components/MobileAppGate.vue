<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Гар утаснаас нэвтэрсэн үед аппаар ажиллуулах хаалга.
 *
 *  • Апп горимд (standalone) байвал юу ч харагдахгүй.
 *  • Апп суусан атлаа хөтчөөр орсон бол — асуухгүйгээр апп руу шилжүүлнэ.
 *  • iPhone: татах товч байхгүй — Safari «Нүүр дэлгэцэд нэмэх» заавар.
 *  • Компьютер дээр огт харагдахгүй.
 */
const APP_URL = '/dept-dashboard';

const visible = ref(false);
const mode = ref('install'); // install | installed | ios | ios-other
const deferredPrompt = ref(null);
const showFallback = ref(false);
const busy = ref(false);
const copied = ref(false);
const showSteps = ref(true);

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

/** iOS дээр зөвхөн Safari «Нүүр дэлгэцэд нэмэх»-ийг бүрэн дэмжинэ. */
const isIosSafari = () => {
    const ua = window.navigator.userAgent;

    if (! /iphone|ipad|ipod/i.test(ua)) {
        return false;
    }

    // Chrome / Firefox / Edge / Opera / in-app браузер
    if (/CriOS|FxiOS|EdgiOS|OPiOS|YaBrowser|DuckDuckGo|FBAN|FBAV|Instagram|Line\//i.test(ua)) {
        return false;
    }

    return /Safari/i.test(ua);
};

// Утас / таблет мөн эсэх — компьютер дээр хаалга ажиллахгүй.
const isMobile = () => /android|iphone|ipad|ipod|mobile/i.test(window.navigator.userAgent)
    || window.matchMedia('(max-width: 820px) and (pointer: coarse)').matches;

const onPrompt = (event) => {
    event.preventDefault();
    deferredPrompt.value = event;

    if (isMobile() && ! isStandalone()) {
        mode.value = 'install';
        visible.value = true;
    }
};

const onInstalled = () => {
    deferredPrompt.value = null;
    mode.value = 'installed';
    openApp();
};

const openApp = () => {
    busy.value = true;
    window.location.href = APP_URL;

    setTimeout(() => {
        busy.value = false;
        showFallback.value = true;
    }, 4000);
};

const install = async () => {
    const prompt = deferredPrompt.value;

    if (! prompt) {
        showFallback.value = true;

        return;
    }

    deferredPrompt.value = null;
    prompt.prompt();

    const choice = await prompt.userChoice;

    if (choice?.outcome !== 'accepted') {
        showFallback.value = true;
    }
};

const dismiss = () => {
    visible.value = false;
    sessionStorage.setItem('mdAppGateSkipped', '1');
};

const copyLink = async () => {
    const url = window.location.origin + APP_URL;

    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    } catch {
        window.prompt('Safari-ээр нээх холбоос:', url);
    }
};

const title = computed(() => {
    if (mode.value === 'installed') {
        return 'Аппаараа нээж байна…';
    }

    if (mode.value === 'ios-other') {
        return 'Safari-ээр нээнэ үү';
    }

    if (mode.value === 'ios') {
        return 'Апп болгож нэмэх (iPhone)';
    }

    return 'Утсандаа апп болгож суулгана уу';
});

onMounted(async () => {
    if (! isMobile() || isStandalone()) {
        return;
    }

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);

    try {
        const related = await window.navigator.getInstalledRelatedApps?.();

        if (related?.length) {
            mode.value = 'installed';
            visible.value = true;
            openApp();

            return;
        }
    } catch {
        // ignore
    }

    if (sessionStorage.getItem('mdAppGateSkipped') === '1') {
        return;
    }

    if (isIos()) {
        // iPhone дээр «татаж суулгах» товч байхгүй — заавар эсвэл Safari руу чиглүүлнэ.
        mode.value = isIosSafari() ? 'ios' : 'ios-other';
        visible.value = true;

        return;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onPrompt);
    window.removeEventListener('appinstalled', onInstalled);
});
</script>

<template>
    <!--
      iOS: доод талд байрлуулбал Safari-ийн Хуваалцах товчийг хааж төөрөгдүүлдэг тул
      дээд талд байрлуулна. Бүтэн харласан хаалт биш — доор зай үлдээнэ.
    -->
    <div
        v-if="visible"
        class="fixed inset-0 z-[100] flex justify-center p-4"
        :class="mode === 'ios' || mode === 'ios-other'
            ? 'items-start bg-brand-navy-950/50 pt-[max(1rem,env(safe-area-inset-top))]'
            : 'items-end bg-brand-navy-950/70 backdrop-blur-sm sm:items-center'"
    >
        <div class="w-full max-w-sm rounded-3xl bg-white p-5 shadow-2xl sm:p-6">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-navy-50 text-brand-navy-600">
                <svg v-if="mode === 'ios' || mode === 'ios-other'" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7M12 3v12m0 0l-4-4m4 4l4-4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h2 class="mt-4 text-center text-base font-bold text-brand-navy-800">{{ title }}</h2>

            <p v-if="mode === 'installed'" class="mt-2 text-center text-sm leading-relaxed text-slate-500">
                Апп суусан байна. Систем аппаар нээгдэнэ — товшилт шаардлагагүй.
            </p>

            <!-- iPhone + Safari: татах товч байхгүй гэдгийг тодорхой хэлнэ -->
            <div v-else-if="mode === 'ios'" class="mt-3 space-y-3">
                <p class="rounded-xl bg-amber-50 px-3 py-2 text-center text-xs leading-relaxed text-amber-900">
                    iPhone дээр <b>апп татах товч байдаггүй</b>.
                    Доорх алхмуудыг Safari дээр хийнэ.
                </p>

                <ol v-if="showSteps" class="space-y-2.5 text-sm text-slate-700">
                    <li class="flex gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-navy-600 text-xs font-bold text-white">1</span>
                        <span>
                            Дэлгэцийн <b>доод</b> талын
                            <span class="inline-flex items-center gap-0.5 font-semibold text-brand-navy-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 3l-1.4 1.4 6.6 6.6H4v2h13.2l-6.6 6.6L12 21l9-9-9-9z" />
                                </svg>
                                Хуваалцах
                            </span>
                            товчийг дарна.
                        </span>
                    </li>
                    <li class="flex gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-navy-600 text-xs font-bold text-white">2</span>
                        <span>Жагсаалтыг доош гүйлгээд <b>«Нүүр дэлгэцэд нэмэх»</b> (Add to Home Screen) сонгоно.</span>
                    </li>
                    <li class="flex gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-navy-600 text-xs font-bold text-white">3</span>
                        <span>Баруун дээд <b>«Нэмэх»</b> дарж, нүүр дэлгэц дээрх шинэ icon-оор нээнэ.</span>
                    </li>
                </ol>

                <button
                    type="button"
                    class="w-full text-center text-xs font-medium text-brand-navy-600"
                    @click="showSteps = ! showSteps"
                >
                    {{ showSteps ? 'Заавар нуух' : 'Заавар харах' }}
                </button>
            </div>

            <!-- iPhone + Chrome/бусад: Safari руу чиглүүлнэ -->
            <div v-else-if="mode === 'ios-other'" class="mt-3 space-y-3">
                <p class="rounded-xl bg-amber-50 px-3 py-2 text-center text-xs leading-relaxed text-amber-900">
                    Chrome болон бусад апп доторх хөтчөөр <b>апп болгож нэмэх боломжгүй</b>.
                    Зөвхөн <b>Safari</b>-ээр нээнэ үү.
                </p>
                <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                    <li>«Холбоос хуулах» дарна.</li>
                    <li><b>Safari</b> аппыг нээнэ.</li>
                    <li>Хаягийн мөрөнд буулгаад орно.</li>
                    <li>Дараа нь «Нүүр дэлгэцэд нэмэх» хийнэ.</li>
                </ol>
            </div>

            <p v-else class="mt-2 text-center text-sm leading-relaxed text-slate-500">
                Утсанд зориулсан хувилбар нь хурдан, офлайн ажиллах боломжтой.
                Суулгасны дараа систем шууд аппаар нээгдэнэ.
            </p>

            <div class="mt-5 space-y-2">
                <button
                    v-if="mode === 'install'"
                    type="button"
                    class="w-full rounded-xl bg-brand-navy-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-navy-700"
                    @click="install"
                >
                    Апп суулгах
                </button>

                <button
                    v-if="mode === 'ios-other'"
                    type="button"
                    class="w-full rounded-xl bg-brand-navy-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-navy-700"
                    @click="copyLink"
                >
                    {{ copied ? 'Хуулагдлаа ✓' : 'Safari-д нээх холбоос хуулах' }}
                </button>

                <button
                    v-if="mode === 'installed'"
                    type="button"
                    :disabled="busy"
                    class="w-full rounded-xl bg-brand-navy-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-brand-navy-700 disabled:opacity-60"
                    @click="openApp"
                >
                    {{ busy ? 'Нээж байна…' : 'Аппаар нээх' }}
                </button>

                <button
                    v-if="mode !== 'installed' || showFallback"
                    type="button"
                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
                    @click="dismiss"
                >
                    {{ mode === 'ios' || mode === 'ios-other' ? 'Одоо биш — хөтчөөр үргэлжлүүлэх' : 'Хөтчөөр үргэлжлүүлэх' }}
                </button>
            </div>
        </div>
    </div>
</template>
