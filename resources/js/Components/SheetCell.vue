<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    expandPersonNames,
    formatPersonNamesDisplay,
    serializePersonNames,
    visiblePersonGroups,
} from '@/utils/soumGovernors';

const CATEGORY_FILTERS = [
    { key: 'udirdlaga', label: 'Аймгийн удирдлагууд', short: 'Удирдлага' },
    { key: 'sum', label: 'Сум', short: 'Сум' },
    { key: 'heltes', label: 'Хэлтэс', short: 'Хэлтэс' },
    { key: 'agentlag', label: 'Агентлаг', short: 'Агентлаг' },
    { key: 'baiguullaga', label: 'Байгууллага', short: 'Байгууллага' },
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

// Бүх SheetCell хуваалцах — өөр нүд нээхэд сүүлийн ангиллын шүүлт үлдэнэ.
const defaultCategoryFilters = () => ({
    udirdlaga: true,
    heltes: true,
    sum: true,
    agentlag: true,
    baiguullaga: true,
});

let sharedCategoryFilters = defaultCategoryFilters();

const editing = ref(false);
const local = ref(props.modelValue ?? '');
const search = ref('');
const selected = ref([]);
const categoryOn = ref({ ...sharedCategoryFilters });
const inputRef = ref(null);
const rootRef = ref(null);
const menuRef = ref(null);
const highlight = ref(0);
const menuStyle = ref({});
const ignoreBlur = ref(false);
let blurTimer = null;

watch(categoryOn, (val) => {
    sharedCategoryFilters = { ...val };
}, { deep: true });

const hasOptions = computed(() => Array.isArray(props.options) && props.options.length > 0);

// Нэрийн товчлол — «Н.Алдарбаяр» → «НА»
const initials = (opt) => {
    const text = String(opt?.label ?? opt?.value ?? '').replace(/\s+/g, ' ').trim();
    if (! text) return '?';
    const parts = text.split(/[.\s]+/).filter(Boolean);

    return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '');
};

const allCategoriesOn = computed(() => CATEGORY_FILTERS.every((c) => categoryOn.value[c.key]));

const toggleAllCategories = () => {
    const next = ! allCategoriesOn.value;
    CATEGORY_FILTERS.forEach((c) => {
        categoryOn.value[c.key] = next;
    });
};

const clearSelection = () => {
    selected.value = [];
};

const selectedSet = computed(() => new Set(selected.value));

/** Ангиллын шүүлтэнд тохирох бүлгүүд (Сум → Засаг/ЗДТГ, Агентлаг → дарга…) */
const personGroups = computed(() => (
    hasOptions.value
        ? visiblePersonGroups(props.options, categoryOn.value)
        : []
));

const isGroupSelected = (group) => {
    const names = group?.names ?? [];
    if (! names.length) return false;

    return names.every((n) => selectedSet.value.has(n));
};

const togglePersonGroup = (group) => {
    ignoreBlur.value = true;
    const names = group?.names ?? [];
    if (! names.length) {
        releaseIgnoreBlur();

        return;
    }

    if (isGroupSelected(group)) {
        const drop = new Set(names);
        selected.value = selected.value.filter((n) => ! drop.has(n));
    } else if (props.multiple) {
        selected.value = [...new Set([...selected.value, ...names])];
    } else {
        commitValue(group.label);

        return;
    }

    releaseIgnoreBlur();
};

