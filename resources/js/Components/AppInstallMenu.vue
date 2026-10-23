<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
                        v-if="! inDesktopApp"
                        :href="route('desktop.download')"
                        class="ui-btn-primary w-full justify-center !py-2 text-xs"
                    >
                        Програм татах (Windows)
                    </a>

                    <ol class="list-decimal space-y-1 rounded-lg bg-slate-50 px-3 py-2 pl-5 text-xs leading-relaxed text-slate-600">
                        <li>Татсан <b>Manage-Dornogovi-Setup.exe</b> файлыг ажиллуулна.</li>
                        <li>Ширээн дээр үүссэн <b>Manage Dornogovi</b> товчлолоор орно.</li>
                        <li>«Холбосон системүүд» цэсэнд нэвтрэх мэдээллээ нэг удаа хадгална.</li>
                    </ol>
                </div>
            </div>
        </Transition>
    </div>
</template>
