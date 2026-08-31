<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SheetCell from '@/Components/SheetCell.vue';
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
const isDoc = computed(() => ! isBlank.value);

const docLabel = computed(() => {
    if (isNiit.value) return 'Захирамж, тушаал';
    return isZahiramj.value ? 'Захирамж' : 'Тушаал';
});
const titleLabel = computed(() => {
    if (isNiit.value) return 'Гарчиг / тэргүү';
    return isZahiramj.value ? 'Захирамжийн тэргүү' : 'Тушаалын гарчиг';
});
const numberLabel = computed(() => {
    if (isNiit.value) return 'Бүртгэл';
    return isZahiramj.value ? 'Захирамжийн дугаар' : 'Тушаалын дугаар';
});

const kindOptions = [
    { value: 'zahiramj_a', label: 'Захирамж А' },
    { value: 'zahiramj_b', label: 'Захирамж Б' },
    { value: 'tushaal_a', label: 'Тушаал А' },
    { value: 'tushaal_b', label: 'Тушаал Б' },
];

const canAddRow = computed(() => (props.canEdit || props.canManage) && ! isNiit.value);

const canEditRows = computed(() => props.canEdit || props.canManage);
const canManageRows = computed(() => props.canManage || props.canEdit);

const officialOptions = computed(() => {
    const pending = props.pendingOfficials ?? [];
    const seen = new Set(pending.map((p) => p.value));
    const extras = props.rows
        .map((row) => row.person_name)
        .filter((name) => name && ! seen.has(name))
        .map((name) => {
            seen.add(name);

            return {
                value: name,
                label: name,
                hint: '',
                org: '',
                category: 'baiguullaga',
            };
        });

    return [...pending, ...extras];
});

const canManage = computed(() => canManageRows.value);

const cellClass = 'border border-slate-800 p-0 align-middle overflow-hidden';

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
    groupEditable(rowId, group) ? '' : 'bg-slate-100',
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
    'kind', 'number', 'issued_on', 'title', 'page_count',
    'attachment_name', 'attachment_pages', 'person_name', 'body',
];

const syncDrafts = () => {
    Object.keys(drafts).forEach((key) => delete drafts[key]);
    props.rows.forEach((row) => {
        if (isBlank.value) {
            drafts[row.id] = Object.fromEntries(blankFields.map((f) => [f, row[f] ?? '']));
        } else {
            drafts[row.id] = Object.fromEntries(docFields.map((f) => [f, row[f] ?? '']));
        }
    });
};

watch(() => [props.rows, props.tab], syncDrafts, { immediate: true, deep: true });

const switchTab = (value) => {
    router.get(route('decrees.index'), { tab: value }, { preserveState: false, preserveScroll: true });
};

const today = () => {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
};

const addRow = () => {
    if (isNiit.value || ! canAddRow.value) return;

    const payload = isBlank.value
        ? { tab: 'blank', person_name: '', issued_on: today() }
        : { tab: props.tab, title: '', issued_on: today() };

    router.post(route('decrees.store'), payload, { preserveScroll: true });
};

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

const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

const loadImage = (file) => new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
        URL.revokeObjectURL(url);
        resolve(img);
    };
    img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Зураг уншигдсангүй.'));
    };
    img.src = url;
});

const canvasToBlob = (canvas, quality) => new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
});

