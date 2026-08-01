<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import {
    computed,
    defineAsyncComponent,
    ref,
    onMounted,
    onUnmounted,
} from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import FlashToast from '@/components/FlashToast.vue';
import GlobalCampaignListener from '@/components/GlobalCampaignListener.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import QROrderAlertCenter from '@/components/QROrderAlertCenter.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

const LazyChatbotWidget = defineAsyncComponent(
    () => import('@/components/ChatbotWidget.vue'),
);
const LazyCommandPalette = defineAsyncComponent(
    () => import('@/components/CommandPalette.vue'),
);

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isImpersonating = computed(() => !!page.props.is_impersonating);
const upcomingMaintenance = computed(
    () => page.props.upcoming_maintenance as any,
);

const user = computed(() => (page.props.auth?.user as any) ?? null);
const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));
const isSuperAdmin = computed(() =>
    hasRole(
        'super_admin',
        'system_admin',
        'billing_admin',
        'support_specialist',
        'admin',
    ),
);
const isOwner = computed(() => hasRole('owner'));

const showChatbot = computed(
    () => !user.value || isOwner.value || isSuperAdmin.value,
);

const tenant = computed(() => page.props.tenant as any);
const quotaSummary = computed(() => tenant.value?.quota_summary ?? null);

const quotaWarnings = computed(() => {
    if (!quotaSummary.value || !quotaSummary.value.resources) {
        return [];
    }

    return Object.entries(quotaSummary.value.resources)
        .map(([key, res]: [string, any]) => ({
            key,
            label:
                key === 'branches'
                    ? 'Chi nhánh'
                    : key === 'tables'
                      ? 'Bàn'
                      : key === 'employees'
                        ? 'Nhân viên'
                        : 'Khu vực',
            ...res,
        }))
        .filter((res) => !res.unlimited && res.percentage >= 85);
});

function openUpgradeModal() {
    window.dispatchEvent(new CustomEvent('open-upgrade-modal'));
}

const shiftAllowedUntil = computed(
    () => page.props.auth?.shift_allowed_until as number | null,
);
const currentTimeSec = ref(Math.floor(Date.now() / 1000));
const showShiftExpiredModal = ref(false);

const shiftWarningMinutes = computed(() => {
    if (!shiftAllowedUntil.value) {
        return null;
    }

    const diff = shiftAllowedUntil.value - currentTimeSec.value;

    if (diff > 0 && diff <= 600) {
        // 10 minutes or less
        return Math.max(1, Math.ceil(diff / 60));
    }

    return null;
});

let checkShiftTimer: any = null;
let autoLogoutTimer: any = null;
let deferredWidgetsTimer: ReturnType<typeof setTimeout> | null = null;
const deferredWidgetsReady = ref(false);

const confirmLogout = () => {
    router.flushAll();
    router.post(
        '/logout',
        {},
        {
            onSuccess: () => {
                window.location.href = '/login';
            },
            onError: () => {
                window.location.href = '/login';
            },
        },
    );
};

const triggerShiftExpired = () => {
    if (showShiftExpiredModal.value) {
        return;
    }

    showShiftExpiredModal.value = true;

    // Dispatch save event to active pages (like cashier dashboard)
    window.dispatchEvent(new CustomEvent('shift-expired-save'));

    // Auto logout after 8 seconds
    autoLogoutTimer = setTimeout(() => {
        confirmLogout();
    }, 8000);
};

onMounted(() => {
    window.addEventListener('shift-expired', triggerShiftExpired);

    // Defer non-critical global widgets until the first screen is interactive.
    // They remain available shortly after navigation without blocking the page
    // component and its primary data request.
    if (showChatbot.value || isSuperAdmin.value) {
        deferredWidgetsTimer = setTimeout(() => {
            deferredWidgetsReady.value = true;
        }, 800);
    }

    checkShiftTimer = setInterval(() => {
        currentTimeSec.value = Math.floor(Date.now() / 1000);

        if (
            shiftAllowedUntil.value &&
            currentTimeSec.value > shiftAllowedUntil.value
        ) {
            triggerShiftExpired();
        }
    }, 10000); // Check every 10s

    // Initial check
    if (
        shiftAllowedUntil.value &&
        currentTimeSec.value > shiftAllowedUntil.value
    ) {
        triggerShiftExpired();
    }
});

