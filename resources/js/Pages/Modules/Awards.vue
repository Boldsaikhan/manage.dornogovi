<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

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

const showForm = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
    category: props.tab,
    subtype: props.subtype || defaultSubtype(props.tab),
    year: props.year || new Date().getFullYear(),
    surname: '',
    given_name: '',
    register_no: '',
    age: '',
    gender: '',
    nominated_award: '',
    years_in_country: '',
    years_in_sector: '',
    award_date: '',
    resolution_number: '',
    position: '',
    address: '',
    last_award: '',
    supporting_org: '',
    presidential_letter: '',
    award_name: '',
    work_sector: '',
    job_title: '',
    total_years: '',
    position_years: '',
    order_ref: '',
    award_note: '',
    notes: '',
});

const form = useForm(emptyForm());

function defaultSubtype(tab) {
    const list = props.categorySubtypes[tab] || [];
    return list[0] || '';
}

const formSubtypes = computed(() => {
    const keys = props.categorySubtypes[form.category] || [];
    return keys.map((key) => ({ value: key, label: props.allSubtypes[key] || key }));
});

const needsSubtype = computed(() => formSubtypes.value.length > 0);
const isStateHigh = computed(() => form.category === 'state_high');
const isOther = computed(() => form.category === 'other');
const isGovernorLike = computed(() =>
    form.category === 'governor_honor' || form.category === 'governor_leading' || form.category === 'other',
);

watch(
    () => form.category,
    (value) => {
        const allowed = props.categorySubtypes[value] || [];
        if (!allowed.includes(form.subtype)) {
            form.subtype = allowed[0] || '';
        }
    },
);

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

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    Object.assign(form, emptyForm());
    showForm.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.clearErrors();
    Object.assign(form, {
        category: row.category,
        subtype: row.subtype || '',
        year: row.year || new Date().getFullYear(),
        surname: row.surname || '',
        given_name: row.given_name || '',
        register_no: row.register_no || '',
        age: row.age ?? '',
        gender: row.gender || '',
        nominated_award: row.nominated_award || '',
        years_in_country: row.years_in_country ?? '',
        years_in_sector: row.years_in_sector ?? '',
        award_date: row.award_date || '',
        resolution_number: row.resolution_number || '',
        position: row.position || '',
        address: row.address || '',
        last_award: row.last_award || '',
        supporting_org: row.supporting_org || '',
        presidential_letter: row.presidential_letter || '',
        award_name: row.award_name || '',
        work_sector: row.work_sector || '',
        job_title: row.job_title || '',
        total_years: row.total_years ?? '',
        position_years: row.position_years ?? '',
        order_ref: row.order_ref || '',
        award_note: row.award_note || '',
        notes: row.notes || '',
    });
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    };

    if (editingId.value) {
        form.patch(route('awards.update', editingId.value), options);
    } else {
        form.post(route('awards.store'), options);
    }
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('awards.destroy', id), { preserveScroll: true });
};

const cellValue = (row, key) => {
    const value = row[key];
    if (value === null || value === undefined || value === '') return '—';
    return value;
};

const pageTitle = computed(() => props.categories[props.tab] || 'Шагнал');
</script>

