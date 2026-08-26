<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Web Push — албан хаагчид холбоотой мэдээллийг төхөөрөмж рүү мэдэгдэнэ.
 */
const page = usePage();
const status = ref('idle'); // idle | denied | subscribed | unsupported | error
const message = ref('');
const busy = ref(false);

const webPush = computed(() => page.props.webPush ?? { enabled: false, publicKey: null, subscribed: false });
const supported = computed(() => typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window);

const label = computed(() => {
    if (! webPush.value.enabled) return 'Тохиргоо хийгдээгүй';
    if (! supported.value) return 'Дэмжихгүй хөтөч';
    if (status.value === 'subscribed') return 'асаалттай';
    if (status.value === 'denied') return 'хориглосон';
    return 'унтраастай';
});

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
};

const currentSubscription = async () => {
    const reg = await navigator.serviceWorker.ready;

    return reg.pushManager.getSubscription();
};

const syncStatus = async () => {
    if (! supported.value) {
        status.value = 'unsupported';

        return;
    }

    if (Notification.permission === 'denied') {
        status.value = 'denied';

        return;
    }

    try {
        const sub = await currentSubscription();
        status.value = sub ? 'subscribed' : 'idle';
    } catch {
        status.value = 'idle';
    }
};

const enable = async () => {
    if (! webPush.value.enabled || ! webPush.value.publicKey) {
        message.value = 'Серверийн push тохиргоо дутуу байна.';

        return;
    }

    busy.value = true;
    message.value = '';

    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            status.value = 'denied';
            message.value = 'Мэдэгдэлийн зөвшөөрөл өгөөгүй байна. Хөтчийн тохиргооноос зөвшөөрнө үү.';

            return;
        }

        const reg = await navigator.serviceWorker.ready;
        let sub = await reg.pushManager.getSubscription();

        if (! sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(webPush.value.publicKey),
            });
        }

        const json = sub.toJSON();
        await window.axios.post(route('push.subscribe'), {
            endpoint: json.endpoint,
            keys: json.keys,
            contentEncoding: (PushManager.supportedContentEncodings && PushManager.supportedContentEncodings[0]) || 'aesgcm',
        });

        status.value = 'subscribed';
        message.value = 'Мэдэгдэл асаалттай. Танд холбоотой бүртгэл гарахад төхөөрөмж рүү ирнэ.';
    } catch (e) {
        status.value = 'error';
        message.value = e?.response?.data?.message || e?.message || 'Идэвхжүүлж чадсангүй.';
    } finally {
        busy.value = false;
    }
};

const disable = async () => {
    busy.value = true;
    message.value = '';

    try {
        const sub = await currentSubscription();
        if (sub) {
            await window.axios.delete(route('push.unsubscribe'), {
                data: { endpoint: sub.endpoint },
            });
            await sub.unsubscribe();
        }
        status.value = 'idle';
        message.value = 'Мэдэгдэл унтраалаа.';
    } catch (e) {
        message.value = e?.message || 'Унтрааж чадсангүй.';
    } finally {
        busy.value = false;
    }
};

onMounted(() => {
    if (webPush.value.subscribed) {
        status.value = 'subscribed';
    }
    syncStatus();
});
</script>

<template>
    <div
        class="rounded-2xl border p-4"
        :class="status === 'subscribed' ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200'"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-800">Push мэдэгдэл</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    Чөлөө, үүрэг, томилолт, бланк зэрэг <b>танд холбоотой</b> мэдээллийг
                    утас/компьютер рүү шууд мэдэгдэнэ.
                </p>
            </div>
            <span
                class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="status === 'subscribed'
                    ? 'bg-emerald-100 text-emerald-700'
                    : status === 'denied'
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-slate-100 text-slate-600'"
            >
                {{ label }}
            </span>
        </div>

        <div v-if="message" class="mt-2 rounded-xl bg-white/80 px-3 py-2 text-xs text-slate-600">
            {{ message }}
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                v-if="status !== 'subscribed'"
                type="button"
                class="ui-btn-primary !py-1.5 text-xs"
                :disabled="busy || ! webPush.enabled || status === 'unsupported'"
                @click="enable"
            >
                {{ busy ? 'Идэвхжүүлж байна…' : 'Мэдэгдэл асаах' }}
            </button>
            <button
                v-else
                type="button"
                class="ui-btn-ghost !py-1.5 text-xs"
                :disabled="busy"
                @click="disable"
            >
                Унтраах
            </button>
        </div>
    </div>
</template>
