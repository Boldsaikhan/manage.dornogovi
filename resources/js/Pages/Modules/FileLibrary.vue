<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { cellMatchesFilter, EMPTY_FILTER_HINT, EMPTY_FILTER_PLACEHOLDER } from '@/utils/columnFilter';

const props = defineProps({
    files: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({ q: '', folder: '' }),
    },
    canManage: { type: Boolean, default: false },
    maxUploadMb: { type: Number, default: 20 },
    allowedExtensions: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({ count: 0, total_size: 0, total_size_label: '0 B' }),
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const columns = [
    { key: 'name', label: 'Нэр' },
    { key: 'extension', label: 'Төрөл' },
    { key: 'size_label', label: 'Хэмжээ' },
    { key: 'folder', label: 'Хавтас' },
    { key: 'uploaded_by', label: 'Байршуулсан' },
    { key: 'created_at_label', label: 'Огноо' },
];

const columnFilters = reactive({});
columns.forEach((col) => { columnFilters[col.key] = ''; });

const searchKey = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/ө/g, 'о')
    .replace(/ү/g, 'у')
    .replace(/ё/g, 'е')
    .replace(/й/g, 'и');

const hasColumnFilters = computed(() => Object.values(columnFilters).some((v) => String(v).trim() !== ''));
const clearColumnFilters = () => Object.keys(columnFilters).forEach((key) => { columnFilters[key] = ''; });

const matchesFilters = (row) => Object.entries(columnFilters).every(([key, needle]) => (
    cellMatchesFilter(row[key], needle, { normalize: searchKey })
));

const visibleRows = computed(() => (
    hasColumnFilters.value ? props.files.filter(matchesFilters) : props.files
));

const query = ref(props.filters.q || '');
const activeFolder = ref(props.filters.folder || '');

watch(() => props.filters, (next) => {
    query.value = next?.q || '';
    activeFolder.value = next?.folder || '';
}, { deep: true });

let searchTimer = null;
const applyServerFilters = () => {
    router.get(route('file-library.index'), {
        q: query.value || undefined,
        folder: activeFolder.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyServerFilters, 280);
};

const selectFolder = (folder) => {
    activeFolder.value = folder || '';
    applyServerFilters();
};

const showUpload = ref(false);
const showEdit = ref(false);
const editing = ref(null);
const dragOver = ref(false);
const fileInput = ref(null);

const uploadForm = useForm({
    title: '',
    folder: '',
    file: null,
});

const editForm = useForm({
    title: '',
    folder: '',
});

const openUpload = () => {
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadForm.title = '';
    uploadForm.folder = activeFolder.value || '';
    uploadForm.file = null;
    showUpload.value = true;
};

const closeUpload = () => {
    showUpload.value = false;
    uploadForm.reset();
    uploadForm.clearErrors();
};

const pickFile = (file) => {
    if (! file) return;
    uploadForm.file = file;
    if (! uploadForm.title) {
        uploadForm.title = file.name.replace(/\.[^.]+$/, '');
    }
};

const onFileChange = (event) => {
    pickFile(event.target.files?.[0] ?? null);
    event.target.value = '';
};

const onDrop = (event) => {
    dragOver.value = false;
    pickFile(event.dataTransfer?.files?.[0] ?? null);
};

const submitUpload = () => {
    uploadForm.post(route('file-library.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => closeUpload(),
    });
};

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.title = row.title || row.original_name || '';
    editForm.folder = row.folder || '';
    showEdit.value = true;
};

const closeEdit = () => {
    showEdit.value = false;
    editing.value = null;
    editForm.reset();
};

