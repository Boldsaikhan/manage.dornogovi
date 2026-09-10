<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';
import TableScrollViewport from '@/Components/TableScrollViewport.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    tab: { type: String, default: 'zahiramj_a' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    pendingOfficials: { type: Array, default: () => [] },
    nextNumber: { type: String, default: null },
    canManage: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canExport: { type: Boolean, default: true },
    canPrint: { type: Boolean, default: true },
    canImportFile: { type: Boolean, default: false },
    undoCount: { type: Number, default: 0 },
});

const downloadOpen = ref(false);
const downloadRoot = ref(null);

const downloadFormats = [
    { format: 'docx', label: 'Word (.docx)' },
    { format: 'xlsx', label: 'Excel (.xlsx)' },
    { format: 'pdf', label: 'PDF (.pdf)' },
];

const exportUrl = (format) => route('decrees.export', { tab: props.tab, format });

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

const isBlank = computed(() => props.tab === 'blank');
const isNiit = computed(() => props.tab === 'niit');
const isZahiramj = computed(() => props.tab.startsWith('zahiramj'));
const isDaalgavar = computed(() => props.tab === 'alban_daalgavar');
const isDoc = computed(() => ! isBlank.value);

const docLabel = computed(() => {
    if (isNiit.value) return 'Захирамж, тушаал';
    if (isDaalgavar.value) return 'Албан даалгавар';
    return isZahiramj.value ? 'Захирамж' : 'Тушаал';
});
const titleLabel = computed(() => {
    if (isNiit.value) return 'Гарчиг / тэргүү';
    if (isDaalgavar.value) return 'Албан даалгаврын гарчиг';
    return isZahiramj.value ? 'Захирамжийн тэргүү' : 'Тушаалын гарчиг';
});
const numberLabel = computed(() => {
    if (isNiit.value) return 'Бүртгэл';
    if (isDaalgavar.value) return 'Албан даалгаврын дугаар';
    return isZahiramj.value ? 'Захирамжийн дугаар' : 'Тушаалын дугаар';
});

const kindOptions = [
    { value: 'zahiramj_a', label: 'Захирамж А' },
    { value: 'zahiramj_b', label: 'Захирамж Б' },
    { value: 'tushaal_a', label: 'Тушаал А' },
    { value: 'tushaal_b', label: 'Тушаал Б' },
    { value: 'alban_daalgavar', label: 'Албан даалгавар' },
];

const canAddRow = computed(() => (props.canEdit || props.canManage) && ! isNiit.value);

const canEditRows = computed(() => props.canEdit || props.canManage);
const canManageRows = computed(() => props.canManage || props.canEdit);

/**
 * «Боловсруулсан албан тушаалтан» — утасны жагсаалтын БҮХ албан хаагч.
 *
 * Бланк авсан хүмүүсийг эхэнд нь, «Бланк авсан» тэмдэглэгээтэй харуулна —
 * ихэвчлэн тэднийг сонгодог ч, жагсаалтаас хэнийг ч сонгож болно.
 */
const officialOptions = computed(() => {
    const pending = props.pendingOfficials ?? [];
    const pendingHints = new Map(pending.map((p) => [p.value, p.hint || 'Бланк авсан']));
    const seen = new Set();
    const options = [];

    const push = (option) => {
        if (! option?.value || seen.has(option.value)) return;

        seen.add(option.value);
        options.push(option);
    };

    pending.forEach(push);

    (props.people ?? []).forEach((person) => push({
        ...person,
        hint: pendingHints.has(person.value)
            ? [pendingHints.get(person.value), person.hint].filter(Boolean).join(' · ')
            : person.hint,
    }));

    // Хүснэгтэд бичигдсэн боловч жагсаалтад байхгүй нэрс.
    props.rows.forEach((row) => push({
        value: row.person_name,
        label: row.person_name,
        hint: '',
        org: '',
        category: 'baiguullaga',
    }));

    return options;
});

/**
 * Багана тус бүрийн хайлт.
 *
 * Хүснэгтийн толгойн доор жижиг талбар гарч, бичсэн үгээр нь мөрүүдийг
 * шүүнэ. Хэд хэдэн баганад зэрэг бичвэл бүгдэд нь тохирсон мөр л үлдэнэ.
 */
const filters = reactive({
    // Бланкны хүснэгтийн багана.
    blank_person: '',
    blank_issued_on: '',
    qty_zahiramj: '',
    qty_zahiramj_mn: '',
    qty_tushaal: '',
    qty_tushaal_mn: '',
    qty_assignment: '',
    qty_assignment_mn: '',
    qty_council: '',
    qty_council_mn: '',
    num_zahiramj: '',
    num_tushaal: '',
    void_zahiramj: '',
    void_tushaal: '',
    // Захирамж, тушаалын хүснэгтийн багана.
    issued_on: '',
    number: '',
    title: '',
    page_count: '',
    effective_on: '',
    attachment_name: '',
    attachment_pages: '',
    original_form: '',
    file_index: '',
    person_name: '',
    kind_label: '',
});

const hasFilters = computed(() => Object.values(filters).some((v) => String(v).trim() !== ''));

const clearFilters = () => {
    Object.keys(filters).forEach((key) => (filters[key] = ''));
};

// Таб солигдоход хайлт цэвэрлэгдэнэ.
watch(() => props.tab, () => clearFilters());

/** Хайхдаа том/жижиг үсэг, ө/о, ү/у зэргийн зөрүүг үл тооно. */
const searchKey = (value) => String(value ?? '')
    .toLowerCase()
    .replace(/ө/g, 'о')
    .replace(/ү/g, 'у')
    .replace(/ё/g, 'е')
    .replace(/й/g, 'и');

