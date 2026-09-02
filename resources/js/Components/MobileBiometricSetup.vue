<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';
import { dismissBioSetup, hasWebAuthnDeviceHint, isBioSetupDismissed, isStandalonePwa, markWebAuthnDevice } from '@/utils/pwaClient';
import { isWebAuthnSupported, registerBiometric } from '@/utils/webauthn';

const page = usePage();
const busy = ref(false);
const error = ref('');
const done = ref(false);
const dismissed = ref(isBioSetupDismissed());

const shouldShow = computed(() => {
    if (! isMobileDevice() || dismissed.value || done.value) {
        return false;
    }

    const user = page.props.auth?.user;
    if (! user) {
        return false;
    }

    const hasLocalWebAuthn = hasWebAuthnDeviceHint();

    return ! hasLocalWebAuthn && isWebAuthnSupported();
});

const enable = async () => {
    if (busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await registerBiometric();
        markWebAuthnDevice();
        done.value = true;
    } catch (e) {
        const msg = e?.response?.data?.message || e?.message || '';
        if (/NotAllowedError|AbortError/i.test(e?.name || '')) {
            error.value = 'Үйлдэл цуцлагдлаа.';
        } else {
            error.value = msg || 'Идэвхжүүлж чадсангүй.';
        }
    } finally {
        busy.value = false;
    }
};

const skip = () => {
    dismissBioSetup();
    dismissed.value = true;
};
</script>

<template>
    <div
        v-if="shouldShow"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-brand-navy-200 bg-white p-4 shadow-2xl sm:left-auto sm:right-6 sm:bottom-6 sm:max-w-sm sm:rounded-2xl sm:border"
    >
        <p class="text-sm font-semibold text-brand-navy-900">Хуруу / цараайгаар нэвтрэх</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-600">
            Энэ утсан дээр хуруу/царайгаар нэвтрэхийг идэвхжүүлнэ үү.
            {{ isStandalonePwa() ? 'Дараагийн удаа нууц үг асуухгүй.' : '' }}
        </p>
        <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
        <div class="mt-3 flex gap-2">
            <button
                type="button"
                class="ui-btn-primary flex-1 !py-2"
                :disabled="busy"
                @click="enable"
            >
                {{ busy ? 'Идэвхжүүлж байна…' : 'Идэвхжүүлэх' }}
            </button>
            <button type="button" class="ui-btn-ghost !py-2" @click="skip">Дараа</button>
        </div>
    </div>
</template>
