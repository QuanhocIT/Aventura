<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    DollarSign,
    TrendingUp,
    ShoppingCart,
    Building2,
    Crown,
    Download,
    AlertTriangle,
    CreditCard,
    Wallet,
    Landmark,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AreaChart from '@/components/charts/AreaChart.vue';
import {
    PageHeader,
    StatCard,
    SectionCard,
    ProgressBar,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    revenueByRestaurant: Array<{
        id: number;
        name: string;
        code: string;
        revenue: number;
        orders_count: number;
    }>;
    revenueByBranch: Array<{
        id: number;
        name: string;
        revenue: number;
        orders_count: number;
    }>;
    dailyRevenue: Array<{ label: string; value: number; orders_count: number }>;
    compareRevenue: Array<{ label: string; value: number }>;
    revenueByPlan: Array<{ name: string; code: string; revenue: number }>;
    revenueByPaymentMethod: Array<{
        method: string;
        revenue: number;
        count: number;
    }>;
    topItems: Array<{ name: string; quantity: number; revenue: number }>;
    retentionRate: number;
    forecastRevenue: Array<{
        label: string;
        value: number;
        is_forecast: boolean;
    }>;
    anomalies: Array<{
        type: string;
        severity: string;
        title: string;
        message: string;
    }>;
    plans: Array<{ id: number; code: string; name: string }>;
    restaurants: Array<{ id: number; name: string; code: string }>;
    stats: {
        total_revenue: number;
        total_orders: number;
        avg_order_value: number;
        active_restaurants: number;
    };
    filters: { range: string; plan?: string; restaurant_id?: string };
}>();

const range = ref(props.filters.range);
const planFilter = ref(props.filters.plan || 'all');
const restaurantFilter = ref(props.filters.restaurant_id || 'all');
const revenueByPlanPage = ref(1);
const revenueByPlanPerPage = 5;

const paginatedRevenueByPlan = computed(() => {
    const start = (revenueByPlanPage.value - 1) * revenueByPlanPerPage;

    return props.revenueByPlan.slice(start, start + revenueByPlanPerPage);
});

const revenueByPlanTotalPages = computed(() =>
    Math.ceil(props.revenueByPlan.length / revenueByPlanPerPage),
);

watch(
    () => props.revenueByPlan,
    () => {
        revenueByPlanPage.value = 1;
    },
);
const chartMode = ref<'compare' | 'forecast'>('compare'); // 'compare' (so sánh kỳ trước) hoặc 'forecast' (dự báo AI)

function applyFilter() {
    router.get(
        '/super-admin/revenue',
        {
            range: range.value,
            plan: planFilter.value !== 'all' ? planFilter.value : undefined,
            restaurant_id:
                restaurantFilter.value !== 'all'
                    ? restaurantFilter.value
                    : undefined,
        },
        { preserveState: true, replace: true },
    );
}

function exportCSV() {
    const url = `/super-admin/revenue/export?range=${range.value}&plan=${planFilter.value !== 'all' ? planFilter.value : ''}&restaurant_id=${restaurantFilter.value !== 'all' ? restaurantFilter.value : ''}`;
    window.open(url, '_blank');
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(val);
}

const paymentMethodLabels: Record<string, string> = {
    cash: 'Tiền mặt',
    ewallet: 'Ví điện tử',
    bank_transfer: 'Chuyển khoản QR',
    card: 'Thẻ tín dụng',
};

const maxRestaurantRevenue = Math.max(
    ...(props.revenueByRestaurant.map((r) => r.revenue) || [1]),
);
const maxBranchRevenue = Math.max(
    ...(props.revenueByBranch.map((b) => b.revenue) || [1]),
);
const maxPlanRevenue = Math.max(
    ...(props.revenueByPlan.map((p) => p.revenue) || [1]),
);
void maxPlanRevenue;
const maxItemQty = Math.max(...(props.topItems.map((i) => i.quantity) || [1]));
const totalPaymentsSum =
    props.revenueByPaymentMethod.reduce((sum, item) => sum + item.revenue, 0) ||
    1;
