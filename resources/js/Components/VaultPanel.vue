<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import VaultUnlockForm from '@/Components/VaultUnlockForm.vue';

/**
 * Нэвтрэх мэдээллийн сан — нээх, хаах, төлөв харуулах.
 *
 * Сан нь хэрэглэгч бүрт тусдаа: холбосон систем рүү хадгалсан нэр, нууц
 * үгээ ашиглахын тулд Manage-ийн нууц үгээрээ нээнэ.
 */
const page = usePage();

const unlocked = computed(() => page.props.vault?.unlocked ?? false);
const until = computed(() => page.props.vault?.until ?? null);

const untilText = computed(() => {
    if (! until.value) {
        return '';
    }

    const at = new Date(until.value);

    return Number.isNaN(at.getTime())
        ? ''
        : at.toLocaleTimeString('mn-MN', { hour: '2-digit', minute: '2-digit' });
});

const busy = ref(false);

const lock = async () => {
    if (busy.value) {
        return;
    }

    busy.value = true;

    try {
        await window.axios.post(route('vault.lock'));
        router.reload({ only: ['vault'] });
    } finally {
        busy.value = false;
    }
};
</script>

<template>
    <div class="max-w-xl space-y-4">
        <div
            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm"
            :class="unlocked
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-slate-200 bg-slate-50 text-slate-700'"
        >
            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    :d="unlocked
                        ? 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0'
                        : 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM16 11V7a4 4 0 00-8 0v4'"
                />
            </svg>
            <span>
                <b>{{ unlocked ? 'Сан нээлттэй' : 'Сан түгжээтэй' }}</b>
                <span v-if="unlocked && untilText" class="block text-xs opacity-80">
                    {{ untilText }} хүртэл нээлттэй байна.
                </span>
            </span>
        </div>

        <template v-if="unlocked">
            <p class="text-sm text-slate-600">
                Холбосон систем дээр дарахад хадгалсан нэр, нууц үгээр шууд нэвтэрнэ.
                Ажлаа дуусгасан бол санг хаая.
            </p>

            <button type="button" class="ui-btn-ghost" :disabled="busy" @click="lock">
                {{ busy ? 'Хааж байна…' : 'Сан хаах' }}
            </button>
        </template>

        <template v-else>
            <p class="text-sm text-slate-600">
                Холбосон систем рүү хадгалсан нэр, нууц үгээр нэвтрэхийн тулд санг нээнэ.
                Доор <b>Manage-ийн өөрийн нууц үгээ</b> бичээд «Сан нээх» дарна.
                Нэг удаа нээхэд тодорхой хугацаанд нээлттэй байж, системээс гарахад
                дахин түгжигдэнэ.
            </p>

            <VaultUnlockForm />
        </template>
    </div>
</template>
