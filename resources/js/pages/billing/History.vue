<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Receipt,
    Sparkles,
    CalendarClock,
    BadgeCheck,
    FileText,
    Gift,
    ArrowUpRight,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Subscription = {
    id: number;
    plan_name: string | null;
    plan_code: string | null;
    status: string;
    billing_cycle: string | null;
    price: number;
    original_price: number;
    coupon_code: string | null;
    started_at: string | null;
    ended_at: string | null;
    transaction_code: string | null;
};

type Invoice = {
    id: number;
    invoice_number: string;
    type: string;
    status: string;
    currency: string;
    subtotal: number;
    discount_amount: number;
    total: number;
    issued_on: string | null;
    due_on: string | null;
    sent_at: string | null;
};

type Adjustment = {
    id: number;
    type: string;
    days: number | null;
    discount_amount: number;
    coupon_code: string | null;
    reason: string | null;
    created_at: string | null;
};

const props = defineProps<{
    restaurant: {
        name: string;
        plan_name: string | null;
        plan_code: string | null;
        status: string;
        subscription_ends_at: string | null;
        trial_ends_at: string | null;
    };
    subscriptions: Subscription[];
    invoices: Invoice[];
    adjustments: Adjustment[];
}>();

function formatCurrency(val: number): string {
    return val.toLocaleString('vi-VN') + ' ₫';
}

const subscriptionStatusLabels: Record<string, string> = {
    trial: 'Dùng thử',
    active: 'Đang hoạt động',
    expired: 'Đã hết hạn',
    cancelled: 'Đã hủy',
};

const subscriptionStatusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    trial: 'secondary',
    active: 'default',
    expired: 'destructive',
    cancelled: 'outline',
};

const invoiceStatusLabels: Record<string, string> = {
    pending: 'Chờ xử lý',
    generated: 'Đã tạo',
    sent: 'Đã gửi',
};

const invoiceStatusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    generated: 'outline',
    sent: 'default',
};

const adjustmentTypeLabels: Record<string, string> = {
    trial: 'Gia hạn dùng thử',
    extend: 'Gia hạn gói cước',
    discount: 'Giảm giá thủ công',
};

const restaurantStatusLabels: Record<string, string> = {
    active: 'Đang hoạt động',
    trial: 'Dùng thử',
    expired: 'Đã hết hạn',
    suspended: 'Tạm ngừng',
};

const restaurantStatusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    active: 'default',
    trial: 'secondary',
    expired: 'destructive',
    suspended: 'destructive',
};
</script>

