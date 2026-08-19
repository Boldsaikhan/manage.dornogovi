<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    sources: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
});

const UNKNOWN = 'Тодорхойгүй';
const NO_DEPT = 'Хэлтэс оноогоогүй';

const iconPaths = {
    list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    building: 'M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M4 21h16M16 9h2a2 2 0 012 2v10M8 7h2M8 11h2M8 15h2',
    chart: 'M4 20V10M10 20V4M16 20v-6M22 20H2',
    check: 'M20 6L9 17l-5-5',
    users: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
    folder: 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
};

// ---------- төлөв ----------
const view = ref('list');       // list | dept | chart
const sourceId = ref('');       // '' = бүх эх сурвалж
const query = ref('');
const deptFilter = ref('');
const sectorFilter = ref('');
const doneOnly = ref(false);

const sourceName = (id) => props.sources.find((s) => s.id === id)?.name ?? '';
const deptOf = (task) => (task.department || '').trim() || NO_DEPT;
const sectorOf = (task) => (task.sector || '').trim() || UNKNOWN;

// ---------- шүүлтүүр ----------
const matches = (task, { useDept = true, useSector = true } = {}) => {
    if (sourceId.value && task.task_source_id !== Number(sourceId.value)) return false;
    if (doneOnly.value && task.progress < 100) return false;
    if (useDept && deptFilter.value && deptOf(task) !== deptFilter.value) return false;
    if (useSector && sectorFilter.value && sectorOf(task) !== sectorFilter.value) return false;

    const q = query.value.trim().toLowerCase();
    if (!q) return true;

    return [task.text, task.responsible, task.collaborator, task.note, task.sector, task.department]
        .join(' ')
        .toLowerCase()
        .includes(q);
};

const filtered = computed(() => props.tasks.filter((t) => matches(t)));
const average = (list) => (list.length ? Math.round(list.reduce((a, t) => a + t.progress, 0) / list.length) : 0);

// Жагсаалтын дугаар — эх сурвалж, түүний дотор салбар тус бүрд 1-ээс эхэлнэ
const numbers = computed(() => {
    const seen = {};
    const map = {};
    props.tasks.forEach((t) => {
        const key = `${t.task_source_id}|${sectorOf(t)}`;
        map[t.id] = seen[key] = (seen[key] || 0) + 1;
    });

    return map;
});

// ---------- бүлэглэлт ----------
const groupBy = (list, keyFn) => {
    const map = new Map();
    list.forEach((t) => {
        const k = keyFn(t);
        if (!map.has(k)) map.set(k, []);
        map.get(k).push(t);
    });

    return map;
};

const orderGroups = (map, unknownKey) =>
    [...map.keys()].sort((a, b) => {
        if (a === unknownKey) return 1;
        if (b === unknownKey) return -1;
        return map.get(b).length - map.get(a).length || a.localeCompare(b, 'mn');
    });

const deptGroups = computed(() => {
    const map = groupBy(props.tasks.filter((t) => matches(t, { useDept: false })), deptOf);

    return orderGroups(map, NO_DEPT).map((name) => ({ name, list: map.get(name), value: average(map.get(name)) }));
});

const sectorGroups = computed(() => {
    const map = groupBy(props.tasks.filter((t) => matches(t, { useSector: false })), sectorOf);

    return orderGroups(map, UNKNOWN).map((name) => ({ name, list: map.get(name), value: average(map.get(name)) }));
});

// Хариуцагч → хэлтсийн зураглал (нэг хариуцагчийн бүх ажлыг нэг дор оноох)
const owners = computed(() => {
    const map = new Map();
    props.tasks.forEach((t) => {
        const owner = (t.responsible || '').trim();
        if (!owner) return;
        if (!map.has(owner)) map.set(owner, { owner, count: 0, department: (t.department || '').trim() });
        map.get(owner).count += 1;
    });

    return [...map.values()].sort((a, b) => b.count - a.count || a.owner.localeCompare(b.owner, 'mn'));
});

const knownDepts = computed(() => [...new Set(props.tasks.map(deptOf).filter((d) => d !== NO_DEPT))].sort((a, b) => a.localeCompare(b, 'mn')));

