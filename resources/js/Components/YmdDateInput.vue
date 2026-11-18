<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { registerSheetEditorFlusher } from '@/composables/sheetEditorFlush';
import {
    daysInMonth,
    formatYmdDisplay,
    joinYmdParts,
    parseYmd,
    splitYmdParts,
} from '@/utils/ymdDate';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    disabled: { type: Boolean, default: false },
    /** Хүснэгтийн нүдэнд жижиг харагдац. */
    compact: { type: Boolean, default: false },
    placeholder: { type: String, default: 'ӨӨӨӨ.СС.ӨӨ' },
});

const emit = defineEmits(['update:modelValue', 'commit', 'blur', 'interact']);

const year = ref('');
const month = ref('');
const day = ref('');
const rootRef = ref(null);

const syncFromModel = () => {
    const parts = splitYmdParts(props.modelValue);
    year.value = parts.year;
    month.value = parts.month;
    day.value = parts.day;
};

watch(() => props.modelValue, syncFromModel, { immediate: true });

const currentYear = new Date().getFullYear();

const years = computed(() => {
    const list = [];
    for (let y = currentYear + 2; y >= 1950; y -= 1) {
        list.push(String(y));
    }

    return list;
});

const months = computed(() => (
    Array.from({ length: 12 }, (_, i) => String(i + 1).padStart(2, '0'))
));

const maxDay = computed(() => {
    const y = Number(year.value);
    const m = Number(month.value);

    return daysInMonth(y || currentYear, m || 1);
});

const days = computed(() => (
    Array.from({ length: maxDay.value }, (_, i) => String(i + 1).padStart(2, '0'))
));

watch([year, month], () => {
    if (day.value && Number(day.value) > maxDay.value) {
        day.value = String(maxDay.value).padStart(2, '0');
        pushValue(false);
    }
});

const pushValue = (commit = false) => {
    const next = joinYmdParts(year.value, month.value, day.value);
    // Хоосон эсвэл бүрэн — хэсэгчилсэн утгыг бүү илгээ.
    const empty = ! year.value && ! month.value && ! day.value;

    if (! empty && ! next) {
        return;
    }

    const value = empty ? '' : next;
    emit('update:modelValue', value);

    if (commit) {
        emit('commit', value);
    }
};

const onPartChange = () => {
    pushValue(false);

    if (year.value && month.value && day.value && joinYmdParts(year.value, month.value, day.value)) {
        pushValue(true);
    }
};

const onClear = () => {
    year.value = '';
    month.value = '';
    day.value = '';
    emit('update:modelValue', '');
    emit('commit', '');
};

const flushPending = () => {
    const nextValue = joinYmdParts(year.value, month.value, day.value);
    const empty = ! year.value && ! month.value && ! day.value;

    if (! empty && ! nextValue) {
        syncFromModel();

        return;
    }

    pushValue(true);
};

const onRootBlur = (event) => {
    const next = event.relatedTarget;
    if (next instanceof Node && rootRef.value?.contains(next)) {
        return;
    }

    // Бүрэн бус бол өмнөх утга руу буцаана.
    const nextValue = joinYmdParts(year.value, month.value, day.value);
    const empty = ! year.value && ! month.value && ! day.value;

    if (! empty && ! nextValue) {
        syncFromModel();
        emit('blur');

        return;
    }

    pushValue(true);
    emit('blur');
};

let unregisterFlusher = null;

onMounted(() => {
    unregisterFlusher = registerSheetEditorFlusher(flushPending);
});

onBeforeUnmount(() => {
    unregisterFlusher?.();
    unregisterFlusher = null;
});

const onInteract = () => {
    emit('interact');
};

const displayHint = computed(() => {
    const shown = formatYmdDisplay(props.modelValue);

    return shown || props.placeholder;
});
</script>

<template>
    <div
        ref="rootRef"
        class="ymd-date"
        :class="compact ? 'ymd-date--compact' : 'ymd-date--form'"
        :title="`Огноо: ${displayHint} (он → сар → өдөр)`"
        @mousedown="onInteract"
        @focusout="onRootBlur"
    >
        <select
            v-model="year"
            class="ymd-date__select"
            :disabled="disabled"
            aria-label="Он"
            @change="onPartChange"
        >
            <option value="">Он</option>
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <span class="ymd-date__sep" aria-hidden="true">.</span>
        <select
            v-model="month"
            class="ymd-date__select"
            :disabled="disabled"
            aria-label="Сар"
            @change="onPartChange"
        >
            <option value="">Сар</option>
            <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
        </select>
        <span class="ymd-date__sep" aria-hidden="true">.</span>
        <select
            v-model="day"
            class="ymd-date__select"
            :disabled="disabled"
            aria-label="Өдөр"
            @change="onPartChange"
        >
            <option value="">Өдөр</option>
            <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
        </select>
        <button
            v-if="parseYmd(modelValue) || year || month || day"
            type="button"
            class="ymd-date__clear"
            :disabled="disabled"
            title="Цэвэрлэх"
            aria-label="Цэвэрлэх"
            @mousedown.prevent="onInteract"
            @click="onClear"
        >
            ✕
        </button>
    </div>
</template>

<style scoped>
.ymd-date {
    display: inline-flex;
    max-width: 100%;
    align-items: center;
    gap: 0.15rem;
}

.ymd-date__sep {
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
}

.ymd-date__select {
    min-width: 0;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    background: #fff;
    color: #0f172a;
    outline: none;
}

.ymd-date__select:focus {
    border-color: #1e3a5f;
    box-shadow: 0 0 0 2px rgb(30 58 95 / 0.15);
}

.ymd-date--compact .ymd-date__select {
    padding: 0.15rem 0.2rem;
    font-size: 0.7rem;
    line-height: 1.2;
}

.ymd-date--compact .ymd-date__select:nth-child(1) {
    width: 3.4rem;
}

.ymd-date--compact .ymd-date__select:nth-child(3),
.ymd-date--compact .ymd-date__select:nth-child(5) {
    width: 2.6rem;
}

.ymd-date--form {
    gap: 0.35rem;
}

.ymd-date--form .ymd-date__select {
    padding: 0.45rem 0.55rem;
    font-size: 0.875rem;
}

.ymd-date--form .ymd-date__select:nth-child(1) {
    width: 5.5rem;
}

.ymd-date--form .ymd-date__select:nth-child(3),
.ymd-date--form .ymd-date__select:nth-child(5) {
    width: 4.25rem;
}

.ymd-date__clear {
    margin-left: 0.15rem;
    display: inline-flex;
    height: 1.25rem;
    width: 1.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    color: #94a3b8;
    font-size: 0.65rem;
    line-height: 1;
}

.ymd-date__clear:hover {
    background: #fee2e2;
    color: #e11d48;
}

:global(.dark) .ymd-date__select {
    border-color: #475569;
    background: #0f172a;
    color: #e2e8f0;
}

:global(.dark) .ymd-date__sep {
    color: #64748b;
}
</style>