<template>
    <Head title="Hóa đơn & Lịch sử gói cước" />

    <div class="px-4 py-6 space-y-6 max-w-5xl mx-auto w-full">
        <!-- Tổng quan gói cước hiện tại -->
        <div class="w-full rounded-2xl border border-border bg-card p-6 sm:p-8 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-2">
                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 border border-primary/20 px-2.5 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                        <Sparkles class="size-3.5" /> Gói {{ restaurant.plan_name ?? 'Chưa xác định' }}
                    </span>
                    <h2 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                        Hóa đơn & Lịch sử gói cước
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Theo dõi trạng thái gói dịch vụ, hóa đơn và các điều chỉnh billing của {{ restaurant.name }}.
                    </p>
                </div>

                <div class="flex flex-col items-start gap-2 sm:items-end">
                    <Badge :variant="restaurantStatusVariants[restaurant.status] ?? 'outline'">
                        <BadgeCheck class="size-3.5" />
                        {{ restaurantStatusLabels[restaurant.status] ?? restaurant.status }}
                    </Badge>
                    <p v-if="restaurant.subscription_ends_at" class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <CalendarClock class="size-3.5" />
                        Hết hạn: {{ restaurant.subscription_ends_at }}
                    </p>
                    <p v-else-if="restaurant.trial_ends_at" class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <CalendarClock class="size-3.5" />
                        Hết hạn dùng thử: {{ restaurant.trial_ends_at }}
                    </p>
                    <Button as-child size="sm" variant="outline">
                        <Link href="/dashboard">
                            Nâng cấp / Đổi gói
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Annual savings hint -->
        <div v-if="restaurant.plan_code && restaurant.plan_code !== 'free'" class="w-full rounded-xl border border-primary/20 bg-primary/5 p-4 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 bg-primary/10 text-primary rounded-lg flex items-center justify-center shrink-0">
                    <Gift class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-bold text-foreground">Thanh toán năm tiết kiệm 20%</p>
                    <p class="text-xs text-muted-foreground">Chuyển sang thanh toán theo năm để tiết kiệm đáng kể cho doanh nghiệp.</p>
                </div>
            </div>
            <Button as-child size="sm" variant="brand" class="shrink-0">
                <Link :href="`/billing/checkout?plan=${restaurant.plan_code}&cycle=yearly`">
                    Chuyển sang năm →
                </Link>
            </Button>
        </div>

        <!-- Lịch sử gói cước -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Receipt class="size-4 text-primary" /> Lịch sử gói cước
                </CardTitle>
                <CardDescription>Toàn bộ các lần đăng ký, gia hạn hoặc đổi gói dịch vụ.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="subscriptions.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    Chưa có lịch sử gói cước nào.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="sub in subscriptions"
                        :key="sub.id"
                        class="flex flex-col gap-2 rounded-xl border border-border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-foreground">{{ sub.plan_name ?? sub.plan_code ?? 'N/A' }}</span>
                                <Badge :variant="subscriptionStatusVariants[sub.status] ?? 'outline'">
                                    {{ subscriptionStatusLabels[sub.status] ?? sub.status }}
                                </Badge>
                                <Badge v-if="sub.billing_cycle" variant="outline">
                                    {{ sub.billing_cycle === 'yearly' ? 'Theo năm' : 'Theo tháng' }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Bắt đầu: {{ sub.started_at ?? '—' }} · Kết thúc: {{ sub.ended_at ?? '—' }}
                                <span v-if="sub.coupon_code"> · Mã giảm giá: <span class="font-medium text-foreground">{{ sub.coupon_code }}</span></span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">{{ formatCurrency(sub.price) }}</p>
                            <p v-if="sub.original_price > sub.price" class="text-xs text-muted-foreground">
                                Giá gốc: <del>{{ formatCurrency(sub.original_price) }}</del>
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Hóa đơn -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <FileText class="size-4 text-primary" /> Hóa đơn
                </CardTitle>
                <CardDescription>Danh sách hóa đơn đã phát hành và sắp đến hạn thanh toán.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="invoices.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                    Chưa có hóa đơn nào được phát hành.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="flex flex-col gap-2 rounded-xl border border-border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-foreground">{{ invoice.invoice_number }}</span>
                                <Badge :variant="invoiceStatusVariants[invoice.status] ?? 'outline'">
                                    {{ invoiceStatusLabels[invoice.status] ?? invoice.status }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Phát hành: {{ invoice.issued_on ?? '—' }} · Hạn thanh toán: {{ invoice.due_on ?? '—' }}
                                <span v-if="invoice.sent_at"> · Đã gửi: {{ invoice.sent_at }}</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">{{ formatCurrency(invoice.total) }}</p>
                            <p v-if="invoice.discount_amount > 0" class="text-xs text-emerald-600">
                                Đã giảm {{ formatCurrency(invoice.discount_amount) }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Điều chỉnh billing -->
        <Card v-if="adjustments.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Gift class="size-4 text-primary" /> Điều chỉnh billing
                </CardTitle>
                <CardDescription>Các điều chỉnh đặc biệt do quản trị viên áp dụng cho tài khoản của bạn.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div
                        v-for="adj in adjustments"
                        :key="adj.id"
                        class="flex flex-col gap-2 rounded-xl border border-border bg-muted/30 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline">{{ adjustmentTypeLabels[adj.type] ?? adj.type }}</Badge>
                                <span v-if="adj.coupon_code" class="text-xs text-muted-foreground">Mã: {{ adj.coupon_code }}</span>
                            </div>
                            <p v-if="adj.reason" class="text-xs text-muted-foreground">{{ adj.reason }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ adj.created_at }}</p>
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            <p v-if="adj.days">+{{ adj.days }} ngày</p>
                            <p v-if="adj.discount_amount > 0">Giảm {{ formatCurrency(adj.discount_amount) }}</p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
