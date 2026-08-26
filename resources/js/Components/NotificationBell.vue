<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import PushSubscribe from '@/Components/PushSubscribe.vue';
import {
    addNotification,
    clearNotifications,
    loadNotifications,
    markAllRead,
    markOneRead,
    unreadCount,
} from '@/utils/notificationInbox';

/**
 * Толгой bell — ирсэн push мэдэгдлийг icon + тоогоор харуулна.
 */
const page = usePage();
const open = ref(false);
const root = ref(null);
const items = ref(loadNotifications());

const unread = computed(() => unreadCount(items.value));
const pushConfigured = computed(() => !! page.props.webPush?.enabled);

const refresh = () => {
    items.value = loadNotifications();
};

const onSwMessage = (event) => {
    if (event.data?.type !== 'PUSH_NOTIFICATION') {
        return;
    }

    addNotification(event.data.payload || {});
    refresh();
};

const onOutside = (event) => {
    if (open.value && root.value && ! root.value.contains(event.target)) {
        open.value = false;
    }
};

const onEscape = (event) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

const toggle = () => {
    open.value = ! open.value;
    if (open.value) {
        refresh();
    }
};

const openItem = (item) => {
    items.value = markOneRead(item.id, items.value);
    open.value = false;
    router.visit(item.url || '/dept-dashboard');
};

const markRead = () => {
    items.value = markAllRead(items.value);
};

const clearAll = () => {
    items.value = clearNotifications();
};

const formatTime = (iso) => {
    try {
        const d = new Date(iso);

        return d.toLocaleString('mn-MN', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
};

onMounted(() => {
    refresh();
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', onSwMessage);
    }
    window.addEventListener('storage', refresh);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.removeEventListener('message', onSwMessage);
    }
    window.removeEventListener('storage', refresh);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition"
            :class="unread
                ? 'border-brand-orange-400 bg-brand-orange-50 text-brand-orange-700 hover:bg-brand-orange-100'
                : open
                    ? 'border-brand-navy-300 bg-brand-navy-50 text-brand-navy-700'
                    : 'border-slate-200 bg-white text-brand-navy-700 hover:border-brand-navy-200 hover:bg-brand-navy-50'"
            title="Мэдэгдэл"
            aria-label="Мэдэгдэл"
            @click.stop="toggle"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path
                    d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
            <span
                v-if="unread"
                class="absolute -right-1 -top-1 flex min-w-[1.1rem] items-center justify-center rounded-full bg-brand-orange-500 px-1 text-[10px] font-bold leading-4 text-white ring-2 ring-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 z-40 mt-2 w-[min(92vw,22rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2.5">
                    <p class="text-sm font-semibold text-slate-800">Мэдэгдэл</p>
                    <div class="flex gap-2">
                        <button
                            v-if="unread"
                            type="button"
                            class="text-[11px] font-medium text-brand-navy-600 hover:underline"
                            @click="markRead"
                        >
                            Уншсан
                        </button>
                        <button
                            v-if="items.length"
                            type="button"
                            class="text-[11px] font-medium text-slate-500 hover:underline"
                            @click="clearAll"
                        >
                            Цэвэрлэх
                        </button>
                    </div>
                </div>

                <ul v-if="items.length" class="max-h-80 overflow-y-auto divide-y divide-slate-50">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            class="flex w-full flex-col gap-0.5 px-3 py-2.5 text-left transition hover:bg-slate-50"
                            :class="item.read ? 'opacity-70' : 'bg-brand-orange-50/40'"
                            @click="openItem(item)"
                        >
                            <span class="text-sm font-semibold text-slate-800">{{ item.title }}</span>
                            <span v-if="item.body" class="line-clamp-2 text-xs text-slate-500">{{ item.body }}</span>
                            <span class="text-[10px] text-slate-400">{{ formatTime(item.at) }}</span>
                        </button>
                    </li>
                </ul>
                <p v-else class="px-3 py-8 text-center text-sm text-slate-400">
                    Мэдэгдэл алга.
                </p>

                <div v-if="pushConfigured" class="border-t border-slate-100 p-2">
                    <PushSubscribe />
                </div>
                <p v-else class="border-t border-slate-100 px-3 py-2 text-[11px] text-slate-500">
                    Push тохиргоог админ «Системийн тохиргоо» хэсэгт хийнэ.
                </p>
            </div>
        </Transition>
    </div>
</template>
