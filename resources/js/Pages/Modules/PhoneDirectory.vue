<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    orgNames: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    departmentUnits: { type: Array, default: () => [] },
    unitTypes: { type: Object, default: () => ({}) },
    canManage: Boolean,
});

const page = usePage();
const search = ref('');
const categoryFilter = ref('all');
const showForm = ref(false);
const showImport = ref(false);
const fileInput = ref(null);

const isDirectory = computed(() => true);

const form = useForm({
    org_name: '',
    category: '',
    person_name: '',
    position: '',
    office_phone: '',
    mobile_phone: '',
    // Хоосон биш бол шинэ хүснэгтийг тухайн бүлгийн ӨМНӨ байрлуулна.
    before_org_name: '',
});

const editingId = ref(null);
const isEditing = computed(() => editingId.value !== null);

const importForm = useForm({
    file: null,
    replace: false,
});

// Ангилал тус бүрийн хүний тоо + ангилалгүй үлдсэн бүлгүүд.
const categoryTabs = computed(() => {
    const counts = {};
    let none = 0;

    props.groups.forEach((g) => {
        const key = g.category || '';

        if (! key) {
            none += g.rows.length;

            return;
        }

        counts[key] = (counts[key] ?? 0) + g.rows.length;
    });

    const tabs = [
        { value: 'all', label: 'Бүгд', count: props.total },
        ...Object.entries(props.categories).map(([value, label]) => ({
            value,
            label,
            count: counts[value] ?? 0,
        })),
    ];

    if (none) {
        tabs.push({ value: 'none', label: 'Ангилалгүй', count: none });
    }

    return tabs;
});

// Албан хаагчдын нэгжүүд (хэлтэс) — АЗДТГ хэсгийн сонголт.
const filteredGroups = computed(() => {
    const q = search.value.trim().toLowerCase();
    const scoped = categoryFilter.value === 'all'
        ? props.groups
        : props.groups.filter((g) => (categoryFilter.value === 'none'
            ? ! g.category
            : g.category === categoryFilter.value));

    if (!q) return scoped;

    return scoped
        .map((g) => ({
            ...g,
            rows: g.rows.filter((r) =>
                [g.org_name, r.person_name, r.position, r.office_phone, r.mobile_phone]
                    .filter(Boolean)
                    .some((v) => String(v).toLowerCase().includes(q)),
            ),
        }))
        .filter((g) => g.rows.length);
});

// Хайлт/шүүлт идэвхтэй үед байрлал нь бодит дараалалтай таарахгүй тул нуна.
const canInsert = computed(() => props.canManage
    && ! search.value.trim()
    && categoryFilter.value === 'all');

const submit = () => {
    if (isEditing.value) {
        form.patch(route('phone-directory.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                editingId.value = null;
                showForm.value = false;
            },
        });
        return;
    }

    form.post(route('phone-directory.store'), {
        preserveScroll: true,
        onSuccess: () => {
            const org = form.org_name;
            const cat = form.category;
            form.reset();
            form.org_name = org;
            form.category = cat;
            showForm.value = false;
        },
    });
};

const submitImport = () => {
    importForm.post(route('phone-directory.import'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.reset();
            if (fileInput.value) fileInput.value.value = '';
            showImport.value = false;
        },
    });
};

const changeCategory = (orgName, category) => {
    router.patch(
        route('phone-directory.category'),
        { org_name: orgName, category },
        { preserveScroll: true },
    );
};

// --- Бүлгийг (хэлтэс/байгууллага) зөөх ---
const draggingOrg = ref(null);
const dropTargetOrg = ref(null);

const moveGroup = (group, direction) => {
    router.patch(
        route('phone-directory.reorder'),
        { org_name: group.org_name, direction },
        { preserveScroll: true },
    );
};

const startDrag = (group, event) => {
    draggingRow.value = null;
    draggingOrg.value = group.org_name;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', group.org_name);
};

