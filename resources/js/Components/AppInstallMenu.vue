<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppExtensionManager from '@/Components/AppExtensionManager.vue';
import { isMobileDevice } from '@/utils/mobileClient';

/**
 * Толгой icon — өргөтгөл суугаагүй бол улаан. Утсан дээр харагдахгүй.
 */
const open = ref(false);
const root = ref(null);
const onPhone = ref(false);
const extensionReady = ref(false);
const extensionChecked = ref(false);

const FALLBACK_ID = 'hoiannpahebnneonhkjianfpmjfhpdmm';

const verifyExtension = () => {
    const id = document.documentElement.dataset.mdExtensionId || FALLBACK_ID;

    if (! window.chrome?.runtime?.sendMessage) {
        extensionReady.value = document.documentElement.dataset.mdExtension === '1';
        extensionChecked.value = true;

        return;
    }

    try {
        chrome.runtime.sendMessage(id, { type: 'status' }, (response) => {
            const alive = ! chrome.runtime.lastError && !! response;
            extensionReady.value = alive;
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

/** Өргөтгөл суугаагүй үед icon улаан. */
const extensionMissing = computed(() => extensionChecked.value && ! extensionReady.value);

const onOutside = (event) => {
    if (open.value && root.value && ! root.value.contains(event.target)) {
        open.value = false;
    }
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

onMounted(() => {
    onPhone.value = isMobileDevice();
    if (onPhone.value) {
        return;
    }

    extensionReady.value = document.documentElement.dataset.mdExtension === '1';
    verifyExtension();
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('focus', verifyExtension);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('focus', verifyExtension);
});

const toggle = () => {
    verifyExtension();
    open.value = ! open.value;
};
</script>

<template>
    <div v-if="! onPhone" ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition"
            :class="extensionMissing
                ? 'border-rose-400 bg-rose-50 text-rose-600 hover:border-rose-500 hover:bg-rose-100'
                : open
                    ? 'border-brand-navy-300 bg-brand-navy-50 text-brand-navy-700'
                    : 'border-slate-200 bg-white text-brand-navy-700 hover:border-brand-navy-200 hover:bg-brand-navy-50'"
            :title="extensionMissing ? 'Өргөтгөл суугаагүй — дарж суулгана уу' : 'Өргөтгөл'"
            :aria-label="extensionMissing ? 'Өргөтгөл суугаагүй' : 'Өргөтгөл'"
            @click.stop="toggle"
        >
            <!-- Туслах програм / өргөтгөл (puzzle) -->
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"
                />
            </svg>
            <span
                v-if="extensionMissing"
                class="absolute -right-0.5 -top-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-rose-600 text-[8px] font-bold text-white ring-2 ring-white"
            >
                !
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 z-50 mt-2 w-[min(92vw,20rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800">Өргөтгөл</p>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Хаах"
                        @click="open = false"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="max-h-[min(70vh,24rem)] overflow-y-auto overscroll-contain p-2.5">
                    <AppExtensionManager compact />
                </div>
            </div>
        </Transition>
    </div>
</template>