/** 2MB-аас их бол JPEG-р шахаж 2MB дотор оруулна. */
const compressImageToLimit = async (file) => {
    if (file.size <= MAX_IMAGE_BYTES) {
        return file;
    }

    const img = await loadImage(file);
    let width = img.width;
    let height = img.height;
    const maxSide = 2400;

    if (width > maxSide || height > maxSide) {
        const scale = Math.min(maxSide / width, maxSide / height);
        width = Math.round(width * scale);
        height = Math.round(height * scale);
    }

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    ctx.drawImage(img, 0, 0, width, height);

    let quality = 0.85;
    let blob = await canvasToBlob(canvas, quality);

    while (blob && blob.size > MAX_IMAGE_BYTES && quality > 0.35) {
        quality -= 0.1;
        blob = await canvasToBlob(canvas, quality);
    }

    while (blob && blob.size > MAX_IMAGE_BYTES && (canvas.width > 800 || canvas.height > 800)) {
        canvas.width = Math.round(canvas.width * 0.8);
        canvas.height = Math.round(canvas.height * 0.8);
        const c = canvas.getContext('2d');
        c.fillStyle = '#ffffff';
        c.fillRect(0, 0, canvas.width, canvas.height);
        c.drawImage(img, 0, 0, canvas.width, canvas.height);
        blob = await canvasToBlob(canvas, Math.max(quality, 0.5));
    }

    if (! blob || blob.size > MAX_IMAGE_BYTES) {
        throw new Error('Зургийг 2MB хүртэл шахаж чадсангүй. Өөр зураг сонгоно уу.');
    }

    return new File([blob], (file.name.replace(/\.[^.]+$/, '') || 'decree') + '.jpg', {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
};

const pickImage = (id) => {
    uploadingId.value = id;
    imageInput.value?.click();
};

const onImagePicked = async (event) => {
    const file = event.target.files?.[0];
    const id = uploadingId.value;
    event.target.value = '';

    if (! file || ! id) return;

    if (! file.type.startsWith('image/')) {
        alert('Зөвхөн зураг файл оруулна уу.');
        return;
    }

    try {
        const ready = await compressImageToLimit(file);
        useForm({ image: ready }).post(route('decrees.image.upload', id), {
            forceFormData: true,
            preserveScroll: true,
        });
    } catch (err) {
        alert(err?.message || 'Зураг оруулахад алдаа гарлаа.');
    } finally {
        uploadingId.value = null;
    }
};

const openPreview = (row) => {
    if (! row.image_url) return;
    preview.value = {
        url: row.image_url,
        title: [row.number, row.title].filter(Boolean).join(' — ') || 'Захирамжийн зураг',
    };
};

const closePreview = () => {
    preview.value = null;
};

const removeImage = (id) => {
    if (!confirm('Зургийг устгах уу?')) return;
    router.delete(route('decrees.image.destroy', id), { preserveScroll: true });
};

const blankColCount = computed(() => 15 + (props.canManage ? 1 : 0));
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
                    <a
                        :href="route('decrees.print', { tab })"
                        target="_blank"
                        class="ui-btn-ghost"
                        title="Харагдаж байгаа хүснэгтийг хэвлэх"
                    >
                        Хэвлэх
                    </a>
                    <div ref="downloadRoot" class="relative">
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
                        v-if="canAddRow"
                        type="button"
                        class="ui-btn-accent"
                        @click="addRow"
                    >
                        Шинэ мөр
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
            <div v-if="isBlank" class="decree-sheet overflow-x-auto border border-slate-800 bg-white">
                <table class="w-full min-w-[1100px] border-collapse text-center text-[11px] leading-tight text-slate-900">
                    <colgroup>
                        <col style="width: 2.25rem" />
                        <col style="width: 9.5rem" />
                        <col style="width: 6.5rem" />
                        <col v-for="n in 8" :key="`q-${n}`" style="width: 3.25rem" />
                        <col v-for="n in 4" :key="`n-${n}`" style="width: 4.5rem" />
                        <col style="width: 7rem" />
                        <col v-if="canManage" style="width: 3.5rem" />
                    </colgroup>
                    <thead>
                        <tr class="bg-slate-50">
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">Д/д</th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хэвлэмэл хуудас авсан<br>ажилтны нэр
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">Огноо</th>
                            <th colspan="8" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Олгосон хэвлэмэл хуудас
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хэвлэмэл хуудасны дугаар
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Үрэгдүүлсэн хуудасны дугаар
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold">
                                Хүлээн авсан<br>гарын үсэг
                            </th>
                            <th v-if="canManage" rowspan="2" class="border border-slate-800 px-1 py-1.5 font-semibold w-16" />
                        </tr>
                        <tr class="bg-slate-50">
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Албан<br>даалгавар</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Зөвлөлийн<br>хурал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Монгол<br>бичиг</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Захирамж</th>
                            <th class="border border-slate-800 px-1 py-1 font-medium">Тушаал</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50 focus-within:bg-sky-50"
                        >
                            <td class="border border-slate-800 px-1 py-1">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="canManage"
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
                                    :editable="canManage"
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
                                    :editable="canManage"
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
                                    :editable="canManage"
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
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_zahiramj', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].void_tushaal"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'void_tushaal', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].body"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'body', v)"
                                />
                            </td>
                            <td v-if="canManage" class="border border-slate-800 px-1 py-1">
                                <button
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
                            <td :colspan="blankColCount" class="border border-slate-800 px-2 py-10 text-slate-400">
                                Бүртгэл алга. «Шинэ мөр» дарж нүдэн дээр бөглөнө үү.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Захирамж / Тушаалын дугаар -->
            <div v-else-if="isDoc" class="decree-sheet overflow-x-auto border border-slate-800 bg-white">
                <div class="border-b border-slate-800 px-3 py-2 text-center text-sm font-semibold tracking-wide">
                    Аймгийн Засаг даргын {{ docLabel }}ийн бүртгэл
                </div>
                <table class="w-full min-w-[1000px] border-collapse text-center text-[12px] leading-tight text-slate-900">
                    <colgroup>
                        <col style="width: 2.5rem" />
                        <col style="width: 5rem" />
                        <col style="width: 6.5rem" />
                        <col />
                        <col style="width: 4.5rem" />
                        <col style="width: 10rem" />
                        <col style="width: 4.5rem" />
                        <col style="width: 8rem" />
                        <col v-if="isNiit" style="width: 6.5rem" />
                        <col style="width: 6.5rem" />
                    </colgroup>
                    <thead>
                        <tr class="bg-slate-50">
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-10">№</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-20">Дугаар</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-24">Огноо</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold">{{ titleLabel }}</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-20">
                                Хуудасны<br>тоо
                            </th>
                            <th colspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold">
                                Хавсралтын мэдээлэл
                            </th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-36">
                                Боловсруулсан<br>албан тушаалтан
                            </th>
                            <th v-if="isNiit" rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-24">Төрөл</th>
                            <th rowspan="2" class="border border-slate-800 px-1.5 py-2 font-semibold w-24">Зураг</th>
                        </tr>
                        <tr class="bg-slate-50">
                            <th class="border border-slate-800 px-1.5 py-1.5 font-medium">Баримт бичгийн нэр</th>
                            <th class="border border-slate-800 px-1.5 py-1.5 font-medium w-20">Хуудасны тоо</th>
                        </tr>
                        <tr class="bg-slate-100 text-[10px] text-slate-500">
                            <th class="border border-slate-800 py-0.5">1</th>
                            <th class="border border-slate-800 py-0.5">2</th>
                            <th class="border border-slate-800 py-0.5">3</th>
                            <th class="border border-slate-800 py-0.5">4</th>
                            <th class="border border-slate-800 py-0.5">5</th>
                            <th class="border border-slate-800 py-0.5">6</th>
                            <th class="border border-slate-800 py-0.5">7</th>
                            <th class="border border-slate-800 py-0.5">8</th>
                            <th v-if="isNiit" class="border border-slate-800 py-0.5">9</th>
                            <th class="border border-slate-800 py-0.5">{{ isNiit ? 10 : 9 }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="hover:bg-sky-50 focus-within:bg-sky-50"
                        >
                            <td class="border border-slate-800 px-1.5 py-1.5">{{ row.no }}</td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].number"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    placeholder="Дугаар…"
                                    @commit="(v) => saveField(row.id, 'number', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].issued_on"
                                    type="date"
                                    align="center"
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'issued_on', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].title"
                                    multiline
                                    :editable="canManage"
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
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'page_count', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].attachment_name"
                                    :editable="canManage"
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
                                    :editable="canManage"
                                    empty-label=""
                                    @commit="(v) => saveField(row.id, 'attachment_pages', v)"
                                />
                            </td>
                            <td :class="cellClass">
                                <SheetCell
                                    v-if="drafts[row.id]"
                                    v-model="drafts[row.id].person_name"
                                    :editable="canManage"
                                    :options="officialOptions"
                                    align="center"
                                    empty-label=""
                                    placeholder="Бланк авсан…"
                                    @commit="(v) => saveField(row.id, 'person_name', v)"
                                />
                            </td>
                            <td v-if="isNiit" class="border border-slate-800 px-1 py-1">
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
                            <td class="border border-slate-800 px-1 py-1">
                                <div class="flex items-center justify-center gap-0.5">
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-500 transition hover:bg-brand-navy-50 hover:text-brand-navy-700"
                                        title="Зураг оруулах"
                                        aria-label="Зураг оруулах"
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
                                        title="Зураг харах"
                                        aria-label="Зураг харах"
                                        @click="openPreview(row)"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canManage && row.has_image"
                                        type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-400 transition hover:bg-amber-50 hover:text-amber-700"
                                        title="Зураг устгах"
                                        aria-label="Зураг устгах"
                                        @click="removeImage(row.id)"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canManage"
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
                            <td :colspan="docColumnCount" class="border border-slate-800 px-2 py-10 text-slate-400">
                                {{ isNiit ? 'Бүртгэл алга.' : `${numberLabel}ын бүртгэл алга. «Шинэ мөр» дарж бөглөнө үү.` }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <input
            ref="imageInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/jpg"
            class="hidden"
            @change="onImagePicked"
        />

        <Modal :show="!! preview" max-width="4xl" @close="closePreview">
            <div class="p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <h3 class="text-sm font-semibold text-brand-navy-900">{{ preview?.title }}</h3>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closePreview">✕</button>
                </div>
                <div class="max-h-[75vh] overflow-auto rounded-lg bg-slate-100">
                    <img
                        v-if="preview?.url"
                        :src="preview.url"
                        alt="Захирамжийн зураг"
                        class="mx-auto max-h-[75vh] w-auto max-w-full object-contain"
                    />
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
