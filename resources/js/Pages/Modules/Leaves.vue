<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    activeScope: { type: String, default: 'baiguullaga' },
    tabs: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    directory: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    types: { type: Object, default: () => ({}) },
    signers: { type: Object, default: () => ({}) },
});

const showForm = ref(false);
const previewCopies = ref(6);
const actingName = ref('М.МӨНХБАТ');

const form = useForm({
    scope: props.activeScope === 'all' ? 'baiguullaga' : props.activeScope,
    org_name: '',
    person_name: '',
    slip_number: '',
    signer: 'acting',
    type: 'tsalintai',
    start_date: new Date().toISOString().slice(0, 10),
    days: 1,
    reason: '',
    status: 'approved',
});

const typeEntries = computed(() => Object.entries(props.types));
const signerEntries = computed(() => Object.entries(props.signers));

const directoryForScope = computed(() => {
    if (!form.scope) return props.directory;
    return props.directory.filter((d) => d.category === form.scope);
});

const orgOptions = computed(() => directoryForScope.value.map((d) => d.org_name));

const peopleOptions = computed(() => {
    if (!form.org_name) return [];
    return directoryForScope.value.find((d) => d.org_name === form.org_name)?.people ?? [];
});

const unitFromOrg = (name) => {
    const text = String(name || '').trim();
    return text.replace(/\s*хэлт(эс|сийн)$/iu, '').trim();
};

watch(
    () => form.scope,
    () => {
        if (form.org_name && !orgOptions.value.includes(form.org_name)) {
            form.org_name = '';
            form.person_name = '';
        }
    },
);

watch(
    () => form.org_name,
    () => {
        const names = peopleOptions.value.map((p) => p.name);
        if (form.person_name && !names.includes(form.person_name)) {
            form.person_name = '';
        }
    },
);

const switchScope = (value) => {
    router.get(route('leaves.index'), { scope: value }, { preserveState: false, preserveScroll: true });
};

const openForm = () => {
    form.reset();
    form.clearErrors();
    form.scope = props.activeScope === 'all' ? 'baiguullaga' : props.activeScope;
    form.signer = 'acting';
    form.type = 'tsalintai';
    form.start_date = new Date().toISOString().slice(0, 10);
    form.days = 1;
    form.status = 'approved';
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
};

const submit = () => {
    form.post(route('leaves.store'), {
        preserveScroll: true,
        onSuccess: () => closeForm(),
    });
};

const destroyRow = (id) => {
    if (!confirm('Устгах уу?')) return;
    router.delete(route('leaves.destroy', id), { preserveScroll: true });
};

const slipPrintUrl = (row) => {
    const base = row.slip_url || route('leaves.slip', row.id);
    const params = new URLSearchParams({
        copies: String(previewCopies.value),
        signer: row.signer || 'acting',
        name: actingName.value,
    });
    return `${base}?${params.toString()}`;
};

/** Жагсаалтыг A4 хуудас бүрт 6 ширхэгээр хуваана. */
const pages = computed(() => {
    const size = 6;
    const chunks = [];
    for (let i = 0; i < props.rows.length; i += size) {
        const slice = props.rows.slice(i, i + size);
        while (slice.length < size && props.rows.length > 0) {
            // Сүүлийн хуудсыг бүрэн дүүргэхгүй — зөвхөн бодит мөрүүд
            break;
        }
        chunks.push(slice);
    }
    return chunks;
});

const kindLabel = (key) => ({
    tsalintai: 'цалинтай',
    tsalingui: 'цалингүй',
    eeljiin: 'ээлжийн амралтаас',
}[key] || key);

const emptyMessage = computed(() => {
    const map = {
        all: 'Бүртгэл алга',
        agentlag: 'Агентлагийн бүртгэл алга',
        sum: 'Сумын бүртгэл алга',
        baiguullaga: 'Байгууллагын бүртгэл алга',
    };
    return map[props.activeScope] || 'Бүртгэл алга';
});
</script>

