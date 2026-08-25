<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    multiline: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    type: { type: String, default: 'text' },
    editable: { type: Boolean, default: true },
    align: { type: String, default: 'left' },
    emptyLabel: { type: String, default: '—' },
    /** Утасны жагсаалт гэх мэт сонголтууд: [{ value, label, hint? }] */
    options: { type: Array, default: null },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const editing = ref(false);
const local = ref(props.modelValue ?? '');
const inputRef = ref(null);
const rootRef = ref(null);
const highlight = ref(0);
const menuStyle = ref({});

const updateMenuPosition = () => {
    const el = rootRef.value;
    if (! el) {
        return;
    }
    const rect = el.getBoundingClientRect();
    menuStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        top: `${rect.bottom}px`,
        width: `${Math.max(rect.width, 220)}px`,
        zIndex: 80,
    };
};

const hasOptions = computed(() => Array.isArray(props.options) && props.options.length > 0);

const filteredOptions = computed(() => {
    if (! hasOptions.value) {
        return [];
    }
    const q = String(local.value ?? '').trim().toLowerCase();
    if (! q) {
        return props.options.slice(0, 80);
    }

    return props.options
        .filter((opt) => {
            const label = String(opt.label ?? opt.value ?? '').toLowerCase();
            const hint = String(opt.hint ?? '').toLowerCase();

            return label.includes(q) || hint.includes(q);
        })
        .slice(0, 80);
});

watch(
    () => props.modelValue,
    (value) => {
        if (! editing.value) {
            local.value = value ?? '';
        }
    },
);

watch(filteredOptions, () => {
    highlight.value = 0;
});

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
    highlight.value = 0;
    await nextTick();
    updateMenuPosition();
    const el = inputRef.value;
    if (! el) {
        return;
    }
    el.focus();
    if (props.multiline) {
        el.style.height = 'auto';
        el.style.height = `${Math.max(40, el.scrollHeight)}px`;
    } else if (typeof el.select === 'function' && ! hasOptions.value) {
        el.select();
    }
};

const commitValue = (value) => {
    editing.value = false;
    let next = value;
    if (props.type === 'number') {
        const n = Number.parseInt(next, 10);
        next = Number.isNaN(n) ? 0 : n;
    }
    local.value = next;
    emit('update:modelValue', next);
    emit('commit', next);
};

const finish = () => {
    if (! editing.value) {
        return;
    }
    commitValue(local.value);
};

const cancel = () => {
    local.value = props.modelValue ?? '';
    editing.value = false;
};

const pickOption = (opt) => {
    commitValue(opt.value ?? opt.label ?? '');
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        cancel();

        return;
    }

    if (hasOptions.value && filteredOptions.value.length) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight.value = (highlight.value + 1) % filteredOptions.value.length;

            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight.value = (highlight.value - 1 + filteredOptions.value.length) % filteredOptions.value.length;

            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            pickOption(filteredOptions.value[highlight.value]);

            return;
        }
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
        ref="rootRef"
        class="ui-sheet-cell"
        :class="{
            'is-editing': editing,
            'is-editable': editable,
            'is-center': align === 'center',
            'is-placeholder': ! modelValue && !! placeholder,
            'has-options': hasOptions,
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
            :type="type === 'number' ? 'number' : 'text'"
            class="ui-sheet-editor"
            :class="{ 'text-center': align === 'center' }"
            :placeholder="hasOptions ? (placeholder || 'Хайлт / сонгох…') : placeholder"
            autocomplete="off"
            @blur="finish"
            @keydown="onKeydown"
            @input="updateMenuPosition"
        />
        <div
            v-else
            class="ui-sheet-display ui-clamp-2"
            :class="{ 'text-center': align === 'center', 'text-slate-400': ! modelValue && !! placeholder }"
            :title="modelValue ? String(modelValue) : ''"
        >
            <slot>{{ displayText() }}</slot>
        </div>

        <Teleport to="body">
            <div
                v-if="editing && hasOptions"
                class="max-h-56 overflow-y-auto border border-slate-200 bg-white shadow-lg"
                :style="menuStyle"
                @mousedown.prevent
            >
                <button
                    v-for="(opt, idx) in filteredOptions"
                    :key="`${opt.value}-${idx}`"
                    type="button"
                    class="flex w-full flex-col items-start gap-0.5 px-2.5 py-1.5 text-left text-sm hover:bg-brand-navy-50"
                    :class="idx === highlight ? 'bg-brand-navy-50' : ''"
                    @mousedown.prevent="pickOption(opt)"
                >
                    <span class="font-medium text-slate-800">{{ opt.label ?? opt.value }}</span>
                    <span v-if="opt.hint" class="text-[11px] text-slate-500">{{ opt.hint }}</span>
                </button>
                <p v-if="! filteredOptions.length" class="px-2.5 py-2 text-xs text-slate-400">
                    Утасны жагсаалтад тохирох нэр олдсонгүй.
                </p>
            </div>
        </Teleport>
    </div>
</template>
