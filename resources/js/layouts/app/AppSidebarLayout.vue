<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import FlashToast from '@/components/FlashToast.vue';
import GlobalCampaignListener from '@/components/GlobalCampaignListener.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import QROrderAlertCenter from '@/components/QROrderAlertCenter.vue';
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

const tenant = computed(() => page.props.tenant as any);
const quotaSummary = computed(() => tenant.value?.quota_summary ?? null);

const quotaWarnings = computed(() => {
    if (!quotaSummary.value || !quotaSummary.value.resources) {
return [];
}

    return Object.entries(quotaSummary.value.resources)
        .map(([key, res]: [string, any]) => ({
            key,
            label: key === 'branches' ? 'Chi nhánh' : key === 'tables' ? 'Bàn' : key === 'employees' ? 'Nhân viên' : 'Khu vực',
            ...res
        }))
        .filter(res => !res.unlimited && res.percentage >= 85);
});

function openUpgradeModal() {
    window.dispatchEvent(new CustomEvent('open-upgrade-modal'));
}
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

            <!-- Quota Alerting Banner -->
            <div v-if="isOwner && quotaWarnings.length > 0" class="bg-gradient-to-r from-amber-500 to-orange-600 text-white px-4 py-2.5 flex flex-col sm:flex-row items-center justify-between text-xs sm:text-sm font-medium border-b border-orange-600/30 w-full shrink-0 gap-2 shadow-sm">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-4 shrink-0 animate-pulse text-white" />
                    <span>
                        Bạn đã sử dụng gần hết hạn mức: 
                        <span v-for="(warn, idx) in quotaWarnings" :key="warn.key">
                            <strong class="underline font-bold">{{ warn.used }}/{{ warn.limit }} {{ warn.label }}</strong> ({{ warn.percentage }}%){{ idx < quotaWarnings.length - 1 ? ', ' : '' }}
                        </span>. 
                        Nâng cấp gói dịch vụ để không làm gián đoạn trải nghiệm của nhà hàng.
                    </span>
                </div>
                <button @click="openUpgradeModal" class="bg-white text-orange-700 hover:bg-orange-50 px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm cursor-pointer whitespace-nowrap">
                    Nâng cấp gói ngay
                </button>
            </div>
            
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
        <FlashToast />
        <QROrderAlertCenter />
        <GlobalCampaignListener />
        <OnboardingTour />
        <ChatbotWidget v-if="showChatbot" source="support" />
    </AppShell>
</template>
