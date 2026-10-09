<script setup>
import { computed, ref } from 'vue';
import {
    MONGOLIAN_MONTHS,
    MONGOLIAN_WEEKDAYS,
    calendarCells,
    dateKey,
    parseTaskPeriod,
    tasksForCalendarMonth,
} from '@/utils/taskPeriod';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

const emit = defineEmits(['focus-task', 'show-tasks']);

const today = new Date();
const viewYear = ref(today.getFullYear());
const viewMonth = ref(today.getMonth());

const monthLabel = computed(() => `${viewYear.value} оны ${MONGOLIAN_MONTHS[viewMonth.value]}`);

const cells = computed(() => calendarCells(viewYear.value, viewMonth.value));

const monthTasks = computed(() => tasksForCalendarMonth(props.tasks, viewYear.value, viewMonth.value));

const todayKey = dateKey(today);

const shiftMonth = (delta) => {
    const next = new Date(viewYear.value, viewMonth.value + delta, 1);
    viewYear.value = next.getFullYear();
    viewMonth.value = next.getMonth();
};

const goToday = () => {
    viewYear.value = today.getFullYear();
    viewMonth.value = today.getMonth();
};

const tasksOnDay = (key) => monthTasks.value.byDay.get(key) ?? [];

/**
 * Тухайн оны сар тус бүрд хамаарах үүргүүд.
 *
 * Хугацааны мөр хэд хэдэн сарыг хөндөж байвал тэр бүх саранд тооцогдоно.
 */
const monthlySummary = computed(() => MONGOLIAN_MONTHS.map((label, month) => {
    const ids = [];
    const from = new Date(viewYear.value, month, 1).getTime();
    const to = new Date(viewYear.value, month + 1, 0).getTime();

    props.tasks.forEach((task) => {
        const parsed = parseTaskPeriod(task.period, viewYear.value);
        if (! parsed || parsed.unparsed || ! parsed.start || ! parsed.end) {
            return;
        }

        if (parsed.start.getTime() <= to && parsed.end.getTime() >= from) {
            ids.push(task.id);
        }
    });

    return { month, label, ids };
}));

const taskChipClass = (progress) => {
    const value = Number(progress ?? 0);
    if (value >= 100) return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (value > 0) return 'bg-amber-50 text-amber-900 border-amber-200';

    return 'bg-slate-100 text-slate-700 border-slate-200';
};

const taskLabel = (task) => {
    const no = task.no ? `${task.no}. ` : '';
    const text = String(task.text ?? '').replace(/\s+/g, ' ').trim();
    if (!text) return `${no}Үүрэг`;

    return text.length > 42 ? `${no}${text.slice(0, 40)}…` : `${no}${text}`;
};

const focusTask = (task) => {
    emit('focus-task', task);
};

/** Бүх үүрэг даалгаврыг хүснэгтээр харуулна. */
const showAll = () => {
    emit('show-tasks', { label: null, ids: null });
};

/** Тухайн сарын үүргүүдийг хүснэгтээр харуулна. */
const showMonth = (entry) => {
    if (! entry.ids.length) {
        return;
    }

    viewMonth.value = entry.month;

    emit('show-tasks', {
        label: `${viewYear.value} оны ${entry.label}`,
        ids: entry.ids,
    });
};

/** Тухайн өдрийн үүргүүдийг хүснэгтээр харуулна. */
const showDay = (cell) => {
    const list = tasksOnDay(cell.key);
    if (! list.length) {
        return;
    }

    emit('show-tasks', {
        label: `${monthLabel.value} ${cell.date.getDate()}`,
        ids: list.map((task) => task.id),
    });
};
</script>

