<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    module: String,
    title: String,
    description: String,
    columns: Array,
    fields: Array,
    rows: Array,
    canManage: Boolean,
    storeUrl: String,
    scopeTabs: { type: Array, default: () => [] },
    activeScope: { type: String, default: 'all' },
    scopeField: { type: String, default: null },
    directory: { type: Array, default: () => [] },
    rowActions: { type: Array, default: () => [] },
});

const showForm = ref(false);
const formState = reactive({});

props.fields.forEach((f) => {
    formState[f.name] = f.type === 'checkbox' ? false : (f.type === 'file' ? null : '');
});

if (props.scopeField && props.activeScope !== 'all') {
    formState[props.scopeField] = props.activeScope;
}

const form = useForm({ ...formState });

const hasFileField = computed(() => props.fields.some((f) => f.type === 'file'));

const previewId = ref(props.rows.find((r) => r.file_is_pdf)?.id ?? null);

watch(
    () => props.rows,
    (rows) => {
        if (previewId.value && rows.some((r) => r.id === previewId.value && r.file_is_pdf)) {
            return;
        }
        previewId.value = rows.find((r) => r.file_is_pdf)?.id ?? null;
    },
    { deep: true },
);

const previewRow = computed(() => props.rows.find((r) => r.id === previewId.value) ?? null);

const isFileColumn = (col) => col.type === 'file' || col.key === 'file';
const fieldNames = computed(() => props.fields.map((f) => f.name));
const hasField = (name) => fieldNames.value.includes(name);

// Утасны жагсаалтын байгууллага, хүмүүс — хамрах хүрээгээр шүүнэ.
const directoryFor = (field) => {
    const scopeField = field?.scope_field;
    const scope = scopeField ? form[scopeField] : null;

    if (!scope) return props.directory;

    return props.directory.filter((d) => d.category === scope);
};

const orgOptions = (field) => directoryFor(field).map((d) => d.org_name);

const peopleFor = (orgName) => {
    if (!orgName) return props.directory.flatMap((d) => d.people);

    return props.directory.find((d) => d.org_name === orgName)?.people ?? [];
};

// Хамрах хүрээ солигдоход тухайн хүрээнд байхгүй байгууллагыг цэвэрлэнэ.
watch(
    () => (props.scopeField ? form[props.scopeField] : null),
    () => {
        const orgField = props.fields.find((f) => f.type === 'directory_org');
        if (!orgField) return;

        const names = orgOptions(orgField);
        if (form[orgField.name] && !names.includes(form[orgField.name])) {
            form[orgField.name] = '';
            if (hasField('person_name')) form.person_name = '';
        }
    },
);

const personOptions = (field) => peopleFor(field.depends_on ? form[field.depends_on] : null);

// Байгууллага солигдоход өмнөх хүн тухайн байгууллагад байхгүй бол цэвэрлэнэ.
watch(
    () => (hasField('org_name') ? form.org_name : null),
    (org) => {
        if (!hasField('person_name')) return;
        const names = peopleFor(org).map((p) => p.name);
        if (form.person_name && !names.includes(form.person_name)) {
            form.person_name = '';
        }
    },
);

// Огноо ↔ хоногийн харилцан тооцоо (эхлэх/дуусах өдрийг оруулж тооцно).
const dayMs = 24 * 60 * 60 * 1000;
const syncing = ref(false);

const toDate = (value) => {
    if (!value) return null;
    // UTC-ээр тооцно — цагийн бүсээс болж огноо гулсахаас сэргийлнэ.
    const d = new Date(`${value}T00:00:00Z`);

    return Number.isNaN(d.getTime()) ? null : d;
};

const toInput = (date) => date.toISOString().slice(0, 10);

const hasDateFields = computed(() => hasField('start_date') && hasField('end_date') && hasField('days'));

const recalcDays = () => {
    if (!hasDateFields.value || syncing.value) return;
    const start = toDate(form.start_date);
    const end = toDate(form.end_date);
    if (!start || !end || end < start) return;

    syncing.value = true;
    form.days = Math.round((end - start) / dayMs) + 1;
    syncing.value = false;
};

const recalcEndDate = () => {
    if (!hasDateFields.value || syncing.value) return;
    const start = toDate(form.start_date);
    const days = parseInt(form.days, 10);
    if (!start || !days || days < 1) return;

    syncing.value = true;
    form.end_date = toInput(new Date(start.getTime() + (days - 1) * dayMs));
    syncing.value = false;
};

