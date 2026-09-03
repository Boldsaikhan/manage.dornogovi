<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import SheetCell from '@/Components/SheetCell.vue';
import TaskPeriodCell from '@/Components/TaskPeriodCell.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import TaskCalendar from '@/Components/TaskCalendar.vue';
import { expandPersonNames, GROUP_LABELS } from '@/utils/soumGovernors';
import { formatTaskPeriodMd, isTaskDone, isTaskOverdue, isTaskPending } from '@/utils/taskPeriod';

const props = defineProps({
    kind: { type: String, required: true },
    kinds: { type: Array, default: () => [] },
    source: { type: Object, required: true },
    columnChoices: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canEditProgress: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    undoCount: { type: Number, default: 0 },
});

const undoing = ref(false);

const undo = () => {
    if (undoing.value || props.undoCount < 1) {
        return;
    }

    undoing.value = true;
    router.post(route('undo.store'), {}, {
        preserveScroll: true,
        onFinish: () => {
            undoing.value = false;
        },
    });
};

const kindTabs = computed(() => (
    props.kinds.length
        ? props.kinds
        : [
            { key: 'directive', label: 'Үүрэг чиглэл', layout: 'directive', is_system: true },
            { key: 'prep_plan', label: 'Бэлтгэл ажил хангах төлөвлөгөө', layout: 'prep_plan', is_system: true },
        ]
));

const isDirective = computed(() => (props.source?.layout || props.kind) !== 'prep_plan');

const defaultColumnChoices = [
    { key: 'sector', label: 'Ажлын чиглэл', field: 'sector', type: 'text', width: 140 },
    { key: 'measure', label: 'Арга хэмжээ', field: 'measure', type: 'multiline', width: 280 },
    { key: 'text', label: 'Үүрэг чиглэл', field: 'text', type: 'multiline', width: 320 },
    { key: 'period', label: 'Хугацаа', field: 'period', type: 'period', width: 120 },
    { key: 'responsible', label: 'Хариуцах эзэн', field: 'responsible', type: 'people', width: 180 },
    { key: 'collaborator', label: 'Хяналт тавих', field: 'collaborator', type: 'people', width: 200 },
    { key: 'note', label: 'Хэрэгжилт', field: 'note', type: 'multiline', width: 160 },
];

const columnChoices = computed(() => (
    props.columnChoices?.length ? props.columnChoices : defaultColumnChoices
));

const tableColumns = computed(() => (
    props.source?.columns?.length ? props.source.columns : (
        isDirective.value
            ? defaultColumnChoices.filter((col) => col.key !== 'sector' && col.key !== 'measure')
            : defaultColumnChoices.filter((col) => col.key !== 'measure').map((col) => (
                col.key === 'text' ? { ...col, label: 'Арга хэмжээ' } : col
            ))
    )
));

const hasColumn = (key) => tableColumns.value.some((col) => col.key === key);

const columnLabel = (key, fallback) => tableColumns.value.find((col) => col.key === key)?.label || fallback;

const viewMode = ref('table');
const statusFilter = ref(null); // 'done' | 'pending' | 'overdue'

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
onBeforeUnmount(() => {
    document.removeEventListener('click', closeDownload);
    // Чирэлт дундуур хуудас солигдвол сонсогчид үлдэхээс сэргийлнэ.
    onColumnPointerUp();
});

const drafts = reactive({});
const fileInput = ref(null);
const showAddForm = ref(false);
const addFormRoot = ref(null);
const wordPreviewing = ref(false);
const wordConfirming = ref(false);
const wordPreview = ref(null); // { document_id, original_name, kind, layout, count, rows }
const isPrepPreview = computed(() => (wordPreview.value?.layout || wordPreview.value?.kind) === 'prep_plan');

const showNewKind = ref(false);
const newKindForm = useForm({
    name: '',
    columns: ['sector', 'measure', 'text', 'period', 'responsible', 'collaborator', 'note'],
});
const deletingKind = ref(false);

const toggleNewKindColumn = (key) => {
    // Чирээд тавихад гарах click нь сонголтыг унтраахаас сэргийлнэ.
    if (Date.now() < suppressColumnClickUntil) {
        return;
    }

    const selected = [...newKindForm.columns];
    const index = selected.indexOf(key);

    if (index >= 0) {
        selected.splice(index, 1);
    } else {
        // Сонгосон дарааллыг хадгална — шинээр сонгсон нь хамгийн ард.
        selected.push(key);
    }

    newKindForm.columns = selected;
};

const isNewKindColumnOn = (key) => newKindForm.columns.includes(key);

/** Сонгосон багана эхэндээ өөрийн дарааллаар, сонгоогүй нь ардаа орно. */
const orderedColumnChoices = computed(() => {
    const byKey = new Map(columnChoices.value.map((col) => [col.key, col]));

    const chosen = newKindForm.columns
        .map((key) => byKey.get(key))
        .filter(Boolean);

    const rest = columnChoices.value.filter((col) => ! newKindForm.columns.includes(col.key));

    return [...chosen, ...rest];
});

/** Сонгосон баганыг зүүн/баруун тийш зөөнө. */
const moveNewKindColumn = (key, step) => {
    const cols = [...newKindForm.columns];
    const from = cols.indexOf(key);
    const to = from + step;

    if (from < 0 || to < 0 || to >= cols.length) {
        return;
    }

    [cols[from], cols[to]] = [cols[to], cols[from]];
    newKindForm.columns = cols;
};

const newKindColumnIndex = (key) => newKindForm.columns.indexOf(key);

/* ---------- Багануудыг чирж зөөх ----------
 *
 * HTML5 drag&drop-ыг ашиглахгүй — энэ төсөлд найдваргүй болох нь батлагдсан.
 * Pointer event-ээр өөрсдөө хөтөлж, elementFromPoint-оор доорх чипийг олно.
 */
const draggingColumn = ref(null);
let columnDragStart = null;
let columnDragMoved = false;
let suppressColumnClickUntil = 0;

