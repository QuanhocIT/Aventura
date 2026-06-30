<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChatbotWidget from '@/components/ChatbotWidget.vue';
import FlashToast from '@/components/FlashToast.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import { Toaster } from '@/components/ui/sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { ShoppingBag, Bell, AlertTriangle, Check, X, ShieldAlert } from 'lucide-vue-next';
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
        const audioCtx = new (
            window.AudioContext || (window as any).webkitAudioContext
        )();
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

const roles = computed(() => (page.props as any).roles ?? []);
const isOwnerOrManager = computed(
    () => roles.value.includes('owner') || roles.value.includes('manager'),
);
const canReceiveQrAlerts = computed(
    () => roles.value.includes('cashier') || roles.value.includes('order_staff') || isOwnerOrManager.value
);

const isNewOrderAlertOpen = ref(false);
const activeAlertOrder = ref<any>(null);
const confirmingOrder = ref(false);
const cancellingOrder = ref(false);

let alarmInterval: any = null;

function playAlarmSound() {
    if (alarmInterval) {
        return;
    }
    
    const runSound = () => {
        try {
            const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
            const now = audioCtx.currentTime;
            
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(880, now);
            osc.frequency.setValueAtTime(440, now + 0.3);
            osc.frequency.setValueAtTime(880, now + 0.6);
            osc.frequency.setValueAtTime(440, now + 0.9);
            
            gain.gain.setValueAtTime(0.2, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
            
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            
            osc.start(now);
            osc.stop(now + 1.2);
        } catch (err) {
            console.error('Failed to play alarm chime', err);
        }
    };
    
    runSound();
    alarmInterval = setInterval(runSound, 1500);
}

function stopAlarmSound() {
    if (alarmInterval) {
        clearInterval(alarmInterval);
        alarmInterval = null;
    }
}

const handleConfirmQrOrder = async () => {
    if (!activeAlertOrder.value || confirmingOrder.value) {
        return;
    }
    
    confirmingOrder.value = true;
    try {
        const response = await axios.post(`/orders/${activeAlertOrder.value.id}/confirm-qr`);
        if (response.data.success) {
            toast.success('Xác nhận và chuyển đơn xuống bếp thành công!');
            stopAlarmSound();
            isNewOrderAlertOpen.value = false;
            activeAlertOrder.value = null;
            router.reload({ only: ['orders', 'summary'] });
        } else {
            toast.error(response.data.message || 'Xác nhận đơn thất bại.');
        }
    } catch (e: any) {
        console.error(e);
        toast.error(e.response?.data?.message || 'Lỗi khi xác nhận đơn.');
    } finally {
        confirmingOrder.value = false;
    }
};

const handleCancelSpamOrder = async () => {
    if (!activeAlertOrder.value || cancellingOrder.value) {
        return;
    }

    if (!confirm('Bạn có chắc chắn muốn hủy và báo cáo đơn hàng ảo này không? Lệnh này sẽ xóa sạch dữ liệu đơn hàng.')) {
        return;
    }

    cancellingOrder.value = true;
    try {
        const response = await axios.post(`/orders/${activeAlertOrder.value.id}/cancel-spam`);
        if (response.data.success) {
            toast.warning('Đã hủy đơn hàng ảo và gửi báo cáo về Quản lý!');
            stopAlarmSound();
            isNewOrderAlertOpen.value = false;
            activeAlertOrder.value = null;
            router.reload({ only: ['orders', 'summary'] });
        } else {
            toast.error(response.data.message || 'Không thể hủy đơn.');
        }
    } catch (e: any) {
        console.error(e);
        toast.error(e.response?.data?.message || 'Lỗi khi hủy đơn hàng ảo.');
    } finally {
        cancellingOrder.value = false;
    }
};

onMounted(() => {
    if (echo && user.value && user.value.restaurant_id) {
        echo.private(`restaurant.${user.value.restaurant_id}`)
            .listen('.qr-order.placed', (e: any) => {
                const isThirdParty = !!e.order.third_party_source;

                if (isThirdParty) {
                    playNotificationSound();
                    toast.warning(`Đơn hàng mới từ đối tác ${e.order.third_party_source}!`, {
                        description: `Mã đơn: ${e.order.order_number} - Số tiền: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(e.order.total_amount)}`,
                        action: {
                            label: 'Xem đơn',
                            onClick: () => {
                                router.visit('/orders?status=pending');
                            },
                        },
                        duration: 10000,
                    });
                } else {
                    if (canReceiveQrAlerts.value) {
                        activeAlertOrder.value = e.order;
                        isNewOrderAlertOpen.value = true;
                        playAlarmSound();
                    } else {
                        playNotificationSound();
                        toast.warning(`Khách tại bàn ${e.order.table_name || '—'} vừa gọi món qua QR!`, {
                            description: `Mã đơn: ${e.order.order_number} - Số tiền: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(e.order.total_amount)}`,
                            action: {
                                label: 'Xem đơn',
                                onClick: () => {
                                    router.visit('/orders?status=pending');
                                },
                            },
                            duration: 10000,
                        });
                    }
                }

                if (window.location.pathname === '/orders') {
                    router.reload({ only: ['orders', 'summary'] });
                }
            })
            .listen('.order.split', (e: any) => {
                if (isOwnerOrManager.value) {
                    playNotificationSound();

                    toast.error(`Cảnh báo: Đơn hàng vừa bị tách!`, {
                        description: `Bàn ${e.original_table_name || '—'} (${e.original_order_number}) tách sang Bàn ${e.new_table_name || '—'} (${e.new_order_number}) bởi ${e.split_by || 'Nhân viên'}`,
                        duration: 10000,
                    });
                }
            })
            .listen('.spam-order.cancelled', (e: any) => {
                if (isOwnerOrManager.value) {
                    playNotificationSound();

                    toast.error(`CẢNH BÁO SPAM: Đơn hàng ảo bị hủy!`, {
                        description: `Mã đơn: ${e.order_data.order_number} tại ${e.order_data.table_name} (${e.order_data.area_name}) - Trị giá: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(e.order_data.total_amount)} - Hủy bởi: ${e.cancelled_by}`,
                        duration: 15000,
                    });
                }
            })
            .listen('.purchase-order.created', (e: any) => {
                if (
                    isOwnerOrManager.value ||
                    roles.value.includes('inventory_staff')
                ) {
                    playNotificationSound();

                    toast.info(
                        `Yêu cầu đặt hàng PO mới: ${e.purchase_order.po_number}`,
                        {
                            description: `Nhà cung cấp: ${e.purchase_order.supplier_name} - Số tiền: ${new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(e.purchase_order.total_amount)}`,
                            action: {
                                label: 'Xem đơn PO',
                                onClick: () => {
                                    router.visit('/suppliers?tab=po');
                                },
                            },
                            duration: 10000,
                        },
                    );
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

        <!-- REALTIME QR ORDER PENDING ALERT DIALOG -->
        <Dialog :open="isNewOrderAlertOpen" @update:open="(val) => { if(!val) stopAlarmSound(); isNewOrderAlertOpen = val; }">
            <DialogContent class="max-w-md border-rose-200 bg-white/95 shadow-2xl backdrop-blur-md dark:border-rose-950 dark:bg-slate-900/95 sm:rounded-3xl">
                <DialogHeader class="flex flex-col items-center text-center">
                    <div class="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-500 shadow-inner dark:bg-rose-950/50 dark:text-rose-400">
                        <Bell class="size-8 animate-bounce" />
                        <span class="absolute top-0 right-0 flex h-3.5 w-3.5">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex h-3.5 w-3.5 rounded-full bg-rose-500"></span>
                        </span>
                    </div>
                    <DialogTitle class="mt-4 text-xl font-black tracking-tight text-rose-600 dark:text-rose-400">
                        YÊU CẦU ĐẶT MÓN TẠI BÀN
                    </DialogTitle>
                    <DialogDescription class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                        Vui lòng kiểm tra và xác nhận món trực tiếp tại bàn khách
                    </DialogDescription>
                </DialogHeader>

                <div v-if="activeAlertOrder" class="mt-4 space-y-4">
                    <!-- Bill Summary -->
                    <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-4 dark:border-rose-950/60 dark:bg-rose-950/20">
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Bàn & Khu vực</span>
                                <span class="text-sm font-extrabold text-slate-800 dark:text-slate-200">
                                    {{ activeAlertOrder.table_name || '—' }} ({{ activeAlertOrder.area_name || 'Khu vực chính' }})
                                </span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Mã đơn hàng</span>
                                <span class="font-mono text-sm font-extrabold text-slate-800 dark:text-slate-200">
                                    {{ activeAlertOrder.order_number }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Thời gian gửi</span>
                                <span class="text-xs font-extrabold text-slate-700 dark:text-slate-350">
                                    {{ activeAlertOrder.created_at }}
                                </span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-400 uppercase">Tổng tiền đơn đệm</span>
                                <span class="text-sm font-black text-rose-600 dark:text-rose-400">
                                    {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(activeAlertOrder.total_amount) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="space-y-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Chi tiết món ăn ({{ activeAlertOrder.items_count }} món)</span>
                        <div class="max-h-40 overflow-y-auto divide-y border rounded-2xl p-2.5 bg-slate-50/30 dark:bg-slate-900/50">
                            <div v-for="item in activeAlertOrder.items" :key="item.id" class="flex items-start justify-between py-2 text-xs">
                                <div class="flex-grow pr-2">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ item.product_name }}</span>
                                    <span v-if="item.notes" class="block text-[10px] text-slate-400 italic">"{{ item.notes }}"</span>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-semibold text-slate-600 dark:text-slate-400">x{{ item.quantity }}</span>
                                    <span class="block text-[10px] font-medium text-slate-400">
                                        {{ new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(item.price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-between sm:gap-0">
                    <Button 
                        variant="outline" 
                        size="sm" 
                        class="h-10 rounded-xl border-slate-200 text-slate-500 hover:bg-slate-50 font-bold dark:border-slate-800 dark:text-slate-400 dark:hover:bg-slate-800" 
                        @click="handleCancelSpamOrder"
                        :disabled="confirmingOrder || cancellingOrder"
                    >
                        <X class="size-4 mr-1.5" />
                        Hủy yêu cầu (Spam)
                    </Button>
                    <Button 
                        size="sm" 
                        class="h-10 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 font-bold text-white shadow-md hover:from-emerald-700 hover:to-teal-700" 
                        @click="handleConfirmQrOrder"
                        :disabled="confirmingOrder || cancellingOrder"
                    >
                        <Check class="size-4 mr-1.5" />
                        Xác nhận & Chuyển bếp
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppShell>
</template>
