<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    token: String,
    valid: Boolean,
    state: String,
    device: Object,
});

const page = usePage();
const busy = ref(false);
const finished = ref(false);

const send = (routeName) => {
    busy.value = true;

    router.post(route(routeName, props.token), {}, {
        preserveScroll: true,
        onSuccess: () => {
            finished.value = true;
        },
        onFinish: () => {
            busy.value = false;
        },
    });
};

const stateText = {
    approved: 'Энэ хүсэлт аль хэдийн зөвшөөрөгдсөн байна.',
    consumed: 'Энэ хүсэлтээр нэвтэрсэн байна.',
    rejected: 'Энэ хүсэлт цуцлагдсан байна.',
    missing: 'Ийм хүсэлт олдсонгүй.',
    pending: 'Хүсэлтийн хугацаа дууссан байна.',
};
</script>

<template>
    <Head title="QR нэвтрэлт баталгаажуулах" />

    <AuthenticatedLayout>
        <template #header>QR нэвтрэлт</template>

        <div class="ui-page mx-auto max-w-md">
            <div v-if="finished || page.props.flash?.success" class="ui-card p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="mt-4 text-base font-semibold text-brand-navy-900">
                    {{ page.props.flash?.success ?? 'Хийгдлээ.' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">Энэ хуудсыг хааж болно.</p>
            </div>

            <div v-else-if="! valid" class="ui-card p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                    </svg>
                </div>
                <h2 class="mt-4 text-base font-semibold text-brand-navy-900">
                    {{ stateText[state] ?? 'Хүсэлт хүчингүй байна.' }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Компьютер дээрээ QR кодыг шинэчлээд дахин уншуулна уу.
                </p>
            </div>

            <div v-else class="ui-card p-6">
                <h2 class="text-base font-semibold text-brand-navy-900">Компьютерт нэвтрэхийг зөвшөөрөх үү?</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Доорх төхөөрөмж таны эрхээр нэгдсэн системд нэвтрэхийг хүсэж байна.
                    Хэрэв энэ та биш бол <strong>Татгалзах</strong> дарна уу.
                </p>

                <dl class="mt-5 space-y-2 rounded-xl bg-slate-50 p-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Төхөөрөмж</dt>
                        <dd class="font-medium text-brand-navy-800">{{ device?.agent }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">IP хаяг</dt>
                        <dd class="font-mono text-brand-navy-800">{{ device?.ip }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Хүсэлт</dt>
                        <dd class="text-brand-navy-800">{{ device?.requested_at }}</dd>
                    </div>
                </dl>

                <p class="mt-3 text-xs text-slate-400">
                    Хүсэлт 2 минут хүчинтэй. Зөвшөөрсний дараа тухайн компьютер таны эрхээр нэвтэрнэ.
                </p>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="ui-btn-ghost"
                        :disabled="busy"
                        @click="send('login.qr.reject')"
                    >
                        Татгалзах
                    </button>
                    <button
                        type="button"
                        class="ui-btn-primary"
                        :disabled="busy"
                        @click="send('login.qr.approve')"
                    >
                        Зөвшөөрөх
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
