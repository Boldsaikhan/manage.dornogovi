<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';

const props = defineProps({
    kind: { type: String, required: true },
    source: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    azdtgUnits: { type: Array, default: () => [] },
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

// ── Дашбоард: хэрэгжилтийг хэлтэс, ангиллаар нэгтгэнэ ──────────────────────────
const showDashboard = ref(false); // дэлгэц дүүрэн дашбоард

const CATEGORY_ORDER = ['udirdlaga', 'heltes', 'azdtg', 'agentlag', 'sum', 'baiguullaga', 'unknown'];
const filter = ref(null); // { type: 'category' | 'org', value, label }

const CATEGORY_LABELS = {
    udirdlaga: 'Аймгийн удирдлагууд',
    heltes: 'Хэлтэс',
    azdtg: 'АЗДТГ-ын албан хаагчид',
    agentlag: 'Агентлаг',
    sum: 'Сумд',
    baiguullaga: 'Байгууллага',
    unknown: 'Тодорхойгүй',
};

const peopleIndex = computed(() => {
    const map = {};
    props.people.forEach((p) => {
        map[p.value] = p;
    });

    return map;
});

const splitNames = (value) => String(value ?? '')
    .split(/[/;,|]+/)
    .map((v) => v.trim())
    .filter(Boolean);

// Нэг үүрэг олон хариуцагчтай байж болно.
const taskOwners = (task) => {
    const names = splitNames(task.responsible);

    if (!names.length) {
        return [{ value: '—', org: 'Тодорхойгүй', category: 'unknown' }];
    }

    return names.map((name) => {
        const person = peopleIndex.value[name];

        return {
            value: name,
            org: person?.org || 'Тодорхойгүй',
            category: person?.category || 'unknown',
        };
    });
};

const average = (list) => (list.length
    ? Math.round(list.reduce((sum, t) => sum + (Number(t.progress) || 0), 0) / list.length)
    : 0);

const buildStats = (keyFn, labelFn) => {
    const groups = new Map();

    props.tasks.forEach((task) => {
        const seen = new Set();

        taskOwners(task).forEach((owner) => {
            const key = keyFn(owner);
            if (seen.has(key)) return;
            seen.add(key);

            if (!groups.has(key)) {
                groups.set(key, { key, label: labelFn(owner, key), tasks: [] });
            }
            groups.get(key).tasks.push(task);
        });
    });

    return [...groups.values()]
        .map((g) => ({
            key: g.key,
            label: g.label,
            count: g.tasks.length,
            done: g.tasks.filter((t) => Number(t.progress) >= 100).length,
            progress: average(g.tasks),
        }))
        .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label, 'mn'));
};

// АЗДТГ-ын нэгжүүд (хэлтэс) — үүрэггүй нэгжийг ч 0%-иар харуулна.
const azdtgStats = computed(() => {
    const groups = new Map();

    props.azdtgUnits.forEach((unit) => {
        groups.set(unit, { key: unit, label: unit, tasks: [] });
    });

    props.tasks.forEach((task) => {
        taskOwners(task)
            .filter((owner) => owner.category === 'azdtg')
            .forEach((owner) => {
                const key = owner.org || 'Тодорхойгүй';
                if (!groups.has(key)) groups.set(key, { key, label: key, tasks: [] });
                if (!groups.get(key).tasks.includes(task)) groups.get(key).tasks.push(task);
            });
    });

    return [...groups.values()]
        .map((g) => ({
            key: g.key,
            label: g.label,
            count: g.tasks.length,
            done: g.tasks.filter((t) => Number(t.progress) >= 100).length,
            progress: average(g.tasks),
        }))
        .sort((a, b) => b.progress - a.progress || b.count - a.count || a.label.localeCompare(b.label, 'mn'));
});

// Дугуй диаграмын тойрог
const donut = (value, radius = 52) => {
    const circumference = 2 * Math.PI * radius;
    const filled = (Math.max(0, Math.min(100, value)) / 100) * circumference;

    return { circumference, dash: `${filled} ${circumference - filled}` };
};

