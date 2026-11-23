<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import { cellMatchesFilter, EMPTY_FILTER_HINT, EMPTY_FILTER_PLACEHOLDER } from '@/utils/columnFilter';

const props = defineProps({
    meetings: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    sttReady: { type: Boolean, default: false },
    sttHint: { type: String, default: '' },
    maxUploadMb: { type: Number, default: 25 },
    hasProcessing: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

const columns = [
    { key: 'title', label: 'Гарчиг' },
    { key: 'held_at_label', label: 'Огноо' },
    { key: 'transcription_label', label: 'Боловсруулалт' },
    { key: 'status_label', label: 'Төлөв' },
    { key: 'creator', label: 'Бүртгэсэн' },
];

const filters = reactive({});
columns.forEach((col) => { filters[col.key] = ''; });

const hasFilters = computed(() => Object.values(filters).some((v) => String(v).trim() !== ''));
const clearFilters = () => Object.keys(filters).forEach((key) => { filters[key] = ''; });

const searchKey = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/ө/g, 'о')
    .replace(/ү/g, 'у')
    .replace(/ё/g, 'е')
    .replace(/й/g, 'и');

const matchesFilters = (row) => Object.entries(filters).every(([key, needle]) => (
    cellMatchesFilter(row[key], needle, { normalize: searchKey })
));

const visibleRows = computed(() => (hasFilters.value ? props.meetings.filter(matchesFilters) : props.meetings));

const showModal = ref(false);
const editingId = ref(null);
const audioFile = ref(null);
const audioPreviewName = ref('');
const fileInput = ref(null);
const dragOver = ref(false);

const form = useForm({
    title: '',
    held_at: '',
    minutes: '',
    transcript: '',
    status: 'draft',
    audio: null,
});

const isEditing = computed(() => editingId.value != null);
const editingRow = computed(() => props.meetings.find((m) => m.id === editingId.value) ?? null);

const transcriptionBadge = (status) => {
    const map = {
        none: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        processing: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        failed: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
    };
    return map[status] || map.none;
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.title = '';
    form.held_at = new Date().toISOString().slice(0, 16);
    form.minutes = '';
    form.transcript = '';
    form.status = 'draft';
    form.audio = null;
    audioFile.value = null;
    audioPreviewName.value = '';
    showModal.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    form.clearErrors();
    form.title = row.title || '';
    form.held_at = row.held_at || '';
    form.minutes = row.minutes || '';
    form.transcript = row.transcript || '';
    form.status = row.status || 'draft';
    form.audio = null;
    audioFile.value = null;
    audioPreviewName.value = row.audio_name || '';
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    stopRecording();
};

watch(
    () => props.meetings,
    (list) => {
        if (! editingId.value) return;
        const row = list.find((m) => m.id === editingId.value);
        if (! row) return;
        if (row.transcription_status === 'ready' || row.transcription_status === 'failed') {
            form.minutes = row.minutes || form.minutes;
            form.transcript = row.transcript || form.transcript;
            audioPreviewName.value = row.audio_name || audioPreviewName.value;
        }
    },
    { deep: true },
);

const setAudio = (file) => {
    if (! file) return;
    const maxBytes = props.maxUploadMb * 1024 * 1024;
    if (file.size > maxBytes) {
        form.setError('audio', `Файлын хэмжээ ${props.maxUploadMb}MB-аас хэтрэхгүй.`);
        return;
    }
    form.clearErrors('audio');
    audioFile.value = file;
    audioPreviewName.value = file.name;
    form.audio = file;
};

const onFileChange = (event) => {
    const file = event.target.files?.[0];
    setAudio(file);
};

const onDrop = (event) => {
    dragOver.value = false;
    const file = event.dataTransfer?.files?.[0];
    setAudio(file);
};

const submit = () => {
    if (isEditing.value) {
        form.audio = null;
        form.patch(route('meetings.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
        return;
    }

    form.audio = audioFile.value;
    form.post(route('meetings.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            audioFile.value = null;
            closeModal();
        },
    });
};

const uploadAudioToExisting = () => {
    if (! editingId.value || ! audioFile.value) return;
    const upload = useForm({ audio: audioFile.value });
    upload.post(route('meetings.audio', editingId.value), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            audioFile.value = null;
        },
    });
};

