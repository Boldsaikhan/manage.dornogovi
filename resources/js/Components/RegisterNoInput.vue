<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { registerSheetEditorFlusher } from '@/composables/sheetEditorFlush';
import {
    joinRegisterParts,
    normalizeRegisterDigits,
    normalizeRegisterLetters,
    splitRegisterParts,
} from '@/utils/mongolianRegister';

const props = defineProps({
    modelValue: { type: String, default: '' },
    editable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const letters = ref('');
const digits = ref('');
const letterRef = ref(null);
const digitRef = ref(null);
let blurTimer = null;
let editing = false;

const syncFromModel = () => {
    // Бичиж байхад modelValue → local sync хийвэл тоо/үсэг устана.
    if (editing) {
        return;
    }

    const parts = splitRegisterParts(props.modelValue);
    letters.value = parts.letters;
    digits.value = parts.digits;
};

watch(() => props.modelValue, syncFromModel, { immediate: true });

const combined = () => joinRegisterParts(letters.value, digits.value);

const pushValue = (commit = false) => {
    const value = combined();
    emit('update:modelValue', value);

    if (commit) {
        emit('commit', value);
    }
};

const onLettersInput = (event) => {
    letters.value = normalizeRegisterLetters(event.target.value);
    event.target.value = letters.value;
    pushValue(false);

    if (letters.value.length >= 2) {
        nextTick(() => digitRef.value?.focus());
    }
};

const onDigitsInput = (event) => {
    digits.value = normalizeRegisterDigits(event.target.value);
    event.target.value = digits.value;
    pushValue(false);
};

const clearBlurTimer = () => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
};

const onPartFocus = () => {
    editing = true;
    clearBlurTimer();
};

const onPartBlur = () => {
    clearBlurTimer();
    blurTimer = setTimeout(() => {
        const active = document.activeElement;
        if (active === letterRef.value || active === digitRef.value) {
            return;
        }

        editing = false;
        pushValue(true);
    }, 120);
};

const onLettersKeydown = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        digitRef.value?.focus();
        return;
    }

    if (event.key === 'ArrowRight' && letterRef.value?.selectionStart === letters.value.length) {
        digitRef.value?.focus();
    }
};

const onDigitsKeydown = (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        editing = false;
        pushValue(true);
        digitRef.value?.blur();
        return;
    }

    if (
        event.key === 'Backspace'
        && ! digits.value
        && digitRef.value?.selectionStart === 0
    ) {
        letterRef.value?.focus();
    }

    if (event.key === 'ArrowLeft' && digitRef.value?.selectionStart === 0) {
        letterRef.value?.focus();
    }
};

let unregisterFlusher = null;

onMounted(() => {
    unregisterFlusher = registerSheetEditorFlusher(() => {
        if (editing) {
            clearBlurTimer();
            editing = false;
            pushValue(true);
        }
    });
});

onBeforeUnmount(() => {
    unregisterFlusher?.();
    unregisterFlusher = null;
    clearBlurTimer();

    // Мөр хаах / нүд устгах үед blur timer амжихгүй — өөрчлөлтийг алдахгүйн тулд commit хийнэ.
    if (editing) {
        editing = false;
        pushValue(true);
    }
});
</script>

<template>
    <div
        class="ui-register-no inline-flex w-full max-w-full items-center gap-0"
        :title="editable ? 'Эхний 2 үсэг криллээр, дараа нь 8 орон' : undefined"
    >
        <input
            ref="letterRef"
            :value="letters"
            type="text"
            inputmode="text"
            autocomplete="off"
            spellcheck="false"
            maxlength="2"
            class="ui-input w-[1.85rem] shrink-0 !px-0.5 !py-1 text-center text-xs font-semibold uppercase tracking-tight"
            placeholder="АА"
            :disabled="!editable"
            aria-label="Регистрийн эхний хоёр үсэг"
            @input="onLettersInput"
            @focus="onPartFocus"
            @blur="onPartBlur"
            @keydown="onLettersKeydown"
        >
        <input
            ref="digitRef"
            :value="digits"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="off"
            spellcheck="false"
            maxlength="8"
            class="ui-input min-w-0 flex-1 !px-0.5 !py-1 text-xs tabular-nums tracking-tight"
            placeholder="00000000"
            :disabled="!editable"
            aria-label="Регистрийн 8 орон"
            @input="onDigitsInput"
            @focus="onPartFocus"
            @blur="onPartBlur"
            @keydown="onDigitsKeydown"
        >
    </div>
</template>
