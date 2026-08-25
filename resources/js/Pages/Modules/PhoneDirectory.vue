<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    tab: { type: String, default: 'directory' },
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    orgNames: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    staff: { type: Array, default: () => [] },
    staffTotal: { type: Number, default: 0 },
    staffOrganizations: { type: Array, default: () => [] },
    canManage: Boolean,
});

const page = usePage();
const search = ref('');
const categoryFilter = ref('all');
const unitFilter = ref('all');
const showForm = ref(false);
const showImport = ref(false);
const showStaffForm = ref(false);
const showStaffImport = ref(false);
const fileInput = ref(null);
const staffFileInput = ref(null);

const isDirectory = computed(() => props.tab !== 'staff');

const form = useForm({
    org_name: '',
    category: '',
    person_name: '',
    position: '',
    office_phone: '',
    mobile_phone: '',
});

const editingId = ref(null);
const isEditing = computed(() => editingId.value !== null);

const staffForm = useForm({
    organization: '',
    unit: '',
    position: '',
    last_name: '',
    first_name: '',
    room: '',
    work_phone: '',
    mobile_phone: '',
    email: '',
});

const importForm = useForm({
    file: null,
    replace: false,
});

const staffImportForm = useForm({
    file: null,
    replace: false,
});

watch(
    () => props.tab,
    () => {
        search.value = '';
        showForm.value = false;
        showImport.value = false;
        showStaffForm.value = false;
        showStaffImport.value = false;
        categoryFilter.value = 'all';
        unitFilter.value = 'all';
    },
);

// Ангилал тус бүрийн байгууллагын тоо.
const categoryTabs = computed(() => {
    const counts = {};
    props.groups.forEach((g) => {
        counts[g.category] = (counts[g.category] ?? 0) + g.rows.length;
    });

    return [
        { value: 'all', label: 'Бүгд', count: props.total },
        ...Object.entries(props.categories).map(([value, label]) => ({
            value,
            label,
            count: counts[value] ?? 0,
        })),
    ];
});

// Албан хаагчдын нэгжүүд (хэлтэс).
const unitOptions = computed(() => {
    const units = props.staff.map((r) => r.unit).filter(Boolean);

    return [...new Set(units)].sort((a, b) => a.localeCompare(b, 'mn'));
});

const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();
    const scoped = categoryFilter.value === 'all'
        ? props.groups
        : props.groups.filter((g) => g.category === categoryFilter.value);

    if (!q) return scoped;

    return scoped
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

const filteredStaff = computed(() => {
    const q = search.value.trim().toLowerCase();
    const scoped = unitFilter.value === 'all'
        ? props.staff
        : props.staff.filter((r) => (r.unit || '—') === unitFilter.value);

    if (!q) return scoped;

    return scoped.filter((r) =>
        [
            r.organization,
            r.unit,
            r.position,
            r.last_name,
            r.first_name,
            r.room,
            r.work_phone,
            r.mobile_phone,
            r.email,
        ]
            .filter(Boolean)
            .some((v) => String(v).toLowerCase().includes(q)),
    );
});

const submit = () => {
    if (isEditing.value) {
        form.patch(route('phone-directory.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                editingId.value = null;
                showForm.value = false;
            },
        });
        return;
    }

    form.post(route('phone-directory.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const org = form.org_name;
            const cat = form.category;
            form.reset();
            form.org_name = org;
            form.category = cat;
            showForm.value = false;
        },
    });
};

const submitStaff = () => {
    staffForm.post(route('phone-directory.staff.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const org = staffForm.organization;
            staffForm.reset();
            staffForm.organization = org;
            showStaffForm.value = false;
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

const submitStaffImport = () => {
    staffImportForm.post(route('phone-directory.staff.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            staffImportForm.reset();
            if (staffFileInput.value) staffFileInput.value.value = '';
            showStaffImport.value = false;
        },
    });
};

// Байгууллагын ангиллыг (хэлтэс/агентлаг/сум/байгууллага) бүлгээр нь солино.
const changeCategory = (orgName, category) => {
    router.patch(
        route('phone-directory.category'),
        { org_name: orgName, category },
        { preserveScroll: true },
    );
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('phone-directory.destroy', id), { preserveScroll: true });
};

const destroyStaff = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('phone-directory.staff.destroy', id), { preserveScroll: true });
};

