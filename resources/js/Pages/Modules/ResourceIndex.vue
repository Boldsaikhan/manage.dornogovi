<script setup>
import { computed, reactive, ref } from 'vue';
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
});

const showForm = ref(false);
const formState = reactive({});

props.fields.forEach((f) => {
    formState[f.name] = f.type === 'checkbox' ? false : '';
});

if (props.scopeField && props.activeScope !== 'all') {
    formState[props.scopeField] = props.activeScope;
}

const form = useForm({ ...formState });

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
    form.transform(() => ({ ...form.data() })).post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            closeForm();
            resetFormDefaults();
        },
    });
};

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

            <nav v-if="scopeTabs.length" class="flex flex-wrap gap-2">
                <button
                    v-for="tab in scopeTabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-full border px-4 py-1.5 text-sm font-medium transition"
                    :class="
                        tab.value === activeScope
                            ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300 hover:text-brand-navy-700'
                    "
                    @click="switchScope(tab.value)"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">{{ tab.count }}</span>
                </button>
            </nav>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                            <th v-if="canManage" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td v-for="col in columns" :key="col.key">{{ row[col.key] }}</td>
                            <td v-if="canManage" class="text-right">
                                <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyRow(row.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="columns.length + (canManage ? 1 : 0)" class="!py-12 text-center text-slate-400">
                                {{ activeScopeLabel && activeScope !== 'all' ? activeScopeLabel + '-ын бүртгэл алга.' : 'Одоогоор бүртгэл алга.' }}
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
                        <textarea
                            v-else-if="field.type === 'textarea'"
                            v-model="form[field.name]"
                            rows="3"
                            class="ui-input"
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
                        />
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