/** Чипүүд: сонгогдсон бүлгүүдийг нэгтгэж харуулна */
const selectedChips = computed(() => {
    let remaining = [...selected.value];
    const chips = [];

    for (const group of personGroups.value) {
        const names = group.names;
        if (! names.length) continue;
        if (! names.every((n) => remaining.includes(n))) continue;

        const drop = new Set(names);
        remaining = remaining.filter((n) => ! drop.has(n));
        chips.push({ key: group.key, label: group.label, group: true, groupKey: group.key });
    }

    // Шүүлт унтраасан үед ч хадгалсан бүлгийн нэрийг харуулна.
    for (const group of visiblePersonGroups(props.options || [], {
        sum: true, agentlag: true, udirdlaga: true, heltes: true, baiguullaga: true,
    })) {
        if (chips.some((c) => c.key === group.key)) continue;
        const names = group.names;
        if (! names.length) continue;
        if (! names.every((n) => remaining.includes(n))) continue;

        const drop = new Set(names);
        remaining = remaining.filter((n) => ! drop.has(n));
        chips.push({ key: group.key, label: group.label, group: true, groupKey: group.key });
    }

    return [
        ...chips,
        ...remaining.map((n) => ({ key: n, label: n, group: false })),
    ];
});

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
    const width = Math.min(Math.max(rect.width, 430), window.innerWidth - 16);
    let left = rect.left;
    if (left + width > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - width - 8);
    }
    // Доор багтахгүй бол нүдний дээр талд гаргана.
    const belowSpace = window.innerHeight - rect.bottom - 16;
    const openUp = belowSpace < 260 && rect.top > belowSpace;

    menuStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
        width: `${width}px`,
        maxHeight: `${Math.max(240, (openUp ? rect.top : belowSpace) - 8)}px`,
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

    if (hasOptions.value) {
        return formatPersonNamesDisplay(value, props.options) || String(value);
    }

    return String(value);
};

const startEdit = async () => {
    if (! props.editable || editing.value) {
        return;
    }

    editing.value = true;
    highlight.value = 0;
    // Сүүлийн шүүлтийг хадгална (өөр нүд/хүснэгт рүү шилжихэд идэвхтэй үлдэнэ).
    categoryOn.value = { ...sharedCategoryFilters };

    if (hasOptions.value) {
        search.value = '';
        local.value = '';
        selected.value = expandPersonNames(props.modelValue, props.options);
    } else {
        local.value = props.modelValue ?? '';
    }

    await nextTick();
    updateMenuPosition();
    window.addEventListener('scroll', updateMenuPosition, true);
    window.addEventListener('resize', updateMenuPosition);
    if (hasOptions.value) {
        document.addEventListener('pointerdown', onPointerDownOutside, true);
    }

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
    document.removeEventListener('pointerdown', onPointerDownOutside, true);
};

const onPointerDownOutside = (event) => {
    if (! editing.value) {
        return;
    }

    const target = event.target;
    if (! (target instanceof Node)) {
        return;
    }

    if (rootRef.value?.contains(target) || menuRef.value?.contains(target)) {
        return;
    }

    // Гадна дарахад шууд хаана — одоогийн утгыг хадгална (сонгоогүй бол өмнөх утга үлдэнэ).
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }
    ignoreBlur.value = false;
    finish();
};

const releaseIgnoreBlur = () => {
    nextTick(() => {
        ignoreBlur.value = false;
        inputRef.value?.focus();
    });
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
        const raw = String(next ?? '').trim();
        if (raw === '') {
            next = '';
        } else {
            const n = Number.parseInt(raw, 10);
            next = Number.isNaN(n) ? '' : n;
        }
    }
    local.value = next;
    search.value = '';
    selected.value = [];
    emit('update:modelValue', next);
    emit('commit', next);
};

// Нүдийг нэг товшилтоор цэвэрлэнэ (жагсаалтаас сонгосон нэрийг устгах).
const clearCell = () => {
    selected.value = [];
    search.value = '';
    commitValue('');
};

const commitMulti = () => {
    commitValue(serializePersonNames(selected.value, props.options));
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
        releaseIgnoreBlur();

        return;
    }

    commitValue(value);
};