const flash = computed(() => page.props.flash?.success ?? null);

const resetDirectoryForm = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
};

const openAdd = () => {
    if (isDirectory.value) {
        resetDirectoryForm();
        showForm.value = true;
        showImport.value = false;
    } else {
        showStaffForm.value = true;
    }
};

const openEdit = (row, group) => {
    resetDirectoryForm();
    editingId.value = row.id;
    form.org_name = group.org_name;
    form.category = group.category || '';
    form.person_name = row.person_name || '';
    form.position = row.position || '';
    form.office_phone = row.office_phone || '';
    form.mobile_phone = row.mobile_phone || '';
    showForm.value = true;
    showImport.value = false;
};

const closeDirectoryForm = () => {
    showForm.value = false;
    resetDirectoryForm();
};
</script>

<template>
    <AuthenticatedLayout title="Утасны жагсаалт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Утасны жагсаалт</h2>
                    <p class="ui-subtitle">
                        <template v-if="isDirectory">
                            Байгууллага, албан хаагчдын ажлын өрөө болон гар утасны нэгдсэн жагсаалт.
                            Нийт {{ total }} бүртгэл.
                        </template>
                        <template v-else>
                            АЗДТГ-н албан хаагчдын дэлгэрэнгүй утасны бүртгэл.
                            Нийт {{ staffTotal }} бүртгэл.
                        </template>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="isDirectory && total"
                        :href="route('phone-directory.export')"
                        class="ui-btn-ghost"
                    >
                        Word татах
                    </a>
                    <a
                        v-if="!isDirectory && staffTotal"
                        :href="route('phone-directory.staff.export')"
                        class="ui-btn-ghost"
                    >
                        Word татах
                    </a>
                    <button
                        v-if="canManage && isDirectory"
                        type="button"
                        class="ui-btn-primary"
                        @click="showImport = !showImport; showForm = false"
                    >
                        {{ showImport ? 'Хаах' : 'Word импорт' }}
                    </button>
                    <button
                        v-if="canManage && !isDirectory"
                        type="button"
                        class="ui-btn-primary"
                        @click="showStaffImport = !showStaffImport"
                    >
                        {{ showStaffImport ? 'Хаах' : 'Word импорт' }}
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="openAdd"
                    >
                        Шинэ нэмэх
                    </button>
                </div>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ flash }}
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <Link
                    :href="route('phone-directory.index', { tab: 'directory' })"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="isDirectory
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                >
                    Утасны жагсаалт
                    <span class="ml-1 opacity-70">{{ total }}</span>
                </Link>
                <Link
                    :href="route('phone-directory.index', { tab: 'staff' })"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="!isDirectory
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                >
                    АЗДТГ-н албан хаагчид
                    <span class="ml-1 opacity-70">{{ staffTotal }}</span>
                </Link>
            </div>

            <!-- Directory: import -->
            <form v-if="isDirectory && showImport && canManage" class="ui-card grid gap-4 p-5" @submit.prevent="submitImport">
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

            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="search"
                    class="ui-input md:max-w-sm"
                    :placeholder="isDirectory
                        ? 'Нэр, албан тушаал, утсаар хайх…'
                        : 'Байгууллага, нэр, утас, и-мэйлээр хайх…'"
                />

                <!-- Ангиллаар шүүх (утасны жагсаалт) -->
                <div v-if="isDirectory" class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in categoryTabs"
                        :key="tab.value"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="
                            tab.value === categoryFilter
                                ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300 hover:text-brand-navy-700'
                        "
                        @click="categoryFilter = tab.value"
                    >
                        {{ tab.label }}
                        <span class="ml-1 text-xs opacity-70">{{ tab.count }}</span>
                    </button>
                </div>

                <!-- Нэгжээр шүүх (албан хаагчид) -->
                <select
                    v-else
                    v-model="unitFilter"
                    class="ui-input md:max-w-xs"
                    title="Нэгж (хэлтэс)-ээр шүүх"
                >
                    <option value="all">Бүх нэгж ({{ staffTotal }})</option>
                    <option v-for="unit in unitOptions" :key="unit" :value="unit">{{ unit }}</option>
                </select>
            </div>

            <!-- Staff: import -->
            <form
                v-if="!isDirectory && showStaffImport && canManage"
                class="ui-card grid gap-4 p-5"
                @submit.prevent="submitStaffImport"
            >
                <div>
                    <label class="ui-label">Word, Excel эсвэл PDF файл</label>
                    <input
                        ref="staffFileInput"
                        type="file"
                        accept=".docx,.docm,.xlsx,.xlsm,.pdf"
                        class="ui-input"
                        @change="staffImportForm.file = $event.target.files[0]"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        .docx, .xlsx, .pdf (20 MB хүртэл). Баганын дараалал: № / Байгууллага / Нэгж / Албан тушаал /
                        Овог / Нэр / Өрөө / Ажлын утас / Гар утас / И-мэйл хаяг. Нийлүүлсэн ганц нүдтэй мөрийг
                        байгууллагын нэр гэж уншина. Сканнердсан зурган PDF уншигдахгүй.
                    </p>
                    <p v-if="staffImportForm.errors.staff_file" class="mt-1 text-sm text-rose-600">
                        {{ staffImportForm.errors.staff_file }}
                    </p>
                    <p v-if="staffImportForm.errors.file" class="mt-1 text-sm text-rose-600">
                        {{ staffImportForm.errors.file }}
                    </p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input
                        v-model="staffImportForm.replace"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                    />
                    Одоо байгаа жагсаалтыг устгаад шинээр оруулах
                </label>
                <div>
                    <button
                        type="submit"
                        class="ui-btn-primary"
                        :disabled="staffImportForm.processing || !staffImportForm.file"
                    >
                        {{ staffImportForm.processing ? 'Уншиж байна…' : 'Импортлох' }}
                    </button>
                </div>
            </form>

            <!-- Tab 1: directory -->
            <div v-if="isDirectory" class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[720px]">
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
                                    <select
                                        v-if="canManage"
                                        :value="group.category || ''"
                                        class="ml-2 rounded-lg border-slate-300 bg-white py-0.5 pl-2 pr-7 text-xs font-medium not-italic text-slate-600"
                                        title="Чөлөөний бүртгэлд ямар хамрах хүрээнд харагдахыг тодорхойлно"
                                        @change="changeCategory(group.org_name, $event.target.value)"
                                    >
                                        <option value="">Сонголтгүй</option>
                                        <option v-for="(label, value) in categories" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                    <span
                                        v-else
                                        class="ml-2 rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium not-italic text-slate-500"
                                    >
                                        {{ categories[group.category] || 'Сонголтгүй' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-for="(row, index) in group.rows" :key="row.id">
                                <td class="text-center">{{ index + 1 }}</td>
                                <td>
                                    <span class="ui-clamp-2" :title="row.person_name">{{ row.person_name }}</span>
                                </td>
                                <td>
                                    <span class="ui-clamp-2" :title="row.position || ''">{{ row.position || '—' }}</span>
                                </td>
                                <td class="text-center">{{ row.office_phone || '—' }}</td>
                                <td class="text-center">{{ row.mobile_phone || '—' }}</td>
                                <td v-if="canManage" class="text-right whitespace-nowrap">
                                    <button type="button" class="ui-btn-ghost mr-1 !py-1 text-xs" @click="openEdit(row, group)">Засах</button>
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

            <!-- Tab 2: staff -->
            <div v-else class="ui-table-wrap overflow-x-auto">
                <table class="ui-table w-full min-w-[1400px] table-fixed">
                    <thead>
                        <tr>
                            <th class="w-10 text-center">№</th>
                            <th class="w-56">Байгууллага</th>
                            <th class="w-44">Нэгж</th>
                            <th class="w-64">Албан тушаал</th>
                            <th class="w-28">Овог</th>
                            <th class="w-28">Нэр</th>
                            <th class="w-16 text-center">Өрөө</th>
                            <th class="w-24 text-center">Ажлын утас</th>
                            <th class="w-28 text-center">Гар утас</th>
                            <th class="w-56">И-Мэйл хаяг</th>
                            <th v-if="canManage" class="w-20" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, index) in filteredStaff" :key="row.id">
                            <td class="text-center">{{ index + 1 }}</td>
                            <td>
                                <span class="ui-clamp-2" :title="row.organization">{{ row.organization }}</span>
                            </td>
                            <td>
                                <span class="ui-clamp-2" :title="row.unit || ''">{{ row.unit || '—' }}</span>
                            </td>
                            <td>
                                <span class="ui-clamp-2" :title="row.position || ''">{{ row.position || '—' }}</span>
                            </td>
                            <td>
                                <span class="ui-clamp-2" :title="row.last_name">{{ row.last_name }}</span>
                            </td>
                            <td>
                                <span class="ui-clamp-2" :title="row.first_name">{{ row.first_name }}</span>
                            </td>
                            <td class="text-center">{{ row.room || '—' }}</td>
                            <td class="text-center">{{ row.work_phone || '—' }}</td>
                            <td class="text-center">{{ row.mobile_phone || '—' }}</td>
                            <td>
                                <a
                                    v-if="row.email"
                                    :href="`mailto:${row.email}`"
                                    class="ui-clamp-2 break-all text-brand-navy-600 hover:underline"
                                    :title="row.email"
                                >
                                    {{ row.email }}
                                </a>
                                <span v-else>—</span>
                            </td>
                            <td v-if="canManage" class="text-right">
                                <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyStaff(row.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!filteredStaff.length">
                            <td :colspan="canManage ? 11 : 10" class="!py-12 text-center text-slate-400">
                                {{ search ? 'Хайлтад тохирох бүртгэл алга.' : 'Одоогоор бүртгэл алга. «Шинэ нэмэх» дарж оруулна уу.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: directory add / edit -->
        <Modal :show="showForm && canManage && isDirectory" max-width="2xl" @close="closeDirectoryForm">
            <form class="p-6" @submit.prevent="submit">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">
                            {{ isEditing ? 'Бүртгэл засах' : 'Шинэ бүртгэл' }}
                        </h3>
                        <p class="mt-0.5 text-sm text-slate-500">Утасны жагсаалт</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closeDirectoryForm">✕</button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="ui-label">Байгууллага / хэлтэс</label>
                        <input v-model="form.org_name" list="phone-org-names" class="ui-input" required />
                        <datalist id="phone-org-names">
                            <option v-for="name in orgNames" :key="name" :value="name" />
                        </datalist>
                        <InputError :message="form.errors.org_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Ангилал</label>
                        <select v-model="form.category" class="ui-input">
                            <option value="">Сонголтгүй</option>
                            <option v-for="(label, value) in categories" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Овог нэр</label>
                        <input v-model="form.person_name" class="ui-input" required />
                        <InputError :message="form.errors.person_name" class="mt-1" />
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
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeDirectoryForm">Болих</button>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Хадгалах</button>
                </div>
            </form>
        </Modal>

        <!-- Modal: staff add -->
        <Modal :show="showStaffForm && canManage && !isDirectory" max-width="2xl" @close="showStaffForm = false">
            <form class="p-6" @submit.prevent="submitStaff">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Шинэ бүртгэл</h3>
                        <p class="mt-0.5 text-sm text-slate-500">АЗДТГ-н албан хаагч</p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="showStaffForm = false">✕</button>
                </div>
                <div class="grid max-h-[65vh] gap-4 overflow-y-auto pr-1 md:grid-cols-2">
                    <div>
                        <label class="ui-label">Байгууллага</label>
                        <input v-model="staffForm.organization" list="staff-org-names" class="ui-input" required />
                        <datalist id="staff-org-names">
                            <option v-for="name in staffOrganizations" :key="name" :value="name" />
                        </datalist>
                        <InputError :message="staffForm.errors.organization" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Нэгж</label>
                        <input v-model="staffForm.unit" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Албан тушаал</label>
                        <input v-model="staffForm.position" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Овог</label>
                        <input v-model="staffForm.last_name" class="ui-input" required />
                        <InputError :message="staffForm.errors.last_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Нэр</label>
                        <input v-model="staffForm.first_name" class="ui-input" required />
                        <InputError :message="staffForm.errors.first_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Өрөө</label>
                        <input v-model="staffForm.room" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Ажлын утас</label>
                        <input v-model="staffForm.work_phone" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Гар утас</label>
                        <input v-model="staffForm.mobile_phone" class="ui-input" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="ui-label">И-Мэйл хаяг</label>
                        <input v-model="staffForm.email" type="email" class="ui-input" />
                        <InputError :message="staffForm.errors.email" class="mt-1" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="showStaffForm = false">Болих</button>
                    <button type="submit" class="ui-btn-primary" :disabled="staffForm.processing">Хадгалах</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