<template>
    <AuthenticatedLayout title="Чөлөөний бүртгэл">
        <div class="ui-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="ui-title">Чөлөөний бүртгэл</h2>
                    <p class="ui-subtitle">
                        Албан хаагчдын чөлөөний хуудсыг загварын дагуу бөглөж, A4 цаасанд 6 ширхэгээр харна.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <span>Хэвлэх:</span>
                        <select v-model.number="previewCopies" class="ui-input w-20 py-1.5">
                            <option :value="1">1</option>
                            <option :value="2">2</option>
                            <option :value="4">4</option>
                            <option :value="6">6</option>
                        </select>
                    </label>
                    <button
                        v-if="canManage"
                        type="button"
                        class="ui-btn-accent"
                        @click="openForm"
                    >
                        Шинэ нэмэх
                    </button>
                </div>
            </div>

            <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-soft">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="activeScope === item.value
                        ? 'bg-brand-navy-600 text-white shadow-md shadow-brand-navy-600/20'
                        : 'text-slate-600 hover:bg-slate-50'"
                    @click="switchScope(item.value)"
                >
                    {{ item.label }}
                    <span class="ml-1 text-xs opacity-70">{{ item.count }}</span>
                </button>
            </nav>

            <div v-if="!rows.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center text-slate-500">
                {{ emptyMessage }}
            </div>

            <div v-else class="space-y-8">
                <section
                    v-for="(pageRows, pageIndex) in pages"
                    :key="pageIndex"
                    class="leave-a4 mx-auto overflow-hidden bg-white shadow-soft"
                >
                    <div class="leave-a4-grid">
                        <article
                            v-for="row in pageRows"
                            :key="row.id"
                            class="leave-slip group relative"
                        >
                            <h3 class="leave-slip-title">ЧӨЛӨӨНИЙ ХУУДАС</h3>
                            <p class="leave-slip-no">
                                № <span class="leave-blank">{{ row.slip_number || row.id }}</span>
                            </p>
                            <p class="leave-slip-body">
                                Аймгийн ЗДТГ-ын
                                <span class="leave-blank">{{ row.unit || unitFromOrg(row.org_name) }}</span>
                                хэлтсийн мэргэжилтэн
                                <span class="leave-blank">{{ row.person_name }}</span>
                                нь
                                <span class="leave-blank">{{ row.reason || '………………' }}</span>
                                үндэслэлээр
                                {{ row.year || '____' }} оны
                                <span class="leave-blank">{{ row.month || '…' }}</span>
                                сарын
                                <span class="leave-blank">{{ row.day || '…' }}</span>-ны өдрөөс
                                ажлын
                                <span class="leave-blank">{{ row.days || '…' }}</span>
                                өдрийн чөлөө /
                                <span :class="{ 'underline decoration-black': row.type === 'tsalintai' }">{{ kindLabel('tsalintai') }}</span>,
                                <span :class="{ 'underline decoration-black': row.type === 'tsalingui' }">{{ kindLabel('tsalingui') }}</span>,
                                <span :class="{ 'underline decoration-black': row.type === 'eeljiin' }">{{ kindLabel('eeljiin') }}</span>
                                / олгов.
                            </p>
                            <div class="leave-slip-sign">
                                <span class="leave-sign-title">
                                    <template v-if="row.signer === 'head'">Хэлтсийн дарга</template>
                                    <template v-else>Даргын албан үүргийг түр орлон гүйцэтгэгч</template>
                                </span>
                                <span class="leave-sign-name">
                                    <template v-if="row.signer === 'head'">/ &nbsp;&nbsp;&nbsp;&nbsp; /</template>
                                    <template v-else>{{ actingName }}</template>
                                </span>
                            </div>

                            <div class="absolute right-1 top-1 flex gap-1 opacity-0 transition group-hover:opacity-100">
                                <a
                                    :href="slipPrintUrl(row)"
                                    target="_blank"
                                    class="rounded bg-brand-navy-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                >
                                    Хэвлэх
                                </a>
                                <button
                                    v-if="canManage"
                                    type="button"
                                    class="rounded bg-rose-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                    @click="destroyRow(row.id)"
                                >
                                    Устгах
                                </button>
                            </div>
                        </article>
                    </div>
                    <p class="border-t border-slate-100 px-3 py-1.5 text-center text-[11px] text-slate-400">
                        A4 · хуудас {{ pageIndex + 1 }} · {{ pageRows.length }}/6
                    </p>
                </section>
            </div>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="closeForm">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900">Чөлөөний хуудас нэмэх</h3>
                <p class="mt-1 text-sm text-slate-500">Доорх талбарууд хэвлэх загварын хоёр хүснэгтэд нийцнэ.</p>

                <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Хамрах хүрээ</span>
                        <select v-model="form.scope" class="ui-input" required>
                            <option value="agentlag">Агентлаг</option>
                            <option value="sum">Сумд</option>
                            <option value="baiguullaga">Байгууллага</option>
                        </select>
                        <InputError :message="form.errors.scope" class="mt-1" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Гарын үсэг (загвар)</span>
                        <select v-model="form.signer" class="ui-input" required>
                            <option v-for="[value, label] in signerEntries" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.signer" class="mt-1" />
                    </label>

                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block font-medium text-slate-700">Хэлтэс / байгууллага</span>
                        <select v-if="orgOptions.length" v-model="form.org_name" class="ui-input" required>
                            <option value="" disabled>Сонгох…</option>
                            <option v-for="name in orgOptions" :key="name" :value="name">{{ name }}</option>
                        </select>
                        <input v-else v-model="form.org_name" class="ui-input" required placeholder="Хэлтсийн нэр" />
                        <InputError :message="form.errors.org_name" class="mt-1" />
                    </label>

                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block font-medium text-slate-700">Мэргэжилтэн / албан хаагч</span>
                        <select v-if="peopleOptions.length" v-model="form.person_name" class="ui-input" required>
                            <option value="" disabled>Сонгох…</option>
                            <option v-for="p in peopleOptions" :key="p.name" :value="p.name">
                                {{ p.name }}{{ p.position ? ` — ${p.position}` : '' }}
                            </option>
                        </select>
                        <input v-else v-model="form.person_name" class="ui-input" required placeholder="Овог нэр" />
                        <InputError :message="form.errors.person_name" class="mt-1" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Хуудасны №</span>
                        <input v-model="form.slip_number" class="ui-input" placeholder="Жишээ: 12" />
                        <InputError :message="form.errors.slip_number" class="mt-1" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Төрөл (доогуур зурах)</span>
                        <select v-model="form.type" class="ui-input" required>
                            <option v-for="[value, label] in typeEntries" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Эхлэх өдөр</span>
                        <input v-model="form.start_date" type="date" class="ui-input" required />
                        <InputError :message="form.errors.start_date" class="mt-1" />
                    </label>

                    <label class="block text-sm">
                        <span class="mb-1 block font-medium text-slate-700">Ажлын өдөр</span>
                        <input v-model.number="form.days" type="number" min="1" max="365" class="ui-input" required />
                        <InputError :message="form.errors.days" class="mt-1" />
                    </label>

                    <label class="block text-sm sm:col-span-2">
                        <span class="mb-1 block font-medium text-slate-700">Үндэслэл</span>
                        <textarea v-model="form.reason" rows="2" class="ui-input" placeholder="Үндэслэл…" />
                        <InputError :message="form.errors.reason" class="mt-1" />
                    </label>

                    <label v-if="form.signer === 'acting'" class="block text-sm sm:col-span-2">
                        <span class="mb-1 block font-medium text-slate-700">Үүрэг гүйцэтгэгчийн нэр (харагдах)</span>
                        <input v-model="actingName" class="ui-input" />
                    </label>

                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" class="ui-btn-ghost" @click="closeForm">Болих</button>
                        <button type="submit" class="ui-btn-accent" :disabled="form.processing">Хадгалах</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* A4 харьцаа — дэлгэц дээр 6 ширхэг (2×3) багтана. */
