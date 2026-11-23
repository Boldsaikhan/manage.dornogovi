<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
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
    docFormat: {
        type: Object,
        default: () => ({
            width_mm: 210,
            height_mm: 297,
            margin_top_mm: 20,
            margin_right_mm: 20,
            margin_bottom_mm: 20,
            margin_left_mm: 30,
            font_name: 'Arial',
            font_size_pt: 12,
            line_spacing: 1,
        }),
    },
});

const docPageStyle = computed(() => {
    const f = props.docFormat || {};

    return {
        '--doc-width': `${f.width_mm ?? 210}mm`,
        '--doc-height': `${f.height_mm ?? 297}mm`,
        '--doc-pad-top': `${f.margin_top_mm ?? 20}mm`,
        '--doc-pad-right': `${f.margin_right_mm ?? 20}mm`,
        '--doc-pad-bottom': `${f.margin_bottom_mm ?? 20}mm`,
        '--doc-pad-left': `${f.margin_left_mm ?? 30}mm`,
        '--doc-font': `"${f.font_name || 'Arial'}", Arial, Helvetica, sans-serif`,
        '--doc-size': `${f.font_size_pt ?? 12}pt`,
        '--doc-line': String(f.line_spacing ?? 1),
    };
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
    transcript_segments: [],
    status: 'draft',
    audio: null,
});

const isEditing = computed(() => editingId.value != null);
const editingRow = computed(() => props.meetings.find((m) => m.id === editingId.value) ?? null);

const editorTab = ref('convert'); // convert | notes
const segments = ref([]);
const speakerFilter = ref('');
const followSpeech = ref(true);
const audioPlayer = ref(null);
const playing = ref(false);
const currentTime = ref(0);
const mediaDuration = ref(0);
const playbackRate = ref(1);
const docEditor = ref(null);
const notesMetaOpen = ref(false);
const transcriptDraft = ref('');
const transcriptDirty = ref(false);
const transcriptHovered = ref(false);

const speakerChoices = [
    'Танигдаагүй яригч',
    'Яригч 1',
    'Яригч 2',
    'Яригч 3',
    'Яригч 4',
    'Яригч 5',
];

