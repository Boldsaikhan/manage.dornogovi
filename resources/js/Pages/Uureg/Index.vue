<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';
import { expandPersonNames, GROUP_LABELS } from '@/utils/soumGovernors';

const props = defineProps({
    kind: { type: String, required: true },
    source: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const kinds = [
    { key: 'directive', label: 'Үүрэг чиглэл' },
    { key: 'prep_plan', label: 'Бэлтгэл ажил хангах төлөвлөгөө' },
];

const isDirective = computed(() => props.kind === 'directive');

const downloadOpen = ref(false);
const downloadRoot = ref(null);
const downloadFormats = [
    { format: 'docx', label: 'Word (.docx)' },
    { format: 'xlsx', label: 'Excel (.xlsx)' },
    { format: 'pdf', label: 'PDF (.pdf)' },
];

const exportUrl = (format) => route('tasks.export', { kind: props.kind, format });

const toggleDownload = () => {
    downloadOpen.value = ! downloadOpen.value;
};

const closeDownload = (event) => {
    if (! downloadRoot.value?.contains(event.target)) {
        downloadOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', closeDownload));
onBeforeUnmount(() => document.removeEventListener('click', closeDownload));

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

const CATEGORY_ORDER = ['udirdlaga', 'heltes', 'agentlag', 'sum', 'baiguullaga', 'unknown'];
const filter = ref(null); // { type: 'category' | 'org' | 'person', value, label }

const CATEGORY_LABELS = {
    udirdlaga: 'Аймгийн удирдлагууд',
    heltes: 'Хэлтэс',
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

const splitNames = (value) => expandPersonNames(value, props.people);

// Нэг үүрэг олон хариуцагчтай байж болно.
const taskOwners = (task) => {
    const names = splitNames(task.responsible);

    if (! names.length) {
        return [{ value: '—', org: 'Тодорхойгүй', category: 'unknown' }];
    }

    return names.map((name) => {
        if (GROUP_LABELS.includes(name)) {
            return {
                value: name,
                org: name.includes('Агентлаг') ? 'Агентлаг' : 'Сумд',
                category: name.includes('Агентлаг') ? 'agentlag' : 'sum',
            };
        }

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

const averageNumbers = (values) => (values.length
    ? Math.round(values.reduce((sum, n) => sum + n, 0) / values.length)
    : 0);

const buildStats = (keyFn, labelFn) => {
    const groups = new Map();

    props.tasks.forEach((task) => {
        const seen = new Set();

        taskOwners(task).forEach((owner) => {
            const key = keyFn(owner);
            if (seen.has(key)) return;
            seen.add(key);

            if (! groups.has(key)) {
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

// Албан хаагч бүрийн үүргийн дундаж хэрэгжилт.
const personStats = computed(() => {
    const groups = new Map();

    props.tasks.forEach((task) => {
        taskOwners(task).forEach((owner) => {
            if (owner.value === '—') return;

            if (! groups.has(owner.value)) {
                groups.set(owner.value, {
                    key: owner.value,
                    label: owner.value,
                    org: owner.org,
                    category: owner.category,
                    tasks: [],
                });
            }

            const row = groups.get(owner.value);
            // Утасны жагсаалтаас олдсон мэдээллийг илүүд үзнэ.
            if (owner.category !== 'unknown') {
                row.org = owner.org;
                row.category = owner.category;
            }
            if (! row.tasks.some((t) => t.id === task.id)) {
                row.tasks.push(task);
            }
        });
    });

    return [...groups.values()]
        .map((p) => ({
            key: p.key,
            label: p.label,
            org: p.org,
            category: p.category,
            count: p.tasks.length,
            done: p.tasks.filter((t) => Number(t.progress) >= 100).length,
            progress: average(p.tasks),
        }))
        .sort((a, b) => b.progress - a.progress || a.label.localeCompare(b.label, 'mn'));
});

/**
 * Нэгж (хэлтэс/агентлаг…) бүрийн үзүүлэлт =
 * тухайн нэгжийн албан хаагчдын хэрэгжилтийн дундаж.
 */
const unitStats = computed(() => {
    const groups = new Map();

    personStats.value.forEach((person) => {
        const key = person.org || 'Тодорхойгүй';

        if (! groups.has(key)) {
            groups.set(key, {
                key,
                label: key,
                category: person.category || 'unknown',
                people: [],
            });
        }

        const g = groups.get(key);
        if (person.category !== 'unknown' && (g.category === 'unknown' || ! g.category)) {
            g.category = person.category;
        }
        g.people.push(person);
    });

    return [...groups.values()]
        .map((g) => {
            const taskIds = new Set();
            let doneTasks = 0;

            props.tasks.forEach((task) => {
                const owners = taskOwners(task);
                if (owners.some((o) => (o.org || 'Тодорхойгүй') === g.key)) {
                    taskIds.add(task.id);
                    if (Number(task.progress) >= 100) doneTasks += 1;
                }
            });

            return {
                key: g.key,
                label: g.label,
                category: g.category,
                categoryLabel: CATEGORY_LABELS[g.category] ?? g.category,
                people: [...g.people].sort((a, b) => b.progress - a.progress || a.label.localeCompare(b.label, 'mn')),
                peopleCount: g.people.length,
                count: taskIds.size,
                done: doneTasks,
                // Хэлтсийн үзүүлэлт = албан хаагчдын дундаж хэрэгжилт
                progress: averageNumbers(g.people.map((p) => p.progress)),
            };
        })
        .sort((a, b) => {
            const ai = CATEGORY_ORDER.indexOf(a.category);
            const bi = CATEGORY_ORDER.indexOf(b.category);
            if (ai !== bi) return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);

            return b.progress - a.progress || b.count - a.count || a.label.localeCompare(b.label, 'mn');
        });
});

const heltesStats = computed(() => {
    // Утасны жагсаалтад «Хэлтэс» гэж тэмдэглэсэн нэгжүүдээс
    // зөвхөн үүрэг даалгаварт орсон хэлтсүүдийг харуулна.
    const directoryOrgs = new Map();

    props.people.forEach((p) => {
        if (p.category !== 'heltes' || ! p.org) return;

        if (! directoryOrgs.has(p.org)) {
            directoryOrgs.set(p.org, new Set());
        }
        directoryOrgs.get(p.org).add(p.value);
    });

    return unitStats.value
        .filter((u) => u.category === 'heltes' && u.count > 0)
        .map((u) => ({
            ...u,
            directoryPeopleCount: directoryOrgs.get(u.key)?.size || u.peopleCount,
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'mn'));
});

const otherUnitStats = computed(() => unitStats.value.filter((u) => u.category !== 'heltes'));

const shortOrgLabel = (label) => {
    const text = String(label || '');
    if (text.length <= 28) return text;

    return `${text.slice(0, 26)}…`;
};

// Дугуй диаграмын тойрог
const donut = (value, radius = 52) => {
    const circumference = 2 * Math.PI * radius;
    const filled = (Math.max(0, Math.min(100, value)) / 100) * circumference;

    return { circumference, dash: `${filled} ${circumference - filled}` };
};

const statusSegments = computed(() => {
    const total = overall.value.count || 1;

    return [
        { label: 'Дууссан', value: overall.value.done, color: '#22c55e' },
        { label: 'Хэрэгжиж буй', value: overall.value.started, color: '#eab308' },
        { label: 'Эхлээгүй', value: overall.value.pending, color: '#f97316', warn: true },
    ].map((s) => ({ ...s, percent: Math.round((s.value / total) * 100) }));
});

// Ангиллын картуудыг тогтмол дарааллаар харуулна.
const categoryStats = computed(() => buildStats(
    (owner) => owner.category,
    (owner, key) => CATEGORY_LABELS[key] ?? key,
).sort((a, b) => {
    const ai = CATEGORY_ORDER.indexOf(a.key);
    const bi = CATEGORY_ORDER.indexOf(b.key);

    return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
}));

// Хэлтэс ангиллын карт — албан хаагчдын дунджаар (бусад ангилал үүргийн дундаж хэвээр).
const dashboardCards = computed(() => categoryStats.value.map((item) => {
    if (item.key !== 'heltes') {
        return { ...item, type: 'category' };
    }

    const departments = heltesStats.value;
    const progress = departments.length
        ? averageNumbers(departments.map((d) => d.progress))
        : item.progress;

    return {
        ...item,
        type: 'category',
        progress,
        peopleCount: departments.reduce((sum, d) => sum + (d.directoryPeopleCount || d.peopleCount), 0),
        count: departments.reduce((sum, d) => sum + d.count, 0),
    };
}));

const overall = computed(() => ({
    count: props.tasks.length,
    progress: average(props.tasks),
    done: props.tasks.filter((t) => Number(t.progress) >= 100).length,
    started: props.tasks.filter((t) => Number(t.progress) > 0 && Number(t.progress) < 100).length,
    pending: props.tasks.filter((t) => ! Number(t.progress)).length,
}));

/**
 * Хэрэгжилтийн өнгө:
 * 0% (эхлээгүй) — саарал + анхааруулга
 * <50 — улаан · 50–89 — шар · ≥90 — ногоон
 */
const progressLevel = (value) => {
    const n = Number(value) || 0;
    if (n <= 0) return 'pending';
    if (n < 50) return 'low';
    if (n < 90) return 'mid';

    return 'high';
};

const progressStroke = (value) => ({
    pending: '#94a3b8',
    low: '#ef4444',
    mid: '#eab308',
    high: '#22c55e',
}[progressLevel(value)]);

const progressTextClass = (value) => ({
    pending: 'text-slate-500',
    low: 'text-red-600',
    mid: 'text-yellow-600',
    high: 'text-emerald-600',
}[progressLevel(value)]);

const barColor = (value) => ({
    pending: 'bg-slate-300',
    low: 'bg-red-500',
    mid: 'bg-yellow-400',
    high: 'bg-emerald-500',
}[progressLevel(value)]);

const isPendingProgress = (value) => progressLevel(value) === 'pending';

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
    if (! filter.value) return props.tasks;

    return props.tasks.filter((task) => taskOwners(task).some((owner) => {
        if (filter.value.type === 'category') return owner.category === filter.value.value;
        if (filter.value.type === 'person') return owner.value === filter.value.value;

        return owner.org === filter.value.value;
    }));
});

/** Олон мөр сонгоод нэг дор мэдээлэл оруулах */
const selectedIds = ref([]);
const bulkSaving = ref(false);
const bulk = reactive({
    responsible: '',
    collaborator: '',
    note: '',
    progress: '',
});
const bulkApply = reactive({
    responsible: false,
    collaborator: false,
    note: false,
    progress: false,
});

const selectedCount = computed(() => selectedIds.value.length);

const allVisibleSelected = computed(() => (
    visibleTasks.value.length > 0
    && visibleTasks.value.every((t) => selectedIds.value.includes(t.id))
));

const someVisibleSelected = computed(() => (
    visibleTasks.value.some((t) => selectedIds.value.includes(t.id))
));

const isSelected = (id) => selectedIds.value.includes(id);

const toggleSelect = (id) => {
    const i = selectedIds.value.indexOf(id);
    if (i >= 0) {
        selectedIds.value.splice(i, 1);
    } else {
        selectedIds.value.push(id);
    }
};

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        const visible = new Set(visibleTasks.value.map((t) => t.id));
        selectedIds.value = selectedIds.value.filter((id) => ! visible.has(id));
        return;
    }
    const next = new Set(selectedIds.value);
    visibleTasks.value.forEach((t) => next.add(t.id));
    selectedIds.value = [...next];
};

const clearSelection = () => {
    selectedIds.value = [];
};

const resetBulkForm = () => {
    bulk.responsible = '';
    bulk.collaborator = '';
    bulk.note = '';
    bulk.progress = '';
    bulkApply.responsible = false;
    bulkApply.collaborator = false;
    bulkApply.note = false;
    bulkApply.progress = false;
};

const markBulkField = (field, value) => {
    bulk[field] = value;
    bulkApply[field] = true;
};

const applyBulk = () => {
    if (! selectedIds.value.length) return;

    const fields = {};
    if (bulkApply.responsible) fields.responsible = bulk.responsible ?? '';
    if (bulkApply.collaborator) fields.collaborator = bulk.collaborator ?? '';
    if (bulkApply.note) fields.note = bulk.note ?? '';
    if (bulkApply.progress) {
        let n = Number.parseInt(bulk.progress, 10);
        if (Number.isNaN(n)) n = 0;
        n = Math.min(100, Math.max(0, n));
        fields.progress = n;
        bulk.progress = n;
    }

    if (! Object.keys(fields).length) {
        alert('Оруулах талбар сонгоно уу (хажуугийн нүдийг идэвхжүүлнэ үү).');
        return;
    }

    bulkSaving.value = true;
    router.patch(
        route('tasks.bulk'),
        { ids: [...selectedIds.value], fields },
        {
            preserveScroll: true,
            onFinish: () => {
                bulkSaving.value = false;
            },
            onSuccess: () => {
                // Локал draft-уудыг шууд шинэчилж UI-г хүлээхгүй шинэчилнэ.
                selectedIds.value.forEach((id) => {
                    if (! drafts[id]) return;
                    Object.assign(drafts[id], fields);
                });
                clearSelection();
                resetBulkForm();
            },
        },
    );
};

watch(
    () => props.kind,
    () => {
        clearSelection();
        resetBulkForm();
    },
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
    selectedIds.value = selectedIds.value.filter((id) => id !== taskId);
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
    const fixed = 48 + 140 + 160 + 96 + (props.canManage ? 48 + 40 : 0);
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
    const fixed = 48 + 140 + 110 + 140 + 150 + 96 + (props.canManage ? 48 + 40 : 0);
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
                    <div ref="downloadRoot" class="relative">
                        <button
                            type="button"
                            class="ui-btn-ghost"
                            :aria-expanded="downloadOpen"
                            title="Хүснэгтийг Word, Excel, PDF-ээр татах"
                            @click.stop="toggleDownload"
                        >
                            Татах
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <div
                            v-if="downloadOpen"
                            class="absolute right-0 z-30 mt-1.5 min-w-[11rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
                        >
                            <a
                                v-for="item in downloadFormats"
                                :key="item.format"
                                :href="exportUrl(item.format)"
                                class="block px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="downloadOpen = false"
                            >
                                {{ item.label }}
                            </a>
                        </div>
                    </div>
                    <a
                        v-if="latestDocument"
                        :href="route('tasks.documents.download', latestDocument.id)"
                        class="ui-btn-ghost"
                        title="Оруулсан эх Word файлыг татах"
                    >
                        Эх файл
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

                <span class="flex items-center gap-1 text-2xl font-bold" :class="progressTextClass(overall.progress)">
                    <svg
                        v-if="isPendingProgress(overall.progress)"
                        class="h-5 w-5 text-orange-500"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                    </svg>
                    {{ overall.progress }}%
                </span>

                <span class="h-2 min-w-[6rem] flex-1 overflow-hidden rounded-full bg-slate-200">
                    <span class="block h-full rounded-full" :class="barColor(overall.progress)" :style="{ width: overall.progress + '%' }" />
                </span>

                <span class="flex gap-3 text-xs text-slate-500">
                    <span>Нийт <b class="text-slate-700">{{ overall.count }}</b></span>
                    <span>Дууссан <b class="text-emerald-600">{{ overall.done }}</b></span>
                    <span class="inline-flex items-center gap-0.5">
                        Эхлээгүй
                        <svg class="h-3.5 w-3.5 text-orange-500" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                        </svg>
                        <b class="text-orange-600">{{ overall.pending }}</b>
                    </span>
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

            <!-- Олон мөрөнд ижил мэдээлэл оруулах -->
            <div
                v-if="canManage && selectedCount"
                class="sticky top-2 z-20 rounded-2xl border border-brand-navy-200 bg-white/95 p-3 shadow-soft backdrop-blur"
            >
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-brand-navy-800">
                        {{ selectedCount }} мөр сонгосон — нэг удаа мэдээлэл оруулна
                    </p>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-800" @click="clearSelection">
                        Сонголт цуцлах
                    </button>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="bulkApply.responsible" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            Хариуцах эзэн
                        </span>
                        <SheetCell
                            v-model="bulk.responsible"
                            :editable="true"
                            :options="people"
                            multiple
                            placeholder="Сонгох…"
                            @commit="(v) => markBulkField('responsible', v)"
                        />
                    </label>
                    <label class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="bulkApply.collaborator" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            {{ isDirective ? 'Хяналт тавих' : 'Хамтран хэрэгжүүлэх' }}
                        </span>
                        <SheetCell
                            v-model="bulk.collaborator"
                            :editable="true"
                            :options="people"
                            multiple
                            placeholder="Сонгох…"
                            @commit="(v) => markBulkField('collaborator', v)"
                        />
                    </label>
                    <label class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="bulkApply.note" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            Хэрэгжилт
                        </span>
                        <SheetCell
                            v-model="bulk.note"
                            multiline
                            :editable="true"
                            placeholder="Хэрэгжилт…"
                            @commit="(v) => markBulkField('note', v)"
                        />
                    </label>
                    <label class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="bulkApply.progress" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            Биелэлтийн хувь
                        </span>
                        <SheetCell
                            v-model="bulk.progress"
                            type="number"
                            align="center"
                            :editable="true"
                            placeholder="0–100"
                            @commit="(v) => markBulkField('progress', v)"
                        >
                            <span v-if="bulk.progress !== '' && bulk.progress != null">{{ bulk.progress }}%</span>
                            <span v-else class="text-slate-400">—</span>
                        </SheetCell>
                    </label>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="bulkSaving"
                        @click="applyBulk"
                    >
                        {{ bulkSaving ? 'Хадгалж байна…' : 'Сонгосон мөрүүдэд хэрэглэх' }}
                    </button>
                    <span class="text-xs text-slate-500">Зөвхөн идэвхжүүлсэн талбарууд шинэчлэгдэнэ.</span>
                </div>
            </div>

            <!-- Үүрэг чиглэл -->
            <div v-if="isDirective" class="ui-table-wrap w-full overflow-x-auto">
                <table
                    class="ui-table table-fixed"
                    :style="{ width: `${directiveTableMinWidth}px`, minWidth: `${directiveTableMinWidth}px` }"
                >
                    <colgroup>
                        <col v-if="canManage" style="width: 40px" />
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
                            <th v-if="canManage" class="text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="allVisibleSelected"
                                    :ref="(el) => { if (el) el.indeterminate = someVisibleSelected && !allVisibleSelected; }"
                                    title="Бүгдийг сонгох"
                                    @change="toggleSelectAll"
                                />
                            </th>
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
                        <tr
                            v-for="task in visibleTasks"
                            :key="task.id"
                            :class="isSelected(task.id) ? 'bg-brand-navy-50/70' : ''"
                        >
                            <td v-if="canManage" class="text-center align-middle">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="isSelected(task.id)"
                                    @change="toggleSelect(task.id)"
                                />
                            </td>
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
                                    <span :class="progressTextClass(drafts[task.id].progress)">
                                        {{ drafts[task.id].progress ?? 0 }}%
                                    </span>
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
                            <td :colspan="canManage ? 8 : 6" class="!py-14 text-center text-slate-400">
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
                        <col v-if="canManage" style="width: 40px" />
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
                            <th v-if="canManage" class="text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="allVisibleSelected"
                                    :ref="(el) => { if (el) el.indeterminate = someVisibleSelected && !allVisibleSelected; }"
                                    title="Бүгдийг сонгох"
                                    @change="toggleSelectAll"
                                />
                            </th>
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
                        <tr
                            v-for="task in visibleTasks"
                            :key="task.id"
                            :class="isSelected(task.id) ? 'bg-brand-navy-50/70' : ''"
                        >
                            <td v-if="canManage" class="text-center align-middle">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="isSelected(task.id)"
                                    @change="toggleSelect(task.id)"
                                />
                            </td>
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
                                    <span :class="progressTextClass(drafts[task.id].progress)">
                                        {{ drafts[task.id].progress ?? 0 }}%
                                    </span>
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
                            <td :colspan="canManage ? 10 : 8" class="!py-14 text-center text-slate-400">
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
                        <div class="relative h-32 w-32 shrink-0">
                            <svg viewBox="0 0 130 130" class="h-32 w-32 -rotate-90">
                                <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="16" />
                                <circle
                                    cx="65"
                                    cy="65"
                                    r="52"
                                    fill="none"
                                    :stroke="progressStroke(overall.progress)"
                                    stroke-width="16"
                                    stroke-linecap="round"
                                    :stroke-dasharray="donut(overall.progress).dash"
                                />
                            </svg>
                            <span
                                v-if="isPendingProgress(overall.progress)"
                                class="absolute inset-0 flex items-center justify-center text-orange-500"
                                title="Эхлээгүй"
                            >
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                                </svg>
                            </span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Нийт хэрэгжилт</p>
                            <p class="text-4xl font-bold" :class="progressTextClass(overall.progress)">{{ overall.progress }}%</p>
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
                                <span
                                    v-if="segment.warn"
                                    class="inline-flex text-orange-500"
                                    title="Эхлээгүй — анхааруулга"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                                    </svg>
                                </span>
                                <span v-else class="h-3 w-3 rounded-sm" :style="{ background: segment.color }" />
                                <span class="text-slate-600">{{ segment.label }}</span>
                                <b class="text-slate-800">{{ segment.value }}</b>
                                <span class="text-xs text-slate-400">({{ segment.percent }}%)</span>
                            </span>
                        </div>
                    </section>
                </div>

                <section class="ui-card-pad mt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Ангиллаар</p>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <button
                            v-for="item in dashboardCards"
                            :key="item.type + item.key"
                            type="button"
                            class="rounded-2xl border border-slate-200 p-4 text-left transition hover:border-brand-navy-400 hover:shadow-soft"
                            @click="applyFilterAndClose(item.type, item.key, item.label)"
                        >
                            <div class="flex items-center gap-4">
                                <div class="relative h-16 w-16 shrink-0">
                                    <svg viewBox="0 0 130 130" class="h-16 w-16 -rotate-90">
                                        <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="20" />
                                        <circle
                                            cx="65"
                                            cy="65"
                                            r="52"
                                            fill="none"
                                            :stroke="progressStroke(item.progress)"
                                            stroke-width="20"
                                            :stroke-dasharray="donut(item.progress).dash"
                                        />
                                    </svg>
                                    <span
                                        v-if="isPendingProgress(item.progress)"
                                        class="absolute inset-0 flex items-center justify-center text-orange-500"
                                        title="Эхлээгүй"
                                    >
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                            <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                                        </svg>
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-700" :title="item.label">{{ item.label }}</p>
                                    <p class="text-2xl font-bold" :class="progressTextClass(item.progress)">{{ item.progress }}%</p>
                                    <p class="text-xs text-slate-500">
                                        <template v-if="item.key === 'heltes' && item.peopleCount">
                                            {{ item.peopleCount }} албан хаагч · {{ item.count }} үүрэг
                                        </template>
                                        <template v-else>
                                            {{ item.count }} үүрэг · {{ item.done }} дууссан
                                        </template>
                                    </p>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- Утасны жагсаалтын бүх хэлтэс — нэг мөрөнд дугуй диаграм -->
                <section v-if="heltesStats.length" class="ui-card-pad mt-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Хэлтэс бүрийн үзүүлэлт
                    </p>
                    <p class="mb-3 text-xs text-slate-500">
                        Үүрэг даалгаварт орсон хэлтсүүд · хувь = албан хаагчдын дундаж хэрэгжилт.
                    </p>
                    <div class="flex flex-nowrap items-stretch gap-3 overflow-x-auto pb-1">
                        <button
                            v-for="unit in heltesStats"
                            :key="'heltes-' + unit.key"
                            type="button"
                            class="flex min-w-[9.5rem] max-w-[11rem] flex-1 flex-col items-center gap-2 rounded-2xl border border-slate-200 px-2.5 py-3 text-center transition hover:border-brand-navy-400 hover:shadow-soft"
                            :title="unit.label"
                            @click="applyFilterAndClose('org', unit.key, unit.label)"
                        >
                            <div class="relative h-16 w-16 shrink-0">
                                <svg viewBox="0 0 130 130" class="h-16 w-16 -rotate-90">
                                    <circle cx="65" cy="65" r="52" fill="none" stroke="#e2e8f0" stroke-width="18" />
                                    <circle
                                        cx="65"
                                        cy="65"
                                        r="52"
                                        fill="none"
                                        :stroke="progressStroke(unit.progress)"
                                        stroke-width="18"
                                        stroke-linecap="round"
                                        :stroke-dasharray="donut(unit.progress).dash"
                                    />
                                </svg>
                                <span
                                    v-if="isPendingProgress(unit.progress)"
                                    class="absolute inset-0 flex items-center justify-center text-orange-500"
                                    title="Эхлээгүй"
                                >
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                                    </svg>
                                </span>
                            </div>
                            <p class="line-clamp-2 min-h-[2.5rem] text-xs font-semibold leading-snug text-slate-700">
                                {{ shortOrgLabel(unit.label) }}
                            </p>
                            <p class="flex items-center justify-center gap-1 text-xl font-bold" :class="progressTextClass(unit.progress)">
                                <svg
                                    v-if="isPendingProgress(unit.progress)"
                                    class="h-4 w-4 text-orange-500"
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                                </svg>
                                {{ unit.progress }}%
                            </p>
                            <p class="text-[10px] leading-tight text-slate-500">
                                {{ unit.directoryPeopleCount || unit.peopleCount }} хүн
                                <span v-if="unit.count"> · {{ unit.count }} үүрэг</span>
                            </p>
                        </button>
                    </div>
                </section>

                <section v-if="otherUnitStats.length" class="ui-card-pad mb-8 mt-4">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Бусад нэгж (агентлаг, байгууллага…)
                    </p>
                    <div class="space-y-3">
                        <div
                            v-for="unit in otherUnitStats"
                            :key="'other-' + unit.key"
                            class="overflow-hidden rounded-2xl border border-slate-200"
                        >
                            <button
                                type="button"
                                class="flex w-full flex-wrap items-center gap-3 bg-white px-3 py-2.5 text-left transition hover:bg-slate-50"
                                @click="applyFilterAndClose('org', unit.key, unit.label)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-800" :title="unit.label">{{ unit.label }}</span>
                                    <span class="text-[11px] text-slate-400">{{ unit.categoryLabel }}</span>
                                </span>
                                <span class="h-2.5 w-28 overflow-hidden rounded-full bg-slate-200 sm:w-40">
                                    <span
                                        class="block h-full rounded-full"
                                        :class="barColor(unit.progress)"
                                        :style="{ width: unit.progress + '%' }"
                                    />
                                </span>
                                <span class="w-12 shrink-0 text-right text-sm font-bold" :class="progressTextClass(unit.progress)">
                                    <span v-if="isPendingProgress(unit.progress)" class="mr-0.5 inline-block align-middle text-orange-500" title="Эхлээгүй">⚠</span>{{ unit.progress }}%
                                </span>
                                <span class="w-28 shrink-0 text-right text-xs text-slate-500">
                                    {{ unit.peopleCount }} хүн · {{ unit.count }} үүрэг
                                </span>
                            </button>
                            <div v-if="unit.people.length" class="divide-y divide-slate-100 border-t border-slate-100 px-2 py-1">
                                <button
                                    v-for="person in unit.people"
                                    :key="person.key"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left transition hover:bg-slate-50"
                                    @click="applyFilterAndClose('person', person.key, person.label)"
                                >
                                    <span class="w-40 shrink-0 truncate text-sm text-slate-700 sm:w-52">{{ person.label }}</span>
                                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <span
                                            class="block h-full rounded-full"
                                            :class="barColor(person.progress)"
                                            :style="{ width: person.progress + '%' }"
                                        />
                                    </span>
                                    <span class="w-10 shrink-0 text-right text-xs font-semibold" :class="progressTextClass(person.progress)">
                                        <span v-if="isPendingProgress(person.progress)" class="mr-0.5 inline-block align-middle text-orange-500" title="Эхлээгүй">⚠</span>{{ person.progress }}%
                                    </span>
                                    <span class="w-16 shrink-0 text-right text-xs text-slate-400">{{ person.count }} үүрэг</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
