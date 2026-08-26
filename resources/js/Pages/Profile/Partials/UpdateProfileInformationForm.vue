<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Профайлын мэдээлэл
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Таны бүртгэлийн мэдээлэл. Засварлах боломжгүй — зөвхөн харна.
            </p>
        </header>

        <div class="mt-6 space-y-6">
            <div>
                <InputLabel value="Нэр" />
                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                    {{ user.name || '—' }}
                </p>
            </div>

            <div>
                <InputLabel value="И-мэйл" />
                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                    {{ user.email || '—' }}
                </p>
                <p
                    v-if="mustVerifyEmail && user.email_verified_at === null"
                    class="mt-2 text-sm text-amber-700"
                >
                    И-мэйл хаяг баталгаажаагүй байна.
                </p>
            </div>

            <div>
                <InputLabel value="Утасны дугаар" />
                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800">
                    {{ user.phone || '—' }}
                </p>
            </div>
        </div>
    </section>
</template>
