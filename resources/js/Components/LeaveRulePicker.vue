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
    /** Энэ мөрийн хоног — «Тохиолдол бүрт» заалтын зарцуулалт. */
    rowDays: { type: [Number, String], default: 0 },
    editable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'commit']);

const open = ref(false);
const rootRef = ref(null);
const menuRef = ref(null);

const selected = computed(() => props.rules.find((rule) => rule.code === props.modelValue) || null);

const isBlocked = (code) => props.blocked.includes(code);

/**
 * Зарцуулсан хоног.
 *
 * «Тохиолдол бүрт» заалтад хуримтлал байхгүй тул энэ мөрийн өөрийнх нь
 * хоногийг тооцно — нэг удаад хэд олгож байгаа нь тэр.
 */
const usedDays = (rule) => (rule.period === 'once'
    ? Math.max(0, Number(props.rowDays) || 0)
    : Number(props.usage[rule.code] ?? 0));

/** Хоног заагаагүй заалтад үлдэгдэл утгагүй. */
const hasCap = (rule) => Number(rule.days) > 0;

const leftDays = (rule) => (hasCap(rule) ? Math.max(0, rule.days - usedDays(rule)) : null);

/** «Жилд 5 хоног», «Тохиолдол бүрт 1 хоног», «Улиралд — хязгааргүй». */
const limitText = (rule) => (hasCap(rule)
    ? `${rule.period_label} ${rule.days} хоног`
    : `${rule.period_label} — хязгааргүй`);

/** Нүдний доор гарах богино тайлбар. */
const summary = computed(() => {
    const rule = selected.value;

    if (! rule) {
        return '';
    }

    if (! hasCap(rule)) {
        return limitText(rule);
    }

    return `${rule.days} хоногоос ${usedDays(rule)} авсан, ${leftDays(rule)} үлдсэн`;
});

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
    }
};

const stop = () => {
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
    document.addEventListener('keydown', onKeydown);
};

const pick = (code) => {
    // Лимит дүүрсэн заалтыг ч сонгоно — тэр чөлөө цалингүй болно.
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
            <!-- Дэлгэцийн голд — хүснэгтийн ирмэгээс хальж таслагдахгүй. -->
            <div
                v-if="open"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/30 p-4"
                @click.self="close"
            >
                <div
                    ref="menuRef"
                    class="ui-card flex max-h-[85vh] w-full max-w-5xl flex-col overflow-hidden shadow-2xl"
                >
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 px-3 py-2">
                    <p class="text-xs font-semibold text-slate-700">Хөдөлмөрийн дотоод журмын заалт</p>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-800" @click="pick('')">
                        Сонголтыг арилгах
                    </button>
                </div>

                <div class="ui-table-wrap min-h-0 flex-1 overflow-y-auto">
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
                                    'cursor-pointer hover:bg-brand-navy-50',
                                    isBlocked(rule.code) ? 'text-slate-500' : '',
                                    rule.code === modelValue ? 'bg-brand-navy-50 font-semibold' : '',
                                ]"
                                @click="pick(rule.code)"
                            >
                                <td class="whitespace-nowrap font-semibold">{{ rule.code }}</td>
                                <td class="!text-left">
                                    {{ rule.label.replace(rule.code + ' ', '') }}
                                    <span v-if="isBlocked(rule.code)" class="ml-1 whitespace-nowrap text-[10px] font-semibold text-rose-600">
                                        лимит дүүрсэн — цалингүй болно
                                    </span>
                                </td>
                                <td class="whitespace-nowrap text-center">{{ limitText(rule) }}</td>
                                <td class="text-center">{{ usedDays(rule) }}</td>
                                <td class="text-center">
                                    <span v-if="! hasCap(rule)" class="text-slate-400">хязгааргүй</span>
                                    <span v-else :class="leftDays(rule) === 0 ? 'font-semibold text-rose-600' : ''">
                                        {{ leftDays(rule) }}
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
            </div>
        </Teleport>
    </div>
</template>
