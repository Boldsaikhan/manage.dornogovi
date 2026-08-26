<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PushSubscribe from '@/Components/PushSubscribe.vue';
import InstallAppButton from '@/Components/InstallAppButton.vue';

/**
 * Mobile = апп (+ өргөтгөл татах), Desktop = өргөтгөл суулгах.
 */
const props = defineProps({
    /** true бол зөвхөн өргөтгөл суугаагүй үеийн мэдэгдэл. */
    notifyOnly: { type: Boolean, default: false },
});

const extensionReady = ref(false);
const extensionChecked = ref(false);
const extensionId = ref('');
const appInstalled = ref(false);
const isMobileDevice = ref(false);
const canVerifyExtension = ref(false);
const message = ref('');
const showHelp = ref(false);
const showAppHelp = ref(false);
const bannerDismissed = ref(false);

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

const FALLBACK_ID = 'hoiannpahebnneonhkjianfpmjfhpdmm';
const DISMISS_KEY = 'md_extension_missing_dismissed';

const verifyExtension = () => {
    const id = document.documentElement.dataset.mdExtensionId || extensionId.value || FALLBACK_ID;

    if (! window.chrome?.runtime?.sendMessage) {
        extensionReady.value = false;
        extensionChecked.value = true;
        canVerifyExtension.value = false;

        return;
    }

    canVerifyExtension.value = true;

    try {
        chrome.runtime.sendMessage(id, { type: 'status' }, (response) => {
            const alive = ! chrome.runtime.lastError && !! response;

            extensionReady.value = alive;
            extensionId.value = alive ? id : '';
            extensionChecked.value = true;

            if (! alive) {
                delete document.documentElement.dataset.mdExtension;
                delete document.documentElement.dataset.mdExtensionId;
            }
        });
    } catch {
        extensionReady.value = false;
        extensionChecked.value = true;
    }
};

/** Өргөтгөл суугаагүй — анхааруулга (утас/компьютер аль алинд). */
const extensionMissing = computed(() => extensionChecked.value && ! extensionReady.value);

const showMissingNotice = computed(() => (
    extensionMissing.value && ! bannerDismissed.value
));

const dismissMissingNotice = () => {
    bannerDismissed.value = true;
    try {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch {
        // ignore
    }
};

onMounted(() => {
    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    isMobileDevice.value = /Android|iPhone|iPad|iPod|Mobile/i.test(window.navigator.userAgent)
        || window.matchMedia('(max-width: 820px) and (pointer: coarse)').matches;

    extensionId.value = document.documentElement.dataset.mdExtensionId ?? '';
    extensionReady.value = document.documentElement.dataset.mdExtension === '1';
    appInstalled.value = standalone;
    canVerifyExtension.value = !! window.chrome?.runtime?.sendMessage;

    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        if (raw && Date.now() - Number(raw) < 24 * 60 * 60 * 1000) {
            bannerDismissed.value = true;
        }
    } catch {
        // ignore
    }

    verifyExtension();
    window.addEventListener('focus', verifyExtension);
});

onBeforeUnmount(() => window.removeEventListener('focus', verifyExtension));

const removeExtension = () => {
    const id = extensionId.value || document.documentElement.dataset.mdExtensionId || FALLBACK_ID;

    if (! window.chrome?.runtime?.sendMessage) {
        message.value = 'Өргөтгөлтэй холбогдож чадсангүй. chrome://extensions хуудсаас устгана уу.';

        return;
    }

    chrome.runtime.sendMessage(id, { type: 'uninstall' }, () => {
        message.value = 'Устгах хүсэлт илгээлээ. Гарч ирэх цонхонд баталгаажуулна уу.';

        let tries = 0;
        const timer = setInterval(() => {
            verifyExtension();
            tries += 1;

            if (! extensionReady.value || tries > 20) {
                clearInterval(timer);
                if (! extensionReady.value) {
                    message.value = 'Өргөтгөл устгагдлаа. Шаардвал «Өргөтгөл татах» товчоор дахин суулгана уу.';
                }
            }
        }, 1000);
    });
};