// ---------- статистик ----------
const stats = computed(() => ({
    total: filtered.value.length,
    all: props.tasks.length,
    depts: deptGroups.value.filter((g) => g.name !== NO_DEPT).length,
    owners: owners.value.length,
    done: filtered.value.filter((t) => t.progress >= 100).length,
    average: average(filtered.value),
}));

// ---------- засвар ----------
const editing = ref(null);      // `${id}:${field}`
const draft = ref('');
const saving = ref(false);

const startEdit = (task, field) => {
    editing.value = `${task.id}:${field}`;
    draft.value = task[field] ?? '';
};

const cancelEdit = () => {
    editing.value = null;
    draft.value = '';
};

const commitEdit = (task, field) => {
    const value = field === 'progress' ? Math.max(0, Math.min(100, Number(draft.value) || 0)) : draft.value;
    cancelEdit();
    if (String(value) === String(task[field] ?? '')) return;

    saving.value = true;
    router.patch(route('tasks.update', task.id), { [field]: value }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => (saving.value = false),
    });
};

const setProgress = (task, value) => {
    saving.value = true;
    router.patch(route('tasks.update', task.id), { progress: Math.max(0, Math.min(100, Number(value) || 0)) }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => (saving.value = false),
    });
};

const assignDepartment = (owner, department) => {
    if ((department || '').trim() === (owner.department || '').trim()) return;

    saving.value = true;
    router.post(route('tasks.assign-department'), { responsible: owner.owner, department }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => (saving.value = false),
    });
};

const pickDept = (name) => {
    deptFilter.value = deptFilter.value === name ? '' : name;
};

const pickSector = (name) => {
    sectorFilter.value = sectorFilter.value === name ? '' : name;
};

const statusClass = (task) =>
    task.progress >= 100 ? 'bg-green-600' : task.progress > 0 ? 'bg-brand-navy-600' : 'bg-brand-navy-200';
</script>

