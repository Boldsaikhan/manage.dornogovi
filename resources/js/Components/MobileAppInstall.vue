<script setup>
import { computed, onMounted, ref } from 'vue';
import {
    canPromptInstall,
    isIos,
    isPwaInstalledHint,
    isStandalonePwa,
    markPwaInstalled,
    promptInstall,
} from '@/utils/pwaClient';
import { isMobileDevice } from '@/utils/mobileClient';

const showInstall = ref(false);
const showOpenInApp = ref(false);
const installing = ref(false);
const installError = ref('');

const isIosDevice = isIos();
const canNativeInstall = ref(false);

onMounted(() => {
    if (! isMobileDevice() || isStandalonePwa()) {
        return;
    }

    if (isPwaInstalledHint()) {
        showOpenInApp.value = true;

        return;
    }

    showInstall.value = true;
    canNativeInstall.value = canPromptInstall();

    // Android: beforeinstallprompt заримдаа хожим ирдэг.
    window.setTimeout(() => {
        canNativeInstall.value = canPromptInstall();
    }, 1200);
});

const iosHint = computed(() => (
    isIosDevice
        ? 'Safari → Дэлгэцийн доод «Хуваалцах» → «Дэлгэцэнд нэмэх»'
        : 'Chrome цэс → «Апп суулгах» / «Дэлгэцэнд нэмэх»'
));

const installApp = async () => {
    installing.value = true;
    installError.value = '';

    try {
        const { outcome } = await promptInstall();

        if (outcome === 'accepted') {
            markPwaInstalled();
            showInstall.value = false;
            showOpenInApp.value = true;

            return;
        }

        if (outcome === 'unavailable' && ! isIosDevice) {
            installError.value = 'Хөтчийн цэснээс «Апп суулгах»-ыг сонгоно уу.';
        }
    } finally {
        installing.value = false;
    }
};

const dismissInstall = () => {
    showInstall.value = false;
};
</script>

<template>
    <div v-if="showOpenInApp" class="mb-4 rounded-2xl border border-brand-navy-200 bg-brand-navy-50 px-4 py-4 text-center">
        <p class="text-sm font-semibold text-brand-navy-800">manage апп суулгасан байна</p>
        <p class="mt-1 text-xs leading-relaxed text-brand-navy-700/80">
            Дэлгэцийн <strong>manage</strong> дүрсийг дарж апп-аар нээнэ үү. Хөтчөөр орвол биометрик бүрэн ажиллахгүй байж болно.
        </p>
    </div>

    <div
        v-else-if="showInstall"
        class="mb-4 rounded-2xl border border-brand-orange-200 bg-gradient-to-br from-brand-orange-50 to-white px-4 py-4"
    >
        <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-navy-600 text-white shadow-md">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3z" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-brand-navy-900">manage апп суулгах</p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-600">
                    Утсан дээр апп болгон суулгаад хуруу / царайгаар хурдан нэвтэрнэ.
                </p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-white/80 hover:text-slate-600"
                aria-label="Хаах"
                @click="dismissInstall"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <button
            v-if="canNativeInstall"
            type="button"
            class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-navy-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md hover:bg-brand-navy-700 disabled:opacity-60"
            :disabled="installing"
            @click="installApp"
        >
            {{ installing ? 'Суулгаж байна…' : 'Апп суулгах' }}
        </button>

        <p v-else class="mt-3 rounded-xl bg-white/80 px-3 py-2 text-xs leading-relaxed text-slate-600">
            {{ iosHint }}
        </p>

        <p v-if="installError" class="mt-2 text-xs text-red-600">{{ installError }}</p>
    </div>
</template>
