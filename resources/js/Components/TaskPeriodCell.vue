<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { formatTaskPeriodMd, periodToInputRange } from '@/utils/taskPeriod';

const props = defineProps({
    modelValue: { type: String, default: '' },
    editable: { type: Boolean, default: true },
    placeholder: { type: String, default: '08.01–09.30' },
    emptyLabel: { type: String, default: '—' },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const editing = ref(false);
const startDate = ref('');
const endDate = ref('');
const rootRef = ref(null);
const menuRef = ref(null);
const menuStyle = ref({});
const ignoreBlur = ref(false);
let blurTimer = null;

const hasValue = computed(() => String(props.modelValue ?? '').trim().length > 0);

const displayText = computed(() => {
    if (hasValue.value) {
        return props.modelValue;
    }

    return props.placeholder || props.emptyLabel;
});

const updateMenuPosition = () => {
    const el = rootRef.value;
    if (! el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 16);
    let left = rect.left;
    if (left + width > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - width - 8);
    }

    const belowSpace = window.innerHeight - rect.bottom - 16;
    const openUp = belowSpace < 220 && rect.top > belowSpace;

    menuStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
        width: `${width}px`,
        zIndex: 200,
    };
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

    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }

    ignoreBlur.value = false;
    commit();
};

const open = async () => {
    if (! props.editable || editing.value) {
        return;
    }

    const range = periodToInputRange(props.modelValue);
    startDate.value = range.start;
    endDate.value = range.end;
    editing.value = true;

    await nextTick();
    updateMenuPosition();
    window.addEventListener('scroll', updateMenuPosition, true);
    window.addEventListener('resize', updateMenuPosition);
    document.addEventListener('pointerdown', onPointerDownOutside, true);
};

const closeWithoutSave = () => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }

    const range = periodToInputRange(props.modelValue);
    startDate.value = range.start;
    endDate.value = range.end;
    editing.value = false;
    ignoreBlur.value = false;
    stopListeners();
};

const commit = () => {
    if (blurTimer) {
        clearTimeout(blurTimer);
        blurTimer = null;
    }

    let period = '';
    if (startDate.value && endDate.value) {
        period = formatTaskPeriodMd(startDate.value, endDate.value);
    } else if (startDate.value) {
        period = formatTaskPeriodMd(startDate.value, startDate.value);
    }

    editing.value = false;
    ignoreBlur.value = false;
    stopListeners();
    emit('update:modelValue', period);
    emit('commit', period);
};

const clear = () => {
    startDate.value = '';
    endDate.value = '';
    commit();
};

const onStartChange = () => {
    if (startDate.value && endDate.value && endDate.value < startDate.value) {
        endDate.value = startDate.value;
    }
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        closeWithoutSave();
    }
};

watch(
    () => props.modelValue,
    () => {
        if (! editing.value) {
            const range = periodToInputRange(props.modelValue);
            startDate.value = range.start;
            endDate.value = range.end;
        }
    },
);

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
            'is-placeholder': ! hasValue && !! placeholder,
        }"
        @click="open"
    >
        <div
            v-if="! editing"
            class="ui-sheet-display text-center"
            :class="{ 'text-slate-400': ! hasValue && !! placeholder }"
            :title="hasValue ? modelValue : 'Хугацаа сонгох'"
        >
            {{ displayText }}
        </div>

        <Teleport to="body">
            <div
                v-if="editing"
                ref="menuRef"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl ring-1 ring-slate-900/5"
                :style="menuStyle"
                @mousedown.prevent="ignoreBlur = true"
                @keydown="onKeydown"
            >
                <p class="mb-2 text-xs font-semibold text-slate-600">Хугацаа сонгох</p>
                <div class="grid gap-2 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-slate-400">Эхлэх</span>
                        <input
                            v-model="startDate"
                            type="date"
                            class="ui-input w-full text-sm"
                            @change="onStartChange"
                        />
                    </label>
                    <span class="hidden text-center text-slate-400 sm:block">—</span>
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-slate-400">Дуусах</span>
                        <input
                            v-model="endDate"
                            type="date"
                            class="ui-input w-full text-sm"
                            :min="startDate || undefined"
                        />
                    </label>
                </div>
                <div class="mt-3 flex items-center justify-between gap-2 border-t border-slate-100 pt-2">
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-50 hover:text-rose-600"
                        @mousedown.prevent="clear"
                    >
                        Цэвэрлэх
                    </button>
                    <span class="flex items-center gap-1.5">
                        <button
                            type="button"
                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                            @mousedown.prevent="closeWithoutSave"
                        >
                            Болих
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-brand-navy-600 px-3.5 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-navy-700"
                            @mousedown.prevent="commit"
                        >
                            Болсон
                        </button>
                    </span>
                </div>
            </div>
        </Teleport>
    </div>
</template>
