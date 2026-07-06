<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Award,
    CheckCircle,
    Clock,
    Gift,
    Calendar,
    Users,
    ChevronDown,
    ChevronUp,
    Utensils,
    ShoppingBag,
    TrendingUp,
    Loader2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';

interface Reward {
    id: number;
    name: string;
    value: number;
    image_url: string | null;
    points_cost: number;
}

interface OrderItem {
    name: string;
    quantity: number;
    price: number;
}

interface OrderHistory {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total_amount: number;
    created_at: string;
    items: OrderItem[];
}

const props = defineProps<{
    restaurant: { id: number; name: string };
    customer: {
        id: number;
        full_name: string;
        phone: string;
        membership_level: string;
        loyalty_points: number;
    };
    orders: OrderHistory[];
    rewards: Reward[];
}>();

const portalToken = new URLSearchParams(window.location.search).get('token') ?? '';

// Navigation back to QR Menu
const goBack = () => {
    const lastUrl = localStorage.getItem('last_qr_order_url');

    if (lastUrl) {
        window.location.href = lastUrl;
    } else {
        window.history.back();
    }
};

// Form states for Pre-order & Reservation
const isReserving = ref(false);
const reservationName = ref(props.customer.full_name || '');
const reservationPhone = ref(props.customer.phone || '');
const reservationTime = ref('');
const guestsCount = ref(2);
const reservationNotes = ref('');

