<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
const search = ref('');
const inputRef = ref(null);
const rootRef = ref(null);
const highlight = ref(0);
const menuStyle = ref({});
const ignoreBlur = ref(false);
let blurTimer = null;

const hasOptions = computed(() => Array.isArray(props.options) && props.options.length > 0);

const filteredOptions = computed(() => {
    if (! hasOptions.value) {
        return [];
    }

    const q = String(search.value ?? '').trim().toLowerCase();
    const list = ! q
        ? props.options
        : props.options.filter((opt) => {
            const label = String(opt.label ?? opt.value ?? '').toLowerCase();
            const hint = String(opt.hint ?? '').toLowerCase();

            return label.includes(q) || hint.includes(q);
        });

    return list.slice(0, 200);
});

const updateMenuPosition = () => {
    const el = rootRef.value;
    if (! el) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const width = Math.max(rect.width, 280);
    let left = rect.left;
    if (left + width > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - width - 8);
    }
    menuStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        top: `${rect.bottom + 2}px`,
        width: `${width}px`,
        zIndex: 200,
    };
};

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

watch(search, () => {
    if (editing.value && hasOptions.value) {
        nextTick(updateMenuPosition);
    }
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
    highlight.value = 0;

    if (hasOptions.value) {
        // Хайлтыг хоосон эхлүүлнэ — бүх жагсаалт харагдана
        search.value = '';
        local.value = '';
    } else {
        local.value = props.modelValue ?? '';
    }

    await nextTick();
    updateMenuPosition();
    window.addEventListener('scroll', updateMenuPosition, true);
    window.addEventListener('resize', updateMenuPosition);

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

const stopListeners = () => {
    window.removeEventListener('scroll', updateMenuPosition, true);
    window.removeEventListener('resize', updateMenuPosition);
};

const commitValue = (value) => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
    editing.value = false;
    ignoreBlur.value = false;
    stopListeners();

    let next = value;
    if (props.type === 'number') {
        const n = Number.parseInt(next, 10);
        next = Number.isNaN(n) ? 0 : n;
    }
    local.value = next;
    search.value = '';
    emit('update:modelValue', next);
    emit('commit', next);
};

const finish = () => {
    if (! editing.value) {
        return;
    }

    // Сонголттой горимд blur дээр хайлтын текстийг хадгалахгүй
    if (hasOptions.value) {
        editing.value = false;
        search.value = '';
        local.value = props.modelValue ?? '';
        stopListeners();

        return;
    }

    commitValue(local.value);
};

const onBlur = () => {
    if (ignoreBlur.value) {
        return;
    }
    blurTimer = setTimeout(() => {
        finish();
    }, 120);
};

const cancel = () => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
    local.value = props.modelValue ?? '';
    search.value = '';
    editing.value = false;
    stopListeners();
};

const pickOption = (opt) => {
    ignoreBlur.value = true;
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

    if (! props.multiline && ! hasOptions.value && event.key === 'Enter') {
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

onBeforeUnmount(() => {
    stopListeners();
    if (blurTimer) {
        clearTimeout(blurTimer);
    }
});
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
            @blur="onBlur"
            @keydown="onKeydown"
            @input="onInput"
        />
        <input
            v-else-if="editing && hasOptions"
            ref="inputRef"
            v-model="search"
            type="text"
            class="ui-sheet-editor"
            :class="{ 'text-center': align === 'center' }"
            :placeholder="placeholder || 'Нэрээр хайх…'"
            autocomplete="off"
            @blur="onBlur"
            @keydown="onKeydown"
            @input="updateMenuPosition"
        />
        <input
            v-else-if="editing"
            ref="inputRef"
            v-model="local"
            :type="type === 'number' ? 'number' : 'text'"
            class="ui-sheet-editor"
            :class="{ 'text-center': align === 'center' }"
            :placeholder="placeholder"
            autocomplete="off"
            @blur="onBlur"
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

        <Teleport to="body">
            <div
                v-if="editing && hasOptions"
                class="max-h-64 overflow-y-auto rounded-md border border-slate-200 bg-white py-1 shadow-xl"
                :style="menuStyle"
                @mousedown.prevent="ignoreBlur = true"
            >
                <p class="border-b border-slate-100 px-2.5 py-1.5 text-[11px] text-slate-500">
                    {{ filteredOptions.length }} / {{ options.length }} хүн · нэрээр хайна уу
                </p>
                <button
                    v-for="(opt, idx) in filteredOptions"
                    :key="`${opt.value}-${idx}`"
                    type="button"
                    class="flex w-full flex-col items-start gap-0.5 px-2.5 py-1.5 text-left text-sm hover:bg-brand-navy-50"
                    :class="[
                        idx === highlight ? 'bg-brand-navy-50' : '',
                        String(opt.value) === String(modelValue) ? 'font-semibold text-brand-navy-800' : '',
                    ]"
                    @mousedown.prevent="pickOption(opt)"
                >
                    <span class="text-slate-800">{{ opt.label ?? opt.value }}</span>
                    <span v-if="opt.hint" class="text-[11px] font-normal text-slate-500">{{ opt.hint }}</span>
                </button>
                <p v-if="! filteredOptions.length" class="px-2.5 py-3 text-xs text-slate-400">
                    Утасны жагсаалтад тохирох нэр олдсонгүй.
                </p>
            </div>
        </Teleport>
    </div>
</template>
