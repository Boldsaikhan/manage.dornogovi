<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    open: { type: Boolean, default: false },
    name: { type: String, default: 'Manage AI' },
    href: { type: String, default: '/ai' },
});

const emit = defineEmits(['close']);

const messages = ref([]);
const conversationId = ref(null);
const remaining = ref(null);
const draft = ref('');
const loading = ref(false);
const sending = ref(false);
const error = ref('');
const box = ref(null);
const loaded = ref(false);

const suggestions = [
    'Хүлээгдэж буй чөлөөний хүсэлт хэд байна',
    'Өнөөдрийн хурлуудыг харуул',
    'Үүрэг даалгаврын биелэлтийг харуул',
];

const scrollToEnd = async () => {
    await nextTick();
    if (box.value) box.value.scrollTop = box.value.scrollHeight;
};

const load = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get('/ai/panel');
        messages.value = data.messages ?? [];
        conversationId.value = data.conversation_id ?? null;
        remaining.value = data.remaining ?? null;
        loaded.value = true;
        await scrollToEnd();
    } catch {
        error.value = 'Чатыг ачаалж чадсангүй.';
    } finally {
        loading.value = false;
    }
};

const send = async (preset) => {
    const text = (preset ?? draft.value).trim();
    if (!text || sending.value) return;

    sending.value = true;
    error.value = '';
    draft.value = '';
    messages.value = [...messages.value, { id: `tmp-${Date.now()}`, role: 'user', content: text }];
    await scrollToEnd();

    try {
        const { data } = await window.axios.post('/ai/panel/ask', {
            message: text,
            conversation_id: conversationId.value,
        });
        messages.value = data.messages ?? messages.value;
        conversationId.value = data.conversation_id ?? conversationId.value;
        remaining.value = data.remaining ?? remaining.value;
        await scrollToEnd();
    } catch {
        error.value = 'Хариу авахад алдаа гарлаа. Дахин оролдоно уу.';
    } finally {
        sending.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && !loaded.value) load();
        if (isOpen) scrollToEnd();
    },
    { immediate: true },
);

const hasMessages = computed(() => messages.value.length > 0);
</script>

<template>
    <!-- Жижиг дэлгэцэд самбар бүхэлдээ нээгдэх тул арын дэвсгэр -->
    <div
        v-if="open"
        class="fixed inset-0 z-40 bg-brand-navy-950/30 backdrop-blur-[2px] xl:hidden"
        @click="emit('close')"
    />

    <aside
        v-show="open"
        class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl sm:w-[24rem]"
    >
        <header class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand-navy-600 text-white">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path
                        d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3z"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-brand-navy-900">{{ name }}</p>
                <p v-if="remaining !== null" class="text-[11px] text-slate-500">
                    Өнөөдөр {{ remaining }} асуулт үлдсэн
                </p>
            </div>
            <Link :href="href" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Бүтэн дэлгэцээр нээх">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M15 3h6v6M10 14L21 3M21 14v5a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </Link>
            <button
                type="button"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                title="Хаах"
                @click="emit('close')"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                </svg>
            </button>
        </header>

        <div ref="box" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
            <p v-if="loading" class="text-center text-sm text-slate-400">Ачаалж байна…</p>

            <template v-if="hasMessages">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="flex"
                    :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-3.5 py-2 text-sm leading-relaxed"
                        :class="message.role === 'user'
                            ? 'bg-brand-navy-600 text-white'
                            : 'bg-slate-100 text-slate-700'"
                    >
                        {{ message.content }}
                    </div>
                </div>
            </template>

            <div v-else-if="!loading" class="space-y-3 pt-4 text-center">
                <p class="text-sm text-slate-500">Юу асуумаар байна?</p>
                <div class="flex flex-col gap-2">
                    <button
                        v-for="item in suggestions"
                        :key="item"
                        type="button"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-left text-sm text-slate-600 transition hover:border-brand-navy-300 hover:bg-brand-navy-50"
                        @click="send(item)"
                    >
                        {{ item }}
                    </button>
                </div>
            </div>

            <p v-if="sending" class="text-center text-xs text-slate-400">Бодож байна…</p>
            <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-center text-xs text-rose-600">{{ error }}</p>
        </div>

        <form class="border-t border-slate-100 p-3" @submit.prevent="send()">
            <div class="flex items-end gap-2">
                <textarea
                    v-model="draft"
                    rows="2"
                    placeholder="Асуултаа бичнэ үү…"
                    class="max-h-32 min-h-[2.75rem] flex-1 resize-y rounded-xl border-slate-200 text-sm focus:border-brand-navy-500 focus:ring-brand-navy-500/25"
                    @keydown.enter.exact.prevent="send()"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-brand-navy-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-navy-700 disabled:opacity-50"
                    :disabled="sending || !draft.trim()"
                >
                    Илгээх
                </button>
            </div>
        </form>
    </aside>
</template>