const onFieldInput = (field) => {
    if (!hasDateFields.value) return;
    if (field.name === 'start_date') {
        // Эхлэх огноо солигдоход хоног мэдэгдэж байвал дуусахыг, эсрэг тохиолдолд хоногийг бодно.
        form.days ? recalcEndDate() : recalcDays();

        return;
    }
    if (field.name === 'end_date') recalcDays();
    if (field.name === 'days') recalcEndDate();
};

const activeScopeLabel = computed(
    () => props.scopeTabs.find((t) => t.value === props.activeScope)?.label ?? '',
);

const resetFormDefaults = () => {
    form.reset();
    form.clearErrors();
    if (props.scopeField && props.activeScope !== 'all') {
        form[props.scopeField] = props.activeScope;
    }
};

const openForm = () => {
    resetFormDefaults();
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
};

const switchScope = (value) => {
    router.get(
        `/modules/${props.module}`,
        value === 'all' ? {} : { scope: value },
        { preserveState: false, preserveScroll: true },
    );
};

const submit = () => {
    form.transform(() => {
        const data = { ...form.data() };
        if (props.scopeField && props.activeScope && props.activeScope !== 'all') {
            data[props.scopeField] = props.activeScope;
        }
        return data;
    }).post(props.storeUrl, {
        preserveScroll: true,
        forceFormData: hasFileField.value,
        onSuccess: () => {
            closeForm();
            resetFormDefaults();
        },
    });
};

// Мөр бүрийн нэмэлт үйлдэл (жишээ нь чөлөөний хуудас хэвлэх).
const actionUrl = (action, id) => action.url.replace('{id}', id);

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(`/modules/${props.module}/${id}`, { preserveScroll: true });
};
</script>

