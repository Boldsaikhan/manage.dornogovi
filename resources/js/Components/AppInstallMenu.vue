<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';

/**
 * Толгой дээрх «Програм» цэс.
 *
 * Ширээний програм (Manage Dornogovi.exe) суулгаад түүгээрээ ороход
 * холбосон системүүд рүү автоматаар нэвтэрнэ. Суулгаагүй бол icon
 * улаан болж сануулна. Гар утсан дээр харагдахгүй.
 */
const open = ref(false);
const root = ref(null);
const onPhone = ref(false);

/** Ширээний програм дотор ажиллаж байгаа эсэх. */
const inDesktopApp = ref(false);
const checked = ref(false);

const verify = () => {
    inDesktopApp.value = window.manageDesktop?.version !== undefined
        || document.documentElement.dataset.mdDesktop === '1';
    checked.value = true;
};

const appMissing = computed(() => checked.value && ! inDesktopApp.value);

/** Серверт суулгац байршсан эсэх. */
const page = usePage();
const installer = computed(() => page.props.desktopApp ?? { available: false, canUpload: false });
const installerSize = computed(() => {
    const bytes = installer.value.size;

    return bytes ? `${(bytes / 1024 / 1024).toFixed(1)} МБ` : '';
});

const uploading = ref(false);
const uploadError = ref('');
const progress = ref(0);
const fileInput = ref(null);

const pickInstaller = () => fileInput.value?.click();

/** Суулгац ~80 МБ тул nginx/PHP-ийн хязгаарт багтаан хэсэгчлэн илгээнэ. */
const CHUNK_BYTES = 4 * 1024 * 1024;

const randomId = () => Array.from(
    crypto.getRandomValues(new Uint8Array(12)),
    (b) => b.toString(16).padStart(2, '0'),
).join('');

const sendChunk = async (file, uploadId, index, total) => {
    const body = new FormData();

    body.append('chunk', file.slice(index * CHUNK_BYTES, (index + 1) * CHUNK_BYTES), 'part');
    body.append('upload_id', uploadId);
    body.append('index', String(index));
    body.append('total', String(total));

    const response = await fetch(route('desktop.upload'), {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
    });

    if (! response.ok) {
        const payload = await response.json().catch(() => ({}));

        throw new Error(payload.message || `Сервер ${response.status} гэж хариулав.`);
    }

    return response.json();
};

const onInstallerPicked = async (event) => {
    const file = event.target.files?.[0];

    event.target.value = '';

    if (! file) {
        return;
    }

    if (! /\.exe$/i.test(file.name)) {
        uploadError.value = 'Зөвхөн .exe файл байршуулна.';

        return;
    }

    uploadError.value = '';
    progress.value = 0;
    uploading.value = true;

    const uploadId = randomId();
    const total = Math.max(1, Math.ceil(file.size / CHUNK_BYTES));

    try {
        for (let index = 0; index < total; index += 1) {
            // Сервер хэсгүүдийг дараалан залгадаг тул зэрэг илгээхгүй.
            // eslint-disable-next-line no-await-in-loop
            await sendChunk(file, uploadId, index, total);

            progress.value = Math.round(((index + 1) / total) * 100);
        }

        router.reload({ only: ['desktopApp'] });
    } catch (error) {
        uploadError.value = error.message || 'Байршуулахад алдаа гарлаа.';
    } finally {
        uploading.value = false;
    }
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

onMounted(() => {
    onPhone.value = isMobileDevice();

    if (onPhone.value) {
        return;
    }

    verify();
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onEscape);
    window.addEventListener('focus', verify);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onEscape);
    window.removeEventListener('focus', verify);
});

const toggle = () => {
    verify();
    open.value = ! open.value;
};
</script>

