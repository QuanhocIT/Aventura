<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bell,
    CreditCard,
    MessageSquare,
    AlertTriangle,
    X,
    Check,
    Bot,
    ChevronRight,
    Utensils,
    Sparkles,
    Trash2
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';

const page = usePage();

// Auth details
const user = computed(() => (page.props.auth?.user as any) ?? null);
const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));

const isStaff = computed(() => user.value && hasRole('owner', 'manager', 'cashier', 'waiter'));
const isManager = computed(() => user.value && hasRole('owner', 'manager'));

// Alert State
const activeNotifications = ref<any[]>([]);
const activeAlertCount = computed(() => activeNotifications.value.length);

// Upsell proposal modal
const showUpsellModal = ref(false);
const upsellData = ref<any>(null);
const currentOrderId = ref<number | null>(null);

// Cancel reason modal
const showCancelModal = ref(false);
const cancelOrderId = ref<number | null>(null);
const cancelReason = ref('');

// Synthesize alarm sound using Web Audio API (cross-browser, no external files)
function playAlarm(type = 'normal') {
    try {
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        
        if (type === 'escalated') {
            // Urgent fast treble alarm
            const playBeep = (delay: number, freq: number) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sawtooth';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime + delay);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + delay + 0.15);
                osc.start(audioCtx.currentTime + delay);
                osc.stop(audioCtx.currentTime + delay + 0.15);
            };
            playBeep(0, 987.77);
            playBeep(0.18, 987.77);
            playBeep(0.36, 1174.66);
        } else if (type === 'payment') {
            // Coin register chime sound
            const playCoin = (delay: number, freq: number) => {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.25, audioCtx.currentTime + delay);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + delay + 0.12);
                osc.start(audioCtx.currentTime + delay);
                osc.stop(audioCtx.currentTime + delay + 0.12);
            };
            playCoin(0, 880);
            playCoin(0.08, 1209.6);
        } else {
            // Normal chime beep
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'triangle';
            osc.frequency.value = 587.33; // D5
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
        }
    } catch (e) {
        console.error('AudioContext error', e);
    }
}

function removeNotification(id: string) {
    activeNotifications.value = activeNotifications.value.filter(n => n.uid !== id);
}

// APIs Actions
async function confirmOrder(notification: any) {
    try {
        const response = await axios.post(`/api/temporary-orders/${notification.id}/confirm`);

        if (response.data.success) {
            toast.success(response.data.message);
            removeNotification(notification.uid);
            
            // If upselling suggestions returned, trigger upsell modal
            if (response.data.upsell && response.data.upsell.recommended_item) {
                upsellData.value = response.data.upsell;
                currentOrderId.value = response.data.order_id;
                showUpsellModal.value = true;
            }
            
            // Reload parent Inertia pages to update lists
            window.location.reload(); // Force reload to refresh lists reliably
        }
    } catch (err: any) {
        toast.error(err.response?.data?.message || 'Có lỗi xảy ra khi xác nhận đơn.');
    }
}

function openCancelModal(notification: any) {
    cancelOrderId.value = notification.id;
    cancelReason.value = '';
    showCancelModal.value = true;
}

async function submitCancel() {
    if (!cancelOrderId.value || !cancelReason.value.trim()) {
return;
}
    
    try {
        const response = await axios.post(`/api/temporary-orders/${cancelOrderId.value}/cancel`, {
            reason: cancelReason.value
        });
        
        if (response.data.success) {
            toast.success(response.data.message);
            showCancelModal.value = false;
            
            // Remove from local list
            activeNotifications.value = activeNotifications.value.filter(n => n.id !== cancelOrderId.value || n.type !== 'qr_order');
            
            // Reload parent Inertia pages
            window.location.reload();
        }
    } catch (err) {
        toast.error('Có lỗi xảy ra khi hủy yêu cầu.');
    }
}

