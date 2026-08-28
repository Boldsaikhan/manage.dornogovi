<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    maxHeightClass: { type: String, default: 'max-h-56' },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');

const selected = computed(() => props.modelValue ?? []);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = props.employees ?? [];

    if (! q) {
        return list;
    }

    return list.filter((e) => {
        const hay = [e.name, e.position, e.department, e.phone]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return hay.includes(q);
    });
});

const toggle = (id) => {
    const list = selected.value;
    emit('update:modelValue', list.includes(id) ? list.filter((x) => x !== id) : [...list, id]);
};

const clear = () => emit('update:modelValue', []);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <input
                v-model="search"
                type="search"
                placeholder="Нэр, албан тушаал, хэлтэс, утсаар хайх…"
                class="min-w-0 flex-1 rounded-md border border-brand-navy-200 px-3 py-1.5 text-sm"
            />
            <button
                type="button"
                class="rounded-md border border-brand-navy-200 px-3 py-1.5 text-xs text-slate-600 hover:bg-white"
                @click="clear"
            >
                Цэвэрлэх
            </button>
        </div>

        <p class="mt-2 text-xs text-slate-500">
            {{ selected.length ? selected.length + ' албан хаагч сонгогдсон' : 'Хэн ч сонгоогүй — цэсэнд харагдахгүй' }}
        </p>

        <div :class="['mt-2 overflow-y-auto rounded-lg border border-brand-navy-100 bg-white', maxHeightClass]">
            <p v-if="! filtered.length" class="px-3 py-4 text-center text-xs text-slate-400">
                Илэрц олдсонгүй.
            </p>
            <label
                v-for="e in filtered"
                :key="e.id"
                class="flex cursor-pointer items-center gap-2 border-b border-slate-50 px-3 py-2 text-sm last:border-0 hover:bg-brand-navy-50"
            >
                <input
                    type="checkbox"
                    class="rounded border-brand-navy-200 text-brand-orange-500"
                    :checked="selected.includes(e.id)"
                    @change="toggle(e.id)"
                />
                <span class="min-w-0 flex-1">
                    <span class="font-medium text-brand-navy-800">{{ e.name }}</span>
                    <span v-if="e.position || e.department" class="ml-1.5 text-xs text-brand-navy-300">
                        {{ [e.position, e.department].filter(Boolean).join(' · ') }}
                    </span>
                </span>
                <span v-if="e.phone" class="shrink-0 text-xs tabular-nums text-slate-400">{{ e.phone }}</span>
            </label>
        </div>
    </div>
</template>
