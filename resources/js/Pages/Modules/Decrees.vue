<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    tab: { type: String, default: 'blank' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const showForm = ref(false);
const isBlank = computed(() => props.tab === 'blank');
const isZahiramj = computed(() => props.tab === 'zahiramj');
const docLabel = computed(() => (isZahiramj.value ? 'Захирамж' : 'Тушаал'));
const titleLabel = computed(() => (isZahiramj.value ? 'Захирамжийн тэргүү' : 'Тушаалын гарчиг'));
const numberLabel = computed(() => (isZahiramj.value ? 'Захирамжийн дугаар' : 'Тушаалын дугаар'));

const kindOptions = computed(() => (isZahiramj.value
    ? [
        { value: 'zahiramj_a', label: 'Захирамж А' },
        { value: 'zahiramj_b', label: 'Захирамж Б' },
    ]
    : [
        { value: 'tushaal_a', label: 'Тушаал А' },
        { value: 'tushaal_b', label: 'Тушаал Б' },
    ]));

const blankForm = useForm({
    tab: 'blank',
    person_name: '',
    issued_on: '',
    qty_zahiramj: 0,
    qty_zahiramj_mn: 0,
    qty_tushaal: 0,
    qty_tushaal_mn: 0,
    qty_assignment: 0,
    qty_assignment_mn: 0,
    qty_council: 0,
    qty_council_mn: 0,
    num_zahiramj: '',
    num_tushaal: '',
    void_zahiramj: '',
    void_tushaal: '',
    body: '',
});

const docForm = useForm({
    tab: props.tab,
    kind: '',
    number: '',
    title: '',
    issued_on: '',
    page_count: null,
    attachment_name: '',
    attachment_pages: null,
    person_name: '',
    body: '',
});

watch(
    () => props.tab,
    (tab) => {
        docForm.tab = tab;
        blankForm.tab = 'blank';
        docForm.kind = '';
    },
);

const switchTab = (value) => {
    router.get(route('decrees.index'), { tab: value }, { preserveState: false, preserveScroll: true });
};

const openForm = () => {
    if (isBlank.value) {
        blankForm.reset();
        blankForm.clearErrors();
        blankForm.tab = 'blank';
        blankForm.issued_on = new Date().toISOString().slice(0, 10);
    } else {
        docForm.reset();
        docForm.clearErrors();
        docForm.tab = props.tab;
        docForm.kind = kindOptions.value[0]?.value ?? '';
        docForm.issued_on = new Date().toISOString().slice(0, 10);
    }
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
};

const submit = () => {
    const form = isBlank.value ? blankForm : docForm;
    form.post(route('decrees.store'), {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('decrees.destroy', id), { preserveScroll: true });
};

const activeForm = computed(() => (isBlank.value ? blankForm : docForm));
const docColumnCount = computed(() => 9 + (props.canManage ? 1 : 0));
</script>

<template>
    <AuthenticatedLayout title="Захирамж, тушаал">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Захирамж, тушаал</h2>
                    <p class="ui-subtitle">
                        Бланкны дугаар болон захирамж/тушаалын дугаарыг тусад нь бүртгэнэ.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="ui-btn-accent"
                    @click="openForm"
                >
                    Шинэ нэмэх
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

            <!-- Бланкны дугаар: хэвлэмэл хуудас олголт -->
            <div v-if="isBlank" class="overflow-x-auto border border-slate-800 bg-white">
                <table class="w-full min-w-[1100px] border-collapse text-center text-[11px] leading-tight text-slate-900">
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
                            <td class="border border-slate-800 px-1 py-1 text-left">
                                <span class="ui-clamp-2" :title="row.person_name || row.title || ''">
                                    {{ row.person_name || row.title || '—' }}
                                </span>
                            </td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.issued_on || '—' }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_zahiramj }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_zahiramj_mn }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_tushaal }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_tushaal_mn }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_assignment }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_assignment_mn }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_council }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.qty_council_mn }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.num_zahiramj || '' }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.num_tushaal || '' }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.void_zahiramj || '' }}</td>
                            <td class="border border-slate-800 px-1 py-1">{{ row.void_tushaal || '' }}</td>
                            <td class="border border-slate-800 px-1 py-1 text-left">
                                <span class="ui-clamp-2" :title="row.body || ''">{{ row.body || '' }}</span>
                            </td>
                            <td v-if="canManage" class="border border-slate-800 px-1 py-1">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyRow(row.id)">
                                    Устгах
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="canManage ? 16 : 15" class="border border-slate-800 px-2 py-10 text-slate-400">
                                Бланкны дугаарын бүртгэл алга. «Шинэ нэмэх» дарж оруулна уу.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Захирамж / Тушаалын дугаар: цаасан бүртгэлийн хүснэгт -->
            <div v-else class="overflow-x-auto border border-slate-800 bg-white">
                <div class="border-b border-slate-800 px-3 py-2 text-center text-sm font-semibold tracking-wide">
                    Аймгийн Засаг даргын {{ docLabel }}ийн бүртгэл
                </div>
                <table class="w-full min-w-[1000px] border-collapse text-center text-[12px] leading-tight text-slate-900">
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
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-16">Төрөл</th>
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
                            <th class="border border-slate-800 py-0.5" />
                            <th v-if="canManage" class="border border-slate-800 py-0.5" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50"
                        >
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.no }}</td>
                            <td class="border border-slate-800 px-1.5 py-1.5 font-medium">{{ row.number || '—' }}</td>
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.issued_on_display || row.issued_on || '—' }}</td>
                            <td class="border border-slate-800 px-1.5 py-1.5 text-left">
                                <span class="ui-clamp-2" :title="row.title || ''">{{ row.title || '—' }}</span>
                            </td>
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.page_count ?? '' }}</td>
                            <td class="border border-slate-800 px-1.5 py-1.5 text-left">
                                <span class="ui-clamp-2" :title="row.attachment_name || ''">{{ row.attachment_name || '' }}</span>
                            </td>
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.attachment_pages ?? '' }}</td>
                            <td class="border border-slate-800 px-1.5 py-1.5">
                                <span class="ui-clamp-2" :title="row.person_name || ''">{{ row.person_name || '' }}</span>
                            </td>
                            <td class="border border-slate-800 px-1.5 py-1.5 text-[11px]">{{ row.kind_label }}</td>
                            <td v-if="canManage" class="border border-slate-800 px-1.5 py-1.5">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyRow(row.id)">
                                    Устгах
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="docColumnCount" class="border border-slate-800 px-2 py-10 text-slate-400">
                                {{ numberLabel }}ын бүртгэл алга.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="showForm && canManage" max-width="2xl" @close="closeForm">
            <form class="p-6" @submit.prevent="submit">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">
                            {{ isBlank ? 'Бланкны дугаар олгох' : `${numberLabel} бүртгэх` }}
                        </h3>
                        <p class="mt-0.5 text-sm text-slate-500">Шаардлагатай талбаруудыг бөглөнө үү.</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closeForm">✕</button>
                </div>

                <!-- Бланкны дугаар -->
                <div v-if="isBlank" class="grid max-h-[65vh] gap-3 overflow-y-auto pr-1 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label class="ui-label">Хэвлэмэл хуудас авсан ажилтны нэр</label>
                        <input v-model="blankForm.person_name" class="ui-input" required />
                        <InputError :message="blankForm.errors.person_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Огноо</label>
                        <input v-model="blankForm.issued_on" type="date" class="ui-input" required />
                        <InputError :message="blankForm.errors.issued_on" class="mt-1" />
                    </div>
                    <div class="md:col-span-4 grid gap-2 rounded-lg border border-slate-200 p-3 md:grid-cols-4">
                        <p class="md:col-span-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Олгосон хэвлэмэл хуудас (ширхэг)</p>
                        <div>
                            <label class="ui-label">Захирамж</label>
                            <input v-model.number="blankForm.qty_zahiramj" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Монгол бичиг</label>
                            <input v-model.number="blankForm.qty_zahiramj_mn" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Тушаал</label>
                            <input v-model.number="blankForm.qty_tushaal" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Монгол бичиг</label>
                            <input v-model.number="blankForm.qty_tushaal_mn" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Албан даалгавар</label>
                            <input v-model.number="blankForm.qty_assignment" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Монгол бичиг</label>
                            <input v-model.number="blankForm.qty_assignment_mn" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Зөвлөлийн хурал</label>
                            <input v-model.number="blankForm.qty_council" type="number" min="0" class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Монгол бичиг</label>
                            <input v-model.number="blankForm.qty_council_mn" type="number" min="0" class="ui-input" />
                        </div>
                    </div>
                    <div>
                        <label class="ui-label">Бланкны дугаар — Захирамж</label>
                        <input v-model="blankForm.num_zahiramj" class="ui-input" placeholder="ж: 810-812" />
                    </div>
                    <div>
                        <label class="ui-label">Бланкны дугаар — Тушаал</label>
                        <input v-model="blankForm.num_tushaal" class="ui-input" placeholder="ж: 263-264" />
                    </div>
                    <div>
                        <label class="ui-label">Үрэгдүүлсэн — Захирамж</label>
                        <input v-model="blankForm.void_zahiramj" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Үрэгдүүлсэн — Тушаал</label>
                        <input v-model="blankForm.void_tushaal" class="ui-input" />
                    </div>
                    <div class="md:col-span-4">
                        <label class="ui-label">Хүлээн авсан / тэмдэглэл</label>
                        <textarea v-model="blankForm.body" rows="2" class="ui-input" />
                    </div>
                </div>

                <!-- Захирамж / Тушаалын дугаар -->
                <div v-else class="grid max-h-[65vh] gap-4 overflow-y-auto pr-1 md:grid-cols-2">
                    <div>
                        <label class="ui-label">Төрөл</label>
                        <select v-model="docForm.kind" class="ui-input" required>
                            <option v-for="opt in kindOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <InputError :message="docForm.errors.kind" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">{{ numberLabel }}</label>
                        <input v-model="docForm.number" class="ui-input" required placeholder="ж: 01" />
                        <InputError :message="docForm.errors.number" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Огноо</label>
                        <input v-model="docForm.issued_on" type="date" class="ui-input" required />
                        <InputError :message="docForm.errors.issued_on" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Хуудасны тоо</label>
                        <input v-model.number="docForm.page_count" type="number" min="0" class="ui-input" />
                        <InputError :message="docForm.errors.page_count" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="ui-label">{{ titleLabel }}</label>
                        <textarea v-model="docForm.title" rows="2" class="ui-input" required />
                        <InputError :message="docForm.errors.title" class="mt-1" />
                    </div>
                    <div class="md:col-span-2 border-t border-slate-100 pt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Хавсралтын мэдээлэл
                    </div>
                    <div>
                        <label class="ui-label">Баримт бичгийн нэр</label>
                        <input v-model="docForm.attachment_name" class="ui-input" placeholder="ж: Арын бичилт, Монгол бичиг" />
                        <InputError :message="docForm.errors.attachment_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Хавсралтын хуудасны тоо</label>
                        <input v-model.number="docForm.attachment_pages" type="number" min="0" class="ui-input" />
                        <InputError :message="docForm.errors.attachment_pages" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="ui-label">Боловсруулсан албан тушаалтан</label>
                        <input v-model="docForm.person_name" class="ui-input" placeholder="ж: Б.Зоригтбаатар" />
                        <InputError :message="docForm.errors.person_name" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="ui-label">Тэмдэглэл</label>
                        <textarea v-model="docForm.body" rows="2" class="ui-input" />
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="ui-btn-ghost" @click="closeForm">Болих</button>
                    <button type="submit" class="ui-btn-primary" :disabled="activeForm.processing">Хадгалах</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
