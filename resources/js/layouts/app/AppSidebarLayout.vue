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
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <!-- Impersonation Warning Banner -->
            <div
                v-if="isImpersonating"
                class="flex w-full shrink-0 items-center justify-between border-b border-amber-600/30 bg-amber-500 px-4 py-2 text-xs font-medium text-amber-950 sm:text-sm"
            >
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2 w-2">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-600 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2 w-2 rounded-full bg-amber-700"
                        ></span>
                    </span>
                    <span
                        >Bạn đang sắm vai thành viên:
                        <strong class="underline">{{
                            page.props.auth?.user?.name
                        }}</strong>
                        ({{ page.props.auth?.user?.email }})</span
                    >
                </div>
                <Link
                    href="/impersonate/stop"
                    method="post"
                    as="button"
                    class="rounded bg-amber-950 px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-amber-900"
                >
                    Thoát sắm vai
                </Link>
            </div>

            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
        <FlashToast />
        <OnboardingTour />
        <ChatbotWidget source="support" />
    </AppShell>
</template>
