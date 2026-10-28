<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    unitId: { type: Number, default: null },
    periods: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});

const SLOT_LABELS = { half: 'хагас жил', year: 'жилийн эцэс' };

/** Нүднүүдийн түр хуулбар — засаж байхад шууд харагдана. */
const drafts = reactive({});

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    props.rows.forEach((row) => {
        drafts[row.id] = {
            title: row.title ?? '',
            measure_unit: row.measure_unit ?? '',
            target: row.target ?? '',
            note: row.note ?? '',
            values: { ...row.values },
        };
    });
};

syncDrafts();
watch(() => props.rows, syncDrafts, { deep: true });

/** Толгойн доод мөрөнд хэдэн нүд гарах вэ. */
const valueColumnCount = computed(
    () => props.periods.reduce((sum, p) => sum + p.slots.length, 0),
);

const totalColumns = computed(() => 5 + valueColumnCount.value + (props.canEdit ? 1 : 0));

const switchUnit = (id) => {
    router.get(route('indicators.index'), { unit: id }, {
        preserveState: false,
        preserveScroll: true,
    });
};

const addRow = () => {
    router.post(route('indicators.store'), { unit: props.unitId }, { preserveScroll: true });
};

const removeRow = (row) => {
    if (! confirm('Энэ мөрийг устгах уу?')) return;

    router.delete(route('indicators.destroy', row.id), { preserveScroll: true });
};

