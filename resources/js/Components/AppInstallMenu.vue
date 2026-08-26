<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import InstallAppButton from '@/Components/InstallAppButton.vue';
import AppExtensionManager from '@/Components/AppExtensionManager.vue';

/**
 * Толгой хэсгийн «Апп ба өргөтгөл» цэс.
 *
 * Хажуугийн «Утсандаа апп болгож суулгах» товч болон самбар дээрх
 * «Апп ба өргөтгөл» хэсгийг нэгтгэж, Админ цэсний хажууд icon болгов.
 */
const open = ref(false);
const root = ref(null);

const appInstalled = ref(false);
const extensionReady = ref(false);
const extensionApplicable = ref(false);

const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const isMobile = () => /android|iphone|ipad|ipod|mobile/i.test(window.navigator.userAgent);

const refreshState = () => {
    appInstalled.value = isStandalone();
    extensionReady.value = document.documentElement.dataset.mdExtension === '1';
    // Өргөтгөл нь зөвхөн компьютерийн Chrome/Edge дээр хэрэгтэй.
    extensionApplicable.value = ! isMobile() && ! isStandalone() && !! window.chrome?.runtime;
};

// Анхаарал татах цэг — суулгах зүйл үлдсэн бол.
const needsAttention = computed(() => (! appInstalled.value && isMobile())
    || (extensionApplicable.value && ! extensionReady.value));

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
    refreshState();
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('focus', refreshState);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('focus', refreshState);
});

const toggle = () => {
    refreshState();
    open.value = ! open.value;
};
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-brand-navy-700 shadow-sm transition hover:border-brand-navy-200 hover:bg-brand-navy-50"
            :class="open ? 'border-brand-navy-300 bg-brand-navy-50' : ''"
            title="Апп ба өргөтгөл"
            aria-label="Апп ба өргөтгөл"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span
                v-if="needsAttention"
                class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-brand-orange-500 ring-2 ring-white"
            ></span>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 z-40 mt-2 w-[min(92vw,26rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                @click.stop
            >
                <div class="max-h-[75vh] overflow-y-auto p-1">
                    <InstallAppButton class="pt-3" />
                    <AppExtensionManager class="!shadow-none !ring-0" />
                </div>
            </div>
        </Transition>
    </div>
</template>