const removeRow = (row) => {
    if (! confirm(`«${row.title}» тэмдэглэлийг устгах уу?`)) return;
    router.delete(route('meetings.destroy', row.id), { preserveScroll: true });
};

const retryTranscription = (row) => {
    router.post(route('meetings.retry', row.id), {}, { preserveScroll: true });
};

/* ── Browser audio record ─────────────────────────────────────────── */
const recording = ref(false);
const recordSeconds = ref(0);
let mediaRecorder = null;
let recordTimer = null;
let recordChunks = [];

const recordLabel = computed(() => {
    const m = String(Math.floor(recordSeconds.value / 60)).padStart(2, '0');
    const s = String(recordSeconds.value % 60).padStart(2, '0');
    return `${m}:${s}`;
});

const startRecording = async () => {
    if (! navigator.mediaDevices?.getUserMedia) {
        form.setError('audio', 'Энэ браузер микрофон дэмжихгүй байна.');
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        recordChunks = [];
        const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
            ? 'audio/webm;codecs=opus'
            : 'audio/webm';
        mediaRecorder = new MediaRecorder(stream, { mimeType: mime });
        mediaRecorder.ondataavailable = (e) => {
            if (e.data?.size) recordChunks.push(e.data);
        };
        mediaRecorder.onstop = () => {
            stream.getTracks().forEach((t) => t.stop());
            const blob = new Blob(recordChunks, { type: mime.split(';')[0] });
            const file = new File([blob], `recording-${Date.now()}.webm`, { type: blob.type });
            setAudio(file);
        };
        mediaRecorder.start(250);
        recording.value = true;
        recordSeconds.value = 0;
        recordTimer = setInterval(() => { recordSeconds.value += 1; }, 1000);
    } catch {
        form.setError('audio', 'Микрофон руу хандах зөвшөөрөл олдсонгүй.');
    }
};

const stopRecording = () => {
    if (recordTimer) {
        clearInterval(recordTimer);
        recordTimer = null;
    }
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }
    mediaRecorder = null;
    recording.value = false;
};

/* ── Poll while processing ────────────────────────────────────────── */
let pollTimer = null;

const pollIfNeeded = () => {
    if (! props.hasProcessing) return;
    pollTimer = setTimeout(() => {
        router.reload({
            only: ['meetings', 'hasProcessing'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                pollTimer = null;
                pollIfNeeded();
            },
        });
    }, 4000);
};

onMounted(() => {
    pollIfNeeded();
    const params = new URLSearchParams(window.location.search);
    const openId = Number(params.get('open') || 0);
    if (openId) {
        const row = props.meetings.find((m) => m.id === openId);
        if (row) openEdit(row);
    }
});

watch(() => props.hasProcessing, (v) => {
    if (v && ! pollTimer) pollIfNeeded();
});

onBeforeUnmount(() => {
    if (pollTimer) clearTimeout(pollTimer);
    stopRecording();
});
</script>