const clearAppData = async () => {
    try {
        if ('serviceWorker' in navigator) {
            const regs = await navigator.serviceWorker.getRegistrations();
            await Promise.all(regs.map((r) => r.unregister()));
        }

        if (window.caches) {
            const keys = await caches.keys();
            await Promise.all(keys.map((k) => caches.delete(k)));
        }

        message.value = 'Аппын кэш цэвэрлэгдлээ. Icon-ыг нүүр дэлгэцээсээ устгана уу.';
    } catch {
        message.value = 'Цэвэрлэж чадсангүй. Хөтчийн тохиргооноос сайтын өгөгдлийг устгана уу.';
    }
};

const appHelp = computed(() => (isIos()
    ? 'iPhone: нүүр дэлгэц дээрх icon дээр удаан дараад «Апп устгах» сонгоно.'
    : 'Android: icon дээр удаан дараад «Устгах». Компьютер: chrome://apps → icon дээр баруун товч → «Remove from Chrome».'));
</script>

<template>
    <div
        v-if="props.notifyOnly && showMissingNotice"
        class="mb-4 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 shadow-sm"
        role="status"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-rose-900">
                    Автомат нэвтрэлтийн өргөтгөл суугаагүй байна
                </p>
                <p class="mt-0.5 text-xs text-rose-800/90">
                    Холбосон систем рүү ороход нэр, нууц үгээ гараар оруулах болно. Суулгавал автоматаар бөглөгдөнө.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <a :href="route('extension.download')" class="ui-btn-primary !py-1.5 text-xs">
                    Өргөтгөл татах
                </a>
                <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="verifyExtension">
                    Шалгах
                </button>
                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-xs font-medium text-rose-800/70 hover:bg-rose-100"
                    @click="dismissMissingNotice"
                >
                    Хаах
                </button>
            </div>
        </div>
    </div>

    <section v-else-if="! props.notifyOnly" class="ui-card-pad space-y-4">
        <div
            v-if="showMissingNotice"
            class="rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-3"
            role="status"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-rose-900">
                        Өргөтгөл суугаагүй — автомат нэвтрэлт идэвхгүй
                    </p>
                    <p class="mt-0.5 text-xs text-rose-800/90">
                        Desktop хэсгээс «Өргөтгөл татах» дарж Chrome/Edge дээр суулгана уу.
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-rose-800/70 hover:text-rose-900"
                    @click="dismissMissingNotice"
                >
                    Хаах
                </button>
            </div>
        </div>

        <div>
            <h3 class="text-base font-semibold text-slate-800">Апп ба өргөтгөл</h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Гар утас — апп суулгах. Компьютер — өргөтгөл суулгах. Өргөтгөлийг утснаас ч татаж болно.
            </p>
        </div>

        <div v-if="message" class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
            {{ message }}
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- ── Mobile ── -->
            <div
                class="space-y-3 rounded-2xl border p-4"
                :class="isMobileDevice ? 'border-brand-navy-300 bg-brand-navy-50/40 ring-1 ring-brand-navy-200' : 'border-slate-200 bg-white'"
            >
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-bold uppercase tracking-wide text-brand-navy-800">Mobile</h4>
                    <span class="rounded-full bg-brand-navy-100 px-2 py-0.5 text-[10px] font-semibold text-brand-navy-700">
                        Апп суулгах
                    </span>
                </div>

                <div
                    class="rounded-xl border bg-white p-3"
                    :class="appInstalled ? 'border-emerald-200' : 'border-slate-200'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Утасны апп (PWA)</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ appInstalled
                                    ? 'Апп горимоор ажиллаж байна.'
                                    : 'Доорх товч эсвэл хажуугийн цэсээр нүүр дэлгэцэд нэмнэ.' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="appInstalled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                        >
                            {{ appInstalled ? 'суусан' : 'хөтчөөр' }}
                        </span>
                    </div>
                    <div class="mt-2">
                        <InstallAppButton />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="clearAppData">
                            Аппын кэш цэвэрлэх
                        </button>
                        <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="showAppHelp = ! showAppHelp">
                            {{ showAppHelp ? 'Хаах' : 'Устгах заавар' }}
                        </button>
                    </div>
                    <p v-if="showAppHelp" class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-xs text-slate-600">
                        {{ appHelp }}
                    </p>
                </div>

                <!-- Утсан дээр ч өргөтгөл татах -->
                <div
                    class="rounded-xl border bg-white p-3"
                    :class="extensionReady ? 'border-emerald-200' : 'border-rose-200'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Өргөтгөл татах</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                ZIP татаад компьютер дээрх Chrome/Edge-д суулгана (холбосон системд автомат нэвтрэлт).
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="extensionReady ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                        >
                            {{ extensionReady ? 'суусан' : 'суугаагүй' }}
                        </span>
                    </div>
                    <div class="mt-2">
                        <a
                            :href="route('extension.download')"
                            class="!py-1.5 text-xs"
                            :class="extensionReady ? 'ui-btn-ghost' : 'ui-btn-primary'"
                        >
                            {{ extensionReady ? 'Дахин татах' : 'Өргөтгөл татах' }}
                        </a>
                    </div>
                </div>

                <PushSubscribe />
            </div>

            <!-- ── Desktop ── -->
            <div
                class="space-y-3 rounded-2xl border p-4"
                :class="! isMobileDevice ? 'border-brand-navy-300 bg-brand-navy-50/40 ring-1 ring-brand-navy-200' : 'border-slate-200 bg-white'"
            >
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-bold uppercase tracking-wide text-brand-navy-800">Desktop</h4>
                    <span class="rounded-full bg-brand-navy-100 px-2 py-0.5 text-[10px] font-semibold text-brand-navy-700">
                        Өргөтгөл суулгах
                    </span>
                </div>

                <div
                    class="rounded-xl border bg-white p-3"
                    :class="extensionReady
                        ? 'border-emerald-200 bg-emerald-50/40'
                        : 'border-rose-300 bg-rose-50/50'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Автомат нэвтрэлтийн өргөтгөл</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ extensionReady
                                    ? 'Суусан — холбосон системд нэр, нууц үг автоматаар бөглөгдөнө.'
                                    : 'Суугаагүй — Chrome/Edge дээр суулгана уу.' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="extensionReady ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                        >
                            {{ extensionReady ? 'идэвхтэй' : 'суугаагүй' }}
                        </span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <a
                            :href="route('extension.download')"
                            class="!py-1.5 text-xs"
                            :class="extensionReady ? 'ui-btn-ghost' : 'ui-btn-primary'"
                        >
                            {{ extensionReady ? 'Дахин татах' : 'Өргөтгөл татах' }}
                        </a>
                        <button
                            v-if="extensionReady"
                            type="button"
                            class="ui-btn-danger !py-1.5 text-xs"
                            @click="removeExtension"
                        >
                            Устгах
                        </button>
                        <button
                            v-if="canVerifyExtension"
                            type="button"
                            class="ui-btn-ghost !py-1.5 text-xs"
                            @click="verifyExtension"
                        >
                            Төлөв шалгах
                        </button>
                        <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="showHelp = ! showHelp">
                            {{ showHelp ? 'Хаах' : 'Заавар' }}
                        </button>
                    </div>

                    <ol v-if="showHelp" class="mt-3 list-decimal space-y-1 pl-5 text-xs leading-relaxed text-slate-600">
                        <li>ZIP-ийг татаад задлана — <b>manage-dornogovi-extension</b> хавтас үүснэ.</li>
                        <li><b>chrome://extensions</b> нээж, <b>Developer mode</b>-ыг асаана.</li>
                        <li><b>Load unpacked</b> дарж тэр хавтсыг сонгоод хуудсыг сэргээнэ.</li>
                        <li v-if="extensionReady">Устгах: энэ товч эсвэл chrome://extensions → Remove.</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
</template>
