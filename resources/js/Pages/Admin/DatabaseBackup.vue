<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
});

const downloading = ref(false);
const filter = ref('');

const tables = computed(() => {
    const q = filter.value.trim().toLowerCase();
    const list = props.stats?.tables ?? [];
    if (! q) {
        return list;
    }

    return list.filter((row) => String(row.name || '').toLowerCase().includes(q));
});

const refresh = () => {
    router.reload({ only: ['stats'] });
};

const download = () => {
    downloading.value = true;
    window.location.assign(route('admin.database.download'));
    window.setTimeout(() => {
        downloading.value = false;
    }, 4000);
};

const formatRows = (value) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return Number(value).toLocaleString('mn-MN');
};
</script>

<template>
    <Head title="Өгөгдлийн сан" />

    <AuthenticatedLayout title="Өгөгдлийн сан">
        <div class="ui-page ui-page--tight">
            <section class="ui-card-pad space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="ui-title text-base">Нөөцлөлт / үзүүлэлт</h3>
                        <p class="ui-subtitle mt-1">
                            Бүх хүснэгтийн SQL нөөцийг татаж авна. Зөвхөн үндсэн админд харагдана.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="ui-btn-ghost" @click="refresh">Шинэчлэх</button>
                        <button
                            type="button"
                            class="ui-btn-accent"
                            :disabled="downloading"
                            @click="download"
                        >
                            {{ downloading ? 'Татаж байна…' : 'Нөөцлөх / татах' }}
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="ui-stat">
                        <div class="ui-stat-label">Өгөгдлийн сан</div>
                        <div class="ui-stat-value !text-lg">{{ stats.name || '—' }}</div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ stats.driver }}{{ stats.host ? ` · ${stats.host}` : '' }}
                        </p>
                    </div>
                    <div class="ui-stat">
                        <div class="ui-stat-label">Хүснэгт</div>
                        <div class="ui-stat-value">{{ formatRows(stats.table_count) }}</div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Engine: {{ stats.engine || '—' }}
                        </p>
                    </div>
                    <div class="ui-stat">
                        <div class="ui-stat-label">Мөр (ойролцоо)</div>
                        <div class="ui-stat-value">{{ formatRows(stats.total_rows) }}</div>
                    </div>
                    <div class="ui-stat">
                        <div class="ui-stat-label">Хэмжээ</div>
                        <div class="ui-stat-value !text-lg">{{ stats.total_size || '—' }}</div>
                    </div>
                </div>
            </section>

            <section class="ui-register">
                <div class="ui-register__filters flex flex-wrap items-end gap-3 p-3">
                    <div class="min-w-[12rem] flex-1">
                        <label class="ui-label" for="db-table-filter">Хүснэгт хайх</label>
                        <input
                            id="db-table-filter"
                            v-model="filter"
                            type="search"
                            class="ui-input"
                            placeholder="нэр…"
                        >
                    </div>
                    <p class="pb-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ tables.length }} / {{ stats.table_count }}
                    </p>
                </div>

                <div class="ui-table-wrap !rounded-none !border-0 !shadow-none">
                    <table class="ui-register__table">
                        <thead>
                            <tr>
                                <th class="ui-register__cell--no">№</th>
                                <th>Хүснэгт</th>
                                <th>Engine</th>
                                <th class="text-right">Мөр</th>
                                <th class="text-right">Хэмжээ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="! tables.length">
                                <td colspan="5" class="ui-register__empty">Хүснэгт олдсонгүй.</td>
                            </tr>
                            <tr v-for="(row, index) in tables" :key="row.name">
                                <td class="ui-register__cell--no">{{ index + 1 }}</td>
                                <td class="font-medium text-brand-navy-800 dark:text-brand-navy-200">
                                    <span class="ui-clamp-1">{{ row.name }}</span>
                                </td>
                                <td class="text-slate-500 dark:text-slate-400">{{ row.engine || '—' }}</td>
                                <td class="text-right tabular-nums">{{ formatRows(row.rows) }}</td>
                                <td class="text-right tabular-nums">{{ row.size || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
