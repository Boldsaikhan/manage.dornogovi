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
    /** Биелэлтийн хураангуй: { count, done, pending, overdue, progress }. */
    stats: { type: Object, default: () => ({}) },
    statusFilter: { type: String, default: null },
});

const emit = defineEmits(['focus-task', 'show-tasks', 'toggle-status']);

/** Сарын мөрний баруун талд гарах хэрэгжилтийн шүүлтүүр. */
const STATUS_ICONS = {
    // Дууссан — чагт
    done: 'M5 12.5l4.5 4.5L19 7.5',
    // Эхлээгүй — цаг
    pending: 'M12 7.5v4.75l3 1.75',
    // Хэтэрсэн — анхааруулга
    overdue: 'M12 8v5m0 3.5v.01M10.3 3.9L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
};

const statusChips = computed(() => [
    {
        key: 'done',
        icon: STATUS_ICONS.done,
        label: 'Дууссан',
        count: props.stats.done ?? 0,
        active: 'bg-emerald-600 text-white',
        idle: 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
    },
    {
        key: 'pending',
        icon: STATUS_ICONS.pending,
        label: 'Эхлээгүй',
        count: props.stats.pending ?? 0,
        active: 'bg-orange-500 text-white',
        idle: 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100',
    },
    {
        key: 'overdue',
        icon: STATUS_ICONS.overdue,
        label: 'Хэтэрсэн',
        count: props.stats.overdue ?? 0,
        active: 'bg-red-600 text-white',
        idle: 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100',
    },
]);

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

const shiftYear = (delta) => {
    viewYear.value += delta;
};