<template>
    <AuthenticatedLayout title="Шагнал">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Шагнал</h2>
                    <p class="ui-subtitle">
                        Төрийн дээд, аймгийн Засаг даргын болон бусад шагналын бүртгэл.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="exportUrl"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-soft hover:border-brand-navy-300"
                    >
                        Excel татах
                    </a>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="openCreate"
                    >
                        Шинэ нэмэх
                    </button>
                </div>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-3.5 py-2.5 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <div class="flex flex-wrap items-center gap-3">
                <div v-if="subtypes.length" class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="!subtype
                            ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                        @click="switchSubtype('')"
                    >
                        Бүгд
                    </button>
                    <button
                        v-for="item in subtypes"
                        :key="item.value"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="subtype === item.value
                            ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300'"
                        @click="switchSubtype(item.value)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <label class="ml-auto flex items-center gap-2 text-sm text-slate-600">
                    <span>Он:</span>
                    <select
                        class="ui-input w-28 py-1.5"
                        :value="year ?? 'all'"
                        @change="switchYear($event.target.value)"
                    >
                        <option value="all">Бүгд</option>
                        <option
                            v-for="y in years"
                            :key="y"
                            :value="y"
                        >
                            {{ y }}
                        </option>
                        <option
                            v-if="year && !years.includes(year)"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[1600px]">
                    <thead>
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="align-bottom text-xs font-semibold leading-tight"
                                :class="col.vertical
                                    ? 'w-12 px-1 py-2'
                                    : 'whitespace-nowrap'"
                            >
                                <span
                                    v-if="col.vertical"
                                    class="inline-block max-h-36 origin-bottom-left translate-y-0 whitespace-nowrap"
                                    style="writing-mode: vertical-rl; transform: rotate(180deg);"
                                >{{ col.label }}</span>
                                <span v-else>{{ col.label }}</span>
                            </th>
                            <th v-if="canManage" class="w-28" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length">
                            <td
                                :colspan="columns.length + (canManage ? 1 : 0)"
                                class="!py-10 text-center text-slate-500"
                            >
                                Бүртгэл алга — «Шинэ нэмэх»-ээр мөр нэмнэ.
                            </td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="max-w-[220px] align-top"
                                :class="col.key === 'no' || col.vertical ? 'text-center whitespace-nowrap' : ''"
                            >
                                <span class="line-clamp-3 whitespace-pre-wrap">{{ cellValue(row, col.key) }}</span>
                            </td>
                            <td v-if="canManage" class="whitespace-nowrap">
                                <button
                                    type="button"
                                    class="mr-2 text-sm font-medium text-brand-navy-700 hover:underline"
                                    @click="openEdit(row)"
                                >
                                    Засах
                                </button>
                                <button
                                    type="button"
                                    class="text-sm font-medium text-rose-600 hover:underline"
                                    @click="destroyRow(row.id)"
                                >
                                    Устгах
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showForm" max-width="4xl" @close="closeForm">
            <div class="max-h-[85vh] overflow-y-auto p-6">
                <h3 class="text-lg font-semibold text-slate-900">
                    {{ editingId ? 'Шагнал засах' : 'Шинэ шагнал' }}
                </h3>
                <p class="mt-1 text-sm text-slate-500">{{ pageTitle }}</p>

                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label">Төрөл</label>
                            <select v-model="form.category" class="ui-input" :disabled="!!editingId">
                                <option
                                    v-for="(label, value) in categories"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.category" />
                        </div>
                        <div v-if="needsSubtype">
                            <label class="ui-label">Дэд төрөл</label>
                            <select v-model="form.subtype" class="ui-input">
                                <option
                                    v-for="item in formSubtypes"
                                    :key="item.value"
                                    :value="item.value"
                                >
                                    {{ item.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.subtype" />
                        </div>
                        <div>
                            <label class="ui-label">Он</label>
                            <input v-model="form.year" type="number" min="1990" max="2100" class="ui-input">
                            <InputError :message="form.errors.year" />
                        </div>
                        <div v-if="isOther">
                            <label class="ui-label">Шагналын нэр</label>
                            <input v-model="form.award_name" type="text" class="ui-input">
                            <InputError :message="form.errors.award_name" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="ui-label">Овог</label>
                            <input v-model="form.surname" type="text" class="ui-input">
                            <InputError :message="form.errors.surname" />
                        </div>
                        <div>
                            <label class="ui-label">Нэр</label>
                            <input v-model="form.given_name" type="text" class="ui-input">
                            <InputError :message="form.errors.given_name" />
                        </div>
                        <div>
                            <label class="ui-label">Регистр</label>
                            <input v-model="form.register_no" type="text" class="ui-input">
                            <InputError :message="form.errors.register_no" />
                        </div>
                    </div>

                    <template v-if="isStateHigh">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="ui-label">Нас</label>
                                <input v-model="form.age" type="number" min="1" max="120" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Хүйс</label>
                                <select v-model="form.gender" class="ui-input">
                                    <option value="">—</option>
                                    <option value="эр">эр</option>
                                    <option value="эм">эм</option>
                                </select>
                            </div>
                            <div>
                                <label class="ui-label">Өргөн мэдүүлсэн шагнал</label>
                                <input v-model="form.nominated_award" type="text" class="ui-input" placeholder="Жнь: ХГҮТО, АГО">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="ui-label">Улсад ажилласан жил</label>
                                <input v-model="form.years_in_country" type="number" min="0" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Салбартаа ажилласан жил</label>
                                <input v-model="form.years_in_sector" type="number" min="0" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Огноо</label>
                                <input v-model="form.award_date" type="date" class="ui-input">
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="ui-label">Тогтоолын дугаар</label>
                                <input v-model="form.resolution_number" type="text" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Сүүлд авсан шагнал, он</label>
                                <input v-model="form.last_award" type="text" class="ui-input">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Албан тушаал</label>
                            <textarea v-model="form.position" rows="2" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Оршин суугаа хаяг</label>
                            <textarea v-model="form.address" rows="2" class="ui-input" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="ui-label">Дэмжсэн байгууллага</label>
                                <input v-model="form.supporting_org" type="text" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">ЕТГ-т уламжилсан албан бичгийн огноо дугаар</label>
                                <input v-model="form.presidential_letter" type="text" class="ui-input">
                            </div>
                        </div>
                    </template>

                    <template v-if="isGovernorLike">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="ui-label">Ажилладаг салбар</label>
                                <input v-model="form.work_sector" type="text" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Захирамжийн огноо, дугаар</label>
                                <input v-model="form.order_ref" type="text" class="ui-input" placeholder="Жнь: A/219">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Эрхэлдэг ажил, албан тушаал</label>
                            <textarea v-model="form.job_title" rows="2" class="ui-input" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="ui-label">Нийт ажилласан жил</label>
                                <input v-model="form.total_years" type="number" min="0" class="ui-input">
                            </div>
                            <div>
                                <label class="ui-label">Тухайн албан тушаалд ажилласан жил</label>
                                <input v-model="form.position_years" type="number" min="0" class="ui-input">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Шагналын дугаар, хүлээн авсан тэмдэглэл</label>
                            <textarea v-model="form.award_note" rows="2" class="ui-input" />
                        </div>
                        <div v-if="isOther">
                            <label class="ui-label">Нэмэлт тэмдэглэл</label>
                            <textarea v-model="form.notes" rows="2" class="ui-input" />
                        </div>
                    </template>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="ui-btn-ghost" @click="closeForm">Болих</button>
                        <button
                            type="submit"
                            class="ui-btn-accent"
                            :disabled="form.processing"
                        >
                            {{ editingId ? 'Хадгалах' : 'Нэмэх' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
