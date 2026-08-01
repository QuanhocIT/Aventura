<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Receipt,
    Sparkles,
    CalendarClock,
    FileText,
    Gift,
    ArrowUpRight,
    CreditCard,
    Crown,
    Clock,
    CheckCircle2,
    ShieldAlert,
    AlertTriangle,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
}

const subscriptionStatusLabels: Record<string, string> = {
    trial: 'Dùng thử',
    active: 'Đang hoạt động',
    expired: 'Đã hết hạn',
    cancelled: 'Đã hủy',
};

const subscriptionStatusVariants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
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

const invoiceStatusVariants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
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

const restaurantStatusVariants: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    active: 'default',
    trial: 'secondary',
    expired: 'destructive',
    suspended: 'destructive',
};

const totalInvoices = computed(() => props.invoices?.length ?? 0);

const totalSpent = computed(() => {
    return props.invoices.reduce((sum, inv) => sum + inv.total, 0);
});

const daysLeft = computed(() => {
    const dateStr =
        props.restaurant.subscription_ends_at || props.restaurant.trial_ends_at;

    if (!dateStr) {
        return null;
    }

    let targetDate: Date;

    if (dateStr.includes('/')) {
        const parts = dateStr.split('/');
        targetDate = new Date(
            parseInt(parts[2]),
            parseInt(parts[1]) - 1,
            parseInt(parts[0]),
        );
    } else {
        targetDate = new Date(dateStr);
    }

    const diffTime = targetDate.getTime() - new Date().getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    return diffDays > 0 ? diffDays : 0;
});

