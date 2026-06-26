<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Receipt,
    Sparkles,
    CalendarClock,
    FileText,
    Gift,
    ArrowUpRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
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

const totalInvoices = computed(() => props.invoices?.length ?? 0);
const formattedEndsAt = computed(() => {
    if (props.restaurant.subscription_ends_at) {
        return props.restaurant.subscription_ends_at;
    }

    if (props.restaurant.trial_ends_at) {
        return props.restaurant.trial_ends_at;
    }

    return 'Vô thời hạn';
});
</script>

<template>
    <Head title="Hóa đơn & Lịch sử gói cước" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Receipt class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hóa đơn & Lịch sử gói cước</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Theo dõi trạng thái gói dịch vụ, hóa đơn và các điều chỉnh billing của {{ restaurant.name }}.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child size="sm" class="h-10 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5 rounded-xl shadow-xs">
                    <Link href="/dashboard">
                        Nâng cấp / Đổi gói
                        <ArrowUpRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Current Plan -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Gói dịch vụ</CardDescription>
                    <Sparkles class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3 flex flex-col gap-1.5">
                    <span class="text-2xl font-black text-slate-800 dark:text-slate-100">Gói {{ restaurant.plan_name ?? 'Chưa xác định' }}</span>
                    <div>
                        <Badge :variant="restaurantStatusVariants[restaurant.status] ?? 'outline'" class="text-[10px] py-0 px-1.5">
                            {{ restaurantStatusLabels[restaurant.status] ?? restaurant.status }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Expiry Date -->
            <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Thời hạn sử dụng</CardDescription>
                    <CalendarClock class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ formattedEndsAt }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">ngày hết hạn dịch vụ/dùng thử</p>
                </CardContent>
            </Card>

            <!-- Total Invoices -->
            <Card class="shadow-xs border-indigo-100 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Tổng hóa đơn</CardDescription>
                    <FileText class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ totalInvoices }} hóa đơn</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">đã phát hành trên hệ thống</p>
                </CardContent>
            </Card>
        </div>

        <!-- Annual savings hint -->
        <div v-if="restaurant.plan_code && restaurant.plan_code !== 'free'" class="w-full rounded-2xl border border-indigo-100 dark:border-indigo-950/40 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-950/20 dark:to-purple-950/20 p-5 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm hover:translate-y-[-1px] transition-all duration-200">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 bg-indigo-100 dark:bg-indigo-900/60 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center shrink-0">
                    <Gift class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">Thanh toán năm tiết kiệm 20%</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Chuyển sang chu kỳ thanh toán theo năm để nhận ưu đãi chiết khấu cao nhất cho doanh nghiệp.</p>
                </div>
            </div>
            <Button as-child size="sm" class="shrink-0 h-9 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg px-4 flex items-center gap-1">
                <Link :href="`/billing/checkout?plan=${restaurant.plan_code}&cycle=yearly`">
                    Chuyển sang gói năm
                    <ArrowUpRight class="size-3.5" />
                </Link>
            </Button>
        </div>

        <!-- Lịch sử gói cước -->
        <Card class="shadow-md rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/45">
            <CardHeader class="border-b border-border/60 pb-4">
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Receipt class="size-4 text-indigo-500" /> Lịch sử gói cước
                </CardTitle>
                <CardDescription>Toàn bộ các lần đăng ký, gia hạn hoặc đổi gói dịch vụ.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="subscriptions.length === 0" class="py-12 text-center text-xs font-semibold text-slate-500">
                    Chưa có lịch sử gói cước nào.
                </div>
                <div v-else class="divide-y divide-border/60">
                    <div
                        v-for="sub in subscriptions"
                        :key="sub.id"
                        class="flex flex-col gap-3 py-4 px-6 hover:bg-muted/15 transition-colors sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ sub.plan_name ?? sub.plan_code ?? 'N/A' }}</span>
                                <Badge :variant="subscriptionStatusVariants[sub.status] ?? 'outline'" class="text-[10px] py-0 px-1.5 font-semibold">
                                    {{ subscriptionStatusLabels[sub.status] ?? sub.status }}
                                </Badge>
                                <Badge v-if="sub.billing_cycle" variant="outline" class="text-[10px] py-0 px-1.5 font-medium bg-slate-50 dark:bg-slate-800">
                                    {{ sub.billing_cycle === 'yearly' ? 'Theo năm' : 'Theo tháng' }}
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Bắt đầu: {{ sub.started_at ?? '—' }} · Kết thúc: {{ sub.ended_at ?? '—' }}
                                <span v-if="sub.coupon_code"> · Mã giảm giá: <span class="font-medium text-slate-700 dark:text-slate-350">{{ sub.coupon_code }}</span></span>
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(sub.price) }}</p>
                            <p v-if="sub.original_price > sub.price" class="text-[10px] text-muted-foreground mt-0.5">
                                Giá gốc: <del>{{ formatCurrency(sub.original_price) }}</del>
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Hóa đơn -->
        <Card class="shadow-md rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/45">
            <CardHeader class="border-b border-border/60 pb-4">
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <FileText class="size-4 text-indigo-500" /> Hóa đơn
                </CardTitle>
                <CardDescription>Danh sách hóa đơn đã phát hành và sắp đến hạn thanh toán.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="invoices.length === 0" class="py-12 text-center text-xs font-semibold text-slate-500">
                    Chưa có hóa đơn nào được phát hành.
                </div>
                <div v-else class="divide-y divide-border/60">
                    <div
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="flex flex-col gap-3 py-4 px-6 hover:bg-muted/15 transition-colors sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-xs text-slate-850 dark:text-slate-100">{{ invoice.invoice_number }}</span>
                                <Badge :variant="invoiceStatusVariants[invoice.status] ?? 'outline'" class="text-[10px] py-0 px-1.5 font-semibold">
                                    {{ invoiceStatusLabels[invoice.status] ?? invoice.status }}
                                </Badge>
                            </div>
                            <p class="text-[11px] text-muted-foreground">
                                Phát hành: {{ invoice.issued_on ?? '—' }} · Hạn thanh toán: {{ invoice.due_on ?? '—' }}
                                <span v-if="invoice.sent_at"> · Đã gửi: {{ invoice.sent_at }}</span>
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(invoice.total) }}</p>
                            <p v-if="invoice.discount_amount > 0" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5">
                                Đã giảm {{ formatCurrency(invoice.discount_amount) }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Điều chỉnh billing -->
        <Card v-if="adjustments.length > 0" class="shadow-md rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/45">
            <CardHeader class="border-b border-border/60 pb-4">
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Gift class="size-4 text-indigo-500" /> Điều chỉnh billing
                </CardTitle>
                <CardDescription>Các điều chỉnh đặc biệt do quản trị viên áp dụng cho tài khoản của bạn.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y divide-border/60">
                    <div
                        v-for="adj in adjustments"
                        :key="adj.id"
                        class="flex flex-col gap-3 py-4 px-6 hover:bg-muted/15 transition-colors sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Badge variant="outline" class="text-[10px] py-0 px-1.5 font-semibold bg-slate-50 dark:bg-slate-800">
                                    {{ adjustmentTypeLabels[adj.type] ?? adj.type }}
                                </Badge>
                                <span v-if="adj.coupon_code" class="text-xs text-muted-foreground">Mã: {{ adj.coupon_code }}</span>
                            </div>
                            <p v-if="adj.reason" class="text-[11px] text-slate-700 dark:text-slate-350 font-medium">{{ adj.reason }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ adj.created_at }}</p>
                        </div>
                        <div class="text-left sm:text-right text-xs text-muted-foreground font-semibold">
                            <p v-if="adj.days" class="text-indigo-650 dark:text-indigo-400 font-bold">+{{ adj.days }} ngày sử dụng</p>
                            <p v-if="adj.discount_amount > 0" class="text-emerald-600 dark:text-emerald-400">Giảm {{ formatCurrency(adj.discount_amount) }}</p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