const dragOverGroup = (group) => {
    if (draggingRow.value) {
        dropTargetRow.value = 'top:' + group.org_name;

        return;
    }

    if (draggingOrg.value && draggingOrg.value !== group.org_name) {
        dropTargetOrg.value = group.org_name;
    }
};

const endDrag = () => {
    draggingOrg.value = null;
    dropTargetOrg.value = null;
    draggingRow.value = null;
    dropTargetRow.value = null;
};

// Бүлгийн толгой дээр буулгах: мөр бол тухайн бүлгийн эхэнд, бүлэг бол өмнө нь.
const dropOnGroup = (group) => {
    const row = draggingRow.value;
    const org = draggingOrg.value;
    endDrag();

    if (row) {
        moveRow(row.id, group.org_name, group.rows[0]?.id ?? null);

        return;
    }

    if (! org || org === group.org_name) return;

    router.patch(
        route('phone-directory.reorder'),
        { org_name: org, before_org_name: group.org_name },
        { preserveScroll: true },
    );
};

// --- Албан хаагчийн мөрийг зөөх (бүлэг дотор ба бүлэг хооронд) ---
const draggingRow = ref(null);
const dropTargetRow = ref(null);

const moveRow = (id, orgName, beforeId) => {
    router.patch(
        route('phone-directory.reorder-row'),
        { id, org_name: orgName, before_id: beforeId },
        { preserveScroll: true },
    );
};

const startRowDrag = (row, group, event) => {
    draggingOrg.value = null;
    draggingRow.value = { id: row.id, org_name: group.org_name };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(row.id));
    event.stopPropagation();
};

const dragOverRow = (row) => {
    if (draggingRow.value && draggingRow.value.id !== row.id) {
        dropTargetRow.value = 'row:' + row.id;
    }
};

// Чирсэн мөрийг энэ мөрийн өмнө тавина.
const dropOnRow = (row, group) => {
    const dragged = draggingRow.value;
    endDrag();

    if (! dragged || dragged.id === row.id) return;

    moveRow(dragged.id, group.org_name, row.id);
};

// Бүлгийн хамгийн ард тавина.
const dropAtGroupEnd = (group) => {
    const dragged = draggingRow.value;
    endDrag();

    if (! dragged) return;

    moveRow(dragged.id, group.org_name, null);
};

// Жагсаалтын хамгийн ард буулгах.
const dropAtEnd = () => {
    const org = draggingOrg.value;
    endDrag();

    if (! org) return;

    router.patch(
        route('phone-directory.reorder'),
        { org_name: org, before_org_name: '' },
        { preserveScroll: true },
    );
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('phone-directory.destroy', id), { preserveScroll: true });
};

const flash = computed(() => page.props.flash?.success ?? null);

const resetDirectoryForm = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
};

const openAdd = () => {
    resetDirectoryForm();
    showForm.value = true;
    showImport.value = false;
};

// Тухайн хэлтэс/байгууллагын доор шууд шинэ мөр нэмнэ.
const openAddRow = (group) => {
    resetDirectoryForm();
    form.org_name = group.org_name;
    form.category = group.category || '';
    showForm.value = true;
    showImport.value = false;
};

// Хоёр бүлгийн хооронд шинэ хүснэгт (байгууллага/хэлтэс) оруулна.
const openInsertGroup = (beforeGroup) => {
    resetDirectoryForm();
    form.before_org_name = beforeGroup.org_name;
    showForm.value = true;
    showImport.value = false;
};

const openEdit = (row, group) => {
    resetDirectoryForm();
    editingId.value = row.id;
    form.org_name = group.org_name;
    form.category = group.category || '';
    form.person_name = row.person_name || '';
    form.position = row.position || '';
    form.office_phone = row.office_phone || '';
    form.mobile_phone = row.mobile_phone || '';
    showForm.value = true;
    showImport.value = false;
};

const closeDirectoryForm = () => {
    showForm.value = false;
    resetDirectoryForm();
};
</script>

