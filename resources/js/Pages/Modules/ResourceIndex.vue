<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    module: String,
    title: String,
    description: String,
    columns: Array,
    fields: Array,
    rows: Array,
    canManage: Boolean,
    storeUrl: String,
});

const showForm = ref(false);
const formState = reactive({});

props.fields.forEach((f) => {
    formState[f.name] = f.type === 'checkbox' ? false : '';
});

const form = useForm({ ...formState });

const submit = () => {
    form.transform(() => ({ ...form.data() })).post(props.storeUrl, {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
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
                    @click="showForm = !showForm"
                >
                    {{ showForm ? 'Хаах' : 'Шинэ нэмэх' }}
                </button>
            </div>

            <form
                v-if="showForm && canManage"
                class="ui-card grid gap-4 p-5 md:grid-cols-2"
                @submit.prevent="submit"
            >
                <div v-for="field in fields" :key="field.name" :class="field.type === 'textarea' ? 'md:col-span-2' : ''">
                    <label v-if="field.type !== 'checkbox'" class="ui-label">{{ field.label }}</label>
                    <select
                        v-if="field.type === 'select'"
                        v-model="form[field.name]"
                        class="ui-input"
                        :required="field.required"
                    >
                        <option value="">—</option>
                        <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        v-model="form[field.name]"
                        rows="3"
                        class="ui-input"
                    />
                    <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input v-model="form[field.name]" type="checkbox" class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600" />
                        {{ field.label }}
                    </label>
                    <input
                        v-else
                        v-model="form[field.name]"
                        :type="field.type === 'number' ? 'number' : field.type === 'datetime' ? 'datetime-local' : field.type"
                        class="ui-input"
                        :required="field.required"
                    />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Хадгалах</button>
                </div>
            </form>

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
                                Одоогоор бүртгэл алга.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
