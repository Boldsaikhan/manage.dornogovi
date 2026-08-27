<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    tab: { type: String, default: 'state_high' },
    tabs: { type: Array, default: () => [] },
    subtype: { type: String, default: '' },
    subtypes: { type: Array, default: () => [] },
    year: { type: Number, default: null },
    years: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    categories: { type: Object, default: () => ({}) },
    allSubtypes: { type: Object, default: () => ({}) },
    categorySubtypes: { type: Object, default: () => ({}) },
});

const drafts = reactive({});

const numberFields = new Set([
    'age', 'years_in_country', 'years_in_sector', 'total_years', 'position_years',
]);

const draftFields = computed(() => {
    const fields = props.columns
        .filter((col) => ! col.readonly && col.field)
        .map((col) => col.field);

    return [...new Set(fields)];
});

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);

    props.rows.forEach((row) => {
        drafts[row.id] = Object.fromEntries(
            draftFields.value.map((field) => [field, row[field] ?? '']),
        );
    });
};

watch(() => [props.rows, props.columns], syncDrafts, { immediate: true, deep: true });

const queryParams = (overrides = {}) => {
    const params = {
        tab: props.tab,
        ...(props.subtype ? { subtype: props.subtype } : {}),
        ...(props.year ? { year: props.year } : {}),
        ...overrides,
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === '' || params[key] === null || params[key] === undefined) {
            delete params[key];
        }
    });

    return params;
};

const switchTab = (value) => {
    router.get(route('awards.index'), { tab: value }, { preserveState: false, preserveScroll: true });
};

const switchSubtype = (value) => {
    router.get(route('awards.index'), queryParams({ subtype: value || undefined }), {
        preserveState: false,
        preserveScroll: true,
    });
};

const switchYear = (value) => {
    const year = value === '' || value === 'all' ? undefined : Number(value);
    router.get(route('awards.index'), queryParams({ year }), {
        preserveState: false,
        preserveScroll: true,
    });
};

const exportUrl = computed(() => route('awards.export', queryParams()));

const defaultSubtype = () => {
    if (props.subtype) {
        return props.subtype;
    }

    const list = props.categorySubtypes[props.tab] || [];

    return list[0] || '';
};

const addRow = () => {
    const payload = {
        category: props.tab,
        year: props.year || new Date().getFullYear(),
    };

    const subtype = defaultSubtype();
    if (subtype) {
        payload.subtype = subtype;
    }

    useForm(payload).post(route('awards.store'), { preserveScroll: true });
};

