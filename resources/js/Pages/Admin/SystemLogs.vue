<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    entries: { type: Array, default: () => [] },
    logPath: { type: String, default: '' },
});

const open = ref([]);

const toggle = (index) => {
    open.value = open.value.includes(index)
        ? open.value.filter((i) => i !== index)
        : [...open.value, index];
};

const refresh = () => {
    router.reload({ only: ['entries'] });
};

const levelClass = (level) => ({
    ERROR: 'bg-red-50 text-red-700',
    CRITICAL: 'bg-red-100 text-red-800',
    ALERT: 'bg-orange-100 text-orange-800',
    EMERGENCY: 'bg-red-200 text-red-900',
}[level] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <Head title="Системийн алдаа" />

    <AuthenticatedLayout title="Системийн алдаа">
        <div class="ui-page ui-page--tight">
            <section class="ui-card-pad space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="ui-title text-base">Сүүлийн алдаанууд</h3>
                        <p class="ui-subtitle mt-1">
                            Сервер дээр гарсан сүүлийн {{ entries.length }} алдаа.
                            Мөр дээр дарж дэлгэрэнгүйг нь харна.
                        </p>
                    </div>

                    <button type="button" class="ui-btn-ghost" @click="refresh">Шинэчлэх</button>
                </div>

                <p v-if="! entries.length" class="py-8 text-center text-sm text-slate-500">
                    Алдаа бүртгэгдээгүй байна.
                </p>

                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="(entry, index) in entries" :key="index" class="py-2">
                        <button
                            type="button"
                            class="flex w-full items-start gap-3 text-left"
                            @click="toggle(index)"
                        >
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold"
                                :class="levelClass(entry.level)"
                            >
                                {{ entry.level }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-brand-navy-800">
                                    {{ entry.message }}
                                </span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ entry.time }}</span>
                            </span>
                        </button>

                        <pre
                            v-if="open.includes(index) && entry.trace"
                            class="mt-2 max-h-80 overflow-auto rounded-xl bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-200"
                        >{{ entry.trace }}</pre>
                    </li>
                </ul>

                <p class="border-t border-slate-200 pt-2 text-[11px] text-slate-400">
                    {{ logPath }}
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
