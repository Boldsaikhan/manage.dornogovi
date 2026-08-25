<script setup>
import { computed, reactive, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    tab: { type: String, default: 'zahiramj_a' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const isBlank = computed(() => props.tab === 'blank');
const isNiit = computed(() => props.tab === 'niit');
const isZahiramj = computed(() => props.tab.startsWith('zahiramj'));
const isDoc = computed(() => ! isBlank.value);

const docLabel = computed(() => {
    if (isNiit.value) return 'Захирамж, тушаал';
    return isZahiramj.value ? 'Захирамж' : 'Тушаал';
});
const titleLabel = computed(() => {
    if (isNiit.value) return 'Гарчиг / тэргүү';
    return isZahiramj.value ? 'Захирамжийн тэргүү' : 'Тушаалын гарчиг';
});
const numberLabel = computed(() => {
    if (isNiit.value) return 'Бүртгэл';
    return isZahiramj.value ? 'Захирамжийн дугаар' : 'Тушаалын дугаар';
});

const kindOptions = [
    { value: 'zahiramj_a', label: 'Захирамж А' },
    { value: 'zahiramj_b', label: 'Захирамж Б' },
    { value: 'tushaal_a', label: 'Тушаал А' },
    { value: 'tushaal_b', label: 'Тушаал Б' },
];

const canAddRow = computed(() => props.canManage && ! isNiit.value);

const drafts = reactive({});

const blankFields = [
    'person_name', 'issued_on',
    'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
    'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
    'num_zahiramj', 'num_tushaal', 'void_zahiramj', 'void_tushaal', 'body',
];

const docFields = [
    'kind', 'number', 'issued_on', 'title', 'page_count',
    'attachment_name', 'attachment_pages', 'person_name', 'body',
];

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    props.rows.forEach((row) => {
        if (isBlank.value) {
            drafts[row.id] = Object.fromEntries(blankFields.map((f) => [f, row[f] ?? '']));
        } else {
            drafts[row.id] = Object.fromEntries(docFields.map((f) => [f, row[f] ?? '']));
        }
    });
};

watch(() => [props.rows, props.tab], syncDrafts, { immediate: true, deep: true });

const switchTab = (value) => {
    router.get(route('decrees.index'), { tab: value }, { preserveState: false, preserveScroll: true });
};

const addRow = () => {
    if (isNiit.value) return;

    if (isBlank.value) {
        useForm({
            tab: 'blank',
            person_name: '',
            issued_on: '',
        }).post(route('decrees.store'), { preserveScroll: true });
        return;
    }

    useForm({
        tab: props.tab,
        number: '',
        title: '',
        issued_on: '',
    }).post(route('decrees.store'), { preserveScroll: true });
};

const saveField = (id, field, value) => {
    let next = value;
    const qtyFields = [
        'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
        'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
        'page_count', 'attachment_pages',
    ];

    if (qtyFields.includes(field)) {
        if (next === '' || next === null || next === undefined) {
            next = null;
        } else {
            const n = Number.parseInt(next, 10);
            next = Number.isNaN(n) ? null : n;
        }
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next;
    }

    if (drafts[id] && Object.prototype.hasOwnProperty.call(drafts[id], field)) {
        drafts[id][field] = next ?? '';
    }

    router.patch(
        route('decrees.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('decrees.destroy', id), { preserveScroll: true });
};

const blankColCount = computed(() => 15 + (props.canManage ? 1 : 0));
const docColumnCount = computed(() => {
    let n = 8;
    if (isNiit.value) n += 1;
    if (props.canManage) n += 1;
    return n;
});

const cellClass = 'border border-slate-800 p-0 align-middle overflow-hidden';
</script>

<template>
    <AuthenticatedLayout title="Захирамж, тушаал">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Захирамж, тушаал</h2>
                    <p class="ui-subtitle">
                        Мөрийг нэмээд нүдэн дээр дарж шууд бөглөнө.
                    </p>
                </div>
                <button
                    v-if="canAddRow"
                    type="button"
                    class="ui-btn-accent"
                    @click="addRow"
                >
                    Шинэ мөр
                </button>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <!-- Бланкны дугаар -->
            <div v-if="isBlank" class="decree-sheet overflow-x-auto border border-slate-800 bg-white">
                <table class="w-full min-w-[1100px] border-collapse text-center text-[11px] leading-tight text-slate-900">
                    <colgroup>
                        <col style="width: 2.25rem" />
                        <col style="width: 9.5rem" />
                        <col style="width: 6.5rem" />
                        <col v-for="n in 8" :key="`q-${n}`" style="width: 3.25rem" />
                        <col v-for="n in 4" :key="`n-${n}`" style="width: 4.5rem" />
                        <col style="width: 7rem" />
                        <col v-if="canManage" style="width: 3.5rem" />
                    </colgroup>
                    <thead>
                        <tr class="bg-slate-50">
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">Д/д</th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хэвлэмэл хуудас авсан<br>ажилтны нэр
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">Огноо</th>
                            <th colspan="8" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Олгосон хэвлэмэл хуудас
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хэвлэмэл хуудасны дугаар
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Үрэгдүүлсэн хуудасны дугаар
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хүлээн авсан<br>гарын үсэг
                            </th>
                            <th v-if="canManage" rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold w-16" />
                        </tr>
                        <tr class="bg-slate-50">
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Албан<br>даалгавар</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Зөвлөлийн<br>хурал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50 focus-within:bg-sky-50"
                        >
                            <td class="border border-slate-800 px-1 py-1">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="canManage"
                                    :options="people"
                                    empty-label=""
                                    placeholder="Нэр сонгох…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].issued_on"
                                    type="date"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'issued_on', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_zahiramj"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_zahiramj_mn"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_zahiramj_mn', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_tushaal"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_tushaal_mn"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_tushaal_mn', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_assignment"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_assignment', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_assignment_mn"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_assignment_mn', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_council"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_council', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_council_mn"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_council_mn', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].num_zahiramj"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'num_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].num_tushaal"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'num_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].void_zahiramj"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].void_tushaal"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].body"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'body', v)"
                                />
                            </td>
                            <td v-if="canManage" class="border border-slate-800 px-1 py-1">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyRow(row.id)">
                                    Устгах
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="blankColCount" class="border border-slate-800 px-2 py-10 text-slate-400">
                                Бүртгэл алга. «Шинэ мөр» дарж нүдэн дээр бөглөнө үү.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Захирамж / Тушаалын дугаар -->
            <div v-else-if="isDoc" class="decree-sheet overflow-x-auto border border-slate-800 bg-white">
                <div class="border-b border-slate-800 px-3 py-2 text-center text-sm font-semibold tracking-wide">
                    Аймгийн Засаг даргын {{ docLabel }}ийн бүртгэл
                </div>
                <table class="w-full min-w-[1000px] border-collapse text-center text-[12px] leading-tight text-slate-900">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 5rem" />
                        <col style="width: 6.5rem" />
                        <col />
                        <col style="width: 4.5rem" />
                        <col style="width: 10rem" />
                        <col style="width: 4.5rem" />
                        <col style="width: 8rem" />
                        <col v-if="isNiit" style="width: 6.5rem" />
                        <col v-if="canManage" style="width: 3.5rem" />
                    </colgroup>
                    <thead>
                        <tr class="bg-slate-50">
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-10">№</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-20">Дугаар</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-24">Огноо</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold">{{ titleLabel }}</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-20">
                                Хуудасны<br>тоо
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold">
                                Хавсралтын мэдээлэл
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-36">
                                Боловсруулсан<br>албан тушаалтан
                            </th>
                            <th v-if="isNiit" rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-24">Төрөл</th>
                            <th v-if="canManage" rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-16" />
                        </tr>
                        <tr class="bg-slate-50">
                            <th class="border border-slate-800 px-1.5 py-1.5 font-medium">Баримт бичгийн нэр</th>
                            <th class="border border-slate-800 px-1.5 py-1.5 font-medium w-20">Хуудасны тоо</th>
                        </tr>
                        <tr class="bg-slate-100 text-[10px] text-slate-500">
                            <th class="border border-slate-800 py-0.5">1</th>
                            <th class="border border-slate-800 py-0.5">2</th>
                            <th class="border border-slate-800 py-0.5">3</th>
                            <th class="border border-slate-800 py-0.5">4</th>
                            <th class="border border-slate-800 py-0.5">5</th>
                            <th class="border border-slate-800 py-0.5">6</th>
                            <th class="border border-slate-800 py-0.5">7</th>
                            <th class="border border-slate-800 py-0.5">8</th>
                            <th v-if="isNiit" class="border border-slate-800 py-0.5">9</th>
                            <th v-if="canManage" class="border border-slate-800 py-0.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50 focus-within:bg-sky-50"
                        >
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    placeholder="Дугаар…"
                                    @commit="(v) => saveField(row.id, 'number', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].issued_on"
                                    type="date"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'issued_on', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].title"
                                    multiline
                                    :editable="canManage"
                                    empty-label=""
                                    placeholder="Гарчиг…"
                                    @commit="(v) => saveField(row.id, 'title', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].page_count"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'page_count', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].attachment_name"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'attachment_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].attachment_pages"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'attachment_pages', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="canManage"
                                    :options="people"
                                    align="center"
                                    empty-label=""
                                    placeholder="Нэр сонгох…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td v-if="isNiit" class="border border-slate-800 px-1 py-1">
                                <select
                                    v-if="canManage && drafts[row.id]"
                                    v-model="drafts[row.id].kind"
                                    class="w-full border-0 bg-transparent py-1 text-center text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'kind', drafts[row.id].kind)"
                                >
                                    <option v-for="opt in kindOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <span v-else class="text-[11px]">{{ row.kind_label }}</span>
                            </td>
                            <td v-if="canManage" class="border border-slate-800 px-1.5 py-1.5">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyRow(row.id)">
                                    Устгах
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="docColumnCount" class="border border-slate-800 px-2 py-10 text-slate-400">
                                {{ isNiit ? 'Бүртгэл алга.' : `${numberLabel}ын бүртгэл алга. «Шинэ мөр» дарж бөглөнө үү.` }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
