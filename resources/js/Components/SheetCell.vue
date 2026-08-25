<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    multiline: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    type: { type: String, default: 'text' },
    editable: { type: Boolean, default: true },
    align: { type: String, default: 'left' },
    emptyLabel: { type: String, default: '—' },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const editing = ref(false);
const local = ref(props.modelValue ?? '');
const inputRef = ref(null);

watch(
    () => props.modelValue,
    (value) => {
        if (! editing.value) {
            local.value = value ?? '';
        }
    },
);

const displayText = () => {
    const value = props.modelValue;
    if (value === null || value === undefined || value === '') {
        return props.placeholder || props.emptyLabel;
    }

    return String(value);
};

const startEdit = async () => {
    if (! props.editable || editing.value) {
        return;
    }

    editing.value = true;
    local.value = props.modelValue ?? '';
    await nextTick();
    const el = inputRef.value;
    if (! el) {
        return;
    }
    el.focus();
    if (props.multiline) {
        el.style.height = 'auto';
        el.style.height = `${Math.max(40, el.scrollHeight)}px`;
    } else if (typeof el.select === 'function') {
        el.select();
    }
};

const finish = () => {
    if (! editing.value) {
        return;
    }

    editing.value = false;
    let value = local.value;
    if (props.type === 'number') {
        const n = Number.parseInt(value, 10);
        value = Number.isNaN(n) ? 0 : n;
        local.value = value;
    }

    emit('update:modelValue', value);
    emit('commit', value);
};

const cancel = () => {
    local.value = props.modelValue ?? '';
    editing.value = false;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        cancel();

        return;
    }

    if (! props.multiline && event.key === 'Enter') {
        event.preventDefault();
        finish();
    }
};

const onInput = (event) => {
    if (! props.multiline) {
        return;
    }
    const el = event.target;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
};
</script>

<template>
    <div
        class="ui-sheet-cell"
        :class="{
            'is-editing': editing,
            'is-editable': editable,
            'is-center': align === 'center',
            'is-placeholder': ! modelValue && !! placeholder,
        }"
        @click="startEdit"
    >
        <textarea
            v-if="editing && multiline"
            ref="inputRef"
            v-model="local"
            class="ui-sheet-editor"
            :placeholder="placeholder"
            rows="2"
            @blur="finish"
            @keydown="onKeydown"
            @input="onInput"
        />
        <input
            v-else-if="editing"
            ref="inputRef"
            v-model="local"
            :type="type"
            class="ui-sheet-editor"
            :class="{ 'text-center': align === 'center' }"
            :placeholder="placeholder"
            @blur="finish"
            @keydown="onKeydown"
        />
        <div
            v-else
            class="ui-sheet-display ui-clamp-2"
            :class="{ 'text-center': align === 'center', 'text-slate-400': ! modelValue && !! placeholder }"
            :title="modelValue ? String(modelValue) : ''"
        >
            <slot>{{ displayText() }}</slot>
        </div>
    </div>
</template>