const submitReservation = async () => {
    if (!reservationTime.value) {
        toast.error('Vui lòng chọn thời gian đặt bàn');

        return;
    }

    isReserving.value = true;

    try {
        const res = await axios.post(`/customer/portal/reserve/${props.restaurant.id}`, {
            customer_name: reservationName.value,
            customer_phone: reservationPhone.value,
            reservation_time: reservationTime.value,
            guests_count: guestsCount.value,
            items: [] // Pre-orders can be configured in future phase
        });

        if (res.data.success) {
            toast.success(res.data.message);
            reservationTime.value = '';
            reservationNotes.value = '';
        } else {
            toast.error(res.data.message || 'Lỗi khi đặt bàn');
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra, vui lòng kiểm tra lại');
    } finally {
        isReserving.value = false;
    }
};

// Redemption logic
const isRedeeming = ref<number | null>(null);
const currentPoints = ref(props.customer.loyalty_points);

const redeem = async (reward: Reward) => {
    if (currentPoints.value < reward.points_cost) {
        toast.error('Bạn không đủ điểm để đổi phần thưởng này');

        return;
    }

    isRedeeming.value = reward.id;

    try {
        const res = await axios.post(`/customer/portal/redeem/${props.restaurant.id}/${props.customer.phone}?token=${encodeURIComponent(portalToken)}`, {
            reward_id: reward.id,
        });

        if (res.data.success) {
            currentPoints.value = res.data.new_points;
            toast.success(res.data.message || `Đổi thành công: ${reward.name}!`);
        } else {
            toast.error(res.data.message || 'Lỗi đổi quà');
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi đổi quà');
    } finally {
        isRedeeming.value = null;
    }
};

// Expandable order states
const expandedOrders = ref<Record<number, boolean>>({});
const toggleOrder = (orderId: number) => {
    expandedOrders.value[orderId] = !expandedOrders.value[orderId];
};

const formatCurrency = (val: number) => {
    return val.toLocaleString('vi-VN') + 'đ';
};

const formatDate = (dateStr: string) => {
    const d = new Date(dateStr);

    return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')} ${d.getDate().toString().padStart(2, '0')}/${(d.getMonth() + 1).toString().padStart(2, '0')}/${d.getFullYear()}`;
};

const translateStatus = (status: string) => {
    const statuses: Record<string, string> = {
        pending: 'Chờ xử lý',
        confirmed: 'Đã xác nhận',
        preparing: 'Đang chuẩn bị',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy'
    };

    return statuses[status] || status;
};
</script>

<template>
    <Head :title="`Cổng Hội Viên - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col font-sans max-w-md mx-auto relative border-x border-slate-200/80 shadow-[0_0_50px_rgba(0,0,0,0.03)] pb-10">
        <!-- ── Top Header ────────────────────────────────────────────── -->
        <header class="sticky top-0 z-30 bg-white/85 backdrop-blur-md border-b border-slate-100 px-5 py-4 flex items-center gap-4 shadow-sm">
            <button @click="goBack" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-all cursor-pointer">
                <ArrowLeft class="size-4.5" />
            </button>
            <div>
                <h1 class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ restaurant.name }}</h1>
                <p class="text-[10px] text-amber-600 font-extrabold uppercase tracking-widest">Cổng thành viên ưu tú</p>
            </div>
        </header>

        <main class="p-5 space-y-6">
            <!-- ── Membership Card ────────────────────────────────────────── -->
            <div class="bg-gradient-to-br from-slate-900 via-slate-850 to-slate-950 text-white rounded-[26px] p-5 shadow-lg relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 opacity-10">
                    <Award class="size-36 text-white" />
                </div>
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-[9px] font-black tracking-widest uppercase text-slate-400">Thành viên liên kết</span>
                        <h2 class="text-base font-black tracking-tight mt-0.5">{{ customer.full_name }}</h2>
                        <p class="text-xs text-slate-400 font-medium tracking-wide mt-0.5">{{ customer.phone }}</p>
                    </div>
                    <span :class="[
                        'px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shadow-sm border',
                        customer.membership_level === 'diamond' ? 'bg-indigo-600/80 border-indigo-400 text-white' :
                        customer.membership_level === 'gold' ? 'bg-amber-500/80 border-amber-300 text-white' :
                        'bg-slate-700/80 border-slate-500 text-white'
                    ]">
                        {{ customer.membership_level === 'diamond' ? '💎 Kim Cương' : customer.membership_level === 'gold' ? '⭐ Vàng' : '🥈 Bạc' }}
                    </span>
                </div>

                <div class="mt-8">
                    <div class="flex justify-between items-end mb-1.5">
                        <span class="text-xxs text-slate-400 font-bold uppercase tracking-wider">Điểm khả dụng</span>
                        <span class="text-lg font-black text-amber-400 tracking-tight">{{ currentPoints }} <span class="text-[10px] font-bold text-slate-300">pt</span></span>
                    </div>
                    <!-- Points Bar -->
                    <div class="w-full h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-300 rounded-full" :style="{ width: `${Math.min(100, (currentPoints / 1000) * 100)}%` }"></div>
                    </div>
                    <div class="flex justify-between text-[9px] text-slate-500 mt-1 font-bold">
                        <span>0 pt</span>
                        <span>Đạt 1,000 pt để nhận đặc quyền VIP</span>
                    </div>
                </div>
            </div>

            <!-- ── Loyalty point Redemption Center ───────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <Gift class="size-4.5 text-amber-500" />
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Đổi điểm lấy quà tặng</h3>
                </div>
                
                <div v-if="rewards.length === 0" class="p-5 text-center text-xs text-slate-400 bg-white border border-slate-200 rounded-2xl">
                    Hiện chưa có chương trình đổi quà khả dụng.
                </div>

                <div v-else class="grid grid-cols-1 gap-3.5">
                    <div v-for="reward in rewards" :key="reward.id" class="p-4 bg-white border border-slate-200 rounded-2xl flex gap-3.5 items-center shadow-sm relative">
                        <div class="size-16 rounded-xl bg-slate-100 overflow-hidden shrink-0 border border-slate-200">
                            <img v-if="reward.image_url" :src="reward.image_url" :alt="reward.name" class="size-full object-cover" />
                            <div v-else class="size-full flex items-center justify-center text-slate-400"><Utensils class="size-5" /></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-black text-slate-800 line-clamp-1 leading-snug">{{ reward.name }}</h4>
                            <p class="text-[10px] text-slate-400 font-bold mt-0.5">Trị giá: {{ formatCurrency(reward.value) }}</p>

                            <div class="flex items-center justify-between mt-2">
                                <span class="text-[11px] font-black text-amber-600 bg-amber-50 px-2 py-0.5 rounded-lg border border-amber-100">{{ reward.points_cost }} pt</span>

                                <button
                                    @click="redeem(reward)"
                                    :disabled="currentPoints < reward.points_cost || isRedeeming !== null"
                                    class="h-8 px-4 rounded-xl text-xxs font-black tracking-wider uppercase bg-slate-900 hover:bg-slate-800 text-white disabled:bg-slate-100 disabled:text-slate-400 cursor-pointer transition-all flex items-center justify-center gap-1.5 shadow-sm active:scale-95"
                                >
                                    <Loader2 v-if="isRedeeming === reward.id" class="size-3 animate-spin" />
                                    <span>Đổi quà</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ── Pre-order & Reservation (Đặt bàn từ xa) ────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <Calendar class="size-4.5 text-indigo-500" />
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Đặt bàn trước từ xa</h3>
                </div>

                <form @submit.prevent="submitReservation" class="bg-white p-5 border border-slate-200 rounded-[26px] space-y-4 shadow-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 block mb-1 uppercase tracking-wider">Họ & Tên</label>
                            <input 
                                v-model="reservationName" 
                                type="text" 
                                required
                                placeholder="Ví dụ: Anh Quân" 
                                class="w-full h-10 px-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 transition-all shadow-inner"
                            />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 block mb-1 uppercase tracking-wider">Số điện thoại</label>
                            <input 
                                v-model="reservationPhone" 
                                type="text" 
                                required
                                placeholder="090..." 
                                class="w-full h-10 px-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 transition-all shadow-inner"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 block mb-1 uppercase tracking-wider">Thời gian đến</label>
                            <input 
                                v-model="reservationTime" 
                                type="datetime-local" 
                                required
                                class="w-full h-10 px-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-850 focus:outline-none focus:border-slate-800 transition-all shadow-inner"
                            />
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 block mb-1 uppercase tracking-wider">Số lượng khách</label>
                            <div class="flex items-center h-10 bg-slate-50 rounded-xl border border-slate-200 px-2 justify-between">
                                <button type="button" @click="guestsCount = Math.max(1, guestsCount - 1)" class="p-1 hover:text-amber-500 font-black text-slate-500">-</button>
                                <span class="text-xs font-black text-slate-800">{{ guestsCount }} khách</span>
                                <button type="button" @click="guestsCount++" class="p-1 hover:text-amber-500 font-black text-slate-500">+</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="text-[9px] font-black text-slate-400 block mb-1 uppercase tracking-wider">Yêu cầu đặc biệt (tùy chọn)</label>
                        <textarea 
                            v-model="reservationNotes" 
                            rows="2"
                            placeholder="Ví dụ: Bàn gần cửa sổ, ăn kiêng..." 
                            class="w-full p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-slate-800 resize-none shadow-inner"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        :disabled="isReserving"
                        class="w-full h-11 bg-slate-900 hover:bg-slate-800 disabled:bg-slate-200 text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                    >
                        <Loader2 v-if="isReserving" class="size-4 animate-spin" />
                        <span v-else>Đăng ký đặt bàn & món trước</span>
                    </button>
                </form>
            </section>

            <!-- ── Order History ────────────────────────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <ShoppingBag class="size-4.5 text-indigo-500" />
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Lịch sử giao dịch</h3>
                </div>

                <div v-if="orders.length === 0" class="p-5 text-center text-xs text-slate-400 bg-white border border-slate-200 rounded-2xl">
                    Chưa ghi nhận lịch sử đơn hàng nào.
                </div>

                <div v-else class="space-y-3">
                    <div v-for="order in orders" :key="order.id" class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                        <header @click="toggleOrder(order.id)" class="p-4 flex justify-between items-center cursor-pointer hover:bg-slate-50/50 transition-colors">
                            <div>
                                <h4 class="text-xs font-black text-slate-800">Đơn hàng #{{ order.order_number }}</h4>
                                <p class="text-[9px] text-slate-400 mt-0.5 font-bold">{{ formatDate(order.created_at) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="[
                                    'px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wide border',
                                    order.status === 'completed' ? 'bg-emerald-50 text-emerald-600 border-emerald-250' :
                                    order.status === 'cancelled' ? 'bg-slate-100 text-slate-400 border-slate-200' :
                                    'bg-amber-50 text-amber-600 border-amber-250 animate-pulse'
                                ]">
                                    {{ translateStatus(order.status) }}
                                </span>
                                <ChevronDown v-if="!expandedOrders[order.id]" class="size-4 text-slate-400" />
                                <ChevronUp v-else class="size-4 text-slate-400" />
                            </div>
                        </header>

                        <!-- Expanded details -->
                        <div v-if="expandedOrders[order.id]" class="p-4 border-t border-slate-100 bg-slate-50/50 space-y-3 text-xxs">
                            <div class="space-y-1.5 bg-white p-3 rounded-xl border border-slate-200 shadow-inner">
                                <div v-for="(item, idx) in order.items" :key="idx" class="flex justify-between">
                                    <span class="font-medium text-slate-700">{{ item.quantity }}x {{ item.name }}</span>
                                    <span class="font-bold text-slate-800">{{ formatCurrency(item.price * item.quantity) }}</span>
                                </div>
                            </div>

                            <div class="flex justify-between items-center font-bold text-xs pt-1">
                                <span class="text-slate-500 font-black">Tổng thanh toán:</span>
                                <span class="text-amber-600 font-black">{{ formatCurrency(order.total_amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.size-4\.5 {
    width: 1.125rem;
    height: 1.125rem;
}
.size-7\.5 {
    width: 1.875rem;
    height: 1.875rem;
}
</style>
