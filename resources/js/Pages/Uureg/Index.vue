<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    kind: { type: String, required: true },
    source: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const kinds = [
    { key: 'directive', label: 'Үүрэг чиглэл' },
    { key: 'prep_plan', label: 'Бэлтгэл ажил хангах төлөвлөгөө' },
];

const isDirective = computed(() => props.kind === 'directive');

const drafts = reactive({});
const fileInput = ref(null);

const uploadForm = useForm({
    kind: props.kind,
    file: null,
});

watch(
    () => props.kind,
    (k) => {
        uploadForm.kind = k;
        uploadForm.file = null;
        if (fileInput.value) fileInput.value.value = '';
    },
);

watch(
    () => props.tasks,
    (list) => {
        list.forEach((t) => {
            drafts[t.id] = {
                text: t.text ?? '',
                period: t.period ?? '',
                responsible: t.responsible ?? '',
                collaborator: t.collaborator ?? '',
                sector: t.sector ?? '',
            };
        });
    },
    { immediate: true, deep: true },
);

const switchKind = (key) => {
    router.get(route('tasks.index'), { kind: key }, { preserveState: false });
};

const addRow = () => {
    useForm({
        kind: props.kind,
        text: '',
        period: '',
        responsible: '',
        collaborator: '',
        sector: '',
    }).post(route('tasks.store'), { preserveScroll: true });
};

const saveField = (taskId, field, value) => {
    router.patch(
        route('tasks.update', taskId),
        { [field]: value },
        { preserveScroll: true, preserveState: true },
    );
};

const removeRow = (taskId) => {
    if (!confirm('Энэ мөрийг устгах уу?')) return;
    router.delete(route('tasks.destroy', taskId), { preserveScroll: true });
};

const onFileChange = (e) => {
    uploadForm.file = e.target.files?.[0] ?? null;
};