const onColumnPointerMove = (event) => {
    if (! columnDragStart) return;

    const dx = event.clientX - columnDragStart.x;
    const dy = event.clientY - columnDragStart.y;

    // Санамсаргүй бага хөдөлгөөнийг чирэлт гэж үзэхгүй.
    if (! columnDragMoved && Math.hypot(dx, dy) < 5) return;

    columnDragMoved = true;
    draggingColumn.value = columnDragStart.key;
    event.preventDefault();

    const el = document.elementFromPoint(event.clientX, event.clientY);
    const overKey = el?.closest('[data-col-drop]')?.dataset.colDrop;

    if (! overKey || overKey === draggingColumn.value || ! isNewKindColumnOn(overKey)) return;

    const cols = [...newKindForm.columns];
    const from = cols.indexOf(draggingColumn.value);
    const to = cols.indexOf(overKey);

    if (from < 0 || to < 0) return;

    cols.splice(to, 0, cols.splice(from, 1)[0]);
    newKindForm.columns = cols;
};

const onColumnPointerUp = () => {
    window.removeEventListener('pointermove', onColumnPointerMove);
    window.removeEventListener('pointerup', onColumnPointerUp);
    window.removeEventListener('pointercancel', onColumnPointerUp);
    document.body.classList.remove('select-none');

    // Чирсний дараах click нь тэмдэглэгээг санамсаргүй унтраахаас сэргийлнэ.
    if (columnDragMoved) {
        suppressColumnClickUntil = Date.now() + 300;
    }

    columnDragStart = null;
    columnDragMoved = false;
    draggingColumn.value = null;
};

const onColumnPointerDown = (event, key) => {
    // Тэмдэглэх нүд, зөөх товч дээр дарахад чирэлт эхлүүлэхгүй.
    if (event.target.closest('input, button')) return;
    if (! isNewKindColumnOn(key)) return;

    columnDragStart = { key, x: event.clientX, y: event.clientY };
    columnDragMoved = false;
    document.body.classList.add('select-none');

    window.addEventListener('pointermove', onColumnPointerMove, { passive: false });
    window.addEventListener('pointerup', onColumnPointerUp);
    window.addEventListener('pointercancel', onColumnPointerUp);
};

const submitNewKind = () => {
    newKindForm.post(route('tasks.sources.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newKindForm.reset();
            showNewKind.value = false;
        },
    });
};

const kindError = ref('');

const removeKind = () => {
    kindError.value = '';

    if (kindTabs.value.length <= 1) {
        kindError.value = 'Сүүлчийн үүрэг даалгаврыг устгах боломжгүй. Эхлээд шинийг нэмнэ үү.';

        return;
    }

    if (! props.kind) {
        kindError.value = 'Үүрэг даалгавар тодорхойгүй байна. Хуудсыг дахин ачаална уу.';

        return;
    }

    if (! confirm('«' + (props.source?.name || '') + '» үүрэг даалгавар болон түүний бүх мөрийг устгах уу?')) {
        return;
    }

    let url;

    // Серверийн route жагсаалт хуучирсан бол route() алдаа өгөж, товч чимээгүй болдог.
    try {
        url = route('tasks.sources.destroy', props.kind);
    } catch (e) {
        kindError.value = 'Устгах хаяг олдсонгүй. Хуудсыг шинэчлээд дахин оролдоно уу.';

        return;
    }

    deletingKind.value = true;
    router.delete(url, {
        preserveScroll: true,
        onError: (errors) => {
            kindError.value = Object.values(errors ?? {})[0]
                || 'Үүрэг даалгаврыг устгаж чадсангүй.';
        },
        onFinish: () => {
            deletingKind.value = false;
        },
    });
};

const uploadForm = useForm({
    kind: props.kind,
    file: null,
});

const addForm = useForm({
    kind: props.kind,
    text: '',
    measure: '',
    period: '',
    responsible: '',
    collaborator: '',
    sector: '',
});

const addPeriodStart = ref('');
const addPeriodEnd = ref('');

watch([addPeriodStart, addPeriodEnd], ([start, end]) => {
    if (start && end) {
        addForm.period = formatTaskPeriodMd(start, end);
    } else if (start) {
        addForm.period = formatTaskPeriodMd(start, start);
    } else {
        addForm.period = '';
    }
});

const resetAddPeriodInputs = () => {
    addPeriodStart.value = '';
    addPeriodEnd.value = '';
};

watch(
    () => props.kind,
    (k) => {
        uploadForm.kind = k;
        uploadForm.file = null;
        addForm.kind = k;
        addForm.reset('text', 'measure', 'period', 'responsible', 'collaborator', 'sector');
        addForm.clearErrors();
        resetAddPeriodInputs();
        showAddForm.value = false;
        closeWordPreview();
        if (fileInput.value) fileInput.value.value = '';
    },
);

