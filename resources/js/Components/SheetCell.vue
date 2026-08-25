<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const CATEGORY_FILTERS = [
    { key: 'udirdlaga', label: 'Удирдлагууд' },
    { key: 'heltes', label: 'Хэлтэс' },
    { key: 'sum', label: 'Сум' },
    { key: 'agentlag', label: 'Агентлаг' },
    { key: 'baiguullaga', label: 'Байгууллага' },
];

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    multiline: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    type: { type: String, default: 'text' },
    editable: { type: Boolean, default: true },
    align: { type: String, default: 'left' },
    emptyLabel: { type: String, default: '—' },
    /** Утасны жагсаалт: [{ value, label, hint?, org?, category? }] */
    options: { type: Array, default: null },
    /** Олон нэр сонгох (хадгалахдаа «/»-аар залгана) */
    multiple: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const editing = ref(false);
const local = ref(props.modelValue ?? '');
const search = ref('');
const selected = ref([]);
const categoryOn = ref({
    udirdlaga: true,
    heltes: true,
    sum: true,
    agentlag: true,
    baiguullaga: true,
});
const inputRef = ref(null);
const rootRef = ref(null);
const highlight = ref(0);
const menuStyle = ref({});
const ignoreBlur = ref(false);
let blurTimer = null;

const hasOptions = computed(() => Array.isArray(props.options) && props.options.length > 0);

const parseSelected = (value) => String(value ?? '')
    .split(/[/;,|]+/)
    .map((s) => s.trim())
    .filter(Boolean);

const selectedSet = computed(() => new Set(selected.value));

const filteredOptions = computed(() => {
    if (! hasOptions.value) {
        return [];
    }

    const q = String(search.value ?? '').trim().toLowerCase();
    const cats = categoryOn.value;

    const list = props.options.filter((opt) => {
        const cat = opt.category || 'baiguullaga';
        if (! cats[cat]) {
            return false;
        }
        if (! q) {
            return true;
        }
        const label = String(opt.label ?? opt.value ?? '').toLowerCase();
        const hint = String(opt.hint ?? '').toLowerCase();
        const org = String(opt.org ?? '').toLowerCase();

        return label.includes(q) || hint.includes(q) || org.includes(q);
    });

    return list.slice(0, 200);
});

const updateMenuPosition = () => {
    const el = rootRef.value;
    if (! el) {
        return;
    }
    const rect = el.getBoundingClientRect();
    const width = Math.max(rect.width, 320);
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
    categoryOn.value = { udirdlaga: true, heltes: true, sum: true, agentlag: true, baiguullaga: true };

    if (hasOptions.value) {
        search.value = '';
        local.value = '';
        selected.value = parseSelected(props.modelValue);
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
    selected.value = [];
    emit('update:modelValue', next);
    emit('commit', next);
};

const commitMulti = () => {
    commitValue(selected.value.join('/'));
};

const finish = () => {
    if (! editing.value) {
        return;
    }

    if (hasOptions.value) {
        if (props.multiple) {
            commitMulti();
        } else {
            editing.value = false;
            search.value = '';
            local.value = props.modelValue ?? '';
            selected.value = [];
            stopListeners();
        }

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
    }, 150);
};

const cancel = () => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
    local.value = props.modelValue ?? '';
    search.value = '';
    selected.value = [];
    editing.value = false;
    stopListeners();
};

const isSelected = (opt) => selectedSet.value.has(String(opt.value ?? opt.label ?? ''));

const pickOption = (opt) => {
    ignoreBlur.value = true;
    const value = String(opt.value ?? opt.label ?? '');
    if (! value) {
        return;
    }

    if (props.multiple) {
        const idx = selected.value.indexOf(value);
        if (idx >= 0) {
            selected.value.splice(idx, 1);
        } else {
            selected.value.push(value);
        }
        nextTick(() => {
            ignoreBlur.value = false;
            inputRef.value?.focus();
        });

        return;
    }

    commitValue(value);
};

const removeChip = (name) => {
    ignoreBlur.value = true;
    selected.value = selected.value.filter((n) => n !== name);
    nextTick(() => {
        ignoreBlur.value = false;
        inputRef.value?.focus();
    });
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
                class="flex max-h-[22rem] flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-xl"
                :style="menuStyle"
                @mousedown.prevent="ignoreBlur = true"
            >
                <div class="shrink-0 space-y-2 border-b border-slate-100 px-2.5 py-2">
                    <div class="flex flex-wrap gap-3 text-xs text-slate-700">
                        <label
                            v-for="cat in CATEGORY_FILTERS"
                            :key="cat.key"
                            class="inline-flex cursor-pointer items-center gap-1.5"
                        >
                            <input
                                v-model="categoryOn[cat.key]"
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-500"
                            />
                            {{ cat.label }}
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        {{ filteredOptions.length }} / {{ options.length }} хүн
                        <span v-if="multiple"> · олон сонгох боломжтой</span>
                    </p>
                    <div v-if="multiple && selected.length" class="flex flex-wrap gap-1">
                        <span
                            v-for="name in selected"
                            :key="name"
                            class="inline-flex max-w-full items-center gap-1 rounded bg-brand-navy-50 px-1.5 py-0.5 text-[11px] font-medium text-brand-navy-800"
                        >
                            <span class="truncate">{{ name }}</span>
                            <button type="button" class="text-brand-navy-500 hover:text-red-600" @mousedown.prevent="removeChip(name)">×</button>
                        </span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto py-1">
                    <button
                        v-for="(opt, idx) in filteredOptions"
                        :key="`${opt.value}-${idx}`"
                        type="button"
                        class="flex w-full items-start gap-2 px-2.5 py-1.5 text-left text-sm hover:bg-brand-navy-50"
                        :class="[
                            idx === highlight ? 'bg-brand-navy-50' : '',
                            isSelected(opt) ? 'bg-sky-50' : '',
                        ]"
                        @mousedown.prevent="pickOption(opt)"
                    >
                        <span
                            v-if="multiple"
                            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[10px]"
                            :class="isSelected(opt)
                                ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                                : 'border-slate-300 text-transparent'"
                        >
                            ✓
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-slate-800">{{ opt.label ?? opt.value }}</span>
                            <span
                                v-if="opt.hint"
                                class="block truncate text-[11px] font-normal text-slate-500"
                                :title="opt.hint"
                            >{{ opt.hint }}</span>
                        </span>
                    </button>
                    <p v-if="! filteredOptions.length" class="px-2.5 py-3 text-xs text-slate-400">
                        Утасны жагсаалтад тохирох нэр олдсонгүй.
                    </p>
                </div>

                <div v-if="multiple" class="flex shrink-0 items-center justify-between gap-2 border-t border-slate-100 px-2.5 py-2">
                    <span class="text-[11px] text-slate-500">Сонгосон: {{ selected.length }}</span>
                    <button
                        type="button"
                        class="rounded-lg bg-brand-navy-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-navy-700"
                        @mousedown.prevent="commitMulti"
                    >
                        Болсон
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