/** Сарын хавчуурга дээр дарахад тэр сарын хуанли гарна. */
const selectMonth = (entry) => {
    viewMonth.value = entry.month;
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

/** Сонгосон сард хэдэн үүрэг хамаарч байгаа. */
const activeMonthCount = computed(
    () => monthlySummary.value[viewMonth.value]?.ids.length ?? 0,
);

const taskChipClass = (progress) => {
    const value = Number(progress ?? 0);
    if (value >= 100) return 'border-emerald-400 bg-emerald-50 text-emerald-800';
    if (value > 0) return 'border-amber-400 bg-amber-50 text-amber-900';

    return 'border-slate-300 bg-slate-50 text-slate-700';
};

const taskLabel = (task) => {
    const no = task.no ? `${task.no}. ` : '';
    const text = String(task.text ?? '').replace(/\s+/g, ' ').trim();
    if (!text) return `${no}Үүрэг`;

    return text.length > 42 ? `${no}${text.slice(0, 40)}…` : `${no}${text}`;
};

/** Долоо хоногийн амралтын багана (Бя, Ня). */
const isWeekend = (index) => index % 7 >= 5;

const focusTask = (task) => {
    emit('focus-task', task);
};

/** Сонгосон сарын үүргүүдийг хүснэгтээр харуулна. */
const showMonthTable = () => {
    const entry = monthlySummary.value[viewMonth.value];

    if (! entry || ! entry.ids.length) {
        return;
    }

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
    <div class="ui-card overflow-hidden">
        <!-- Толгой: нэрлэл, жилийн шилжилт, бүгдийг харах -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-gradient-to-r from-brand-navy-50 to-white px-3 py-1.5">
            <div>
                <h3 class="text-sm font-semibold text-brand-navy-900">Цаглалт / төлөвлөгөө</h3>
                <p class="text-xs text-slate-500">
                    Сар дээр дарж тухайн сарын хуанлийг харна (хугацааны жишээ: 08.01–09.30)
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-1 py-0.5">
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-base leading-none text-brand-navy-600 transition hover:bg-brand-navy-50"
                        aria-label="Өмнөх жил"
                        @click="shiftYear(-1)"
                    >
                        &lsaquo;
                    </button>
                    <span class="min-w-[3.25rem] text-center text-sm font-semibold text-brand-navy-800">
                        {{ viewYear }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-base leading-none text-brand-navy-600 transition hover:bg-brand-navy-50"
                        aria-label="Дараагийн жил"
                        @click="shiftYear(1)"
                    >
                        &rsaquo;
                    </button>
                </div>

                <button type="button" class="ui-btn-ghost px-3 py-1.5 text-xs" @click="goToday">
                    Өнөөдөр
                </button>

            </div>
        </div>

        <!-- Сарын хавчуурга + баруун талд хэрэгжилтийн тоо -->
        <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-2.5 py-1">
        <div class="flex min-w-0 flex-1 gap-1 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <button
                v-for="entry in monthlySummary"
                :key="'tab-' + entry.month"
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition"
                :class="entry.month === viewMonth
                    ? 'border-brand-navy-600 bg-brand-navy-600 text-white shadow-sm'
                    : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300 hover:text-brand-navy-700'"
                :title="entry.label + ' — хуанлийг харах'"
                @click="selectMonth(entry)"
            >
                {{ entry.label }}
                <span
                    v-if="entry.ids.length"
                    class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                    :class="entry.month === viewMonth
                        ? 'bg-white/20 text-white'
                        : 'bg-brand-orange-50 text-brand-orange-700'"
                >
                    {{ entry.ids.length }}
                </span>
            </button>
        </div>

            <!-- Хэрэгжилт — icon + тоо -->
            <div class="flex shrink-0 items-center gap-1">
                <button
                    v-for="chip in statusChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-xs font-bold transition"
                    :class="statusFilter === chip.key
                        ? chip.active + ' border-transparent shadow-sm'
                        : chip.idle"
                    :title="chip.label + ' — хүснэгтээр шүүх'"
                    :aria-label="chip.label"
                    @click="emit('toggle-status', chip.key)"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <circle v-if="chip.key === 'pending'" cx="12" cy="12" r="8.5" stroke-width="1.8" />
                        <path :d="chip.icon" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    {{ chip.count }}
                </button>

                <span
                    class="inline-flex items-center gap-1 rounded-full border border-brand-navy-200 bg-brand-navy-50 px-2 py-1 text-xs font-bold text-brand-navy-700"
                    :title="'Нийт ' + props.tasks.length + ' үүрэг'"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16M4 12h16M4 17.5h10" />
                    </svg>
                    {{ props.tasks.length }}
                </span>
            </div>
        </div>

        <!-- Сонгосон сар — баруун талд хэрэгжилт -->
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-2.5 py-1">
            <div class="flex items-center gap-1">
                <button
                    type="button"
                    class="rounded-lg px-2.5 py-1 text-base leading-none text-brand-navy-600 transition hover:bg-brand-navy-50"
                    aria-label="Өмнөх сар"
                    @click="shiftMonth(-1)"
                >
                    &lsaquo;
                </button>

                <button
                    type="button"
                    class="rounded-lg px-3 py-1 text-sm font-semibold text-brand-navy-800 transition"
                    :class="activeMonthCount ? 'hover:bg-brand-navy-50' : 'cursor-default'"
                    :title="activeMonthCount ? 'Энэ сарын үүргүүдийг хүснэгтээр харах' : ''"
                    @click="showMonthTable"
                >
                    {{ monthLabel }}
                    <span v-if="activeMonthCount" class="ml-1 text-xs font-normal text-slate-500">
                        · {{ activeMonthCount }} үүрэг
                    </span>
                </button>

                <button
                    type="button"
                    class="rounded-lg px-2.5 py-1 text-base leading-none text-brand-navy-600 transition hover:bg-brand-navy-50"
                    aria-label="Дараагийн сар"
                    @click="shiftMonth(1)"
                >
                    &rsaquo;
                </button>
            </div>
        </div>

        <div class="grid grid-cols-7 border-b border-slate-200 bg-brand-navy-50 text-center text-xs font-semibold uppercase tracking-wide text-brand-navy-500">
            <div
                v-for="(weekday, index) in MONGOLIAN_WEEKDAYS"
                :key="weekday"
                class="px-1 py-1"
                :class="index >= 5 ? 'text-brand-orange-600' : ''"
            >
                {{ weekday }}
            </div>
        </div>

        <div class="grid grid-cols-7">
            <div
                v-for="(cell, index) in cells"
                :key="cell.key"
                class="min-h-[4.25rem] border-b border-r border-slate-100 p-1 align-top transition last:border-r-0"
                :class="[
                    cell.inMonth
                        ? (isWeekend(index) ? 'bg-brand-orange-50/30' : 'bg-white')
                        : 'bg-slate-50/70',
                    cell.inMonth ? 'hover:bg-brand-navy-50/50' : '',
                ]"
            >
                <div class="mb-0.5 flex items-center justify-between gap-1">
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[11px] font-semibold"
                        :class="cell.key === todayKey
                            ? 'bg-brand-navy-600 text-white shadow-sm'
                            : (cell.inMonth ? 'text-slate-700' : 'text-slate-300')"
                    >
                        {{ cell.date.getDate() }}
                    </span>
                    <button
                        v-if="tasksOnDay(cell.key).length"
                        type="button"
                        class="inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-brand-orange-50 px-1 text-[10px] font-semibold text-brand-orange-700 transition hover:bg-brand-orange-100"
                        title="Хүснэгтээр харах"
                        @click="showDay(cell)"
                    >
                        {{ tasksOnDay(cell.key).length }}
                    </button>
                </div>

                <div class="space-y-0.5">
                    <button
                        v-for="task in tasksOnDay(cell.key).slice(0, 2)"
                        :key="task.id + '-' + cell.key"
                        type="button"
                        class="block w-full truncate rounded border-l-2 px-1.5 py-0.5 text-left text-[10px] leading-tight transition hover:brightness-95"
                        :class="taskChipClass(task.progress)"
                        :title="task.text"
                        @click="focusTask(task)"
                    >
                        {{ taskLabel(task) }}
                    </button>
                    <button
                        v-if="tasksOnDay(cell.key).length > 2"
                        type="button"
                        class="px-1 text-[10px] font-medium text-brand-navy-600 hover:underline"
                        @click="showDay(cell)"
                    >
                        +{{ tasksOnDay(cell.key).length - 2 }} бусад
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
</template>
