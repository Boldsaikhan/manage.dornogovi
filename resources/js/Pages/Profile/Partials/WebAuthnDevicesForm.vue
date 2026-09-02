<script setup>
import { computed, ref } from 'vue';
import { markWebAuthnDevice } from '@/utils/pwaClient';
import { isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';

const props = defineProps({
    credentials: { type: Array, default: () => [] },
});

const supported = isWebAuthnSupported();
const busy = ref(false);
const message = ref('');
const error = ref('');
const list = ref([...props.credentials]);

const hasDevices = computed(() => list.value.length > 0);

const addDevice = async () => {
    if (! supported || busy.value) return;

    busy.value = true;
    message.value = '';
    error.value = '';

    try {
        const data = await registerBiometric();
        if (data?.credential) {
            list.value = [
                {
                    id: data.credential.id,
                    device_name: data.credential.device_name || 'Төхөөрөмж',
                    created_at: new Date().toLocaleString('mn-MN'),
                },
                ...list.value,
            ];
        }
        message.value = 'Хуруу / нүүрээр нэвтрэх идэвхжлээ. Дараагийн удаа нэвтрэх хуудаснаас ашиглана.';
        markWebAuthnDevice();
    } catch (e) {
        const msg = e?.response?.data?.message
            || e?.message
            || 'Бүртгэл амжилтгүй.';
        if (/NotAllowedError|abort|цуцла/i.test(String(msg))) {
            error.value = 'Үйлдэл цуцлагдлаа.';
        } else {
            error.value = msg;
        }
    } finally {
        busy.value = false;
    }
};

const removeDevice = async (id) => {
    if (busy.value) return;
    if (! confirm('Энэ төхөөрөмжийн биометрик нэвтрэлтийг устгах уу?')) return;

    busy.value = true;
    error.value = '';
    try {
        await window.axios.delete(route('webauthn.destroy', id));
        list.value = list.value.filter((c) => c.id !== id);
        message.value = 'Төхөөрөмж устгагдлаа.';
    } catch (e) {
        error.value = e?.response?.data?.message || 'Устгаж чадсангүй.';
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Хуруу / нүүрээр нэвтрэх
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Утсан дээрээ нэг удаа идэвхжүүлсний дараа нэвтрэх хуудаснаас Fingerprint эсвэл Face ID ашиглана.
                HTTPS шаардлагатай.
            </p>
        </header>

        <div class="mt-4 space-y-3">
            <p v-if="! supported" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Энэ төхөөрөмж/хөтөч биометрик нэвтрэлтийг дэмжихгүй эсвэл HTTPS биш байна.
            </p>

            <div v-if="message" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</div>
            <div v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

            <ul v-if="hasDevices" class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                <li
                    v-for="item in list"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                    <div>
                        <div class="font-medium text-slate-800">{{ item.device_name }}</div>
                        <div class="text-xs text-slate-400">{{ item.created_at }}</div>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-semibold text-rose-600 hover:underline"
                        :disabled="busy"
                        @click="removeDevice(item.id)"
                    >
                        Устгах
                    </button>
                </li>
            </ul>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-navy-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-navy-700 disabled:opacity-60"
                :disabled="! supported || busy"
                @click="addDevice"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.567 3-3.5S13.657 4 12 4 9 5.567 9 7.5 10.343 11 12 11z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 20c.8-3.2 2.9-5 5.5-5s4.7 1.8 5.5 5" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8.5c-.8.6-1.3 1.6-1.3 2.7 0 2.3 1.6 3.8 3.3 4.3M17 8.5c.8.6 1.3 1.6 1.3 2.7 0 2.3-1.6 3.8-3.3 4.3" />
                </svg>
                {{ busy ? 'Хүлээж байна…' : (hasDevices ? 'Өөр төхөөрөмж нэмэх' : 'Хуруу / нүүр идэвхжүүлэх') }}
            </button>
        </div>
    </section>
</template>
