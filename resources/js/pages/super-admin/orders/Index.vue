<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ShoppingCart,
    CheckCircle,
    XCircle,
    Search,
    TrendingUp,
    Percent,
    BarChart2,
    Calendar,
    Eye,
    Clock,
    CreditCard,
    User,
    Phone,
    MapPin,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { PageHeader, FilterBar, Pagination } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface OrderItemData {
    id: number;
    order_number: string;
    restaurant: string;
    restaurant_code: string;
    plan_name: string;
    billing_cycle: string;
    status: string;
    payment_status: string;
    total_amount: string;
    total_raw: number;
    type: string;
    created_at: string;
    due_on: string;
    recipient_email: string;
    buyer_name?: string;
    buyer_phone?: string;
    buyer_email?: string;
    buyer_address?: string;
    tax_code?: string;
}

interface RevenueTrendPoint {
    date: string;
    revenue: number;
}

interface Stats {
    total_today: number;
    revenue_today: number;
    completed_today: number;
    cancelled_today: number;
    unpaid_today: number;
    plan_counts_today: Array<{ code: string; name: string; count: number }>;
    revenue_trend: RevenueTrendPoint[];
}

const props = defineProps<{
    orders: {
        data: OrderItemData[];
        links: any[];
        total: number;
        last_page: number;
        current_page: number;
    };
    stats: Stats;
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: {
        restaurant_id?: string;
        status?: string;
        payment_status?: string;
        type?: string;
        search?: string;
        date_from?: string;
        date_to?: string;
    };
}>();

const restaurantId = ref(props.filters.restaurant_id || 'all');
const status = ref(props.filters.status || 'all');
const paymentStatus = ref(props.filters.payment_status || 'all');
const type = ref(props.filters.type || 'all');
const search = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 400);
});