const formatClock = (sec) => {
    const n = Math.max(0, Math.floor(Number(sec) || 0));
    const h = Math.floor(n / 3600);
    const m = Math.floor((n % 3600) / 60);
    const s = n % 60;
    if (h > 0) {
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    return `${m}:${String(s).padStart(2, '0')}`;
};

const syncSegmentsFromRow = (row) => {
    const list = Array.isArray(row?.transcript_segments) ? row.transcript_segments : [];
    if (list.length) {
        segments.value = list.map((seg) => ({
            id: seg.id || `seg_${Math.random().toString(36).slice(2)}`,
            start: Number(seg.start || 0),
            end: Number(seg.end || 0),
            speaker: seg.speaker || 'Танигдаагүй яригч',
            text: seg.text || '',
        }));

        return;
    }

    if (row?.transcript) {
        segments.value = [{
            id: `seg_${Math.random().toString(36).slice(2)}`,
            start: 0,
            end: Number(row.audio_duration_sec || 0),
            speaker: 'Танигдаагүй яригч',
            text: row.transcript,
        }];

        return;
    }

    segments.value = [];
};

const speakersInUse = computed(() => {
    const set = new Set(segments.value.map((s) => s.speaker || 'Танигдаагүй яригч'));

    return [...set];
});

const filteredSegments = computed(() => {
    if (! speakerFilter.value) return segments.value;

    return segments.value.filter((s) => s.speaker === speakerFilter.value);
});

const activeSegment = computed(() => {
    const list = [...segments.value].sort((a, b) => a.start - b.start);
    if (! list.length) return null;
    const t = currentTime.value;

    const exact = list.find((s) => t >= s.start && t < Math.max(s.end, s.start + 0.35));
    if (exact) return exact;

    // Цоорхойд — хамгийн ойрын өмнөх сегмент (тоглуулж буй байрлалыг харуулах).
    let prev = null;
    for (const s of list) {
        if (s.start <= t) prev = s;
        else break;
    }

    return prev || list[0];
});

const activeSegmentId = computed(() => activeSegment.value?.id || null);

const activeSegmentLabel = computed(() => {
    const seg = activeSegment.value;
    if (! seg) return '';
    const preview = String(seg.text || '').replace(/\s+/g, ' ').trim().slice(0, 72);

    return `${seg.speaker || 'Яригч'} · ${formatClock(seg.start)}` + (preview ? ` — ${preview}` : '');
});

const totalDurationLabel = computed(() => {
    const sec = editingRow.value?.audio_duration_sec || mediaDuration.value || 0;

    return formatClock(sec);
});

const syncTranscriptFromSegments = () => {
    form.transcript = segments.value.map((s) => s.text).filter(Boolean).join('\n\n');
    form.transcript_segments = segments.value;
    if (! transcriptDirty.value) {
        transcriptDraft.value = form.transcript;
    }
};

const syncTranscriptFieldFromSegments = () => {
    if (transcriptDirty.value) return;
    const joined = segments.value.map((s) => String(s.text || '').trim()).filter(Boolean).join('\n\n');
    transcriptDraft.value = joined || form.transcript || '';
    form.transcript = transcriptDraft.value;
    form.transcript_segments = segments.value;
};

const onTranscriptFieldInput = () => {
    transcriptDirty.value = true;
    form.transcript = transcriptDraft.value;
    const dur = Number(editingRow.value?.audio_duration_sec || mediaDuration.value || 0);
    const first = segments.value[0];
    segments.value = [{
        id: first?.id || `seg_${Math.random().toString(36).slice(2)}`,
        start: 0,
        end: dur,
        speaker: first?.speaker || 'Танигдаагүй яригч',
        text: transcriptDraft.value,
    }];
    form.transcript_segments = segments.value;
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const monthSuffixMn = (month) => {
    // Албан бичгийн стандарт: 1,3,4,5,9,11 → дүгээр; бусад → дугаар
    return [1, 3, 4, 5, 9, 11].includes(month) ? 'дүгээр' : 'дугаар';
};

const daySuffixMn = (day) => {
    const unit = day % 10;
    return [1, 4, 9].includes(unit) && ! [11, 14].includes(day) ? 'ний' : 'ны';
};

const formatMnLongDate = (iso) => {
    if (! iso) return '…… оны … дугаар сарын …-ны өдөр';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '…… оны … дугаар сарын …-ны өдөр';
    const y = d.getFullYear();
    const m = d.getMonth() + 1;
    const day = d.getDate();

    return `${y} оны ${String(m).padStart(2, '0')} ${monthSuffixMn(m)} сарын ${day}-${daySuffixMn(day)} өдөр`;
};

const formatMnClockPhrase = (iso) => {
    if (! iso) return '…… цаг …… минут';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '…… цаг …… минут';

    return `${d.getHours()} цаг ${String(d.getMinutes()).padStart(2, '0')} минутад`;
};

const buildFormalMinutes = () => {
    const title = (form.title || 'Хурлын тэмдэглэл').trim();
    const dateLine = formatMnLongDate(form.held_at);
    const timePhrase = formatMnClockPhrase(form.held_at);
    const bodySegs = segments.value.filter((s) => String(s.text || '').trim() !== '');

    const lines = [
        title,
        '',
        dateLine,
        '',
        `Аймгийн Засаг даргын Тамгын газрын ${title} хурал ${timePhrase} эхэлж, дараахь бүрэлдэхүүнтэйгээр хуралдав. Үүнд:`,
        '',
        'ХУРАЛД ОРОЛЦСОН БҮРЭЛДЭХҮҮН:',
        '',
        'Хурлын ирц: 0/0 (0.0%)',
        '',
        'ХЭЛЭЛЦСЭН АСУУДАЛ:',
        '',
        'ХУРЛЫН ТЭМДЭГЛЭЛ:',
        '',
    ];

    if (bodySegs.length) {
        bodySegs.forEach((seg) => {
            const speaker = (seg.speaker || 'Танигдаагүй яригч').trim();
            const text = String(seg.text || '').trim();
            lines.push(`${speaker}: ${text}`);
            lines.push('');
        });
    } else if (form.transcript) {
        lines.push(String(form.transcript).trim());
    } else if (form.minutes && ! looksLikeFormalMinutes(form.minutes)) {
        lines.push(String(form.minutes).trim());
    }

    return lines.join('\n').trimEnd() + '\n';
};

const looksLikeFormalMinutes = (text) => {
    const t = String(text || '');

    return t.includes('ХУРЛЫН ТЭМДЭГЛЭЛ') || t.includes('ХУРАЛД ОРОЛЦСОН');
};

const minutesToHtml = (text) => {
    const raw = String(text || '').replace(/\r\n/g, '\n').trim();
    if (! raw) {
        return '<p><br></p>';
    }

    const parts = raw.split('\n');
    const html = [];
    let isFirstNonEmpty = true;

    parts.forEach((line) => {
        if (line === '') {
            html.push('<p><br></p>');

            return;
        }

        if (isFirstNonEmpty) {
            html.push(`<p class="meeting-doc__title">${escapeHtml(line)}</p>`);
            isFirstNonEmpty = false;

            return;
        }

        if (/^(ХУРАЛД ОРОЛЦСОН БҮРЭЛДЭХҮҮН|ХЭЛЭЛЦСЭН АСУУДАЛ|ХУРЛЫН ТЭМДЭГЛЭЛ):?\s*$/u.test(line)) {
            html.push(`<p class="meeting-doc__heading">${escapeHtml(line)}</p>`);

            return;
        }

        const speakerMatch = line.match(/^([^:]{1,80}):\s*(.*)$/u);
        if (speakerMatch && /Яригч|Танигдаагүй|дарга|нарийн|гишүүн/i.test(speakerMatch[1])) {
            html.push(
                `<p><strong>${escapeHtml(speakerMatch[1])}:</strong> ${escapeHtml(speakerMatch[2])}</p>`,
            );

            return;
        }

        html.push(`<p>${escapeHtml(line)}</p>`);
    });

    return html.join('');
};

const syncDocEditorFromMinutes = async () => {
    await nextTick();
    if (! docEditor.value) return;
    docEditor.value.innerHTML = minutesToHtml(form.minutes || '');
};

const onDocInput = () => {
    if (! docEditor.value) return;
    form.minutes = docEditor.value.innerText
        .replace(/\u00a0/g, ' ')
        .replace(/\n{3,}/g, '\n\n')
        .trimEnd();
};

const ensureFormalMinutes = (force = false) => {
    const current = String(form.minutes || '').trim();
    if (force) {
        form.minutes = buildFormalMinutes();

        return;
    }
    if (looksLikeFormalMinutes(current)) {
        return;
    }
    // Хоосон, эсвэл сегменттэй боловч албан загвар биш бол Word маягийн тэмдэглэл бүрдүүлнэ.
    if (current === '' || segments.value.length > 0) {
        form.minutes = buildFormalMinutes();
    }
};

const regenerateMinutesFromSegments = async () => {
    syncTranscriptFromSegments();
    form.minutes = buildFormalMinutes();
    await syncDocEditorFromMinutes();
};

const switchEditorTab = async (tab) => {
    editorTab.value = tab;
    if (tab === 'notes') {
        notesMetaOpen.value = false;
        ensureFormalMinutes(false);
        await syncDocEditorFromMinutes();
    }
};

const removeSegment = (id) => {
    segments.value = segments.value.filter((s) => s.id !== id);
    syncTranscriptFromSegments();
};

const seekTo = (sec) => {
    const el = audioPlayer.value;
    if (! el) return;
    el.currentTime = Math.max(0, sec);
    currentTime.value = el.currentTime;
};

const togglePlay = async () => {
    const el = audioPlayer.value;
    if (! el) return;
    if (el.paused) {
        await el.play();
        playing.value = true;
    } else {
        el.pause();
        playing.value = false;
    }
};

const onAudioTimeUpdate = () => {
    const el = audioPlayer.value;
    if (! el) return;
    currentTime.value = el.currentTime || 0;
    if (Number.isFinite(el.duration)) {
        mediaDuration.value = el.duration;
    }
};

const onAudioEnded = () => {
    playing.value = false;
};

const cycleSpeed = () => {
    const rates = [1, 1.25, 1.5, 2, 0.75];
    const idx = rates.indexOf(playbackRate.value);
    playbackRate.value = rates[(idx + 1) % rates.length];
    if (audioPlayer.value) {
        audioPlayer.value.playbackRate = playbackRate.value;
    }
};

const copySegmentText = async (text) => {
    try {
        await navigator.clipboard.writeText(text || '');
    } catch {
        // ignore
    }
};

const transcriptionBadge = (status) => {
    const map = {
        none: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        converting: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
        processing: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
        ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        failed: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
    };
    return map[status] || map.none;
};

const isBusyStatus = (status) => ['pending', 'converting', 'processing'].includes(status);

const busyProgress = computed(() => {
    const row = editingRow.value;
    if (! row || ! isBusyStatus(row.transcription_status)) {
        return 0;
    }

    return Math.max(1, Math.min(100, Number(row.transcription_progress || 0)));
});

const busyStage = computed(() => {
    const row = editingRow.value;
    if (! row) return '';

    return row.transcription_stage
        || row.transcription_label
        || 'Боловсруулж байна…';
});

const stuckBusy = ref(false);
let stuckWatchProgress = null;
let stuckWatchSince = 0;

const noteBusyProgress = (row) => {
    if (! row || ! isBusyStatus(row.transcription_status)) {
        stuckBusy.value = false;
        stuckWatchProgress = null;
        stuckWatchSince = 0;

        return;
    }

    const progress = Number(row.transcription_progress || 0);
    const now = Date.now();
    if (stuckWatchProgress !== progress) {
        stuckWatchProgress = progress;
        stuckWatchSince = now;
        stuckBusy.value = false;

        return;
    }

    // Ижил % дээр 90 сек+ бол гацсан гэж үзнэ.
    stuckBusy.value = now - stuckWatchSince >= 90_000;
};

const openCreate = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.title = '';
    form.held_at = new Date().toISOString().slice(0, 16);
    form.minutes = '';
    form.transcript = '';
    form.transcript_segments = [];
    form.status = 'draft';
    form.audio = null;
    audioFile.value = null;
    audioPreviewName.value = '';
    segments.value = [];
    transcriptDraft.value = '';
    transcriptDirty.value = false;
    editorTab.value = 'notes';
    speakerFilter.value = '';
    notesMetaOpen.value = false;
    currentTime.value = 0;
    mediaDuration.value = 0;
    playing.value = false;
    form.minutes = buildFormalMinutes();
    showModal.value = true;
    nextTick(() => syncDocEditorFromMinutes());
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
    transcriptDirty.value = false;
    syncSegmentsFromRow(row);
    syncTranscriptFieldFromSegments();
    const preferConvert = row.has_audio || (row.transcript_segments || []).length;
    editorTab.value = preferConvert ? 'convert' : 'notes';
    speakerFilter.value = '';
    notesMetaOpen.value = false;
    currentTime.value = 0;
    mediaDuration.value = Number(row.audio_duration_sec || 0);
    playing.value = false;
    if (! preferConvert) {
        ensureFormalMinutes(false);
    }
    showModal.value = true;
    if (! preferConvert) {
        nextTick(() => syncDocEditorFromMinutes());
    }
};

const closeModal = () => {
    showModal.value = false;
    stopRecording();
    const el = audioPlayer.value;
    if (el) {
        el.pause();
    }
    playing.value = false;
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
            syncSegmentsFromRow(row);
            if (editorTab.value === 'notes' && row.transcription_status === 'ready') {
                if (! looksLikeFormalMinutes(form.minutes)) {
                    ensureFormalMinutes(true);
                }
                syncDocEditorFromMinutes();
            }
        }
    },
    { deep: true },
);