const removeChip = (chip) => {
    ignoreBlur.value = true;

    if (chip?.group) {
        const allGroups = visiblePersonGroups(props.options || [], {
            sum: true, agentlag: true, udirdlaga: true, heltes: true, baiguullaga: true,
        });
        const group = allGroups.find((g) => g.key === chip.key || g.label === chip.label);
        const drop = new Set(group?.names ?? []);
        selected.value = selected.value.filter((n) => ! drop.has(n));
    } else {
        const name = typeof chip === 'string' ? chip : chip?.key;
        selected.value = selected.value.filter((n) => n !== name);
    }

    releaseIgnoreBlur();
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
            :type="type === 'number' ? 'number' : (type === 'date' ? 'date' : 'text')"
            class="ui-sheet-editor"
            :class="{ 'text-center': align === 'center' }"
            :placeholder="placeholder"
            autocomplete="off"
            @blur="onBlur"
            @keydown="onKeydown"
        />
        <button
            v-if="! editing && editable && hasOptions && modelValue"
            type="button"
            class="ui-sheet-clear"
            title="Цэвэрлэх"
            @click.stop="clearCell"
        >
            ✕
        </button>
        <div
            v-if="! editing"
            class="ui-sheet-display ui-clamp-2"
            :class="{ 'text-center': align === 'center', 'text-slate-400': ! modelValue && !! placeholder }"
            :title="modelValue ? (formatPersonNamesDisplay(modelValue, options) || String(modelValue)) : ''"
        >
            <slot>{{ displayText() }}</slot>
        </div>

        <Teleport to="body">
            <div
                v-if="editing && hasOptions"
                ref="menuRef"
                class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl ring-1 ring-slate-900/5"
                :style="menuStyle"
                @mousedown.prevent="ignoreBlur = true; releaseIgnoreBlur()"
            >
                <div class="shrink-0 space-y-2 border-b border-slate-100 bg-slate-50/70 px-3 py-2.5">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-3.5-3.5" stroke-linecap="round" />
                        </svg>
                        <span class="min-w-0 flex-1 truncate text-xs text-slate-500">
                            <template v-if="search">«{{ search }}» — {{ filteredOptions.length }} илэрц</template>
                            <template v-else>Нэрээр бичиж хайна · {{ filteredOptions.length }}/{{ options.length }} хүн</template>
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-slate-500 transition hover:bg-white hover:text-brand-navy-700"
                            @mousedown.prevent="toggleAllCategories"
                        >
                            {{ allCategoriesOn ? 'Шүүлт цэвэрлэх' : 'Бүгдийг сонгох' }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-1">
                        <button
                            v-for="cat in CATEGORY_FILTERS"
                            :key="cat.key"
                            type="button"
                            class="shrink-0 whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide transition"
                            :class="categoryOn[cat.key]
                                ? 'border-brand-navy-500 bg-brand-navy-600 text-white'
                                : 'border-slate-200 bg-white text-slate-500 hover:border-brand-navy-300'"
                            :title="cat.label"
                            @mousedown.prevent="categoryOn[cat.key] = ! categoryOn[cat.key]"
                        >
                            {{ cat.short ?? cat.label }}
                        </button>
                        <button
                            v-for="group in personGroups"
                            :key="`chip-${group.key}`"
                            type="button"
                            class="shrink-0 whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-semibold transition"
                            :class="isGroupSelected(group)
                                ? 'border-emerald-600 bg-emerald-600 text-white'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-400'"
                            :title="`${group.label} — ${group.names.length} хүн`"
                            @mousedown.prevent="togglePersonGroup(group)"
                        >
                            {{ group.label }} ({{ group.names.length }})
                        </button>
                    </div>

                    <div v-if="multiple && selectedChips.length" class="flex flex-wrap gap-1 pt-0.5">
                        <span
                            v-for="chip in selectedChips"
                            :key="chip.key"
                            class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium text-white"
                            :class="chip.group ? 'bg-emerald-700' : 'bg-emerald-600'"
                        >
                            <span class="truncate">{{ chip.label }}</span>
                            <button
                                type="button"
                                class="text-white/70 transition hover:text-white"
                                title="Хасах"
                                @mousedown.prevent="removeChip(chip)"
                            >
                                ✕
                            </button>
                        </span>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto py-1">
                    <button
                        v-if="! multiple"
                        type="button"
                        class="flex w-full items-center gap-2 border-b border-slate-100 px-3 py-2 text-left text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                        @mousedown.prevent="clearCell"
                    >
                        <span class="flex h-5 w-5 items-center justify-center rounded-full border border-rose-200 text-[11px]">✕</span>
                        Сонголтгүй (цэвэрлэх)
                    </button>
                    <button
                        v-for="group in personGroups"
                        :key="`row-${group.key}`"
                        type="button"
                        class="flex w-full items-center gap-2.5 border-b border-slate-100 px-3 py-2.5 text-left transition"
                        :class="isGroupSelected(group)
                            ? 'bg-emerald-50 ring-1 ring-inset ring-emerald-200'
                            : 'hover:bg-emerald-50/60'"
                        @mousedown.prevent="togglePersonGroup(group)"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                            :class="isGroupSelected(group) ? 'bg-emerald-600 text-white' : 'bg-emerald-100 text-emerald-800'"
                        >
                            {{ group.names.length }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-800">{{ group.label }}</span>
                            <span class="block text-[10px] text-slate-500">
                                {{ group.names.length }} хүнийг нэг дор сонгоно
                            </span>
                        </span>
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px]"
                            :class="isGroupSelected(group)
                                ? 'bg-emerald-600 text-white'
                                : 'border border-slate-200 text-transparent'"
                        >
                            ✓
                        </span>
                    </button>
                    <button
                        v-for="(opt, idx) in filteredOptions"
                        :key="`${opt.value}-${idx}`"
                        type="button"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left transition"
                        :class="[
                            isSelected(opt)
                                ? 'bg-emerald-50 ring-1 ring-inset ring-emerald-200'
                                : (idx === highlight ? 'bg-brand-navy-50' : 'hover:bg-slate-50'),
                        ]"
                        @mousedown.prevent="pickOption(opt)"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold uppercase"
                            :class="isSelected(opt)
                                ? 'bg-emerald-600 text-white'
                                : 'bg-slate-100 text-slate-500'"
                        >
                            {{ initials(opt) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex min-w-0 items-baseline gap-1.5">
                                <span class="shrink-0 text-sm font-semibold text-slate-800">{{ opt.label ?? opt.value }}</span>
                                <span
                                    v-if="opt.hint"
                                    class="min-w-0 truncate text-[11px] text-slate-500"
                                    :title="opt.hint"
                                >— {{ opt.hint }}</span>
                            </span>
                            <span
                                v-if="opt.org"
                                class="block truncate text-[10px] uppercase tracking-wide text-slate-400"
                                :title="opt.org"
                            >{{ opt.org }}</span>
                        </span>

                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px]"
                            :class="isSelected(opt)
                                ? 'bg-emerald-600 text-white'
                                : 'border border-slate-200 text-transparent'"
                        >
                            ✓
                        </span>
                    </button>
                    <p v-if="! filteredOptions.length" class="px-3 py-6 text-center text-xs text-slate-400">
                        Тохирох нэр олдсонгүй.<br>
                        <span class="text-[11px]">Ангиллын шүүлтээ өөрчилж эсвэл өөр нэр бичиж үзнэ үү.</span>
                    </p>
                </div>

                <div v-if="multiple" class="flex shrink-0 items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/70 px-3 py-2">
                    <span class="text-[11px] text-slate-500">
                        Сонгосон: <b class="text-slate-700">{{ selected.length }}</b>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <button
                            v-if="selected.length"
                            type="button"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-white hover:text-red-600"
                            @mousedown.prevent="clearSelection"
                        >
                            Цэвэрлэх
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-brand-navy-600 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-navy-700"
                            @mousedown.prevent="commitMulti"
                        >
                            Болсон
                        </button>
                    </span>
                </div>
            </div>
        </Teleport>
    </div>
</template>