const statusSegments = computed(() => {
    const total = overall.value.count || 1;

    return [
        { label: 'Дууссан', value: overall.value.done, color: '#10b981' },
        { label: 'Хэрэгжиж буй', value: overall.value.started, color: '#f59e0b' },
        { label: 'Эхлээгүй', value: overall.value.pending, color: '#cbd5e1' },
    ].map((s) => ({ ...s, percent: Math.round((s.value / total) * 100) }));
});

// Ангиллын хэсэгт: бусад ангилал + АЗДТГ-ын нэгж бүр (албан хаагчид гэсэн нэгдсэн карт байхгүй).
const dashboardCards = computed(() => [
    ...categoryStats.value
        .filter((item) => item.key !== 'azdtg')
        .map((item) => ({ ...item, type: 'category' })),
    ...azdtgStats.value.map((item) => ({ ...item, type: 'org' })),
]);

// Ангиллын картуудыг тогтмол дарааллаар харуулна.
const categoryStats = computed(() => buildStats(
    (owner) => owner.category,
    (owner, key) => CATEGORY_LABELS[key] ?? key,
).sort((a, b) => {
    const ai = CATEGORY_ORDER.indexOf(a.key);
    const bi = CATEGORY_ORDER.indexOf(b.key);

    return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
}));

const orgStats = computed(() => buildStats(
    (owner) => owner.org,
    (owner) => owner.org,
));

const overall = computed(() => ({
    count: props.tasks.length,
    progress: average(props.tasks),
    done: props.tasks.filter((t) => Number(t.progress) >= 100).length,
    started: props.tasks.filter((t) => Number(t.progress) > 0 && Number(t.progress) < 100).length,
    pending: props.tasks.filter((t) => !Number(t.progress)).length,
}));

const barColor = (value) => {
    if (value >= 80) return 'bg-emerald-500';
    if (value >= 50) return 'bg-brand-navy-500';
    if (value > 0) return 'bg-amber-500';

    return 'bg-slate-300';
};

const openDashboard = () => {
    showDashboard.value = true;
};

const applyFilterAndClose = (type, key, label) => {
    applyFilter(type, key, label);
    showDashboard.value = false;
};

const applyFilter = (type, key, label) => {
    filter.value = filter.value && filter.value.type === type && filter.value.value === key
        ? null
        : { type, value: key, label };
};

const clearFilter = () => {
    filter.value = null;
};

// Шүүлттэй үед зөвхөн холбогдох үүрэг чиглэл харагдана.
const visibleTasks = computed(() => {
    if (!filter.value) return props.tasks;

    return props.tasks.filter((task) => taskOwners(task).some((owner) => (
        filter.value.type === 'category'
            ? owner.category === filter.value.value
            : owner.org === filter.value.value
    )));
});

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

/** Урт бичвэрийг 2 мөрөнд багтаахад шаардлагатай баганы өргөн (px) */
const twoLineWidth = (text, { min = 220, max = 780, pxPerChar = 7.2 } = {}) => {
    const len = String(text ?? '').replace(/\s+/g, ' ').trim().length;
    if (!len) return min;
    return Math.min(max, Math.max(min, Math.ceil(len / 2) * pxPerChar));
};

const directiveTextColWidth = computed(() => {
    const widths = props.tasks.map((t) => {
        const draft = drafts[t.id];
        return twoLineWidth(draft?.text ?? t.text, { min: 280, max: 900 });
    });
    return widths.length ? Math.max(...widths) : 320;
});

const directiveNoteColWidth = computed(() => {
    const widths = props.tasks.map((t) => {
        const draft = drafts[t.id];
        return twoLineWidth(draft?.note ?? t.note, { min: 140, max: 360, pxPerChar: 7 });
    });
    return widths.length ? Math.max(...widths) : 160;
});

