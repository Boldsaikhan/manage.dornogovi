<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    formats: { type: Array, default: () => [] },
    standards: { type: Array, default: () => [] },
    canManage: Boolean,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success ?? null);
const showStandardForm = ref(false);

// Формат бүрийн засварлаж буй утга.
const drafts = reactive({});

props.formats.forEach((f) => {
    drafts[f.id] = { ...f };
});

const saving = ref(null);

const saveFormat = (id) => {
    saving.value = id;
    router.patch(route('document-formats.update', id), { ...drafts[id] }, {
        preserveScroll: true,
        onFinish: () => (saving.value = null),
    });
};

const makeDefault = (id) => {
    router.post(route('document-formats.default', id), {}, { preserveScroll: true });
};

const standardForm = useForm({ title: '', body: '', sort_order: 0 });

const submitStandard = () => {
    standardForm.post(route('document-standards.store'), {
        preserveScroll: true,
        onSuccess: () => {
            standardForm.reset();
            showStandardForm.value = false;
        },
    });
};

const destroyStandard = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('document-standards.destroy', id), { preserveScroll: true });
};

// Хуудасны цэвэр талбай — хэрэглэгчид шалгахад хялбар болгоно.
const contentSize = (d) => ({
    width: d.width_mm - d.margin_left_mm - d.margin_right_mm,
    height: d.height_mm - d.margin_top_mm - d.margin_bottom_mm,
});
</script>

<template>
    <AuthenticatedLayout title="Бичиг хэргийн стандарт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Бичиг хэргийн стандарт</h2>
                    <p class="ui-subtitle">
                        Албан хэрэг хөтлөлтийн журмын дагуу A4, A5 хуудасны тохиргоог тааруулна.
                        Системээс гаргаж байгаа бүх Word файл үндсэн стандартын дагуу үүснэ.
                    </p>
                </div>
                <button v-if="canManage" type="button" class="ui-btn-accent" @click="showStandardForm = !showStandardForm">
                    {{ showStandardForm ? 'Хаах' : 'Заавар нэмэх' }}
                </button>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ flash }}
            </div>

            <!-- Хуудасны стандартууд -->
            <div class="grid gap-4 lg:grid-cols-2">
                <section v-for="format in formats" :key="format.id" class="ui-card-pad space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-800">
                                {{ drafts[format.id].label }}
                                <span
                                    v-if="format.is_default"
                                    class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
                                >
                                    үндсэн
                                </span>
                            </h3>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Бичвэрийн талбай:
                                {{ contentSize(drafts[format.id]).width }} × {{ contentSize(drafts[format.id]).height }} мм
                            </p>
                        </div>
                        <button
                            v-if="canManage && !format.is_default"
                            type="button"
                            class="ui-btn-ghost !py-1.5 text-xs"
                            @click="makeDefault(format.id)"
                        >
                            Үндсэн болгох
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="ui-label">Нэр</label>
                            <input v-model="drafts[format.id].label" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Өргөн (мм)</label>
                            <input v-model.number="drafts[format.id].width_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Өндөр (мм)</label>
                            <input v-model.number="drafts[format.id].height_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Зүүн зах (мм)</label>
                            <input v-model.number="drafts[format.id].margin_left_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Баруун зах (мм)</label>
                            <input v-model.number="drafts[format.id].margin_right_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Дээд зах (мм)</label>
                            <input v-model.number="drafts[format.id].margin_top_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Доод зах (мм)</label>
                            <input v-model.number="drafts[format.id].margin_bottom_mm" type="number" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Фонт</label>
                            <input v-model="drafts[format.id].font_name" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Үсгийн хэмжээ (pt)</label>
                            <input v-model.number="drafts[format.id].font_size_pt" type="number" step="0.5" class="ui-input" :disabled="!canManage" />
                        </div>
                        <div>
                            <label class="ui-label">Мөр хоорондын зай</label>
                            <input v-model.number="drafts[format.id].line_spacing" type="number" step="0.05" class="ui-input" :disabled="!canManage" />
                        </div>
                    </div>

                    <div v-if="canManage">
                        <button type="button" class="ui-btn-primary" :disabled="saving === format.id" @click="saveFormat(format.id)">
                            {{ saving === format.id ? 'Хадгалж байна…' : 'Хадгалах' }}
                        </button>
                    </div>
                </section>
            </div>

            <!-- Заавар, бичвэрүүд -->
            <form v-if="showStandardForm && canManage" class="ui-card grid gap-4 p-5" @submit.prevent="submitStandard">
                <div>
                    <label class="ui-label">Гарчиг</label>
                    <input v-model="standardForm.title" class="ui-input" required />
                    <InputError :message="standardForm.errors.title" class="mt-1" />
                </div>
                <div>
                    <label class="ui-label">Агуулга</label>
                    <textarea v-model="standardForm.body" rows="4" class="ui-input" />
                </div>
                <div class="max-w-[10rem]">
                    <label class="ui-label">Дараалал</label>
                    <input v-model.number="standardForm.sort_order" type="number" class="ui-input" />
                </div>
                <div>
                    <button type="submit" class="ui-btn-primary" :disabled="standardForm.processing">Хадгалах</button>
                </div>
            </form>

            <div class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th class="w-64">Гарчиг</th>
                            <th>Агуулга</th>
                            <th class="w-24 text-center">Дараалал</th>
                            <th v-if="canManage" class="w-20" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in standards" :key="row.id">
                            <td class="font-medium text-slate-800">
                                <span class="ui-clamp-2" :title="row.title">{{ row.title }}</span>
                            </td>
                            <td>
                                <span class="ui-clamp-2" :title="row.body || ''">{{ row.body || '—' }}</span>
                            </td>
                            <td class="text-center">{{ row.sort_order }}</td>
                            <td v-if="canManage" class="text-right">
                                <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyStandard(row.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!standards.length">
                            <td :colspan="canManage ? 4 : 3" class="!py-12 text-center text-slate-400">
                                Одоогоор заавар алга.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
