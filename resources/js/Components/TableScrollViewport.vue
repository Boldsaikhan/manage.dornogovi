<script setup>
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';

const props = defineProps({
    maxHeight: { type: String, default: 'min(70vh, calc(100dvh - 12rem))' },
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

    trackWidth.value = el.scrollWidth;
    showHBar.value = el.scrollWidth > el.clientWidth + 1;
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

onMounted(async () => {
    await nextTick();
    measure();

    resizeObserver = new ResizeObserver(() => measure());
    if (bodyRef.value) {
        resizeObserver.observe(bodyRef.value);
        if (bodyRef.value.firstElementChild) {
            resizeObserver.observe(bodyRef.value.firstElementChild);
        }
    }

    window.addEventListener('resize', measure);
});

onUpdated(() => {
    nextTick(measure);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', measure);
});

watch(() => props.maxHeight, () => nextTick(measure));
</script>

<template>
    <div
        class="ui-card ui-table-scroll flex w-full flex-col overflow-hidden"
        :style="{ maxHeight }"
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
            <div :style="{ width: `${trackWidth}px`, height: '1px' }" />
        </div>
    </div>
</template>