/** Огноог зөвхөн цифрээр нь харьцуулна: «2026.09.03», «09/03», «0903» бүгд таарна. */
const DATE_FIELDS = ['issued_on', 'effective_on', 'blank_issued_on'];

const digitsOnly = (value) => String(value ?? '').replace(/\D+/g, '');

const matchesFilters = (row) => Object.entries(filters).every(([field, needle]) => {
    const text = String(needle).trim();

    if (text === '') return true;

    if (DATE_FIELDS.includes(field)) {
        const value = field === 'effective_on'
            ? (row.effective_on_display ?? row.effective_on ?? '')
            : (row.issued_on ?? '');

        return digitsOnly(value).includes(digitsOnly(text));
    }

    const value = field === 'number'
        ? (row.number_display ?? row.number)
        : (field === 'blank_person' ? row.person_name : row[field]);

    return searchKey(value).includes(searchKey(text));
});

const visibleRows = computed(() => (hasFilters.value ? props.rows.filter(matchesFilters) : props.rows));

/**
 * Нэг дор бүх мөрийг зурахгүй.
 *
 * Мөр бүр 10 орчим засварлагдах нүдтэй тул 500 мөр = 5000 бүрэлдэхүүн болж
 * таб солиход мэдэгдэхүйц удаашруулна. Эхлээд эхний хэсгийг зураад, доош
 * гүйлгэхэд нэмж зурна.
 */
const RENDER_STEP = 60;

const renderLimit = ref(RENDER_STEP);

const renderedRows = computed(() => visibleRows.value.slice(0, renderLimit.value));

const hasMoreRows = computed(() => visibleRows.value.length > renderedRows.value.length);

const growRenderLimit = () => {
    if (hasMoreRows.value) {
        renderLimit.value += RENDER_STEP;
    }
};

// Таб солих, хайлт өөрчлөгдөхөд эхнээс нь эхэлнэ.
watch(() => [props.tab, hasFilters.value, visibleRows.value.length], () => {
    renderLimit.value = RENDER_STEP;
});

/**
 * Толгойн мөрүүдийг наалдуулах.
 *
 * Мөрийн өндөр нь бичвэрийн урт, дэлгэцийн өргөнөөс хамаарч өөрчлөгддөг тул
 * байрлалыг CSS-д тогтмолоор бичих боломжгүй — бодит өндрийг нь хэмжиж
 * дараалуулна. Үгүй бол мөрүүдийн хооронд завсар үүсч, өгөгдөл цухуйна.
 */
const sheetEl = ref(null);
const blankSheetEl = ref(null);

const syncStickyHead = () => {
    const head = (sheetEl.value ?? blankSheetEl.value)?.querySelector('thead');

    if (! head) return;

    let top = 0;

    Array.from(head.rows).forEach((row, index) => {
        // 1 пикселээр давхарлана — хүрээ хуваалцсанаас үүсэх завсрыг арилгана.
        const offset = index === 0 ? 0 : Math.max(0, Math.round(top) - index);

        Array.from(row.cells).forEach((cell) => {
            cell.style.position = 'sticky';
            cell.style.top = `${offset}px`;
            cell.style.zIndex = String(30 - index);
        });

        top += row.getBoundingClientRect().height;
    });
};

const scheduleStickySync = () => nextTick(() => requestAnimationFrame(syncStickyHead));

onMounted(() => {
    scheduleStickySync();
    // Фонт хожуу ачаалагдвал толгойн өндөр өөрчлөгддөг тул дахин нэг хэмжинэ.
    setTimeout(syncStickyHead, 400);
    window.addEventListener('resize', syncStickyHead);
});

onBeforeUnmount(() => window.removeEventListener('resize', syncStickyHead));

// Мөр нэмэгдэх, таб солигдох, хайлт шүүхэд толгойн өндөр өөрчлөгдөж болно.
watch(() => [props.rows.length, props.tab, hasFilters.value], scheduleStickySync);

const canManage = computed(() => canManageRows.value);

const cellClass = 'decree-sheet__cell';

// Сүүлийн үйлдлийг буцаана (сервер дээр хадгалагддаг тул дахин ачаалсан ч ажиллана).
const undoing = ref(false);

const undo = () => {
    if (undoing.value || props.undoCount < 1) return;

    undoing.value = true;
    router.post(route('undo.store'), {}, {
        preserveScroll: true,
        onFinish: () => (undoing.value = false),
    });
};

const drafts = reactive({});

// Хэвлэмэл хуудасны бүлгүүд — нэг мөрд зөвхөн нэгийг нь бөглөнө.
const BLANK_GROUPS = {
    zahiramj: ['qty_zahiramj', 'qty_zahiramj_mn'],
    tushaal: ['qty_tushaal', 'qty_tushaal_mn'],
    assignment: ['qty_assignment', 'qty_assignment_mn'],
    council: ['qty_council', 'qty_council_mn'],
};

const activeGroup = (rowId) => {
    const draft = drafts[rowId];
    if (! draft) return null;

    return Object.keys(BLANK_GROUPS).find(
        (group) => BLANK_GROUPS[group].some((field) => Number(draft[field]) > 0),
    ) ?? null;
};

// Хэвлэмэл хуудас авсан ажилтны нэр бөглөгдсөн эсэх.
const hasPerson = (rowId) => String(drafts[rowId]?.person_name ?? '').trim() !== '';

