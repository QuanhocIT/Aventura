<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    User,
    Building2,
    Shield,
    Gift,
    Blocks,
    Landmark,
    Settings2,
    Palette,
    FlaskConical,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const page = usePage();
const currentUrl = computed(() => page.url);

const roles = computed(() => {
    const raw = (page.props.roles as string[] | undefined) ?? [];

    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});

const isOwnerOrSuperAdmin = computed(() =>
    roles.value.some((role) =>
        ['owner', 'super_admin', 'system_admin'].includes(role),
    ),
);
const isManager = computed(() => roles.value.includes('manager'));

const sidebarNavItems = computed(() => {
    const items = [
        {
            title: 'Hồ sơ cá nhân',
            href: '/settings/profile?tab=profile',
            icon: User,
        },
    ];

    if (isOwnerOrSuperAdmin.value || isManager.value) {
        items.push({
            title: 'Nhà hàng',
            href: '/settings/restaurant',
            icon: Building2,
        });
    }

    if (isOwnerOrSuperAdmin.value) {
        items.push({
            title: 'Chi nhánh',
            href: '/settings/branches',
            icon: Landmark,
        });
        items.push({
            title: 'Tích hợp',
            href: '/settings/integrations',
            icon: Blocks,
        });
    }

    items.push(
        {
            title: 'Bảo mật',
            href: '/settings/profile?tab=security',
            icon: Shield,
        },
        {
            title: 'Giới thiệu & Nhận thưởng',
            href: '/settings/profile?tab=referrals',
            icon: Gift,
        },
        {
            title: 'Giao diện',
            href: '/settings/appearance',
            icon: Palette,
        },
    );

    if (isOwnerOrSuperAdmin.value) {
        items.push({
            title: 'Sandbox & Demo',
            href: '/settings/sandbox',
            icon: FlaskConical,
        });
    }

    return items;
});

const isItemActive = (href: string) => {
    const origin =
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost';
    const itemUrl = new URL(href, origin);
    const currentUrlObj = new URL(currentUrl.value, origin);

    if (itemUrl.pathname === '/settings/profile') {
        const itemTab = itemUrl.searchParams.get('tab') || 'profile';
        const currentTab = currentUrlObj.searchParams.get('tab') || 'profile';

        return (
            currentUrlObj.pathname === itemUrl.pathname &&
            currentTab === itemTab
        );
    }

    return currentUrlObj.pathname === itemUrl.pathname;
};
</script>

<template>
    <div
        class="settings-shell mx-auto w-full max-w-[1600px] space-y-9 px-6 py-8 xl:px-8 2xl:px-10 2xl:py-10"
    >
        <div class="settings-hero flex items-start gap-4">
            <div class="settings-hero-icon">
                <Settings2 class="size-6" />
            </div>
            <div class="space-y-1.5">
                <p class="settings-eyebrow">
                    {{ isOwnerOrSuperAdmin ? 'Không gian quản trị' : 'Cài đặt cá nhân' }}
                </p>
                <h1
                    class="bg-gradient-to-r from-neutral-950 via-neutral-800 to-neutral-500 bg-clip-text text-3xl font-black tracking-tight text-neutral-950 text-transparent sm:text-4xl dark:from-neutral-50 dark:via-neutral-200 dark:to-neutral-500 dark:text-neutral-50"
                >
                    {{ isOwnerOrSuperAdmin ? 'Thiết lập hệ thống' : 'Hồ sơ & Tài khoản' }}
                </h1>
                <p
                    class="max-w-3xl text-base leading-7 text-neutral-500 dark:text-neutral-400"
                >
                    {{
                        isOwnerOrSuperAdmin
                            ? 'Quản lý thông tin hồ sơ cá nhân, bảo mật tài khoản và cài đặt cửa hàng của bạn.'
                            : 'Quản lý thông tin cá nhân, cập nhật mật khẩu bảo mật và tùy chỉnh giao diện sử dụng.'
                    }}
                </p>
            </div>
        </div>

        <Separator
            class="settings-divider bg-neutral-200/70 dark:bg-neutral-800/70"
        />

        <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:gap-8">
            <aside
                class="order-2 w-full shrink-0 lg:sticky lg:top-24 lg:order-2 lg:w-64"
            >
                <div class="settings-nav-heading">Danh mục cài đặt</div>
                <nav
                    class="settings-nav flex scrollbar-none flex-row gap-1.5 overflow-x-auto pb-2 lg:flex-col lg:pb-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'settings-nav-item group justify-start gap-2.5 rounded-xl px-4 py-2.5 text-[13px] font-bold tracking-wide transition-all duration-200 lg:w-full',
                            isItemActive(item.href)
                                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20 dark:bg-white dark:text-slate-950 dark:shadow-none'
                                : 'text-slate-700 font-semibold hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white',
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component
                                :is="item.icon"
                                class="size-[18px] shrink-0 transition-transform duration-200 group-hover:scale-105"
                            />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <div class="order-1 w-full min-w-0 flex-1 lg:order-1">
                <section class="w-full space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