const directiveTableMinWidth = computed(() => {
    const fixed = 48 + 140 + 160 + 96 + (props.canManage ? 48 : 0);
    return fixed + directiveTextColWidth.value + directiveNoteColWidth.value;
});

const prepTextColWidth = computed(() => {
    const widths = props.tasks.map((t) => {
        const draft = drafts[t.id];
        return twoLineWidth(draft?.text ?? t.text, { min: 260, max: 720 });
    });
    return widths.length ? Math.max(...widths) : 280;
});

const prepNoteColWidth = computed(() => {
    const widths = props.tasks.map((t) => {
        const draft = drafts[t.id];
        return twoLineWidth(draft?.note ?? t.note, { min: 140, max: 320, pxPerChar: 7 });
    });
    return widths.length ? Math.max(...widths) : 160;
});

const prepTableMinWidth = computed(() => {
    const fixed = 48 + 140 + 110 + 140 + 150 + 96 + (props.canManage ? 48 : 0);
    return fixed + prepTextColWidth.value + prepNoteColWidth.value;
});
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

            <!-- Дашбоард: товч хураангуй, дарахад дэлгэц дүүрэн нээгдэнэ -->
            <button
                type="button"
                class="flex w-full flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-soft transition hover:border-brand-navy-300"
                @click="openDashboard"
            >
                <span class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-navy-50 text-brand-navy-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M4 20V10M10 20V4M16 20v-6M22 20H2" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-slate-800">Хэрэгжилтийн дашбоард</span>
                        <span class="block text-xs text-slate-500">Дарж дэлгэрэнгүй графикаар харна</span>
                    </span>
                </span>

                <span class="text-2xl font-bold text-brand-navy-800">{{ overall.progress }}%</span>

                <span class="h-2 min-w-[6rem] flex-1 overflow-hidden rounded-full bg-slate-200">
                    <span class="block h-full rounded-full" :class="barColor(overall.progress)" :style="{ width: overall.progress + '%' }" />
                </span>

                <span class="flex gap-3 text-xs text-slate-500">
                    <span>Нийт <b class="text-slate-700">{{ overall.count }}</b></span>
                    <span>Дууссан <b class="text-emerald-600">{{ overall.done }}</b></span>
                    <span>Эхлээгүй <b class="text-slate-600">{{ overall.pending }}</b></span>
                </span>
            </button>

            <!-- Идэвхтэй шүүлт -->
            <div v-if="filter" class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate-500">Шүүлт:</span>
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-navy-600 px-3 py-1 font-medium text-white">
                    {{ filter.label }}
                    <button type="button" class="text-white/80 hover:text-white" @click="clearFilter">✕</button>
                </span>
                <span class="text-slate-500">{{ visibleTasks.length }} үүрэг чиглэл</span>
            </div>

            <!-- Үүрэг чиглэл -->
            <div v-if="isDirective" class="ui-table-wrap w-full overflow-x-auto">
                <table
                    class="ui-table table-fixed"
                    :style="{ width: `${directiveTableMinWidth}px`, minWidth: `${directiveTableMinWidth}px` }"
                >
                    <colgroup>
                        <col class="w-12" />
                        <col :style="{ width: `${directiveTextColWidth}px` }" />
                        <col style="width: 140px" />
                        <col style="width: 160px" />
                        <col :style="{ width: `${directiveNoteColWidth}px` }" />
                        <col style="width: 96px" />
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
                        <tr v-for="task in visibleTasks" :key="task.id">
                            <td class="text-center font-semibold text-slate-500">{{ task.no }}</td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    multiline
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'text', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    :editable="canManage"
                                    :options="people"
                                    multiple
                                    placeholder="Утасны жагсаалтаас сонгох…"
                                    @commit="(v) => saveField(task.id, 'responsible', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    :editable="canManage"
                                    :options="people"
                                    multiple
                                    placeholder="Утасны жагсаалтаас сонгох…"
                                    @commit="(v) => saveField(task.id, 'collaborator', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].note"
                                    multiline
                                    placeholder="Хэрэгжилт…"
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'note', v)"
                                />
                            </td>
                            <td class="ui-sheet-td text-center">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].progress"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    @commit="() => saveProgress(task.id)"
                                >
                                    {{ drafts[task.id].progress ?? 0 }}%
                                </SheetCell>
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
                        <tr v-if="!visibleTasks.length">
                            <td :colspan="canManage ? 7 : 6" class="!py-14 text-center text-slate-400">
                                {{ filter ? 'Энэ шүүлтэд тохирох үүрэг чиглэл алга.' : 'Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Бэлтгэл ажил хангах төлөвлөгөө -->
            <div v-else class="ui-table-wrap overflow-x-auto">
                <table
                    class="ui-table table-fixed"
                    :style="{ width: `${prepTableMinWidth}px`, minWidth: `${prepTableMinWidth}px` }"
                >
                    <colgroup>
                        <col style="width: 48px" />
                        <col style="width: 140px" />
                        <col :style="{ width: `${prepTextColWidth}px` }" />
                        <col style="width: 110px" />
                        <col style="width: 140px" />
                        <col style="width: 150px" />
                        <col :style="{ width: `${prepNoteColWidth}px` }" />
                        <col style="width: 96px" />
                        <col v-if="canManage" style="width: 48px" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="text-center">№</th>
                            <th>Ажлын чиглэл</th>
                            <th>Арга хэмжээ</th>
                            <th>Хугацаа</th>
                            <th>Хариуцах эзэн</th>
                            <th>Хамтран хэрэгжүүлэх</th>
                            <th>Хэрэгжилт</th>
                            <th class="text-center">Биелэлтийн хувь</th>
                            <th v-if="canManage" class="text-center" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in visibleTasks" :key="task.id">
                            <td class="text-center font-semibold text-slate-500">{{ task.no }}</td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].sector"
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'sector', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].text"
                                    multiline
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'text', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].period"
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'period', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].responsible"
                                    :editable="canManage"
                                    :options="people"
                                    multiple
                                    placeholder="Утасны жагсаалтаас сонгох…"
                                    @commit="(v) => saveField(task.id, 'responsible', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].collaborator"
                                    :editable="canManage"
                                    :options="people"
                                    multiple
                                    placeholder="Утасны жагсаалтаас сонгох…"
                                    @commit="(v) => saveField(task.id, 'collaborator', v)"
                                />
                            </td>
                            <td class="ui-sheet-td">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].note"
                                    multiline
                                    placeholder="Хэрэгжилт…"
                                    :editable="canManage"
                                    @commit="(v) => saveField(task.id, 'note', v)"
                                />
                            </td>
                            <td class="ui-sheet-td text-center">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].progress"
                                    type="number"
                                    align="center"
                                    :editable="canManage"
                                    @commit="() => saveProgress(task.id)"
                                >
                                    {{ drafts[task.id].progress ?? 0 }}%
                                </SheetCell>
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
                        <tr v-if="!visibleTasks.length">
                            <td :colspan="canManage ? 9 : 8" class="!py-14 text-center text-slate-400">
                                {{ filter ? 'Энэ шүүлтэд тохирох үүрэг чиглэл алга.' : 'Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Дэлгэц дүүрэн график дашбоард -->
        <div v-if="showDashboard" class="fixed inset-0 z-50 overflow-y-auto bg-slate-100">
            <div class="w-full p-4 sm:p-6 lg:p-8">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold text-brand-navy-900">Хэрэгжилтийн дашбоард</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ source.name }} · нийт {{ overall.count }} үүрэг чиглэл. Аль ч хэсэг дээр дарж холбогдох
                            үүргүүдийг хүснэгтээр харна.
                        </p>
                    </div>
                    <button type="button" class="ui-btn-ghost" @click="showDashboard = false">Хаах</button>
                </div>

                <div class="grid gap-4 lg:grid-cols-3">
                    <section class="ui-card-pad flex items-center gap-5">
                        <svg viewBox="0 0 130 130" class="h-32 w-32 shrink-0 -rotate-90">
                            <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="16" />
                            <circle
                                cx="65"
                                cy="65"
                                r="52"
                                fill="none"
                                :stroke="overall.progress >= 80 ? '#10b981' : overall.progress >= 50 ? '#3771b8' : '#f59e0b'"
                                stroke-width="16"
                                stroke-linecap="round"
                                :stroke-dasharray="donut(overall.progress).dash"
                            />
                        </svg>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Нийт хэрэгжилт</p>
                            <p class="text-4xl font-bold text-brand-navy-800">{{ overall.progress }}%</p>
                            <p class="mt-1 text-sm text-slate-500">{{ overall.done }}/{{ overall.count }} үүрэг дууссан</p>
                        </div>
                    </section>

                    <section class="ui-card-pad lg:col-span-2">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Гүйцэтгэлийн төлөв</p>
                        <div class="flex h-5 overflow-hidden rounded-full bg-slate-200">
                            <div
                                v-for="segment in statusSegments"
                                :key="segment.label"
                                :style="{ width: segment.percent + '%', background: segment.color }"
                                :title="segment.label + ': ' + segment.value"
                            />
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4 text-sm">
                            <span v-for="segment in statusSegments" :key="segment.label" class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-sm" :style="{ background: segment.color }" />
                                <span class="text-slate-600">{{ segment.label }}</span>
                                <b class="text-slate-800">{{ segment.value }}</b>
                                <span class="text-xs text-slate-400">({{ segment.percent }}%)</span>
                            </span>
                        </div>
                    </section>
                </div>

                <section class="ui-card-pad mt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Ангиллаар · АЗДТГ-ын хэлтсүүд</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <button
                            v-for="item in dashboardCards"
                            :key="item.type + item.key"
                            type="button"
                            class="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-brand-navy-400 hover:shadow-soft"
                            @click="applyFilterAndClose(item.type, item.key, item.label)"
                        >
                            <div class="flex items-center gap-4">
                                <svg viewBox="0 0 130 130" class="h-16 w-16 shrink-0 -rotate-90">
                                    <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="20" />
                                    <circle
                                        cx="65"
                                        cy="65"
                                        r="52"
                                        fill="none"
                                        :stroke="item.progress >= 80 ? '#10b981' : item.progress >= 50 ? '#3771b8' : item.progress > 0 ? '#f59e0b' : '#cbd5e1'"
                                        stroke-width="20"
                                        :stroke-dasharray="donut(item.progress).dash"
                                    />
                                </svg>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-700" :title="item.label">{{ item.label }}</p>
                                    <p class="text-2xl font-bold text-brand-navy-700">{{ item.progress }}%</p>
                                    <p class="text-xs text-slate-500">{{ item.count }} үүрэг · {{ item.done }} дууссан</p>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <section v-if="orgStats.length" class="ui-card-pad mb-8 mt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Хэлтэс, агентлаг, байгууллагаар
                    </p>
                    <div class="space-y-2">
                        <button
                            v-for="item in orgStats"
                            :key="item.key"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-left transition hover:border-brand-navy-400"
                            @click="applyFilterAndClose('org', item.key, item.label)"
                        >
                            <span class="w-64 shrink-0 truncate text-sm text-slate-700" :title="item.label">{{ item.label }}</span>
                            <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-slate-200">
                                <span class="block h-full rounded-full" :class="barColor(item.progress)" :style="{ width: item.progress + '%' }" />
                            </span>
                            <span class="w-12 shrink-0 text-right text-sm font-semibold text-brand-navy-700">{{ item.progress }}%</span>
                            <span class="w-20 shrink-0 text-right text-xs text-slate-500">{{ item.count }} үүрэг</span>
                        </button>
                    </div>
                </section>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
