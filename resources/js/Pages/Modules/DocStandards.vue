<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    formats: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    standards: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', category: '', status: 'current' }) },
    canManage: Boolean,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success ?? null);
const showForm = ref(false);
const query = ref(props.filters.q || '');

const roots = computed(() => props.categories.filter((row) => !row.parent_id));
const childrenOf = (id) => props.categories.filter((row) => row.parent_id === id);

const statusLabel = {
    in_force: 'Хүчин төгөлдөр',
    referenced: 'Журамд дурдсан',
    repealed: 'Хүчингүй',
};

const apply = (patch) => {
    router.get(route('doc-standards.index'), { ...props.filters, ...patch }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const drafts = reactive({});
props.formats.forEach((format) => {
    drafts[format.id] = { ...format };
});
const saving = ref(null);
const saveFormat = (id) => {
    saving.value = id;
    router.patch(route('document-formats.update', id), { ...drafts[id] }, {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    });
};
const makeDefault = (id) => router.post(route('document-formats.default', id), {}, { preserveScroll: true });

const form = useForm({
    category_id: props.categories.find((row) => row.key === 'letter')?.id ?? props.categories[0]?.id ?? '',
    title: '',
    code: '',
    summary: '',
    body: '',
    source_title: '',
    source_url: '',
    source_number: '',
    status: 'in_force',
    effective_on: '',
    amended_on: '',
    repealed_on: '',
    sort_order: 100,
    correct_example: '',
    incorrect_example: '',
    example_clause: '',
});

const submit = () => {
    form.post(route('document-standards.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'code', 'summary', 'body', 'correct_example', 'incorrect_example');
            showForm.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout title="Бичиг хэргийн стандарт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p class="max-w-3xl text-sm text-slate-500">
                    Хүчин төгөлдөр эх нь Засгийн газрын 2024 оны 12 дугаар сарын 18-ны өдрийн 214 дүгээр тогтоол.
                    2020 оны 246 дугаар тогтоол хүчингүй. Журамд байхгүй зах, үсгийн хэмжээг нэмээгүй.
                </p>
                <button v-if="canManage" type="button" class="ui-btn-accent" @click="showForm = !showForm">
                    {{ showForm ? 'Хаах' : 'Стандарт нэмэх' }}
                </button>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ flash }}
            </div>

            <div class="ui-card-pad space-y-3">
                <form class="flex flex-wrap gap-2" @submit.prevent="apply({ q: query })">
                    <input v-model="query" class="ui-input max-w-md" placeholder="Хайх" />
                    <button type="submit" class="ui-btn-primary">Хайх</button>
                </form>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="filters.status === 'current' ? 'bg-brand-navy-50 text-brand-navy-800' : ''" @click="apply({ status: 'current' })">Хүчин төгөлдөр</button>
                    <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="filters.status === 'repealed' ? 'bg-brand-navy-50 text-brand-navy-800' : ''" @click="apply({ status: 'repealed' })">Хүчингүй</button>
                    <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="filters.status === 'archived' ? 'bg-brand-navy-50 text-brand-navy-800' : ''" @click="apply({ status: 'archived' })">Архив</button>
                    <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="filters.status === 'all' ? 'bg-brand-navy-50 text-brand-navy-800' : ''" @click="apply({ status: 'all' })">Бүгд</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="!filters.category ? 'bg-brand-orange-100 text-brand-navy-900' : ''" @click="apply({ category: '' })">Бүх ангилал</button>
                    <template v-for="root in roots" :key="root.id">
                        <button type="button" class="ui-btn-ghost !py-1 text-xs" :class="filters.category === root.key ? 'bg-brand-orange-100 text-brand-navy-900' : ''" @click="apply({ category: root.key })">{{ root.label }}</button>
                        <button
                            v-for="child in childrenOf(root.id)"
                            :key="child.id"
                            type="button"
                            class="ui-btn-ghost !py-1 text-xs"
                            :class="filters.category === child.key ? 'bg-brand-orange-100 text-brand-navy-900' : ''"
                            @click="apply({ category: child.key })"
                        >
                            {{ child.label }}
                        </button>
                    </template>
                </div>
            </div>

            <form v-if="showForm && canManage" class="ui-card grid gap-4 p-5 md:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="ui-label">Ангилал</label>
                    <select v-model="form.category_id" class="ui-input">
                        <option v-for="row in categories" :key="row.id" :value="row.id">{{ row.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="ui-label">Төлөв</label>
                    <select v-model="form.status" class="ui-input">
                        <option value="in_force">Хүчин төгөлдөр</option>
                        <option value="referenced">Журамд дурдсан</option>
                        <option value="repealed">Хүчингүй</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="ui-label">Гарчиг</label>
                    <input v-model="form.title" class="ui-input" required />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>
                <div>
                    <label class="ui-label">Заалт</label>
                    <input v-model="form.code" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Эх сурвалжийн дугаар</label>
                    <input v-model="form.source_number" class="ui-input" />
                </div>
                <div class="md:col-span-2">
                    <label class="ui-label">Товч</label>
                    <input v-model="form.summary" class="ui-input" />
                </div>
                <div class="md:col-span-2">
                    <label class="ui-label">Агуулга</label>
                    <textarea v-model="form.body" rows="5" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Эх сурвалжийн нэр</label>
                    <input v-model="form.source_title" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Эх сурвалжийн холбоос</label>
                    <input v-model="form.source_url" type="url" class="ui-input" />
                    <InputError :message="form.errors.source_url" class="mt-1" />
                </div>
                <div>
                    <label class="ui-label">Хүчин төгөлдөр огноо</label>
                    <input v-model="form.effective_on" type="date" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Шинэчлэгдсэн огноо</label>
                    <input v-model="form.amended_on" type="date" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Хүчингүй болсон огноо</label>
                    <input v-model="form.repealed_on" type="date" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Дараалал</label>
                    <input v-model.number="form.sort_order" type="number" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Зөв жишээ</label>
                    <textarea v-model="form.correct_example" rows="3" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Буруу жишээ</label>
                    <textarea v-model="form.incorrect_example" rows="3" class="ui-input" />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Хадгалах</button>
                </div>
            </form>

            <div class="grid gap-3 md:grid-cols-2">
                <Link
                    v-for="row in standards"
                    :key="row.id"
                    :href="row.slug ? route('doc-standards.show', row.slug) : route('doc-standards.index')"
                    class="ui-card-pad block hover:border-brand-navy-200"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-brand-navy-600">{{ row.code || '—' }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="row.status === 'repealed' ? 'bg-slate-200 text-slate-600' : 'bg-brand-navy-50 text-brand-navy-800'"
                        >
                            {{ statusLabel[row.status] || row.status }}
                        </span>
                        <span v-if="row.archived_at" class="text-[11px] text-slate-400">архив</span>
                    </div>
                    <h2 class="mt-2 text-base font-semibold text-slate-800">{{ row.title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ row.summary }}</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ row.category_label }} · {{ row.source_number }}
                        <span v-if="row.effective_on"> · хүчинтэй {{ row.effective_on }}</span>
                        <span v-if="row.repealed_on"> · хүчингүй {{ row.repealed_on }}</span>
                    </p>
                </Link>
                <p v-if="!standards.length" class="text-sm text-slate-400">Таарсан стандарт алга.</p>
            </div>

            <section class="space-y-3">
                <div>
                    <h2 class="ui-title">Системийн хэвлэх тохиргоо</h2>
                    <p class="ui-subtitle">
                        Word файлд хэрэглэнэ. 214 дүгээр тогтоол зах, үсгийн цэг, мөр хоорондын зайг тоогоор заагаагүй тул эдгээр тоо нь хуулийн шаардлага биш.
                    </p>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <section v-for="format in formats" :key="format.id" class="ui-card-pad space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-800">
                                    {{ drafts[format.id].label }}
                                    <span v-if="format.is_default" class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">үндсэн</span>
                                </h3>
                            </div>
                            <button v-if="canManage && !format.is_default" type="button" class="ui-btn-ghost !py-1.5 text-xs" @click="makeDefault(format.id)">Үндсэн болгох</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="ui-label">Нэр</label>
                                <input v-model="drafts[format.id].label" class="ui-input" :disabled="!canManage" />
                            </div>
                            <div><label class="ui-label">Өргөн (мм)</label><input v-model.number="drafts[format.id].width_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Өндөр (мм)</label><input v-model.number="drafts[format.id].height_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Зүүн зах (мм)</label><input v-model.number="drafts[format.id].margin_left_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Баруун зах (мм)</label><input v-model.number="drafts[format.id].margin_right_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Дээд зах (мм)</label><input v-model.number="drafts[format.id].margin_top_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Доод зах (мм)</label><input v-model.number="drafts[format.id].margin_bottom_mm" type="number" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Фонт</label><input v-model="drafts[format.id].font_name" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Үсгийн хэмжээ (pt)</label><input v-model.number="drafts[format.id].font_size_pt" type="number" step="0.5" class="ui-input" :disabled="!canManage" /></div>
                            <div><label class="ui-label">Мөр хоорондын зай</label><input v-model.number="drafts[format.id].line_spacing" type="number" step="0.05" class="ui-input" :disabled="!canManage" /></div>
                        </div>
                        <button v-if="canManage" type="button" class="ui-btn-primary" :disabled="saving === format.id" @click="saveFormat(format.id)">
                            {{ saving === format.id ? 'Хадгалж байна…' : 'Хадгалах' }}
                        </button>
                    </section>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
