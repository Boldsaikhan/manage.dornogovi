<script setup>
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';

const props = defineProps({
    maxHeight: { type: String, default: 'min(72vh, calc(100dvh - 11rem))' },
    /** Контент өөрчлөгдөхөд дахин хэмжих (ж: хүснэгтийн өргөн) */
    measureKey: { type: [String, Number], default: null },
});

const bodyRef = ref(null);
const hBarRef = ref(null);
const trackWidth = ref(0);
const showHBar = ref(false);
let syncing = false;
let resizeObserver = null;

const measure = () => {
    const el = bodyRef.value;
    if (! el) {
        return;
    }

    const inner = el.firstElementChild;
    const innerWidth = inner instanceof HTMLElement
        ? Math.max(inner.scrollWidth, inner.offsetWidth, inner.clientWidth)
        : 0;

    trackWidth.value = Math.max(el.scrollWidth, innerWidth);
    showHBar.value = trackWidth.value > el.clientWidth + 1;
};

const syncFromBody = () => {
    if (syncing || ! hBarRef.value || ! bodyRef.value) {
        return;
    }

    syncing = true;
    hBarRef.value.scrollLeft = bodyRef.value.scrollLeft;
    syncing = false;
};

const syncFromBar = () => {
    if (syncing || ! hBarRef.value || ! bodyRef.value) {
        return;
    }

    syncing = true;
    bodyRef.value.scrollLeft = hBarRef.value.scrollLeft;
    syncing = false;
};

const bindObservers = () => {
    resizeObserver?.disconnect();
    resizeObserver = new ResizeObserver(() => measure());

    if (! bodyRef.value) {
        return;
    }

    resizeObserver.observe(bodyRef.value);

    const inner = bodyRef.value.firstElementChild;
    if (inner instanceof Element) {
        resizeObserver.observe(inner);
    }
};

onMounted(async () => {
    await nextTick();
    measure();
    bindObservers();
    window.addEventListener('resize', measure);
});

onUpdated(() => {
    nextTick(() => {
        bindObservers();
        measure();
    });
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', measure);
});

watch(() => props.maxHeight, () => nextTick(measure));
watch(() => props.measureKey, () => nextTick(measure));
</script>

<template>
    <div
        class="ui-card ui-table-scroll flex w-full flex-col overflow-hidden"
        :style="{ height: maxHeight, maxHeight }"
    >
        <div
            ref="bodyRef"
            class="ui-table-scroll-body min-h-0 flex-1 overflow-auto overscroll-contain"
            @scroll="syncFromBody"
        >
            <slot />
        </div>
        <div
            v-show="showHBar"
            ref="hBarRef"
            class="ui-table-hscroll shrink-0 border-t border-slate-200 bg-white"
            aria-hidden="true"
            @scroll="syncFromBar"
        >
            <div class="ui-table-hscroll-track" :style="{ width: `${trackWidth}px` }" />
        </div>
    </div>
</template>
