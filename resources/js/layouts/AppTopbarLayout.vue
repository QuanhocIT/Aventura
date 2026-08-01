<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, Monitor, Settings, X } from 'lucide-vue-next';

import { computed, ref, onMounted, onUnmounted } from 'vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import FlashToast from '@/components/FlashToast.vue';
import Footer from '@/components/Footer.vue';
import GlobalCampaignListener from '@/components/GlobalCampaignListener.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

const page = usePage();
const user = computed(() => (page.props.auth?.user as User | null) ?? null);
const roles = computed(() => {
    const raw = (page.props as any).roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));
const isSuperAdmin = computed(() =>
    hasRole('super_admin', 'system_admin', 'billing_admin', 'support_specialist', 'admin'),
);
const isOwner = computed(() => hasRole('owner'));

const showChatbot = computed(
    () => !user.value || isOwner.value || isSuperAdmin.value,
);

const isStaff = computed(() => roles.value.length > 0);
const tenant = computed(() => (page.props as any).tenant ?? null);
const isMobileOpen = ref(false);

// Scroll-based navbar hide/show
const navHidden = ref(false);
let lastScrollY = 0;
let ticking = false;

function onScroll() {
    if (ticking) {
        return;
    }

    ticking = true;
    requestAnimationFrame(() => {
        const currentY = window.scrollY;
        const diff = currentY - lastScrollY;

        // Hide when scrolling down more than 60px from top, show when scrolling up
        if (currentY > 120) {
            navHidden.value = diff > 0;
        } else {
            navHidden.value = false;
        }

        lastScrollY = currentY;
        ticking = false;
    });
}

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

withDefaults(
    defineProps<{
        transparent?: boolean;
    }>(),
    {
        transparent: false,
    },
);

const { getInitials } = useInitials();

const publicNavItems = [
    { label: 'Tính năng', href: '#features' },
    { label: 'Bảng giá', href: '#pricing' },
    { label: 'Tin tức', href: '/tin-tuc' },
];

const authNavItems = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Sản phẩm', href: '/products' },
    { label: 'Kho', href: '/inventory' },
    { label: 'Nhân viên', href: '/employees' },
    { label: 'Đặt bàn', href: '/reservations' },
    { label: 'Hỗ trợ', href: '/support' },
];

const navItems = computed(() => (user.value ? authNavItems : publicNavItems));

function isActiveNav(href: string): boolean {
    if (href.startsWith('#')) {
        return false;
    }

    return page.url.startsWith(href);
}

const handleLogout = () => {
    router.flushAll();
    router.post(
        '/logout',
        {},
        {
            onSuccess: () => router.visit('/', { replace: true }),
        },
    );
};
</script>

