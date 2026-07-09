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
    Loader2,
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

const portalToken =
    new URLSearchParams(window.location.search).get('token') ?? '';

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
        const res = await axios.post(
            `/customer/portal/reserve/${props.restaurant.id}`,
            {
                customer_name: reservationName.value,
                customer_phone: reservationPhone.value,
                reservation_time: reservationTime.value,
                guests_count: guestsCount.value,
                items: [], // Pre-orders can be configured in future phase
            },
        );

        if (res.data.success) {
            toast.success(res.data.message);
            reservationTime.value = '';
            reservationNotes.value = '';
        } else {
            toast.error(res.data.message || 'Lỗi khi đặt bàn');
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra, vui lòng kiểm tra lại',
        );
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
        const res = await axios.post(
            `/customer/portal/redeem/${props.restaurant.id}/${props.customer.phone}?token=${encodeURIComponent(portalToken)}`,
            {
                reward_id: reward.id,
            },
        );

        if (res.data.success) {
            currentPoints.value = res.data.new_points;
            toast.success(
                res.data.message || `Đổi thành công: ${reward.name}!`,
            );
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
        cancelled: 'Đã hủy',
    };

    return statuses[status] || status;
};
</script>

<template>
    <Head :title="`Cổng Hội Viên - ${restaurant.name}`" />

    <div
        class="relative mx-auto flex min-h-screen max-w-md flex-col border-x border-slate-200/80 bg-slate-50 pb-10 font-sans text-slate-800 shadow-[0_0_50px_rgba(0,0,0,0.03)]"
    >
        <!-- ── Top Header ────────────────────────────────────────────── -->
        <header
            class="sticky top-0 z-30 flex items-center gap-4 border-b border-slate-100 bg-white/85 px-5 py-4 shadow-sm backdrop-blur-md"
        >
            <button
                @click="goBack"
                class="cursor-pointer rounded-xl bg-slate-100 p-2 text-slate-600 transition-all hover:bg-slate-200"
            >
                <ArrowLeft class="size-4.5" />
            </button>
            <div>
                <h1
                    class="text-sm font-black tracking-tight text-slate-800 uppercase"
                >
                    {{ restaurant.name }}
                </h1>
                <p
                    class="text-[10px] font-extrabold tracking-widest text-amber-600 uppercase"
                >
                    Cổng thành viên ưu tú
                </p>
            </div>
        </header>

        <main class="space-y-6 p-5">
            <!-- ── Membership Card ────────────────────────────────────────── -->
            <div
                class="via-slate-850 relative overflow-hidden rounded-[26px] bg-gradient-to-br from-slate-900 to-slate-950 p-5 text-white shadow-lg"
            >
                <div class="absolute -right-6 -bottom-6 opacity-10">
                    <Award class="size-36 text-white" />
                </div>

                <div class="flex items-start justify-between">
                    <div>
                        <span
                            class="text-[9px] font-black tracking-widest text-slate-400 uppercase"
                            >Thành viên liên kết</span
                        >
                        <h2 class="mt-0.5 text-base font-black tracking-tight">
                            {{ customer.full_name }}
                        </h2>
                        <p
                            class="mt-0.5 text-xs font-medium tracking-wide text-slate-400"
                        >
                            {{ customer.phone }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'rounded-full border px-3 py-1 text-[10px] font-black tracking-wider uppercase shadow-sm',
                            customer.membership_level === 'diamond'
                                ? 'border-indigo-400 bg-indigo-600/80 text-white'
                                : customer.membership_level === 'gold'
                                  ? 'border-amber-300 bg-amber-500/80 text-white'
                                  : 'border-slate-500 bg-slate-700/80 text-white',
                        ]"
                    >
                        {{
                            customer.membership_level === 'diamond'
                                ? '💎 Kim Cương'
                                : customer.membership_level === 'gold'
                                  ? '⭐ Vàng'
                                  : '🥈 Bạc'
                        }}
                    </span>
                </div>

                <div class="mt-8">
                    <div class="mb-1.5 flex items-end justify-between">
                        <span
                            class="text-xxs font-bold tracking-wider text-slate-400 uppercase"
                            >Điểm khả dụng</span
                        >
                        <span
                            class="text-lg font-black tracking-tight text-amber-400"
                            >{{ currentPoints }}
                            <span class="text-[10px] font-bold text-slate-300"
                                >pt</span
                            ></span
                        >
                    </div>
                    <!-- Points Bar -->
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-white/10"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-amber-400 to-amber-300"
                            :style="{
                                width: `${Math.min(100, (currentPoints / 1000) * 100)}%`,
                            }"
                        ></div>
                    </div>
                    <div
                        class="mt-1 flex justify-between text-[9px] font-bold text-slate-500"
                    >
                        <span>0 pt</span>
                        <span>Đạt 1,000 pt để nhận đặc quyền VIP</span>
                    </div>
                </div>
            </div>

            <!-- ── Loyalty point Redemption Center ───────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <Gift class="size-4.5 text-amber-500" />
                    <h3
                        class="text-xs font-black tracking-widest text-slate-800 uppercase"
                    >
                        Đổi điểm lấy quà tặng
                    </h3>
                </div>

                <div
                    v-if="rewards.length === 0"
                    class="rounded-2xl border border-slate-200 bg-white p-5 text-center text-xs text-slate-400"
                >
                    Hiện chưa có chương trình đổi quà khả dụng.
                </div>

                <div v-else class="grid grid-cols-1 gap-3.5">
                    <div
                        v-for="reward in rewards"
                        :key="reward.id"
                        class="relative flex items-center gap-3.5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div
                            class="size-16 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
                        >
                            <img
                                v-if="reward.image_url"
                                :src="reward.image_url"
                                :alt="reward.name"
                                class="size-full object-cover"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center text-slate-400"
                            >
                                <Utensils class="size-5" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4
                                class="line-clamp-1 text-xs leading-snug font-black text-slate-800"
                            >
                                {{ reward.name }}
                            </h4>
                            <p
                                class="mt-0.5 text-[10px] font-bold text-slate-400"
                            >
                                Trị giá: {{ formatCurrency(reward.value) }}
                            </p>

                            <div class="mt-2 flex items-center justify-between">
                                <span
                                    class="rounded-lg border border-amber-100 bg-amber-50 px-2 py-0.5 text-[11px] font-black text-amber-600"
                                    >{{ reward.points_cost }} pt</span
                                >

                                <button
                                    @click="redeem(reward)"
                                    :disabled="
                                        currentPoints < reward.points_cost ||
                                        isRedeeming !== null
                                    "
                                    class="text-xxs flex h-8 cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-slate-900 px-4 font-black tracking-wider text-white uppercase shadow-sm transition-all hover:bg-slate-800 active:scale-95 disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <Loader2
                                        v-if="isRedeeming === reward.id"
                                        class="size-3 animate-spin"
                                    />
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
                    <h3
                        class="text-xs font-black tracking-widest text-slate-800 uppercase"
                    >
                        Đặt bàn trước từ xa
                    </h3>
                </div>

                <form
                    @submit.prevent="submitReservation"
                    class="space-y-4 rounded-[26px] border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >Họ & Tên</label
                            >
                            <input
                                v-model="reservationName"
                                type="text"
                                required
                                placeholder="Ví dụ: Anh Quân"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs text-slate-800 shadow-inner transition-all focus:border-slate-800 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >Số điện thoại</label
                            >
                            <input
                                v-model="reservationPhone"
                                type="text"
                                required
                                placeholder="090..."
                                class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs text-slate-800 shadow-inner transition-all focus:border-slate-800 focus:outline-none"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label
                                class="mb-1 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >Thời gian đến</label
                            >
                            <input
                                v-model="reservationTime"
                                type="datetime-local"
                                required
                                class="text-slate-850 h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs shadow-inner transition-all focus:border-slate-800 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                                >Số lượng khách</label
                            >
                            <div
                                class="flex h-10 items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-2"
                            >
                                <button
                                    type="button"
                                    @click="
                                        guestsCount = Math.max(
                                            1,
                                            guestsCount - 1,
                                        )
                                    "
                                    class="p-1 font-black text-slate-500 hover:text-amber-500"
                                >
                                    -
                                </button>
                                <span class="text-xs font-black text-slate-800"
                                    >{{ guestsCount }} khách</span
                                >
                                <button
                                    type="button"
                                    @click="guestsCount++"
                                    class="p-1 font-black text-slate-500 hover:text-amber-500"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-[9px] font-black tracking-wider text-slate-400 uppercase"
                            >Yêu cầu đặc biệt (tùy chọn)</label
                        >
                        <textarea
                            v-model="reservationNotes"
                            rows="2"
                            placeholder="Ví dụ: Bàn gần cửa sổ, ăn kiêng..."
                            class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 shadow-inner focus:border-slate-800 focus:outline-none"
                        ></textarea>
                    </div>

                    <button
                        type="submit"
                        :disabled="isReserving"
                        class="flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-slate-900 text-xs font-black tracking-wider text-white uppercase shadow-sm transition-all hover:bg-slate-800 disabled:bg-slate-200"
                    >
                        <Loader2
                            v-if="isReserving"
                            class="size-4 animate-spin"
                        />
                        <span v-else>Đăng ký đặt bàn & món trước</span>
                    </button>
                </form>
            </section>

            <!-- ── Order History ────────────────────────────────────────── -->
            <section class="space-y-3">
                <div class="flex items-center gap-2">
                    <ShoppingBag class="size-4.5 text-indigo-500" />
                    <h3
                        class="text-xs font-black tracking-widest text-slate-800 uppercase"
                    >
                        Lịch sử giao dịch
                    </h3>
                </div>

                <div
                    v-if="orders.length === 0"
                    class="rounded-2xl border border-slate-200 bg-white p-5 text-center text-xs text-slate-400"
                >
                    Chưa ghi nhận lịch sử đơn hàng nào.
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="order in orders"
                        :key="order.id"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <header
                            @click="toggleOrder(order.id)"
                            class="flex cursor-pointer items-center justify-between p-4 transition-colors hover:bg-slate-50/50"
                        >
                            <div>
                                <h4 class="text-xs font-black text-slate-800">
                                    Đơn hàng #{{ order.order_number }}
                                </h4>
                                <p
                                    class="mt-0.5 text-[9px] font-bold text-slate-400"
                                >
                                    {{ formatDate(order.created_at) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'rounded border px-2 py-0.5 text-[9px] font-black tracking-wide uppercase',
                                        order.status === 'completed'
                                            ? 'border-emerald-250 bg-emerald-50 text-emerald-600'
                                            : order.status === 'cancelled'
                                              ? 'border-slate-200 bg-slate-100 text-slate-400'
                                              : 'border-amber-250 animate-pulse bg-amber-50 text-amber-600',
                                    ]"
                                >
                                    {{ translateStatus(order.status) }}
                                </span>
                                <ChevronDown
                                    v-if="!expandedOrders[order.id]"
                                    class="size-4 text-slate-400"
                                />
                                <ChevronUp
                                    v-else
                                    class="size-4 text-slate-400"
                                />
                            </div>
                        </header>

                        <!-- Expanded details -->
                        <div
                            v-if="expandedOrders[order.id]"
                            class="text-xxs space-y-3 border-t border-slate-100 bg-slate-50/50 p-4"
                        >
                            <div
                                class="space-y-1.5 rounded-xl border border-slate-200 bg-white p-3 shadow-inner"
                            >
                                <div
                                    v-for="(item, idx) in order.items"
                                    :key="idx"
                                    class="flex justify-between"
                                >
                                    <span class="font-medium text-slate-700"
                                        >{{ item.quantity }}x
                                        {{ item.name }}</span
                                    >
                                    <span class="font-bold text-slate-800">{{
                                        formatCurrency(
                                            item.price * item.quantity,
                                        )
                                    }}</span>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between pt-1 text-xs font-bold"
                            >
                                <span class="font-black text-slate-500"
                                    >Tổng thanh toán:</span
                                >
                                <span class="font-black text-amber-600">{{
                                    formatCurrency(order.total_amount)
                                }}</span>
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
