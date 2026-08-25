<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import StateEmblem from '@/Components/StateEmblem.vue';
import OrnamentMark from '@/Components/OrnamentMark.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const sidebarOpen = ref(false);

const iconPaths = {
    grid: 'M4 5a1 1 0 011-1h5v6H4V5zM14 4h5a1 1 0 011 1v5h-6V4zM4 14h6v6H5a1 1 0 01-1-1v-5zM14 14h6v5a1 1 0 01-1 1h-5v-6z',
    sparkles: 'M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9L12 3zM19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2z',
    settings: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 008 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9c.14.36.43.65.79.79H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'M6 6l12 12M18 6L6 18',
    lock: 'M5 11h14a1 1 0 011 1v8a1 1 0 01-1 1H5a1 1 0 01-1-1v-8a1 1 0 011-1zM8 11V7a4 4 0 018 0v4',
    external: 'M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3',
    wallet: 'M3 7a2 2 0 012-2h11a2 2 0 012 2v1M3 7v10a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2H5a2 2 0 01-2-2zM17 13h.01',
    building: 'M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M4 21h16M16 9h2a2 2 0 012 2v10M8 7h2M8 11h2M8 15h2',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    mail: 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l9 6 9-6',
    chart: 'M4 20V10M10 20V4M16 20v-6M22 20H2',
    clipboard: 'M9 4h6a1 1 0 011 1v1H8V5a1 1 0 011-1zM8 6H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-2M9 12h6M9 16h4',
    users: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
    calendar: 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z',
    plane: 'M10 21l-1-6-6-3 16-7-7 16-3-6z',
    book: 'M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5V6a2 2 0 012-2h14v15H6.5A2.5 2.5 0 004 19.5z',
    file: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8zM14 2v6h6M8 13h8M8 17h5',
    hash: 'M4 9h16M4 15h16M10 3L8 21M16 3l-2 18',
    archive: 'M3 5h18v4H3zM5 9v10a2 2 0 002 2h10a2 2 0 002-2V9M10 13h4',
    mic: 'M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3zM19 10a7 7 0 01-14 0M12 19v4M8 23h8',
    phone: 'M5 3h3l2 5-2.5 1.5a12 12 0 006 6L15 13l5 2v3a2 2 0 01-2.2 2A16.8 16.8 0 013 5.2 2 2 0 015 3z',
    graduation: 'M22 10L12 5 2 10l10 5 10-5zM6 12v5c0 2 3 3 6 3s6-1 6-3v-5',
};

const user = computed(() => page.props.auth.user);
const moduleNav = computed(() => (page.props.moduleNav ?? []).filter((g) => g.key !== 'systems'));
const navSystems = computed(() => page.props.nav ?? []);
const externalSystems = computed(() =>
    page.props.systemsHubEnabled === false
        ? []
        : navSystems.value.filter((s) => !s.is_internal),
);
const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

const systemHint = (system) => {
    if (!system.requires_login) return `${system.name} — нээх`;
    if (!system.has_credential) return `${system.name} — нэвтрэх мэдээлэл нэмнэ`;
    if (!vaultUnlocked.value) return `${system.name} — эхлээд санг нээнэ`;

    return `${system.name} рүү нэвтрэх`;
};

const openSystem = (system) => {
    if (!system.requires_login) {
        if (system.is_embeddable) {
            router.get(route('systems.show', system.id));
        } else {
            window.open(system.entry_url, '_blank', 'noopener');
        }

        return;
    }

    if (system.has_credential && vaultUnlocked.value) {
        window.open(route('systems.launch', system.id), '_blank', 'noopener');

        return;
    }

    router.get(route('dashboard'), { focus: system.id });
};