</script>

<template>
    <Head title="Doanh thu toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- AI Anomalies Alert -->
        <div v-if="anomalies && anomalies.length > 0" class="space-y-3">
            <div
                v-for="(anomaly, idx) in anomalies"
                :key="idx"
                class="flex items-start gap-3 rounded-lg border border-rose-500/20 bg-rose-500/10 p-4 text-rose-700 shadow-xs dark:text-rose-300"
            >
                <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                <div class="space-y-1">
                    <h5 class="text-sm font-bold">{{ anomaly.title }}</h5>
                    <p class="text-xs">{{ anomaly.message }}</p>
                </div>
            </div>
        </div>

        <PageHeader
            title="Doanh thu toàn hệ thống"
            subtitle="Phân tích doanh thu toàn hệ thống, nhà hàng nổi bật và xu hướng."
            :icon="DollarSign"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2.5">
                    <!-- Nhà hàng filter -->
                    <Select
                        v-model="restaurantFilter"
                        @update:model-value="applyFilter"
                    >
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Lọc theo nhà hàng" />
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

                    <!-- Gói cước filter -->
                    <Select
                        v-model="planFilter"
                        @update:model-value="applyFilter"
                    >
                        <SelectTrigger class="w-[165px]">
                            <SelectValue placeholder="Lọc theo gói cước" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả gói cước</SelectItem>
                            <SelectItem
                                v-for="p in plans"
                                :key="p.code"
                                :value="p.code"
                                >{{ p.name }}</SelectItem
                            >
                        </SelectContent>
                    </Select>

                    <!-- Khoảng thời gian filter -->
                    <Select v-model="range" @update:model-value="applyFilter">
                        <SelectTrigger class="w-[130px]">
                            <SelectValue placeholder="Khoảng thời gian" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="7">7 ngày</SelectItem>
                            <SelectItem value="30">30 ngày</SelectItem>
                            <SelectItem value="90">90 ngày</SelectItem>
                        </SelectContent>
                    </Select>

                    <!-- Nút Xuất CSV -->
                    <Button
                        variant="outline"
                        @click="exportCSV"
                        class="h-10 cursor-pointer gap-1.5 text-xs font-semibold"
                    >
                        <Download class="size-4" /> Xuất báo cáo
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard
                label="Tổng doanh thu"
                :value="formatVND(stats.total_revenue)"
                :icon="DollarSign"
                color="emerald"
                class=""
            />
            <StatCard
                label="Tổng đơn hàng"
                :value="stats.total_orders"
                :icon="ShoppingCart"
                color="sky"
                class=""
            />
            <StatCard
                label="Giá trị trung bình/đơn"
                :value="formatVND(stats.avg_order_value)"
                :icon="TrendingUp"
                color="violet"
                class=""
            />
            <StatCard
                label="Nhà hàng hoạt động"
                :value="stats.active_restaurants"
                :icon="Building2"
                color="amber"
                class=""
            />
            <StatCard
                label="Tỷ lệ quay lại"
                :value="`${retentionRate}%`"
                :icon="TrendingUp"
                color="pink"
                class=""
            />
        </div>

        <!-- Analytics & Forecasting row -->
        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <!-- Area Chart with Compare / Forecast toggling -->
            <SectionCard
                accent-color="emerald"
                class="flex flex-col justify-between"
            >
                <div>
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <TrendingUp class="size-4 text-emerald-500" />
                                Xu hướng doanh thu & AI Phân tích
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    chartMode === 'compare'
                                        ? 'So sánh doanh thu thực tế kỳ này với kỳ trước'
                                        : 'Doanh thu thực tế tích hợp dự báo AI 7 ngày tới'
                                }}
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-1.5 rounded-lg border border-border/40 bg-secondary/20 p-1 text-[10px] font-bold"
                        >
                            <button
                                type="button"
                                class="cursor-pointer rounded px-2 py-1 transition-all"
                                :class="
                                    chartMode === 'compare'
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="chartMode = 'compare'"
                            >
                                So sánh kỳ trước
                            </button>
                            <button
                                type="button"
                                class="cursor-pointer rounded px-2 py-1 transition-all"
                                :class="
                                    chartMode === 'forecast'
                                        ? 'bg-background text-foreground shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="chartMode = 'forecast'"
                            >
                                Dự báo AI
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex h-56 items-end">
                        <AreaChart
                            v-if="dailyRevenue && dailyRevenue.length > 0"
                            :series="dailyRevenue"
                            :compare-series="
                                chartMode === 'compare'
                                    ? compareRevenue
                                    : forecastRevenue
                            "
                            gradient-id="revenueGrowthGrad"
                            color="#10b981"
                            :compare-color="
                                chartMode === 'compare' ? '#3b82f6' : '#64748b'
                            "
                            class="h-full w-full"
                        >
                            <template #tooltip="{ point, comparePoint }">
                                <div
                                    class="flex flex-col gap-0.5 text-[10px] font-bold text-foreground"
                                >
                                    <template v-if="point">
                                        <span
                                            class="font-mono text-[8px] tracking-wider text-muted-foreground uppercase"
                                            >{{ point.label }} (Kỳ này)</span
                                        >
                                        <span>{{
                                            formatVND(point.value)
                                        }}</span>
                                    </template>
                                    <template v-if="comparePoint">
                                        <span
                                            class="mt-1 font-mono text-[8px] tracking-wider text-muted-foreground uppercase"
                                        >
                                            {{
                                                chartMode === 'compare'
                                                    ? 'Kỳ trước'
                                                    : 'AI Dự báo'
                                            }}
                                        </span>
                                        <span
                                            :class="
                                                chartMode === 'compare'
                                                    ? 'text-sky-500'
                                                    : 'text-slate-500 dark:text-slate-400'
                                            "
                                            >{{
                                                formatVND(comparePoint.value)
                                            }}</span
                                        >
                                    </template>
                                </div>
                            </template>
                        </AreaChart>
                        <div
                            v-else
                            class="w-full py-12 text-center text-xs text-muted-foreground"
                        >
                            Chưa có dữ liệu doanh thu
                        </div>
                    </div>

                    <!-- Chart Legend -->
                    <div
                        class="mt-3 flex items-center justify-center gap-4 text-[10px] font-bold text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><span
                                class="size-2 rounded-full bg-emerald-500"
                            ></span>
                            Doanh thu kỳ này</span
                        >
                        <span
                            v-if="chartMode === 'compare'"
                            class="flex items-center gap-1"
                            ><span
                                class="size-2 rounded-full bg-sky-500"
                            ></span>
                            Doanh thu kỳ trước</span
                        >
                        <span v-else class="flex items-center gap-1"
                            ><span
                                class="size-2 rounded-full bg-slate-500"
                            ></span>
                            AI Dự báo (7 ngày tiếp theo)</span
                        >
                    </div>
                </div>
            </SectionCard>

            <!-- Plan Revenue Distribution -->
            <SectionCard
                accent-color="violet"
                class="flex flex-col justify-between"
            >
                <div>
                    <div
                        class="mb-4 flex items-center justify-between border-b border-border/40 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <Crown class="size-4 text-violet-500" /> Doanh
                                thu theo gói cước
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Tổng doanh thu phát sinh nhóm theo phân khúc gói
                                dịch vụ
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div
                            v-for="plan in paginatedRevenueByPlan"
                            :key="plan.code"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span
                                    class="flex items-center gap-1 text-foreground"
                                >
                                    <Crown
                                        v-if="
                                            plan.code === 'pro' ||
                                            plan.code === 'enterprise'
                                        "
                                        class="size-3.5 text-amber-500"
                                    />
                                    {{ plan.name }}
                                </span>
                                <span
                                    class="text-muted-foreground tabular-nums"
                                    >{{ formatVND(plan.revenue) }}</span
                                >
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-slate-400': plan.code === 'free',
                                        'bg-sky-500': plan.code === 'starter',
                                        'bg-purple-500': plan.code === 'pro',
                                        'bg-emerald-500':
                                            plan.code === 'enterprise',
                                    }"
                                    :style="{
                                        width: `${(plan.revenue / (stats.total_revenue || 1)) * 100}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="revenueByPlanTotalPages > 1"
                        class="mt-5 flex flex-wrap items-center justify-center gap-2 border-t border-border/40 pt-4"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            :disabled="revenueByPlanPage === 1"
                            @click="revenueByPlanPage--"
                        >
                            Trang trước
                        </Button>

                        <div class="flex items-center gap-1">
                            <Button
                                v-for="page in revenueByPlanTotalPages"
                                :key="page"
                                size="sm"
                                :variant="
                                    revenueByPlanPage === page
                                        ? 'default'
                                        : 'outline'
                                "
                                class="size-8 cursor-pointer px-0"
                                :class="
                                    revenueByPlanPage === page
                                        ? 'bg-violet-600 text-white hover:bg-violet-700'
                                        : ''
                                "
                                @click="revenueByPlanPage = page"
                            >
                                {{ page }}
                            </Button>
                        </div>

                        <Button
                            variant="outline"
                            size="sm"
                            class="cursor-pointer"
                            :disabled="
                                revenueByPlanPage === revenueByPlanTotalPages
                            "
                            @click="revenueByPlanPage++"
                        >
                            Trang sau
                        </Button>
                    </div>
                </div>
            </SectionCard>
        </div>

        <!-- Payments & Products Grid Row -->
        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <!-- Top Selling Products -->
            <SectionCard accent-color="amber" class="">
                <div
                    class="mb-4 flex items-center justify-between border-b border-border/40 pb-2"
                >
                    <div class="space-y-1">
                        <h3
                            class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                        >
                            <Crown class="size-4 text-amber-500" /> Top 5 món ăn
                            bán chạy nhất hệ thống
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Các món ăn có lượng bán hàng đầu của toàn hệ thống
                            trong kỳ lọc
                        </p>
                    </div>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(item, idx) in topItems"
                        :key="idx"
                        class="flex items-center gap-3 rounded-xl border border-border/30 p-3 transition-all duration-200 hover:bg-muted/20"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-xs font-black text-amber-500"
                        >
                            #{{ idx + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="truncate text-sm font-medium">
                                    {{ item.name }}
                                </p>
                                <span
                                    class="shrink-0 font-mono text-xs font-bold text-amber-600 tabular-nums dark:text-amber-400"
                                >
                                    {{ formatVND(item.revenue) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <ProgressBar
                                    :value="item.quantity"
                                    :max="maxItemQty"
                                    color="amber"
                                    class="flex-1"
                                />
                                <span
                                    class="shrink-0 text-[10px] text-muted-foreground"
                                    >{{ item.quantity }} phần</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="!topItems || !topItems.length"
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu sản phẩm.
                    </p>
                </div>
            </SectionCard>

            <!-- Payment Methods -->
            <SectionCard
                accent-color="sky"
                class="flex flex-col justify-between"
            >
                <div>
                    <div
                        class="mb-4 flex items-center justify-between border-b border-border/40 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <CreditCard class="size-4 text-sky-500" /> Phân
                                tích Kênh Thanh toán
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Tỷ lệ sử dụng và doanh thu theo các cổng thanh
                                toán
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div
                            v-for="pay in revenueByPaymentMethod"
                            :key="pay.method"
                            class="space-y-2 rounded-lg border border-border/40 bg-secondary/15 p-3"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span
                                    class="flex items-center gap-2 font-bold text-foreground"
                                >
                                    <Wallet
                                        v-if="pay.method === 'ewallet'"
                                        class="size-4 text-rose-500"
                                    />
                                    <Landmark
                                        v-else-if="
                                            pay.method === 'bank_transfer'
                                        "
                                        class="size-4 text-sky-500"
                                    />
                                    <CreditCard
                                        v-else-if="pay.method === 'card'"
                                        class="size-4 text-purple-500"
                                    />
                                    <DollarSign
                                        v-else
                                        class="size-4 text-emerald-500"
                                    />
                                    {{
                                        paymentMethodLabels[pay.method] ??
                                        pay.method
                                    }}
                                </span>
                                <span
                                    class="text-muted-foreground tabular-nums"
                                    >{{ formatVND(pay.revenue) }}</span
                                >
                            </div>
                            <div class="flex items-center gap-2">
                                <ProgressBar
                                    :value="pay.revenue"
                                    :max="totalPaymentsSum"
                                    color="sky"
                                    class="flex-1"
                                />
                                <span
                                    class="shrink-0 text-[10px] text-muted-foreground tabular-nums"
                                    >{{ pay.count }} đơn ({{
                                        Math.round(
                                            (pay.revenue / totalPaymentsSum) *
                                                100,
                                        )
                                    }}%)</span
                                >
                            </div>
                        </div>
                        <p
                            v-if="!revenueByPaymentMethod.length"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            Chưa có dữ liệu thanh toán.
                        </p>
                    </div>
                </div>
            </SectionCard>
        </div>

        <!-- Dynamic Top Performers List (Top restaurants vs branches) -->
        <div class="grid gap-6">
            <!-- If no specific restaurant is selected -->
            <SectionCard
                v-if="!restaurantFilter"
                accent-color="emerald"
                class=""
            >
                <div
                    class="mb-4 flex items-center gap-2 border-b border-border/40 pb-2 text-sm font-bold"
                >
                    <Crown class="size-4 text-emerald-500" />
                    Top nhà hàng theo doanh thu
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(r, idx) in revenueByRestaurant"
                        :key="r.id"
                        class="flex items-center gap-3 rounded-xl border border-border/30 p-3 transition-all duration-200 hover:bg-muted/20"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-black text-primary"
                        >
                            {{ idx + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="truncate text-sm font-medium">
                                    {{ r.name }}
                                </p>
                                <span
                                    class="shrink-0 font-mono text-xs font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                                >
                                    {{ formatVND(r.revenue) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <ProgressBar
                                    :value="r.revenue"
                                    :max="maxRestaurantRevenue"
                                    color="emerald"
                                    class="flex-1"
                                />
                                <span
                                    class="shrink-0 text-[10px] text-muted-foreground"
                                    >{{ r.orders_count }} đơn</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="
                            !revenueByRestaurant || !revenueByRestaurant.length
                        "
                        class="col-span-full py-8 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu doanh thu nhà hàng.
                    </p>
                </div>
            </SectionCard>

            <!-- If a restaurant filter is active -->
            <SectionCard v-else accent-color="emerald" class="">
                <div
                    class="mb-4 flex items-center gap-2 border-b border-border/40 pb-2 text-sm font-bold"
                >
                    <Building2 class="size-4 text-emerald-500" />
                    Top chi nhánh của nhà hàng theo doanh thu
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="(b, idx) in revenueByBranch"
                        :key="b.id"
                        class="flex items-center gap-3 rounded-xl border border-border/30 p-3 transition-all duration-200 hover:bg-muted/20"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-black text-primary"
                        >
                            {{ idx + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="truncate text-sm font-medium">
                                    {{ b.name }}
                                </p>
                                <span
                                    class="shrink-0 font-mono text-xs font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                                >
                                    {{ formatVND(b.revenue) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <ProgressBar
                                    :value="b.revenue"
                                    :max="maxBranchRevenue"
                                    color="emerald"
                                    class="flex-1"
                                />
                                <span
                                    class="shrink-0 text-[10px] text-muted-foreground"
                                    >{{ b.orders_count }} đơn</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="!revenueByBranch || !revenueByBranch.length"
                        class="col-span-full py-8 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu doanh thu chi nhánh.
                    </p>
                </div>
            </SectionCard>
        </div>
    </div>
</template>
