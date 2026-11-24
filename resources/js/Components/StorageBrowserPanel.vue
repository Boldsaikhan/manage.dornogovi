<script setup>
import { computed, onMounted, ref } from 'vue';
import Modal from '@/Components/Modal.vue';

const roots = ref([]);
const root = ref('');
const path = ref('');
const breadcrumbs = ref([]);
const directories = ref([]);
const files = ref([]);
const loading = ref(false);
const error = ref('');
const query = ref('');
const preview = ref(null);

const load = async (nextRoot = root.value, nextPath = path.value) => {
    if (! nextRoot) return;

    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get(route('admin.storage.browse'), {
            params: { root: nextRoot, path: nextPath || undefined },
        });

        roots.value = data.roots || [];
        root.value = data.root;
        path.value = data.path || '';
        breadcrumbs.value = data.breadcrumbs || [];
        directories.value = data.directories || [];
        files.value = data.files || [];
    } catch (err) {
        error.value = err?.response?.data?.message || 'Хавтас ачаалж чадсангүй.';
        directories.value = [];
        files.value = [];
    } finally {
        loading.value = false;
    }
};

const filteredDirectories = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (! q) return directories.value;

    return directories.value.filter((item) => item.name.toLowerCase().includes(q));
});

const filteredFiles = computed(() => {
    const q = query.value.trim().toLowerCase();

    if (! q) return files.value;

    return files.value.filter((item) => item.name.toLowerCase().includes(q));
});

const openRoot = (key) => {
    query.value = '';
    load(key, '');
};

const openPath = (nextPath) => {
    query.value = '';
    load(root.value, nextPath || '');
};

const openPreview = (file) => {
    if (! file?.is_image || ! file.preview_url) return;
    preview.value = file;
};

onMounted(async () => {
    for (const candidate of ['private', 'public', 'web_images', 'web_icons']) {
        await load(candidate, '');

        if (roots.value.length && ! error.value) {
            return;
        }
    }

    if (! roots.value.length) {
        error.value = 'Үзэх хавтас олдсонгүй.';
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem]">
                <label class="ui-label" for="storage-root">Үндсэн хавтас</label>
                <select
                    id="storage-root"
                    v-model="root"
                    class="ui-input"
                    @change="openRoot(root)"
                >
                    <option v-for="item in roots" :key="item.key" :value="item.key">
                        {{ item.label }}
                    </option>
                </select>
            </div>
            <div class="min-w-[14rem] flex-1">
                <label class="ui-label" for="storage-search">Хайлт</label>
                <input
                    id="storage-search"
                    v-model="query"
                    type="search"
                    class="ui-input"
                    placeholder="Файлын нэр…"
                />
            </div>
        </div>

        <nav class="flex flex-wrap items-center gap-1 text-sm text-slate-600" aria-label="Зам">
            <template v-for="(crumb, index) in breadcrumbs" :key="crumb.path + '-' + index">
                <span v-if="index > 0" class="text-slate-300">/</span>
                <button
                    type="button"
                    class="rounded px-1.5 py-0.5 font-medium hover:bg-slate-100 hover:text-brand-navy-800"
                    :class="index === breadcrumbs.length - 1 ? 'text-brand-navy-800' : 'text-slate-500'"
                    @click="openPath(crumb.path)"
                >
                    {{ crumb.label }}
                </button>
            </template>
        </nav>

        <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">
            {{ error }}
        </p>

        <div v-if="loading" class="py-16 text-center text-sm text-slate-400">
            Ачаалж байна…
        </div>

        <div v-else class="ui-card overflow-hidden">
            <div class="ui-table-wrap !rounded-none !border-0 !shadow-none">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Нэр</th>
                            <th class="w-28">Төрөл</th>
                            <th class="w-28 text-right">Хэмжээ</th>
                            <th class="w-40">Огноо</th>
                            <th class="w-28 text-center">Үйлдэл</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="dir in filteredDirectories" :key="'d-' + dir.path">
                            <td>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 text-left text-sm font-medium text-brand-navy-800 hover:underline"
                                    @click="openPath(dir.path)"
                                >
                                    <svg class="h-4 w-4 shrink-0 text-brand-orange-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h5l2 2h11v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z" />
                                    </svg>
                                    {{ dir.name }}
                                </button>
                            </td>
                            <td class="text-sm text-slate-500">Хавтас</td>
                            <td class="text-right text-sm text-slate-400">—</td>
                            <td class="text-sm text-slate-400">—</td>
                            <td class="text-center">
                                <button type="button" class="ui-btn-ghost !px-2 !py-1 text-xs" @click="openPath(dir.path)">
                                    Нээх
                                </button>
                            </td>
                        </tr>

                        <tr v-for="file in filteredFiles" :key="'f-' + file.path">
                            <td>
                                <div class="flex items-center gap-3">
                                    <button
                                        v-if="file.is_image && file.preview_url"
                                        type="button"
                                        class="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                                        @click="openPreview(file)"
                                    >
                                        <img :src="file.preview_url" :alt="file.name" class="h-full w-full object-cover" />
                                    </button>
                                    <span
                                        v-else
                                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-[10px] font-semibold uppercase text-slate-500"
                                    >
                                        {{ file.extension || 'file' }}
                                    </span>
                                    <button
                                        v-if="file.is_image"
                                        type="button"
                                        class="text-left text-sm font-medium text-brand-navy-800 hover:underline"
                                        @click="openPreview(file)"
                                    >
                                        {{ file.name }}
                                    </button>
                                    <span v-else class="text-sm font-medium text-slate-800">{{ file.name }}</span>
                                </div>
                            </td>
                            <td class="text-sm uppercase text-slate-500">{{ file.extension || '—' }}</td>
                            <td class="text-right text-sm tabular-nums text-slate-600">{{ file.size_label }}</td>
                            <td class="text-sm tabular-nums text-slate-500">{{ file.modified_at || '—' }}</td>
                            <td class="text-center">
                                <a :href="file.download_url" class="ui-btn-ghost !px-2 !py-1 text-xs">
                                    Татах
                                </a>
                            </td>
                        </tr>

                        <tr v-if="! filteredDirectories.length && ! filteredFiles.length">
                            <td colspan="5" class="py-12 text-center text-sm text-slate-400">
                                Энэ хавтас хоосон.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="!! preview" max-width="5xl" @close="preview = null">
            <div class="ui-card-pad space-y-3" v-if="preview">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="ui-title text-base">{{ preview.name }}</h3>
                        <p class="ui-subtitle !mt-0 text-sm">{{ preview.size_label }} · {{ preview.modified_at }}</p>
                    </div>
                    <a :href="preview.download_url" class="ui-btn-primary shrink-0">Татах</a>
                </div>
                <div class="overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-2">
                    <img :src="preview.preview_url" :alt="preview.name" class="mx-auto max-h-[70vh] max-w-full object-contain" />
                </div>
            </div>
        </Modal>
    </div>
</template>
