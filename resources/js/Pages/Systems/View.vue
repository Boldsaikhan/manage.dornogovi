<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    system: Object,
    target: String,
});

const loading = ref(true);
const frameKey = ref(0);

const reload = () => {
    loading.value = true;
    frameKey.value++;
};
</script>

<template>
    <Head :title="system.name" />

    <AuthenticatedLayout>
        <template #header>{{ system.name }}</template>

        <!-- Дотор нээгддэг систем -->
        <div
            v-if="system.is_embeddable"
            class="overflow-hidden rounded-xl border border-brand-navy-100 bg-white shadow-sm"
        >
            <div class="flex items-center gap-3 border-b border-brand-navy-100 px-4 py-2">
                <span class="truncate text-xs text-brand-navy-400">{{ target }}</span>
                <button
                    class="ml-auto shrink-0 rounded-md border border-brand-navy-200 px-2.5 py-1 text-xs text-brand-navy-700 hover:bg-brand-navy-50"
                    @click="reload"
                >
                    Дахин ачаалах
                </button>
                <a
                    :href="target"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="shrink-0 rounded-md border border-brand-navy-200 px-2.5 py-1 text-xs text-brand-navy-700 hover:bg-brand-navy-50"
                >
                    Шинэ табд нээх
                </a>
            </div>

            <div class="relative" style="height: calc(100vh - 10rem); min-height: 30rem">
                <div
                    v-if="loading"
                    class="absolute inset-0 flex items-center justify-center bg-white text-sm text-brand-navy-400"
                >
                    Ачаалж байна…
                </div>
                <iframe
                    :key="frameKey"
                    :src="target"
                    class="h-full w-full"
                    referrerpolicy="no-referrer-when-downgrade"
                    @load="loading = false"
                />
            </div>
        </div>

        <!-- Дотор нээгдэхийг сервер нь хориглосон -->
        <div v-else class="rounded-xl border border-brand-navy-100 bg-white p-6 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-yellow-50 text-yellow-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h3 class="font-semibold text-brand-navy-800">
                        Энэ системийг дотор нь нээх боломжгүй
                    </h3>
                    <p class="mt-1 text-sm text-brand-navy-400">
                        {{ system.name }}-ийн сервер өөр сайт дотор ачаалагдахыг хориглосон байна.
                        Энэ нь тухайн системийн аюулгүй байдлын тохиргоо тул бидний талаас
                        тойрч гарах боломжгүй.
                    </p>
                    <p v-if="system.embed_blocked_by" class="mt-2 font-mono text-xs text-brand-navy-300">
                        {{ system.embed_blocked_by }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a
                            :href="target"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-md bg-brand-orange-500 px-4 py-1.5 text-sm font-medium text-white hover:bg-brand-orange-600"
                        >
                            Шинэ табд нээх
                        </a>
                        <Link
                            :href="route('dashboard')"
                            class="rounded-md border border-brand-navy-200 px-4 py-1.5 text-sm font-medium text-brand-navy-700 hover:bg-brand-navy-50"
                        >
                            Системүүд рүү буцах
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
