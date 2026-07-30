<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { User, Building2, Shield, Gift, Blocks, Landmark } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';

const page = usePage();
const currentUrl = computed(() => page.url);

const isOwner = computed(() =>
    ((page.props.roles as string[] | undefined) ?? []).includes('owner'),
);

const sidebarNavItems = computed(() => {
    const items = [
        {
            title: 'Hồ sơ cá nhân',
            href: '/settings/profile?tab=profile',
            icon: User,
        },
        {
            title: 'Nhà hàng',
            href: '/settings/restaurant',
            icon: Building2,
        },
    ];

    if (isOwner.value) {
        items.push({
            title: 'Chi nhánh',
            href: '/settings/branches',
            icon: Landmark,
        });
    }

    items.push(
        {
            title: 'Tích hợp',
            href: '/settings/integrations',
            icon: Blocks,
        },
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
    );

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
    <div class="mx-auto max-w-7xl space-y-8 px-6 py-8">
        <div class="space-y-1">
            <h1
                class="bg-gradient-to-r from-neutral-900 to-neutral-600 bg-clip-text text-3xl font-black tracking-tight text-neutral-900 text-transparent dark:from-neutral-50 dark:to-neutral-400 dark:text-neutral-50"
            >
                Thiết lập hệ thống
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Quản lý thông tin hồ sơ cá nhân, bảo mật tài khoản và cài đặt
                cửa hàng của bạn.
            </p>
        </div>

        <Separator class="bg-neutral-200/60 dark:bg-neutral-800/60" />

        <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
            <aside class="w-full shrink-0 lg:w-64">
                <nav
                    class="flex scrollbar-none flex-row gap-1 overflow-x-auto pb-2 lg:flex-col lg:pb-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'justify-start gap-3 rounded-xl px-4 py-2.5 text-xs font-bold tracking-wider uppercase transition-all duration-200 lg:w-full',
                            isItemActive(item.href)
                                ? 'bg-neutral-900 text-white shadow-md shadow-neutral-900/10 dark:bg-neutral-100 dark:text-neutral-900 dark:shadow-none'
                                : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800/60 dark:hover:text-neutral-200',
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <div class="w-full max-w-3xl flex-1">
                <section class="w-full space-y-10">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