const saveField = (id, field, value) => {
    let next = value;

    if (numberFields.has(field)) {
        if (next === '' || next === null || next === undefined) {
            next = null;
        } else {
            const n = Number.parseInt(String(next), 10);
            next = Number.isNaN(n) ? null : n;
        }
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next.trim();
    }

    if (drafts[id] && Object.prototype.hasOwnProperty.call(drafts[id], field)) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('awards.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const destroyRow = (id) => {
    if (! confirm('Устгах уу?')) {
        return;
    }

    router.delete(route('awards.destroy', id), { preserveScroll: true });
};

const cellValue = (row, key) => {
    const value = row[key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return value;
};

const fieldFor = (col) => col.field || col.key;

const isEditable = (col) => props.canManage && ! col.readonly && col.field;

const pageTitle = computed(() => props.categories[props.tab] || 'Шагнал');

const activeSubtypeLabel = computed(() => {
    if (! props.subtype) {
        return 'Бүгд';
    }

    return props.subtypes.find((item) => item.value === props.subtype)?.label || props.subtype;
});

const rowCountLabel = computed(() => `${props.rows.length} мөр`);

const fitsViewport = computed(() => (
    props.tab === 'governor_honor'
    || props.tab === 'governor_leading'
    || props.tab === 'other'
));

const fitColPercents = computed(() => {
    if (props.tab === 'other') {
        return ['3%', '9%', '7%', '7%', '8%', '9%', '14%', '5%', '6%', '9%', '11%', '8%'];
    }

    return ['3%', '8%', '8%', '9%', '11%', '18%', '6%', '8%', '10%', '13%'];
});
</script>

<template>
    <AuthenticatedLayout title="Шагнал">
        <div class="awards-page ui-page">
            <section class="awards-hero">
                <div class="awards-hero__copy">
                    <p class="awards-hero__eyebrow">Бүртгэл</p>
                    <h2 class="awards-hero__title">Шагнал</h2>
                    <p class="awards-hero__subtitle">
                        Төрийн дээд, аймгийн Засаг даргын болон бусад шагналын бүртгэл.
                    </p>
                </div>

                <div class="awards-hero__actions">
                    <a :href="exportUrl" class="awards-btn awards-btn--ghost">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Excel татах
                    </a>
                    <button
                        v-if="canManage"
                        type="button"
                        class="awards-btn awards-btn--primary"
                        @click="addRow"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round" />
                        </svg>
                        Шинэ нэмэх
                    </button>
                </div>
            </section>

            <section class="awards-toolbar">
                <div class="awards-toolbar__tabs">
                    <button
                        v-for="item in tabs"
                        :key="item.value"
                        type="button"
                        class="awards-tab"
                        :class="{ 'awards-tab--active': tab === item.value }"
                        @click="switchTab(item.value)"
                    >
                        <span class="awards-tab__label">{{ item.label }}</span>
                        <span class="awards-tab__count">{{ item.count }}</span>
                    </button>
                </div>

                <div v-if="subtypes.length || years.length" class="awards-toolbar__filters">
                    <div v-if="subtypes.length" class="awards-pills">
                        <button
                            type="button"
                            class="awards-pill"
                            :class="{ 'awards-pill--active': !subtype }"
                            @click="switchSubtype('')"
                        >
                            Бүгд
                        </button>
                        <button
                            v-for="item in subtypes"
                            :key="item.value"
                            type="button"
                            class="awards-pill"
                            :class="{ 'awards-pill--active': subtype === item.value }"
                            @click="switchSubtype(item.value)"
                        >
                            {{ item.label }}
                        </button>
                    </div>

                    <label class="awards-year">
                        <span class="awards-year__label">Он</span>
                        <select
                            class="awards-year__select"
                            :value="year ?? 'all'"
                            @change="switchYear($event.target.value)"
                        >
                            <option value="all">Бүгд</option>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            <option v-if="year && !years.includes(year)" :value="year">{{ year }}</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="awards-sheet">
                <div class="awards-sheet__meta">
                    <div>
                        <h3 class="awards-sheet__title">{{ pageTitle }}</h3>
                        <p class="awards-sheet__hint">
                            <span v-if="subtypes.length">{{ activeSubtypeLabel }}</span>
                            <span v-if="subtypes.length && year"> · </span>
                            <span v-if="year">{{ year }} он</span>
                            <span v-if="!subtypes.length && !year">Бүртгэлийн хүснэгт</span>
                        </p>
                    </div>
                    <div class="awards-sheet__stats">
                        <span class="awards-sheet__stat">{{ rowCountLabel }}</span>
                        <span v-if="canManage" class="awards-sheet__stat awards-sheet__stat--muted">Нүд дээр шууд засна</span>
                    </div>
                </div>

                <div
                    class="awards-sheet__scroll"
                    :class="fitsViewport ? 'awards-sheet__scroll--fit' : 'awards-sheet__scroll--wide'"
                >
                    <table
                        class="awards-grid"
                        :class="fitsViewport ? 'awards-grid--fit table-fixed' : 'awards-grid--wide min-w-[1500px]'"
                    >
                        <colgroup>
                            <col
                                v-for="(col, index) in columns"
                                :key="`col-${col.key}`"
                                :style="fitsViewport
                                    ? { width: fitColPercents[index] }
                                    : (col.width ? { width: col.width } : undefined)"
                            />
                            <col v-if="canManage" :style="{ width: fitsViewport ? '2.25rem' : '2.75rem' }" />
                        </colgroup>

                        <thead>
                            <tr>
                                <th
                                    v-for="col in columns"
                                    :key="col.key"
                                    class="awards-grid__head"
                                >
                                    <template v-if="col.lines?.length">
                                        <span
                                            v-for="(line, idx) in col.lines"
                                            :key="idx"
                                            class="awards-grid__head-line"
                                        >{{ line }}</span>
                                    </template>
                                    <span v-else class="awards-grid__head-line">{{ col.label }}</span>
                                </th>
                                <th v-if="canManage" class="awards-grid__head awards-grid__head--action" />
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-if="!rows.length">
                                <td
                                    :colspan="columns.length + (canManage ? 1 : 0)"
                                    class="awards-grid__empty"
                                >
                                    <div class="awards-empty">
                                        <div class="awards-empty__icon">📋</div>
                                        <p class="awards-empty__title">Бүртгэл алга</p>
                                        <p class="awards-empty__text">
                                            <template v-if="canManage">
                                                «Шинэ нэмэх» товчоор мөр нэмж, хүснэгтэд шууд бөглөнө.
                                            </template>
                                            <template v-else>
                                                {{ pageTitle }} бүртгэл одоогоор хоосон байна.
                                            </template>
                                        </p>
                                    </div>
                                </td>
                            </tr>

                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="awards-grid__row"
                            >
                                <td
                                    v-for="col in columns"
                                    :key="`${row.id}-${col.key}`"
                                    class="awards-grid__cell"
                                    :class="{
                                        'awards-grid__cell--index': col.key === 'no',
                                        'awards-grid__cell--editable': isEditable(col),
                                    }"
                                >
                                    <div
                                        v-if="col.readonly || col.key === 'no'"
                                        class="awards-grid__readonly"
                                    >
                                        {{ row.no }}
                                    </div>

                                    <template v-else-if="isEditable(col) && drafts[row.id]">
                                        <select
                                            v-if="col.input === 'gender'"
                                            v-model="drafts[row.id][fieldFor(col)]"
                                            class="awards-grid__input text-center"
                                            @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                        >
                                            <option value="" />
                                            <option value="эр">эр</option>
                                            <option value="эм">эм</option>
                                        </select>

                                        <input
                                            v-else-if="col.input === 'date'"
                                            v-model="drafts[row.id][fieldFor(col)]"
                                            type="date"
                                            class="awards-grid__input"
                                            @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                        />

                                        <input
                                            v-else-if="col.input === 'number'"
                                            v-model="drafts[row.id][fieldFor(col)]"
                                            type="number"
                                            min="0"
                                            class="awards-grid__input text-center"
                                            @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                        />

                                        <textarea
                                            v-else-if="col.multiline"
                                            v-model="drafts[row.id][fieldFor(col)]"
                                            rows="2"
                                            class="awards-grid__textarea"
                                            @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                        />

                                        <input
                                            v-else
                                            v-model="drafts[row.id][fieldFor(col)]"
                                            type="text"
                                            class="awards-grid__input"
                                            @change="saveField(row.id, fieldFor(col), drafts[row.id][fieldFor(col)])"
                                        />
                                    </template>

                                    <div
                                        v-else
                                        class="awards-grid__readonly"
                                        :class="col.key === 'no' ? 'text-center' : ''"
                                    >
                                        <span
                                            class="ui-clamp-2"
                                            :title="String(cellValue(row, col.key))"
                                        >{{ cellValue(row, col.key) }}</span>
                                    </div>
                                </td>

                                <td v-if="canManage" class="awards-grid__cell awards-grid__cell--action">
                                    <button
                                        type="button"
                                        class="awards-grid__delete"
                                        title="Устгах"
                                        @click="destroyRow(row.id)"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.awards-page {
    --awards-grid: #111827;
    --awards-grid-strong: #111827;
    --awards-head: #f8fafc;
    --awards-head-text: #000000;
}

.awards-hero {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 1.25rem;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    box-shadow: 0 10px 30px rgb(15 23 42 / 0.04);
}

.awards-hero__eyebrow {
    margin: 0;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #64748b;
}

.awards-hero__title {
    margin: 0.25rem 0 0;
    font-size: 1.75rem;
    line-height: 1.1;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: #0f2f63;
}

.awards-hero__subtitle {
    margin: 0.5rem 0 0;
    max-width: 42rem;
    font-size: 0.875rem;
    line-height: 1.5;
    color: #64748b;
}

.awards-hero__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.625rem;
}

