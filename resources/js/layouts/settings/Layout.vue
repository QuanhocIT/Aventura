<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { User, Building2, Shield, Gift, Blocks } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';


const sidebarNavItems = [
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
];

const page = usePage();
const currentUrl = computed(() => page.url);

const isItemActive = (href: string) => {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const itemUrl = new URL(href, origin);
    const currentUrlObj = new URL(currentUrl.value, origin);
    
    if (itemUrl.pathname === '/settings/profile') {
        const itemTab = itemUrl.searchParams.get('tab') || 'profile';
        const currentTab = currentUrlObj.searchParams.get('tab') || 'profile';

        return currentUrlObj.pathname === itemUrl.pathname && currentTab === itemTab;
    }
    
    return currentUrlObj.pathname === itemUrl.pathname;
};
</script>

<template>
    <div class="px-6 py-8 max-w-7xl mx-auto space-y-8">
        <div class="space-y-1">
            <h1 class="text-3xl font-black tracking-tight text-neutral-900 dark:text-neutral-50 bg-gradient-to-r from-neutral-900 to-neutral-600 bg-clip-text text-transparent dark:from-neutral-50 dark:to-neutral-400">
                Thiết lập hệ thống
            </h1>
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Quản lý thông tin hồ sơ cá nhân, bảo mật tài khoản và cài đặt cửa hàng của bạn.
            </p>
        </div>

        <Separator class="bg-neutral-200/60 dark:bg-neutral-800/60" />

        <div class="flex flex-col lg:flex-row gap-8 lg:gap-12">
            <aside class="w-full lg:w-64 shrink-0">
                <nav
                    class="flex flex-row lg:flex-col gap-1 overflow-x-auto pb-2 lg:pb-0 scrollbar-none"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'lg:w-full justify-start gap-3 px-4 py-2.5 rounded-xl transition-all duration-200 font-bold text-xs uppercase tracking-wider',
                            isItemActive(item.href)
                                ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900 shadow-md shadow-neutral-900/10 dark:shadow-none'
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

            <div class="flex-1 w-full max-w-3xl">
                <section class="space-y-10 w-full">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
