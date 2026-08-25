<script setup>
import { nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    messages: Array,
    canManage: Boolean,
});

const form = useForm({ message: '' });
const box = ref(null);

watch(
    () => props.messages?.length,
    async () => {
        await nextTick();
        if (box.value) box.value.scrollTop = box.value.scrollHeight;
    },
);

const ask = () => {
    form.post(route('ai.ask'), {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
};
</script>

<template>
    <AuthenticatedLayout title="AI туслах">
        <div class="mx-auto flex h-[calc(100vh-9rem)] max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-panel">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="ui-title text-base">Хиймэл оюун ухаант туслах</h2>
                <p class="ui-subtitle !mt-0.5">Зөвхөн энэ системд байгаа мэдээлэл дээр суурилна.</p>
            </div>
            <div ref="box" class="flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-5">
                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                    :class="msg.role === 'user' ? 'ml-auto bg-brand-navy-600 text-white' : 'bg-white text-slate-800 ring-1 ring-slate-100'"
                >
                    {{ msg.content }}
                </div>
                <p v-if="!messages.length" class="py-10 text-center text-sm text-slate-400">
                    Жишээ: «хүлээгдэж буй чөлөө хэд байна», «үүрэг», «журам»
                </p>
            </div>
            <form class="flex gap-2 border-t border-slate-100 bg-white p-4" @submit.prevent="ask">
                <input
                    v-model="form.message"
                    required
                    placeholder="Асуултаа бичнэ үү…"
                    class="ui-input flex-1"
                />
                <button class="ui-btn-primary" :disabled="form.processing">Илгээх</button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