<template>
    <AuthenticatedLayout title="Хурлын тэмдэглэл">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="ui-title">Хурлын тэмдэглэл</h1>
                    <p class="ui-subtitle mt-1 max-w-2xl">
                        Аудио оруулаад эсвэл шууд бичлэг хийгээд монгол хэлний текст тэмдэглэл авна.
                        Бэлэн болсон текстийг засаж хадгална.
                    </p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="ui-btn-accent"
                    @click="openCreate"
                >
                    + Шинэ тэмдэглэл
                </button>
            </div>

            <div
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
            >
                {{ flashError }}
            </div>

            <div
                v-if="!sttReady"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100"
            >
                <p class="font-medium">Аудио → текст одоогоор идэвхгүй</p>
                <p class="mt-1 opacity-90">{{ sttHint }}</p>
                <p class="mt-1 text-xs opacity-75">Гар аргаар тэмдэглэл бичиж хадгалах боломжтой.</p>
            </div>

            <div class="ui-card overflow-hidden">
                <TableScrollViewport>
                    <div class="ui-register">
                        <table class="ui-register__table">
                            <colgroup>
                                <col style="width: 3.5rem" />
                                <col style="width: 28%" />
                                <col style="width: 12%" />
                                <col style="width: 16%" />
                                <col style="width: 12%" />
                                <col style="width: 14%" />
                                <col v-if="canManage" style="width: 7rem" />
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="ui-register__cell--no">№</th>
                                    <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                                    <th v-if="canManage" class="ui-sticky-actions">Үйлдэл</th>
                                </tr>
                                <tr class="ui-filters ui-register__filters">
                                    <th />
                                    <th v-for="col in columns" :key="`f-${col.key}`">
                                        <input
                                            v-model="filters[col.key]"
                                            type="text"
                                            class="ui-input !py-1 text-xs"
                                            :placeholder="EMPTY_FILTER_PLACEHOLDER"
                                            :title="EMPTY_FILTER_HINT"
                                        />
                                    </th>
                                    <th v-if="canManage" class="ui-sticky-actions">
                                        <button
                                            v-if="hasFilters"
                                            type="button"
                                            class="ui-btn-ghost !px-2 !py-1 text-xs"
                                            @click="clearFilters"
                                        >
                                            Цэвэр
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visibleRows.length">
                                    <td
                                        :colspan="columns.length + (canManage ? 2 : 1)"
                                        class="ui-register__empty !py-14 text-center text-slate-400"
                                    >
                                        Одоогоор бүртгэл алга
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, i) in visibleRows"
                                    :key="row.id"
                                    class="cursor-pointer"
                                    @click="openEdit(row)"
                                >
                                    <td class="ui-register__cell--no">{{ i + 1 }}</td>
                                    <td>
                                        <div class="ui-clamp-2 font-medium text-slate-800 dark:text-slate-100">
                                            {{ row.title }}
                                        </div>
                                        <div v-if="row.has_audio" class="mt-0.5 text-xs text-slate-400">
                                            Аудио · {{ row.audio_name || 'файл' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ row.held_at_label || '—' }}
                                    </td>
                                    <td>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="transcriptionBadge(row.transcription_status)"
                                        >
                                            {{ row.transcription_label }}
                                        </span>
                                        <div
                                            v-if="row.transcription_status === 'failed' && row.transcription_error"
                                            class="ui-clamp-1 mt-1 text-xs text-rose-500"
                                            :title="row.transcription_error"
                                        >
                                            {{ row.transcription_error }}
                                        </div>
                                    </td>
                                    <td>{{ row.status_label }}</td>
                                    <td class="ui-clamp-1 text-slate-500">{{ row.creator || '—' }}</td>
                                    <td v-if="canManage" class="ui-sticky-actions" @click.stop>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                class="ui-icon-btn"
                                                title="Засах"
                                                @click="openEdit(row)"
                                            >
                                                ✎
                                            </button>
                                            <button
                                                v-if="row.transcription_status === 'failed'"
                                                type="button"
                                                class="ui-icon-btn"
                                                title="Дахин боловсруулах"
                                                @click="retryTranscription(row)"
                                            >
                                                ↻
                                            </button>
                                            <button
                                                type="button"
                                                class="ui-icon-btn ui-icon-btn--danger"
                                                title="Устгах"
                                                @click="removeRow(row)"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableScrollViewport>
            </div>
        </div>

        <Modal :show="showModal" max-width="4xl" @close="closeModal">
            <div class="space-y-4 p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy-800 dark:text-slate-100">
                            {{ isEditing ? 'Тэмдэглэл засах' : 'Шинэ хурлын тэмдэглэл' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Аудио → Whisper (монгол) → засварлах текст
                        </p>
                    </div>
                    <button type="button" class="ui-btn-ghost !px-2 !py-1" @click="closeModal">Хаах</button>
                </div>

                <div
                    v-if="editingRow?.transcription_status === 'processing' || editingRow?.transcription_status === 'pending'"
                    class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100"
                >
                    <span class="inline-block h-3 w-3 animate-pulse rounded-full bg-sky-500" />
                    Текст рүү хөрвүүлж байна… хуудас автоматаар шинэчлэгдэнэ.
                </div>

                <div
                    v-if="editingRow?.transcription_status === 'failed'"
                    class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
                >
                    <p>{{ editingRow.transcription_error || 'Боловсруулалт амжилтгүй.' }}</p>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-ghost mt-2 !py-1 text-xs"
                        @click="retryTranscription(editingRow)"
                    >
                        Дахин оролдох
                    </button>
                </div>

                <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <div class="md:col-span-2">
                        <label class="ui-label">Гарчиг</label>
                        <input v-model="form.title" class="ui-input" required :disabled="!canManage" />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <div>
                        <label class="ui-label">Огноо</label>
                        <input v-model="form.held_at" type="datetime-local" class="ui-input" :disabled="!canManage" />
                        <InputError :message="form.errors.held_at" class="mt-1" />
                    </div>

                    <div>
                        <label class="ui-label">Төлөв</label>
                        <select v-model="form.status" class="ui-input" :disabled="!canManage">
                            <option value="draft">Ноорог</option>
                            <option value="final">Батлагдсан</option>
                        </select>
                    </div>

                    <div v-if="canManage" class="md:col-span-2 space-y-2">
                        <label class="ui-label">Аудио (MP3, WAV, M4A, WEBM, WMA · max {{ maxUploadMb }}MB)</label>
                        <div
                            class="rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
                            :class="dragOver
                                ? 'border-brand-orange-400 bg-brand-orange-50/60 dark:bg-brand-orange-950/20'
                                : 'border-slate-200 dark:border-slate-700'"
                            @dragover.prevent="dragOver = true"
                            @dragleave.prevent="dragOver = false"
                            @drop.prevent="onDrop"
                        >
                            <p class="text-sm text-slate-600 dark:text-slate-300">
                                Файл чирж оруулах эсвэл сонгох
                            </p>
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                                <button
                                    type="button"
                                    class="ui-btn-primary !py-2"
                                    @click="fileInput?.click()"
                                >
                                    Файл сонгох
                                </button>
                                <button
                                    v-if="!recording"
                                    type="button"
                                    class="ui-btn-ghost !py-2"
                                    :disabled="!sttReady"
                                    @click="startRecording"
                                >
                                    ● Бичлэг эхлүүлэх
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="ui-btn-danger !py-2"
                                    @click="stopRecording"
                                >
                                    ■ Зогсоох ({{ recordLabel }})
                                </button>
                                <button
                                    v-if="isEditing && audioFile"
                                    type="button"
                                    class="ui-btn-accent !py-2"
                                    :disabled="!sttReady"
                                    @click="uploadAudioToExisting"
                                >
                                    Аудио илгээж хөрвүүлэх
                                </button>
                            </div>
                            <p v-if="audioPreviewName" class="mt-3 text-xs text-slate-500">
                                Сонгосон: {{ audioPreviewName }}
                            </p>
                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg,.flac,.wma,.asf"
                                @change="onFileChange"
                            />
                        </div>
                        <InputError :message="form.errors.audio" class="mt-1" />
                        <p v-if="!sttReady" class="text-xs text-amber-700 dark:text-amber-300">
                            {{ sttHint }}
                        </p>
                        <p v-if="editingRow?.has_audio && !audioFile" class="text-xs text-slate-400">
                            <a
                                :href="route('meetings.audio.download', editingRow.id)"
                                class="underline"
                                @click.stop
                            >
                                Одоогийн аудио татах
                            </a>
                        </p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="ui-label">Транскрипт (түүхий текст)</label>
                        <textarea
                            v-model="form.transcript"
                            rows="5"
                            class="ui-input font-mono text-sm"
                            :disabled="!canManage"
                            placeholder="Аудио хөрвүүлсний дараа энд гарна…"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="ui-label">Хурлын тэмдэглэл</label>
                        <textarea
                            v-model="form.minutes"
                            rows="10"
                            class="ui-input"
                            :disabled="!canManage"
                            placeholder="Цэгцтэй тэмдэглэл — засаж хадгална"
                        />
                        <InputError :message="form.errors.minutes" class="mt-1" />
                    </div>

                    <div v-if="canManage" class="md:col-span-2 flex flex-wrap justify-end gap-2">
                        <button type="button" class="ui-btn-ghost" @click="closeModal">Болих</button>
                        <button
                            type="submit"
                            class="ui-btn-accent"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Хадгалж байна…' : (isEditing ? 'Хадгалах' : 'Үүсгэх') }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
