<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PushSubscribe from '@/Components/PushSubscribe.vue';

/**
 * Утасны апп (PWA) болон нэвтрэлтийн өргөтгөлийг суулгах / устгах хэсэг.
 */
const props = defineProps({
    /** true бол зөвхөн өргөтгөл суугаагүй үеийн мэдэгдэл (бүтэн удирдлага биш). */
    notifyOnly: { type: Boolean, default: false },
});

const extensionReady = ref(false);
const extensionChecked = ref(false);
const extensionId = ref('');
const appInstalled = ref(false);
const message = ref('');
const showHelp = ref(false);
const showAppHelp = ref(false);
const bannerDismissed = ref(false);

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

/** Chrome/Edge дээр өргөтгөл суулгах боломжтой эсэх (утас/PWA-д шаардлагагүй). */
const extensionApplicable = ref(false);

// Манифестын key-ээс хамаарах тогтмол ID — dataset алга бол энэгээр шалгана.
const FALLBACK_ID = 'hoiannpahebnneonhkjianfpmjfhpdmm';
const DISMISS_KEY = 'md_extension_missing_dismissed';

/**
 * Өргөтгөл үнэхээр амьд эсэхийг шалгана. Устгасны дараа хуудсан дээрх
 * data-тэмдэг хэвээрээ үлддэг тул зөвхөн түүнд найдвал «татах» товч дахин гарч ирэхгүй.
 */
const verifyExtension = () => {
    if (! extensionApplicable.value) {
        extensionReady.value = false;
        extensionChecked.value = true;

        return;
    }

    const id = document.documentElement.dataset.mdExtensionId || extensionId.value || FALLBACK_ID;

    if (! window.chrome?.runtime?.sendMessage) {
        extensionReady.value = false;
        extensionChecked.value = true;

        return;
    }

    try {
        chrome.runtime.sendMessage(id, { type: 'status' }, (response) => {
            const alive = ! chrome.runtime.lastError && !! response;

            extensionReady.value = alive;
            extensionId.value = alive ? id : '';
            extensionChecked.value = true;

            if (! alive) {
                // Хуучин тэмдэгийг цэвэрлэнэ — бусад хуудас бас зөв төлөв харна.
                delete document.documentElement.dataset.mdExtension;
                delete document.documentElement.dataset.mdExtensionId;
            }
        });
    } catch {
        extensionReady.value = false;
        extensionChecked.value = true;
    }
};

const showMissingNotice = computed(() => (
    extensionChecked.value
    && extensionApplicable.value
    && ! extensionReady.value
    && ! bannerDismissed.value
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
    const mobile = /Android|iPhone|iPad|iPod/i.test(window.navigator.userAgent);

    extensionApplicable.value = !! window.chrome?.runtime?.sendMessage && ! standalone && ! mobile;

    extensionId.value = document.documentElement.dataset.mdExtensionId ?? '';
    extensionReady.value = document.documentElement.dataset.mdExtension === '1';
    appInstalled.value = standalone;

    try {
        const raw = localStorage.getItem(DISMISS_KEY);
        // Нэг өдөр хаасныг сануулна — дараа нь дахин мэдэгдэнэ.
        if (raw && Date.now() - Number(raw) < 24 * 60 * 60 * 1000) {
            bannerDismissed.value = true;
        }
    } catch {
        // ignore
    }

    verifyExtension();

    // Буцаж ирэхэд (жишээ нь chrome://extensions-ээс) дахин шалгана.
    window.addEventListener('focus', verifyExtension);
});

onBeforeUnmount(() => window.removeEventListener('focus', verifyExtension));