watch(
    () => props.tasks,
    (list) => {
        list.forEach((t) => {
            drafts[t.id] = {
                text: t.text ?? '',
                measure: t.measure ?? '',
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

/** Дуусаагүй үүрэг — дашбоард дээр хугацаа + хэрэгжилтийн хувиар. */
const incompleteDashboardTasks = computed(() => props.tasks
    .filter((task) => Number(task.progress) < 100)
    .slice()
    .sort((a, b) => {
        const pa = Number(a.progress) || 0;
        const pb = Number(b.progress) || 0;
        if (pa !== pb) {
            return pa - pb;
        }

        return String(a.period || '').localeCompare(String(b.period || ''), 'mn')
            || String(a.text || '').localeCompare(String(b.text || ''), 'mn');
    }));

const highlightedTaskId = ref(null);

const focusTaskRow = (task) => {
    filter.value = null;
    showDashboard.value = false;
    viewMode.value = 'table';
    highlightedTaskId.value = task.id;
    nextTick(() => {
        document.getElementById(`task-row-${task.id}`)?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    });
};

const focusTaskFromDashboard = (task) => {
    focusTaskRow(task);
};

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
    done: props.tasks.filter((t) => isTaskDone(t)).length,
    started: props.tasks.filter((t) => Number(t.progress) > 0 && Number(t.progress) < 100).length,
    pending: props.tasks.filter((t) => isTaskPending(t)).length,
    overdue: props.tasks.filter((t) => isTaskOverdue(t)).length,
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

const toggleStatusFilter = (key) => {
    statusFilter.value = statusFilter.value === key ? null : key;
};

const clearStatusFilter = () => {
    statusFilter.value = null;
};

const statusFilterLabel = computed(() => ({
    done: 'Дууссан',
    pending: 'Эхлээгүй',
    overdue: 'Хугацаа хэтэрсэн',
}[statusFilter.value] ?? ''));

const emptyTasksMessage = computed(() => {
    if (filter.value && statusFilter.value) {
        return 'Энэ шүүлтэд тохирох үүрэг чиглэл алга.';
    }
    if (statusFilter.value) {
        return `«${statusFilterLabel.value}» ангилалд үүрэг чиглэл алга.`;
    }
    if (filter.value) {
        return 'Энэ шүүлтэд тохирох үүрэг чиглэл алга.';
    }

    return 'Одоогоор мөр алга. «Мөр нэмэх» дарж эхлүүлнэ үү.';
});

// Шүүлттэй үед зөвхөн холбогдох үүрэг чиглэл харагдана.
const visibleTasks = computed(() => {
    let list = props.tasks;

    if (filter.value) {
        list = list.filter((task) => taskOwners(task).some((owner) => {
            if (filter.value.type === 'category') return owner.category === filter.value.value;
            if (filter.value.type === 'person') return owner.value === filter.value.value;

            return owner.org === filter.value.value;
        }));
    }

    if (statusFilter.value === 'done') {
        list = list.filter((task) => isTaskDone(task));
    } else if (statusFilter.value === 'pending') {
        list = list.filter((task) => isTaskPending(task));
    } else if (statusFilter.value === 'overdue') {
        list = list.filter((task) => isTaskOverdue(task));
    }

    return list;
});

const TASK_BATCH = 30;
const renderLimit = ref(TASK_BATCH);

const resetRenderLimit = () => {
    renderLimit.value = TASK_BATCH;
};

const loadMoreTasks = () => {
    if (renderLimit.value >= visibleTasks.value.length) {
        return;
    }

    renderLimit.value = Math.min(renderLimit.value + TASK_BATCH, visibleTasks.value.length);
};

const tableTasks = computed(() => visibleTasks.value.slice(0, renderLimit.value));
const hasMoreTasks = computed(() => renderLimit.value < visibleTasks.value.length);

watch([visibleTasks, () => props.kind, statusFilter, filter], resetRenderLimit, { deep: true });

/** Олон мөр сонгоод нэг дор мэдээлэл оруулах */
const selectedIds = ref([]);
const bulkSaving = ref(false);
const bulk = reactive({
    period: '',
    responsible: '',
    collaborator: '',
    note: '',
    progress: '',
});
const bulkApply = reactive({
    period: false,
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
    bulk.period = '';
    bulk.responsible = '';
    bulk.collaborator = '';
    bulk.note = '';
    bulk.progress = '';
    bulkApply.period = false;
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
    if (bulkApply.period) fields.period = bulk.period ?? '';
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
        viewMode.value = 'table';
        statusFilter.value = null;
    },
);

const switchKind = (key) => {
    router.get(route('tasks.index'), { kind: key }, { preserveState: false });
};

const openAddForm = () => {
    showAddForm.value = true;
    addForm.kind = props.kind;
    requestAnimationFrame(() => {
        addFormRoot.value?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
};

const submitAddForm = () => {
    addForm.kind = props.kind;
    addForm.post(route('tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('text', 'measure', 'period', 'responsible', 'collaborator', 'sector');
            addForm.clearErrors();
            resetAddPeriodInputs();
        },
    });
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

const closeWordPreview = () => {
    wordPreview.value = null;
    wordPreviewing.value = false;
    wordConfirming.value = false;
};

const openWordPreview = async ({ file = null, documentId = null } = {}) => {
    wordPreviewing.value = true;
    wordPreview.value = null;

    try {
        const body = new FormData();
        if (file) {
            body.append('kind', props.kind);
            body.append('file', file);
        } else if (documentId) {
            body.append('document_id', String(documentId));
        } else {
            throw new Error('Файл сонгоно уу.');
        }

        const { data } = await window.axios.post(route('tasks.documents.preview'), body);
        wordPreview.value = data;
        router.reload({ only: ['documents'], preserveScroll: true, preserveState: true });

        if (! data.count) {
            alert('Хүснэгт олдсонгүй. .docx хэлбэртэй, хүснэгттэй файл байх шаардлагатай.');
            closeWordPreview();
        }
    } catch (e) {
        const msg = e?.response?.data?.message
            || e?.response?.data?.errors?.file?.[0]
            || e?.message
            || 'Word файлыг уншиж чадсангүй.';
        alert(msg);
        closeWordPreview();
    } finally {
        wordPreviewing.value = false;
        uploadForm.reset('file');
        if (fileInput.value) fileInput.value.value = '';
    }
};

const onFileChange = (e) => {
    const file = e.target.files?.[0] ?? null;
    uploadForm.file = file;
    if (file) {
        openWordPreview({ file });
    }
};

const confirmWordImport = (replace) => {
    const docId = wordPreview.value?.document_id;
    if (! docId || wordConfirming.value) return;

    if (replace && ! confirm('Одоо байгаа бүх мөрийг устгаад Word-ийн мөрөөр солих уу?')) {
        return;
    }

    wordConfirming.value = true;
    router.post(
        route('tasks.documents.import', docId),
        { replace: !! replace },
        {
            preserveScroll: true,
            onFinish: () => {
                wordConfirming.value = false;
            },
            onSuccess: () => {
                closeWordPreview();
            },
        },
    );
};

const importDocument = (id) => {
    openWordPreview({ documentId: id });
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

const fieldColWidth = (field, { min, max, pxPerChar = 7.2 } = {}) => {
    const widths = props.tasks.map((t) => {
        const draft = drafts[t.id];
        return twoLineWidth(draft?.[field] ?? t[field], { min, max, pxPerChar });
    });
    return widths.length ? Math.max(...widths) : min;
};

const columnWidthPx = (col) => {
    if (col.key === 'text') {
        return fieldColWidth('text', { min: col.width || 280, max: 900 });
    }
    if (col.key === 'measure') {
        return fieldColWidth('measure', { min: col.width || 260, max: 720 });
    }
    if (col.key === 'note') {
        return fieldColWidth('note', { min: col.width || 140, max: 360, pxPerChar: 7 });
    }
    return col.width || 120;
};

/**
 * Хүснэгт дэлгэцээс нарийн байвал илүү зайг энэ багана шингээнэ.
 *
 * Ингэснээр бусад багана өөрийн өргөнөө хадгалж, баруун талд наалдсан
 * «Биелэлтийн хувь», үйлдлийн багана зөв байрлана.
 */
const flexColumnKey = computed(() => {
    const keys = tableColumns.value.map((col) => col.key);

    if (keys.includes('text')) return 'text';
    if (keys.includes('measure')) return 'measure';

    return keys.at(-1) ?? null;
});

const tableMinWidth = computed(() => {
    const cols = tableColumns.value.reduce((sum, col) => sum + columnWidthPx(col), 0);
    return 48 + 96 + cols + (props.canEdit ? 48 + 40 : 0);
});

const tableColspan = computed(() => (
    tableColumns.value.length + 2 + (props.canEdit ? 2 : 0)
));

const cellEditable = (col) => (col.field === 'note' ? props.canEditProgress : props.canEdit);
</script>

<template>
    <Head :title="source.name" />

    <AuthenticatedLayout :title="source.name">
        <div class="ui-page" :class="viewMode === 'table' ? 'ui-page--tasks-table' : ''">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h2 class="ui-title">Үүрэг даалгавар</h2>
                    <p class="ui-subtitle">Word файлын хүснэгтийг уншиж, мөр бүрийг шууд засварлана.</p>
                </div>
                <div
                    class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center"
                >
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        class="hidden"
                        @change="onFileChange"
                    />
                    <button
                        v-if="canEdit || canEditProgress"
                        type="button"
                        class="ui-btn-ghost w-full sm:w-auto"
                        :disabled="undoCount < 1 || undoing"
                        :title="undoCount ? 'Сүүлийн үйлдлийг буцаах' : 'Буцаах үйлдэл алга'"
                        @click="undo"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M9 14L4 9l5-5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 9h10a6 6 0 010 12h-3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Буцаах<span v-if="undoCount"> ({{ undoCount }})</span>
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-primary w-full sm:w-auto"
                        :disabled="wordPreviewing"
                        @click="pickWordFile"
                    >
                        {{ wordPreviewing ? 'Уншиж байна…' : 'Word оруулах' }}
                    </button>
                    <div ref="downloadRoot" class="relative w-full sm:w-auto">
                        <button
                            type="button"
                            class="ui-btn-ghost w-full sm:w-auto"
                            :aria-expanded="downloadOpen"
                            title="Хүснэгтийг Word, Excel, PDF-ээр татах"
                            @click.stop="toggleDownload"
                        >
                            Татах
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <div
                            v-if="downloadOpen"
                            class="absolute left-0 right-0 z-30 mt-1.5 min-w-[11rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg sm:left-auto sm:right-0"
                        >
                            <a
                                v-for="item in downloadFormats"
                                :key="item.format"
                                :href="exportUrl(item.format)"
                                class="block px-3.5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                @click="downloadOpen = false"
                            >
                                {{ item.label }}
                            </a>
                        </div>
                    </div>
                    <a
                        v-if="latestDocument"
                        :href="route('tasks.documents.download', latestDocument.id)"
                        class="ui-btn-ghost w-full sm:w-auto"
                        title="Оруулсан эх Word файлыг татах"
                    >
                        Эх файл
                    </a>
                    <button
                        v-if="canManage && kindTabs.length > 1"
                        type="button"
                        class="ui-btn-ghost w-full text-red-600 sm:w-auto"
                        :disabled="deletingKind"
                        @click="removeKind"
                    >
                        Үүрэг даалгавар устгах
                    </button>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="ui-btn-accent w-full sm:w-auto"
                        @click="openAddForm"
                    >
                        {{ hasColumn('text') && ! hasColumn('sector') ? 'Үүрэг чиглэл нэмэх' : 'Мөр нэмэх' }}
                    </button>
                </div>
            </div>
            <p v-if="uploadForm.errors.file" class="text-sm text-red-600">{{ uploadForm.errors.file }}</p>
            <p v-if="kindError" class="text-sm text-red-600">{{ kindError }}</p>

            <div
                class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:rounded-2xl sm:border sm:border-slate-200 sm:bg-white sm:p-1.5 sm:shadow-soft sm:[&::-webkit-scrollbar]:auto [&::-webkit-scrollbar]:hidden"
            >
                <Link
                    v-for="item in kindTabs"
                    :key="item.key"
                    :href="route('tasks.index', { kind: item.key })"
                    class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-semibold transition sm:px-4"
                    :class="kind === item.key
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 sm:border-0'"
                    @click.prevent="switchKind(item.key)"
                >
                    {{ item.label }}
                </Link>
                <button
                    v-if="canManage"
                    type="button"
                    class="shrink-0 whitespace-nowrap rounded-xl border border-dashed border-brand-navy-300 px-3.5 py-2.5 text-sm font-semibold text-brand-navy-600 transition hover:bg-brand-navy-50 sm:px-4"
                    @click="showNewKind = ! showNewKind"
                >
                    + Үүрэг даалгавар нэмэх
                </button>
            </div>

            <section
                v-if="canManage && showNewKind"
                class="rounded-2xl border border-brand-navy-200 bg-white p-4 shadow-soft sm:p-5"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Шинэ үүрэг даалгавар нэмэх</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Хүснэгтийн толгойд оруулах талбаруудыг сонгоно. Сонгосон дарааллаар багана гарна.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100"
                        @click="showNewKind = false"
                    >
                        Хаах
                    </button>
                </div>
                <form class="space-y-4" @submit.prevent="submitNewKind">
                    <input
                        v-model="newKindForm.name"
                        type="text"
                        class="ui-input"
                        placeholder="Үүрэг даалгаврын нэр"
                        required
                    />
                    <fieldset>
                        <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Хүснэгтийн толгой
                            <span class="ml-2 font-normal normal-case tracking-normal text-slate-400">
                                — чирж эсвэл ‹ › товчоор байрлалыг солино
                            </span>
                        </legend>
                        <!-- Нэг мөрөнд — багтахгүй бол хажуу тийш гүйнэ. Зүүнээс барууншаа баганын дараалал. -->
                        <div class="-mx-1 flex items-stretch gap-2 overflow-x-auto px-1 pb-1">
                            <div
                                v-for="col in orderedColumnChoices"
                                :key="'new-col-' + col.key"
                                :data-col-drop="col.key"
                                class="flex shrink-0 items-center gap-1.5 rounded-xl border px-2.5 py-2 text-sm transition"
                                :class="[
                                    isNewKindColumnOn(col.key)
                                        ? 'cursor-grab touch-none border-brand-navy-300 bg-brand-navy-50 text-brand-navy-800 active:cursor-grabbing'
                                        : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50',
                                    draggingColumn === col.key ? 'opacity-50 ring-2 ring-brand-navy-400' : '',
                                ]"
                                @pointerdown="onColumnPointerDown($event, col.key)"
                            >
                                <label class="flex cursor-pointer items-center gap-1.5 whitespace-nowrap">
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-brand-navy-600"
                                        :checked="isNewKindColumnOn(col.key)"
                                        @change="toggleNewKindColumn(col.key)"
                                    />
                                    <span
                                        v-if="isNewKindColumnOn(col.key)"
                                        class="rounded-md bg-brand-navy-600 px-1.5 text-[11px] font-bold text-white"
                                    >{{ newKindColumnIndex(col.key) + 1 }}</span>
                                    <span class="font-medium">{{ col.label }}</span>
                                </label>

                                <span v-if="isNewKindColumnOn(col.key)" class="inline-flex">
                                    <button
                                        type="button"
                                        class="rounded-l-md border border-brand-navy-200 bg-white px-1 text-xs text-brand-navy-700 hover:bg-brand-navy-100 disabled:opacity-30"
                                        :disabled="newKindColumnIndex(col.key) === 0"
                                        title="Зүүн тийш зөөх"
                                        @click="moveNewKindColumn(col.key, -1)"
                                    >‹</button>
                                    <button
                                        type="button"
                                        class="-ml-px rounded-r-md border border-brand-navy-200 bg-white px-1 text-xs text-brand-navy-700 hover:bg-brand-navy-100 disabled:opacity-30"
                                        :disabled="newKindColumnIndex(col.key) === newKindForm.columns.length - 1"
                                        title="Баруун тийш зөөх"
                                        @click="moveNewKindColumn(col.key, 1)"
                                    >›</button>
                                </span>
                            </div>
                        </div>
                    </fieldset>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <p v-if="newKindForm.columns.length" class="mr-auto text-xs text-slate-500">
                            {{ newKindForm.columns.length }} талбар ·
                            {{ columnChoices.filter((col) => isNewKindColumnOn(col.key)).map((col) => col.label).join(' · ') }}
                        </p>
                        <button type="submit" class="ui-btn-accent whitespace-nowrap" :disabled="newKindForm.processing || ! newKindForm.columns.length">
                            {{ newKindForm.processing ? 'Нэмэж байна…' : 'Нэмэх' }}
                        </button>
                    </div>
                </form>
                <p v-if="newKindForm.errors.name" class="mt-2 text-xs text-red-600">{{ newKindForm.errors.name }}</p>
                <p v-if="newKindForm.errors.columns" class="mt-2 text-xs text-red-600">{{ newKindForm.errors.columns }}</p>
            </section>

            <!-- Шинэ мөр нэмэх форм -->
            <section
                v-if="canEdit && showAddForm"
                ref="addFormRoot"
                class="rounded-2xl border border-brand-navy-200 bg-white p-4 shadow-soft sm:p-5"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">
                            {{ hasColumn('text') && ! hasColumn('sector') ? 'Үүрэг чиглэл нэмэх' : 'Төлөвлөгөөний мөр нэмэх' }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Талбаруудыг бөглөж хадгална. Хоосон үлдээсэн талбар дараа засварлана.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100"
                        @click="showAddForm = false"
                    >
                        Хаах
                    </button>
                </div>

                <form class="space-y-3" @submit.prevent="submitAddForm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label v-if="hasColumn('sector')" class="block">
                            <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('sector', 'Ажлын чиглэл') }}</span>
                            <input v-model="addForm.sector" type="text" class="ui-input w-full" placeholder="Ж: Зудын эсрэг" />
                        </label>
                        <label v-if="hasColumn('period')" class="block" :class="hasColumn('sector') ? '' : 'sm:col-span-2'">
                            <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('period', 'Хугацаа') }}</span>
                            <div class="grid gap-2 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                                <input v-model="addPeriodStart" type="date" class="ui-input w-full" />
                                <span class="hidden text-center text-slate-400 sm:block">—</span>
                                <input v-model="addPeriodEnd" type="date" class="ui-input w-full" :min="addPeriodStart || undefined" />
                            </div>
                            <p v-if="addForm.period" class="mt-1 text-xs text-slate-500">{{ addForm.period }}</p>
                        </label>
                    </div>

                    <label v-if="hasColumn('text')" class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('text', 'Үүрэг чиглэл') }}</span>
                        <textarea
                            v-model="addForm.text"
                            rows="3"
                            class="ui-input w-full resize-y"
                            placeholder="Үүрэг чиглэлийн агуулга…"
                            :required="! hasColumn('measure')"
                        />
                        <p v-if="addForm.errors.text" class="mt-1 text-xs text-red-600">{{ addForm.errors.text }}</p>
                    </label>

                    <label v-if="hasColumn('measure')" class="block">
                        <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('measure', 'Арга хэмжээ') }}</span>
                        <textarea
                            v-model="addForm.measure"
                            rows="3"
                            class="ui-input w-full resize-y"
                            placeholder="Арга хэмжээний тайлбар…"
                            :required="! hasColumn('text')"
                        />
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-if="hasColumn('responsible')">
                            <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('responsible', 'Хариуцах эзэн') }}</span>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <SheetCell
                                    v-model="addForm.responsible"
                                    :options="people"
                                    multiple
                                    placeholder="Нэр сонгох…"
                                    empty-label="—"
                                />
                            </div>
                        </div>
                        <div v-if="hasColumn('collaborator')">
                            <span class="mb-1 block text-xs font-semibold text-slate-600">{{ columnLabel('collaborator', 'Хяналт тавих') }}</span>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 px-2 py-1.5">
                                <SheetCell
                                    v-model="addForm.collaborator"
                                    :options="people"
                                    multiple
                                    placeholder="Нэр сонгох…"
                                    empty-label="—"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
                        <button type="button" class="ui-btn-ghost" @click="showAddForm = false">Болих</button>
                        <button type="submit" class="ui-btn-primary" :disabled="addForm.processing">
                            {{ addForm.processing ? 'Хадгалж байна…' : 'Хадгалах' }}
                        </button>
                    </div>
                </form>
            </section>

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
                                title="Урьдчилан харж хүснэгт болгоно"
                                :disabled="wordPreviewing"
                                @click="importDocument(doc.id)"
                            >
                                Урьдчилан харах
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

            <!-- Дашбоард + төлөвийн шүүлт — нэг мөр -->
            <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft">
                <button
                    type="button"
                    class="flex min-w-[min(100%,16rem)] flex-1 items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-slate-50"
                    @click="openDashboard"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-navy-50 text-brand-navy-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M4 20V10M10 20V4M16 20v-6M22 20H2" stroke-linecap="round" />
                        </svg>
                    </span>
                    <span class="min-w-0 shrink">
                        <span class="block text-sm font-semibold text-slate-800">Хэрэгжилтийн дашбоард</span>
                        <span class="block text-xs text-slate-500">Нийт {{ overall.count }} · дарж дэлгэрэнгүй</span>
                    </span>
                    <span class="flex shrink-0 items-center gap-1 text-xl font-bold sm:text-2xl" :class="progressTextClass(overall.progress)">
                        <svg
                            v-if="isPendingProgress(overall.progress)"
                            class="h-4 w-4 text-orange-500 sm:h-5 sm:w-5"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path d="M12 3.2L2.5 19.5A1.2 1.2 0 003.55 21.2h16.9a1.2 1.2 0 001.05-1.7L12 3.2zm0 5.3c.55 0 1 .4 1 .95v4.6c0 .55-.45 1-1 1s-1-.45-1-1v-4.6c0-.55.45-.95 1-.95zm0 8.5a1.15 1.15 0 110-2.3 1.15 1.15 0 010 2.3z" />
                        </svg>
                        {{ overall.progress }}%
                    </span>
                    <span class="hidden h-2 min-w-[5rem] max-w-[10rem] flex-1 overflow-hidden rounded-full bg-slate-200 sm:block">
                        <span class="block h-full rounded-full" :class="barColor(overall.progress)" :style="{ width: overall.progress + '%' }" />
                    </span>
                </button>

                <div class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-0.5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition sm:px-3"
                        :class="statusFilter === 'done'
                            ? 'bg-emerald-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-emerald-50 hover:text-emerald-700'"
                        @click="toggleStatusFilter('done')"
                    >
                        Дууссан
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                            :class="statusFilter === 'done' ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-700'"
                        >
                            {{ overall.done }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition sm:px-3"
                        :class="statusFilter === 'pending'
                            ? 'bg-orange-500 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700'"
                        @click="toggleStatusFilter('pending')"
                    >
                        Эхлээгүй
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                            :class="statusFilter === 'pending' ? 'bg-white/20 text-white' : 'bg-orange-50 text-orange-700'"
                        >
                            {{ overall.pending }}
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-medium transition sm:px-3"
                        :class="statusFilter === 'overdue'
                            ? 'bg-red-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-red-50 hover:text-red-700'"
                        @click="toggleStatusFilter('overdue')"
                    >
                        <span class="hidden sm:inline">Хугацаа хэтэрсэн</span>
                        <span class="sm:hidden">Хэтэрсэн</span>
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[11px] font-bold"
                            :class="statusFilter === 'overdue' ? 'bg-white/20 text-white' : 'bg-red-50 text-red-700'"
                        >
                            {{ overall.overdue }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Харагдах горим -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Харагдах байдал</span>
                <div class="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 shadow-soft">
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="viewMode === 'table' ? 'bg-brand-navy-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                        @click="viewMode = 'table'"
                    >
                        Хүснэгт
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="viewMode === 'calendar' ? 'bg-brand-navy-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                        @click="viewMode = 'calendar'"
                    >
                        Цаглалт
                    </button>
                </div>
            </div>

            <!-- Цаглалт / төлөвлөгөө -->
            <TaskCalendar
                v-if="viewMode === 'calendar'"
                :tasks="visibleTasks"
                @focus-task="focusTaskRow"
            />

            <div v-if="viewMode === 'table'" class="ui-tasks-table-shell">
            <!-- Идэвхтэй шүүлт -->
            <div v-if="filter || statusFilter" class="flex flex-wrap items-center gap-2 text-sm">
                <span v-if="filter || statusFilter" class="text-slate-500">Шүүлт:</span>
                <span v-if="filter" class="inline-flex items-center gap-2 rounded-full bg-brand-navy-600 px-3 py-1 font-medium text-white">
                    {{ filter.label }}
                    <button type="button" class="text-white/80 hover:text-white" @click="clearFilter">✕</button>
                </span>
                <span v-if="statusFilter" class="inline-flex items-center gap-2 rounded-full bg-slate-700 px-3 py-1 font-medium text-white">
                    {{ statusFilterLabel }}
                    <button type="button" class="text-white/80 hover:text-white" @click="clearStatusFilter">✕</button>
                </span>
                <span class="text-slate-500">{{ visibleTasks.length }} үүрэг чиглэл</span>
            </div>

            <!-- Олон мөрөнд ижил мэдээлэл оруулах -->
            <div
                v-if="canEdit && selectedCount && viewMode === 'table'"
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
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                    <label v-if="hasColumn('period')" class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <input v-model="bulkApply.period" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            Хугацаа
                        </span>
                        <input
                            v-model="bulk.period"
                            type="text"
                            class="ui-input w-full text-sm"
                            placeholder="Ж: 08.01–09.30"
                            @input="markBulkField('period', bulk.period)"
                        />
                    </label>
                    <label v-if="hasColumn('responsible')" class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
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
                    <label v-if="hasColumn('collaborator')" class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
                        <span class="flex items-center gap-2 text-xs font-medium text-slate-600">
                            <input v-model="bulkApply.collaborator" type="checkbox" class="rounded border-slate-300 text-brand-navy-600" />
                            {{ columnLabel('collaborator', isDirective ? 'Хяналт тавих' : 'Хамтран хэрэгжүүлэх') }}
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
                    <label v-if="hasColumn('note')" class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-slate-50/80 p-2">
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

            <!-- Хүснэгт — сонгосон толгойгоор, доод талын хэвтээ гүйлгэх хэсэг үргэлж харагдана -->
            <TableScrollViewport
                fill
                :measure-key="tableMinWidth"
                @near-bottom="loadMoreTasks"
            >
                <!-- Багана цөөн үед дэлгэцээ дүүргэж, олон үед хэвтээ гүйнэ. -->
                <div
                    class="block w-full max-w-none shrink-0"
                    :style="{ minWidth: `${tableMinWidth}px` }"
                >
                <table
                    class="ui-table ui-table--pin-actions table-fixed w-full"
                    :style="{ '--pin-actions-width': canEdit ? '48px' : '0px' }"
                >
                    <colgroup>
                        <col v-if="canEdit" style="width: 40px" />
                        <col style="width: 48px" />
                        <col
                            v-for="col in tableColumns"
                            :key="'w-' + col.key"
                            :style="col.key === flexColumnKey ? undefined : { width: `${columnWidthPx(col)}px` }"
                        />
                        <col style="width: 96px" />
                        <col v-if="canEdit" style="width: 48px" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th v-if="canEdit" class="sticky top-0 z-20 bg-brand-navy-50 text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="allVisibleSelected"
                                    :ref="(el) => { if (el) el.indeterminate = someVisibleSelected && !allVisibleSelected; }"
                                    title="Бүгдийг сонгох"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="sticky top-0 z-20 bg-brand-navy-50 text-center">№</th>
                            <th
                                v-for="col in tableColumns"
                                :key="'h-' + col.key"
                                class="sticky top-0 z-20 bg-brand-navy-50"
                            >
                                {{ col.label }}
                            </th>
                            <th class="sticky top-0 z-20 bg-brand-navy-50 text-center ui-sticky-progress">Биелэлтийн хувь</th>
                            <th v-if="canEdit" class="sticky top-0 z-20 bg-brand-navy-50 text-center ui-sticky-actions" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="task in tableTasks"
                            :id="'task-row-' + task.id"
                            :key="task.id"
                            :class="isSelected(task.id) || highlightedTaskId === task.id ? 'bg-brand-navy-50/70' : ''"
                        >
                            <td v-if="canEdit" class="text-center align-middle">
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-navy-600"
                                    :checked="isSelected(task.id)"
                                    @change="toggleSelect(task.id)"
                                />
                            </td>
                            <td class="text-center font-semibold text-slate-500">{{ task.no }}</td>
                            <td
                                v-for="col in tableColumns"
                                :key="task.id + '-' + col.key"
                                class="ui-sheet-td"
                            >
                                <TaskPeriodCell
                                    v-if="col.type === 'period' && drafts[task.id]"
                                    v-model="drafts[task.id][col.field]"
                                    :editable="cellEditable(col)"
                                    placeholder="08.01–09.30"
                                    @commit="(v) => saveField(task.id, col.field, v)"
                                />
                                <SheetCell
                                    v-else-if="col.type === 'people' && drafts[task.id]"
                                    v-model="drafts[task.id][col.field]"
                                    :editable="cellEditable(col)"
                                    :options="people"
                                    multiple
                                    placeholder="Утасны жагсаалтаас сонгох…"
                                    @commit="(v) => saveField(task.id, col.field, v)"
                                />
                                <SheetCell
                                    v-else-if="drafts[task.id]"
                                    v-model="drafts[task.id][col.field]"
                                    :multiline="col.type === 'multiline'"
                                    :editable="cellEditable(col)"
                                    :placeholder="col.key === 'note' ? 'Хэрэгжилт…' : ''"
                                    @commit="(v) => saveField(task.id, col.field, v)"
                                />
                            </td>
                            <td class="ui-sheet-td ui-sticky-progress text-center">
                                <SheetCell
                                    v-if="drafts[task.id]"
                                    v-model="drafts[task.id].progress"
                                    type="number"
                                    align="center"
                                    :editable="canEditProgress"
                                    @commit="() => saveProgress(task.id)"
                                >
                                    <span :class="progressTextClass(drafts[task.id].progress)">
                                        {{ drafts[task.id].progress ?? 0 }}%
                                    </span>
                                </SheetCell>
                            </td>
                            <td v-if="canEdit" class="ui-sticky-actions text-center align-middle">
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
                            <td :colspan="tableColspan" class="!py-14 text-center text-slate-400">
                                {{ emptyTasksMessage }}
                            </td>
                        </tr>
                        <tr v-else-if="hasMoreTasks">
                            <td :colspan="tableColspan" class="!py-3 text-center text-xs text-slate-400">
                                Дараах {{ visibleTasks.length - renderLimit }} мөр — доош гүйлгэнэ үү
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>
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

                <section class="ui-card-pad mb-8 mt-4">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        Хэрэгжээгүй үүрэг даалгавар
                    </p>
                    <p class="mb-3 text-xs text-slate-500">
                        Дуусаагүй үүрэг, хэрэгжилтийн хуваарь (хугацаа) болон биелэлтийн хувь.
                    </p>
                    <p v-if="! incompleteDashboardTasks.length" class="text-sm text-slate-400">
                        Бүх үүрэг 100% хэрэгжсэн байна.
                    </p>
                    <div v-else class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[36rem] border-collapse text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-10 px-3 py-2">№</th>
                                    <th class="px-3 py-2">Үүрэг чиглэл</th>
                                    <th class="w-36 px-3 py-2">Хугацаа</th>
                                    <th class="w-44 px-3 py-2">Хариуцах эзэн</th>
                                    <th class="w-40 px-3 py-2 text-right">Хэрэгжилт</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="task in incompleteDashboardTasks"
                                    :key="'open-' + task.id"
                                    class="cursor-pointer align-top transition hover:bg-slate-50"
                                    @click="focusTaskFromDashboard(task)"
                                >
                                    <td class="px-3 py-2.5 text-slate-400">{{ task.no }}</td>
                                    <td class="max-w-md whitespace-pre-wrap px-3 py-2.5 text-slate-800">{{ task.text || '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-700">{{ task.period || '—' }}</td>
                                    <td class="px-3 py-2.5 text-slate-700">{{ task.responsible || '—' }}</td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-2">
                                            <span class="h-2 w-16 overflow-hidden rounded-full bg-slate-200">
                                                <span
                                                    class="block h-full rounded-full"
                                                    :class="barColor(task.progress)"
                                                    :style="{ width: (Number(task.progress) || 0) + '%' }"
                                                />
                                            </span>
                                            <span class="w-12 text-right text-sm font-bold" :class="progressTextClass(task.progress)">
                                                <span v-if="isPendingProgress(task.progress)" class="mr-0.5 inline text-orange-500" title="Эхлээгүй">⚠</span>{{ Number(task.progress) || 0 }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Word урьдчилсан харах -->
        <Modal :show="!! wordPreview" max-width="7xl" @close="closeWordPreview">
            <div class="flex max-h-[85vh] flex-col">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-800">Word-ийн урьдчилсан харагдах байдал</h3>
                    <p class="mt-0.5 text-sm text-slate-500">
                        <span class="font-medium text-slate-700">{{ wordPreview?.original_name }}</span>
                        · {{ wordPreview?.count ?? 0 }} мөр · хүснэгтэд оруулахаасаа өмнө шалгана уу
                    </p>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-3 py-3 sm:px-5">
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[40rem] border-collapse text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="w-12 px-3 py-2.5">№</th>
                                    <template v-if="isPrepPreview">
                                        <th class="px-3 py-2.5">Ажлын чиглэл</th>
                                        <th class="px-3 py-2.5">Арга хэмжээ</th>
                                        <th class="px-3 py-2.5">Хугацаа</th>
                                        <th class="px-3 py-2.5">Хариуцах эзэн</th>
                                        <th class="px-3 py-2.5">Хамтран хэрэгжүүлэх</th>
                                    </template>
                                    <template v-else>
                                        <th class="px-3 py-2.5">Үүрэг чиглэл</th>
                                        <th class="px-3 py-2.5">Хариуцах эзэн</th>
                                        <th class="px-3 py-2.5">Хяналт тавих</th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="(row, idx) in (wordPreview?.rows || [])"
                                    :key="idx"
                                    class="align-top"
                                >
                                    <td class="px-3 py-2.5 text-slate-400">{{ idx + 1 }}</td>
                                    <template v-if="isPrepPreview">
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.sector || '—' }}</td>
                                        <td class="max-w-md whitespace-pre-wrap px-3 py-2.5 text-slate-800">{{ row.text || '—' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.period || '—' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.responsible || '—' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.collaborator || '—' }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="max-w-lg whitespace-pre-wrap px-3 py-2.5 text-slate-800">{{ row.text || '—' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.responsible || '—' }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ row.collaborator || '—' }}</td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t border-slate-100 px-5 py-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-slate-500">
                        «Нэмэх» — одоогийн мөр дээр нэмнэ. «Орлуулах» — бүх мөрийг солино.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-ghost" :disabled="wordConfirming" @click="closeWordPreview">
                            Болих
                        </button>
                        <button
                            type="button"
                            class="ui-btn-ghost"
                            :disabled="wordConfirming || ! wordPreview?.count"
                            @click="confirmWordImport(true)"
                        >
                            Орлуулах
                        </button>
                        <button
                            type="button"
                            class="ui-btn-primary"
                            :disabled="wordConfirming || ! wordPreview?.count"
                            @click="confirmWordImport(false)"
                        >
                            {{ wordConfirming ? 'Оруулж байна…' : 'Хүснэгтэд нэмэх' }}
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
