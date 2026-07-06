<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ShieldCheck,
    Utensils,
    ShoppingCart,
    ChevronRight,
    CheckCircle2,
    XCircle,
    Clock,
    Banknote,
    AlertTriangle,
    Info
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface RecentOrder {
    id: number;
    order_number: string;
    table_name: string | null;
    total_amount: number;
    status: string;
    payment_status: string;
    channel: string;
    created_at: string;
}

interface Alert {
    type: 'warning' | 'danger' | 'info';
    message: string;
    href: string;
}

const props = defineProps<{
    onboardingComplete: boolean | undefined;
    recentOrders: RecentOrder[] | undefined;
    stats: any;
    alerts: Alert[] | undefined;
    user: any;
}>();

import { useFeatureGate } from '@/composables/useFeatureGate';

const { planCode } = useFeatureGate();
const activePlanCode = computed(() => planCode());

// Onboarding steps derived from user's onboarding_status
const onboardingSteps = computed(() => {
    const status = (props.user?.onboarding_status as any) ?? {};

    return [
        { key: 'day_1', label: 'Thêm sản phẩm & thực đơn',     href: '/products',            done: !!status.day_1?.completed_at },
        { key: 'day_2', label: 'Thiết lập sơ đồ bàn',           href: '/tables',              done: !!status.day_2?.completed_at },
        { key: 'day_3', label: 'Cấu hình kho nguyên liệu',      href: '/inventory',           done: !!status.day_3?.completed_at },
        { key: 'day_4', label: 'Thêm nhân viên & phân quyền',   href: '/employees',           done: !!status.day_4?.completed_at },
        { key: 'day_5', label: 'Cấu hình thanh toán & gói',     href: '/billing/history',     done: !!status.day_5?.completed_at },
    ];
});

const onboardingProgress = computed(() =>
    onboardingSteps.value.filter(s => s.done).length
);

const recentOrdersList = computed(() => props.recentOrders ?? []);
const alertsList = computed(() => props.alerts ?? []);