.awards-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 2.625rem;
    padding: 0.625rem 1rem;
    border-radius: 0.875rem;
    font-size: 0.875rem;
    font-weight: 700;
    transition: all 0.15s ease;
}

.awards-btn--ghost {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.awards-btn--ghost:hover {
    border-color: #93c5fd;
    background: #f8fafc;
    color: #1e3a8a;
}

.awards-btn--primary {
    border: 0;
    background: linear-gradient(180deg, #f97316 0%, #ea580c 100%);
    color: #fff;
    box-shadow: 0 10px 24px rgb(249 115 22 / 0.24);
}

.awards-btn--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 28px rgb(249 115 22 / 0.28);
}

.awards-toolbar {
    display: grid;
    gap: 0.875rem;
    padding: 1rem;
    border: 1px solid rgb(226 232 240 / 0.9);
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 8px 24px rgb(15 23 42 / 0.04);
}

.awards-toolbar__tabs {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding-bottom: 0.125rem;
    scrollbar-width: none;
}

.awards-toolbar__tabs::-webkit-scrollbar {
    display: none;
}

.awards-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 2.75rem;
    padding: 0.625rem 0.95rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.95rem;
    background: #f8fafc;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 700;
    white-space: nowrap;
    transition: all 0.15s ease;
}

.awards-tab:hover {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
}

.awards-tab--active {
    border-color: #1c55a5;
    background: linear-gradient(180deg, #1c55a5 0%, #164a8f 100%);
    color: #fff;
    box-shadow: 0 10px 24px rgb(28 85 165 / 0.22);
}

.awards-tab__count {
    display: inline-flex;
    min-width: 1.375rem;
    align-items: center;
    justify-content: center;
    padding: 0.125rem 0.375rem;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.16);
    font-size: 0.6875rem;
    font-weight: 800;
}

