<script setup>
import { ref } from 'vue';
import SoyomboMark from '@/Components/SoyomboMark.vue';

/**
 * Системийн лого.
 *
 * `public/images/emblem.png` файл байвал түүнийг харуулна. Байхгүй бол
 * доорх энгийн хувилбарт автоматаар шилжинэ — ингэснээр зураг байршуулаагүй
 * ч хуудас гуйвахгүй.
 *
 * Логоо солихдоо зөвхөн тэр файлыг солиход хангалттай:
 *   public/images/emblem.png   (тунгалаг дэвсгэртэй, 512×512 орчим)
 */
const useFallback = ref(false);
</script>

<template>
    <span class="relative inline-flex items-center justify-center">
        <img
            v-if="!useFallback"
            src="/images/emblem.png"
            alt="Manage дотоод системийн лого"
            class="h-full w-full object-contain"
            @error="useFallback = true"
        />

        <span
            v-else
            class="flex h-full w-full items-center justify-center rounded-full bg-brand-navy-600 ring-2 ring-inset ring-brand-orange-300/60"
            role="img"
            aria-label="Manage дотоод системийн лого"
        >
            <span
                class="flex h-[82%] w-[82%] items-center justify-center rounded-full bg-brand-navy-700"
            >
                <SoyomboMark class="h-[72%] w-[72%] text-brand-orange-300" />
            </span>
        </span>
    </span>
</template>
