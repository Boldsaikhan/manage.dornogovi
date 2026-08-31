<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const emit = defineEmits(['unlocked']);

const password = ref('');
const error = ref('');
const busy = ref(false);

const submit = async () => {
    if (! password.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = '';

    try {
        await window.axios.post(route('vault.unlock'), {
            account_password: password.value,
        });
        password.value = '';
        router.reload({
            only: ['vault'],
            onFinish: () => emit('unlocked'),
        });
    } catch (e) {
        error.value = e?.response?.data?.errors?.account_password?.[0]
            || 'Сан нээж чадсангүй.';
        busy.value = false;
    }
};
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <div>
            <label class="mb-1 block text-xs font-medium text-brand-navy-700">
                Manage-ийн нууц үг
            </label>
            <input
                v-model="password"
                type="password"
                autocomplete="current-password"
                required
                class="ui-input"
                placeholder="Сан нээхийн тулд нууц үгээ оруулна"
            />
            <InputError :message="error" class="mt-1" />
        </div>
        <button type="submit" class="ui-btn-primary" :disabled="busy">
            {{ busy ? 'Нээж байна…' : 'Сан нээх' }}
        </button>
    </form>
</template>