const orderStatusMap: Record<string, { label: string; class: string }> = {
    pending:   { label: 'Chờ xác nhận', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    confirmed: { label: 'Đã xác nhận',  class: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400' },
    preparing: { label: 'Đang chế biến', class: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' },
    completed: { label: 'Hoàn thành',   class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
    cancelled: { label: 'Đã huỷ',       class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' },
};

const channelMap: Record<string, string> = {
    dine_in:  'Tại bàn',
    takeaway: 'Mang về',
    delivery: 'Giao hàng',
    qr:       'QR',
};

// Hiệu suất hôm nay — tính toán từ stats
const completionRate = computed(() => {
    if (!props.stats?.orders_today) {
        return 0;
    }

    return Math.round((props.stats.orders_completed / props.stats.orders_today) * 100);
});

const cancellationRate = computed(() => {
    if (!props.stats?.orders_today) {
        return 0;
    }

    return Math.round(((props.stats.orders_cancelled ?? 0) / props.stats.orders_today) * 100);
});

const pendingCount = computed(() => {
    if (!props.stats) {
        return 0;
    }

    return props.stats.orders_today - props.stats.orders_completed - (props.stats.orders_cancelled ?? 0);
});

// Alert icon map
const alertIconMap = { warning: AlertTriangle, danger: XCircle, info: Info };
const alertColorMap = {
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-300',
    danger:  'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/30 dark:text-rose-300',
    info:    'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/50 dark:bg-sky-950/30 dark:text-sky-300',
};

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Onboarding widget -->
        <template v-if="!onboardingComplete">
            <div>
                <h2 class="text-base font-semibold">Hoàn thiện thiết lập</h2>
                <Card class="mt-4">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold">
                            {{ onboardingProgress }}/{{ onboardingSteps.length }} bước hoàn thành
                        </CardTitle>
                        <div class="mt-2 h-1.5 rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="`width: ${(onboardingProgress / onboardingSteps.length) * 100}%`"
                            />
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-2 pb-4">
                        <Link
                            v-for="step in onboardingSteps"
                            :key="step.key"
                            :href="step.done ? '#' : step.href"
                            class="flex items-center gap-2.5 rounded-lg p-2 text-sm transition-colors hover:bg-muted/50"
                            :class="step.done ? 'text-muted-foreground' : 'text-foreground'"
                        >
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-xs"
                                :class="step.done
                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                    : 'border-border bg-background'"
                            >
                                <ShieldCheck v-if="step.done" class="size-3" />
                                <span v-else></span>
                            </span>
                            <span :class="step.done ? 'line-through opacity-60' : ''">{{ step.label }}</span>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- ── 1. Đơn hàng gần đây ───────────────────── -->
        <div v-if="activePlanCode !== 'free' && activePlanCode !== 'basic'">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Đơn hàng gần đây</h2>
                <Link href="/orders" class="flex items-center gap-1 text-xs text-primary hover:underline">
                    Xem tất cả <ChevronRight class="size-3.5" />
                </Link>
            </div>

            <Card class="mt-4">
                <CardContent class="p-0">
                    <!-- Có đơn -->
                    <div v-if="recentOrdersList.length > 0" class="divide-y divide-border">
                        <Link
                            v-for="order in recentOrdersList"
                            :key="order.id"
                            href="/orders"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-muted/40 transition-colors group"
                        >
                            <!-- Icon channel -->
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                <Utensils class="size-3.5 text-muted-foreground" />
                            </div>

                            <!-- Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-semibold truncate">#{{ order.order_number }}</span>
                                    <span v-if="order.table_name" class="text-xs text-muted-foreground truncate">· {{ order.table_name }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-xs text-muted-foreground">{{ channelMap[order.channel] ?? order.channel }}</span>
                                    <span class="text-xs text-muted-foreground">·</span>
                                    <span class="text-xs text-muted-foreground">{{ order.created_at }}</span>
                                </div>
                            </div>

                            <!-- Right: badge + price -->
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="orderStatusMap[order.status]?.class ?? 'bg-muted text-muted-foreground'"
                                >
                                    {{ orderStatusMap[order.status]?.label ?? order.status }}
                                </span>
                                <span class="text-xs font-semibold">{{ formatMoneyFull(order.total_amount) }}</span>
                            </div>
                        </Link>
                    </div>

                    <!-- Không có đơn -->
                    <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                        <ShoppingCart class="size-8 text-muted-foreground/40 mb-2" />
                        <p class="text-sm text-muted-foreground">Chưa có đơn hàng nào hôm nay</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── 2. Hiệu suất hôm nay ───────────────────── -->
        <div v-if="stats">
            <h2 class="text-base font-semibold">Hiệu suất hôm nay</h2>
            <Card class="mt-4">
                <CardContent class="pt-4 pb-4 space-y-4">
                    <!-- Tỉ lệ hoàn thành -->
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="text-muted-foreground flex items-center gap-1.5">
                                <CheckCircle2 class="size-3.5 text-emerald-500" />
                                Tỉ lệ hoàn thành
                            </span>
                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                {{ completionRate }}%
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                :style="`width: ${completionRate}%`"
                            />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ stats.orders_completed }} / {{ stats.orders_today }} đơn
                        </p>
                    </div>

                    <!-- Tỉ lệ huỷ -->
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1.5">
                            <span class="text-muted-foreground flex items-center gap-1.5">
                                <XCircle class="size-3.5 text-rose-500" />
                                Tỉ lệ huỷ đơn
                            </span>
                            <span
                                class="font-semibold"
                                :class="cancellationRate > 20 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'"
                            >
                                {{ cancellationRate }}%
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="cancellationRate > 20 ? 'bg-rose-500' : 'bg-rose-300'"
                                :style="`width: ${cancellationRate}%`"
                            />
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ stats.orders_cancelled ?? 0 }} đơn đã huỷ
                        </p>
                    </div>

                    <!-- Đang xử lý / pending -->
                    <div class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2.5">
                        <span class="flex items-center gap-2 text-sm text-muted-foreground">
                            <Clock class="size-3.5 text-amber-500" />
                            Đơn đang xử lý
                        </span>
                        <span class="text-sm font-bold" :class="pendingCount > 5 ? 'text-amber-600' : ''">
                            {{ pendingCount }}
                        </span>
                    </div>

                    <!-- Doanh thu -->
                    <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-950/20 px-3 py-2.5">
                        <span class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                            <Banknote class="size-3.5" />
                            Doanh thu
                        </span>
                        <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                            {{ formatMoneyFull(stats.revenue_today) }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── 3. Cảnh báo & Nhắc nhở ─────────────────── -->
        <div v-if="alertsList.length > 0">
            <div class="flex items-center gap-2">
                <h2 class="text-base font-semibold">Cảnh báo & Nhắc nhở</h2>
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold">
                    {{ alertsList.length }}
                </span>
            </div>
            <div class="mt-4 flex flex-col gap-2">
                <Link
                    v-for="(alert, i) in alertsList"
                    :key="i"
                    :href="alert.href"
                    class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-sm transition-all hover:opacity-80"
                    :class="alertColorMap[alert.type]"
                >
                    <component
                        :is="alertIconMap[alert.type]"
                        class="size-4 shrink-0 mt-0.5"
                    />
                    <span class="leading-snug">{{ alert.message }}</span>
                    <ChevronRight class="size-3.5 ml-auto shrink-0 mt-0.5 opacity-60" />
                </Link>
            </div>
        </div>
    </div>
</template>