<template>
    <div class="ui-page">
        <!-- Эхний багана — бүх үүрэг даалгавар, дараагийнх — сар тус бүрийн тоо -->
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">Бүх үүрэг даалгавар</th>
                        <th
                            v-for="entry in monthlySummary"
                            :key="'head-' + entry.month"
                            class="whitespace-nowrap text-center"
                        >
                            {{ entry.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="whitespace-nowrap">
                            <button
                                type="button"
                                class="ui-btn-accent px-3 py-1.5 text-sm"
                                title="Бүх үүрэг даалгаврыг хүснэгтээр харах"
                                @click="showAll"
                            >
                                Нийт {{ props.tasks.length }} — бүгдийг харах
                            </button>
                        </td>
                        <td
                            v-for="entry in monthlySummary"
                            :key="'count-' + entry.month"
                            class="text-center"
                        >
                            <button
                                v-if="entry.ids.length"
                                type="button"
                                class="ui-btn-ghost px-3 py-1.5 text-sm font-semibold"
                                :class="entry.month === viewMonth ? 'border-brand-navy-400 text-brand-navy-700' : ''"
                                :title="entry.label + ' — хүснэгтээр харах'"
                                @click="showMonth(entry)"
                            >
                                {{ entry.ids.length }}
                            </button>
                            <span v-else class="text-sm text-slate-300">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="ui-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Цаглалт / төлөвлөгөө</h3>
                    <p class="text-xs text-slate-500">Хугацааны мөрөөр сарын хуанли дээр харуулна (жишээ: 08.01–09.30)</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="ui-btn-ghost px-2.5 py-1.5 text-sm" @click="shiftMonth(-1)" aria-label="Өмнөх сар">
                        &lsaquo;
                    </button>
                    <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-800">{{ monthLabel }}</span>
                    <button type="button" class="ui-btn-ghost px-2.5 py-1.5 text-sm" @click="shiftMonth(1)" aria-label="Дараагийн сар">
                        &rsaquo;
                    </button>
                    <button type="button" class="ui-btn-ghost px-2.5 py-1.5 text-xs" @click="goToday">
                        Өнөөдөр
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-7 border-b border-slate-200 bg-brand-navy-50/60 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <div v-for="weekday in MONGOLIAN_WEEKDAYS" :key="weekday" class="px-1 py-2">
                    {{ weekday }}
                </div>
            </div>

            <div class="grid grid-cols-7">
                <div
                    v-for="cell in cells"
                    :key="cell.key"
                    class="min-h-[6.5rem] border-b border-r border-slate-100 p-1.5 align-top last:border-r-0"
                    :class="cell.inMonth ? 'bg-white' : 'bg-slate-50/70'"
                >
                    <div class="mb-1 flex items-center justify-between gap-1">
                        <span
                            class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1 text-xs font-semibold"
                            :class="cell.key === todayKey
                                ? 'bg-brand-navy-600 text-white'
                                : (cell.inMonth ? 'text-slate-700' : 'text-slate-400')"
                        >
                            {{ cell.date.getDate() }}
                        </span>
                        <button
                            v-if="tasksOnDay(cell.key).length"
                            type="button"
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-brand-orange-50 px-1 text-[10px] font-semibold text-brand-orange-700 transition hover:bg-brand-orange-100"
                            title="Хүснэгтээр харах"
                            @click="showDay(cell)"
                        >
                            {{ tasksOnDay(cell.key).length }}
                        </button>
                    </div>

                    <div class="space-y-0.5">
                        <button
                            v-for="task in tasksOnDay(cell.key).slice(0, 3)"
                            :key="task.id + '-' + cell.key"
                            type="button"
                            class="block w-full truncate rounded border px-1.5 py-0.5 text-left text-[10px] leading-tight transition hover:brightness-95"
                            :class="taskChipClass(task.progress)"
                            :title="task.text"
                            @click="focusTask(task)"
                        >
                            {{ taskLabel(task) }}
                        </button>
                        <button
                            v-if="tasksOnDay(cell.key).length > 3"
                            type="button"
                            class="px-1 text-[10px] font-medium text-brand-navy-600 hover:underline"
                            @click="showDay(cell)"
                        >
                            +{{ tasksOnDay(cell.key).length - 3 }} бусад
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="monthTasks.unscheduled.length" class="border-t border-slate-200 bg-slate-50/80 px-4 py-3">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Хугацаа тодорхойгүй эсвэл уншиж чадаагүй
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="task in monthTasks.unscheduled"
                        :key="'unscheduled-' + task.id"
                        type="button"
                        class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-700 transition hover:border-brand-navy-300"
                        :title="task.period || task.text"
                        @click="focusTask(task)"
                    >
                        <span class="font-medium text-slate-500">{{ task.period || '—' }}:</span>
                        {{ taskLabel(task) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