function applyFilter() {
    router.get(
        '/super-admin/orders',
        {
            restaurant_id:
                restaurantId.value && restaurantId.value !== 'all'
                    ? restaurantId.value
                    : undefined,
            status:
                status.value && status.value !== 'all'
                    ? status.value
                    : undefined,
            payment_status:
                paymentStatus.value && paymentStatus.value !== 'all'
                    ? paymentStatus.value
                    : undefined,
            type: type.value && type.value !== 'all' ? type.value : undefined,
            search: search.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function hasActiveFilter() {
    return !!(
        (restaurantId.value && restaurantId.value !== 'all') ||
        (status.value && status.value !== 'all') ||
        (paymentStatus.value && paymentStatus.value !== 'all') ||
        (type.value && type.value !== 'all') ||
        search.value ||
        dateFrom.value ||
        dateTo.value
    );
}

function resetFilters() {
    restaurantId.value = 'all';
    status.value = 'all';
    paymentStatus.value = 'all';
    type.value = 'all';
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilter();
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(val);
}

// Compute Completion Rate & AOV
const completionRate = computed(() => {
    const total = props.stats.total_today;

    if (total === 0) {
        return 0;
    }

    return Math.round((props.stats.completed_today / total) * 100);
});

const averageOrderValue = computed(() => {
    const completed = props.stats.completed_today;

    if (completed === 0) {
        return 0;
    }

    return Math.round(props.stats.revenue_today / completed);
});

const statusLabel: Record<string, string> = {
    paid: 'Đã thanh toán',
    unpaid: 'Chưa thanh toán',
    pending: 'Đang tạo chứng từ',
    generated: 'Đã tạo chứng từ',
    sent: 'Đã gửi chứng từ',
    written_off: 'Đã xóa nợ',
};

const typeLabel: Record<string, string> = {
    payment_success: 'Thanh toán thành công',
    upcoming_renewal: 'Hóa đơn gia hạn sắp tới',
    registration: 'Đăng ký mới',
    renewal: 'Gia hạn',
    upgrade: 'Nâng cấp',
    adjustment: 'Điều chỉnh',
};

const billingCycleLabel: Record<string, string> = {
    monthly: 'Hàng tháng',
    quarterly: 'Hàng quý',
    half_yearly: 'Nửa năm',
    yearly: 'Hàng năm',
    biennial: 'Hai năm',
};

// Order Details Modal State
const selectedOrder = ref<OrderItemData | null>(null);
const showDetails = ref(false);

const openOrderDetails = (order: OrderItemData) => {
    selectedOrder.value = order;
    showDetails.value = true;
};

const closeOrderDetails = () => {
    showDetails.value = false;
};

// SVG Donut slices for channels breakdown
const channelSlices = computed(() => {
    const colors = [
        'stroke-blue-500',
        'stroke-purple-500',
        'stroke-emerald-500',
        'stroke-orange-500',
        'stroke-pink-500',
    ];
    const planCounts = props.stats.plan_counts_today ?? [];
    const total = planCounts.reduce((sum, item) => sum + item.count, 0);

    if (total === 0) {
        return [];
    }

    let currentOffset = 0;
    return planCounts.map((item, index) => {
        const percentage = (item.count / total) * 100;
        const slice = {
            percentage,
            dashArray: `${percentage} ${100 - percentage}`,
            dashOffset: 100 - currentOffset,
            color: colors[index % colors.length],
            label: item.name,
            value: item.count,
        };
        currentOffset += percentage;
        return slice;
    });
});

// SVG Area Chart points for 7-day revenue trend
const chartPoints = computed(() => {
    const trend = props.stats.revenue_trend;

    if (!trend || trend.length === 0) {
        return '';
    }

    const maxRev = Math.max(...trend.map((p) => p.revenue), 100000);

    return trend
        .map((p, i) => {
            const x = i * (500 / (trend.length - 1 || 1));
            const y = 90 - (p.revenue / maxRev) * 75;

            return `${x},${y}`;
        })
        .join(' ');
});

const chartAreaPath = computed(() => {
    const points = chartPoints.value;

    if (!points) {
        return '';
    }

    const lastX = 500;

    return `M 0,100 L ${points} L ${lastX},100 Z`;
});
</script>

<template>
    <Head title="Đơn hàng gói dịch vụ" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Đơn hàng gói dịch vụ"
            subtitle="Xem và theo dõi tất cả hóa đơn đăng ký gói dịch vụ của các nhà hàng."
            :icon="ShoppingCart"
        />

        <!-- KPI Stats Cards (6 Columns Glassmorphism) -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <!-- Total orders today -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-sky-600 uppercase dark:text-sky-400"
                    >
                        Hóa đơn hôm nay
                    </p>
                    <div
                        class="font-mono text-2xl font-black text-sky-600 dark:text-sky-400"
                    >
                        {{ stats.total_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Phát sinh trong ngày
                    </p>
                </CardContent>
            </Card>

            <!-- Revenue today -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                    >
                        Doanh thu gói hôm nay
                    </p>
                    <div
                        class="truncate px-1 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ formatVND(stats.revenue_today) }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Đã thanh toán thành công
                    </p>
                </CardContent>
            </Card>

            <!-- Completed today -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-violet-600 uppercase dark:text-violet-400"
                    >
                        Đã thanh toán
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-violet-600 dark:text-violet-400"
                    >
                        <CheckCircle class="size-5 text-violet-500" />
                        {{ stats.completed_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Hóa đơn thu tiền thành công
                    </p>
                </CardContent>
            </Card>

            <!-- Cancelled today -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
                :class="{
                    'border-rose-500/20 bg-rose-500/[0.01]':
                        stats.cancelled_today > 0,
                }"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-rose-600 uppercase dark:text-rose-400"
                    >
                        Chưa thanh toán
                    </p>
                    <div
                        class="flex items-center justify-center gap-1 font-mono text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        <XCircle class="size-5 text-rose-500" />
                        {{ stats.cancelled_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Hóa đơn đang chờ thanh toán
                    </p>
                </CardContent>
            </Card>

            <!-- Completion rate -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-amber-600 uppercase dark:text-amber-400"
                    >
                        Tỷ lệ thanh toán
                    </p>
                    <div
                        class="flex items-center justify-center gap-0.5 font-mono text-2xl font-black text-amber-600 dark:text-amber-400"
                    >
                        <Percent class="size-4 text-amber-500" />
                        {{ completionRate }}%
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Tỷ lệ hóa đơn hoàn thành
                    </p>
                </CardContent>
            </Card>

            <!-- AOV today -->
            <Card
                class="border border-border/40 bg-card/45 backdrop-blur-md transition-all hover:shadow-md"
            >
                <CardContent class="space-y-1.5 p-4 text-center">
                    <p
                        class="text-[10px] font-black tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                    >
                        Hóa đơn trung bình
                    </p>
                    <div
                        class="truncate px-1 font-mono text-2xl font-black text-indigo-600 dark:text-indigo-400"
                    >
                        {{ formatVND(averageOrderValue) }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">
                        Trị giá / đơn thanh toán
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Charts Row (Revenue Trend + Channels Breakdown) -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Revenue Trend 7 days (SVG Area Chart - Spans 2 Columns) -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md md:col-span-2"
            >
                <CardContent class="space-y-4 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3
                                class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                            >
                                <TrendingUp
                                    class="size-4 animate-pulse text-emerald-500"
                                />
                                Xu xu hướng Doanh thu gói dịch vụ 7 ngày
                            </h3>
                            <p class="text-[11px] text-muted-foreground">
                                Biểu đồ tổng doanh thu các gói dịch vụ đã thanh
                                toán
                            </p>
                        </div>
                        <div
                            class="font-mono text-xs font-black text-slate-700 dark:text-slate-200"
                        >
                            Max:
                            {{
                                formatVND(
                                    Math.max(
                                        ...stats.revenue_trend.map(
                                            (p) => p.revenue,
                                        ),
                                        0,
                                    ),
                                )
                            }}
                        </div>
                    </div>

                    <!-- SVG Chart -->
                    <div class="relative h-32 w-full pt-2">
                        <svg
                            viewBox="0 0 500 100"
                            class="size-full overflow-visible"
                        >
                            <!-- Gradients definitions -->
                            <defs>
                                <linearGradient
                                    id="areaGrad"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stop-color="rgba(16, 185, 129, 0.25)"
                                    />
                                    <stop
                                        offset="100%"
                                        stop-color="rgba(16, 185, 129, 0.0)"
                                    />
                                </linearGradient>
                            </defs>

                            <!-- Grid lines -->
                            <line
                                x1="0"
                                y1="20"
                                x2="500"
                                y2="20"
                                stroke="rgba(226, 232, 240, 0.15)"
                                stroke-width="0.5"
                            />
                            <line
                                x1="0"
                                y1="50"
                                x2="500"
                                y2="50"
                                stroke="rgba(226, 232, 240, 0.15)"
                                stroke-width="0.5"
                            />
                            <line
                                x1="0"
                                y1="80"
                                x2="500"
                                y2="80"
                                stroke="rgba(226, 232, 240, 0.15)"
                                stroke-width="0.5"
                            />

                            <!-- Path area fill -->
                            <path
                                :d="chartAreaPath"
                                fill="url(#areaGrad)"
                            ></path>

                            <!-- Polyline border -->
                            <polyline
                                :points="chartPoints"
                                fill="none"
                                stroke="#10b981"
                                stroke-width="2"
                                class="transition-all duration-500"
                            ></polyline>

                            <!-- Nodes & Tooltips -->
                            <circle
                                v-for="(p, i) in stats.revenue_trend"
                                :key="i"
                                :cx="
                                    i *
                                    (500 /
                                        (stats.revenue_trend.length - 1 || 1))
                                "
                                :cy="
                                    90 -
                                    (p.revenue /
                                        Math.max(
                                            ...stats.revenue_trend.map(
                                                (pt) => pt.revenue,
                                            ),
                                            1,
                                        )) *
                                        75
                                "
                                r="3.5"
                                fill="#ffffff"
                                stroke="#10b981"
                                stroke-width="2"
                                class="hover:r-5 cursor-pointer transition-all"
                            >
                                <title>
                                    {{ p.date }}: {{ formatVND(p.revenue) }}
                                </title>
                            </circle>
                        </svg>
                    </div>
                    <!-- X Axis Labels -->
                    <div
                        class="flex justify-between px-1 font-mono text-[10px] font-bold text-slate-400 dark:text-slate-500"
                    >
                        <span v-for="p in stats.revenue_trend" :key="p.date">{{
                            p.date
                        }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Channels Breakdown (SVG Donut Chart - Spans 1 Column) -->
            <Card
                class="border border-border/40 bg-card/50 shadow-xs backdrop-blur-md"
            >
                <CardContent
                    class="flex h-full min-h-[200px] flex-col justify-between p-5"
                >
                    <div class="space-y-1">
                        <h3
                            class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-700 uppercase dark:text-slate-200"
                        >
                            <BarChart2 class="size-4 text-primary" />
                            Cơ cấu Gói dịch vụ
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Tỷ trọng các gói dịch vụ đã bán hôm nay
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-4 pt-2">
                        <div class="shrink-0 space-y-1.5 text-xs font-semibold">
                            <div
                                v-for="slice in channelSlices"
                                :key="slice.label"
                                class="flex items-center gap-1.5"
                            >
                                <span
                                    class="size-2 rounded-full"
                                    :class="
                                        slice.color.replace('stroke-', 'bg-')
                                    "
                                ></span>
                                <span class="text-slate-500"
                                    >{{ slice.label }}:</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-800 dark:text-slate-200"
                                    >{{ slice.value }}</span
                                >
                            </div>
                        </div>

                        <!-- Donut SVG widget -->
                        <div
                            class="relative flex size-24 shrink-0 items-center justify-center"
                        >
                            <svg
                                viewBox="0 0 42 42"
                                class="size-full -rotate-90 transform"
                            >
                                <circle
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    stroke="rgba(226, 232, 240, 0.4)"
                                    stroke-width="4"
                                ></circle>
                                <circle
                                    v-for="(slice, index) in channelSlices"
                                    :key="index"
                                    cx="21"
                                    cy="21"
                                    r="15.915"
                                    fill="transparent"
                                    :class="slice.color"
                                    stroke-width="4"
                                    :stroke-dasharray="slice.dashArray"
                                    :stroke-dashoffset="slice.dashOffset"
                                    class="transition-all duration-700"
                                ></circle>
                            </svg>
                            <div
                                class="absolute flex flex-col items-center justify-center text-center"
                            >
                                <span
                                    class="font-mono text-base font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{ stats.total_today }}
                                </span>
                                <span
                                    class="text-[7px] font-bold text-slate-400 uppercase"
                                    >Gói</span
                                >
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters Block -->
        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Tìm số hóa đơn, tên nhà hàng..."
                    class="pl-9"
                />
            </div>
            <Select v-model="restaurantId" @update:model-value="applyFilter">
                <SelectTrigger class="w-[200px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                    <SelectItem
                        v-for="r in restaurants"
                        :key="r.id"
                        :value="String(r.id)"
                        >{{ r.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select v-model="paymentStatus" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Thanh toán" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả thanh toán</SelectItem>
                    <SelectItem value="paid">Đã thanh toán</SelectItem>
                    <SelectItem value="unpaid">Chưa thanh toán</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]"><SelectValue placeholder="Chứng từ" /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả chứng từ</SelectItem>
                    <SelectItem value="pending">Đang tạo</SelectItem>
                    <SelectItem value="generated">Đã tạo</SelectItem>
                    <SelectItem value="sent">Đã gửi</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="type" @update:model-value="applyFilter">
                <SelectTrigger class="w-[150px]">
                    <SelectValue placeholder="Loại giao dịch" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả loại</SelectItem>
                    <SelectItem value="payment_success">Thanh toán thành công</SelectItem>
                    <SelectItem value="upcoming_renewal">Hóa đơn gia hạn sắp tới</SelectItem>
                </SelectContent>
            </Select>
            <Input
                v-model="dateFrom"
                type="date"
                class="w-[150px]"
                @change="applyFilter"
            />
            <Input
                v-model="dateTo"
                type="date"
                class="w-[150px]"
                @change="applyFilter"
            />
            <template v-if="hasActiveFilter()" #actions>
                <Button
                    variant="ghost"
                    size="sm"
                    @click="resetFilters"
                    class="cursor-pointer text-xs text-muted-foreground"
                    >Xoá lọc</Button
                >
            </template>
        </FilterBar>

        <!-- Invoices Table -->
        <Card class="overflow-hidden border border-border/60 shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr
                            class="border-b border-border/60 bg-muted/40 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >
                            <th class="w-[160px] p-4">Số hóa đơn</th>
                            <th class="p-4">Nhà hàng</th>
                            <th class="w-[180px] p-4 text-center">
                                Gói dịch vụ
                            </th>
                            <th class="w-[130px] p-4 text-center">
                                Trạng thái
                            </th>
                            <th class="w-[130px] p-4 text-center">Chu kỳ</th>
                            <th class="w-[140px] p-4 text-right">Tổng tiền</th>
                            <th class="w-[160px] p-4 text-center">Ngày tạo</th>
                            <th class="w-[120px] p-4 pr-6 text-right">
                                Thao tác
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40 text-xs">
                        <tr
                            v-for="order in orders.data"
                            :key="order.id"
                            class="cursor-pointer transition-all duration-150 hover:bg-muted/20"
                            @click="openOrderDetails(order)"
                        >
                            <!-- Invoice Number -->
                            <td
                                class="p-4 font-mono font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{ order.order_number }}
                            </td>

                            <!-- Restaurant details -->
                            <td class="p-4">
                                <div
                                    class="font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ order.restaurant }}
                                </div>
                                <div
                                    class="mt-0.5 font-mono text-[10px] text-muted-foreground"
                                >
                                    Code: {{ order.restaurant_code }}
                                </div>
                            </td>

                            <!-- Plan Name -->
                            <td class="p-4 text-center">
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ order.plan_name }}
                                </span>
                            </td>

                            <!-- Invoice Status -->
                            <td class="p-4 text-center">
                                <span
                                    class="inline-block rounded-lg border px-2 py-0.5 text-[10px] font-bold"
                                    :class="[
                                        order.payment_status === 'paid'
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                            : order.payment_status === 'unpaid'
                                              ? 'animate-pulse border-rose-500/20 bg-rose-500/10 text-rose-600'
                                              : 'border-amber-500/20 bg-amber-500/10 text-amber-600',
                                    ]"
                                >
                                    {{
                                        statusLabel[order.payment_status] ??
                                        order.payment_status
                                    }}
                                </span>
                            </td>

                            <!-- Billing Cycle -->
                            <td class="p-4 text-center">
                                <span
                                    class="inline-block rounded-lg border px-2 py-0.5 text-[10px] font-bold"
                                    :class="[
                                        order.billing_cycle === 'yearly'
                                            ? 'border-indigo-500/20 bg-indigo-500/10 text-indigo-600'
                                            : 'border-slate-500/20 bg-slate-500/10 text-slate-600',
                                    ]"
                                >
                                    {{
                                        billingCycleLabel[
                                            order.billing_cycle
                                        ] ?? order.billing_cycle
                                    }}
                                </span>
                            </td>

                            <!-- Total amount -->
                            <td
                                class="p-4 text-right font-mono font-bold text-slate-800 tabular-nums dark:text-slate-100"
                            >
                                {{ order.total_amount }}đ
                            </td>

                            <!-- Created time -->
                            <td
                                class="p-4 text-center font-mono text-[11px] text-muted-foreground"
                            >
                                {{ order.created_at }}
                            </td>

                            <!-- Actions -->
                            <td class="p-4 pr-6 text-right" @click.stop>
                                <button
                                    type="button"
                                    @click="openOrderDetails(order)"
                                    class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-border/80 px-2.5 py-1 text-[11px] font-bold transition-all hover:bg-muted/70"
                                >
                                    <Eye class="mr-1 size-3" /> Chi tiết
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.data.length === 0">
                            <td
                                colspan="8"
                                class="p-8 text-center font-medium text-muted-foreground italic"
                            >
                                Không tìm thấy hóa đơn nào phù hợp với bộ lọc.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination link slots -->
            <div
                v-if="orders.last_page > 1"
                class="flex items-center justify-between border-t border-border/60 bg-muted/20 p-4"
            >
                <p class="text-xs text-muted-foreground">
                    Đang hiển thị trang
                    <strong>{{ orders.current_page }}</strong> trên tổng số
                    <strong>{{ orders.last_page }}</strong> trang (Tổng số
                    {{ orders.total }} hóa đơn)
                </p>
                <Pagination :links="orders.links" class="border-t-0 p-0" />
            </div>
        </Card>

        <!-- Invoice Detail Dialog -->
        <Dialog v-model:open="showDetails">
            <DialogContent
                class="flex max-h-[90vh] flex-col overflow-hidden p-6 sm:max-w-[550px]"
            >
                <DialogHeader class="shrink-0">
                    <DialogTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-800 dark:text-slate-100"
                    >
                        <ShoppingCart class="size-5 text-emerald-500" />
                        Hóa đơn: {{ selectedOrder?.order_number }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Chi tiết thanh toán, thông tin người mua và gói dịch vụ
                        đăng ký.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="selectedOrder"
                    class="flex-1 space-y-4 overflow-y-auto pt-4 pr-1"
                >
                    <!-- Buyer Info Section (Thông tin người mua) -->
                    <div
                        class="space-y-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] p-3.5 text-xs dark:bg-emerald-500/[0.05]"
                    >
                        <div
                            class="flex items-center gap-2 border-b border-emerald-500/20 pb-2 text-[11px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                        >
                            <User class="size-4 text-emerald-500" />
                            <span>Thông tin người mua / Khách hàng</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-0.5">
                            <div>
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Người mua / Đại diện</span
                                >
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >{{
                                        selectedOrder.buyer_name ||
                                        'Chưa cập nhật'
                                    }}</span
                                >
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Số điện thoại</span
                                >
                                <span
                                    class="flex items-center gap-1 font-mono text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <Phone class="size-3 text-slate-400" />
                                    {{
                                        selectedOrder.buyer_phone ||
                                        'Chưa cập nhật'
                                    }}
                                </span>
                            </div>
                            <div class="col-span-2">
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Thư điện tử liên hệ / Hóa đơn</span
                                >
                                <span
                                    class="block truncate font-mono text-xs font-bold text-slate-800 dark:text-slate-200"
                                    :title="selectedOrder.buyer_email"
                                >
                                    {{
                                        selectedOrder.buyer_email ||
                                        'Chưa cập nhật'
                                    }}
                                </span>
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Mã số thuế</span
                                >
                                <span
                                    class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >{{
                                        selectedOrder.tax_code ||
                                        'Chưa cập nhật'
                                    }}</span
                                >
                            </div>
                            <div class="col-span-2">
                                <span
                                    class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                    >Địa chỉ</span
                                >
                                <span
                                    class="flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300"
                                >
                                    <MapPin
                                        class="size-3 shrink-0 text-slate-400"
                                    />
                                    {{
                                        selectedOrder.buyer_address ||
                                        'Chưa cập nhật'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- General details summary grid -->
                    <div
                        class="grid grid-cols-2 gap-3.5 rounded-xl border border-border/40 bg-muted/40 p-3.5 text-xs"
                    >
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Nhà hàng</span
                            >
                            <span
                                class="font-bold text-slate-700 dark:text-slate-300"
                                >{{ selectedOrder.restaurant }}</span
                            >
                            <span
                                class="block font-mono text-[10px] text-muted-foreground"
                                >Code: {{ selectedOrder.restaurant_code }}</span
                            >
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Thời gian lập</span
                            >
                            <span
                                class="mt-0.5 flex items-center gap-1 font-mono font-bold text-slate-700 dark:text-slate-300"
                            >
                                <Clock class="size-3.5 text-slate-400" />
                                {{ selectedOrder.created_at }}
                            </span>
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Gói dịch vụ đăng ký</span
                            >
                            <span
                                class="mt-0.5 flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300"
                            >
                                {{ selectedOrder.plan_name }}
                            </span>
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Hạn thanh toán</span
                            >
                            <span
                                class="mt-0.5 flex items-center gap-1 font-mono font-bold text-slate-700 dark:text-slate-300"
                            >
                                <Calendar class="size-3.5 text-slate-400" />
                                {{ selectedOrder?.due_on }}
                            </span>
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Chu kỳ thanh toán</span
                            >
                            <span
                                class="mt-0.5 flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300"
                            >
                                {{
                                    (selectedOrder?.billing_cycle &&
                                        billingCycleLabel[
                                            selectedOrder.billing_cycle
                                        ]) ??
                                    selectedOrder?.billing_cycle
                                }}
                            </span>
                        </div>
                        <div>
                            <span class="block font-bold text-slate-400"
                                >Loại hóa đơn</span
                            >
                            <span
                                class="mt-0.5 flex items-center gap-1 font-bold text-slate-700 dark:text-slate-300"
                            >
                                {{
                                    (selectedOrder?.type &&
                                        typeLabel[selectedOrder.type]) ??
                                    selectedOrder?.type
                                }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="block font-bold text-slate-400"
                                >Thư điện tử nhận hóa đơn</span
                            >
                            <span
                                class="mt-0.5 block truncate font-mono font-medium text-slate-700 dark:text-slate-300"
                                :title="selectedOrder?.recipient_email"
                            >
                                {{ selectedOrder?.recipient_email }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="block font-bold text-slate-400"
                                >Trạng thái thanh toán</span
                            >
                            <span
                                class="mt-1 inline-block rounded-md border px-2 py-0.5 text-[10px] font-bold"
                                :class="
                                    selectedOrder?.payment_status === 'paid'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                        : 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                                "
                            >
                                {{
                                    (selectedOrder?.payment_status &&
                                        statusLabel[selectedOrder.payment_status]) ??
                                    selectedOrder?.payment_status
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Total Calculation Summary -->
                    <div
                        class="flex shrink-0 items-center justify-between rounded-xl bg-slate-950 p-3 font-mono text-white"
                    >
                        <span
                            class="flex items-center gap-1 text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >
                            <CreditCard class="size-4 text-emerald-400" /> Tổng
                            tiền thanh toán
                        </span>
                        <span class="text-base font-black text-emerald-400">
                            {{ selectedOrder.total_amount }}đ
                        </span>
                    </div>
                </div>

                <div
                    class="mt-4 flex shrink-0 justify-end border-t border-border/50 pt-4"
                >
                    <button
                        type="button"
                        @click="closeOrderDetails"
                        class="inline-flex h-9 cursor-pointer items-center justify-center rounded-xl border border-border px-4 text-xs font-bold transition-all hover:bg-muted/50"
                    >
                        Đóng cửa sổ
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
