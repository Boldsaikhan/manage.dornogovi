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
        <div class="mx-auto flex h-[calc(100vh-8rem)] max-w-3xl flex-col rounded-xl border border-brand-navy-100 bg-white shadow-sm">
            <div class="border-b border-brand-navy-100 px-4 py-3">
                <h2 class="font-semibold text-brand-navy-900">Хиймэл оюун ухаант туслах</h2>
                <p class="text-xs text-brand-navy-500">Зөвхөн энэ системд байгаа мэдээлэл дээр суурилна.</p>
            </div>
            <div ref="box" class="flex-1 space-y-3 overflow-y-auto p-4">
                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    class="max-w-[85%] rounded-2xl px-3 py-2 text-sm"
                    :class="msg.role === 'user' ? 'ml-auto bg-brand-navy-700 text-white' : 'bg-brand-navy-50 text-brand-navy-800'"
                >
                    {{ msg.content }}
                </div>
                <p v-if="!messages.length" class="text-center text-sm text-brand-navy-400">
                    Жишээ: «хүлээгдэж буй чөлөө хэд байна», «үүрэг», «журам»
                </p>
            </div>
            <form class="flex gap-2 border-t border-brand-navy-100 p-3" @submit.prevent="ask">
                <input
                    v-model="form.message"
                    required
                    placeholder="Асуултаа бичнэ үү…"
                    class="flex-1 rounded-lg border-brand-navy-200 text-sm"
                />
                <button class="rounded-lg bg-brand-orange-500 px-4 py-2 text-sm font-medium text-white" :disabled="form.processing">
                    Илгээх
                </button>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