const submitUpload = () => {
    if (!uploadForm.file) return;
    uploadForm.post(route('tasks.documents.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
};

const importDocument = (id) => {
    if (!confirm('Энэ файлын хүснэгтийг уншиж, одоо байгаа мөрүүдийг солих уу?')) return;
    router.post(
        route('tasks.documents.import', id),
        { replace: true },
        { preserveScroll: true },
    );
};

const removeDocument = (id) => {
    if (!confirm('Энэ файлыг устгах уу?')) return;
    router.delete(route('tasks.documents.destroy', id), { preserveScroll: true });
};

const formatSize = (bytes) => {
    if (!bytes) return '—';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <Head :title="source.name" />

    <AuthenticatedLayout :title="source.name">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Үүрэг даалгавар</h2>
                    <p class="ui-subtitle">Word файлын хүснэгтийг уншиж, мөр бүрийг шууд засварлана.</p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="ui-btn-accent"
                    @click="addRow"
                >
                    Мөр нэмэх
                </button>
            </div>

            <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <Link
                    v-for="item in kinds"
                    :key="item.key"
                    :href="route('tasks.index', { kind: item.key })"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="kind === item.key
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click.prevent="switchKind(item.key)"
                >
                    {{ item.label }}
                </Link>
            </div>

            <!-- Word файлууд -->
            <section class="ui-card-pad space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Word файл</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            .doc, .docx (хамгийн ихдээ 20 MB). .docx файлын хүснэгтийг оруулмагц доорх хүснэгт болгож уншина.
                        </p>
                    </div>
                </div>

                <form
                    v-if="canManage"
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="submitUpload"
                >
                    <div class="min-w-[220px] flex-1">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Файл сонгох</label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            class="ui-input file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                            @change="onFileChange"
                        />
                        <p v-if="uploadForm.errors.file" class="mt-1 text-xs text-red-600">{{ uploadForm.errors.file }}</p>
                    </div>
                    <button
                        type="submit"
                        class="ui-btn-primary"
                        :disabled="!uploadForm.file || uploadForm.processing"
                    >
                        {{ uploadForm.processing ? 'Оруулж байна…' : 'Оруулах' }}
                    </button>
                </form>

                <ul v-if="documents.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    <li
                        v-for="doc in documents"
                        :key="doc.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800">{{ doc.original_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatSize(doc.size) }}
                                <span v-if="doc.uploader"> · {{ doc.uploader }}</span>
                                <span v-if="doc.uploaded_at"> · {{ doc.uploaded_at }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                v-if="canManage"
                                type="button"
                                class="ui-btn-primary !py-1.5 text-xs"
                                title="Файлын хүснэгтийг доорх хүснэгт болгож уншина"
                                @click="importDocument(doc.id)"
                            >
                                Хүснэгт болгох
                            </button>
                            <a
                                :href="route('tasks.documents.download', doc.id)"
                                class="ui-btn-ghost !py-1.5 text-xs"
                            >
                                Татах
                            </a>
                            <button
                                v-if="canManage"
                                type="button"
                                class="ui-btn-danger !py-1.5 text-xs"
                                @click="removeDocument(doc.id)"
                            >
                                Устгах
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                    Одоогоор Word файл алга.
                </p>
            </section>

            <!-- Үүрэг чиглэл -->
            <div v-if="isDirective" class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th class="w-14">№</th>
                            <th>Үүрэг чиглэл</th>
                            <th class="w-48">Хариуцах эзэн</th>
                            <th class="w-56">Хяналт тавих албан тушаалтан</th>
                            <th v-if="canManage" class="w-20" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in tasks" :key="task.id">
                            <td class="font-semibold text-slate-500">{{ task.no }}</td>
                            <td>
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    rows="2"
                                    class="ui-input"
                                    @change="saveField(task.id, 'text', drafts[task.id].text)"
                                />
                                <span v-else class="whitespace-pre-wrap">{{ task.text || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    class="ui-input"
                                    @change="saveField(task.id, 'responsible', drafts[task.id].responsible)"
                                />
                                <span v-else>{{ task.responsible || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    class="ui-input"
                                    @change="saveField(task.id, 'collaborator', drafts[task.id].collaborator)"
                                />
                                <span v-else>{{ task.collaborator || '—' }}</span>
                            </td>
                            <td v-if="canManage" class="text-right">
                                <button type="button" class="ui-btn-danger !py-1 text-xs" @click="removeRow(task.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!tasks.length">
                            <td :colspan="canManage ? 5 : 4" class="!py-14 text-center text-slate-400">
                                Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Бэлтгэл ажил хангах төлөвлөгөө -->
            <div v-else class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[960px]">
                    <thead>
                        <tr>
                            <th class="w-14">№</th>
                            <th class="w-44">Ажлын чиглэл</th>
                            <th>Арга хэмжээ</th>
                            <th class="w-36">Хугацаа</th>
                            <th class="w-44">Хариуцах эзэн</th>
                            <th class="w-52">Хамтран хэрэгжүүлэх албан тушаалтан</th>
                            <th v-if="canManage" class="w-20" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in tasks" :key="task.id">
                            <td class="font-semibold text-slate-500">{{ task.no }}</td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].sector"
                                    class="ui-input"
                                    @change="saveField(task.id, 'sector', drafts[task.id].sector)"
                                />
                                <span v-else>{{ task.sector || '—' }}</span>
                            </td>
                            <td>
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    rows="2"
                                    class="ui-input"
                                    @change="saveField(task.id, 'text', drafts[task.id].text)"
                                />
                                <span v-else class="whitespace-pre-wrap">{{ task.text || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].period"
                                    class="ui-input"
                                    @change="saveField(task.id, 'period', drafts[task.id].period)"
                                />
                                <span v-else>{{ task.period || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    class="ui-input"
                                    @change="saveField(task.id, 'responsible', drafts[task.id].responsible)"
                                />
                                <span v-else>{{ task.responsible || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    class="ui-input"
                                    @change="saveField(task.id, 'collaborator', drafts[task.id].collaborator)"
                                />
                                <span v-else>{{ task.collaborator || '—' }}</span>
                            </td>
                            <td v-if="canManage" class="text-right">
                                <button type="button" class="ui-btn-danger !py-1 text-xs" @click="removeRow(task.id)">Устгах</button>
                            </td>
                        </tr>
                        <tr v-if="!tasks.length">
                            <td :colspan="canManage ? 7 : 6" class="!py-14 text-center text-slate-400">
                                Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
