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
/** Жагсаалтын бүх засварлах товч/чирэх горимыг идэвхжүүлнэ. */
const manageMode = ref(false);

const isDirectory = computed(() => true);
const editingActive = computed(() => props.canManage && manageMode.value);

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

// Ангилал тус бүрийн нэгжийн тоо + албан хаагчийн тоо.
const categoryTabs = computed(() => {
    const people = {};
    const orgs = {};
    let nonePeople = 0;
    let noneOrgs = 0;

    props.groups.forEach((g) => {
        const key = g.category || '';
        const n = g.rows?.length ?? 0;

        if (! key) {
            nonePeople += n;
            noneOrgs += 1;

            return;
        }

        people[key] = (people[key] ?? 0) + n;
        orgs[key] = (orgs[key] ?? 0) + 1;
    });

    const tabs = [
        {
            value: 'all',
            label: 'Бүгд',
            orgCount: props.groups.length,
            peopleCount: props.total,
        },
        ...Object.entries(props.categories).map(([value, label]) => ({
            value,
            label,
            orgCount: orgs[value] ?? 0,
            peopleCount: people[value] ?? 0,
        })),
    ];

    if (noneOrgs) {
        tabs.push({
            value: 'none',
            label: 'Ангилалгүй',
            orgCount: noneOrgs,
            peopleCount: nonePeople,
        });
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
const canInsert = computed(() => editingActive.value
    && ! search.value.trim()
    && categoryFilter.value === 'all');

const toggleManageMode = () => {
    manageMode.value = ! manageMode.value;
    if (! manageMode.value) {
        showForm.value = false;
        editingId.value = null;
        form.reset();
    }
};

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

const changeCategory = (orgName, category) => {
    router.patch(
        route('phone-directory.category'),
        { org_name: orgName, category },
        { preserveScroll: true },
    );
};

// --- Зөөх (хэлтэс/байгууллага бүлэг ба албан хаагчийн мөр) ---
//
// HTML5 drag&drop-ыг ашиглахгүй: ui-table нь border-collapse тул Chrome/WebKit
// хүснэгтийн мөрийг чирүүлдэггүй. Оронд нь pointer event-ээр өөрсдөө хөтөлнө.
//
// Буулгах бай бүр data-drop="<төрөл>:<утга>" гэсэн шошготой:
//   grp:<байгууллага>  — бүлгийн толгой
//   gap:<байгууллага>  — бүлгүүдийн хоорондох зай (энэ бүлгийн ӨМНӨ)
//   row:<id>           — албан хаагчийн мөр (энэ мөрийн ӨМНӨ)
//   end:<байгууллага>  — бүлгийн хамгийн ард
//   tail               — жагсаалтын хамгийн ард

const dragging = ref(null);   // { kind: 'row'|'group', id?, org }
const dropToken = ref(null);

const moveGroup = (group, direction) => {
    router.patch(
        route('phone-directory.reorder'),
        { org_name: group.org_name, direction },
        { preserveScroll: true },
    );
};

const moveGroupBefore = (orgName, beforeOrgName) => {
    if (orgName === beforeOrgName) return;

    router.patch(
        route('phone-directory.reorder'),
        { org_name: orgName, before_org_name: beforeOrgName ?? '' },
        { preserveScroll: true },
    );
};

const moveRow = (id, orgName, beforeId) => {
    router.patch(
        route('phone-directory.reorder-row'),
        { id, org_name: orgName, before_id: beforeId },
        { preserveScroll: true },
    );
};

// ↑/↓ товч — бүлэг доторх мөрийг нэг байрлалаар зөөнө.
const moveRowBy = (group, index, step) => {
    const rows = group.rows;
    const target = index + step;

    if (target < 0 || target >= rows.length) return;

    // Дээш: хөршийнхөө өмнө. Доош: хөршийн дараах мөрийн өмнө (эсвэл ард).
    const beforeId = step < 0
        ? rows[target].id
        : (rows[target + 1]?.id ?? null);

    moveRow(rows[index].id, group.org_name, beforeId);
};

const groupOfRow = (rowId) => props.groups.find((g) => g.rows.some((r) => r.id === rowId)) ?? null;

const groupAfter = (orgName) => {
    const i = props.groups.findIndex((g) => g.org_name === orgName);

    return i === -1 ? null : (props.groups[i + 1] ?? null);
};

const onPointerMove = (event) => {
    if (! dragging.value) return;

    event.preventDefault();

    const el = document.elementFromPoint(event.clientX, event.clientY);
    dropToken.value = el?.closest('[data-drop]')?.dataset.drop ?? null;
};

const commitDrop = (state, token) => {
    if (! token) return;

    const sep = token.indexOf(':');
    const kind = sep === -1 ? token : token.slice(0, sep);
    const value = sep === -1 ? '' : token.slice(sep + 1);

    if (state.kind === 'row') {
        if (kind === 'row') {
            const targetId = Number(value);
            const target = groupOfRow(targetId);

            if (target && targetId !== state.id) moveRow(state.id, target.org_name, targetId);

            return;
        }

        if (kind === 'grp' || kind === 'gap') {
            const target = props.groups.find((g) => g.org_name === value);

            if (target) moveRow(state.id, value, target.rows[0]?.id ?? null);

            return;
        }

        if (kind === 'end') moveRow(state.id, value, null);

        return;
    }

    // Бүлэг зөөх.
    if (kind === 'grp' || kind === 'gap') {
        moveGroupBefore(state.org, value);

        return;
    }

    if (kind === 'row') {
        const target = groupOfRow(Number(value));

        if (target) moveGroupBefore(state.org, target.org_name);

        return;
    }

    if (kind === 'end') {
        // Тухайн бүлгийн ард = дараагийн бүлгийн өмнө.
        moveGroupBefore(state.org, groupAfter(value)?.org_name ?? '');

        return;
    }

    if (kind === 'tail') moveGroupBefore(state.org, '');
};

const onPointerUp = () => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('pointercancel', onPointerUp);
    document.body.classList.remove('select-none');

    const state = dragging.value;
    const token = dropToken.value;

    dragging.value = null;
    dropToken.value = null;

    if (state) commitDrop(state, token);
};

const startDrag = (event, state) => {
    if (! canInsert.value) return;

    event.preventDefault();
    dragging.value = state;
    dropToken.value = null;
    document.body.classList.add('select-none');

    window.addEventListener('pointermove', onPointerMove, { passive: false });
    window.addEventListener('pointerup', onPointerUp);
    window.addEventListener('pointercancel', onPointerUp);
};

const isDraggingRow = computed(() => dragging.value?.kind === 'row');
const isDraggingGroup = computed(() => dragging.value?.kind === 'group');

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
};

// Тухайн хэлтэс/байгууллагын доор шууд шинэ мөр нэмнэ.
const openAddRow = (group) => {
    resetDirectoryForm();
    form.org_name = group.org_name;
    form.category = group.category || '';
    showForm.value = true;
};

// Хоёр бүлгийн хооронд шинэ хүснэгт (байгууллага/хэлтэс) оруулна.
const openInsertGroup = (beforeGroup) => {
    resetDirectoryForm();
    form.before_org_name = beforeGroup.org_name;
    showForm.value = true;
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
                        class="ui-btn-ghost"
                        :class="manageMode ? '!border-brand-navy-500 !bg-brand-navy-50 !text-brand-navy-800' : ''"
                        @click="toggleManageMode"
                    >
                        {{ manageMode ? 'Засварыг дуусгах' : 'Засварлах' }}
                    </button>
                    <button
                        v-if="editingActive"
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
                        :title="`${tab.orgCount} нэгж · ${tab.peopleCount} албан хаагч`"
                        @click="categoryFilter = tab.value"
                    >
                        {{ tab.label }}
                        <span class="ml-1.5 text-xs opacity-80">{{ tab.orgCount }}</span>
                        <span class="ml-0.5 text-[11px] opacity-60">нэгж</span>
                        <span class="mx-1 text-xs opacity-40">·</span>
                        <span class="text-xs opacity-80">{{ tab.peopleCount }}</span>
                        <span class="ml-0.5 text-[11px] opacity-60">хүн</span>
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
                            <th v-if="editingActive" />
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(group, gi) in filteredGroups" :key="group.org_name">
                            <tr
                                v-if="canInsert"
                                class="group/ins"
                                :data-drop="'gap:' + group.org_name"
                            >
                                <td :colspan="editingActive ? 6 : 5" class="!py-0.5 text-center">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg border border-dashed py-1 text-xs font-medium transition focus:opacity-100"
                                        :class="dropToken === 'gap:' + group.org_name
                                            ? 'border-brand-navy-400 bg-brand-navy-50 text-brand-navy-700 opacity-100'
                                            : (dragging
                                                ? 'border-slate-200 text-slate-400 opacity-100'
                                                : 'border-slate-200 text-slate-400 opacity-0 hover:border-brand-navy-300 hover:text-brand-navy-700 group-hover/ins:opacity-100')"
                                        @click="openInsertGroup(group)"
                                    >
                                        {{ dragging ? '⇩ Энд байрлуулах' : '＋ Энд шинэ хүснэгт нэмэх' }}
                                    </button>
                                </td>
                            </tr>
                            <tr
                                class="bg-brand-navy-50"
                                :class="[
                                    isDraggingGroup && dragging.org === group.org_name ? 'opacity-40' : '',
                                    dropToken === 'grp:' + group.org_name ? 'outline outline-2 -outline-offset-2 outline-brand-navy-500' : '',
                                ]"
                                :data-drop="'grp:' + group.org_name"
                            >
                                <td :colspan="editingActive ? 6 : 5" class="text-center font-semibold italic text-brand-navy-800">
                                    <span
                                        v-if="canInsert"
                                        class="mr-1 inline-block cursor-grab touch-none select-none px-1 text-slate-400 active:cursor-grabbing"
                                        title="Чирж байрлуулна"
                                        @pointerdown="startDrag($event, { kind: 'group', org: group.org_name })"
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
                                        v-if="editingActive"
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
                                        v-if="editingActive"
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
                                :class="[
                                    isDraggingRow && dragging.id === row.id ? 'opacity-40' : '',
                                    dropToken === 'row:' + row.id ? 'outline outline-2 -outline-offset-2 outline-brand-navy-500' : '',
                                ]"
                                :data-drop="'row:' + row.id"
                            >
                                <td class="text-center">
                                    <span
                                        v-if="canInsert"
                                        class="mr-1 inline-block cursor-grab touch-none select-none px-1 text-slate-300 active:cursor-grabbing"
                                        title="Чирж зөөнө"
                                        @pointerdown="startDrag($event, { kind: 'row', id: row.id, org: group.org_name })"
                                    >⠿</span>
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
                                <td v-if="editingActive" class="text-right whitespace-nowrap">
                                    <span v-if="canInsert" class="mr-1 inline-flex align-middle">
                                        <button
                                            type="button"
                                            class="rounded-l-lg border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-600 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="index === 0"
                                            title="Дээш зөөх"
                                            @click="moveRowBy(group, index, -1)"
                                        >↑</button>
                                        <button
                                            type="button"
                                            class="-ml-px rounded-r-lg border border-slate-300 bg-white px-1.5 py-0.5 text-xs text-slate-600 hover:bg-slate-100 disabled:opacity-30"
                                            :disabled="index === group.rows.length - 1"
                                            title="Доош зөөх"
                                            @click="moveRowBy(group, index, 1)"
                                        >↓</button>
                                    </span>
                                    <button type="button" class="ui-btn-ghost mr-1 !py-1 text-xs" @click="openEdit(row, group)">Засах</button>
                                    <button type="button" class="ui-btn-danger !py-1 text-xs" @click="destroyRow(row.id)">Устгах</button>
                                </td>
                            </tr>
                            <tr
                                v-if="canInsert && dragging"
                                :data-drop="'end:' + group.org_name"
                            >
                                <td :colspan="editingActive ? 6 : 5" class="!py-1 text-center">
                                    <span
                                        class="block rounded-lg border border-dashed py-1 text-xs font-medium"
                                        :class="dropToken === 'end:' + group.org_name
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
                            data-drop="tail"
                        >
                            <td :colspan="editingActive ? 6 : 5" class="!py-0.5 text-center">
                                <button
                                    type="button"
                                    class="w-full rounded-lg border border-dashed py-1 text-xs font-medium transition focus:opacity-100"
                                    :class="dropToken === 'tail'
                                        ? 'border-brand-navy-400 bg-brand-navy-50 text-brand-navy-700 opacity-100'
                                        : (dragging
                                            ? 'border-slate-200 text-slate-400 opacity-100'
                                            : 'border-slate-200 text-slate-400 opacity-0 hover:border-brand-navy-300 hover:text-brand-navy-700 group-hover/ins:opacity-100')"
                                    @click="openAdd"
                                >
                                    {{ dragging ? '⇩ Хамгийн ард байрлуулах' : '＋ Шинэ хүснэгт нэмэх' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredGroups.length">
                            <td :colspan="editingActive ? 6 : 5" class="!py-12 text-center text-slate-400">
                                {{ search ? 'Хайлтад тохирох бүртгэл алга.' : 'Одоогоор бүртгэл алга.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal: directory add / edit -->
        <Modal :show="showForm && editingActive && isDirectory" max-width="2xl" @close="closeDirectoryForm">
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
