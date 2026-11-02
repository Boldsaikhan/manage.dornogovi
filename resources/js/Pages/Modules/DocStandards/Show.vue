<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StandardVisual from '@/Components/DocStandards/StandardVisual.vue';

const props = defineProps({
    standard: { type: Object, required: true },
    related: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    formats: { type: Array, default: () => [] },
    canManage: Boolean,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success ?? null);
const editing = ref(false);
const draft = ref('');
const checking = ref(false);
const result = ref(null);

const statusLabel = {
    in_force: 'Хүчин төгөлдөр',
    referenced: 'Журамд дурдсан, бүтэн эх энэ тогтоолд алга',
    repealed: 'Хүчингүй',
};

const form = useForm({
    category_id: props.standard.category_id,
    title: props.standard.title,
    code: props.standard.code || '',
    summary: props.standard.summary || '',
    body: props.standard.body || '',
    source_title: props.standard.source_title || '',
    source_url: props.standard.source_url || '',
    source_number: props.standard.source_number || '',
    status: props.standard.status,
    effective_on: props.standard.effective_on || '',
    amended_on: props.standard.amended_on || '',
    repealed_on: props.standard.repealed_on || '',
    sort_order: props.standard.sort_order || 0,
    correct_example: '',
    incorrect_example: '',
    example_clause: props.standard.code || '',
});

const save = () => {
    form.patch(route('document-standards.update', props.standard.id), {
        preserveScroll: true,
        onSuccess: () => (editing.value = false),
    });
};

const archive = () => {
    router.post(route('document-standards.archive', props.standard.id), {}, { preserveScroll: true });
};

const runCheck = async () => {
    checking.value = true;
    result.value = null;
    try {
        const { data } = await window.axios.post(route('doc-standards.check'), {
            text: draft.value,
            slug: props.standard.slug,
            kind: props.standard.category_key === 'order' ? 'order' : undefined,
        });
        result.value = data;
    } finally {
        checking.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout :title="standard.title">
        <div class="ui-page">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="route('doc-standards.index')" class="text-sm text-brand-navy-600 hover:underline">Бүх стандарт</Link>
                <div v-if="canManage" class="flex gap-2">
                    <button type="button" class="ui-btn-ghost" @click="editing = !editing">{{ editing ? 'Хаах' : 'Засах' }}</button>
                    <button type="button" class="ui-btn-ghost" @click="archive">{{ standard.archived_at ? 'Архиваас гаргах' : 'Архивлах' }}</button>
                </div>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{{ flash }}</div>

            <section v-if="standard.status === 'repealed'" class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-3 text-sm text-slate-700">
                Энэ эх хүчингүй. Шалгалт, шинэ баримт бичигт хэрэглэхгүй.
                <span v-if="standard.repealed_on"> Хүчингүй болсон: {{ standard.repealed_on }}.</span>
            </section>

            <header class="ui-card-pad">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-brand-navy-600">{{ standard.code }}</span>
                    <span class="rounded-full bg-brand-navy-50 px-2 py-0.5 text-[11px] font-semibold text-brand-navy-800">{{ statusLabel[standard.status] }}</span>
                </div>
                <h2 class="ui-title mt-2">{{ standard.title }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ standard.summary }}</p>
                <p class="mt-3 text-xs text-slate-500">
                    {{ standard.source_number }}
                    <span v-if="standard.effective_on"> · хүчин төгөлдөр {{ standard.effective_on }}</span>
                    <span v-if="standard.amended_on"> · шинэчлэгдсэн {{ standard.amended_on }}</span>
                </p>
                <a v-if="standard.source_url" :href="standard.source_url" class="mt-1 inline-block text-xs text-brand-navy-600 hover:underline" target="_blank" rel="noopener">
                    {{ standard.source_title || 'Эх сурвалж' }}
                </a>
            </header>

            <StandardVisual :visual="standard.visual" />

            <section v-if="standard.examples?.length" class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="(example, index) in standard.examples"
                    :key="index"
                    class="ui-card p-4"
                    :class="example.ok ? 'border-emerald-200' : 'border-red-200'"
                >
                    <p class="text-xs font-semibold" :class="example.ok ? 'text-emerald-700' : 'text-red-700'">
                        {{ example.ok ? 'Журамд нийцсэн' : 'Журамд нийцэхгүй' }} · {{ example.clause }}
                    </p>
                    <p class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ example.text }}</p>
                    <p v-if="example.note" class="mt-2 text-xs text-slate-500">{{ example.note }}</p>
                </article>
            </section>

            <section class="ui-card-pad">
                <h3 class="ui-subtitle">Журмын заалт</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ standard.body }}</p>
            </section>

            <section v-if="standard.status !== 'repealed'" class="ui-card-pad space-y-3">
                <h3 class="ui-subtitle">Энэ стандартаар шалгах</h3>
                <p class="text-xs text-slate-500">Manage AI мөн энэ дүрмийг ашиглана. Хүчингүй эх, журамд байхгүй хэмжээ орохгүй.</p>
                <textarea v-model="draft" rows="6" class="ui-input" placeholder="Албан бичгийн төслөө энд буулгана" />
                <button type="button" class="ui-btn-primary" :disabled="checking || !draft.trim()" @click="runCheck">
                    {{ checking ? 'Шалгаж байна…' : 'Шалгах' }}
                </button>
                <div v-if="result" class="space-y-2 text-sm">
                    <p v-if="!result.findings?.length" class="text-emerald-700">Хадгалсан дүрмээр илэрхий зөрчил илрээгүй.</p>
                    <p v-for="(row, index) in result.findings || []" :key="`f-${index}`" class="text-red-700">{{ row.clause }} — {{ row.message }}</p>
                    <p v-for="(row, index) in result.checklist || []" :key="`c-${index}`" class="text-slate-600">{{ row.clause }} — {{ row.message }}</p>
                    <p class="text-xs text-slate-400">{{ result.note }}</p>
                </div>
            </section>

            <form v-if="editing && canManage" class="ui-card grid gap-3 p-5 md:grid-cols-2" @submit.prevent="save">
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
                <div class="md:col-span-2"><label class="ui-label">Гарчиг</label><input v-model="form.title" class="ui-input" required /></div>
                <div><label class="ui-label">Заалт</label><input v-model="form.code" class="ui-input" /></div>
                <div><label class="ui-label">Эх сурвалжийн дугаар</label><input v-model="form.source_number" class="ui-input" /></div>
                <div class="md:col-span-2"><label class="ui-label">Товч</label><input v-model="form.summary" class="ui-input" /></div>
                <div class="md:col-span-2"><label class="ui-label">Агуулга</label><textarea v-model="form.body" rows="8" class="ui-input" /></div>
                <div><label class="ui-label">Эх сурвалж</label><input v-model="form.source_title" class="ui-input" /></div>
                <div><label class="ui-label">Холбоос</label><input v-model="form.source_url" type="url" class="ui-input" /></div>
                <div><label class="ui-label">Хүчин төгөлдөр</label><input v-model="form.effective_on" type="date" class="ui-input" /></div>
                <div><label class="ui-label">Шинэчлэгдсэн</label><input v-model="form.amended_on" type="date" class="ui-input" /></div>
                <div><label class="ui-label">Хүчингүй</label><input v-model="form.repealed_on" type="date" class="ui-input" /></div>
                <div class="md:col-span-2"><button class="ui-btn-primary" :disabled="form.processing">Хадгалах</button></div>
            </form>

            <section v-if="related.length">
                <h3 class="ui-subtitle">Ижил ангилал</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Link v-for="row in related" :key="row.id" :href="route('doc-standards.show', row.slug)" class="ui-btn-ghost !py-1 text-xs">
                        {{ row.title }}
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