// Тухайн бүлгийн нүд засварлаж болох эсэх:
// нэр сонгосон, өөр бүлэг бөглөгдөөгүй байх ёстой.
const groupEditable = (rowId, group) => {
    if (! canManage.value || ! hasPerson(rowId)) return false;

    const active = activeGroup(rowId);

    return active === null || active === group;
};

// Идэвхгүй нүдийг саарлаар ялгана.
const qtyCellClass = (rowId, group) => [
    cellClass,
    groupEditable(rowId, group) ? '' : 'decree-sheet__cell--muted',
];

const imageInput = ref(null);
const uploadingId = ref(null);
const preview = ref(null);

const blankFields = [
    'person_name', 'issued_on',
    'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
    'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
    'num_zahiramj', 'num_tushaal', 'void_zahiramj', 'void_tushaal', 'body',
];

const docFields = [
    'kind', 'number', 'issued_on', 'title', 'page_count', 'effective_on',
    'attachment_name', 'attachment_pages', 'original_form', 'file_index',
    'person_name',
];

/**
 * Нүдний утгыг зөвхөн зурагдаж байгаа мөрд бэлдэнэ.
 *
 * 500 мөрд урьдчилан бэлдэх нь таб солих бүрд 5000 гаруй реактив утга
 * үүсгэдэг байсан — доош гүйлгэхэд шинэ мөрүүд нь өөрсдөө нэмэгдэнэ.
 */
const buildDraft = (row) => {
    if (isBlank.value) {
        return Object.fromEntries(blankFields.map((f) => [f, row[f] ?? '']));
    }

    const draft = Object.fromEntries(docFields.map((f) => [f, row[f] ?? '']));

    // «Дагаж мөрдөх» огноог тусад нь заагаагүй бол батлагдсан огноогоор
    // урьдчилан харуулна. Хэрэглэгч засвал өөрийнх нь утга хадгалагдана.
    if (! draft.effective_on) {
        draft.effective_on = row.issued_on ?? '';
    }

    return draft;
};

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    ensureDrafts();
};

/** Зурагдах мөрүүдэд нүдний утга бэлэн эсэхийг хангана. */
const ensureDrafts = () => {
    renderedRows.value.forEach((row) => {
        if (! drafts[row.id]) {
            drafts[row.id] = buildDraft(row);
        }
    });
};

// Мөрүүд бүхэлдээ солигддог тул гүн ажиглах шаардлагагүй.
watch(() => [props.rows, props.tab], syncDrafts, { immediate: true });

// Доош гүйлгэх, хайх үед шинээр гарч ирсэн мөрүүдэд бэлдэнэ.
// flush: 'sync' — зурагдахаас өмнө бэлэн болно, эс бөгөөс нүд хоромхон
// зуур хоосон харагдана.
watch(renderedRows, ensureDrafts, { flush: 'sync' });

/**
 * Таб солих.
 *
 * Зөвхөн өөрчлөгдөх өгөгдлийг сервэрээс авна — утасны жагсаалт зэрэг бүх
 * табд ижил зүйлийг дахин дахин илгээхгүй тул мэдэгдэхүйц хурдан болно.
 */
const switchTab = (value) => {
    router.get(route('decrees.index'), { tab: value }, {
        preserveScroll: true,
        only: [
            'tab', 'tabs', 'rows', 'pendingOfficials', 'nextNumber',
            'canManage', 'canEdit', 'canExport', 'canPrint', 'canImportFile', 'undoCount',
        ],
    });
};

const today = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
};

const addingRow = ref(false);

const addRow = () => {
    // Хариу ирэхээс өмнө дахин дарвал хоосон мөр давхарлан үүсдэг байсан.
    if (isNiit.value || ! canAddRow.value || addingRow.value) return;

    addingRow.value = true;

    const payload = isBlank.value
        ? { tab: 'blank', person_name: '', issued_on: today() }
        : { tab: props.tab, title: '', issued_on: today() };

    router.post(route('decrees.store'), payload, {
        preserveScroll: true,
        onFinish: () => { addingRow.value = false; },
    });
};

/**
 * «Засварлах» горим — толгойн товчоор асаана.
 *
 * Унтраалттай үед хүснэгт зөвхөн харагдана: нүдэн дээр санамсаргүй дарж утга
 * өөрчлөгдөхгүй. Асаалттай үед бүх нүд идэвхжиж, мөр устгах товч гарна.
 */
const editMode = ref(false);

const rowEditable = () => canManage.value && editMode.value;

const toggleEditMode = () => {
    editMode.value = ! editMode.value;
};

// Таб солигдоход засварын горим хаагдана.
watch(() => props.tab, () => {
    editMode.value = false;
});

const saveField = (id, field, value) => {
    let next = value;
    const qtyFields = [
        'qty_zahiramj', 'qty_zahiramj_mn', 'qty_tushaal', 'qty_tushaal_mn',
        'qty_assignment', 'qty_assignment_mn', 'qty_council', 'qty_council_mn',
        'page_count', 'attachment_pages',
    ];

    if (qtyFields.includes(field)) {
        if (next === '' || next === null || next === undefined) {
            next = null;
        } else {
            const n = Number.parseInt(next, 10);
            next = Number.isNaN(n) ? null : n;
        }
    } else if (typeof next === 'string') {
        next = next.trim() === '' ? null : next;
    }

    if (drafts[id] && Object.prototype.hasOwnProperty.call(drafts[id], field)) {
        drafts[id][field] = next ?? '';
    }

    // Батлагдсан огноог сольсон бөгөөд дагаж мөрдөхийг нь тусад нь заагаагүй
    // бол харагдах утгыг нь дагуулна.
    if (field === 'issued_on' && drafts[id]) {
        const row = props.rows.find((r) => r.id === id);

        if (row && ! row.effective_on) {
            drafts[id].effective_on = next ?? '';
        }
    }

    router.patch(
        route('decrees.update', id),
        { [field]: next },
        { preserveScroll: true, preserveState: true },
    );
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('decrees.destroy', id), { preserveScroll: true });
};

