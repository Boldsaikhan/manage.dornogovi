<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Гар утсанд «Апп суулгах» товч.
 *
 * Chrome/Edge/Android дээр beforeinstallprompt үйлдлээр суулгах цонх нээнэ.
 * iPhone дээр тийм үйлдэл байхгүй тул Safari-гийн зааврыг харуулна.
 */
const deferredPrompt = ref(null);
const installed = ref(false);
const showIosHelp = ref(false);

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

const onPrompt = (event) => {
    event.preventDefault();
    deferredPrompt.value = event;
};

const onInstalled = () => {
    installed.value = true;
    deferredPrompt.value = null;
};

onMounted(() => {
    installed.value = isStandalone();
    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onPrompt);
    window.removeEventListener('appinstalled', onInstalled);
});

// Android/Desktop дээр бэлэн байвал, эсвэл iPhone дээр зааврыг санал болгоно.
const visible = computed(() => ! installed.value && (deferredPrompt.value !== null || isIos()));

const install = async () => {
    if (isIos() && ! deferredPrompt.value) {
        showIosHelp.value = ! showIosHelp.value;

        return;
    }

    const prompt = deferredPrompt.value;
    if (! prompt) return;

    deferredPrompt.value = null;
    prompt.prompt();
    await prompt.userChoice;
};
</script>

<template>
    <div v-if="visible" class="px-3 pb-2">
        <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-xl border border-brand-navy-200 bg-brand-navy-50 px-3 py-2 text-xs font-semibold text-brand-navy-700 transition hover:bg-brand-navy-100"
            @click="install"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Утсандаа апп болгож суулгах
        </button>

        <p v-if="showIosHelp" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-900">
            iPhone дээр апп <b>татаж</b> суулгах товч байхгүй.
            Safari-ийн доод <b>Хуваалцах</b> (□↑) → жагсаалтыг гүйлгээд
            <b>«Нүүр дэлгэцэд нэмэх»</b> → <b>«Нэмэх»</b>.
            Chrome-оор орсон бол эхлээд Safari-ээр нээнэ үү.
        </p>
    </div>
</template>
