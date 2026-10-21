<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Тайлбарыг хулганы дээд талд харуулна.
 *
 * Хөтчийн өөрийн `title` тайлбар хулганы доор, хойшлоод гардаг бөгөөд
 * байрлалыг нь тохируулах боломжгүй. Тиймээс `title`-ыг түр аваад
 * өөрсдийн тайлбарыг элементийн дээр гаргана — markup өөрчлөхгүйгээр
 * системийн бүх товчинд үйлчилнэ.
 */
const text = ref('');
const style = ref({});

/** Тайлбарыг нь түр авсан элемент. */
let holder = null;
let holderTitle = '';

const place = (el) => {
    const rect = el.getBoundingClientRect();
    const left = Math.min(
        Math.max(8, rect.left + rect.width / 2),
        window.innerWidth - 8,
    );

    style.value = {
        position: 'fixed',
        left: `${left}px`,
        // Элементийн дээд ирмэгээс 8px дээш.
        bottom: `${window.innerHeight - rect.top + 8}px`,
        transform: 'translateX(-50%)',
        zIndex: 300,
    };
};

const hide = () => {
    if (holder && holderTitle) {
        holder.setAttribute('title', holderTitle);
    }

    holder = null;
    holderTitle = '';
    text.value = '';
};

const onOver = (event) => {
    const target = event.target instanceof Element
        ? event.target.closest('[title]')
        : null;

    if (! target) {
        if (holder) hide();

        return;
    }

    if (target === holder) {
        return;
    }

    hide();

    const title = (target.getAttribute('title') || '').trim();

    if (title === '') {
        return;
    }

    holder = target;
    holderTitle = title;
    target.removeAttribute('title');
    text.value = title;
    place(target);
};

const onLeave = (event) => {
    if (! holder) {
        return;
    }

    const next = event.relatedTarget;

    if (next instanceof Node && holder.contains(next)) {
        return;
    }

    hide();
};

onMounted(() => {
    document.addEventListener('mouseover', onOver, true);
    document.addEventListener('mouseout', onLeave, true);
    window.addEventListener('scroll', hide, true);
    window.addEventListener('blur', hide);
    document.addEventListener('click', hide, true);
});

onBeforeUnmount(() => {
    hide();
    document.removeEventListener('mouseover', onOver, true);
    document.removeEventListener('mouseout', onLeave, true);
    window.removeEventListener('scroll', hide, true);
    window.removeEventListener('blur', hide);
    document.removeEventListener('click', hide, true);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="text"
            class="pointer-events-none max-w-xs rounded-lg bg-slate-900/95 px-2.5 py-1.5 text-center text-xs font-medium leading-snug text-white shadow-lg"
            :style="style"
            role="tooltip"
        >
            {{ text }}
        </div>
    </Teleport>
</template>