const saveField = (row, field) => {
    const value = drafts[row.id]?.[field] ?? '';

    if (value === (row[field] ?? '')) return;

    router.patch(route('indicators.update', row.id), { [field]: value }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const cellKey = (period, slot) => `${period.id}:${slot}`;

const saveValue = (row, period, slot) => {
    const key = cellKey(period, slot);
    const value = drafts[row.id]?.values?.[key] ?? '';

    if (value === (row.values?.[key] ?? '')) return;

    router.patch(route('indicators.value', row.id), {
        period_id: period.id,
        slot,
        value,
    }, { preserveScroll: true, preserveState: true });
};

/* ── Жилийн багана удирдах ───────────────────────────────────────── */

const showPeriods = ref(false);
const newPeriod = reactive({ label: '', split: false });

const addPeriod = () => {
    const label = newPeriod.label.trim();

    if (! label) return;

    const year = Number.parseInt(label, 10);

    router.post(route('indicators.periods.store'), {
        label,
        year: Number.isNaN(year) ? null : year,
        split: newPeriod.split,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newPeriod.label = '';
            newPeriod.split = false;
        },
    });
};

const toggleSplit = (period) => {
    router.patch(route('indicators.periods.update', period.id), {
        split: ! period.split,
    }, { preserveScroll: true });
};

const removePeriod = (period) => {
    if (! confirm(`«${period.label}» баганыг устгах уу? Тухайн баганад бичсэн бүх утга хамт устана.`)) {
        return;
    }

    router.delete(route('indicators.periods.destroy', period.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Салбарын үзүүлэлт" />

    <AuthenticatedLayout title="Салбарын үзүүлэлт">
        <div class="ui-page ui-page--tight">
            <!-- Табууд — эрх зүйн актын хуудастай нэг эгнээнд -->
            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <span class="pl-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    Хууль тогтоомж
                </span>
                <Link
                    :href="route('legal-acts.index')"
                    class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-50"
                >
                    Эрх зүйн акт, тогтоол
                </Link>

                <span class="mx-1 h-5 w-px bg-slate-200" />

                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
                    Салбарын үзүүлэлт
                </span>
                <span class="shrink-0 rounded-xl bg-brand-navy-600 px-3.5 py-2 text-sm font-semibold text-white shadow-md shadow-brand-navy-600/20">
                    Суурь мэдээлэл
                    <span class="ml-1 text-xs opacity-70">{{ rows.length }}</span>
                </span>

                <div class="ml-auto flex items-center gap-1.5">
                    <select
                        v-if="canManage && units.length"
                        class="ui-input !w-auto !py-1.5 text-sm"
                        :value="unitId"
                        @change="switchUnit($event.target.value)"
                    >
                        <option v-for="unit in units" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>

                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-icon-btn"
                        title="Жилийн багана удирдах"
                        aria-label="Жилийн багана"
                        @click="showPeriods = true"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16M4 12h16M4 17.5h16M9 4v16" />
                        </svg>
                    </button>

                    <button
                        v-if="canEdit"
                        type="button"
                        class="ui-icon-btn border-transparent bg-brand-orange-500 text-white hover:bg-brand-orange-600 hover:text-white"
                        title="Үзүүлэлт нэмэх"
                        aria-label="Үзүүлэлт нэмэх"
                        @click="addRow"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Жилийн багана тохируулаагүй бол -->
            <div v-if="! periods.length" class="ui-card-pad text-center">
                <p class="text-sm font-semibold text-slate-700">Жилийн багана алга.</p>
                <p class="mt-1 text-xs text-slate-500">
                    <template v-if="canManage">
                        Дээрх багана удирдах товчоор «2021 он», «2024 он» гэх мэт баганаа нэмнэ үү.
                    </template>
                    <template v-else>
                        Админ жилийн баганыг тохируулсны дараа мэдээлэл оруулна.
                    </template>
                </p>
            </div>

            <TableScrollViewport v-else>
                <table class="ui-table ui-register__table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="w-12 text-center">№</th>
                            <th rowspan="2" class="min-w-[22rem] text-left">
                                Үзүүлэлтийн дэлгэрэнгүй мэдээлэл
                            </th>
                            <th rowspan="2" class="w-28 text-center">Хэмжих нэгж</th>
                            <th rowspan="2" class="w-28 text-center">Хүрэх түвшин</th>
                            <th
                                v-for="period in periods"
                                :key="'p-' + period.id"
                                :colspan="period.slots.length"
                                class="text-center"
                            >
                                {{ period.label }}
                            </th>
                            <th rowspan="2" class="min-w-[12rem] text-left">Тэмдэглэл</th>
                            <th v-if="canEdit" rowspan="2" class="w-12" />
                        </tr>
                        <tr>
                            <template v-for="period in periods" :key="'s-' + period.id">
                                <th
                                    v-for="slot in period.slots"
                                    :key="period.id + '-' + slot"
                                    class="w-24 text-center !text-[10px] !font-semibold !normal-case"
                                >
                                    {{ period.split ? SLOT_LABELS[slot] : '&nbsp;' }}
                                </th>
                            </template>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td class="ui-register__cell--no text-center">{{ row.no }}</td>

                            <td>
                                <textarea
                                    v-if="canEdit && drafts[row.id]"
                                    v-model="drafts[row.id].title"
                                    rows="1"
                                    class="w-full resize-y rounded border-0 bg-transparent px-1 py-0.5 text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    placeholder="Үзүүлэлтийн нэр…"
                                    @blur="saveField(row, 'title')"
                                />
                                <span v-else class="text-sm">{{ row.title }}</span>
                            </td>

                            <td class="text-center">
                                <input
                                    v-if="canEdit && drafts[row.id]"
                                    v-model="drafts[row.id].measure_unit"
                                    class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @blur="saveField(row, 'measure_unit')"
                                />
                                <span v-else class="text-sm">{{ row.measure_unit }}</span>
                            </td>

                            <td class="text-center">
                                <input
                                    v-if="canEdit && drafts[row.id]"
                                    v-model="drafts[row.id].target"
                                    class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm font-semibold focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @blur="saveField(row, 'target')"
                                />
                                <span v-else class="text-sm font-semibold">{{ row.target }}</span>
                            </td>

                            <template v-for="period in periods" :key="'v-' + period.id">
                                <td
                                    v-for="slot in period.slots"
                                    :key="row.id + '-' + period.id + '-' + slot"
                                    class="text-center"
                                >
                                    <input
                                        v-if="canEdit && drafts[row.id]"
                                        v-model="drafts[row.id].values[cellKey(period, slot)]"
                                        class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-center text-sm tabular-nums focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                        @blur="saveValue(row, period, slot)"
                                    />
                                    <span v-else class="text-sm tabular-nums">
                                        {{ row.values[cellKey(period, slot)] }}
                                    </span>
                                </td>
                            </template>

                            <td>
                                <input
                                    v-if="canEdit && drafts[row.id]"
                                    v-model="drafts[row.id].note"
                                    class="w-full rounded border-0 bg-transparent px-1 py-0.5 text-sm focus:bg-white focus:ring-1 focus:ring-brand-navy-400"
                                    @blur="saveField(row, 'note')"
                                />
                                <span v-else class="text-sm">{{ row.note }}</span>
                            </td>

                            <td v-if="canEdit" class="text-center">
                                <button
                                    type="button"
                                    class="ui-icon-btn ui-icon-btn--danger"
                                    title="Мөрийг устгах"
                                    aria-label="Устгах"
                                    @click="removeRow(row)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" />
                                    </svg>
                                </button>
                            </td>
                        </tr>

                        <tr v-if="! rows.length">
                            <td :colspan="totalColumns" class="ui-register__empty !py-12 text-center text-slate-400">
                                Одоогоор үзүүлэлт бүртгэгдээгүй.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </TableScrollViewport>
        </div>

        <!-- Жилийн багана удирдах -->
        <Modal :show="showPeriods" max-width="lg" @close="showPeriods = false">
            <div class="ui-card-pad space-y-4">
                <div>
                    <h3 class="ui-title text-base">Жилийн багана</h3>
                    <p class="ui-subtitle mt-1">
                        Багана бүр нэг жил. «Хагас жил» сонговол тухайн жил
                        «хагас жил / жилийн эцэс» гэж хоёр нүдтэй болно.
                    </p>
                </div>

                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="period in periods"
                        :key="'m-' + period.id"
                        class="flex items-center gap-2 py-2"
                    >
                        <span class="min-w-0 flex-1 text-sm font-semibold text-brand-navy-800">
                            {{ period.label }}
                        </span>

                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-navy-600"
                                :checked="period.split"
                                @change="toggleSplit(period)"
                            />
                            Хагас жилтэй
                        </label>

                        <button
                            type="button"
                            class="ui-icon-btn ui-icon-btn--danger"
                            title="Баганыг устгах"
                            aria-label="Устгах"
                            @click="removePeriod(period)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" />
                            </svg>
                        </button>
                    </li>
                    <li v-if="! periods.length" class="py-3 text-sm text-slate-500">
                        Багана алга.
                    </li>
                </ul>

                <div class="flex flex-wrap items-end gap-2 border-t border-slate-200 pt-3">
                    <label class="min-w-0 flex-1">
                        <span class="ui-label">Шинэ багана</span>
                        <input
                            v-model="newPeriod.label"
                            class="ui-input"
                            placeholder="2026 он"
                            @keyup.enter="addPeriod"
                        />
                    </label>

                    <label class="flex items-center gap-1.5 pb-2 text-xs text-slate-600">
                        <input
                            v-model="newPeriod.split"
                            type="checkbox"
                            class="rounded border-slate-300 text-brand-navy-600"
                        />
                        Хагас жилтэй
                    </label>

                    <button type="button" class="ui-btn-primary" @click="addPeriod">Нэмэх</button>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="ui-btn-ghost" @click="showPeriods = false">Хаах</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
