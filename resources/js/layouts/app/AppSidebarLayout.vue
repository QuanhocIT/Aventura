<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import FlashToast from '@/components/FlashToast.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import echo from '@/lib/echo';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const isImpersonating = computed(() => !!page.props.is_impersonating);
const user = computed(() => (page.props.auth as any)?.user);

function playNotificationSound() {
    try {
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const now = audioCtx.currentTime;
        
        // Note 1: A5 (880Hz)
        const osc1 = audioCtx.createOscillator();
        const gain1 = audioCtx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(880, now);
        gain1.gain.setValueAtTime(0.25, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(audioCtx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);
        
        // Note 2: C#6 (1109.73Hz)
        const osc2 = audioCtx.createOscillator();
        const gain2 = audioCtx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1109.73, now + 0.12);
        gain2.gain.setValueAtTime(0.25, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
        osc2.connect(gain2);
        gain2.connect(audioCtx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.5);
    } catch (e) {
        console.error('Failed to play notification chime', e);
    }
}

onMounted(() => {
    if (echo && user.value && user.value.restaurant_id) {
        echo.private(`restaurant.${user.value.restaurant_id}`)
            .listen('.qr-order.placed', (e: any) => {
                playNotificationSound();
                
                const isThirdParty = !!e.order.third_party_source;
                const toastTitle = isThirdParty 
                    ? `Đơn hàng mới từ đối tác ${e.order.third_party_source}!`
                    : `Khách tại bàn ${e.order.table_name || '—'} vừa gọi món qua QR!`;

                toast.warning(toastTitle, {
                    description: `Mã đơn: ${e.order.order_number} - Số tiền: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(e.order.total_amount)}`,
                    action: {
                        label: 'Xem đơn',
                        onClick: () => {
                            router.visit('/orders?status=pending');
                        }
                    },
                    duration: 10000,
                });

                if (window.location.pathname === '/orders') {
                    router.reload({ only: ['orders', 'summary'] });
                }
            });
    }
});

onUnmounted(() => {
    if (echo && user.value && user.value.restaurant_id) {
        echo.leave(`restaurant.${user.value.restaurant_id}`);
    }
});
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
