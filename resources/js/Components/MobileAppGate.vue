<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Гар утаснаас нэвтэрсэн үед аппаар ажиллуулах хаалга.
 *
 *  • Апп горимд (standalone) байвал юу ч харагдахгүй.
 *  • Апп суусан атлаа хөтчөөр орсон бол — асуухгүйгээр апп руу шилжүүлнэ.
 *  • Суугаагүй бол — суулгах цонхыг шууд гаргана (iPhone дээр заавар).
 *  • Компьютер дээр огт харагдахгүй, шууд системд орно.
 */
const APP_URL = '/dept-dashboard';

const visible = ref(false);
const mode = ref('install'); // install | installed | ios
const deferredPrompt = ref(null);
const showFallback = ref(false);
const busy = ref(false);

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

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

// Суусан апп руу шилжинэ. Android дээр холбоосыг апп өөрөө барьж авдаг.
const openApp = () => {
    busy.value = true;
    window.location.href = APP_URL;

    // Хэрэв 4 секундэд апп нээгдээгүй бол хөтчөөр үргэлжлүүлэх сонголт гаргана.
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

const title = computed(() => (mode.value === 'installed'
    ? 'Аппаараа нээж байна…'
    : 'Утсандаа апп болгож суулгана уу'));

onMounted(async () => {
    if (! isMobile() || isStandalone()) {
        return;
    }

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);

    // 1) Апп аль хэдийн суусан эсэхийг шалгана (Android Chrome).
    try {
        const related = await window.navigator.getInstalledRelatedApps?.();

        if (related?.length) {
            mode.value = 'installed';
            visible.value = true;
            openApp();

            return;
        }
    } catch {
        // Дэмждэггүй хөтөч — суулгах урилга руу шилжинэ.
    }

    if (sessionStorage.getItem('mdAppGateSkipped') === '1') {
        return;
    }

    // 2) iPhone дээр beforeinstallprompt байхгүй тул зааврыг шууд харуулна.
    if (isIos()) {
        mode.value = 'ios';
        visible.value = true;

        return;
    }

    // 3) Android дээр урилга ирэхийг хүлээнэ (onPrompt цонхыг нээнэ).
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onPrompt);
    window.removeEventListener('appinstalled', onInstalled);
});
</script>

<template>
    <div v-if="visible" class="fixed inset-0 z-[100] flex items-end justify-center bg-brand-navy-950/70 p-4 backdrop-blur-sm sm:items-center">
        <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-navy-50 text-brand-navy-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h2 class="mt-4 text-center text-base font-bold text-brand-navy-800">{{ title }}</h2>

            <p v-if="mode === 'installed'" class="mt-2 text-center text-sm leading-relaxed text-slate-500">
                Апп суусан байна. Систем аппаар нээгдэнэ — товшилт шаардлагагүй.
            </p>
            <p v-else-if="mode === 'ios'" class="mt-2 text-center text-sm leading-relaxed text-slate-500">
                Safari-гийн доод талын <b>Хуваалцах</b> (⬆) товчийг дараад
                <b>«Нүүр дэлгэцэд нэмэх»</b> сонголтыг сонгоно уу.
            </p>
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
                    class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-500 transition hover:bg-slate-50"
                    @click="dismiss"
                >
                    Хөтчөөр үргэлжлүүлэх
                </button>
            </div>
        </div>
    </div>
</template>
