<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Албан хаагч бүрийн нэвтрэх мэдээллийн санг админ тусад нь нээх, хаах хэсэг.
 *
 * Хугацаагаар хаагдахгүй — гараар нээж, гараар хаана. Нээх нь нууц үгийг
 * харуулахгүй, зөвхөн тухайн хүн дахин нууц үгээ бичихгүй болгоно.
 */
const props = defineProps({
    users: { type: Array, default: () => [] },
});

const search = ref('');
const department = ref('all');
const status = ref('all');
const busyId = ref(null);

/** Хэлтсийн жагсаалт — сонгож шүүнэ. */
const departments = computed(() => {
    const names = new Set();

    props.users.forEach((u) => {
        if (u.department) {
            names.add(u.department);
        }
    });

    return [...names].sort((a, b) => a.localeCompare(b, 'mn'));
});

const hasNoDepartment = computed(() => props.users.some((u) => ! u.department));

const rows = computed(() => {
    const needle = search.value.trim().toLowerCase();

    return props.users.filter((u) => {
        if (department.value === 'none' && u.department) {
            return false;
        }

        if (department.value !== 'all' && department.value !== 'none'
            && u.department !== department.value) {
            return false;
        }

        if (status.value === 'open' && ! u.opened_at) {
            return false;
        }

        if (status.value === 'closed' && u.opened_at) {
            return false;
        }

        if (! needle) {
            return true;
        }

        return [u.name, u.position, u.department]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(needle));
    });
});

const openCount = computed(() => props.users.filter((u) => u.opened_at).length);

const openedText = (seconds) => {
    if (! seconds) {
        return '';
    }

    const at = new Date(seconds * 1000);

    return Number.isNaN(at.getTime())
        ? ''
        : at.toLocaleString('mn-MN', {
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
};

const send = (url, data) => {
    router.post(url, data, {
        preserveScroll: true,
        preserveState: true,
        only: ['vaultUsers', 'flash'],
        onFinish: () => {
            busyId.value = null;
        },
    });
};

const open = (user) => {
    busyId.value = user.id;
    send(route('admin.vault.users.open', user.id), {});
};

const close = (user) => {
    busyId.value = user.id;
    send(route('admin.vault.users.close', user.id), {});
};
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[12rem] flex-1">
                <label class="ui-label">Хайх</label>
                <input
                    v-model="search"
                    type="search"
                    class="ui-input"
                    placeholder="Нэр, албан тушаал"
                >
            </div>
            <div class="min-w-[14rem]">
                <label class="ui-label">Хэлтэс</label>
                <select v-model="department" class="ui-input">
                    <option value="all">Бүх хэлтэс</option>
                    <option v-for="name in departments" :key="name" :value="name">{{ name }}</option>
                    <option v-if="hasNoDepartment" value="none">Хэлтэс оруулаагүй</option>
                </select>
            </div>
            <div class="min-w-[9rem]">
                <label class="ui-label">Төлөв</label>
                <select v-model="status" class="ui-input">
                    <option value="all">Бүгд</option>
                    <option value="open">Нээлттэй</option>
                    <option value="closed">Хаалттай</option>
                </select>
            </div>
            <p class="pb-2 text-sm text-slate-500">
                Нээлттэй: <b class="text-emerald-700">{{ openCount }}</b> / {{ users.length }}
                <span class="block text-xs text-slate-400">Харагдаж буй: {{ rows.length }}</span>
            </p>
        </div>

        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th class="w-10">№</th>
                        <th>Албан хаагч</th>
                        <th>Хэлтэс</th>
                        <th class="w-28 text-center">Хадгалсан</th>
                        <th class="w-48">Төлөв</th>
                        <th class="w-40 text-right">Үйлдэл</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(user, index) in rows" :key="user.id">
                        <td class="text-slate-400">{{ index + 1 }}</td>
                        <td>
                            <div class="font-medium text-brand-navy-900">{{ user.name }}</div>
                            <div v-if="user.position" class="text-xs text-slate-500">{{ user.position }}</div>
                        </td>
                        <td class="text-slate-600">{{ user.department || '—' }}</td>
                        <td class="text-center text-slate-600">{{ user.credentials }}</td>
                        <td>
                            <span
                                v-if="user.opened_at"
                                class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"
                            >
                                Нээлттэй · {{ openedText(user.opened_at) }}-нд нээсэн
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600"
                            >
                                Хаалттай
                            </span>
                        </td>
                        <td class="text-right">
                            <button
                                v-if="user.opened_at"
                                type="button"
                                class="ui-btn-ghost !px-3 !py-1.5 text-xs"
                                :disabled="busyId === user.id"
                                @click="close(user)"
                            >
                                Хаах
                            </button>
                            <button
                                v-else
                                type="button"
                                class="ui-btn-primary !px-3 !py-1.5 text-xs"
                                :disabled="busyId === user.id"
                                @click="open(user)"
                            >
                                Нээх
                            </button>
                        </td>
                    </tr>
                    <tr v-if="! rows.length">
                        <td colspan="6" class="py-6 text-center text-sm text-slate-500">
                            Олдсонгүй.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