<template>
    <header
        class="z-40 transition-all duration-500"
        :class="[
            transparent
                ? 'absolute top-0 right-0 left-0 border-b border-white/10 bg-transparent text-white'
                : 'sticky top-0 border-b border-border bg-background/95 text-foreground backdrop-blur',
            !transparent && navHidden
                ? '-translate-y-full shadow-none'
                : 'translate-y-0',
        ]"
    >
        <div
            class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:px-8"
        >
            <Link
                href="/"
                class="flex items-center gap-2 font-semibold"
                :class="transparent ? 'text-white' : 'text-foreground'"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary font-sans text-sm font-bold text-primary-foreground"
                    >A</span
                >
                <div class="flex flex-col leading-none">
                    <span>Aventura</span>
                    <span
                        v-if="user && tenant?.name"
                        class="mt-0.5 text-xs font-normal"
                        :class="
                            transparent
                                ? 'text-zinc-300'
                                : 'text-muted-foreground'
                        "
                        >{{ tenant.name }}</span
                    >
                </div>
            </Link>

            <nav class="hidden flex-1 items-center gap-1 md:flex">
                <component
                    v-for="item in navItems"
                    :key="item.label"
                    :is="item.href.startsWith('#') ? 'a' : Link"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm transition-colors"
                    :class="
                        transparent
                            ? isActiveNav(item.href)
                                ? 'bg-white/15 font-semibold text-white shadow-sm backdrop-blur-sm'
                                : 'text-zinc-300 transition-all duration-300 hover:bg-white/10 hover:text-white'
                            : isActiveNav(item.href)
                              ? 'bg-primary/10 font-semibold text-primary'
                              : 'text-muted-foreground transition-all duration-300 hover:bg-muted hover:text-foreground'
                    "
                >
                    {{ item.label }}
                </component>
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                <AppearanceToggleInline />

                <!-- Notification Center -->
                <NotificationCenter v-if="user" />

                <template v-if="!user">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="transition-all"
                        :class="
                            transparent
                                ? 'border-white/20 bg-white/5 text-white hover:bg-white/15 hover:text-white'
                                : ''
                        "
                    >
                        <Link href="/login">Đăng nhập</Link>
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        class="transition-all"
                        :class="
                            transparent
                                ? 'border-none bg-amber-500 font-bold text-zinc-950 hover:bg-amber-600'
                                : ''
                        "
                    >
                        <Link href="/register">Dùng miễn phí</Link>
                    </Button>
                </template>

                <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <Avatar
                                class="h-9 w-9 cursor-pointer border"
                                :class="
                                    transparent
                                        ? 'border-white/15'
                                        : 'border-border'
                                "
                            >
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="bg-primary text-sm font-semibold text-primary-foreground"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        class="w-60"
                        align="end"
                        :side-offset="8"
                    >
                        <DropdownMenuLabel class="p-0 font-normal">
                            <div class="flex items-center gap-3 px-3 py-2">
                                <Avatar class="h-10 w-10 shrink-0">
                                    <AvatarImage
                                        v-if="user.avatar"
                                        :src="user.avatar"
                                        :alt="user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-primary font-semibold text-primary-foreground"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ user.name }}</span
                                    >
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                        >{{ user.email }}</span
                                    >
                                    <span
                                        v-if="isStaff"
                                        class="mt-0.5 text-xs font-medium text-primary capitalize"
                                    >
                                        {{ roles[0] }}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>

                        <DropdownMenuSeparator />

                        <DropdownMenuGroup v-if="isStaff">
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/dashboard"
                                    class="flex cursor-pointer items-center"
                                >
                                    <Monitor class="mr-2 h-4 w-4" />
                                    Vào hệ thống
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/settings/profile"
                                    class="flex cursor-pointer items-center"
                                >
                                    <Settings class="mr-2 h-4 w-4" />
                                    Cài đặt tài khoản
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                        </DropdownMenuGroup>

                        <DropdownMenuItem
                            class="cursor-pointer text-destructive focus:text-destructive"
                            @click="handleLogout"
                        >
                            <LogOut class="mr-2 h-4 w-4" />
                            Đăng xuất
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Button
                variant="outline"
                size="icon"
                class="ml-auto md:hidden"
                :class="
                    transparent
                        ? 'border-white/20 bg-white/5 text-white hover:bg-white/10 hover:text-white'
                        : ''
                "
                @click="isMobileOpen = !isMobileOpen"
            >
                <X v-if="isMobileOpen" class="size-4" />
                <Menu v-else class="size-4" />
                <span class="sr-only">Mở menu</span>
            </Button>
        </div>

        <div
            v-if="isMobileOpen"
            class="border-t px-4 py-3 md:hidden"
            :class="
                transparent
                    ? 'border-white/10 bg-zinc-950/95'
                    : 'border-border bg-background'
            "
        >
            <nav class="flex flex-col gap-1">
                <component
                    v-for="item in navItems"
                    :key="item.label"
                    :is="item.href.startsWith('#') ? 'a' : Link"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm transition-colors"
                    :class="
                        transparent
                            ? isActiveNav(item.href)
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-zinc-200 hover:bg-white/5 hover:text-white'
                            : isActiveNav(item.href)
                              ? 'bg-muted font-medium text-foreground'
                              : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="isMobileOpen = false"
                >
                    {{ item.label }}
                </component>
            </nav>
            <div class="mt-3 flex items-center gap-2">
                <AppearanceToggleInline />
                <template v-if="!user">
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        :class="
                            transparent
                                ? 'border-white/20 bg-white/5 text-white hover:bg-white/10'
                                : ''
                        "
                    >
                        <Link href="/login">Đăng nhập</Link>
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        class="flex-1"
                        :class="
                            transparent
                                ? 'border-none bg-amber-500 font-bold text-zinc-950 hover:bg-amber-600'
                                : ''
                        "
                    >
                        <Link href="/register">Dùng miễn phí</Link>
                    </Button>
                </template>
                <Button v-else as-child size="sm" class="flex-1">
                    <Link href="/dashboard">Vào hệ thống</Link>
                </Button>
            </div>
        </div>
    </header>

    <main>
        <slot />
    </main>

    <Footer />

    <ChatbotWidget v-if="showChatbot" source="widget" />
    <MobileBottomNav />
    <FlashToast />
    <ConfirmDialog />
    <GlobalCampaignListener />
</template>

<style scoped>
main {
    min-height: 80vh;
}
</style>