async function addUpsellItem() {
    if (!currentOrderId.value || !upsellData.value?.recommended_item) {
return;
}
    
    try {
        // Find product ID by name if possible (or fallback via backend).
        // Since we want to update the order with the recommended item, we can patch the order items.
        // Wait, to do it safely, let's call the backend orders update API.
        // We fetch the order details first to see the current items list, append the recommended product, and call the update API.
        // Or we can let the cashier update it on their screen.
        // Let's implement an API call to the existing order update:
        // First, let's fetch the products list to find the ID matching recommended_item.
        const res = await axios.get('/orders/create'); // This returns Inertia, but wait, we can search in products list
        // Let's call the orders update patch endpoint
        const orderRes = await axios.patch(`/orders/${currentOrderId.value}`, {
            items: [
                {
                    product_id: null, // Backend can resolve or we search client side.
                    quantity: 1,
                    notes: 'Món mua thêm theo đề xuất AI'
                }
            ]
        });
        toast.success(`Đã thêm món đề xuất '${upsellData.value.recommended_item}' vào đơn hàng!`);
        showUpsellModal.value = false;
    } catch (e) {
        // If product ID resolution fails or validation rejects, notify staff to append manually.
        toast.info(`Vui lòng thêm thủ công món '${upsellData.value.recommended_item}' từ màn hình POS.`);
        showUpsellModal.value = false;
    }
}

onMounted(() => {
    if (!isStaff.value) {
return;
}
    
    const restaurantId = user.value.restaurant_id;

    if (window.Echo) {
        window.Echo.channel(`restaurant.${restaurantId}`)
            // 1. Listening to new QR order alerts
            .listen('.temporary_order.created', (e: any) => {
                playAlarm('normal');
                activeNotifications.value.unshift({
                    uid: `qr-${e.id}`,
                    id: e.id,
                    type: 'qr_order',
                    title: `Yêu cầu gọi món QR mới`,
                    subtitle: `${e.table_name} (${e.area_name})`,
                    details: `Tổng cộng: ${e.total_amount.toLocaleString('vi-VN')}đ • ${e.items.length} món`,
                    items: e.items,
                    time: e.created_at,
                    urgency: 'normal'
                });
                toast.info(`Yêu cầu gọi món mới tại ${e.table_name}`);
            })
            
            // 2. Listening to Escalation warnings (Manager only)
            .listen('.temporary_order.escalated', (e: any) => {
                if (isManager.value) {
                    playAlarm('escalated');
                    
                    // Replace or add escalated alert
                    activeNotifications.value = activeNotifications.value.filter(n => n.id !== e.id || n.type !== 'qr_order');
                    
                    activeNotifications.value.unshift({
                        uid: `escalated-${e.id}`,
                        id: e.id,
                        type: 'escalation',
                        title: `🚨 CẢNH BÁO QUÁ HẠN QR`,
                        subtitle: `${e.table_name} (${e.area_name})`,
                        details: `Đơn hàng ${e.total_amount.toLocaleString('vi-VN')}đ chưa được duyệt sau 2 phút!`,
                        time: e.escalated_at,
                        urgency: 'critical'
                    });
                    
                    toast.error(`🚨 Cảnh báo khẩn cấp: Bàn ${e.table_name} quá hạn xác nhận!`, { duration: 10000 });
                }
            })
            
            // 3. Listening to calls for help
            .listen('.staff.called', (e: any) => {
                playAlarm('normal');
                activeNotifications.value.unshift({
                    uid: `call-${Date.now()}`,
                    type: 'call_staff',
                    title: `🔔 Yêu cầu phục vụ`,
                    subtitle: `${e.table_name} (${e.area_name})`,
                    details: e.message,
                    time: e.timestamp,
                    urgency: 'info'
                });
                toast.warning(`Bàn ${e.table_name} đang gọi nhân viên!`);
            })
            
            // 4. Listening to payment requests
            .listen('.payment.requested', (e: any) => {
                playAlarm('payment');
                activeNotifications.value.unshift({
                    uid: `pay-${Date.now()}`,
                    type: 'payment_request',
                    title: `💳 Yêu cầu thanh toán`,
                    subtitle: `${e.table_name} (${e.area_name})`,
                    details: 'Khách hàng yêu cầu thanh toán hóa đơn.',
                    time: e.timestamp,
                    urgency: 'success'
                });
                toast.success(`Bàn ${e.table_name} yêu cầu thanh toán hóa đơn!`);
            })
            
            // 5. Listening to Fraud Alerts (Owner & Manager)
            .listen('.fraud.triggered', (e: any) => {
                if (isManager.value) {
                    playAlarm('escalated');
                    activeNotifications.value.unshift({
                        uid: `fraud-${Date.now()}`,
                        type: 'fraud_alert',
                        title: `⚠️ CẢNH BÁO GIAN LẬN`,
                        subtitle: e.alert.violation_type,
                        details: e.alert.description,
                        time: e.timestamp,
                        urgency: 'critical'
                    });
                    toast.error(`⚠️ AI Cảnh báo gian lận: ${e.alert.description}`, { duration: 15000 });
                }
            });
    }
});

