<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InstallAppButton from '@/Components/InstallAppButton.vue';
import { downloadExtensionLoose } from '@/utils/extensionInstall';

/**
 * Mobile = апп суулгах (+ өргөтгөл татах), Desktop = өргөтгөл суулгах.
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

const downloading = ref(false);

const downloadExtension = async () => {
    if (downloading.value) return;

    downloading.value = true;
    message.value = '';

    try {
        const result = await downloadExtensionLoose();
        message.value = result.method === 'folder'
            ? `«${result.folder}» хавтас үүслээ (${result.count} файл). Доторх install.bat эсвэл СУУЛГАХ.txt-ийн дагуу Load unpacked хийнэ.`
            : `«${result.folder}.zip» татагдлаа. Задаад гарсан хавтсыг Load unpacked-аар суулгана (install.bat ажиллуулж болно).`;
        showHelp.value = true;
    } catch (e) {
        if (e?.name === 'AbortError') {
            return;
        }
        message.value = e?.response?.data?.message || e?.message || 'Өргөтгөл татаж чадсангүй.';
    } finally {
        downloading.value = false;
    }
};
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
                    Өргөтгөл суугаагүй — автомат нэвтрэлт идэвхгүй
                </p>
                <p class="mt-0.5 text-xs text-rose-800/90">
                    Chrome/Edge дээр суулгавал холбосон систем рүү автоматаар нэвтэрнэ.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="ui-btn-primary !py-1.5 text-xs"
                    :disabled="downloading"
                    @click="downloadExtension"
                >
                    {{ downloading ? 'Татаж байна…' : 'Өргөтгөл татах' }}
                </button>
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

    <section v-else-if="! props.notifyOnly" class="space-y-3 sm:space-y-4">
        <div
            v-if="showMissingNotice"
            class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2.5 sm:px-3.5 sm:py-3"
            role="status"
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-rose-900">
                        Өргөтгөл суугаагүй — автомат нэвтрэлт идэвхгүй
                    </p>
                    <p class="mt-0.5 text-xs text-rose-800/90">
                        Chrome/Edge дээр суулгавал холбосон систем рүү автоматаар нэвтэрнэ.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="ui-btn-primary !py-1.5 text-xs"
                        :disabled="downloading"
                        @click="downloadExtension"
                    >
                        {{ downloading ? 'Татаж байна…' : 'Өргөтгөл татах' }}
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

        <div class="px-0.5">
            <h3 class="text-base font-semibold text-slate-800">Апп ба өргөтгөл</h3>
            <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">
                Гар утас — апп. Компьютер — өргөтгөл. Өргөтгөлийг утснаас ч татаж болно.
            </p>
        </div>

        <div v-if="message" class="rounded-xl bg-slate-100/80 px-3 py-2 text-xs text-slate-600">
            {{ message }}
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:gap-4">
            <!-- ── Mobile ── -->
            <div
                class="overflow-hidden rounded-2xl border"
                :class="isMobileDevice
                    ? 'border-brand-navy-300 bg-brand-navy-50/50 ring-1 ring-brand-navy-200/80'
                    : 'border-slate-200 bg-white'"
            >
                <div class="flex items-center justify-between gap-2 border-b border-slate-200/80 px-3 py-2.5 sm:px-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-brand-navy-800 sm:text-sm">
                        Mobile
                    </h4>
                    <span class="rounded-md bg-brand-navy-100 px-2 py-0.5 text-[10px] font-semibold text-brand-navy-700">
                        Апп суулгах
                    </span>
                </div>

                <div class="space-y-2.5 p-3 sm:space-y-3 sm:p-4">
                    <div
                        class="rounded-xl border bg-white p-3"
                        :class="appInstalled ? 'border-emerald-200' : 'border-slate-200'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">Утасны апп (PWA)</p>
                            <span
                                class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                :class="appInstalled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ appInstalled ? 'суусан' : 'хөтөч' }}
                            </span>
                        </div>
                        <p v-if="appInstalled" class="mt-1 text-xs text-slate-500">
                            Апп горимоор ажиллаж байна.
                        </p>
                        <div class="mt-2">
                            <InstallAppButton />
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" class="ui-btn-ghost !w-full !py-2 text-xs" @click="clearAppData">
                                Кэш цэвэрлэх
                            </button>
                            <button type="button" class="ui-btn-ghost !w-full !py-2 text-xs" @click="showAppHelp = ! showAppHelp">
                                {{ showAppHelp ? 'Хаах' : 'Устгах заавар' }}
                            </button>
                        </div>
                        <p v-if="showAppHelp" class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-xs text-slate-600">
                            {{ appHelp }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border bg-white p-3"
                        :class="extensionReady ? 'border-emerald-200' : 'border-rose-200'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">Өргөтгөл татах</p>
                            <span
                                class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                :class="extensionReady ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                            >
                                {{ extensionReady ? 'суусан' : 'суугаагүй' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">
                            Хавтас сонгоод manage-dornogovi-extension шууд бичнэ (ZIP биш).
                        </p>
                        <button
                            type="button"
                            class="mt-2 !w-full !py-2 text-xs sm:!w-auto"
                            :class="extensionReady ? 'ui-btn-ghost' : 'ui-btn-primary'"
                            :disabled="downloading"
                            @click="downloadExtension"
                        >
                            {{ downloading ? 'Татаж байна…' : (extensionReady ? 'Дахин татах' : 'Өргөтгөл татах') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Desktop ── -->
            <div
                class="overflow-hidden rounded-2xl border"
                :class="! isMobileDevice
                    ? 'border-brand-navy-300 bg-brand-navy-50/50 ring-1 ring-brand-navy-200/80'
                    : 'border-slate-200 bg-white'"
            >
                <div class="flex items-center justify-between gap-2 border-b border-slate-200/80 px-3 py-2.5 sm:px-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-brand-navy-800 sm:text-sm">
                        Desktop
                    </h4>
                    <span class="rounded-md bg-brand-navy-100 px-2 py-0.5 text-[10px] font-semibold text-brand-navy-700">
                        Өргөтгөл суулгах
                    </span>
                </div>

                <div class="p-3 sm:p-4">
                    <div
                        class="rounded-xl border bg-white p-3"
                        :class="extensionReady
                            ? 'border-emerald-200'
                            : 'border-rose-200'"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">Автомат нэвтрэлтийн өргөтгөл</p>
                            <span
                                class="shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                :class="extensionReady ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                            >
                                {{ extensionReady ? 'идэвхтэй' : 'суугаагүй' }}
                            </span>
                        </div>
                        <p v-if="extensionReady" class="mt-1 text-xs text-slate-500">
                            Холбосон системд нэр, нууц үг автоматаар бөглөгдөнө.
                        </p>

                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                            <button
                                type="button"
                                class="!w-full !py-2 text-xs sm:!w-auto sm:!py-1.5"
                                :class="extensionReady ? 'ui-btn-ghost' : 'ui-btn-primary'"
                                :disabled="downloading"
                                @click="downloadExtension"
                            >
                                {{ downloading ? 'Татаж байна…' : (extensionReady ? 'Дахин татах' : 'Өргөтгөл татах') }}
                            </button>
                            <div class="grid grid-cols-2 gap-2 sm:contents">
                                <button
                                    v-if="extensionReady"
                                    type="button"
                                    class="ui-btn-danger !w-full !py-2 text-xs sm:!w-auto sm:!py-1.5"
                                    @click="removeExtension"
                                >
                                    Устгах
                                </button>
                                <button
                                    v-if="canVerifyExtension"
                                    type="button"
                                    class="ui-btn-ghost !w-full !py-2 text-xs sm:!w-auto sm:!py-1.5"
                                    @click="verifyExtension"
                                >
                                    Төлөв шалгах
                                </button>
                                <button
                                    type="button"
                                    class="ui-btn-ghost !w-full !py-2 text-xs sm:!w-auto sm:!py-1.5"
                                    :class="{ 'col-span-2': ! canVerifyExtension && ! extensionReady }"
                                    @click="showHelp = ! showHelp"
                                >
                                    {{ showHelp ? 'Хаах' : 'Заавар' }}
                                </button>
                            </div>
                        </div>

                        <ol v-if="showHelp" class="mt-3 list-decimal space-y-1.5 rounded-lg bg-slate-50 px-3 py-2.5 pl-5 text-xs leading-relaxed text-slate-600">
                            <li>«Өргөтгөл татах» дарж хадгалах <b>хавтсыг сонгоно</b> (Downloads гэх мэт).</li>
                            <li>Дотор нь <b>manage-dornogovi-extension</b> хавтас автоматаар үүснэ.</li>
                            <li>Хавтас доторх <b>install.bat</b> ажиллуулна (эсвэл chrome://extensions нээнэ).</li>
                            <li><b>Developer mode</b> асаагаад <b>Load unpacked</b> → тэр хавтсыг сонгоно.</li>
                            <li v-if="extensionReady">Устгах: энэ товч эсвэл chrome://extensions → Remove.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
