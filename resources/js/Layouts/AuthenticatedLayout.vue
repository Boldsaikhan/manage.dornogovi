<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import StateEmblem from '@/Components/StateEmblem.vue';

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
    // Системийн дүрснүүд
    wallet: 'M3 7a2 2 0 012-2h11a2 2 0 012 2v1M3 7v10a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2H5a2 2 0 01-2-2zM17 13h.01',
    building: 'M4 21V5a2 2 0 012-2h8a2 2 0 012 2v16M4 21h16M16 9h2a2 2 0 012 2v10M8 7h2M8 11h2M8 15h2',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18',
    mail: 'M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zM3 7l9 6 9-6',
    chart: 'M4 20V10M10 20V4M16 20v-6M22 20H2',
    clipboard: 'M9 4h6a1 1 0 011 1v1H8V5a1 1 0 011-1zM8 6H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V8a2 2 0 00-2-2h-2M9 12h6M9 16h4',
};

const user = computed(() => page.props.auth.user);
const navSystems = computed(() => page.props.nav ?? []);
// Гадны төрийн системүүд ("Нэвтрэх") ба байгууллагын өөрийн системүүд ("Дотоод ажил")
const externalSystems = computed(() => navSystems.value.filter((s) => !s.is_internal));
const internalSystems = computed(() => navSystems.value.filter((s) => s.is_internal));
// Апп дотор шууд байрлах дотоод модулиуд (гадаад холбоос биш)
const internalPages = [
    { label: 'Үүрэг, чиглэл', routeName: 'tasks.index', icon: 'clipboard' },
];
const navGroups = computed(() =>
    [
        { label: 'Дотоод ажил', pages: internalPages, items: internalSystems.value },
        { label: 'Нэвтрэх', pages: [], items: externalSystems.value },
    ].filter((group) => group.items.length || group.pages.length),
);
const vaultUnlocked = computed(() => page.props.vault?.unlocked ?? false);

/**
 * Мэдээлэл хадгалсан бөгөөд сан нээлттэй бол шууд нэвтэрнэ. Үгүй бол Dashboard руу
 * очиж, тэнд байгаа хадгалах/нээх цонхыг гаргана.
 */
/** Хулгана аваачихад гарах тайлбар — дарахад юу болохыг урьдчилж хэлнэ. */
const systemHint = (system) => {
    if (!system.requires_login) return `${system.name} — нээх`;
    if (!system.has_credential) return `${system.name} — нэвтрэх мэдээлэл нэмнэ`;
    if (!vaultUnlocked.value) return `${system.name} — эхлээд санг нээнэ`;

    return `${system.name} рүү нэвтрэх`;
};

const openSystem = (system) => {
    // Нэвтрэх мэдээлэл шаарддаггүй систем (жишээ нь дотоод дашбоард) — санг
    // нээх шаардлагагүй, шууд нээнэ.
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
</script>

<template>
    <div class="min-h-screen bg-brand-navy-50">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-brand-navy-800 text-brand-navy-100 transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center gap-3 border-b border-white/10 px-5">
                <StateEmblem class="h-9 w-9 shrink-0" />
                <div class="leading-tight">
                    <div class="text-sm font-semibold text-white">Дорноговь</div>
                    <div class="text-xs text-brand-navy-300">Нэгдсэн систем</div>
                </div>
            </div>

            <nav class="h-[calc(100vh-4rem)] space-y-1 overflow-y-auto p-3">
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-white/5"
                    :class="route().current('dashboard') ? 'bg-brand-orange-500 font-medium text-white hover:bg-brand-orange-500' : 'text-brand-navy-100'"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path :d="iconPaths.grid" />
                    </svg>
                    <span>Системүүд</span>
                </Link>

                <template v-for="group in navGroups" :key="group.label">
                <div class="!mt-3 px-3 pb-1 text-[11px] font-medium uppercase tracking-wide text-brand-navy-400">
                    {{ group.label }}
                </div>

                <Link
                    v-for="page in group.pages"
                    :key="page.routeName"
                    :href="route(page.routeName)"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-white/5"
                    :class="route().current(page.routeName) ? 'bg-brand-orange-500 font-medium text-white hover:bg-brand-orange-500' : 'text-brand-navy-100'"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path :d="iconPaths[page.icon]" />
                    </svg>
                    <span>{{ page.label }}</span>
                </Link>

                <button
                    v-for="system in group.items"
                    :key="system.id"
                    :title="systemHint(system)"
                    class="group flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm text-brand-navy-100 transition hover:bg-white/10 hover:text-white"
                    @click="openSystem(system)"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-brand-navy-300 transition group-hover:text-brand-orange-500"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                    >
                        <path :d="iconPaths[system.icon] ?? iconPaths.globe" />
                    </svg>
                    <span class="flex-1 truncate text-left">{{ system.name }}</span>

                    <!-- Хулгана аваачаагүй үед: мэдээлэл хадгалсан эсэхийн цэг -->
                    <span
                        v-if="system.has_credential"
                        class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-orange-500 group-hover:hidden"
                    />
                    <!-- Хулгана аваачихад: юу болохыг заасан тэмдэг -->
                    <svg
                        class="hidden h-4 w-4 shrink-0 text-brand-orange-500 group-hover:block"
                        fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"
                    >
                        <path :d="!system.requires_login || (system.has_credential && vaultUnlocked) ? iconPaths.external : iconPaths.lock" />
                    </svg>
                </button>
                </template>

                <div class="!mt-4 space-y-1 border-t border-white/10 pt-3">
                    <div class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-brand-navy-300">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths.lock" />
                        </svg>
                        <span class="flex-1">Сан</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px]"
                            :class="vaultUnlocked ? 'bg-green-500/20 text-green-300' : 'bg-white/10 text-brand-navy-300'"
                        >
                            {{ vaultUnlocked ? 'нээлттэй' : 'түгжээтэй' }}
                        </span>
                    </div>

                    <Link
                        v-if="user.is_admin"
                        :href="route('admin.systems.index')"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition hover:bg-white/5"
                        :class="route().current('admin.systems.*') ? 'bg-brand-orange-500 font-medium text-white hover:bg-brand-orange-500' : 'text-brand-navy-100'"
                    >
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths.settings" />
                        </svg>
                        <span>Системийн тохиргоо</span>
                    </Link>

                    <div class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm text-brand-navy-400">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path :d="iconPaths.sparkles" />
                        </svg>
                        <span>AI туслах</span>
                        <span class="ml-auto rounded-full bg-white/10 px-2 py-0.5 text-[10px]">удахгүй</span>
                    </div>
                </div>
            </nav>
        </aside>

        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Main -->
        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-brand-navy-100 bg-white px-4 sm:px-6">
                <button class="text-brand-navy-700 lg:hidden" @click="sidebarOpen = !sidebarOpen">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24">
                        <path :d="sidebarOpen ? iconPaths.close : iconPaths.menu" />
                    </svg>
                </button>

                <h1 class="text-base font-semibold text-brand-navy-900">
                    <slot name="header">Системүүд</slot>
                </h1>

                <div class="ml-auto">
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-brand-navy-700 hover:bg-brand-navy-50">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-navy-100 text-xs font-semibold text-brand-navy-700">
                                    {{ user.name.charAt(0) }}
                                </span>
                                <span class="hidden sm:inline">{{ user.name }}</span>
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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

            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