const progressPercent = computed(() => {
    if (daysLeft.value === null) {
        return 100;
    }

    const cycle = props.restaurant.status === 'trial' ? 14 : 30; // default standard cycle mapping
    const ratio = (daysLeft.value / cycle) * 100;

    return Math.min(100, Math.max(0, ratio));
});

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

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Receipt class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Hóa đơn & Lịch sử gói cước
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Theo dõi trạng thái gói dịch vụ, hóa đơn và các điều
                        chỉnh billing của {{ restaurant.name }}.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    as-child
                    size="sm"
                    class="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl bg-indigo-600 text-xs font-semibold text-white shadow-xs transition hover:bg-indigo-700 active:scale-95"
                >
                    <Link href="/dashboard">
                        Nâng cấp / Đổi gói
                        <ArrowUpRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Current Plan -->
            <Card
                class="border shadow-sm transition-all duration-200 hover:translate-y-[-2px]"
                :class="[
                    restaurant.plan_code === 'enterprise'
                        ? 'border-purple-200 bg-purple-500/[0.01] dark:border-purple-950/30'
                        : restaurant.plan_code === 'pro' ||
                            restaurant.plan_code === 'starter'
                          ? 'border-emerald-200 bg-emerald-500/[0.01] dark:border-emerald-950/30'
                          : 'border-border',
                ]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Gói dịch vụ hiện tại</CardDescription
                    >
                    <component
                        :is="
                            restaurant.plan_code === 'enterprise'
                                ? Crown
                                : restaurant.plan_code === 'pro'
                                  ? Sparkles
                                  : Zap
                        "
                        class="size-4"
                        :class="
                            restaurant.plan_code === 'enterprise'
                                ? 'text-purple-500'
                                : 'text-emerald-500'
                        "
                    />
                </CardHeader>
                <CardContent class="flex flex-col gap-2 pb-3">
                    <span
                        class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >Gói {{ restaurant.plan_name ?? 'Chưa xác định' }}</span
                    >
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                restaurantStatusVariants[restaurant.status] ??
                                'outline'
                            "
                            class="px-2 py-0.5 text-[10px] font-bold"
                        >
                            {{
                                restaurantStatusLabels[restaurant.status] ??
                                restaurant.status
                            }}
                        </Badge>
                        <span
                            class="text-[10px] font-medium text-muted-foreground"
                            >Bản quyền của nhà hàng</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Expiry Date / Countdown -->
            <Card
                class="border-emerald-100 shadow-sm transition-all duration-200 hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Thời hạn sử dụng</CardDescription
                    >
                    <CalendarClock
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="space-y-2 pb-3">
                    <div>
                        <span
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ formattedEndsAt }}</span
                        >
                        <p
                            class="mt-0.5 text-[10px] font-medium text-muted-foreground"
                        >
                            <span
                                v-if="daysLeft !== null"
                                class="font-extrabold text-emerald-600 dark:text-emerald-400"
                                >Còn {{ daysLeft }} ngày</span
                            >
                            <span v-else>Không giới hạn thời gian sử dụng</span>
                        </p>
                    </div>
                    <!-- Usage micro progress bar -->
                    <div
                        v-if="daysLeft !== null"
                        class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                            :style="`width: ${progressPercent}%`"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Total Cumulative Cost -->
            <Card
                class="border-indigo-100 shadow-sm transition-all duration-200 hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Tổng chi tiêu</CardDescription
                    >
                    <CreditCard
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <span
                        class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ formatCurrency(totalSpent) }}</span
                    >
                    <p class="mt-0.5 text-xs font-medium text-muted-foreground">
                        Đã phát hành {{ totalInvoices }} hóa đơn cước
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Annual savings hint banner -->
        <div
            v-if="restaurant.plan_code && restaurant.plan_code !== 'free'"
            class="flex w-full flex-col items-center justify-between gap-4 rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-500/[0.04] to-purple-500/[0.04] p-5 shadow-xs transition-all duration-200 hover:translate-y-[-1px] sm:flex-row dark:border-indigo-950/40 dark:from-indigo-950/20 dark:to-purple-950/20"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 shrink-0 animate-pulse items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 shadow-xs shadow-indigo-500/5 dark:bg-indigo-900/60 dark:text-indigo-400"
                >
                    <Gift class="size-5.5" />
                </div>
                <div>
                    <p
                        class="text-sm font-black text-slate-800 dark:text-slate-100"
                    >
                        Ưu đãi thanh toán năm tiết kiệm 20%
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Chuyển đổi chu kỳ sang thanh toán theo năm để hưởng mức
                        chiết khấu tốt nhất cho hoạt động lâu dài.
                    </p>
                </div>
            </div>
            <Button
                as-child
                size="sm"
                class="flex h-9 shrink-0 cursor-pointer items-center gap-1 rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95"
            >
                <Link
                    :href="`/billing/checkout?plan=${restaurant.plan_code}&cycle=yearly`"
                >
                    Chuyển sang gói năm
                    <ArrowUpRight class="size-3.5" />
                </Link>
            </Button>
        </div>

        <!-- Lịch sử gói cước -->
        <Card
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
        >
            <CardHeader
                class="border-b border-border bg-slate-50/40 pb-4 dark:bg-slate-900/10"
            >
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Receipt class="size-4.5 animate-pulse text-indigo-500" />
                    Lịch sử đăng ký & gia hạn gói cước
                </CardTitle>
                <CardDescription class="text-xs"
                    >Danh sách ghi nhận toàn bộ các lần kích hoạt, nâng cấp hoặc
                    điều chỉnh chu kỳ gói dịch vụ.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <!-- Empty State -->
                <div
                    v-if="subscriptions.length === 0"
                    class="flex flex-col items-center justify-center px-4 py-12 text-center"
                >
                    <div
                        class="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-slate-300 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-700"
                    >
                        <Receipt class="size-7" />
                    </div>
                    <p
                        class="text-xs font-bold text-slate-700 dark:text-slate-200"
                    >
                        Chưa có lịch sử gói cước
                    </p>
                    <p
                        class="mt-1 max-w-[280px] text-[10px] leading-normal text-muted-foreground"
                    >
                        Thông tin về lịch sử đăng ký dịch vụ của nhà hàng sẽ
                        hiển thị đầy đủ tại đây.
                    </p>
                </div>

                <!-- Subscriptions List -->
                <div v-else class="divide-y divide-border">
                    <div
                        v-for="sub in subscriptions"
                        :key="sub.id"
                        class="group flex flex-col gap-3 px-6 py-4.5 transition-all duration-200 hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/20"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="size-2 shrink-0 rounded-full"
                                    :class="
                                        sub.status === 'active'
                                            ? 'animate-pulse bg-emerald-500 shadow-sm shadow-emerald-500'
                                            : 'bg-slate-300 dark:bg-slate-600'
                                    "
                                />
                                <span
                                    class="text-xs font-bold text-slate-800 transition-colors group-hover:text-indigo-600 dark:text-slate-100 dark:group-hover:text-indigo-400"
                                >
                                    {{
                                        sub.plan_name ?? sub.plan_code ?? 'N/A'
                                    }}
                                </span>
                                <Badge
                                    :variant="
                                        subscriptionStatusVariants[
                                            sub.status
                                        ] ?? 'outline'
                                    "
                                    class="scale-95 px-2 py-0 text-[9px] font-bold tracking-wider uppercase"
                                >
                                    {{
                                        subscriptionStatusLabels[sub.status] ??
                                        sub.status
                                    }}
                                </Badge>
                                <Badge
                                    v-if="sub.billing_cycle"
                                    variant="outline"
                                    class="scale-95 border-slate-200 bg-slate-50 px-2 py-0 text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"
                                >
                                    {{
                                        sub.billing_cycle === 'yearly'
                                            ? 'Theo năm'
                                            : 'Theo tháng'
                                    }}
                                </Badge>
                            </div>
                            <p
                                class="text-[11px] font-medium text-muted-foreground"
                            >
                                Bắt đầu:
                                <span
                                    class="dark:text-slate-350 text-slate-700"
                                    >{{ sub.started_at ?? '—' }}</span
                                >
                                · Kết thúc:
                                <span
                                    class="dark:text-slate-350 text-slate-700"
                                    >{{ sub.ended_at ?? '—' }}</span
                                >
                                <span v-if="sub.coupon_code" class="text-xs">
                                    · Mã ưu đãi:
                                    <span
                                        class="rounded-md bg-indigo-500/10 px-1.5 py-0.5 font-bold tracking-wide text-indigo-500 uppercase dark:bg-indigo-950/20"
                                        >{{ sub.coupon_code }}</span
                                    ></span
                                >
                            </p>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p
                                class="text-slate-850 font-mono text-sm font-black dark:text-slate-100"
                            >
                                {{ formatCurrency(sub.price) }}
                            </p>
                            <p
                                v-if="sub.original_price > sub.price"
                                class="mt-0.5 text-[10px] font-medium text-muted-foreground"
                            >
                                Giá gốc:
                                <del class="font-mono">{{
                                    formatCurrency(sub.original_price)
                                }}</del>
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Hóa đơn -->
        <Card
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
        >
            <CardHeader
                class="border-b border-border bg-slate-50/40 pb-4 dark:bg-slate-900/10"
            >
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <FileText class="size-4.5 text-indigo-500" />
                    Danh sách Hóa đơn
                </CardTitle>
                <CardDescription class="text-xs"
                    >Bao gồm hóa đơn đã thanh toán cước phí và các khoản chưa
                    hoàn thành trên hệ thống.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <!-- Empty State -->
                <div
                    v-if="invoices.length === 0"
                    class="flex flex-col items-center justify-center px-4 py-12 text-center"
                >
                    <div
                        class="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 text-slate-300 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-700"
                    >
                        <FileText class="size-7" />
                    </div>
                    <p
                        class="text-xs font-bold text-slate-700 dark:text-slate-200"
                    >
                        Không tìm thấy hóa đơn
                    </p>
                    <p
                        class="mt-1 max-w-[280px] text-[10px] leading-normal text-muted-foreground"
                    >
                        Nhà hàng của bạn chưa phát sinh bất kỳ hóa đơn nào trên
                        hệ thống.
                    </p>
                </div>

                <!-- Invoices List -->
                <div v-else class="divide-y divide-border">
                    <div
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="group flex flex-col gap-3 px-6 py-4.5 transition-all duration-200 hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/20"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-slate-850 text-xs font-bold transition-colors group-hover:text-indigo-600 dark:text-slate-100 dark:group-hover:text-indigo-400"
                                >
                                    {{ invoice.invoice_number }}
                                </span>
                                <Badge
                                    :variant="
                                        invoiceStatusVariants[invoice.status] ??
                                        'outline'
                                    "
                                    class="scale-95 px-2 py-0 text-[9px] font-bold tracking-wider uppercase"
                                >
                                    {{
                                        invoiceStatusLabels[invoice.status] ??
                                        invoice.status
                                    }}
                                </Badge>
                            </div>
                            <p
                                class="text-[11px] font-medium text-muted-foreground"
                            >
                                Ngày xuất:
                                <span
                                    class="dark:text-slate-350 text-slate-700"
                                    >{{ invoice.issued_on ?? '—' }}</span
                                >
                                · Hạn cuối:
                                <span
                                    class="dark:text-slate-350 text-slate-700"
                                    >{{ invoice.due_on ?? '—' }}</span
                                >
                                <span
                                    v-if="invoice.sent_at"
                                    class="text-muted-foreground"
                                >
                                    · Đã gửi: {{ invoice.sent_at }}</span
                                >
                            </p>
                        </div>
                        <div class="shrink-0 text-left sm:text-right">
                            <p
                                class="text-slate-850 font-mono text-sm font-black dark:text-slate-100"
                            >
                                {{ formatCurrency(invoice.total) }}
                            </p>
                            <p
                                v-if="invoice.discount_amount > 0"
                                class="mt-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                Đã chiết khấu
                                {{ formatCurrency(invoice.discount_amount) }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Điều chỉnh billing -->
        <Card
            v-if="adjustments.length > 0"
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
        >
            <CardHeader
                class="border-b border-border bg-slate-50/40 pb-4 dark:bg-slate-900/10"
            >
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Gift class="size-4.5 animate-bounce text-indigo-500" />
                    Các điều chỉnh tài khoản (Adjustments)
                </CardTitle>
                <CardDescription class="text-xs"
                    >Lịch sử điều chỉnh số dư, cộng ngày sử dụng hoặc giảm giá
                    dịch vụ do quản trị viên cấp.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y divide-border">
                    <div
                        v-for="adj in adjustments"
                        :key="adj.id"
                        class="flex flex-col gap-3 px-6 py-4.5 transition-all duration-200 hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/20"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    variant="outline"
                                    class="scale-95 border-slate-200 bg-slate-50 px-2 py-0 text-[9px] font-bold tracking-wider text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-800"
                                >
                                    {{
                                        adjustmentTypeLabels[adj.type] ??
                                        adj.type
                                    }}
                                </Badge>
                                <span
                                    v-if="adj.coupon_code"
                                    class="text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                                    >Coupon: {{ adj.coupon_code }}</span
                                >
                            </div>
                            <p
                                v-if="adj.reason"
                                class="text-slate-850 text-[11px] font-bold dark:text-slate-100"
                            >
                                {{ adj.reason }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                {{ adj.created_at }}
                            </p>
                        </div>
                        <div
                            class="shrink-0 text-left text-xs font-semibold text-muted-foreground sm:text-right"
                        >
                            <p
                                v-if="adj.days"
                                class="flex items-center justify-end gap-1 text-sm font-extrabold text-indigo-600 dark:text-indigo-400"
                            >
                                <Clock class="size-3.5" />
                                +{{ adj.days }} ngày sử dụng
                            </p>
                            <p
                                v-if="adj.discount_amount > 0"
                                class="font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                Khấu trừ
                                {{ formatCurrency(adj.discount_amount) }}
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
