<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import FlashToast from '@/components/FlashToast.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isImpersonating = computed(() => !!page.props.is_impersonating);

const user = computed(() => (page.props.auth?.user as any) ?? null);
const roles = computed(() => {
    const raw = page.props.roles ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));
const isSuperAdmin  = computed(() => hasRole('super_admin') || hasRole('admin'));
const isOwner       = computed(() => hasRole('owner'));

const showChatbot = computed(() => !user.value || isOwner.value || isSuperAdmin.value);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <!-- Impersonation Warning Banner -->
            <div v-if="isImpersonating" class="bg-amber-500 text-amber-950 px-4 py-2 flex items-center justify-between text-xs sm:text-sm font-medium border-b border-amber-600/30 w-full shrink-0">
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-600 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-700"></span>
                    </span>
                    <span>Bạn đang sắm vai thành viên: <strong class="underline">{{ page.props.auth?.user?.name }}</strong> ({{ page.props.auth?.user?.email }})</span>
                </div>
                <Link href="/impersonate/stop" method="post" as="button" class="bg-amber-950 text-white px-3 py-1 rounded text-xs font-semibold hover:bg-amber-900 transition-colors">
                    Thoát sắm vai
                </Link>
            </div>
            
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
        <FlashToast />
        <OnboardingTour />
        <ChatbotWidget v-if="showChatbot" source="support" />
    </AppShell>
</template>
