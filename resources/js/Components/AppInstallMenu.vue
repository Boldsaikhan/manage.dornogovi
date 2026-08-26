<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppExtensionManager from '@/Components/AppExtensionManager.vue';

/**
 * Толгой icon — өргөтгөл суугаагүй бол улаан.
 */
const open = ref(false);
const root = ref(null);
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
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition"
            :class="extensionMissing
                ? 'border-rose-400 bg-rose-50 text-rose-600 hover:border-rose-500 hover:bg-rose-100'
                : open
                    ? 'border-brand-navy-300 bg-brand-navy-50 text-brand-navy-700'
                    : 'border-slate-200 bg-white text-brand-navy-700 hover:border-brand-navy-200 hover:bg-brand-navy-50'"
            :title="extensionMissing ? 'Өргөтгөл суугаагүй — дарж суулгана уу' : 'Апп ба өргөтгөл'"
            :aria-label="extensionMissing ? 'Өргөтгөл суугаагүй' : 'Апп ба өргөтгөл'"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
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
                class="absolute right-0 z-40 mt-2 w-[min(94vw,36rem)] overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl"
                @click.stop
            >
                <div class="max-h-[80vh] overflow-y-auto p-2">
                    <AppExtensionManager />
                </div>
            </div>
        </Transition>
    </div>
</template>