<template>
    <div v-if="! onPhone" ref="root" class="relative">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition"
            :class="appMissing
                ? 'border-rose-400 bg-rose-50 text-rose-600 hover:border-rose-500 hover:bg-rose-100'
                : open
                    ? 'border-brand-navy-300 bg-brand-navy-50 text-brand-navy-700'
                    : 'border-slate-200 bg-white text-brand-navy-700 hover:border-brand-navy-200 hover:bg-brand-navy-50'"
            :title="appMissing ? 'Ширээний програм суугаагүй — дарж татна уу' : 'Ширээний програм'"
            :aria-label="appMissing ? 'Програм суугаагүй' : 'Ширээний програм'"
            @click.stop="toggle"
        >
            <!-- Дэлгэц — ширээний програм -->
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zM9 20h6M12 16v4" />
            </svg>
            <span
                v-if="appMissing"
                class="absolute -right-0.5 -top-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-rose-600 text-[8px] font-bold text-white ring-2 ring-white"
            >
                !
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
                class="absolute right-0 z-50 mt-2 w-[min(92vw,20rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                @click.stop
            >
                <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2">
                    <p class="text-sm font-semibold text-slate-800">Ширээний програм</p>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Хаах"
                        @click="open = false"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-3 p-3">
                    <p v-if="inDesktopApp" class="rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
                        Ширээний програмаар орсон байна. Холбосон системүүд рүү автоматаар нэвтэрнэ.
                    </p>
                    <p v-else class="text-xs leading-relaxed text-slate-600">
                        Windows програмыг суулгавал холбосон системүүд рүү
                        нэр, нууц үгээ бичихгүйгээр шууд нэвтэрнэ.
                    </p>

                    <a
                        v-if="installer.available"
                        :href="route('desktop.download')"
                        class="ui-btn-primary w-full justify-center !py-2 text-xs"
                        download
                    >
                        Програм татах (Windows)
                        <span v-if="installerSize" class="ml-1 opacity-70">· {{ installerSize }}</span>
                    </a>

                    <button
                        v-else
                        type="button"
                        class="ui-btn-primary w-full cursor-not-allowed justify-center !py-2 text-xs opacity-50"
                        disabled
                    >
                        Програм татах (Windows)
                    </button>

                    <p
                        v-if="! installer.available"
                        class="rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800"
                    >
                        Суулгацыг сервер дээр хараахан байршуулаагүй байна.
                        <template v-if="! installer.canUpload">Системийн админд хандана уу.</template>
                    </p>

                    <div v-if="installer.canUpload" class="space-y-1">
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".exe,application/octet-stream"
                            class="hidden"
                            @change="onInstallerPicked"
                        >
                        <button
                            type="button"
                            class="ui-btn-ghost w-full justify-center !py-2 text-xs"
                            :disabled="uploading"
                            @click="pickInstaller"
                        >
                            {{ uploading ? `Байршуулж байна… ${progress}%` : (installer.available ? 'Суулгацыг шинэчлэх' : 'Суулгац байршуулах') }}
                        </button>
                        <p v-if="uploadError" class="text-xs text-rose-600">{{ uploadError }}</p>
                    </div>

                    <ol class="list-decimal space-y-1 rounded-lg bg-slate-50 px-3 py-2 pl-5 text-xs leading-relaxed text-slate-600">
                        <li>Татсан <b>Manage-Dornogovi-Setup.exe</b> файлыг ажиллуулна.</li>
                        <li>
                            Windows «<b>Windows protected your PC</b>» гэж сануулбал
                            <b>More info</b> → <b>Run anyway</b> дарна.
                        </li>
                        <li>Суулгацын тайлбарыг уншаад <b>Install</b> дарж дуустал хүлээнэ.</li>
                        <li>Ширээн дээр үүссэн <b>Manage Dornogovi</b> товчлолоор орно.</li>
                        <li>«Холбосон системүүд» цэсэнд нэвтрэх мэдээллээ нэг удаа хадгална.</li>
                    </ol>

                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-800">
                        Програм нь гарын үсэг (code signing) гэрчилгээгүй тул Windows
                        анх удаа сануулга өгдөг. Энэ нь вирус гэсэн үг биш — суулгац
                        манай системээс татагдаж байна.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>