<template>
    <AuthenticatedLayout :title="title">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">{{ title }}</h2>
                    <p class="ui-subtitle">{{ description }}</p>
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

            <nav v-if="scopeTabs.length" class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="tab in scopeTabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="
                        tab.value === activeScope
                            ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                            : 'text-slate-600 hover:bg-slate-50'
                    "
                    @click="switchScope(tab.value)"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">{{ tab.count }}</span>
                </button>
            </nav>

            <div class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-full">
                    <thead>
                        <tr>
                            <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                            <th v-if="canManage || rowActions.length" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            :class="row.id === previewId && row.file_is_pdf ? 'bg-brand-navy-50/60' : ''"
                        >
                            <td v-for="col in columns" :key="col.key">
                                <template v-if="isFileColumn(col)">
                                    <button
                                        v-if="row.file_url && row.file_is_pdf"
                                        type="button"
                                        class="inline-flex max-w-xs items-center gap-1.5 text-left text-sm font-medium text-brand-navy-700 hover:underline"
                                        @click="previewId = row.id"
                                    >
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" />
                                            <path stroke-linecap="round" d="M13 3v5h5" />
                                        </svg>
                                        <span class="ui-clamp-2">{{ row.file_name || row[col.key] }}</span>
                                    </button>
                                    <a
                                        v-else-if="row.file_url"
                                        :href="row.file_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex max-w-xs items-center gap-1.5 text-sm font-medium text-brand-navy-700 hover:underline"
                                    >
                                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M8 12l4 4 4-4M12 4v12" />
                                        </svg>
                                        <span class="ui-clamp-2">{{ row.file_name || row[col.key] }}</span>
                                    </a>
                                    <span v-else class="text-slate-400">—</span>
                                </template>
                                <span
                                    v-else
                                    class="ui-clamp-2"
                                    :title="row[col.key] != null && row[col.key] !== '' ? String(row[col.key]) : ''"
                                >{{ row[col.key] != null && row[col.key] !== '' ? row[col.key] : '—' }}</span>
                            </td>
                            <td v-if="canManage || rowActions.length" class="whitespace-nowrap text-right">
                                <a
                                    v-for="action in rowActions"
                                    :key="action.label"
                                    :href="actionUrl(action, row.id)"
                                    :target="action.target || '_self'"
                                    class="ui-btn-ghost mr-2 !py-1 text-xs"
                                >
                                    {{ action.label }}
                                </a>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="ui-btn-danger !py-1 text-xs"
                                    @click="destroyRow(row.id)"
                                >
                                    Устгах
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="columns.length + (canManage || rowActions.length ? 1 : 0)" class="!py-12 text-center text-slate-400">
                                {{ activeScopeLabel && activeScope !== 'all' ? activeScopeLabel + '-ын бүртгэл алга.' : 'Одоогоор бүртгэл алга.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <section
                v-if="previewRow?.file_is_pdf && previewRow.file_url"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-soft"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 class="text-sm font-semibold text-brand-navy-900">{{ previewRow.title || previewRow.file_name }}</h3>
                        <p class="text-xs text-slate-500">PDF шууд харагдана</p>
                    </div>
                    <a
                        :href="previewRow.file_url"
                        target="_blank"
                        rel="noopener"
                        class="ui-btn-ghost !py-1.5 text-xs"
                    >
                        Шинэ цонхонд нээх
                    </a>
                </div>
                <iframe
                    :src="previewRow.file_url"
                    class="h-[min(80vh,920px)] w-full bg-slate-50"
                    :title="previewRow.file_name || 'PDF'"
                />
            </section>
        </div>

        <Modal :show="showForm && canManage" max-width="2xl" @close="closeForm">
            <form class="p-6" @submit.prevent="submit">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Шинэ бүртгэл</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ title }} — мэдээллээ оруулна уу.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Хаах"
                        @click="closeForm"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[65vh] gap-4 overflow-y-auto pr-1 md:grid-cols-2">
                    <div
                        v-for="field in fields"
                        :key="field.name"
                        :class="field.type === 'textarea' ? 'md:col-span-2' : ''"
                    >
                        <label v-if="field.type !== 'checkbox'" class="ui-label">{{ field.label }}</label>
                        <select
                            v-if="field.type === 'select'"
                            v-model="form[field.name]"
                            class="ui-input"
                            :required="field.required"
                        >
                            <option value="">—</option>
                            <option
                                v-for="(label, value) in field.options"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <select
                            v-else-if="field.type === 'directory_org' && orgOptions(field).length"
                            v-model="form[field.name]"
                            class="ui-input"
                            :required="field.required"
                        >
                            <option value="">—</option>
                            <option v-for="name in orgOptions(field)" :key="name" :value="name">{{ name }}</option>
                        </select>
                        <select
                            v-else-if="field.type === 'directory_person' && personOptions(field).length"
                            v-model="form[field.name]"
                            class="ui-input"
                            :required="field.required"
                        >
                            <option value="">—</option>
                            <option
                                v-for="person in personOptions(field)"
                                :key="person.name + (person.position || '')"
                                :value="person.name"
                            >
                                {{ person.name }}{{ person.position ? ' — ' + person.position : '' }}
                            </option>
                        </select>
                        <input
                            v-else-if="field.type === 'directory_org' || field.type === 'directory_person'"
                            v-model="form[field.name]"
                            type="text"
                            class="ui-input"
                            :required="field.required"
                            :placeholder="directory.length
                                ? 'Энэ хамрах хүрээнд бүртгэлгүй — гараар бичнэ'
                                : 'Утасны жагсаалт хоосон байна'"
                        />
                        <textarea
                            v-else-if="field.type === 'textarea'"
                            v-model="form[field.name]"
                            rows="3"
                            class="ui-input"
                        />
                        <input
                            v-else-if="field.type === 'file'"
                            type="file"
                            class="ui-input"
                            :required="field.required"
                            :accept="field.accept || '.pdf,.doc,.docx,application/pdf'"
                            @change="form[field.name] = $event.target.files?.[0] || null"
                        />
                        <label
                            v-else-if="field.type === 'checkbox'"
                            class="flex items-center gap-2 text-sm font-medium text-slate-700"
                        >
                            <input
                                v-model="form[field.name]"
                                type="checkbox"
                                class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                            />
                            {{ field.label }}
                        </label>
                        <input
                            v-else
                            v-model="form[field.name]"
                            :type="field.type === 'number' ? 'number' : field.type === 'datetime' ? 'datetime-local' : field.type"
                            class="ui-input"
                            :required="field.required"
                            :min="field.type === 'number' && field.name === 'days' ? 1 : undefined"
                            @change="onFieldInput(field)"
                        />
                        <p
                            v-if="hasDateFields && field.name === 'days'"
                            class="mt-1 text-xs text-slate-500"
                        >
                            Эхлэх, дуусах огноог оруулбал хоног автоматаар бодогдоно. Хоногийг өөрчилвөл дуусах огноо шинэчлэгдэнэ.
                        </p>
                        <InputError :message="form.errors[field.name]" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeForm">Болих</button>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Хадгалж байна…' : 'Хадгалах' }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