.awards-tab--active .awards-tab__count {
    background: rgb(255 255 255 / 0.18);
}

.awards-toolbar__filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.awards-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.awards-pill {
    min-height: 2rem;
    padding: 0.375rem 0.875rem;
    border: 1px solid #dbeafe;
    border-radius: 9999px;
    background: #fff;
    color: #475569;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.15s ease;
}

.awards-pill:hover {
    border-color: #93c5fd;
    color: #1e3a8a;
}

.awards-pill--active {
    border-color: #1c55a5;
    background: #eff6ff;
    color: #1c55a5;
    box-shadow: inset 0 0 0 1px rgb(28 85 165 / 0.08);
}

.awards-year {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
    padding: 0.375rem 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    background: #f8fafc;
}

.awards-year__label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
}

.awards-year__select {
    border: 0;
    background: transparent;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f2f63;
    outline: none;
}

.awards-sheet {
    overflow: hidden;
    border: 1px solid #111827;
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 16px 40px rgb(15 23 42 / 0.06);
}

.awards-sheet__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--awards-grid);
    background: linear-gradient(180deg, #f8fbff 0%, #f1f5f9 100%);
}

.awards-sheet__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 800;
    color: #0f2f63;
}

.awards-sheet__hint {
    margin: 0.125rem 0 0;
    font-size: 0.75rem;
    color: #64748b;
}

.awards-sheet__stats {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.awards-sheet__stat {
    display: inline-flex;
    align-items: center;
    min-height: 1.75rem;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.awards-sheet__stat--muted {
    background: #f8fafc;
    color: #64748b;
    text-transform: none;
    font-weight: 600;
}

.awards-sheet__scroll {
    overflow: auto;
    background: #fff;
}

.awards-sheet__scroll--fit {
    overflow-x: hidden;
}

.awards-sheet__scroll--wide {
    overflow-x: auto;
}

.awards-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.75rem;
    line-height: 1.35;
    color: #0f172a;
}

.awards-grid--wide {
    font-size: 0.6875rem;
}

.awards-grid__head {
    position: sticky;
    top: 0;
    z-index: 2;
    min-height: 3.25rem;
    padding: 0.5rem 0.45rem;
    border-right: 1px solid var(--awards-grid-strong);
    border-bottom: 1px solid var(--awards-grid-strong);
    background: var(--awards-head);
    color: var(--awards-head-text);
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    text-align: center;
    vertical-align: middle;
}

.awards-grid__head-line {
    display: block;
    line-height: 1.25;
    text-align: center;
}

.awards-grid__head--action,
.awards-grid__cell--action {
    width: 2.25rem;
    border-right: 0;
}

.awards-grid__row:nth-child(even) {
    background: #fbfdff;
}

.awards-grid__row:hover {
    background: #f0f9ff;
}

.awards-grid__row:focus-within {
    background: #eff6ff;
}

.awards-grid__cell {
    border-right: 1px solid var(--awards-grid);
    border-bottom: 1px solid var(--awards-grid);
    padding: 0;
    vertical-align: top;
}

.awards-grid__cell--index {
    background: #f8fafc;
}

.awards-grid__readonly {
    min-height: 2.125rem;
    padding: 0.45rem 0.5rem;
}

.awards-grid__cell--index .awards-grid__readonly {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #64748b;
}

.awards-grid__input,
.awards-grid__textarea {
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0.45rem 0.5rem;
    font: inherit;
    color: #0f172a;
    outline: none;
}

.awards-grid__textarea {
    min-height: 2.5rem;
    resize: vertical;
}

.awards-grid__cell--editable:focus-within {
    box-shadow: inset 0 0 0 2px rgb(28 85 165 / 0.28);
    background: #fff;
}

.awards-grid__input:focus,
.awards-grid__textarea:focus {
    background: #fff;
}

.awards-grid__delete {
    display: inline-flex;
    width: 1.5rem;
    height: 1.5rem;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 9999px;
    background: transparent;
    color: #ef4444;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1;
    transition: background 0.15s ease;
}

.awards-grid__delete:hover {
    background: #fef2f2;
}

.awards-grid__empty {
    padding: 2rem 1rem;
    border-bottom: 0;
}

.awards-empty {
    display: grid;
    gap: 0.375rem;
    place-items: center;
    text-align: center;
}

.awards-empty__icon {
    font-size: 1.75rem;
}

.awards-empty__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 800;
    color: #0f2f63;
}

.awards-empty__text {
    margin: 0;
    max-width: 24rem;
    font-size: 0.8125rem;
    color: #64748b;
}

@media (max-width: 768px) {
    .awards-hero {
        padding: 1rem;
    }

    .awards-hero__title {
        font-size: 1.375rem;
    }

    .awards-toolbar {
        padding: 0.875rem;
    }

    .awards-year {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
