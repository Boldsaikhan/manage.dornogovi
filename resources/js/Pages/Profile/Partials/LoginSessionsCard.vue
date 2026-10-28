<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
});

const busy = ref(null);

/** Нэг төхөөрөмжийн холболтыг тасална. */
const revoke = (session) => {
    if (! confirm('Энэ төхөөрөмжийн холболтыг таслах уу? Дахин нэвтрэх шаардлагатай болно.')) {
        return;
    }

    busy.value = session.id;
    router.delete(route('profile.sessions.destroy', session.id), {
        preserveScroll: true,
        onFinish: () => { busy.value = null; },
    });
};

/** Бусад бүх холболтыг тасална. */
const revokeOthers = () => {
    if (! confirm('Энэ төхөөрөмжөөс бусад бүх холболтыг таслах уу?')) {
        return;
    }

    busy.value = 'others';
    router.delete(route('profile.sessions.destroy-others'), {
        preserveScroll: true,
        onFinish: () => { busy.value = null; },
    });
};

const deviceIcon = (device) => {
    if (device === 'iPhone' || device === 'Android') {
        return 'M8 3.5h8a1.5 1.5 0 011.5 1.5v14a1.5 1.5 0 01-1.5 1.5H8A1.5 1.5 0 016.5 19V5A1.5 1.5 0 018 3.5zM11 17.5h2';
    }

    if (device === 'iPad') {
        return 'M6.5 3.5h11A1.5 1.5 0 0119 5v14a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 015 19V5a1.5 1.5 0 011.5-1.5z';
    }

    return 'M4 5.5h16v10H4zM2 19.5h20M9.5 15.5l-.5 4M14.5 15.5l.5 4';
};

const hasOthers = () => props.sessions.some((s) => ! s.is_current);
</script>

<template>
    <section class="ui-card-pad space-y-4">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="ui-title text-base">Нэвтэрсэн төхөөрөмж</h3>
                <p class="ui-subtitle mt-1">
                    Таны бүртгэлээр одоо нээлттэй байгаа холболтууд. Танихгүй
                    төхөөрөмж байвал холболтыг нь таслаад нууц үгээ солино уу.
                </p>
            </div>

            <button
                v-if="hasOthers()"
                type="button"
                class="ui-btn-ghost text-red-600"
                :disabled="busy === 'others'"
                @click="revokeOthers"
            >
                Бусдыг нь салгах
            </button>
        </header>

        <p v-if="! sessions.length" class="text-sm text-slate-500">
            Нээлттэй холболт олдсонгүй.
        </p>

        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="session in sessions"
                :key="session.id"
                class="flex flex-wrap items-center gap-3 py-2.5"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                    :class="session.is_current
                        ? 'bg-emerald-50 text-emerald-600'
                        : 'bg-slate-100 text-slate-500'"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                        <path :d="deviceIcon(session.device)" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-1.5">
                        <span class="text-sm font-semibold text-brand-navy-800">
                            {{ session.device }} · {{ session.browser }}
                        </span>
                        <span
                            v-if="session.is_current"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-700"
                        >
                            Энэ төхөөрөмж
                        </span>
                    </span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        IP {{ session.ip }} · {{ session.last_active }}
                        <span class="text-slate-400">({{ session.last_active_at }})</span>
                    </span>
                </span>

                <button
                    v-if="! session.is_current"
                    type="button"
                    class="ui-icon-btn ui-icon-btn--danger"
                    :disabled="busy === session.id"
                    title="Энэ холболтыг таслах"
                    aria-label="Холболт таслах"
                    @click="revoke(session)"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5L8.5 15.5M8.5 8.5l7 7" />
                        <circle cx="12" cy="12" r="8.5" stroke-width="1.5" />
                    </svg>
                </button>
            </li>
        </ul>
    </section>
</template>
