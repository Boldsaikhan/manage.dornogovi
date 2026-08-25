<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    conversations: { type: Array, default: () => [] },
    conversationId: { type: Number, default: null },
    briefing: { type: Object, default: null },
    usage: { type: Object, default: () => ({}) },
    aiEnabled: { type: Boolean, default: true },
    providerReady: { type: Boolean, default: true },
    canManage: Boolean,
});

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? '');
const form = useForm({
    message: '',
    conversation_id: props.conversationId,
});
const box = ref(null);

const suggestions = [
    { label: 'Үүрэг даалгавар', text: 'Үүрэг даалгаврын тайлан гарга' },
    { label: 'Захирамж', text: 'Сүүлийн 30 хоногийн захирамжуудыг харуул' },
    { label: 'Чөлөө', text: 'Хүлээгдэж буй чөлөөний хүсэлт хэд байна' },
    { label: 'Хурал', text: 'Өнөөдрийн хурлуудыг харуул' },
];

watch(
    () => props.conversationId,
    (id) => {
        form.conversation_id = id;
    },
);

watch(
    () => props.messages?.length,
    async () => {
        await nextTick();
        if (box.value) box.value.scrollTop = box.value.scrollHeight;
    },
);

const ask = (preset) => {
    if (preset) form.message = preset;
    if (!form.message.trim()) return;
    form.post(route('ai.ask'), {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
};

const newChat = () => {
    router.post(route('ai.conversations.store'));
};

const openConversation = (id) => {
    router.get(route('ai.index'), { conversation_id: id }, { preserveState: false });
};

const confirmAction = (action) => {
    if (!action?.type) return;
    router.post(route('ai.confirm'), {
        type: action.type,
        payload: action.data ?? {},
    }, { preserveScroll: true });
};

const usageLabel = computed(() => {
    if (props.usage?.remaining == null && props.usage?.limit === 0) return 'Хязгааргүй';
    if (props.usage?.limit > 0) {
        return `Өнөөдөр: ${props.usage.used ?? 0}/${props.usage.limit}`;
    }
    return '';
});
</script>

<template>
    <AuthenticatedLayout title="AI туслах">
        <div class="grid gap-4 lg:grid-cols-[240px_minmax(0,1fr)]">
            <!-- History -->
            <aside class="ui-card-pad hidden space-y-3 lg:block">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-800">Өмнөх яриа</h3>
                    <button type="button" class="ui-btn-ghost !px-2 !py-1 text-xs" @click="newChat">Шинэ</button>
                </div>
                <ul class="max-h-[70vh] space-y-1 overflow-y-auto">
                    <li v-for="c in conversations" :key="c.id">
                        <button
                            type="button"
                            class="w-full rounded-xl px-3 py-2 text-left text-sm transition"
                            :class="c.id === conversationId
                                ? 'bg-brand-navy-600 text-white'
                                : 'text-slate-700 hover:bg-slate-50'"
                            @click="openConversation(c.id)"
                        >
                            <span class="line-clamp-2 font-medium">{{ c.title || 'Яриа' }}</span>
                        </button>
                    </li>
                    <li v-if="!conversations.length" class="px-2 py-6 text-center text-xs text-slate-400">
                        Одоогоор яриа алга.
                    </li>
                </ul>
            </aside>

            <!-- Chat -->
            <div class="flex h-[calc(100vh-9rem)] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-panel">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="ui-title text-base">Хиймэл оюун ухаант туслах</h2>
                        <p class="ui-subtitle !mt-0.5">
                            ДЗДТГ-ын дотоод мэдээлэл дээр ажиллана · {{ usageLabel }}
                        </p>
                    </div>
                    <Link
                        v-if="page.props.auth?.user?.is_admin"
                        :href="route('admin.systems.index')"
                        class="ui-btn-ghost !py-1.5 text-xs"
                    >
                        Тохиргоо
                    </Link>
                </div>

                <div ref="box" class="flex-1 space-y-3 overflow-y-auto bg-slate-50/60 p-5">
                    <div class="max-w-[90%] rounded-2xl bg-white px-4 py-3 text-sm leading-relaxed text-slate-800 ring-1 ring-slate-100">
                        Сайн байна уу{{ userName ? `, ${userName}` : '' }}.
                        Би ДЗДТГ-ын дотоод мэдээлэл дээр ажиллах AI туслах.
                    </div>

                    <div
                        v-if="briefing?.items?.length"
                        class="max-w-[95%] rounded-2xl border border-slate-200 bg-white p-4 text-sm shadow-sm"
                    >
                        <p class="mb-2 font-semibold text-slate-800">{{ briefing.title }}</p>
                        <ul class="space-y-1.5">
                            <li v-for="(item, i) in briefing.items" :key="i" class="flex gap-2 text-slate-700">
                                <span
                                    class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                    :class="{
                                        'bg-red-500': item.tone === 'warn',
                                        'bg-amber-500': item.tone === 'info',
                                        'bg-emerald-500': item.tone === 'ok',
                                    }"
                                />
                                <span>{{ item.label }}</span>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="max-w-[90%] whitespace-pre-wrap rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                        :class="msg.role === 'user'
                            ? 'ml-auto bg-brand-navy-600 text-white'
                            : 'bg-white text-slate-800 ring-1 ring-slate-100'"
                    >
                        {{ msg.content }}
                        <div
                            v-if="msg.role === 'assistant' && msg.meta?.requires_confirmation && msg.meta?.action"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <button
                                type="button"
                                class="rounded-lg bg-brand-orange-500 px-3 py-1.5 text-xs font-semibold text-white"
                                @click="confirmAction(msg.meta.action)"
                            >
                                Баталгаажуулах
                            </button>
                            <span class="self-center text-xs text-slate-500">AI боловсруулсан — хүний баталгаажуулалт шаардлагатай</span>
                        </div>
                    </div>

                    <p v-if="!aiEnabled" class="py-6 text-center text-sm text-amber-700">
                        AI туслах идэвхгүй байна. Админ тохиргооноос асаана уу.
                    </p>
                    <p v-else-if="!providerReady" class="py-2 text-center text-xs text-amber-700">
                        OpenAI горим сонгосон боловч API түлхүүр алга — локал tool горимоор ажиллана.
                    </p>
                </div>

                <div class="space-y-3 border-t border-slate-100 bg-white p-4">
                    <div class="flex flex-wrap gap-2">
                        <span class="self-center text-xs font-medium text-slate-500">Хурдан асуулт:</span>
                        <button
                            v-for="s in suggestions"
                            :key="s.label"
                            type="button"
                            class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-brand-navy-50"
                            @click="ask(s.text)"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                    <form class="flex gap-2" @submit.prevent="ask()">
                        <input
                            v-model="form.message"
                            required
                            :disabled="!aiEnabled || form.processing"
                            placeholder="Асуултаа энд бичнэ үү…"
                            class="ui-input flex-1"
                        />
                        <button class="ui-btn-primary" :disabled="!aiEnabled || form.processing">
                            {{ form.processing ? '…' : 'Илгээх' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