watch(editorTab, (tab) => {
    if (tab === 'notes') {
        ensureFormalMinutes(false);
        syncDocEditorFromMinutes();
    }
});


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
    if (editorTab.value === 'notes') {
        onDocInput();
    }
    if (editorTab.value === 'convert' || transcriptDirty.value) {
        onTranscriptFieldInput();
    } else {
        syncTranscriptFromSegments();
    }
    form.transcript_segments = segments.value;
    form.transcript = transcriptDraft.value || segments.value.map((s) => s.text).filter(Boolean).join('\n\n');

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
let statusPollTimer = null;

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
    }, 2500);
};

const pollEditingStatus = async () => {
    const id = editingId.value;
    if (! id || ! showModal.value) {
        statusPollTimer = null;

        return;
    }

    const row = props.meetings.find((m) => m.id === id);
    if (! row || ! isBusyStatus(row.transcription_status)) {
        statusPollTimer = null;

        return;
    }

    try {
        const res = await fetch(route('meetings.status', id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            const idx = props.meetings.findIndex((m) => m.id === id);
            if (idx >= 0) {
                Object.assign(props.meetings[idx], data);
            }
            noteBusyProgress(data);
            // Амьд текст / сегмент — боловсруулж байх үед ч шинэчлэгдэнэ.
            if (Array.isArray(data.transcript_segments) && data.transcript_segments.length) {
                syncSegmentsFromRow(data);
                syncTranscriptFieldFromSegments();
            } else if (typeof data.transcript === 'string' && data.transcript !== '') {
                form.transcript = data.transcript;
                if (! transcriptDirty.value) {
                    transcriptDraft.value = data.transcript;
                }
            }
            if (data.transcription_status === 'ready') {
                form.minutes = data.minutes || form.minutes;
                audioPreviewName.value = data.audio_name || audioPreviewName.value;
                transcriptDirty.value = false;
                syncSegmentsFromRow(data);
                syncTranscriptFieldFromSegments();
                stuckBusy.value = false;
            }
            if (data.transcription_status === 'failed') {
                stuckBusy.value = false;
            }
        }
    } catch {
        // ignore transient poll errors
    }

    statusPollTimer = setTimeout(pollEditingStatus, 1200);
};

const ensureStatusPoll = () => {
    if (statusPollTimer) return;
    const row = editingRow.value;
    if (row && isBusyStatus(row.transcription_status)) {
        pollEditingStatus();
    }
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

watch([showModal, editingId, () => editingRow.value?.transcription_status], () => {
    if (showModal.value && editingRow.value && isBusyStatus(editingRow.value.transcription_status)) {
        ensureStatusPoll();
    }
});

onBeforeUnmount(() => {
    if (pollTimer) clearTimeout(pollTimer);
    if (statusPollTimer) clearTimeout(statusPollTimer);
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
                                            v-if="isBusyStatus(row.transcription_status)"
                                            class="mt-1.5 w-36 max-w-full"
                                        >
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-full rounded-full bg-brand-navy-500 transition-all duration-500"
                                                    :style="{ width: `${Math.max(4, row.transcription_progress || 0)}%` }"
                                                />
                                            </div>
                                            <div class="mt-0.5 text-[10px] tabular-nums text-slate-400">
                                                {{ row.transcription_progress || 0 }}%
                                                <span v-if="row.transcription_stage"> · {{ row.transcription_stage }}</span>
                                            </div>
                                        </div>
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

        <Modal :show="showModal" max-width="full" @close="closeModal">
            <div class="flex h-dvh max-h-dvh flex-col" :style="docPageStyle">
                <div
                    v-if="editorTab !== 'notes'"
                    class="flex shrink-0 items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-5"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-brand-navy-800 dark:text-slate-100">
                            {{ isEditing ? 'Тэмдэглэл засах' : 'Шинэ хурлын тэмдэглэл' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Аудио → Whisper → Word маягаар засварлах
                        </p>
                    </div>
                    <button type="button" class="ui-btn-ghost !px-2 !py-1" @click="closeModal">Хаах</button>
                </div>
                <div
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                    :class="editorTab === 'notes' ? 'gap-0 p-0' : 'gap-3 p-4 sm:p-5'"
                >

                <div
                    v-if="editorTab !== 'notes' && editingRow && isBusyStatus(editingRow.transcription_status)"
                    class="shrink-0 rounded-lg border border-sky-200 bg-sky-50 px-3 py-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="inline-block h-3 w-3 shrink-0 animate-pulse rounded-full bg-sky-500" />
                            <span class="truncate font-medium">{{ busyStage }}</span>
                        </div>
                        <span class="shrink-0 tabular-nums text-xs opacity-80">{{ busyProgress }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-sky-100 dark:bg-sky-900/60">
                        <div
                            class="h-full rounded-full bg-brand-navy-600 transition-all duration-500 dark:bg-sky-400"
                            :style="{ width: `${busyProgress}%` }"
                        />
                    </div>
                    <div
                        v-if="editingRow.audio_duration_sec"
                        class="mt-2 flex items-center justify-between text-xs tabular-nums opacity-90"
                    >
                        <span>
                            Бичлэгийн урт:
                            <strong>{{ editingRow.audio_duration_label }}</strong>
                        </span>
                        <span>
                            Бичигдсэн:
                            <strong>{{ editingRow.transcription_position_label || '0:00' }}</strong>
                            /
                            {{ editingRow.audio_duration_label }}
                        </span>
                    </div>
                    <div
                        v-if="editingRow.audio_duration_sec"
                        class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
                        :title="`Бичигдсэн ${editingRow.transcription_position_label || '0:00'} / ${editingRow.audio_duration_label}`"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                            :style="{
                                width: `${Math.min(100, Math.round(100 * (editingRow.transcription_position_sec || 0) / editingRow.audio_duration_sec))}%`,
                            }"
                        />
                    </div>
                    <p class="mt-2 text-xs opacity-80">
                        Бичлэгийг хэсэгчилж боловсруулна — зүүн талын транскрипт эхнээсээ бичигдэнэ.
                    </p>
                    <div
                        v-if="stuckBusy && canManage"
                        class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100"
                    >
                        <p class="text-xs">Явц удаан байна — сервер гацсан байж магадгүй.</p>
                        <button
                            type="button"
                            class="ui-btn-ghost mt-2 !py-1 text-xs"
                            @click="retryTranscription(editingRow)"
                        >
                            Дахин оролдох
                        </button>
                    </div>
                </div>

                <div
                    v-if="editorTab !== 'notes' && editingRow?.transcription_status === 'failed'"
                    class="shrink-0 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-100"
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

                <form class="flex min-h-0 flex-1 flex-col" :class="editorTab === 'notes' ? 'gap-0' : 'gap-3'" @submit.prevent="submit">
                    <div
                        class="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-700"
                        :class="editorTab === 'notes' ? 'bg-slate-50 px-3 py-2 dark:bg-slate-900' : 'pb-2'"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition"
                            :class="editorTab === 'convert'
                                ? 'bg-slate-100 text-brand-navy-800 dark:bg-slate-800 dark:text-sky-100'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700 dark:hover:bg-slate-800'"
                            @click="switchEditorTab('convert')"
                        >
                            <span class="text-base leading-none" aria-hidden="true">☰</span>
                            Хөрвүүлэг
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-t-md border px-3 py-1.5 text-sm font-semibold transition"
                            :class="editorTab === 'notes'
                                ? 'border-sky-400 border-b-white bg-white text-brand-navy-800 shadow-sm dark:border-sky-500 dark:border-b-slate-900 dark:bg-slate-900 dark:text-sky-100'
                                : 'border-transparent text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800'"
                            @click="switchEditorTab('notes')"
                        >
                            <svg class="h-4 w-4 opacity-70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h8l4-4V4a2 2 0 00-2-2H4zm8 13v-3h3l-3 3z" />
                            </svg>
                            Тэмдэглэл
                        </button>
                        <div v-if="editorTab === 'notes'" class="ml-auto flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="ui-btn-ghost !py-1 text-xs"
                                @click="notesMetaOpen = !notesMetaOpen"
                            >
                                {{ notesMetaOpen ? 'Тохиргоо ×' : '⚙' }}
                            </button>
                            <button
                                v-if="canManage && segments.length"
                                type="button"
                                class="ui-btn-ghost !py-1 text-xs"
                                @click="regenerateMinutesFromSegments"
                            >
                                Сегментээс
                            </button>
                            <button
                                v-if="canManage"
                                type="submit"
                                class="ui-btn-accent !py-1.5 !px-3 text-xs"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? '…' : 'Хадгалах' }}
                            </button>
                            <button type="button" class="ui-btn-ghost !py-1.5 !px-3 text-xs" @click="closeModal">
                                Хаах
                            </button>
                        </div>
                    </div>

                    <!-- Хөрвүүлэг: нэг талбар + тоглуулагч -->
                    <div v-show="editorTab === 'convert'" class="flex min-h-0 flex-1 flex-col gap-3">
                        <div
                            class="transcript-field relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border-2 bg-white transition dark:bg-slate-900/50"
                            :class="transcriptHovered || playing
                                ? 'border-brand-navy-500 shadow-md ring-2 ring-brand-navy-400/35 dark:border-sky-400 dark:ring-sky-400/30'
                                : 'border-slate-200 dark:border-slate-700'"
                            @mouseenter="transcriptHovered = true"
                            @mouseleave="transcriptHovered = false"
                        >
                            <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 px-3 py-2 dark:border-slate-800">
                                <div class="min-w-0 truncate text-xs text-slate-500">
                                    <span v-if="activeSegmentLabel" class="text-brand-navy-700 dark:text-sky-200">
                                        <span class="font-semibold">Одоо:</span> {{ activeSegmentLabel }}
                                    </span>
                                    <span v-else>Хөрвүүлсэн текст</span>
                                </div>
                                <button
                                    v-if="transcriptDraft"
                                    type="button"
                                    class="ui-icon-btn shrink-0"
                                    title="Хуулах"
                                    @click="copySegmentText(transcriptDraft)"
                                >
                                    ⎘
                                </button>
                            </div>
                            <textarea
                                v-model="transcriptDraft"
                                class="transcript-field__input w-full flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm leading-relaxed text-slate-800 outline-none focus:ring-0 dark:text-slate-100"
                                :disabled="!canManage"
                                placeholder="Боловсруулж эхлэхэд хөрвүүлсэн текст энд нэг талбарт гарна…"
                                @input="onTranscriptFieldInput"
                                @focus="transcriptHovered = true"
                            />
                        </div>

                        <div
                            v-if="editingRow?.audio_stream_url"
                            class="shrink-0 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <audio
                                ref="audioPlayer"
                                class="hidden"
                                :src="editingRow.audio_stream_url"
                                preload="metadata"
                                @timeupdate="onAudioTimeUpdate"
                                @loadedmetadata="onAudioTimeUpdate"
                                @ended="onAudioEnded"
                                @play="playing = true"
                                @pause="playing = false"
                            />
                            <div class="mb-2 flex items-center gap-3 text-xs tabular-nums text-slate-500">
                                <span>{{ formatClock(currentTime) }}</span>
                                <input
                                    type="range"
                                    class="h-1.5 flex-1 cursor-pointer accent-brand-navy-600"
                                    min="0"
                                    :max="Math.max(mediaDuration || editingRow.audio_duration_sec || 1, 1)"
                                    step="0.1"
                                    :value="currentTime"
                                    @input="seekTo(Number($event.target.value))"
                                />
                                <span>{{ totalDurationLabel }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold dark:border-slate-600"
                                    @click="cycleSpeed"
                                >
                                    {{ playbackRate }}x
                                </button>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="ui-icon-btn"
                                        title="10 сек ухраах"
                                        @click="seekTo(currentTime - 10)"
                                    >
                                        ‹‹
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-navy-600 text-lg text-white shadow"
                                        :title="playing ? 'Түр зогсоох' : 'Тоглуулах'"
                                        @click="togglePlay"
                                    >
                                        {{ playing ? '❚❚' : '▶' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="ui-icon-btn"
                                        title="10 сек урагшлуулах"
                                        @click="seekTo(currentTime + 10)"
                                    >
                                        ››
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-200 px-2 py-1 text-xs dark:border-slate-600"
                                    :class="followSpeech ? 'bg-slate-100 dark:bg-slate-800' : ''"
                                    @click="followSpeech = !followSpeech"
                                >
                                    {{ followSpeech ? 'Дагах' : 'Дагахгүй' }}
                                </button>
                            </div>
                        </div>
                        <p
                            v-else
                            class="rounded-xl border border-dashed border-slate-200 px-3 py-4 text-center text-sm text-slate-400 dark:border-slate-700"
                        >
                            Аудио оруулсны дараа энд тоглуулагч гарна. «Тэмдэглэл» табаас файл оруулна.
                        </p>
                    </div>

                    <!-- Тэмдэглэл: Word / албан бичгийн стандарт хуудас -->
                    <div v-show="editorTab === 'notes'" class="flex min-h-0 flex-1 flex-col">
                        <div
                            v-show="notesMetaOpen"
                            class="grid shrink-0 gap-3 border-b border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/50 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div class="lg:col-span-2">
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
                            <div
                                v-if="canManage"
                                class="sm:col-span-2 lg:col-span-4"
                            >
                                <label class="ui-label">Аудио (MP3, WAV, M4A, WEBM, WMA · max {{ maxUploadMb }}MB)</label>
                                <div
                                    class="rounded-xl border-2 border-dashed px-3 py-3 text-center transition"
                                    :class="dragOver
                                        ? 'border-brand-orange-400 bg-brand-orange-50/60 dark:bg-brand-orange-950/20'
                                        : 'border-slate-200 dark:border-slate-700'"
                                    @dragover.prevent="dragOver = true"
                                    @dragleave.prevent="dragOver = false"
                                    @drop.prevent="onDrop"
                                >
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <button type="button" class="ui-btn-primary !py-1.5 text-xs" @click="fileInput?.click()">
                                            Файл сонгох
                                        </button>
                                        <button
                                            v-if="!recording"
                                            type="button"
                                            class="ui-btn-ghost !py-1.5 text-xs"
                                            :disabled="!sttReady"
                                            @click="startRecording"
                                        >
                                            ● Бичлэг
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="ui-btn-danger !py-1.5 text-xs"
                                            @click="stopRecording"
                                        >
                                            ■ Зогсоох ({{ recordLabel }})
                                        </button>
                                        <button
                                            v-if="isEditing && audioFile"
                                            type="button"
                                            class="ui-btn-accent !py-1.5 text-xs"
                                            :disabled="!sttReady"
                                            @click="uploadAudioToExisting"
                                        >
                                            Хөрвүүлэх
                                        </button>
                                        <span v-if="audioPreviewName" class="text-xs text-slate-500">
                                            {{ audioPreviewName }}
                                            <span v-if="editingRow?.audio_duration_label" class="tabular-nums">
                                                · {{ editingRow.audio_duration_label }}
                                            </span>
                                        </span>
                                    </div>
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        class="hidden"
                                        accept="audio/*,.mp3,.wav,.m4a,.webm,.ogg,.flac,.wma,.asf"
                                        @change="onFileChange"
                                    />
                                </div>
                                <InputError :message="form.errors.audio" class="mt-1" />
                                <p v-if="!sttReady" class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ sttHint }}</p>
                                <p v-if="editingRow?.has_audio && !audioFile" class="mt-1 text-xs text-slate-400">
                                    <a :href="route('meetings.audio.download', editingRow.id)" class="underline" @click.stop>
                                        Одоогийн аудио татах
                                    </a>
                                </p>
                            </div>
                        </div>

                        <div class="meeting-doc-canvas min-h-0 flex-1 overflow-y-auto">
                            <div class="meeting-doc-sheet">
                                <div
                                    ref="docEditor"
                                    class="meeting-doc-page"
                                    :contenteditable="canManage ? 'true' : 'false'"
                                    spellcheck="true"
                                    @input="onDocInput"
                                    @blur="onDocInput"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.minutes" class="px-3" />
                    </div>

                    <div
                        v-if="canManage && editorTab !== 'notes'"
                        class="mt-auto flex shrink-0 flex-wrap justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                    >
                        <button type="button" class="ui-btn-ghost" @click="closeModal">Болих</button>
                        <button type="submit" class="ui-btn-accent" :disabled="form.processing">
                            {{ form.processing ? 'Хадгалж байна…' : (isEditing ? 'Хадгалах' : 'Үүсгэх') }}
                        </button>
                    </div>
                </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.transcript-field__input {
    min-height: 12rem;
    height: 100%;
}

.meeting-doc-canvas {
    background: #e8eaed;
    padding: 16px 12px 28px;
}

.dark .meeting-doc-canvas {
    background: #1e293b;
}

/* Бичиг хэргийн стандарт — DocumentFormat (A4, ирмэг, Arial …) */
.meeting-doc-sheet {
    margin: 0 auto;
    width: var(--doc-width, 210mm);
    min-height: var(--doc-height, 297mm);
    max-width: 100%;
    background: #fff;
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.06),
        0 8px 28px rgba(15, 23, 42, 0.14);
}

.dark .meeting-doc-sheet {
    background: #fff;
}

.meeting-doc-page {
    box-sizing: border-box;
    min-height: var(--doc-height, 297mm);
    padding: var(--doc-pad-top, 20mm) var(--doc-pad-right, 20mm) var(--doc-pad-bottom, 20mm) var(--doc-pad-left, 30mm);
    color: #000;
    font-size: var(--doc-size, 12pt);
    line-height: var(--doc-line, 1);
    font-family: var(--doc-font, Arial, Helvetica, sans-serif);
    outline: none;
    word-break: break-word;
    text-align: justify;
}

.meeting-doc-page:focus {
    box-shadow: inset 0 0 0 1px rgba(14, 165, 233, 0.35);
}

.meeting-doc-page :deep(p) {
    margin: 0 0 0.75em;
    text-indent: 0;
}

.meeting-doc-page :deep(.meeting-doc__title) {
    text-align: center;
    font-weight: 700;
    font-size: 1.08em;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin: 0 0 1.25em;
    text-indent: 0;
}

.meeting-doc-page :deep(.meeting-doc__heading) {
    font-weight: 700;
    margin-top: 1em;
    margin-bottom: 0.5em;
    text-align: left;
    text-indent: 0;
}

.meeting-doc-page :deep(strong) {
    font-weight: 700;
}

@media (max-width: 720px) {
    .meeting-doc-sheet {
        width: 100%;
        min-height: auto;
    }

    .meeting-doc-page {
        min-height: 70vh;
        padding: 16px 14px 24px;
        font-size: 11pt;
    }
}
</style>
