<script setup>
import { nextTick, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';

const props = defineProps({
    maxHeight: { type: String, default: 'min(72vh, calc(100dvh - 11rem))' },
    /** Үлдсэн дэлгэцийг дүүргэнэ — эх үүсвэр flex багана */
    fill: { type: Boolean, default: false },
    /** Контент өөрчлөгдөхөд дахин хэмжих (ж: хүснэгтийн өргөн) */
    measureKey: { type: [String, Number], default: null },
});

const emit = defineEmits(['near-bottom']);

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

    const expected = Number(props.measureKey) || 0;
    trackWidth.value = Math.max(el.scrollWidth, innerWidth, expected);
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

const onBodyScroll = () => {
    syncFromBody();

    const el = bodyRef.value;
    if (! el) {
        return;
    }

    const remaining = el.scrollHeight - el.scrollTop - el.clientHeight;
    if (remaining < 160) {
        emit('near-bottom');
    }
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
        if (inner.firstElementChild instanceof Element) {
            resizeObserver.observe(inner.firstElementChild);
        }
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
watch(() => props.fill, () => nextTick(measure));
</script>

<template>
    <div
        class="ui-card ui-table-scroll flex w-full min-h-0 flex-col overflow-hidden"
        :class="fill ? 'min-h-0 flex-1 basis-0' : ''"
        :style="fill ? undefined : { height: maxHeight, maxHeight }"
    >
        <div
            ref="bodyRef"
            class="ui-table-scroll-body min-h-0 flex-1 overflow-auto overscroll-contain"
            @scroll="onBodyScroll"
        >
            <slot />
        </div>
        <div
            v-show="showHBar"
            ref="hBarRef"
            class="ui-table-hscroll shrink-0 border-t border-slate-200 bg-white shadow-[0_-2px_8px_rgba(15,23,42,0.06)]"
            aria-hidden="true"
            @scroll="syncFromBar"
        >
            <div class="ui-table-hscroll-track" :style="{ width: `${trackWidth}px` }" />
        </div>
    </div>
</template>