const submitEdit = () => {
    if (! editing.value) return;
    editForm.patch(route('file-library.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
};

const destroyFile = (row) => {
    if (! window.confirm(`«${row.name}» файлыг устгах уу?`)) return;
    router.delete(route('file-library.destroy', row.id), { preserveScroll: true });
};

const downloadFile = (row) => {
    window.location.assign(row.download_url);
};

const extensionsHint = computed(() => (props.allowedExtensions || []).join(', '));
</script>

<template>
    <Head title="Файлын сан" />

    <AuthenticatedLayout title="Файлын сан">
        <div class="ui-page ui-page--tight">
            <section class="ui-card-pad space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="ui-title text-base">Файлын сан</h3>
                        <p class="ui-subtitle mt-1">
                            Баримт, загвар, хавсралтыг байршуулж, харах, татах.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="canManage"
                            type="button"
                            class="ui-btn-accent"
                            @click="openUpload"
                        >
                            Файл байршуулах
                        </button>
                    </div>
                </div>

                <div
                    v-if="flashSuccess"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
                >
                    {{ flashError }}
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="ui-stat">
                        <div class="ui-stat-label">Файл</div>
                        <div class="ui-stat-value">{{ stats.count }}</div>
                    </div>
                    <div class="ui-stat">
                        <div class="ui-stat-label">Нийт хэмжээ</div>
                        <div class="ui-stat-value !text-lg">{{ stats.total_size_label }}</div>
                    </div>
                    <div class="ui-stat">
                        <div class="ui-stat-label">Хавтас</div>
                        <div class="ui-stat-value">{{ folders.length }}</div>
                    </div>
                </div>
            </section>

            <section class="ui-register">
                <div class="ui-register__filters flex flex-wrap items-end gap-3 p-3">
                    <div class="min-w-[12rem] flex-1">
                        <label class="ui-label" for="file-library-search">Файл хайх</label>
                        <input
                            id="file-library-search"
                            v-model="query"
                            type="search"
                            class="ui-input"
                            placeholder="нэр, төрөл…"
                            @input="onSearchInput"
                        >
                    </div>
                    <div class="min-w-[10rem]">
                        <label class="ui-label" for="file-library-folder">Хавтас</label>
                        <select
                            id="file-library-folder"
                            v-model="activeFolder"
                            class="ui-input"
                            @change="applyServerFilters"
                        >
                            <option value="">Бүгд</option>
                            <option
                                v-for="folder in folders"
                                :key="folder"
                                :value="folder"
                            >
                                {{ folder }}
                            </option>
                        </select>
                    </div>
                    <p class="pb-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ visibleRows.length }} / {{ files.length }}
                    </p>
                </div>

                <div
                    v-if="folders.length"
                    class="flex flex-wrap gap-2 border-b border-slate-200 px-3 py-2 dark:border-slate-700"
                >
                    <button
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="! activeFolder
                            ? 'bg-brand-navy-700 text-white dark:bg-brand-navy-500'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                        @click="selectFolder('')"
                    >
                        Бүгд
                    </button>
                    <button
                        v-for="folder in folders"
                        :key="`chip-${folder}`"
                        type="button"
                        class="rounded-full px-3 py-1 text-xs font-medium transition"
                        :class="activeFolder === folder
                            ? 'bg-brand-navy-700 text-white dark:bg-brand-navy-500'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                        @click="selectFolder(folder)"
                    >
                        {{ folder }}
                    </button>
                </div>

                <div class="ui-table-wrap !rounded-none !border-0 !shadow-none">
                    <table class="ui-register__table">
                        <thead>
                            <tr>
                                <th class="ui-register__cell--no">№</th>
                                <th
                                    v-for="col in columns"
                                    :key="col.key"
                                >
                                    {{ col.label }}
                                </th>
                                <th class="text-right">Үйлдэл</th>
                            </tr>
                            <tr class="ui-filters ui-register__filters">
                                <th />
                                <th
                                    v-for="col in columns"
                                    :key="`f-${col.key}`"
                                >
                                    <input
                                        v-model="columnFilters[col.key]"
                                        type="text"
                                        class="ui-input !py-1 text-xs"
                                        :placeholder="EMPTY_FILTER_PLACEHOLDER"
                                        :title="EMPTY_FILTER_HINT"
                                    >
                                </th>
                                <th class="text-right">
                                    <button
                                        v-if="hasColumnFilters"
                                        type="button"
                                        class="ui-btn-ghost !px-2 !py-1 text-xs"
                                        @click="clearColumnFilters"
                                    >
                                        Цэвэр
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="! visibleRows.length">
                                <td
                                    :colspan="columns.length + 2"
                                    class="ui-register__empty"
                                >
                                    Файл олдсонгүй.
                                </td>
                            </tr>
                            <tr
                                v-for="(row, index) in visibleRows"
                                :key="row.id"
                            >
                                <td class="ui-register__cell--no">{{ index + 1 }}</td>
                                <td class="font-medium text-brand-navy-800 dark:text-brand-navy-200">
                                    <span class="ui-clamp-1" :title="row.original_name">{{ row.name }}</span>
                                    <span
                                        v-if="row.title && row.title !== row.original_name"
                                        class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-slate-400 ui-clamp-1"
                                    >
                                        {{ row.original_name }}
                                    </span>
                                </td>
                                <td class="uppercase text-slate-500 dark:text-slate-400">{{ row.extension }}</td>
                                <td class="tabular-nums">{{ row.size_label }}</td>
                                <td>
                                    <span class="ui-clamp-1">{{ row.folder || '—' }}</span>
                                </td>
                                <td>
                                    <span class="ui-clamp-1">{{ row.uploaded_by || '—' }}</span>
                                </td>
                                <td class="tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ row.created_at_label || '—' }}
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="ui-btn-ghost !px-2 !py-1 text-xs"
                                            @click="downloadFile(row)"
                                        >
                                            Татах
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="ui-btn-ghost !px-2 !py-1 text-xs"
                                            @click="openEdit(row)"
                                        >
                                            Засах
                                        </button>
                                        <button
                                            v-if="canManage"
                                            type="button"
                                            class="ui-btn-danger !px-2 !py-1 text-xs"
                                            @click="destroyFile(row)"
                                        >
                                            Устгах
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <Modal
            :show="showUpload"
            max-width="lg"
            @close="closeUpload"
        >
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="ui-title text-base">Файл байршуулах</h3>
                    <p class="ui-subtitle mt-1">
                        Дээд хэмжээ {{ maxUploadMb }} MB. Зөвшөөрөгдсөн:
                        {{ extensionsHint || '—' }}
                    </p>
                </div>

                <div>
                    <label class="ui-label" for="upload-title">Гарчиг</label>
                    <input
                        id="upload-title"
                        v-model="uploadForm.title"
                        type="text"
                        class="ui-input"
                        placeholder="Сонголттой"
                    >
                    <InputError :message="uploadForm.errors.title" />
                </div>

                <div>
                    <label class="ui-label" for="upload-folder">Хавтас</label>
                    <input
                        id="upload-folder"
                        v-model="uploadForm.folder"
                        type="text"
                        class="ui-input"
                        list="file-library-folder-options"
                        placeholder="жишээ: Загвар"
                    >
                    <datalist id="file-library-folder-options">
                        <option
                            v-for="folder in folders"
                            :key="`opt-${folder}`"
                            :value="folder"
                        />
                    </datalist>
                    <InputError :message="uploadForm.errors.folder" />
                </div>

                <div
                    class="rounded-xl border-2 border-dashed px-4 py-8 text-center transition"
                    :class="dragOver
                        ? 'border-brand-orange-400 bg-brand-orange-50/60 dark:border-brand-orange-500 dark:bg-brand-orange-950/20'
                        : 'border-slate-300 dark:border-slate-600'"
                    @dragover.prevent="dragOver = true"
                    @dragleave.prevent="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Файл чирж тавих эсвэл сонгох
                    </p>
                    <p
                        v-if="uploadForm.file"
                        class="mt-2 text-sm font-medium text-brand-navy-800 dark:text-brand-navy-200"
                    >
                        {{ uploadForm.file.name }}
                    </p>
                    <button
                        type="button"
                        class="ui-btn-ghost mt-3"
                        @click="fileInput?.click()"
                    >
                        Файл сонгох
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        :accept="allowedExtensions.map((ext) => `.${ext}`).join(',')"
                        @change="onFileChange"
                    >
                    <InputError class="mt-2" :message="uploadForm.errors.file" />
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="ui-btn-ghost"
                        @click="closeUpload"
                    >
                        Болих
                    </button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="uploadForm.processing || ! uploadForm.file"
                        @click="submitUpload"
                    >
                        {{ uploadForm.processing ? 'Байршуулж байна…' : 'Байршуулах' }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal
            :show="showEdit"
            max-width="lg"
            @close="closeEdit"
        >
            <div class="space-y-4 p-5">
                <div>
                    <h3 class="ui-title text-base">Файлын мэдээлэл</h3>
                    <p class="ui-subtitle mt-1">{{ editing?.original_name }}</p>
                </div>

                <div>
                    <label class="ui-label" for="edit-title">Гарчиг</label>
                    <input
                        id="edit-title"
                        v-model="editForm.title"
                        type="text"
                        class="ui-input"
                    >
                    <InputError :message="editForm.errors.title" />
                </div>

                <div>
                    <label class="ui-label" for="edit-folder">Хавтас</label>
                    <input
                        id="edit-folder"
                        v-model="editForm.folder"
                        type="text"
                        class="ui-input"
                        list="file-library-folder-options-edit"
                    >
                    <datalist id="file-library-folder-options-edit">
                        <option
                            v-for="folder in folders"
                            :key="`edit-opt-${folder}`"
                            :value="folder"
                        />
                    </datalist>
                    <InputError :message="editForm.errors.folder" />
                </div>

                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="ui-btn-ghost"
                        @click="closeEdit"
                    >
                        Болих
                    </button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="editForm.processing"
                        @click="submitEdit"
                    >
                        Хадгалах
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