const isCurrent = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-[17.5rem] flex-col border-r border-slate-200/80 bg-white shadow-soft transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <Link
                :href="route('dashboard')"
                class="flex h-[4.5rem] items-center gap-3 border-b border-slate-100 px-5 transition hover:bg-slate-50"
            >
                <StateEmblem class="h-10 w-10 shrink-0" />
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-bold tracking-tight text-brand-navy-800">Дорноговь</div>
                    <div class="text-[11px] font-medium tracking-wide text-slate-500">Нэгдсэн систем</div>
                </div>
            </Link>

            <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
                <template v-for="group in moduleNav" :key="group.key">
                    <div class="ui-section-label">{{ group.label }}</div>
                    <Link
                        v-for="item in group.items"
                        :key="item.key"
                        :href="route(item.route)"
                        class="ui-nav-link"
                        :class="isCurrent(item.route) ? 'ui-nav-link-active' : ''"
                    >
                        <svg class="h-5 w-5 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths[item.icon] || iconPaths.clipboard" />
                        </svg>
                        <span class="truncate">{{ item.label }}</span>
                    </Link>
                </template>

                <div v-if="externalSystems.length" class="ui-section-label">Холбосон системүүд</div>
                <button
                    v-for="system in externalSystems"
                    :key="system.id"
                    type="button"
                    :title="systemHint(system)"
                    class="ui-nav-link w-full text-left"
                    @click="openSystem(system)"
                >
                    <svg class="h-5 w-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                    </svg>
                    <span class="flex-1 truncate">{{ system.name }}</span>
                    <span v-if="system.has_credential" class="h-1.5 w-1.5 rounded-full bg-brand-orange-500" />
                </button>
            </nav>

            <div class="space-y-1 border-t border-slate-100 p-3">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                    <svg class="h-5 w-5 shrink-0 text-brand-navy-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path :d="iconPaths.lock" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="flex-1 font-medium">Сан</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="vaultUnlocked ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                    >
                        {{ vaultUnlocked ? 'нээлттэй' : 'түгжээтэй' }}
                    </span>
                </div>

                <Link
                    v-if="user.is_admin"
                    :href="route('admin.users.index')"
                    class="ui-nav-link"
                    :class="isCurrent('admin.users.*') ? 'ui-nav-link-active' : ''"
                >
                    <svg class="h-5 w-5 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path :d="iconPaths.users" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Хандах эрх</span>
                </Link>

                <Link
                    v-if="user.is_admin"
                    :href="route('admin.systems.index')"
                    class="ui-nav-link"
                    :class="isCurrent('admin.systems.*') ? 'ui-nav-link-active' : ''"
                >
                    <svg class="h-5 w-5 shrink-0 opacity-80" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path :d="iconPaths.settings" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Системийн тохиргоо</span>
                </Link>

                <div class="flex items-center justify-center gap-2 px-2 pt-2">
                    <span class="h-px flex-1 bg-slate-100" />
                    <OrnamentMark class="h-2.5 w-6 text-brand-orange-500" />
                    <span class="h-px flex-1 bg-slate-100" />
                </div>
            </div>
        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-30 bg-brand-navy-950/40 backdrop-blur-[2px] lg:hidden" @click="sidebarOpen = false" />

        <div class="lg:pl-[17.5rem]">
            <header class="sticky top-0 z-20 flex h-[4.5rem] items-center gap-4 border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-md sm:px-6">
                <button class="rounded-xl p-2 text-brand-navy-700 hover:bg-slate-100 lg:hidden" @click="sidebarOpen = !sidebarOpen">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24">
                        <path :d="sidebarOpen ? iconPaths.close : iconPaths.menu" />
                    </svg>
                </button>

                <div>
                    <h1 class="text-base font-bold tracking-tight text-brand-navy-800">
                        <slot name="header">{{ title || 'Системүүд' }}</slot>
                    </h1>
                </div>

                <div class="ml-auto">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-sm text-slate-700 shadow-sm transition hover:border-brand-navy-200 hover:bg-brand-navy-50">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-navy-600 text-xs font-bold text-white">
                                    {{ user.name.charAt(0) }}
                                </span>
                                <span class="hidden font-medium sm:inline">{{ user.name }}</span>
                                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Профайл</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Гарах</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <!-- AI floating entry -->
        <Link
            v-if="page.props.aiAssistant?.available"
            :href="page.props.aiAssistant.href"
            class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-2xl bg-brand-navy-700 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-navy-700/30 transition hover:-translate-y-0.5 hover:bg-brand-navy-800"
            :title="page.props.aiAssistant?.name || 'Manage AI'"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path :d="iconPaths.sparkles" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span class="hidden sm:inline">{{ page.props.aiAssistant?.name || 'Manage AI' }}</span>
        </Link>
    </div>
</template>