<template>
    <AuthenticatedLayout title="Утасны жагсаалт">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Утасны жагсаалт</h2>
                    <p class="ui-subtitle">
                        Байгууллага, албан хаагчдын ажлын өрөө болон гар утасны нэгдсэн жагсаалт.
                        Нийт {{ total }} бүртгэл.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        v-if="isDirectory && total"
                        :href="route('phone-directory.export')"
                        class="ui-btn-ghost"
                    >
                        Word татах
                    </a>
                    <button
                        v-if="canManage && isDirectory"
                        type="button"
                        class="ui-btn-primary"
                        @click="showImport = !showImport; showForm = false"
                    >
                        {{ showImport ? 'Хаах' : 'Word импорт' }}
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="openAdd"
                    >
                        Шинэ нэмэх
                    </button>
                </div>
            </div>

            <div v-if="flash" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                {{ flash }}
            </div>

            <!-- Directory: import -->
            <form v-if="isDirectory && showImport && canManage" class="ui-card grid gap-4 p-5" @submit.prevent="submitImport">
                <div>
                    <label class="ui-label">Word файл (.docx)</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".docx"
                        class="ui-input"
                        @change="importForm.file = $event.target.files[0]"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Хүснэгтийн толгой: № / Овог нэр / Албан тушаал / Ажлын өрөөний утас / Гар утас.
                    </p>
                    <p v-if="importForm.errors.file" class="mt-1 text-sm text-rose-600">{{ importForm.errors.file }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input
                        v-model="importForm.replace"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-navy-600 focus:ring-brand-navy-600"
                    />
                    Одоо байгаа жагсаалтыг устгаад шинээр оруулах
                </label>
                <div>
                    <button type="submit" class="ui-btn-primary" :disabled="importForm.processing || !importForm.file">
                        {{ importForm.processing ? 'Уншиж байна…' : 'Импортлох' }}
                    </button>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="search"
                    class="ui-input md:max-w-sm"
                    :placeholder="isDirectory
                        ? 'Нэр, албан тушаал, утсаар хайх…'
                        : 'Нэгж, нэр, утас, и-мэйлээр хайх…'"
                />

                <!-- Ангиллаар шүүх (утасны жагсаалт) -->
                <div v-if="isDirectory" class="flex flex-wrap gap-2">
                    <button
                        v-for="tab in categoryTabs"
                        :key="tab.value"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                        :class="
                            tab.value === categoryFilter
                                ? 'border-brand-navy-600 bg-brand-navy-600 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-brand-navy-300 hover:text-brand-navy-700'
                        "
                        @click="categoryFilter = tab.value"
                    >
                        {{ tab.label }}
                        <span class="ml-1 text-xs opacity-70">{{ tab.count }}</span>
                    </button>
                </div>

            </div>

            <!-- Tab 1: directory -->
            <div v-if="isDirectory" class="ui-table-wrap overflow-x-auto">
                <table class="ui-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th class="w-14 text-center">№</th>
                            <th>Овог нэр</th>
                            <th>Албан тушаал</th>
                            <th class="text-center">Ажлын өрөөний утас</th>
                            <th class="text-center">Гар утас</th>
                            <th v-if="canManage" />
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(group, gi) in filteredGroups" :key="group.org_name">
                            <tr
                                v-if="canInsert"
                                class="group/ins"
                                @dragover.prevent="dragOverGroup(group)"
                                @drop.prevent="dropOnGroup(group)"
                            >
                                <td :colspan="canManage ? 6 : 5" class="!py-0.5 text-center">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg border border-dashed py-1 text-xs font-medium transition focus:opacity-100"
                                        :class="dropTargetOrg === group.org_name
                                            ? 'border-brand-navy-400 bg-brand-navy-50 text-brand-navy-700 opacity-100'
                                            : 'border-slate-200 text-slate-400 opacity-0 hover:border-brand-navy-300 hover:text-brand-navy-700 group-hover/ins:opacity-100'"
                                        @click="openInsertGroup(group)"
                                    >
                                        {{ dropTargetOrg === group.org_name ? '⇩ Энд байрлуулах' : '＋ Энд шинэ хүснэгт нэмэх' }}
                                    </button>
                                </td>
                            </tr>
                            <tr
                                class="bg-brand-navy-50"
                                :class="draggingOrg === group.org_name ? 'opacity-40' : ''"
                                :draggable="canInsert"
                                @dragstart="startDrag(group, $event)"
                                @dragend="endDrag"
                                @dragover.prevent="dragOverGroup(group)"
                                @drop.prevent="dropOnGroup(group)"
                            >
                                <td :colspan="canManage ? 6 : 5" class="text-center font-semibold italic text-brand-navy-800">
                                    <span
                                        v-if="canInsert"
                                        class="mr-1 cursor-grab select-none text-slate-400 active:cursor-grabbing"
                                        title="Чирж байрлуулна"
                                    >⠿</span>
                                    {{ group.org_name }}
                                    <span v-if="canInsert" class="ml-2 inline-flex align-middle">
                                        <button
                                            type="button"
                                            class="rounded-l-lg border border-slate-300 bg-white px-1.5 py-0.5 text-xs not-italic text-slate-600 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="gi === 0"
                                            title="Дээш зөөх"
                                            @click="moveGroup(group, 'up')"
                                        >↑</button>
                                        <button
                                            type="button"
                                            class="-ml-px rounded-r-lg border border-slate-300 bg-white px-1.5 py-0.5 text-xs not-italic text-slate-600 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="gi === filteredGroups.length - 1"
                                            title="Доош зөөх"
                                            @click="moveGroup(group, 'down')"
                                        >↓</button>
                                    </span>
                                    <select
                                        v-if="canManage"
                                        :value="group.category || ''"
                                        class="ml-2 rounded-lg border-slate-300 bg-white py-0.5 pl-2 pr-7 text-xs font-medium not-italic text-slate-600"
                                        title="Чөлөөний бүртгэлд ямар хамрах хүрээнд харагдахыг тодорхойлно"
                                        @change="changeCategory(group.org_name, $event.target.value)"
                                    >
                                        <option value="">Сонголтгүй</option>
                                        <option v-for="(label, value) in categories" :key="value" :value="value">
                                            {{ label }}
                                        </option>
                                    </select>
                                    <span
                                        v-else
                                        class="ml-2 rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium not-italic text-slate-500"
                                    >
                                        {{ categories[group.category] || 'Сонголтгүй' }}
                                    </span>
                                    <button
                                        v-if="canManage"
                                        type="button"
                                        class="ml-2 rounded-lg border border-brand-navy-200 bg-white px-2 py-0.5 text-xs font-medium not-italic text-brand-navy-700 hover:bg-brand-navy-100"
                                        title="Энэ хэлтэст шинэ албан хаагч нэмэх"
                                        @click="openAddRow(group)"
                                    >
                                        ＋ мөр нэмэх
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-for="(row, index) in group.rows"
                                :key="row.id"
                                :draggable="canInsert"
                                :class="[
                                    draggingRow && draggingRow.id === row.id ? 'opacity-40' : '',
                                    dropTargetRow === 'row:' + row.id ? '!border-t-2 !border-brand-navy-500' : '',
                                ]"
                                @dragstart="startRowDrag(row, group, $event)"
                                @dragend="endDrag"
                                @dragover.prevent="dragOverRow(row)"
                                @drop.prevent="dropOnRow(row, group)"
                            >
                                <td class="text-center">
                                    <span v-if="canInsert" class="mr-1 cursor-grab select-none text-slate-300 active:cursor-grabbing">⠿</span>
                                    {{ index + 1 }}
                                </td>
                                <td>
                                    <span class="ui-clamp-2" :title="row.person_name">{{ row.person_name }}</span>
                                </td>
                                <td>
                                    <span class="ui-clamp-2" :title="row.position || ''">{{ row.position || '—' }}</span>
                                </td>
                                <td class="text-center">{{ row.office_phone || '—' }}</td>
                                <td class="text-center">{{ row.mobile_phone || '—' }}</td>
                                <td v-if="canManage" class="text-right whitespace-nowrap">
                                    <button type="button" class="ui-btn-ghost mr-1 !py-1 text-xs" @click="openEdit(row, group)">Засах</button>
                                    <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyRow(row.id)">Устгах</button>
                                </td>
                            </tr>
                            <tr
                                v-if="canInsert && draggingRow"
                                @dragover.prevent="dropTargetRow = 'end:' + group.org_name"
                                @drop.prevent="dropAtGroupEnd(group)"
                            >
                                <td :colspan="canManage ? 6 : 5" class="!py-1 text-center">
                                    <span
                                        class="block rounded-lg border border-dashed py-1 text-xs font-medium"
                                        :class="dropTargetRow === 'end:' + group.org_name
                                            ? 'border-brand-navy-400 bg-brand-navy-50 text-brand-navy-700'
                                            : 'border-slate-200 text-slate-400'"
                                    >
                                        ⇩ «{{ group.org_name }}»-ийн ард
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr
                            v-if="canInsert && filteredGroups.length"
                            class="group/ins"
                            @dragover.prevent="draggingOrg && (dropTargetOrg = '__end__')"
                            @drop.prevent="dropAtEnd"
                        >
                            <td :colspan="canManage ? 6 : 5" class="!py-0.5 text-center">
                                <button
                                    type="button"
                                    class="w-full rounded-lg border border-dashed py-1 text-xs font-medium transition focus:opacity-100"
                                    :class="dropTargetOrg === '__end__'
                                        ? 'border-brand-navy-400 bg-brand-navy-50 text-brand-navy-700 opacity-100'
                                        : 'border-slate-200 text-slate-400 opacity-0 hover:border-brand-navy-300 hover:text-brand-navy-700 group-hover/ins:opacity-100'"
                                    @click="openAdd"
                                >
                                    {{ dropTargetOrg === '__end__' ? '⇩ Хамгийн ард байрлуулах' : '＋ Шинэ хүснэгт нэмэх' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredGroups.length">
                            <td :colspan="canManage ? 6 : 5" class="!py-12 text-center text-slate-400">
                                {{ search ? 'Хайлтад тохирох бүртгэл алга.' : 'Одоогоор бүртгэл алга. Word файлаас импортлож болно.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: directory add / edit -->
        <Modal :show="showForm && canManage && isDirectory" max-width="2xl" @close="closeDirectoryForm">
            <form class="p-6" @submit.prevent="submit">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-brand-navy-900">
                            {{ isEditing ? 'Бүртгэл засах' : (form.before_org_name ? 'Шинэ хүснэгт' : 'Шинэ бүртгэл') }}
                        </h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ form.before_org_name
                                ? `«${form.before_org_name}»-ийн өмнө нэмэгдэнэ.`
                                : 'Утасны жагсаалт' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" @click="closeDirectoryForm">✕</button>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="ui-label">Байгууллага / хэлтэс</label>
                        <input v-model="form.org_name" list="phone-org-names" class="ui-input" required />
                        <datalist id="phone-org-names">
                            <option v-for="name in orgNames" :key="name" :value="name" />
                        </datalist>
                        <InputError :message="form.errors.org_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Ангилал</label>
                        <select v-model="form.category" class="ui-input">
                            <option value="">Сонголтгүй</option>
                            <option v-for="(label, value) in categories" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.category" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Овог нэр</label>
                        <input v-model="form.person_name" class="ui-input" required />
                        <InputError :message="form.errors.person_name" class="mt-1" />
                    </div>
                    <div>
                        <label class="ui-label">Албан тушаал</label>
                        <input v-model="form.position" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Ажлын өрөөний утас</label>
                        <input v-model="form.office_phone" class="ui-input" />
                    </div>
                    <div>
                        <label class="ui-label">Гар утас</label>
                        <input v-model="form.mobile_phone" class="ui-input" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" class="ui-btn-ghost" @click="closeDirectoryForm">Болих</button>
                    <button type="submit" class="ui-btn-primary" :disabled="form.processing">Хадгалах</button>
                </div>
            </form>
        </Modal>

    </AuthenticatedLayout>
</template>
