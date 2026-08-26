<script setup>
import { computed, onMounted, ref } from 'vue';

/**
 * Утасны апп (PWA) болон нэвтрэлтийн өргөтгөлийг суулгах / устгах хэсэг.
 */
const extensionReady = ref(false);
const extensionId = ref('');
const appInstalled = ref(false);
const message = ref('');
const showHelp = ref(false);
const showAppHelp = ref(false);

const isIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent);

onMounted(() => {
    extensionReady.value = document.documentElement.dataset.mdExtension === '1';
    extensionId.value = document.documentElement.dataset.mdExtensionId ?? '';
    appInstalled.value = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
});

// Өргөтгөл өөрөө өөрийгөө устгана (баталгаажуулах цонх гарна).
const removeExtension = () => {
    if (! extensionId.value || ! window.chrome?.runtime?.sendMessage) {
        message.value = 'Өргөтгөлтэй холбогдож чадсангүй. chrome://extensions хуудсаас устгана уу.';

        return;
    }

    chrome.runtime.sendMessage(extensionId.value, { type: 'uninstall' }, () => {
        message.value = 'Устгах хүсэлт илгээлээ. Гарч ирэх цонхонд баталгаажуулна уу.';
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
    <section class="ui-card-pad space-y-4">
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
            <div class="rounded-2xl border p-4" :class="extensionReady ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200'">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Автомат нэвтрэлтийн өргөтгөл</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ extensionReady
                                ? 'Суусан — систем дээр дарахад нэр, нууц үг автоматаар бөглөгдөнө.'
                                : 'Суугаагүй — систем дээр дарахад нууц үгээ гараар оруулна.' }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="extensionReady ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ extensionReady ? 'идэвхтэй' : 'суугаагүй' }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a v-if="! extensionReady" :href="route('extension.download')" class="ui-btn-primary !py-1.5 text-xs">
                        Өргөтгөл татах
                    </a>
                    <button
                        v-else
                        type="button"
                        class="ui-btn-danger !py-1.5 text-xs"
                        @click="removeExtension"
                    >
                        Өргөтгөлийг устгах
                    </button>
                    <button type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="showHelp = ! showHelp">
                        {{ showHelp ? 'Хаах' : 'Заавар' }}
                    </button>
                </div>

                <ol v-if="showHelp" class="mt-3 list-decimal space-y-1 pl-5 text-xs leading-relaxed text-slate-600">
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
        </div>
    </section>
</template>