<template>
    <Head title="Үүрэг, чиглэл" />

    <AuthenticatedLayout>
        <div class="space-y-4">
            <!-- Статистик -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="card in [
                        { icon: 'folder', label: 'Нийт ажил', value: `${stats.total} / ${stats.all}` },
                        { icon: 'building', label: 'Хэлтэс', value: stats.depts },
                        { icon: 'users', label: 'Хариуцагч', value: stats.owners },
                        { icon: 'check', label: 'Хэрэгжсэн · дундаж', value: `${stats.done} / ${stats.average}%` },
                    ]"
                    :key="card.label"
                    class="flex items-center gap-3 rounded-xl border border-brand-navy-100 bg-white p-4 shadow-sm"
                >
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-navy-50 text-brand-navy-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths[card.icon]" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <div class="text-lg font-semibold text-brand-navy-900">{{ card.value }}</div>
                        <div class="truncate text-xs text-brand-navy-400">{{ card.label }}</div>
                    </div>
                </div>
            </div>

            <!-- Эх сурвалж -->
            <div class="flex flex-wrap gap-2">
                <button
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="sourceId === '' ? 'bg-brand-orange-500 text-white' : 'border border-brand-navy-200 text-brand-navy-700 hover:bg-brand-navy-50'"
                    @click="sourceId = ''"
                >
                    Бүх эх сурвалж
                    <span class="ml-1 text-xs opacity-80">{{ tasks.length }}</span>
                </button>
                <button
                    v-for="source in sources"
                    :key="source.id"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                    :class="Number(sourceId) === source.id ? 'bg-brand-orange-500 text-white' : 'border border-brand-navy-200 text-brand-navy-700 hover:bg-brand-navy-50'"
                    @click="sourceId = source.id"
                >
                    {{ source.name }}
                    <span class="ml-1 text-xs opacity-80">{{ tasks.filter((t) => t.task_source_id === source.id).length }}</span>
                </button>
            </div>

            <!-- Хайлт ба харагдац -->
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-brand-navy-100 bg-white p-3 shadow-sm">
                <input
                    v-model="query"
                    type="search"
                    placeholder="Хайх… (үүрэг чиглэл, хариуцагч, хэлтэс)"
                    class="min-w-[240px] flex-1 rounded-md border border-brand-navy-200 px-3 py-2 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                />

                <div class="flex gap-1 rounded-md border border-brand-navy-200 p-1">
                    <button
                        v-for="tab in [{ k: 'list', l: 'Жагсаалт', i: 'list' }, { k: 'dept', l: 'Хэлтэс', i: 'building' }, { k: 'chart', l: 'График', i: 'chart' }]"
                        :key="tab.k"
                        class="flex items-center gap-1.5 rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="view === tab.k ? 'bg-brand-orange-500 text-white' : 'text-brand-navy-700 hover:bg-brand-navy-50'"
                        @click="view = tab.k"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths[tab.i]" />
                        </svg>
                        {{ tab.l }}
                    </button>
                </div>

                <label class="flex items-center gap-2 text-sm text-brand-navy-700">
                    <input v-model="doneOnly" type="checkbox" class="rounded border-brand-navy-200 text-brand-orange-500 focus:ring-brand-orange-500" />
                    Зөвхөн хэрэгжсэн
                </label>

                <span v-if="saving" class="text-xs text-brand-orange-600">хадгалж байна…</span>
            </div>

            <!-- Идэвхтэй шүүлтүүр -->
            <div v-if="deptFilter || sectorFilter" class="flex flex-wrap gap-2">
                <button v-if="deptFilter" class="rounded-full bg-brand-orange-100 px-3 py-1 text-xs font-medium text-brand-orange-700" @click="deptFilter = ''">
                    ✕ {{ deptFilter }}
                </button>
                <button v-if="sectorFilter" class="rounded-full bg-brand-orange-100 px-3 py-1 text-xs font-medium text-brand-orange-700" @click="sectorFilter = ''">
                    ✕ {{ sectorFilter }}
                </button>
            </div>

            <!-- ЖАГСААЛТ -->
            <div v-show="view === 'list'" class="overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-brand-navy-50 text-left text-xs text-brand-navy-700">
                            <tr>
                                <th class="px-3 py-2">№</th>
                                <th class="px-3 py-2">Салбар</th>
                                <th class="px-3 py-2">Хэлтэс</th>
                                <th class="px-3 py-2 min-w-[280px]">Үүрэг, чиглэл / Ажил</th>
                                <th class="px-3 py-2">Шалгуур үзүүлэлт</th>
                                <th class="px-3 py-2">Суурь</th>
                                <th class="px-3 py-2">Хүрэх</th>
                                <th class="px-3 py-2">Үндсэн хэрэгжүүлэгч</th>
                                <th class="px-3 py-2">Хамтран / Хяналт</th>
                                <th class="px-3 py-2 w-40">Хэрэгжилт</th>
                                <th class="px-3 py-2">Тайлбар</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(task, i) in filtered"
                                :key="task.id"
                                :class="i % 2 === 1 ? 'bg-brand-navy-50' : 'bg-white'"
                                class="border-t border-brand-navy-100 align-top hover:bg-brand-orange-50"
                            >
                                <td class="px-3 py-2 text-brand-navy-400">{{ numbers[task.id] }}</td>
                                <td class="px-3 py-2 text-brand-navy-700">{{ task.sector || '—' }}</td>
                                <td class="px-3 py-2">
                                    <span v-if="task.department" class="rounded-full bg-brand-navy-100 px-2 py-0.5 text-xs text-brand-navy-800">
                                        {{ task.department }}
                                    </span>
                                    <span v-else class="text-xs text-brand-navy-300">—</span>
                                </td>

                                <td v-for="field in ['text', 'indicator', 'baseline', 'target', 'responsible', 'collaborator']"
                                    :key="field"
                                    class="cursor-text px-3 py-2 text-brand-navy-700"
                                    @click="startEdit(task, field)"
                                >
                                    <textarea
                                        v-if="editing === `${task.id}:${field}` && field === 'text'"
                                        v-model="draft"
                                        rows="3"
                                        class="w-full rounded-md border border-brand-orange-500 px-2 py-1 text-sm focus:ring-brand-orange-500"
                                        @blur="commitEdit(task, field)"
                                        @keydown.esc="cancelEdit"
                                    />
                                    <input
                                        v-else-if="editing === `${task.id}:${field}`"
                                        v-model="draft"
                                        class="w-full rounded-md border border-brand-orange-500 px-2 py-1 text-sm focus:ring-brand-orange-500"
                                        @blur="commitEdit(task, field)"
                                        @keyup.enter="commitEdit(task, field)"
                                        @keydown.esc="cancelEdit"
                                    />
                                    <span v-else>{{ task[field] || '—' }}</span>
                                </td>

                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-brand-navy-100">
                                            <div class="h-full rounded-full" :class="statusClass(task)" :style="{ width: task.progress + '%' }" />
                                        </div>
                                        <input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="5"
                                            :value="task.progress"
                                            class="w-16 rounded-md border border-brand-navy-200 px-1 py-0.5 text-center text-xs focus:border-brand-orange-500 focus:ring-brand-orange-500"
                                            @change="setProgress(task, $event.target.value)"
                                        />
                                    </div>
                                </td>

                                <td class="cursor-text px-3 py-2 text-brand-navy-400" @click="startEdit(task, 'note')">
                                    <input
                                        v-if="editing === `${task.id}:note`"
                                        v-model="draft"
                                        class="w-full rounded-md border border-brand-orange-500 px-2 py-1 text-sm focus:ring-brand-orange-500"
                                        @blur="commitEdit(task, 'note')"
                                        @keyup.enter="commitEdit(task, 'note')"
                                        @keydown.esc="cancelEdit"
                                    />
                                    <span v-else>{{ task.note || '—' }}</span>
                                </td>
                            </tr>
                            <tr v-if="!filtered.length">
                                <td colspan="11" class="px-3 py-10 text-center text-sm text-brand-navy-400">Шүүлтүүрт тохирох ажил алга.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-brand-navy-100 px-3 py-2 text-xs text-brand-navy-400">
                    ✏️ Нүд дээр дарж шууд засна — Enter хадгална, Esc буцаана. Засвар нь санд хадгалагдаж бүх хэрэглэгчид харагдана.
                </div>
            </div>

            <!-- ХЭЛТЭС -->
            <div v-show="view === 'dept'" class="space-y-4">
                <div class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-baseline gap-2">
                        <h3 class="font-semibold text-brand-navy-800">Хэлтэс тус бүрийн хэрэгжилт</h3>
                        <span class="text-xs text-brand-navy-400">хэлтсийн нэр дээр дарж шүүнэ</span>
                        <span class="ml-auto text-2xl font-bold text-brand-navy-900">{{ stats.average }}%</span>
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="group in deptGroups"
                            :key="group.name"
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-navy-50"
                            :class="deptFilter === group.name ? 'bg-brand-orange-50' : ''"
                            @click="pickDept(group.name)"
                        >
                            <span class="w-56 shrink-0 truncate text-sm text-brand-navy-700">
                                {{ group.name }}
                                <span class="text-brand-navy-300">({{ group.list.length }})</span>
                            </span>
                            <span class="h-3 flex-1 overflow-hidden rounded-full bg-brand-navy-100">
                                <span class="block h-full rounded-full bg-brand-navy-600" :style="{ width: group.value + '%' }" />
                            </span>
                            <span class="w-12 shrink-0 text-right text-sm font-semibold text-brand-navy-800">{{ group.value }}%</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-brand-navy-800">Хариуцагчийн хэлтсийн зураглал</h3>
                    <p class="mt-1 text-xs text-brand-navy-400">
                        Эх баримт бичигт хэлтсийн нэр байдаггүй тул хариуцагч бүрийг ямар хэлтэст хамаарахыг энд оноож өгнө —
                        тухайн хариуцагчийн бүх ажил нэг дор шинэчлэгдэнэ.
                    </p>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-brand-navy-50 text-left text-xs text-brand-navy-700">
                                <tr>
                                    <th class="px-3 py-2">Үндсэн хэрэгжүүлэгч</th>
                                    <th class="px-3 py-2">Ажлын тоо</th>
                                    <th class="px-3 py-2">Хэлтэс</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(owner, i) in owners" :key="owner.owner" :class="i % 2 === 1 ? 'bg-brand-navy-50' : 'bg-white'" class="border-t border-brand-navy-100">
                                    <td class="px-3 py-2 text-brand-navy-700">{{ owner.owner }}</td>
                                    <td class="px-3 py-2 text-brand-navy-400">{{ owner.count }}</td>
                                    <td class="px-3 py-2">
                                        <input
                                            :value="owner.department"
                                            list="deptOptions"
                                            placeholder="хэлтсийн нэр…"
                                            class="w-64 rounded-md border border-brand-navy-200 px-2 py-1 text-sm focus:border-brand-orange-500 focus:ring-brand-orange-500"
                                            @change="assignDepartment(owner, $event.target.value)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <datalist id="deptOptions">
                            <option v-for="dept in knownDepts" :key="dept" :value="dept" />
                        </datalist>
                    </div>
                </div>
            </div>

            <!-- ГРАФИК -->
            <div v-show="view === 'chart'" class="space-y-4">
                <div class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-baseline gap-2">
                        <h3 class="font-semibold text-brand-navy-800">
                            Нийт хэрэгжилт{{ deptFilter ? ` — ${deptFilter}` : '' }}
                        </h3>
                        <span class="text-xs text-brand-navy-400">
                            {{ stats.total }} ажил · {{ stats.done }} хэрэгжсэн
                        </span>
                        <span class="ml-auto text-2xl font-bold text-brand-navy-900">{{ stats.average }}%</span>
                    </div>
                    <div class="mt-3 h-4 overflow-hidden rounded-full bg-brand-navy-100">
                        <div class="h-full rounded-full bg-brand-navy-600" :style="{ width: stats.average + '%' }" />
                    </div>
                </div>

                <div class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-baseline gap-2">
                        <h3 class="font-semibold text-brand-navy-800">Салбар тус бүрийн хэрэгжилт</h3>
                        <span class="text-xs text-brand-navy-400">салбар дээр дарж шүүнэ</span>
                    </div>
                    <div class="space-y-2">
                        <button
                            v-for="group in sectorGroups"
                            :key="group.name"
                            class="flex w-full items-center gap-3 rounded-lg px-2 py-1.5 text-left transition hover:bg-brand-navy-50"
                            :class="sectorFilter === group.name ? 'bg-brand-orange-50' : ''"
                            @click="pickSector(group.name)"
                        >
                            <span class="w-56 shrink-0 truncate text-sm text-brand-navy-700">
                                {{ group.name }}
                                <span class="text-brand-navy-300">({{ group.list.length }})</span>
                            </span>
                            <span class="h-3 flex-1 overflow-hidden rounded-full bg-brand-navy-100">
                                <span class="block h-full rounded-full bg-brand-navy-600" :style="{ width: group.value + '%' }" />
                            </span>
                            <span class="w-12 shrink-0 text-right text-sm font-semibold text-brand-navy-800">{{ group.value }}%</span>
                        </button>
                    </div>
                </div>

                <div class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
                    <h3 class="mb-4 font-semibold text-brand-navy-800">Ажил тус бүрийн хэрэгжилт</h3>
                    <div class="space-y-1.5">
                        <div v-for="task in filtered" :key="task.id" class="flex items-center gap-3">
                            <span class="w-72 shrink-0 truncate text-xs text-brand-navy-700" :title="task.text">
                                {{ numbers[task.id] }}. {{ task.text }}
                            </span>
                            <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-brand-navy-100">
                                <span class="block h-full rounded-full" :class="statusClass(task)" :style="{ width: task.progress + '%' }" />
                            </span>
                            <span class="w-10 shrink-0 text-right text-xs font-semibold text-brand-navy-800">{{ task.progress }}%</span>
                        </div>
                        <p v-if="!filtered.length" class="py-6 text-center text-sm text-brand-navy-400">Шүүлтүүрт тохирох ажил алга.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