const MAX_FILE_BYTES = 2 * 1024 * 1024;

/** PDF-ийг оруулж байгаа мөр — товч дээр «Боловсруулж байна» гэж харуулна. */
const compressingId = ref(null);

const pickImage = (id) => {
    uploadingId.value = id;
    imageInput.value?.click();
};

const onImagePicked = async (event) => {
    const file = event.target.files?.[0];
    const id = uploadingId.value;
    event.target.value = '';

    if (! file || ! id) return;

    const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);

    if (! isPdf) {
        alert('Зөвхөн PDF файл оруулна уу.');
        uploadingId.value = null;
        return;
    }

    try {
        let ready = file;

        // 2MB-аас хэтэрсэн бол хөтөч дээр нь шахаж багтаана.
        if (file.size > MAX_FILE_BYTES) {
            compressingId.value = id;
            const { compressPdfToLimit } = await import('@/Support/pdfCompress.js');
            ready = await compressPdfToLimit(file, MAX_FILE_BYTES);
        }

        useForm({ image: ready }).post(route('decrees.image.upload', id), {
            forceFormData: true,
            preserveScroll: true,
        });
    } catch (err) {
        alert(err?.message || 'Файл оруулахад алдаа гарлаа.');
    } finally {
        compressingId.value = null;
        uploadingId.value = null;
    }
};

const openPreview = (row) => {
    if (! row.image_url) return;
    preview.value = {
        url: row.image_url,
        is_pdf: row.image_is_pdf !== false,
        title: [row.number, row.title].filter(Boolean).join(' — ') || 'Хавсаргасан PDF',
    };
};

const closePreview = () => {
    preview.value = null;
};

const removeImage = (id) => {
    if (!confirm('Хавсаргасан файлыг устгах уу?')) return;
    router.delete(route('decrees.image.destroy', id), { preserveScroll: true });
};

/**
 * Excel/Word файлаас бүртгэл оруулах.
 *
 * Эхлээд файлыг уншиж, багануудыг таньж урьдчилан харуулна. Хэрэглэгч
 * тааруулгыг шалгаж, «Оруулах» дарсны дараа л хадгална.
 */
const importInput = ref(null);
const importBusy = ref(false);
const importData = ref(null);

const canImport = computed(
    () => props.canImportFile && ! isNiit.value && ! isBlank.value,
);

const pickImportFile = () => importInput.value?.click();

const onImportFile = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (! file) return;

    importBusy.value = true;

    try {
        const form = new FormData();
        form.append('file', file);
        form.append('tab', props.tab);

        const { data } = await window.axios.post(route('decrees.import.preview'), form);

        importData.value = { ...data, file_name: file.name };
    } catch (err) {
        alert(
            err?.response?.data?.errors?.file?.[0]
            || err?.response?.data?.message
            || 'Файлыг уншиж чадсангүй.',
        );
    } finally {
        importBusy.value = false;
    }
};

/** Багана дахин тааруулахад мөрүүд шинэчлэгдэнэ. */
const remapImport = () => {
    if (! importData.value) return;

    const { rows, mapping } = importData.value;

    importData.value.entries = rows.map((row) => {
        const entry = {};

        Object.keys(importData.value.fields).forEach((field) => {
            const index = mapping[field];
            entry[field] = index === null || index === undefined ? null : (row[index] ?? null);
        });

        return entry;
    });
};

const importSample = computed(() => (importData.value?.entries ?? []).slice(0, 8));

const closeImport = () => (importData.value = null);

const confirmImport = () => {
    if (! importData.value || importBusy.value) return;

    importBusy.value = true;

    router.post(route('decrees.import.store'), {
        tab: props.tab,
        entries: importData.value.entries,
    }, {
        preserveScroll: true,
        onSuccess: () => closeImport(),
        onFinish: () => { importBusy.value = false; },
    });
};

/**
 * Өөрчлөлтийн лог.
 *
 * «Энэ мөр хаанаас гарч ирэв?» гэсэн асуултад хариулна — хэн, хэзээ, ямар
 * замаар (гараар эсвэл файлаас) нэмсэн, өөрчилсөн, устгасныг харуулна.
 */
const logOpen = ref(false);
const logBusy = ref(false);
const logRows = ref([]);

const openLog = async () => {
    logOpen.value = true;
    logBusy.value = true;

    try {
        const { data } = await window.axios.get(route('decrees.logs', { tab: props.tab }));
        logRows.value = data.rows ?? [];
    } catch {
        logRows.value = [];
    } finally {
        logBusy.value = false;
    }
};

const logTone = (action) => ({
    created: 'bg-emerald-50 text-emerald-700',
    imported: 'bg-sky-50 text-sky-700',
    updated: 'bg-amber-50 text-amber-700',
    deleted: 'bg-rose-50 text-rose-700',
}[action] ?? 'bg-slate-100 text-slate-600');

const blankColCount = computed(() => 16 + (props.canManage ? 1 : 0));
const docColumnCount = computed(() => {
    let n = 9; // always include actions (зураг/харах/устгах)
    if (isNiit.value) n += 1;
    return n;
});

