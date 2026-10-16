<script setup>
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';

/**
 * Журмын заалт сонгогч.
 *
 * Жагсаалт урт бөгөөд заалт бүр лимит, авсан, үлдсэн хоногтой тул
 * энгийн сонголтын оронд цэгцтэй хүснэгтээр харуулна.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    rules: { type: Array, default: () => [] },
    usage: { type: Object, default: () => ({}) },
    blocked: { type: Array, default: () => [] },
    editable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const open = ref(false);
const rootRef = ref(null);
const menuRef = ref(null);
const menuStyle = ref({});

const selected = computed(() => props.rules.find((rule) => rule.code === props.modelValue) || null);

const isBlocked = (code) => props.blocked.includes(code);

const usedDays = (rule) => (rule.period === 'once' ? null : Number(props.usage[rule.code] ?? 0));

const leftDays = (rule) => {
    const used = usedDays(rule);

    return used === null ? null : Math.max(0, rule.days - used);
};

/** «Жилд 5 хоног», «Тохиолдол бүрт 1 хоног», «Хоног заагаагүй». */
const limitText = (rule) => (rule.days
    ? `${rule.period_label} ${rule.days} хоног`
    : `${rule.period_label} — заагаагүй`);

/** Нүдний доор гарах богино тайлбар. */
const summary = computed(() => {
    const rule = selected.value;

    if (! rule) {
        return '';
    }

    const left = leftDays(rule);

    return left === null
        ? limitText(rule)
        : `${rule.days} хоногоос ${usedDays(rule)} авсан, ${left} үлдсэн`;
});

const place = () => {
    const el = rootRef.value;
    if (! el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const width = Math.min(960, window.innerWidth - 16);
    let left = rect.left;

    if (left + width > window.innerWidth - 8) {
        left = Math.max(8, window.innerWidth - width - 8);
    }

    const below = window.innerHeight - rect.bottom - 16;
    const upward = below < 260 && rect.top > below;

    menuStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        ...(upward
            ? { bottom: `${window.innerHeight - rect.top + 6}px` }
            : { top: `${rect.bottom + 6}px` }),
        width: `${width}px`,
        zIndex: 200,
    };
};

const onPointerDown = (event) => {
    const target = event.target;

    if (! (target instanceof Node)) {
        return;
    }

    if (rootRef.value?.contains(target) || menuRef.value?.contains(target)) {
        return;
    }

    close();
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

const stop = () => {
    window.removeEventListener('scroll', place, true);
    window.removeEventListener('resize', place);
    document.removeEventListener('pointerdown', onPointerDown, true);
    document.removeEventListener('keydown', onKeydown);
};

const close = () => {
    open.value = false;
    stop();
};

const toggle = async () => {
    if (! props.editable) {
        return;
    }

    if (open.value) {
        close();

        return;
    }

    open.value = true;
    await nextTick();
    place();
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    document.addEventListener('pointerdown', onPointerDown, true);
    document.addEventListener('keydown', onKeydown);
};

const pick = (code) => {
    if (code && isBlocked(code)) {
        return;
    }

    close();
    emit('update:modelValue', code);
    emit('commit', code);
};

onBeforeUnmount(stop);
</script>

<template>
    <div ref="rootRef" class="min-w-0">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-1 rounded px-1 py-0.5 text-left text-[11px] leading-tight transition"
            :class="editable ? 'hover:bg-sky-50' : 'cursor-default'"
            :title="selected ? selected.label : 'Журмын заалт сонгох'"
            @click="toggle"
        >
            <span class="ui-clamp-2 min-w-0">
                {{ selected ? selected.label : '— сонгох —' }}
            </span>
            <svg v-if="editable" class="h-3 w-3 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
        <span v-if="summary" class="mt-0.5 block text-[10px] leading-tight text-slate-500">
            {{ summary }}
        </span>

        <Teleport to="body">
            <div
                v-if="open"
                ref="menuRef"
                class="ui-card overflow-hidden shadow-2xl"
                :style="menuStyle"
            >
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                    <p class="text-xs font-semibold text-slate-700">Хөдөлмөрийн дотоод журмын заалт</p>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-800" @click="pick('')">
                        Сонголтыг арилгах
                    </button>
                </div>

                <div class="ui-table-wrap max-h-[22rem] overflow-y-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-16">№</th>
                                <th>Чөлөөний төрөл</th>
                                <th class="w-36 text-center">Лимит</th>
                                <th class="w-20 text-center">Авсан</th>
                                <th class="w-20 text-center">Үлдсэн</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rule in rules"
                                :key="rule.code"
                                class="transition"
                                :class="[
                                    isBlocked(rule.code)
                                        ? 'cursor-not-allowed text-slate-400'
                                        : 'cursor-pointer hover:bg-brand-navy-50',
                                    rule.code === modelValue ? 'bg-brand-navy-50 font-semibold' : '',
                                ]"
                                @click="pick(rule.code)"
                            >
                                <td class="whitespace-nowrap font-semibold">{{ rule.code }}</td>
                                <td class="!text-left">
                                    {{ rule.label.replace(rule.code + ' ', '') }}
                                    <span v-if="isBlocked(rule.code)" class="ml-1 whitespace-nowrap text-[10px] font-semibold text-rose-600">
                                        лимит дүүрсэн
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-center">{{ limitText(rule) }}</td>
                                <td class="text-center">{{ usedDays(rule) === null ? '—' : usedDays(rule) }}</td>
                                <td class="text-center">
                                    <span :class="leftDays(rule) === 0 ? 'font-semibold text-rose-600' : ''">
                                        {{ leftDays(rule) === null ? '—' : leftDays(rule) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="! rules.length">
                                <td colspan="5" class="!py-8 text-center text-slate-400">
                                    «Чөлөөний лимит» цэснээс заалт нэмнэ үү.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Teleport>
    </div>
</template>
