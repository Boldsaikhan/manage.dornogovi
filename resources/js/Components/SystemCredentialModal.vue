<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import VaultUnlockForm from '@/Components/VaultUnlockForm.vue';

/**
 * Холбосон системийн нэвтрэх тохиргоо — хажуугийн цэснээс жижиг цонхоор.
 *
 * Бүтэн хуудас (Systems/View) нь хадгалсан нууц үгийг харах зэрэг
 * нэмэлт боломжтой хэвээр үлдэнэ.
 */
const props = defineProps({
    show: { type: Boolean, default: false },
    system: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const page = usePage();
const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

const loading = ref(false);
const loadError = ref('');
const detail = ref(null);
const showPassword = ref(false);

const form = useForm({
    system_id: null,
    auth_type: 'password',
    username: '',
    password: '',
    remember_device: false,
});

const isDan = computed(() => form.auth_type === 'dan');
const saved = computed(() => Boolean(detail.value?.has_credential));

const load = async () => {
    if (! props.system) {
        return;
    }

    loading.value = true;
    loadError.value = '';
    detail.value = null;
    showPassword.value = false;
    form.clearErrors();

    try {
        const { data } = await window.axios.get(route('systems.credential', props.system.id));

        detail.value = data;
        form.system_id = data.id;
        form.auth_type = data.auth_type || 'password';
        form.username = data.saved_username || '';
        form.password = '';
        form.remember_device = Boolean(data.remember_device);
    } catch (e) {
        loadError.value = e?.response?.status === 403
            ? 'Энэ системд хандах эрхгүй байна.'
            : 'Мэдээллийг уншиж чадсангүй.';
    } finally {
        loading.value = false;
    }
};

watch(() => props.show, (open) => {
    if (open) {
        load();
    }
});

const save = () => {
    form.post(route('credentials.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password');
            emit('close');
        },
    });
};

const remove = () => {
    if (! window.confirm('Хадгалсан нэвтрэх мэдээллийг устгах уу?')) {
        return;
    }

    router.delete(route('credentials.destroy', props.system.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};
</script>

<template>
    <Modal :show="show" max-width="sm" @close="emit('close')">
        <div class="p-5">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="truncate text-base font-semibold text-brand-navy-900">
                        {{ system?.name }}
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">Нэвтрэх нэр, нууц үг</p>
                </div>
                <span
                    v-if="detail"
                    class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                    :class="saved ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                >
                    {{ saved ? 'Хадгалсан' : 'Хадгалаагүй' }}
                </span>
            </div>

            <p v-if="loading" class="py-6 text-center text-sm text-slate-500">Уншиж байна…</p>
            <p v-else-if="loadError" class="py-6 text-center text-sm text-rose-600">{{ loadError }}</p>

            <form v-else-if="detail" class="space-y-3" @submit.prevent="save">
                <div v-if="detail.supports_dan">
                    <label class="ui-label">Нэвтрэх арга</label>
                    <select v-model="form.auth_type" class="ui-input">
                        <option value="dan">ДАН-аар нэвтрэх</option>
                        <option value="password">Нэвтрэх нэр / нууц үг</option>
                    </select>
                </div>

                <div>
                    <label class="ui-label">{{ isDan ? 'Регистрийн дугаар' : 'Нэвтрэх нэр' }}</label>
                    <input
                        v-model="form.username"
                        type="text"
                        autocomplete="username"
                        required
                        class="ui-input"
                        :placeholder="isDan ? 'Жишээ: УХ98010112' : 'И-мэйл, утас эсвэл нэвтрэх нэр'"
                    >
                    <InputError :message="form.errors.username" class="mt-1" />
                </div>

                <div>
                    <label class="ui-label">{{ isDan ? 'ДАН нууц үг' : 'Нууц үг' }}</label>
                    <div class="flex gap-2">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :required="! saved"
                            class="ui-input flex-1"
                            :placeholder="saved ? 'Шинэчлэх бол дахин оруулна' : 'Нууц үг'"
                        >
                        <button
                            type="button"
                            class="ui-btn-ghost shrink-0 !px-3"
                            :title="showPassword ? 'Нуух' : 'Харах'"
                            @click="showPassword = ! showPassword"
                        >
                            {{ showPassword ? 'Нуух' : 'Харах' }}
                        </button>
                    </div>
                    <p v-if="saved && ! form.password" class="mt-1 text-xs text-slate-500">
                        Хоосон орхивол хуучин нууц үг хэвээр үлдэнэ.
                    </p>
                    <InputError :message="form.errors.password" class="mt-1" />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="form.remember_device"
                        type="checkbox"
                        class="rounded border-slate-300 text-brand-navy-600"
                    >
                    Энэ төхөөрөмжийг санах
                </label>

                <div
                    v-if="saved && ! vaultUnlocked"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                >
                    <p class="mb-2 text-xs text-slate-600">
                        Хадгалсан мэдээллээр нэвтрэхийн тулд сангаа нээнэ үү.
                    </p>
                    <VaultUnlockForm />
                </div>

                <div class="flex items-center justify-between gap-2 pt-1">
                    <button
                        v-if="saved"
                        type="button"
                        class="ui-btn-danger !px-3 !py-1.5 text-xs"
                        @click="remove"
                    >
                        Устгах
                    </button>
                    <span v-else></span>

                    <div class="flex gap-2">
                        <button type="button" class="ui-btn-ghost" @click="emit('close')">Болих</button>
                        <button type="submit" class="ui-btn-primary" :disabled="form.processing">
                            {{ form.processing ? 'Хадгалж байна…' : (saved ? 'Шинэчлэх' : 'Хадгалах') }}
                        </button>
                    </div>
                </div>

                <a
                    :href="route('systems.show', system.id)"
                    class="block pt-1 text-center text-xs text-brand-navy-600 hover:underline"
                >
                    Дэлгэрэнгүй тохиргоо
                </a>
            </form>
        </div>
    </Modal>
</template>
