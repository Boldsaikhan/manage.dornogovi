<script setup>
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    orgNames: { type: Array, default: () => [] },
    canManage: Boolean,
});

const page = usePage();
const search = ref('');
const showForm = ref(false);
const showImport = ref(false);
const fileInput = ref(null);

const form = useForm({
    org_name: '',
    person_name: '',
    position: '',
    office_phone: '',
    mobile_phone: '',
});

const importForm = useForm({
    file: null,
    replace: false,
});

const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();

    if (!q) return props.groups;

    return props.groups
        .map((g) => ({
            ...g,
            rows: g.rows.filter((r) =>
                [g.org_name, r.person_name, r.position, r.office_phone, r.mobile_phone]
                    .filter(Boolean)
                    .some((v) => String(v).toLowerCase().includes(q)),
            ),
        }))
        .filter((g) => g.rows.length);
});

const submit = () => {
    form.post(route('phone-directory.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const org = form.org_name;
            form.reset();
            form.org_name = org;
        },
    });
};

const submitImport = () => {
    importForm.post(route('phone-directory.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
            showImport.value = false;
        },
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(`/phone-directory/${id}`, { preserveScroll: true });
};

const flash = computed(() => page.props.flash?.success ?? null);
</script>

<template>
    <AuthenticatedLayout title="Утасны жагсаалт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Утасны жагсаалт</h2>
                    <p class="ui-subtitle">
                        Байгууллага, албан хаагчдын ажлын өрөө болон гар утасны нэгдсэн жагсаалт. Нийт {{ total }} бүртгэл.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-primary"
                        @click="showImport = !showImport; showForm = false"
                    >
                        {{ showImport ? 'Хаах' : 'Word импорт' }}
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="showForm = !showForm; showImport = false"
                    >
                        {{ showForm ? 'Хаах' : 'Шинэ нэмэх' }}
                    </button>
                </div>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ flash }}
            </div>

            <form v-if="showImport && canManage" class="ui-card grid gap-4 p-5" @submit.prevent="submitImport">
                <div>
                    <label class="ui-label">Word файл (.docx)</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".docx"
                        class="ui-input"
                        @change="importForm.file = $event.target.files[0]"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Хүснэгтийн толгой: № / Овог нэр / Албан тушаал / Ажлын өрөөний утас / Гар утас.
                        Нийлүүлсэн ганц нүдтэй мөрийг байгууллагын нэр гэж уншина.
                    </p>
                    <p v-if="importForm.errors.file" class="mt-1 text-sm text-rose-600">{{ importForm.errors.file }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input
                        v-model="importForm.replace"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                    />
                    Одоо байгаа жагсаалтыг устгаад шинээр оруулах
                </label>
                <div>
                    <button type="submit" class="ui-btn-primary" :disabled="importForm.processing || !importForm.file">
                        {{ importForm.processing ? 'Уншиж байна…' : 'Импортлох' }}
                    </button>
                </div>
            </form>

            <form v-if="showForm && canManage" class="ui-card grid gap-4 p-5 md:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="ui-label">Байгууллага / хэлтэс</label>
                    <input v-model="form.org_name" list="phone-org-names" class="ui-input" required />
                    <datalist id="phone-org-names">
                        <option v-for="name in orgNames" :key="name" :value="name" />
                    </datalist>
                </div>
                <div>
                    <label class="ui-label">Овог нэр</label>
                    <input v-model="form.person_name" class="ui-input" required />
                </div>
                <div>
                    <label class="ui-label">Албан тушаал</label>
                    <input v-model="form.position" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Ажлын өрөөний утас</label>
                    <input v-model="form.office_phone" class="ui-input" />
                </div>
                <div>
                    <label class="ui-label">Гар утас</label>
                    <input v-model="form.mobile_phone" class="ui-input" />
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Хадгалах</button>
                </div>
            </form>

            <div>
                <input v-model="search" type="search" class="ui-input md:max-w-sm" placeholder="Нэр, албан тушаал, утсаар хайх…" />
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">№</th>
                            <th>Овог нэр</th>
                            <th>Албан тушаал</th>
                            <th class="text-center">Ажлын өрөөний утас</th>
                            <th class="text-center">Гар утас</th>
                            <th v-if="canManage" />
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in filteredGroups" :key="group.org_name">
                            <tr class="bg-brand-navy-50">
                                <td :colspan="canManage ? 6 : 5" class="text-center font-semibold italic text-brand-navy-800">
                                    {{ group.org_name }}
                                </td>
                            </tr>
                            <tr v-for="(row, index) in group.rows" :key="row.id">
                                <td class="text-center">{{ index + 1 }}</td>
                                <td>{{ row.person_name }}</td>
                                <td>{{ row.position || '—' }}</td>
                                <td class="text-center">{{ row.office_phone || '—' }}</td>
                                <td class="text-center">{{ row.mobile_phone || '—' }}</td>
                                <td v-if="canManage" class="text-right">
                                    <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyRow(row.id)">Устгах</button>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!filteredGroups.length">
                            <td :colspan="canManage ? 6 : 5" class="!py-12 text-center text-slate-400">
                                {{ search ? 'Хайлтад тохирох бүртгэл алга.' : 'Одоогоор бүртгэл алга. Word файлаас импортлож болно.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