.leave-a4 {
    width: min(100%, 210mm);
    aspect-ratio: 210 / 297;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    display: flex;
    flex-direction: column;
}

.leave-a4-grid {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: repeat(3, 1fr);
    gap: 2.5mm 3mm;
    padding: 8mm 7mm 4mm;
    min-height: 0;
}

.leave-slip {
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    padding: 1mm 0.5mm;
    font-family: Arial, "Times New Roman", sans-serif;
    color: #0f172a;
}

.leave-slip-title {
    margin: 0;
    text-align: center;
    font-size: clamp(8px, 1.05vw, 11px);
    font-weight: 700;
    letter-spacing: 0.02em;
}

.leave-slip-no {
    margin: 0.6mm 0 1.2mm;
    text-align: center;
    font-size: clamp(7px, 0.95vw, 10px);
}

.leave-slip-body {
    margin: 0;
    flex: 1;
    text-align: justify;
    font-size: clamp(6.5px, 0.85vw, 9.5px);
    line-height: 1.45;
}

.leave-blank {
    display: inline;
    border-bottom: 1px dotted #334155;
    padding: 0 1px;
}

.leave-slip-sign {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 2mm;
    margin-top: 1.5mm;
    font-size: clamp(6px, 0.8vw, 9px);
    line-height: 1.2;
    text-transform: uppercase;
}

.leave-sign-title {
    max-width: 62%;
}

.leave-sign-name {
    text-transform: none;
    white-space: nowrap;
}

@media (max-width: 640px) {
    .leave-a4 {
        aspect-ratio: auto;
        min-height: 520px;
    }

    .leave-a4-grid {
        grid-template-columns: 1fr;
        grid-template-rows: none;
        gap: 8px;
    }
}
</style>