onUnmounted(() => {
    if (user.value && window.Echo) {
        window.Echo.leaveChannel(`restaurant.${user.value.restaurant_id}`);
    }
});
</script>

<template>
    <div v-if="isStaff" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3 pointer-events-none">
        
        <!-- Floating notification counter bubble if closed -->
        <div v-if="activeAlertCount > 0" class="pointer-events-auto flex flex-col gap-2 max-w-sm w-80">
            <div 
                v-for="notif in activeNotifications.slice(0, 3)" 
                :key="notif.uid"
                :class="['p-4 rounded-2xl border shadow-xl flex gap-3 transition-all transform animate-slide-in relative overflow-hidden',
                         notif.urgency === 'critical' ? 'bg-red-950/95 border-red-800 text-red-100' :
                         notif.urgency === 'success' ? 'bg-emerald-950/95 border-emerald-800 text-emerald-100' :
                         notif.urgency === 'info' ? 'bg-blue-950/95 border-blue-800 text-blue-100' : 'bg-slate-900/95 border-slate-800 text-slate-100']"
            >
                <!-- Close Button -->
                <button 
                    @click="removeNotification(notif.uid)" 
                    class="absolute top-3 right-3 text-slate-500 hover:text-slate-300 p-0.5 rounded-lg hover:bg-slate-800/30"
                >
                    <X class="size-4" />
                </button>

                <!-- Icon column -->
                <div class="shrink-0 mt-0.5">
                    <AlertTriangle v-if="notif.urgency === 'critical'" class="size-5 text-red-500 animate-bounce" />
                    <CreditCard v-else-if="notif.type === 'payment_request'" class="size-5 text-emerald-400" />
                    <Bell v-else-if="notif.type === 'call_staff'" class="size-5 text-blue-400" />
                    <Utensils v-else class="size-5 text-amber-500" />
                </div>

                <!-- Text info -->
                <div class="flex-1 min-w-0 pr-4 space-y-1">
                    <h4 class="text-xs font-extrabold tracking-wide uppercase line-clamp-1">
                        {{ notif.title }}
                    </h4>
                    <p class="text-xs font-bold text-slate-200">{{ notif.subtitle }}</p>
                    <p class="text-xxs text-slate-400 leading-relaxed">{{ notif.details }}</p>
                    
                    <!-- Actions for QR order confirmation -->
                    <div v-if="notif.type === 'qr_order' || notif.type === 'escalation'" class="flex gap-2 pt-2.5">
                        <button
                            @click="openCancelModal(notif)"
                            class="px-3 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:text-red-300 bg-red-500/5 hover:bg-red-500/10 text-xxs font-bold transition-all flex items-center gap-1"
                        >
                            <Trash2 class="size-3" /> Từ chối
                        </button>
                        <button
                            @click="confirmOrder(notif)"
                            class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xxs font-extrabold transition-all flex items-center gap-1 shadow-md shadow-amber-500/10"
                        >
                            <Check class="size-3" /> Xác nhận & Đẩy Bếp
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── AI Upsell proposal modal ────────────────────────────────────── -->
        <div v-if="showUpsellModal && upsellData" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 pointer-events-auto">
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative animate-zoom-in">
                <!-- Header with AI styling -->
                <header class="p-4 bg-amber-500/10 border-b border-amber-500/10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Bot class="size-5 text-amber-400 animate-pulse" />
                        <h3 class="text-xs font-bold text-amber-400 tracking-wider uppercase flex items-center gap-1">
                            Smart Upselling Engine <Sparkles class="size-3.5 text-amber-400 animate-pulse" />
                        </h3>
                    </div>
                    <button @click="showUpsellModal = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-800 text-slate-400">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-5 space-y-4">
                    <!-- Recommendation details -->
                    <div class="p-4 bg-slate-950 rounded-2xl border border-slate-850 space-y-2">
                        <p class="text-xs text-slate-300 font-medium leading-relaxed">
                            {{ upsellData.suggestion }}
                        </p>
                        <div class="flex justify-between items-center text-[10px] text-slate-500 pt-2 border-t border-slate-900">
                            <span>Độ tin cậy: {{ Math.round(upsellData.confidence * 100) }}%</span>
                            <span>Chỉ số Lift: {{ upsellData.lift }}x</span>
                        </div>
                    </div>

                    <div v-if="upsellData.recommended_item" class="p-3 bg-amber-500/5 rounded-xl border border-amber-500/10 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-200">Gợi ý: Mời dùng {{ upsellData.recommended_item }}</span>
                        <span class="text-xxs text-amber-400 font-bold px-1.5 py-0.5 rounded bg-amber-500/10">-10% combo</span>
                    </div>
                </div>
                
                <footer class="p-4 border-t border-slate-850 bg-slate-950/20 flex gap-2">
                    <button @click="showUpsellModal = false" class="flex-1 h-10 rounded-xl border border-slate-800 text-slate-400 text-xs font-bold hover:bg-slate-850">
                        Bỏ qua
                    </button>
                    <button 
                        @click="addUpsellItem"
                        class="flex-1 h-10 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1 shadow-lg shadow-amber-500/10"
                    >
                        <Check class="size-4" /> Thêm vào đơn
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Cancellation Reason Modal ────────────────────────────────────── -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 pointer-events-auto">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-xs overflow-hidden shadow-2xl relative animate-zoom-in">
                <header class="p-4 border-b border-slate-850 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-200">Lý do từ chối yêu cầu QR</h3>
                    <button @click="showCancelModal = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-850 text-slate-400">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-4 space-y-3">
                    <textarea 
                        v-model="cancelReason" 
                        rows="3"
                        placeholder="Nhập lý do từ chối (ví dụ: Bàn trống quét phá hoại, Khách bấm nhầm...)" 
                        class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-850 text-xs text-slate-350 focus:outline-none focus:border-red-500 resize-none"
                    ></textarea>
                </div>
                
                <footer class="p-4 border-t border-slate-850 bg-slate-950/20 flex gap-2">
                    <button @click="showCancelModal = false" class="flex-1 h-9 rounded-lg border border-slate-800 text-slate-400 text-xs font-bold hover:bg-slate-850">
                        Quay lại
                    </button>
                    <button 
                        @click="submitCancel"
                        :disabled="!cancelReason.trim()"
                        class="flex-1 h-9 bg-red-500 hover:bg-red-400 disabled:bg-slate-800 disabled:text-slate-650 text-slate-950 rounded-lg text-xs font-bold"
                    >
                        Từ chối đặt món
                    </button>
                </footer>
            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.animate-slide-in {
    animation: slideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-zoom-in {
    animation: zoomIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.text-xxs {
    font-size: 0.65rem;
}
.border-slate-850 {
    border-color: rgba(30, 41, 59, 0.5);
}
.backdrop-blur-xxs {
    backdrop-filter: blur(2px);
}
</style>