</script>

<template>
    <AuthenticatedLayout title="Захирамж, тушаал">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Захирамж, тушаал</h2>
                    <p class="ui-subtitle">
                        Мөрийг нэмээд нүдэн дээр дарж шууд бөглөнө.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-ghost"
                        :disabled="undoCount < 1 || undoing"
                        :title="undoCount ? 'Сүүлийн үйлдлийг буцаах' : 'Буцаах үйлдэл алга'"
                        @click="undo"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M9 14L4 9l5-5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M4 9h10a6 6 0 010 12h-3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Буцаах<span v-if="undoCount"> ({{ undoCount }})</span>
                    </button>
                    <button
                        type="button"
                        class="ui-btn-ghost"
                        title="Хэн, хэзээ, юу өөрчилснийг харах"
                        @click="openLog"
                    >
                        Түүх
                    </button>
                    <a
                        v-if="canPrint"
                        :href="route('decrees.print', { tab })"
                        target="_blank"
                        class="ui-btn-ghost"
                        title="Харагдаж байгаа хүснэгтийг хэвлэх"
                    >
                        Хэвлэх
                    </a>
                    <div v-if="canExport" ref="downloadRoot" class="relative">
                        <button
                            type="button"
                            class="ui-btn-ghost"
                            :aria-expanded="downloadOpen"
                            title="Word, Excel, PDF татах"
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
                    <button
                        v-if="canImport"
                        type="button"
                        class="ui-btn-ghost"
                        :disabled="importBusy"
                        title="Excel эсвэл Word файлаас бүртгэл оруулах"
                        @click="pickImportFile"
                    >
                        {{ importBusy && ! importData ? 'Уншиж байна…' : 'Файлаас оруулах' }}
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-ghost"
                        :class="editMode ? '!border-brand-navy-500 !bg-brand-navy-50 !text-brand-navy-800' : ''"
                        :title="editMode
                            ? 'Засварыг дуусгаад хүснэгтийг түгжинэ'
                            : 'Нүд засварлах, PDF оруулах / устгах боломжтой болгоно'"
                        @click="toggleEditMode"
                    >
                        {{ editMode ? 'Засварыг дуусгах' : 'Засварлах' }}
                    </button>
                    <button
                        v-if="canAddRow"
                        type="button"
                        class="ui-btn-accent"
                        :disabled="addingRow"
                        @click="addRow"
                    >
                        {{ addingRow ? 'Нэмж байна…' : 'Мөр нэмэх' }}
                    </button>
                </div>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="tab === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchTab(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <!-- Бланкны дугаар -->
            <TableScrollViewport
                v-if="isBlank"
                max-height="min(72vh, calc(100dvh - 11rem))"
                @near-bottom="growRenderLimit"
            >
                <div ref="blankSheetEl" class="decree-sheet">
                    <table class="decree-sheet__table min-w-[1180px]">
                    <colgroup>
                        <col style="width: 2.75rem" />
                        <col style="width: 10.5rem" />
                        <col style="width: 7rem" />
                        <col v-for="n in 8" :key="`q-${n}`" style="width: 3.5rem" />
                        <col v-for="n in 4" :key="`n-${n}`" style="width: 5rem" />
                        <col style="width: 7.5rem" />
                        <col v-if="canManage" style="width: 3.5rem" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2">Д/д</th>
                            <th rowspan="2">
                                Хэвлэмэл хуудас авсан<br>ажилтны нэр
                            </th>
                            <th rowspan="2">Огноо</th>
                            <th colspan="8" class="decree-sheet__head-group--issued">
                                Олгосон хэвлэмэл хуудас
                            </th>
                            <th colspan="2" class="decree-sheet__head-group--numbers">
                                Хэвлэмэл хуудасны дугаар
                            </th>
                            <th colspan="2" class="decree-sheet__head-group--void">
                                Хүчингүй болгосон хэвлэмэл хуудасны тоо
                            </th>
                            <th rowspan="2">
                                Хүлээн авсан<br>гарын үсэг
                            </th>
                            <th v-if="canManage" rowspan="2" class="w-16" />
                        </tr>
                        <tr>
                            <th>Захирамж</th>
                            <th>Монгол<br>бичиг</th>
                            <th>Тушаал</th>
                            <th>Монгол<br>бичиг</th>
                            <th>Албан<br>даалгавар</th>
                            <th>Монгол<br>бичиг</th>
                            <th>Зөвлөлийн<br>хурал</th>
                            <th>Монгол<br>бичиг</th>
                            <th>Захирамж</th>
                            <th>Тушаал</th>
                            <th>Захирамж</th>
                            <th>Тушаал</th>
                        </tr>
                        <tr class="decree-sheet__filters">
                            <th>
                                <button
                                    v-if="hasFilters"
                                    type="button"
                                    class="w-full text-[10px] font-semibold text-brand-orange-600 hover:underline"
                                    title="Хайлтыг цэвэрлэх"
                                    @click="clearFilters"
                                >
                                    Цэвэрлэх
                                </button>
                                <span v-else class="text-[10px] text-slate-300">Хайх</span>
                            </th>
                            <th><input v-model="filters.blank_person" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.blank_issued_on" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.qty_zahiramj" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_zahiramj_mn" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_tushaal" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_tushaal_mn" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_assignment" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_assignment_mn" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_council" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.qty_council_mn" type="search" placeholder="—" /></th>
                            <th><input v-model="filters.num_zahiramj" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.num_tushaal" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.void_zahiramj" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.void_tushaal" type="search" placeholder="Хайх" /></th>
                            <th></th>
                            <th v-if="canManage"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in renderedRows"
                            :key="row.id"
                        >
                            <td class="decree-sheet__cell--no">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="rowEditable()"
                                    :options="people"
                                    empty-label=""
                                    placeholder="Нэр сонгох…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].issued_on"
                                    type="date"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'issued_on', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'zahiramj')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_zahiramj"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'zahiramj')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_zahiramj', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'zahiramj')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_zahiramj_mn"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'zahiramj')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_zahiramj_mn', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'tushaal')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_tushaal"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'tushaal')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_tushaal', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'tushaal')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_tushaal_mn"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'tushaal')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_tushaal_mn', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'assignment')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_assignment"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'assignment')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_assignment', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'assignment')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_assignment_mn"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'assignment')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_assignment_mn', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'council')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_council"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'council')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_council', v)"
                                />
                            </td>
                            <td :class="qtyCellClass(row.id, 'council')">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].qty_council_mn"
                                    type="number"
                                    align="center"
                                    :editable="groupEditable(row.id, 'council')"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'qty_council_mn', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].num_zahiramj"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    placeholder="авто"
                                    @commit="(v) => saveField(row.id, 'num_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].num_tushaal"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    placeholder="авто"
                                    @commit="(v) => saveField(row.id, 'num_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].void_zahiramj"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].void_tushaal"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].body"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'body', v)"
                                />
                            </td>
                            <td v-if="canManage" class="px-1 py-1 text-center">
                                <button
                                    v-if="editMode"
                                    type="button"
                                    class="ui-icon-btn mx-auto"
                                    title="Устгах"
                                    aria-label="Устгах"
                                    @click="destroyRow(row.id)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6M14 11v6M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 7l1 14h12l1-14" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="blankColCount" class="decree-sheet__empty">
                                Бүртгэл алга. «Мөр нэмэх» дарж нүдэн дээр бөглөнө үү.
                            </td>
                        </tr>
                        <tr v-else-if="!visibleRows.length">
                            <td :colspan="blankColCount" class="decree-sheet__empty">
                                Хайлтад тохирох мөр алга.
                                <button type="button" class="font-semibold text-brand-navy-600 hover:underline" @click="clearFilters">
                                    Хайлтыг цэвэрлэх
                                </button>
                            </td>
                        </tr>
                        <tr v-else-if="hasMoreRows">
                            <td :colspan="blankColCount" class="px-4 py-3 text-center text-xs text-slate-500">
                                {{ renderedRows.length }} / {{ visibleRows.length }} мөр харагдаж байна —
                                <button
                                    type="button"
                                    class="font-semibold text-brand-navy-600 hover:underline"
                                    @click="growRenderLimit"
                                >
                                    цааш үзэх
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>

            <!-- Захирамж / Тушаалын дугаар -->
            <TableScrollViewport
                v-else-if="isDoc"
                max-height="min(72vh, calc(100dvh - 11rem))"
                @near-bottom="growRenderLimit"
            >
                <div ref="sheetEl" class="decree-sheet">
                <div class="decree-sheet__banner">
                    Аймгийн Засаг даргын {{ docLabel }}ийн бүртгэл
                </div>
                <table class="decree-sheet__table min-w-[1400px]">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 6.5rem" />
                        <col style="width: 5rem" />
                        <col />
                        <col style="width: 4.5rem" />
                        <col style="width: 6.5rem" />
                        <col style="width: 10rem" />
                        <col style="width: 4.5rem" />
                        <col style="width: 7rem" />
                        <col style="width: 6rem" />
                        <col style="width: 8rem" />
                        <col v-if="isNiit" style="width: 6.5rem" />
                        <col style="width: 6.5rem" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th rowspan="2" class="w-10">Д/д</th>
                            <th colspan="4" class="decree-sheet__head-group--issued">
                                Захирамжлалын баримт бичгийн үндсэн мэдээлэл
                            </th>
                            <th rowspan="2" class="w-24">
                                Дагаж мөрдөх<br>он, сар, өдөр
                            </th>
                            <th colspan="2" class="decree-sheet__head-group--issued">
                                Хавсралтын мэдээлэл
                            </th>
                            <th rowspan="2" class="w-28">
                                Баримт бичгийн<br>эх хувийн шинж
                            </th>
                            <th rowspan="2" class="w-24">
                                ХХНЖ-ын<br>хэргийн индекс
                            </th>
                            <th rowspan="2" class="w-36">
                                Боловсруулсан<br>албан тушаалтан
                            </th>
                            <th v-if="isNiit" rowspan="2" class="w-24">Төрөл</th>
                            <th rowspan="2" class="w-24">PDF</th>
                        </tr>
                        <tr>
                            <th class="w-24">Батлагдсан<br>огноо</th>
                            <th class="w-20">Бүртгэлийн<br>дугаар</th>
                            <th>{{ titleLabel }}</th>
                            <th class="w-20">Хуудасны<br>тоо</th>
                            <th>Баримт бичгийн нэр</th>
                            <th class="w-20">Хуудасны тоо</th>
                        </tr>
