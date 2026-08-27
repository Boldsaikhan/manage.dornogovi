<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { isMobileDevice } from '@/utils/mobileClient';
import { qrScanTarget } from '@/utils/qrScanTarget';

/**
 * Утасны камераар нэвтрэх / холбосон систем (сан нээх) QR уншина.
 * Зөвхөн гар утас дээр харагдана.
 */
const visible = ref(false);
const scanning = ref(false);
const error = ref('');
const video = ref(null);

let stream = null;
let raf = 0;
let closed = false;
let detector = null;
let jsQR = null;
let scanCanvas = null;
let scanCtx = null;

onMounted(() => {
    visible.value = isMobileDevice();
});

const stopCamera = () => {
    closed = true;
    cancelAnimationFrame(raf);
    raf = 0;

    if (stream) {
        stream.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    if (video.value) {
        video.value.srcObject = null;
    }
};

const close = () => {
    stopCamera();
    scanning.value = false;
};

const handleResult = (text) => {
    const target = qrScanTarget(text);

    if (! target) {
        error.value = 'Энэ QR нь нэвтрэх эсвэл систем нээх код биш байна.';

        return false;
    }

    close();
    router.visit(target);

    return true;
};

const detectLoop = async () => {
    if (closed || ! video.value) {
        return;
    }

    const el = video.value;

    if (el.readyState >= 2 && el.videoWidth) {
        try {
            if (detector) {
                const codes = await detector.detect(el);
                const value = codes[0]?.rawValue;

                if (value && handleResult(value)) {
                    return;
                }
            } else if (jsQR) {
                if (! scanCanvas) {
                    scanCanvas = document.createElement('canvas');
                    scanCtx = scanCanvas.getContext('2d', { willReadFrequently: true });
                }

                if (scanCanvas.width !== el.videoWidth) {
                    scanCanvas.width = el.videoWidth;
                }
                if (scanCanvas.height !== el.videoHeight) {
                    scanCanvas.height = el.videoHeight;
                }

                if (scanCtx) {
                    scanCtx.drawImage(el, 0, 0);
                    const image = scanCtx.getImageData(0, 0, scanCanvas.width, scanCanvas.height);
                    const code = jsQR(image.data, image.width, image.height, {
                        inversionAttempts: 'dontInvert',
                    });

                    if (code?.data && handleResult(code.data)) {
                        return;
                    }
                }
            }
        } catch {
            // дараагийн кадр
        }
    }

    raf = requestAnimationFrame(detectLoop);
};

const start = async () => {
    if (! isMobileDevice()) {
        return;
    }

    error.value = '';
    closed = false;
    scanning.value = true;

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
            },
        });

        await new Promise((resolve) => {
            requestAnimationFrame(resolve);
        });

        if (! video.value) {
            throw new Error('video');
        }

        video.value.srcObject = stream;
        await video.value.play();

        detector = null;
        jsQR = null;

        if ('BarcodeDetector' in window) {
            try {
                detector = new BarcodeDetector({ formats: ['qr_code'] });
            } catch {
                detector = null;
            }
        }

        if (! detector) {
            jsQR = (await import('jsqr')).default;
        }

        raf = requestAnimationFrame(detectLoop);
    } catch {
        error.value = 'Камер нээгдсэнгүй. Камерын зөвшөөрлийг асаана уу.';
        stopCamera();
    }
};

onBeforeUnmount(close);

defineExpose({ open: start });
</script>

<template>
    <div v-if="visible" class="contents">
        <button
            type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-brand-navy-700 shadow-sm transition hover:border-brand-navy-200 hover:bg-brand-navy-50"
            title="QR уншуулах"
            aria-label="QR уншуулах"
            @click="start"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M4 7V5a1 1 0 011-1h2M4 17v2a1 1 0 001 1h2M20 7V5a1 1 0 00-1-1h-2M20 17v2a1 1 0 01-1 1h-2M7 7h3v3H7zM14 7h3v3h-3zM7 14h3v3H7zM14 14h1.5v1.5H14zM17.5 14H19v1.5h-1.5zM14 17.5h1.5V19H14zM17.5 17.5H19V19h-1.5z"
                />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="scanning"
                class="fixed inset-0 z-[80] flex flex-col bg-brand-navy-950/95"
                role="dialog"
                aria-modal="true"
                aria-label="QR уншигч"
            >
                <div class="flex items-center justify-between px-4 py-3 text-white">
                    <p class="text-sm font-semibold">QR уншуулах</p>
                    <button
                        type="button"
                        class="rounded-xl px-3 py-1.5 text-sm font-medium text-white/90 hover:bg-white/10"
                        @click="close"
                    >
                        Хаах
                    </button>
                </div>

                <div class="relative mx-4 flex-1 overflow-hidden rounded-2xl bg-black">
                    <video
                        ref="video"
                        class="h-full w-full object-cover"
                        autoplay
                        muted
                        playsinline
                    />
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                        <div class="h-52 w-52 rounded-2xl border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
                    </div>
                </div>

                <p class="px-5 py-4 text-center text-sm text-white/80">
                    Компьютер дээрх нэвтрэх эсвэл холбосон системийн QR кодыг кадрт оруулна уу.
                </p>
                <p v-if="error" class="px-5 pb-5 text-center text-sm text-amber-300">
                    {{ error }}
                </p>
            </div>
        </Teleport>
    </div>
</template>
