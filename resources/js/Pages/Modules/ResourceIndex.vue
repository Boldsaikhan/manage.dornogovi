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
        <div class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-brand-navy-900">{{ title }}</h2>
                    <p class="mt-1 text-sm text-brand-navy-500">{{ description }}</p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-lg bg-brand-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-orange-600"
                    @click="showForm = !showForm"
                >
                    {{ showForm ? 'Хаах' : 'Шинэ нэмэх' }}
                </button>
            </div>

            <form
                v-if="showForm && canManage"
                class="grid gap-3 rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm md:grid-cols-2"
                @submit.prevent="submit"
            >
                <div v-for="field in fields" :key="field.name" :class="field.type === 'textarea' ? 'md:col-span-2' : ''">
                    <label class="mb-1 block text-xs font-medium text-brand-navy-600">{{ field.label }}</label>
                    <select
                        v-if="field.type === 'select'"
                        v-model="form[field.name]"
                        class="w-full rounded-md border-brand-navy-200 text-sm"
                        :required="field.required"
                    >
                        <option value="">—</option>
                        <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
                    </select>
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        v-model="form[field.name]"
                        rows="3"
                        class="w-full rounded-md border-brand-navy-200 text-sm"
                    />
                    <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 text-sm">
                        <input v-model="form[field.name]" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500" />
                        {{ field.label }}
                    </label>
                    <input
                        v-else
                        v-model="form[field.name]"
                        :type="field.type === 'number' ? 'number' : field.type === 'datetime' ? 'datetime-local' : field.type"
                        class="w-full rounded-md border-brand-navy-200 text-sm"
                        :required="field.required"
                    />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="rounded-lg bg-brand-navy-700 px-4 py-2 text-sm font-medium text-white" :disabled="form.processing">
                        Хадгалах
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-brand-navy-50 text-left text-xs text-brand-navy-700">
                        <tr>
                            <th v-for="col in columns" :key="col.key" class="px-3 py-2 font-medium">{{ col.label }}</th>
                            <th v-if="canManage" class="px-3 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="row.id" class="border-t border-brand-navy-100" :class="i % 2 ? 'bg-brand-navy-50/50' : ''">
                            <td v-for="col in columns" :key="col.key" class="px-3 py-2 text-brand-navy-700">{{ row[col.key] }}</td>
                            <td v-if="canManage" class="px-3 py-2 text-right">
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="destroyRow(row.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="columns.length + (canManage ? 1 : 0)" class="px-3 py-10 text-center text-brand-navy-400">
                                Одоогоор бүртгэл алга.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