// Өргөтгөл өөрөө өөрийгөө устгана (баталгаажуулах цонх гарна).
const removeExtension = () => {
    const id = extensionId.value || document.documentElement.dataset.mdExtensionId || FALLBACK_ID;

    if (! window.chrome?.runtime?.sendMessage) {
        message.value = 'Өргөтгөлтэй холбогдож чадсангүй. chrome://extensions хуудсаас устгана уу.';

        return;
    }

    chrome.runtime.sendMessage(id, { type: 'uninstall' }, () => {
        message.value = 'Устгах хүсэлт илгээлээ. Гарч ирэх цонхонд баталгаажуулна уу.';

        // Устгасны дараа төлөв өөрчлөгдтөл хэдэн удаа шалгана.
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

// Аппын кэш, офлайн өгөгдлийг цэвэрлэнэ (утаснаас icon-ыг гараар устгана).
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
    <!-- Зөвхөн мэдэгдэл (layout) -->
    <div
        v-if="props.notifyOnly && showMissingNotice"
        class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 shadow-sm"
        role="status"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900">
                    Автомат нэвтрэлтийн өргөтгөл суугаагүй байна
                </p>
                <p class="mt-0.5 text-xs text-amber-800/90">
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
                    class="rounded-lg px-2 py-1 text-xs font-medium text-amber-800/70 hover:bg-amber-100 hover:text-amber-900"
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
            class="rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-3"
            role="status"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-amber-900">
                        Өргөтгөл суугаагүй — автомат нэвтрэлт идэвхгүй
                    </p>
                    <p class="mt-0.5 text-xs text-amber-800/90">
                        Доорх «Өргөтгөл татах» товчоор суулгана уу. Суулгасны дараа «Төлөв шалгах» дарна.
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-xs font-medium text-amber-800/70 hover:text-amber-900"
                    @click="dismissMissingNotice"
                >
                    Хаах
                </button>
            </div>
        </div>

        <div>
            <h3 class="text-base font-semibold text-slate-800">Апп ба өргөтгөл</h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Утсандаа апп болгож суулгах, холбосон системд автоматаар нэвтрэх өргөтгөлийг эндээс удирдана.
            </p>
        </div>

        <div v-if="message" class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
            {{ message }}
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            <!-- Өргөтгөл -->
            <div
                class="rounded-2xl border p-4"
                :class="extensionReady
                    ? 'border-emerald-200 bg-emerald-50/60'
                    : extensionApplicable
                        ? 'border-amber-200 bg-amber-50/40'
                        : 'border-slate-200'"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Автомат нэвтрэлтийн өргөтгөл</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ ! extensionApplicable
                                ? 'Энэ төхөөрөмж дээр өргөтгөл шаардлагагүй (утас/апп).'
                                : extensionReady
                                    ? 'Суусан — систем дээр дарахад нэр, нууц үг автоматаар бөглөгдөнө.'
                                    : 'Суугаагүй — систем дээр дарахад нууц үгээ гараар оруулна.' }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="extensionReady
                            ? 'bg-emerald-100 text-emerald-700'
                            : extensionApplicable
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-slate-100 text-slate-600'"
                    >
                        {{ ! extensionApplicable ? 'шаардлагагүй' : (extensionReady ? 'идэвхтэй' : 'суугаагүй') }}
                    </span>
                </div>

                <div v-if="extensionApplicable" class="mt-3 flex flex-wrap gap-2">
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
                        Өргөтгөлийг устгах
                    </button>
                    <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="verifyExtension">
                        Төлөв шалгах
                    </button>
                    <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="showHelp = ! showHelp">
                        {{ showHelp ? 'Хаах' : 'Заавар' }}
                    </button>
                </div>

                <ol v-if="showHelp && extensionApplicable" class="mt-3 list-decimal space-y-1 pl-5 text-xs leading-relaxed text-slate-600">
                    <li v-if="! extensionReady">ZIP-ийг татаад задлана — <b>manage-dornogovi-extension</b> хавтас үүснэ.</li>
                    <li v-if="! extensionReady"><b>chrome://extensions</b> нээж, <b>Developer mode</b>-ыг асаана.</li>
                    <li v-if="! extensionReady"><b>Load unpacked</b> дарж тэр хавтсыг сонгоод, энэ хуудсыг сэргээнэ.</li>
                    <li v-if="extensionReady">«Өргөтгөлийг устгах» дарж баталгаажуулна.</li>
                    <li v-if="extensionReady">Эсвэл <b>chrome://extensions</b> → Manage Dornogovi → <b>Remove</b>.</li>
                </ol>
            </div>

            <!-- Утасны апп -->
            <div class="rounded-2xl border p-4" :class="appInstalled ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200'">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Утасны апп (PWA)</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ appInstalled
                                ? 'Энэ төхөөрөмж дээр апп горимоор ажиллаж байна.'
                                : 'Хажуугийн цэсний «Утсандаа апп болгож суулгах» товчоор суулгана.' }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="appInstalled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ appInstalled ? 'суусан' : 'хөтчөөр' }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="clearAppData">
                        Аппын кэш цэвэрлэх
                    </button>
                    <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="showAppHelp = ! showAppHelp">
                        {{ showAppHelp ? 'Хаах' : 'Устгах заавар' }}
                    </button>
                </div>

                <p v-if="showAppHelp" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600">
                    {{ appHelp }}
                    <br>
                    Устгасны дараа систем хөтчөөр хэвийн ажиллана — мэдээлэл алдагдахгүй.
                </p>
            </div>

            <PushSubscribe class="lg:col-span-2" />
        </div>
    </section>
</template>
