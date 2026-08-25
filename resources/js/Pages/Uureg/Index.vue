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
                note: t.note ?? '',
                progress: t.progress ?? 0,
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

const saveProgress = (taskId) => {
    const raw = drafts[taskId]?.progress;
    let n = Number.parseInt(raw, 10);
    if (Number.isNaN(n)) n = 0;
    n = Math.min(100, Math.max(0, n));
    drafts[taskId].progress = n;
    saveField(taskId, 'progress', n);
};

const removeRow = (taskId) => {
    if (!confirm('Энэ мөрийг устгах уу?')) return;
    router.delete(route('tasks.destroy', taskId), { preserveScroll: true });
};

const latestDocument = computed(() => props.documents?.[0] ?? null);

const pickWordFile = () => {
    fileInput.value?.click();
};

const onFileChange = (e) => {
    uploadForm.file = e.target.files?.[0] ?? null;
    if (uploadForm.file) {
        submitUpload();
    }
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

/** Үүрэг чиглэлийн бичвэрийг бүтэн харуулах мөрийн тоо */
const textRows = (text) => {
    const value = String(text ?? '');
    if (!value.trim()) return 2;
    const lines = value.split('\n');
    const estimated = lines.reduce((sum, line) => sum + Math.max(1, Math.ceil(line.length / 90)), 0);
    return Math.max(2, Math.min(30, estimated));
};

const autoGrow = (event) => {
    const el = event.target;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
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
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        class="hidden"
                        @change="onFileChange"
                    />
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-primary"
                        :disabled="uploadForm.processing"
                        @click="pickWordFile"
                    >
                        {{ uploadForm.processing ? 'Оруулж байна…' : 'Word оруулах' }}
                    </button>
                    <a
                        v-if="latestDocument"
                        :href="route('tasks.documents.download', latestDocument.id)"
                        class="ui-btn-ghost"
                    >
                        Word татах
                    </a>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="addRow"
                    >
                        Мөр нэмэх
                    </button>
                </div>
            </div>
            <p v-if="uploadForm.errors.file" class="text-sm text-red-600">{{ uploadForm.errors.file }}</p>

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

            <!-- Оруулсан Word файлын товч жагсаалт -->
            <div v-if="documents.length" class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="doc in documents"
                        :key="doc.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-800">{{ doc.original_name }}</p>
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
                                class="ui-btn-ghost !py-1.5 text-xs"
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
                                class="ui-icon-btn"
                                title="Устгах"
                                aria-label="Устгах"
                                @click="removeDocument(doc.id)"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6" />
                                </svg>
                            </button>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Үүрэг чиглэл -->
            <div v-if="isDirective" class="ui-table-wrap w-full overflow-x-auto">
                <table class="ui-table w-full table-fixed">
                    <colgroup>
                        <col class="w-12" />
                        <col />
                        <col class="w-[12%]" />
                        <col class="w-[14%]" />
                        <col class="w-[16%]" />
                        <col class="w-24" />
                        <col v-if="canManage" class="w-12" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">№</th>
                            <th>Үүрэг чиглэл</th>
                            <th>Хариуцах эзэн</th>
                            <th>Хяналт тавих албан тушаалтан</th>
                            <th>Хэрэгжилт</th>
                            <th class="text-center">Биелэлтийн хувь</th>
                            <th v-if="canManage" class="text-center" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in tasks" :key="task.id">
                            <td class="text-center font-semibold text-slate-500">{{ task.no }}</td>
                            <td class="!align-top">
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    :rows="textRows(drafts[task.id].text)"
                                    class="ui-table-input max-h-none resize-none overflow-hidden whitespace-pre-wrap"
                                    @input="autoGrow"
                                    @change="saveField(task.id, 'text', drafts[task.id].text)"
                                />
                                <div v-else class="whitespace-pre-wrap px-1 py-0.5 leading-snug">{{ task.text || '—' }}</div>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'responsible', drafts[task.id].responsible)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.responsible || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'collaborator', drafts[task.id].collaborator)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.collaborator || '—' }}</span>
                            </td>
                            <td>
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].note"
                                    :rows="textRows(drafts[task.id].note)"
                                    class="ui-table-input resize-none overflow-hidden whitespace-pre-wrap"
                                    placeholder="Хэрэгжилт…"
                                    @input="autoGrow"
                                    @change="saveField(task.id, 'note', drafts[task.id].note)"
                                />
                                <div v-else class="whitespace-pre-wrap px-1 py-0.5 leading-snug">{{ task.note || '—' }}</div>
                            </td>
                            <td class="text-center align-middle">
                                <div v-if="canManage && drafts[task.id]" class="inline-flex items-center gap-0.5">
                                    <input
                                        v-model.number="drafts[task.id].progress"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="ui-table-input !w-14 text-center font-semibold"
                                        @change="saveProgress(task.id)"
                                    />
                                    <span class="text-xs font-medium text-slate-500">%</span>
                                </div>
                                <span
                                    v-else
                                    class="inline-flex min-w-[3rem] justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="(task.progress ?? 0) >= 100
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : (task.progress ?? 0) > 0
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ task.progress ?? 0 }}%
                                </span>
                            </td>
                            <td v-if="canManage" class="text-center align-middle">
                                <button
                                    type="button"
                                    class="ui-icon-btn"
                                    title="Устгах"
                                    aria-label="Устгах"
                                    @click="removeRow(task.id)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6" />
                                    </svg>
                                </button>
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

            <!-- Бэлтгэл ажил хангах төлөвлөгөө -->
            <div v-else class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[1120px]">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">№</th>
                            <th class="w-36">Ажлын чиглэл</th>
                            <th>Арга хэмжээ</th>
                            <th class="w-28">Хугацаа</th>
                            <th class="w-36">Хариуцах эзэн</th>
                            <th class="w-40">Хамтран хэрэгжүүлэх</th>
                            <th class="w-40">Хэрэгжилт</th>
                            <th class="w-28 text-center">Биелэлтийн хувь</th>
                            <th v-if="canManage" class="w-12 text-center" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in tasks" :key="task.id">
                            <td class="text-center font-semibold text-slate-500">{{ task.no }}</td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].sector"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'sector', drafts[task.id].sector)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.sector || '—' }}</span>
                            </td>
                            <td class="min-w-[220px]">
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    rows="3"
                                    class="ui-table-input resize-y"
                                    @change="saveField(task.id, 'text', drafts[task.id].text)"
                                />
                                <span v-else class="block whitespace-pre-wrap px-1 py-0.5 leading-snug">{{ task.text || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].period"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'period', drafts[task.id].period)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.period || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'responsible', drafts[task.id].responsible)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.responsible || '—' }}</span>
                            </td>
                            <td>
                                <input
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    class="ui-table-input"
                                    @change="saveField(task.id, 'collaborator', drafts[task.id].collaborator)"
                                />
                                <span v-else class="block px-1 py-0.5">{{ task.collaborator || '—' }}</span>
                            </td>
                            <td>
                                <textarea
                                    v-if="canManage && drafts[task.id]"
                                    v-model="drafts[task.id].note"
                                    rows="3"
                                    class="ui-table-input resize-y"
                                    placeholder="Хэрэгжилт…"
                                    @change="saveField(task.id, 'note', drafts[task.id].note)"
                                />
                                <span v-else class="block whitespace-pre-wrap px-1 py-0.5 leading-snug">{{ task.note || '—' }}</span>
                            </td>
                            <td class="text-center align-middle">
                                <div v-if="canManage && drafts[task.id]" class="inline-flex items-center gap-0.5">
                                    <input
                                        v-model.number="drafts[task.id].progress"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="ui-table-input !w-14 text-center font-semibold"
                                        @change="saveProgress(task.id)"
                                    />
                                    <span class="text-xs font-medium text-slate-500">%</span>
                                </div>
                                <span
                                    v-else
                                    class="inline-flex min-w-[3rem] justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="(task.progress ?? 0) >= 100
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : (task.progress ?? 0) > 0
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ task.progress ?? 0 }}%
                                </span>
                            </td>
                            <td v-if="canManage" class="text-center align-middle">
                                <button
                                    type="button"
                                    class="ui-icon-btn"
                                    title="Устгах"
                                    aria-label="Устгах"
                                    @click="removeRow(task.id)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14zM10 11v6M14 11v6" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!tasks.length">
                            <td :colspan="canManage ? 9 : 8" class="!py-14 text-center text-slate-400">
                                Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