<tr>
                            <th>1</th>
                            <th>2</th>
                            <th>3</th>
                            <th>4</th>
                            <th>5</th>
                            <th>6</th>
                            <th>7</th>
                            <th>8</th>
                            <th>9</th>
                            <th>10</th>
                            <th>—</th>
                            <th v-if="isNiit">—</th>
                            <th>—</th>
                        </tr>
                        <tr class="decree-sheet__filters">
                            <th>
                                <button
                                    v-if="hasFilters"
                                    type="button"
                                    class="w-full text-[10px] font-semibold text-brand-orange-600 hover:underline"
                                    title="Хайлтыг цэвэрлэх"
                                    @click="clearFilters"
                                >
                                    Цэвэрлэх
                                </button>
                                <span v-else class="text-[10px] text-slate-300">Хайх</span>
                            </th>
                            <th><input v-model="filters.issued_on" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.number" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.title" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.page_count" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.effective_on" type="search" placeholder="2026.09" /></th>
                            <th><input v-model="filters.attachment_name" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.attachment_pages" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.original_form" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.file_index" type="search" placeholder="Хайх" /></th>
                            <th><input v-model="filters.person_name" type="search" placeholder="Хайх" /></th>
                            <th v-if="isNiit"><input v-model="filters.kind_label" type="search" placeholder="Хайх" /></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in renderedRows"
                            :key="row.id"
                        >
                            <td class="decree-sheet__cell--no">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].issued_on"
                                    type="date"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'issued_on', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <div class="flex items-center justify-center">
                                    <!-- Угтвар нь төрлөөсөө гардаг тул засварлахгүй.
                                         Нүдний текстийн хэмжээ, өндөртэй адилтгаж,
                                         дугаартайгаа наалдуулж харуулна. -->
                                    <span
                                        v-if="row.number_prefix"
                                        class="shrink-0 pr-px text-sm leading-snug text-slate-800"
                                    >{{ row.number_prefix }}/</span>
                                    <SheetCell
                                        v-if="drafts[row.id]"
                                        v-model="drafts[row.id].number"
                                        :align="row.number_prefix ? 'left' : 'center'"
                                        :class="row.number_prefix
                                            ? 'w-10 shrink-0 [&_.ui-sheet-display]:px-0'
                                            : 'min-w-0 flex-1'"
                                        :editable="rowEditable()"
                                        empty-label=""
                                        placeholder="01"
                                        @commit="(v) => saveField(row.id, 'number', v)"
                                    />
                                </div>
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].title"
                                    multiline
                                    :editable="rowEditable()"
                                    empty-label=""
                                    placeholder="Гарчиг…"
                                    @commit="(v) => saveField(row.id, 'title', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].page_count"
                                    type="number"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'page_count', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].effective_on"
                                    type="date"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'effective_on', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].attachment_name"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'attachment_name', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].attachment_pages"
                                    type="number"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'attachment_pages', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].original_form"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    placeholder="Эх хувь…"
                                    @commit="(v) => saveField(row.id, 'original_form', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].file_index"
                                    align="center"
                                    :editable="rowEditable()"
                                    empty-label=""
                                    placeholder="Индекс…"
                                    @commit="(v) => saveField(row.id, 'file_index', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="rowEditable()"
                                    :options="officialOptions"
                                    align="center"
                                    empty-label=""
                                    placeholder="Бланк авсан…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td v-if="isNiit" class="px-2 py-1.5 text-xs text-slate-600">
                                <select
                                    v-if="canManage && drafts[row.id]"
                                    v-model="drafts[row.id].kind"
                                    class="w-full border-0 bg-transparent py-1 text-center text-[11px] outline-none focus:bg-sky-50"
                                    @change="saveField(row.id, 'kind', drafts[row.id].kind)"
                                >
                                    <option v-for="opt in kindOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <span v-else class="text-[11px]">{{ row.kind_label }}</span>
                            </td>
                            <td class="px-1 py-1">
                                <div class="flex items-center justify-center gap-0.5">
                                    <button
                                        v-if="canManage && editMode"
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-500 transition hover:bg-brand-navy-50 hover:text-brand-navy-700"
                                        :title="compressingId === row.id ? 'Шахаж байна…' : 'PDF оруулах (2MB хүртэл автоматаар шахна)'"
                                        :aria-label="compressingId === row.id ? 'Шахаж байна' : 'PDF оруулах'"
                                        :disabled="compressingId === row.id"
                                        @click="pickImage(row.id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.5-6 3.5 4.5L15 11l5 5M4 19h16a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1z" />
                                            <circle cx="9" cy="8" r="1.5" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded transition"
                                        :class="row.has_image
                                            ? 'text-brand-navy-600 hover:bg-brand-navy-50'
                                            : 'cursor-not-allowed text-slate-300'"
                                        :disabled="! row.has_image"
                                        title="Хавсаргасан PDF харах"
                                        aria-label="Хавсаргасан PDF харах"
                                        @click="openPreview(row)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canManage && editMode && row.has_image"
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-400 transition hover:bg-amber-50 hover:text-amber-700"
                                        title="Хавсаргасан файлыг устгах"
                                        aria-label="Хавсаргасан файлыг устгах"
                                        @click="removeImage(row.id)"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canManage && editMode"
                                        type="button"
                                        class="ui-icon-btn"
                                        title="Мөр устгах"
                                        aria-label="Мөр устгах"
                                        @click="destroyRow(row.id)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M10 11v6M14 11v6M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M5 7l1 14h12l1-14" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
