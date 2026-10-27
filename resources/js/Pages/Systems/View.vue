<script setup>
import { computed, ref, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import VaultUnlockForm from '@/Components/VaultUnlockForm.vue';

/**
 * Холбосон системийн хуудас.
 *
 * Нэвтрэх нэр, нууц үгийг хажуугийн цэсний харандаа дээрх жижиг цонхоор
 * тохируулна — энд зөвхөн нэвтрэх товч болон дотор нээгддэг систем байна.
 */
const props = defineProps({
    system: Object,
    target: String,
});

const page = usePage();
const loading = ref(true);
const frameKey = ref(0);

const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

const canLaunch = computed(() => (
    props.system.requires_login
    && props.system.has_credential
    && vaultUnlocked.value
));

const launchUrl = computed(() => route('systems.launch', props.system.id));

// Хажуугийн цэснээс нэвтрэхийг оролдоод сан түгжээтэй байсан тохиолдол —
// нээмэгц шууд үргэлжлүүлнэ (ижил табд, тиймээс popup хаагдахгүй).
const pendingLaunch = ref(
    Number(page.props.flash?.launch_after_unlock ?? 0) === Number(props.system.id),
);

watch(vaultUnlocked, (unlocked) => {
    if (unlocked && pendingLaunch.value) {
        pendingLaunch.value = false;
        window.location.href = launchUrl.value;
    }
});

watch(
    () => props.system.id,
    () => {
        loading.value = true;
        frameKey.value++;
    },
);

const openUrl = computed(() => (canLaunch.value ? launchUrl.value : props.target));

const reload = () => {
    loading.value = true;
    frameKey.value++;
};
</script>

<template>
    <Head :title="system.name" />

    <AuthenticatedLayout>
        <template #header>{{ system.name }}</template>

        <div class="space-y-4">
            <div
                v-if="page.props.flash?.success"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="page.props.flash?.info"
                class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800"
            >
                {{ page.props.flash.info }}
            </div>
            <div
                v-if="page.props.flash?.warning"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ page.props.flash.warning }}
            </div>

            <section
                v-if="system.requires_login"
                class="rounded-xl border border-brand-navy-100 bg-white p-5 shadow-sm"
            >
                <template v-if="canLaunch">
                    <a
                        :href="launchUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-md bg-brand-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-orange-600"
                    >
                        {{ system.name }} руу нэвтрэх
                    </a>
                    <p class="mt-2 text-xs text-slate-500">
                        Сан нээлттэй байна. Шинэ табд нэвтрэх хуудас нээгдэнэ.
                    </p>
                </template>

                <template v-else-if="! system.has_credential">
                    <p class="text-sm text-slate-600">
                        Нэвтрэх нэр, нууц үгээ хадгалаагүй байна. Хажуугийн цэсэн дэх
                        <b>{{ system.name }}</b> дээр хулганаа аваачаад
                        <b>харандаа</b> icon дарж хадгална.
                    </p>
                </template>

                <template v-else>
                    <p class="mb-3 text-sm text-slate-600">
                        {{ pendingLaunch
                            ? 'Сан түгжээтэй байна. Нээвэл ' + system.name + ' рүү шууд үргэлжилнэ.'
                            : 'Хадгалсан мэдээллээр нэвтрэхийн тулд сангаа нээнэ үү.' }}
                    </p>
                    <VaultUnlockForm />
                </template>
            </section>

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
                        :href="openUrl"
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
        </div>
    </AuthenticatedLayout>
</template>