onUnmounted(() => {
    window.removeEventListener('shift-expired', triggerShiftExpired);

    if (checkShiftTimer) {
        clearInterval(checkShiftTimer);
    }

    if (autoLogoutTimer) {
        clearTimeout(autoLogoutTimer);
    }

    if (deferredWidgetsTimer) {
        clearTimeout(deferredWidgetsTimer);
    }
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <!-- Upcoming Maintenance Banner -->
            <div
                v-if="upcomingMaintenance"
                class="flex w-full shrink-0 flex-col items-center justify-between gap-2 border-b border-rose-800/30 bg-gradient-to-r from-rose-600 to-red-700 px-4 py-2.5 text-xs font-medium text-white shadow-sm sm:flex-row sm:text-sm"
            >
                <div class="flex items-center gap-2">
                    <AlertTriangle
                        class="size-4 shrink-0 animate-bounce text-white"
                    />
                    <span>
                        <strong>Hệ thống chuẩn bị bảo trì định kỳ:</strong>
                        {{ upcomingMaintenance.title }}. Thời gian:
                        <strong>{{
                            new Date(
                                upcomingMaintenance.downtime_start,
                            ).toLocaleString('vi-VN')
                        }}</strong>
                        đến
                        <strong>{{
                            new Date(
                                upcomingMaintenance.downtime_end,
                            ).toLocaleString('vi-VN')
                        }}</strong
                        >. Ảnh hưởng:
                        <span
                            v-for="serv in upcomingMaintenance.services"
                            :key="serv"
                            class="mr-1 rounded bg-red-900/60 px-1.5 py-0.5 font-mono text-[10px] uppercase"
                            >{{ serv }}</span
                        >.
                        {{ upcomingMaintenance.banner_message }}
                    </span>
                </div>
            </div>

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

            <!-- Quota Alerting Banner -->
            <div
                v-if="isOwner && quotaWarnings.length > 0"
                class="flex w-full shrink-0 flex-col items-center justify-between gap-2 border-b border-orange-600/30 bg-gradient-to-r from-amber-500 to-orange-600 px-4 py-2.5 text-xs font-medium text-white shadow-sm sm:flex-row sm:text-sm"
            >
                <div class="flex items-center gap-2">
                    <AlertTriangle
                        class="size-4 shrink-0 animate-pulse text-white"
                    />
                    <span>
                        Bạn đã sử dụng gần hết hạn mức:
                        <span
                            v-for="(warn, idx) in quotaWarnings"
                            :key="warn.key"
                        >
                            <strong class="font-bold underline"
                                >{{ warn.used }}/{{ warn.limit }}
                                {{ warn.label }}</strong
                            >
                            ({{ warn.percentage }}%){{
                                idx < quotaWarnings.length - 1 ? ', ' : ''
                            }} </span
                        >. Nâng cấp gói dịch vụ để không làm gián đoạn trải
                        nghiệm của nhà hàng.
                    </span>
                </div>
                <button
                    @click="openUpgradeModal"
                    class="cursor-pointer rounded-lg bg-white px-3 py-1.5 text-xs font-bold whitespace-nowrap text-orange-700 shadow-sm transition-all hover:bg-orange-50"
                >
                    Nâng cấp gói ngay
                </button>
            </div>

            <!-- Shift Ending Warning Banner -->
            <div
                v-if="shiftWarningMinutes !== null"
                class="flex w-full shrink-0 items-center justify-between gap-2 border-b border-amber-600/30 bg-gradient-to-r from-amber-500 to-yellow-600 px-4 py-2.5 text-xs font-medium text-white shadow-sm sm:text-sm"
            >
                <div class="flex items-center gap-2">
                    <AlertTriangle
                        class="size-4 shrink-0 animate-bounce text-white"
                    />
                    <span>
                        <strong>Ca làm việc sắp kết thúc:</strong> Ca làm việc
                        của bạn sẽ kết thúc sau {{ shiftWarningMinutes }} phút.
                        Vui lòng hoàn thành các hóa đơn dở dang.
                    </span>
                </div>
            </div>

            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!-- key theo component: hiệu ứng chuyển trang chỉ chạy khi đổi sang trang khác,
                 form submit quay về cùng trang không bị re-mount mất state -->
            <div
                :key="String(page.component)"
                class="page-enter flex flex-1 flex-col"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
        <FlashToast />
        <ConfirmDialog />
        <QROrderAlertCenter />
        <GlobalCampaignListener />
        <OnboardingTour />
        <LazyChatbotWidget
            v-if="showChatbot && deferredWidgetsReady"
            source="support"
        />
        <LazyCommandPalette v-if="isSuperAdmin && deferredWidgetsReady" />

        <!-- Shift Expired Modal Overlay -->
        <div
            v-if="showShiftExpiredModal"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md"
        >
            <div
                class="w-full max-w-md animate-in space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-2xl duration-200 zoom-in-95 fade-in dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="flex items-center gap-3 text-red-600">
                    <AlertTriangle class="size-6 shrink-0 animate-pulse" />
                    <h3
                        class="text-lg font-bold text-slate-900 dark:text-white"
                    >
                        Ca làm việc đã kết thúc
                    </h3>
                </div>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Thời gian ca trực được xếp của bạn đã hết. Hệ thống sẽ tự
                    động đăng xuất để bảo mật thông tin.
                </p>
                <p
                    class="rounded-lg border border-amber-100 bg-amber-50 p-2.5 text-xs text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400"
                >
                    <strong>Lưu nháp:</strong> Giỏ hàng POS hoặc dữ liệu form
                    đang thực hiện đã được tự động lưu tạm trên trình duyệt của
                    bạn và sẽ khôi phục khi bạn vào ca trở lại.
                </p>
                <div class="flex justify-end pt-2">
                    <button
                        @click="confirmLogout"
                        class="w-full cursor-pointer rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white transition-colors hover:bg-red-700"
                    >
                        Đăng xuất ngay
                    </button>
                </div>
            </div>
        </div>
    </AppShell>
</template>