<tr v-if="!rows.length">
                            <td :colspan="docColumnCount" class="decree-sheet__empty">
                                {{ isNiit ? 'Бүртгэл алга.' : `${numberLabel}ын бүртгэл алга. «Мөр нэмэх» дарж бөглөнө үү.` }}
                            </td>
                        </tr>
                        <tr v-else-if="!visibleRows.length">
                            <td :colspan="docColumnCount" class="decree-sheet__empty">
                                Хайлтад тохирох мөр алга.
                                <button type="button" class="font-semibold text-brand-navy-600 hover:underline" @click="clearFilters">
                                    Хайлтыг цэвэрлэх
                                </button>
                            </td>
                        </tr>
                        <tr v-else-if="hasMoreRows">
                            <td :colspan="docColumnCount" class="px-4 py-3 text-center text-xs text-slate-500">
                                {{ renderedRows.length }} / {{ visibleRows.length }} мөр харагдаж байна —
                                <button
                                    type="button"
                                    class="font-semibold text-brand-navy-600 hover:underline"
                                    @click="growRenderLimit"
                                >
                                    цааш үзэх
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </TableScrollViewport>
        </div>

        <input
            ref="imageInput"
            type="file"
            accept="application/pdf,.pdf"
            class="hidden"
            @change="onImagePicked"
        />

        <input
            ref="importInput"
            type="file"
            accept=".xlsx,.xlsm,.docx,.docm,.pdf"
            class="hidden"
            @change="onImportFile"
        />

        <!-- Өөрчлөлтийн лог -->
        <Modal :show="logOpen" max-width="4xl" @close="logOpen = false">
            <div class="p-5">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Өөрчлөлтийн түүх</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ tabs.find((t) => t.value === tab)?.label }} — сүүлийн 200 бичлэг.
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="logOpen = false">✕</button>
                </div>

                <p v-if="logBusy" class="py-8 text-center text-sm text-slate-400">Уншиж байна…</p>
                <p v-else-if="! logRows.length" class="py-8 text-center text-sm text-slate-400">
                    Одоогоор бичлэг алга. Энэ түүх нь шинэчлэлт хийгдсэнээс хойших өөрчлөлтийг харуулна.
                </p>
                <div v-else class="max-h-[65vh] overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-slate-500">
                            <tr>
                                <th class="px-2 py-1.5 font-semibold">Огноо</th>
                                <th class="px-2 py-1.5 font-semibold">Үйлдэл</th>
                                <th class="px-2 py-1.5 font-semibold">Мөр</th>
                                <th class="px-2 py-1.5 font-semibold">Юу өөрчлөгдсөн</th>
                                <th class="px-2 py-1.5 font-semibold">Хэн</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in logRows" :key="item.id" class="border-t border-slate-100 align-top">
                                <td class="whitespace-nowrap px-2 py-1.5 tabular-nums text-slate-500">{{ item.at }}</td>
                                <td class="px-2 py-1.5">
                                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="logTone(item.action)">
                                        {{ item.action_label }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-slate-700">{{ item.label || '—' }}</td>
                                <td class="px-2 py-1.5 text-slate-600">{{ item.summary || '—' }}</td>
                                <td class="whitespace-nowrap px-2 py-1.5 text-slate-700">{{ item.user }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Файлаас оруулах — багана тааруулж, урьдчилан харна -->
        <Modal :show="!! importData" max-width="7xl" @close="closeImport">
            <div v-if="importData" class="p-5">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">Файлаас оруулах</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            <b>{{ importData.file_name }}</b> — {{ importData.total }} мөр олдлоо.
                            Багана бүр зөв тааарсан эсэхийг шалгаад «Оруулах» дарна уу.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"
                        @click="closeImport"
                    >
                        ✕
                    </button>
                </div>

                <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(label, field) in importData.fields" :key="'map-' + field">
                        <label class="ui-label">{{ label }}</label>
                        <select
                            v-model="importData.mapping[field]"
                            class="ui-input !py-1.5 text-sm"
                            @change="remapImport"
                        >
                            <option :value="null">— оруулахгүй —</option>
                            <option
                                v-for="(header, index) in importData.headers"
                                :key="'h-' + field + '-' + index"
                                :value="index"
                            >
                                {{ index + 1 }}. {{ header || '(нэргүй багана)' }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200">
                    <table class="w-full min-w-[900px] text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500">
                            <tr>
                                <th v-for="(label, field) in importData.fields" :key="'ph-' + field" class="px-2 py-1.5 font-semibold">
                                    {{ label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(entry, i) in importSample" :key="'pr-' + i" class="border-t border-slate-100">
                                <td v-for="(label, field) in importData.fields" :key="'pc-' + field + i" class="px-2 py-1.5 text-slate-700">
                                    {{ entry[field] ?? '' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    Эхний {{ importSample.length }} мөрийг үзүүлэв. Бүртгэлд аль хэдийн байгаа
                    дугаартай мөрүүд алгасагдана — байгаа мэдээлэл дарж бичигдэхгүй.
                </p>

                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeImport">Болих</button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="importBusy || ! importData.total"
                        @click="confirmImport"
                    >
                        {{ importBusy ? 'Оруулж байна…' : `Оруулах (${importData.total})` }}
                    </button>
                </div>
            </div>
        </Modal>

        <Modal :show="!! preview" max-width="4xl" @close="closePreview">
            <div class="p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <h3 class="text-sm font-semibold text-brand-navy-900">{{ preview?.title }}</h3>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closePreview">✕</button>
                </div>
                <div class="max-h-[75vh] overflow-auto rounded-lg bg-slate-100">
                    <iframe
                        v-if="preview?.url && preview.is_pdf"
                        :src="preview.url"
                        title="Хавсаргасан PDF"
                        class="h-[75vh] w-full rounded-lg border-0 bg-white"
                    />
                    <img
                        v-else-if="preview?.url"
                        :src="preview.url"
                        alt="Хавсаргасан файл"
                        class="mx-auto max-h-[75vh] w-auto max-w-full object-contain"
                    />
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
