<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Banknote,
    Clock,
    Lightbulb,
    TrendingDown,
    TrendingUp,
    Trash2,
    Award,
    Sparkles,
    Info,
    Calendar,
    Activity,
    Inbox,
    Plus,
    FileText,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    dashboard: {
        total_waste_cost: number;
        total_revenue: number;
        waste_ratio: number;
        waste_count: number;
        benchmark_status: string;
        benchmark_label: string;
        by_category: {
            category: string;
            label: string;
            count: number;
            total_cost: number;
        }[];
        top_ingredients: {
            ingredient_id: number;
            name: string;
            total_qty: number;
            total_cost: number;
            waste_count: number;
        }[];
    };
    trend: { month: string; total_cost: number; count: number }[];
    suggestions: {
        type: string;
        icon: string;
        title: string;
        description: string;
    }[];
    expiring: {
        id: number;
        ingredient_name: string;
        batch_number: string;
        quantity_remaining: number;
        expiry_date: string;
        days_left: number;
    }[];
    ingredients?: {
        id: number;
        name: string;
        average_cost: number;
        unit?: { id: number; symbol: string } | null;
        stock?: number;
    }[];
    employees?: {
        id: number;
        full_name: string;
        job_title?: string;
    }[];
    recentWastes?: {
        id: number;
        is_approval: boolean;
        ingredient_name: string;
        quantity: number;
        unit_symbol: string;
        cost: number;
        notes: string | null;
        performed_by: string;
        employee_name: string;
        timestamp: number;
        occurred_at: string;
        status: string;
        rejection_reason?: string | null;
    }[];
    days: number;
    branchContext?: { scope: string; active_branch_id: number | null };
}>();

const activeTab = ref<'analytics' | 'record'>('analytics');

const wasteForm = useForm({
    ingredient_id: '',
    quantity: '',
    employee_id: '',
    waste_category: 'spoilage',
    notes: '',
    photo: null as File | null,
});

const estimatedWasteCost = computed(() => {
    const ing = props.ingredients?.find(
        (i) => i.id === Number(wasteForm.ingredient_id),
    );

    if (!ing || !wasteForm.quantity) {
        return 0;
    }

    return Number(wasteForm.quantity) * (ing.average_cost ?? 0);
});

function submitWaste() {
    wasteForm.post('/waste-management/record', {
        preserveScroll: true,
        onSuccess: () => {
            wasteForm.reset();
        },
    });
}

function vnd(amount: number | null | undefined): string {
    return (amount ?? 0).toLocaleString('vi-VN') + 'đ';
}

const benchmarkColor: Record<string, string> = {
    excellent: 'text-emerald-500 dark:text-emerald-400',
    normal: 'text-amber-500 dark:text-amber-400',
    high: 'text-rose-500 dark:text-rose-400',
};

const benchmarkBg: Record<string, string> = {
    excellent:
        'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 dark:border-emerald-500/30',
    normal: 'bg-amber-500/10 text-amber-500 border-amber-500/20 dark:border-amber-500/30',
    high: 'bg-rose-500/10 text-rose-500 border-rose-500/20 dark:border-rose-500/30',
};

const benchmarkBorderGlow: Record<string, string> = {
    excellent:
        'hover:shadow-[0_0_20px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-500/30 transition-all duration-300',
    normal: 'hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.15)] hover:border-amber-500/30 transition-all duration-300',
    high: 'hover:shadow-[0_0_20px_-3px_rgba(239,68,68,0.2)] hover:border-rose-500/30 transition-all duration-300',
};

const categoryColor: Record<string, string> = {
    spoilage: '#f97316', // orange
    expired: '#ef4444', // red
    damaged: '#eab308', // yellow
    cooking_loss: '#3b82f6', // blue
    order_cancellation: '#f43f5e', // rose
    theft: '#a855f7', // purple
    other: '#94a3b8', // gray
};

// Doughnut calculations
const hoveredCategoryIdx = ref<number | null>(null);

const totalCategoryCost = computed(() => {
    return props.dashboard.by_category.reduce(
        (sum, cat) => sum + cat.total_cost,
        0,
    );
});

const doughnutPaths = computed(() => {
    const list = props.dashboard.by_category;
    const total = totalCategoryCost.value;

    if (total === 0) {
        return [];
    }

    let currentAngle = -Math.PI / 2; // start at top (12 o'clock)

    return list.map((item, index) => {
        const pct = item.total_cost / total;
        const angle = pct * 2 * Math.PI;
        const start = currentAngle;
        const end = currentAngle + angle;
        currentAngle = end;

        // Arc coordinates with radius 80 centered at (100, 100)
        const startX = 100 + 80 * Math.cos(start);
        const startY = 100 + 80 * Math.sin(start);
        const endX = 100 + 80 * Math.cos(end);
        const endY = 100 + 80 * Math.sin(end);
        const largeArc = angle > Math.PI ? 1 : 0;

        const path = `M 100 100 L ${startX} ${startY} A 80 80 0 ${largeArc} 1 ${endX} ${endY} Z`;

        return {
            ...item,
            percentage: Math.round(pct * 100),
            color: categoryColor[item.category] ?? '#94a3b8',
            path,
            index,
        };
    });
});

const centerLabel = computed(() => {
    if (
        hoveredCategoryIdx.value !== null &&
        doughnutPaths.value[hoveredCategoryIdx.value]
    ) {
        return doughnutPaths.value[hoveredCategoryIdx.value].label;
    }

    return 'Tổng hao hụt';
});

const centerValue = computed(() => {
    if (
        hoveredCategoryIdx.value !== null &&
        doughnutPaths.value[hoveredCategoryIdx.value]
    ) {
        return (
            doughnutPaths.value[
                hoveredCategoryIdx.value
            ].total_cost.toLocaleString() + 'đ'
        );
    }

    return props.dashboard.total_waste_cost.toLocaleString() + 'đ';
});

const centerSub = computed(() => {
    if (
        hoveredCategoryIdx.value !== null &&
        doughnutPaths.value[hoveredCategoryIdx.value]
    ) {
        return (
            doughnutPaths.value[hoveredCategoryIdx.value].percentage +
            '% tổng chi phí'
        );
    }

    return props.dashboard.waste_count + ' lần ghi nhận';
});

// Area chart calculations for monthly trend
const hoveredTrendIdx = ref<number | null>(null);

const maxTrend = computed(() => {
    const list = props.trend;

    if (list.length === 0) {
        return 1;
    }

    return Math.max(...list.map((t) => t.total_cost), 1);
});

const chartWidth = 420;
const chartHeight = 110;

const trendPoints = computed(() => {
    const list = props.trend;

    if (list.length === 0) {
        return [];
    }

    return list.map((item, idx) => {
        const x =
            60 + (list.length > 1 ? (idx / (list.length - 1)) * chartWidth : 0);
        const y =
            20 + chartHeight - (item.total_cost / maxTrend.value) * chartHeight;

        return {
            x,
            y,
            raw: item,
            index: idx,
        };
    });
});

const trendLinePath = computed(() => {
    return trendPoints.value
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`,
        )
        .join(' ');
});

const trendAreaPath = computed(() => {
    const pts = trendPoints.value;

    if (pts.length === 0) {
        return '';
    }

    const line = trendLinePath.value;
    const firstX = pts[0].x.toFixed(1);
    const lastX = pts[pts.length - 1].x.toFixed(1);
    const bottomY = (20 + chartHeight).toFixed(1);

    return `${line} L ${lastX} ${bottomY} L ${firstX} ${bottomY} Z`;
});

const hoveredTrendPoint = computed(() => {
    if (
        hoveredTrendIdx.value !== null &&
        trendPoints.value[hoveredTrendIdx.value]
    ) {
        return trendPoints.value[hoveredTrendIdx.value];
    }

    return null;
});

const hoverRects = computed(() => {
    const pts = trendPoints.value;

    if (pts.length === 0) {
        return [];
    }

    const len = pts.length;
    const rectWidth = len > 1 ? chartWidth / (len - 1) : chartWidth;

    return pts.map((p) => {
        return {
            x: p.x - rectWidth / 2,
            width: rectWidth,
            index: p.index,
        };
    });
});

// Top ingredients max
const maxIngredientCost = computed(() => {
    const list = props.dashboard.top_ingredients;

    if (list.length === 0) {
        return 1;
    }

    return Math.max(...list.map((item) => item.total_cost), 1);
});
</script>

<template>
    <Head title="Hao hụt & Lãng phí" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-8">
        <div
            v-if="props.branchContext?.scope === 'all'"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-medium text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Đang xem <strong>Toàn chuỗi</strong>. Hao hụt và hủy hàng được tổng
            hợp từ các chi nhánh.
        </div>
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border/80 pb-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 ring-4 ring-rose-500/5 dark:text-rose-400"
                >
                    <Trash2 class="size-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-black tracking-tight text-foreground"
                    >
                        Quản lý Hao hụt & Lãng phí
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Dashboard phân tích, benchmark ngành F&B, ghi nhận đổ vỡ và AI gợi ý giảm lãng phí.
                    </p>
                </div>
            </div>

            <!-- Tab Switcher & Quick Record button -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center rounded-xl bg-muted/60 p-1 border border-border">
                    <button
                        @click="activeTab = 'analytics'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'analytics'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <Activity class="size-3.5 text-rose-500" />
                        Tổng quan & Phân tích
                    </button>
                    <button
                        @click="activeTab = 'record'"
                        class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all"
                        :class="
                            activeTab === 'record'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        <FileText class="size-3.5 text-rose-500" />
                        Ghi nhận & Lịch sử
                        <span
                            v-if="props.recentWastes?.length"
                            class="ml-1 rounded-full bg-rose-500/10 px-1.5 py-0.2 text-[10px] font-bold text-rose-600 dark:text-rose-400"
                        >
                            {{ props.recentWastes.length }}
                        </span>
                    </button>
                </div>

                <Button
                    v-if="activeTab !== 'record'"
                    @click="activeTab = 'record'"
                    class="bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 shadow-xs cursor-pointer"
                >
                    <Plus class="mr-1.5 size-4" />
                    Ghi nhận hao hụt
                </Button>
            </div>
        </div>

        <!-- ══ TAB: TỔNG QUAN & PHÂN TÍCH ══════════════════════════════════════ -->
        <template v-if="activeTab === 'analytics'">
            <!-- KPI Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- 1. Tổng hao hụt -->
            <Card
                class="group border border-border bg-card text-card-foreground shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
            >
                <CardContent class="flex h-full flex-col justify-between p-5">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Tổng hao hụt ({{ days }}D)</span
                            >
                            <div
                                class="rounded-lg bg-rose-500/10 p-1.5 text-rose-500 transition-transform group-hover:scale-110"
                            >
                                <AlertTriangle class="size-4" />
                            </div>
                        </div>
                        <p
                            class="text-2xl font-black tracking-tight text-rose-600 dark:text-rose-400"
                        >
                            {{ dashboard.total_waste_cost.toLocaleString() }}đ
                        </p>
                    </div>
                    <div
                        class="mt-4 flex items-center gap-1 text-[10px] font-semibold text-muted-foreground"
                    >
                        <Activity class="size-3 text-rose-400" />
                        <span
                            >{{ dashboard.waste_count }} lần ghi nhận hao
                            hụt</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Tỷ lệ hao hụt -->
            <Card
                :class="[
                    'group border bg-card text-card-foreground shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md',
                    benchmarkBorderGlow[dashboard.benchmark_status] ||
                        'border-border',
                ]"
            >
                <CardContent class="flex h-full flex-col justify-between p-5">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Tỷ lệ hao hụt</span
                            >
                            <div
                                :class="[
                                    'rounded-lg p-1.5 transition-transform group-hover:scale-110',
                                    benchmarkBg[dashboard.benchmark_status],
                                ]"
                            >
                                <TrendingDown
                                    v-if="
                                        dashboard.benchmark_status ===
                                            'excellent' ||
                                        dashboard.benchmark_status === 'normal'
                                    "
                                    class="size-4"
                                />
                                <TrendingUp v-else class="size-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <p
                                :class="[
                                    'text-2xl font-black tracking-tight',
                                    benchmarkColor[
                                        dashboard.benchmark_status
                                    ] || 'text-foreground',
                                ]"
                            >
                                {{ dashboard.waste_ratio }}%
                            </p>
                            <Badge
                                :class="[
                                    benchmarkBg[dashboard.benchmark_status],
                                    'shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold',
                                ]"
                            >
                                {{ dashboard.benchmark_label }}
                            </Badge>
                        </div>
                    </div>
                    <!-- Small Progress Indicator for Gold F&B benchmark standard (5%) -->
                    <div class="mt-4 space-y-1">
                        <div
                            class="flex justify-between text-[9px] font-semibold text-muted-foreground"
                        >
                            <span>Mục tiêu ngành: &le; 5%</span>
                            <span>{{ dashboard.waste_ratio }}% / 5%</span>
                        </div>
                        <div
                            class="h-1 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="[
                                    dashboard.benchmark_status === 'excellent'
                                        ? 'bg-emerald-500'
                                        : dashboard.benchmark_status ===
                                            'normal'
                                          ? 'bg-amber-500'
                                          : 'bg-rose-500',
                                ]"
                                :style="{
                                    width: `${Math.min((dashboard.waste_ratio / 5) * 100, 100)}%`,
                                }"
                            ></div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Doanh thu -->
            <Card
                class="group border border-border bg-card text-card-foreground shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
            >
                <CardContent class="flex h-full flex-col justify-between p-5">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Doanh thu ({{ days }}D)</span
                            >
                            <div
                                class="rounded-lg bg-emerald-500/10 p-1.5 text-emerald-500 transition-transform group-hover:scale-110"
                            >
                                <Banknote class="size-4" />
                            </div>
                        </div>
                        <p
                            class="text-2xl font-black tracking-tight text-foreground"
                        >
                            {{ dashboard.total_revenue.toLocaleString() }}đ
                        </p>
                    </div>
                    <div
                        class="mt-4 flex items-center gap-1.5 text-[10px] font-semibold text-muted-foreground"
                    >
                        <Info class="size-3.5 text-emerald-400" />
                        <span>Chuẩn F&B: Lãng phí 5-10% DT</span>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Sắp hết hạn -->
            <Card
                class="group border border-border bg-card text-card-foreground shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
            >
                <CardContent class="flex h-full flex-col justify-between p-5">
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <span
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Nguyên liệu cận date</span
                            >
                            <div
                                class="rounded-lg bg-amber-500/10 p-1.5 text-amber-500 transition-transform group-hover:scale-110"
                            >
                                <Clock class="size-4" />
                            </div>
                        </div>
                        <p
                            class="text-2xl font-black tracking-tight text-amber-600 dark:text-amber-400"
                        >
                            {{ expiring.length }} nguyên liệu
                        </p>
                    </div>
                    <div
                        class="mt-4 flex items-center gap-1 text-[10px] font-semibold text-muted-foreground"
                    >
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-2 w-2 rounded-full bg-amber-500"
                            ></span>
                        </span>
                        <span>Cần kiểm kê trong 3 ngày tới</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Phân loại nguyên nhân (SVG Doughnut Chart) -->
            <Card
                class="flex flex-col justify-between border border-border bg-card text-card-foreground shadow-sm"
            >
                <div class="border-b border-border/60 p-5">
                    <h3
                        class="flex items-center gap-2 text-base font-bold text-foreground"
                    >
                        <Activity class="size-4 text-orange-500" />
                        Phân loại nguyên nhân
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Cơ cấu chi phí hao hụt theo danh mục phân loại.
                    </p>
                </div>
                <CardContent class="flex flex-1 flex-col justify-center p-5">
                    <div
                        v-if="doughnutPaths.length"
                        class="flex flex-col gap-6"
                    >
                        <!-- Doughnut Chart Container -->
                        <div
                            class="relative mx-auto flex h-44 w-44 items-center justify-center"
                        >
                            <svg
                                viewBox="0 0 200 200"
                                class="h-full w-full -rotate-90 transform"
                            >
                                <g
                                    v-for="slice in doughnutPaths"
                                    :key="slice.category"
                                >
                                    <path
                                        :d="slice.path"
                                        :fill="slice.color"
                                        class="cursor-pointer stroke-card stroke-2 transition-all duration-300"
                                        :class="{
                                            'scale-[1.02] opacity-100 drop-shadow-[0_0_12px_rgba(0,0,0,0.3)] filter':
                                                hoveredCategoryIdx ===
                                                slice.index,
                                            'opacity-85':
                                                hoveredCategoryIdx !== null &&
                                                hoveredCategoryIdx !==
                                                    slice.index,
                                        }"
                                        @mouseenter="
                                            hoveredCategoryIdx = slice.index
                                        "
                                        @mouseleave="hoveredCategoryIdx = null"
                                    />
                                </g>
                                <!-- Central mask hole -->
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="62"
                                    class="fill-card"
                                />
                            </svg>
                            <!-- Center Data display -->
                            <div
                                class="pointer-events-none absolute flex max-w-full flex-col items-center justify-center px-4 text-center select-none"
                            >
                                <span
                                    class="max-w-[110px] truncate text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >{{ centerLabel }}</span
                                >
                                <span
                                    class="mt-0.5 max-w-[120px] truncate text-sm font-extrabold tracking-tight text-foreground"
                                    >{{ centerValue }}</span
                                >
                                <span
                                    class="mt-0.5 max-w-[110px] truncate text-[8px] font-semibold text-muted-foreground"
                                    >{{ centerSub }}</span
                                >
                            </div>
                        </div>

                        <!-- Custom Legend Rows -->
                        <div class="max-h-48 space-y-1.5 overflow-y-auto pr-1">
                            <div
                                v-for="slice in doughnutPaths"
                                :key="slice.category"
                                class="flex cursor-pointer items-center justify-between rounded-xl border border-border/40 bg-muted/5 p-2 transition-all duration-200 hover:bg-muted/15"
                                :class="{
                                    'border-primary/20 bg-muted/20':
                                        hoveredCategoryIdx === slice.index,
                                }"
                                @mouseenter="hoveredCategoryIdx = slice.index"
                                @mouseleave="hoveredCategoryIdx = null"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span
                                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor: slice.color,
                                        }"
                                    ></span>
                                    <span
                                        class="truncate text-xs font-semibold text-foreground"
                                        >{{ slice.label }}</span
                                    >
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                        >({{ slice.count }})</span
                                    >
                                </div>
                                <div
                                    class="ml-2 flex shrink-0 flex-col items-end"
                                >
                                    <span
                                        class="text-xs font-bold text-foreground"
                                        >{{
                                            slice.total_cost.toLocaleString()
                                        }}đ</span
                                    >
                                    <span
                                        class="font-mono text-[9px] font-bold text-muted-foreground"
                                        >{{ slice.percentage }}%</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="flex w-full flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/5 px-4 py-10 text-center"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground/60"
                        >
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">
                            Không có dữ liệu phân loại
                        </p>
                        <p
                            class="mt-1 max-w-[200px] text-[10px] text-muted-foreground"
                        >
                            Hệ thống chưa ghi nhận bất kỳ dữ liệu hao hụt nào.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Xu hướng hao hụt (SVG Area/Line Chart) -->
            <Card
                class="flex flex-col justify-between border border-border bg-card text-card-foreground shadow-sm"
            >
                <div class="border-b border-border/60 p-5">
                    <h3
                        class="flex items-center gap-2 text-base font-bold text-foreground"
                    >
                        <TrendingDown class="size-4 text-rose-500" />
                        Xu hướng hao hụt
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Biểu đồ tổng hợp 6 tháng gần nhất.
                    </p>
                </div>
                <CardContent class="flex flex-1 flex-col justify-center p-5">
                    <div v-if="trend.length" class="relative h-44 w-full">
                        <svg
                            viewBox="0 0 500 160"
                            class="h-full w-full overflow-visible select-none"
                            preserveAspectRatio="none"
                        >
                            <defs>
                                <linearGradient
                                    id="trendGrad"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stop-color="#f43f5e"
                                        stop-opacity="0.25"
                                    />
                                    <stop
                                        offset="100%"
                                        stop-color="#f43f5e"
                                        stop-opacity="0"
                                    />
                                </linearGradient>
                            </defs>

                            <!-- Horizontal Grid Lines -->
                            <g
                                opacity="0.06"
                                class="stroke-foreground"
                                stroke-width="1"
                                stroke-dasharray="3 3"
                            >
                                <line x1="60" y1="20" x2="480" y2="20" />
                                <line x1="60" y1="56.6" x2="480" y2="56.6" />
                                <line x1="60" y1="93.3" x2="480" y2="93.3" />
                                <line x1="60" y1="130" x2="480" y2="130" />
                            </g>

                            <!-- Axes lines -->
                            <line
                                x1="60"
                                y1="130"
                                x2="480"
                                y2="130"
                                class="stroke-border"
                                stroke-width="1"
                            />
                            <line
                                x1="60"
                                y1="20"
                                x2="60"
                                y2="130"
                                class="stroke-border"
                                stroke-width="1"
                            />

                            <!-- Y Axis ticks -->
                            <text
                                x="52"
                                y="24"
                                text-anchor="end"
                                class="fill-muted-foreground font-mono text-[8px] font-semibold"
                            >
                                {{ maxTrend.toLocaleString() }}đ
                            </text>
                            <text
                                x="52"
                                y="79"
                                text-anchor="end"
                                class="fill-muted-foreground font-mono text-[8px] font-semibold"
                            >
                                {{ Math.round(maxTrend / 2).toLocaleString() }}đ
                            </text>
                            <text
                                x="52"
                                y="134"
                                text-anchor="end"
                                class="fill-muted-foreground font-mono text-[8px] font-semibold"
                            >
                                0đ
                            </text>

                            <!-- Area & Line Paths -->
                            <path :d="trendAreaPath" fill="url(#trendGrad)" />
                            <path
                                :d="trendLinePath"
                                fill="none"
                                stroke="#f43f5e"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <!-- Points Dots & Hover Indicators -->
                            <g v-for="pt in trendPoints" :key="pt.raw.month">
                                <circle
                                    :cx="pt.x"
                                    :cy="pt.y"
                                    r="4"
                                    fill="#f43f5e"
                                    stroke="var(--card)"
                                    stroke-width="1.5"
                                    class="transition-all duration-150"
                                    :class="{
                                        'r-5 stroke-2 brightness-110':
                                            hoveredTrendIdx === pt.index,
                                    }"
                                />
                                <circle
                                    v-if="hoveredTrendIdx === pt.index"
                                    :cx="pt.x"
                                    :cy="pt.y"
                                    r="8"
                                    fill="#f43f5e"
                                    opacity="0.3"
                                    class="pointer-events-none animate-ping"
                                />
                            </g>

                            <!-- Interactive Vertical Tracker Line -->
                            <line
                                v-if="hoveredTrendPoint"
                                :x1="hoveredTrendPoint.x"
                                y1="20"
                                :x2="hoveredTrendPoint.x"
                                y2="130"
                                stroke="#f43f5e"
                                stroke-dasharray="2 2"
                                stroke-width="1"
                                opacity="0.4"
                            />

                            <!-- X Axis labels -->
                            <text
                                v-for="pt in trendPoints"
                                :key="pt.raw.month"
                                :x="pt.x"
                                y="146"
                                text-anchor="middle"
                                class="fill-muted-foreground font-mono text-[8px] font-bold transition-colors"
                                :class="{
                                    'fill-foreground font-extrabold':
                                        hoveredTrendIdx === pt.index,
                                }"
                            >
                                {{ pt.raw.month.slice(5) }}
                            </text>

                            <!-- Interactive invisible hover regions -->
                            <rect
                                v-for="rect in hoverRects"
                                :key="rect.index"
                                :x="rect.x"
                                y="10"
                                :width="rect.width"
                                height="130"
                                fill="transparent"
                                class="cursor-crosshair"
                                @mouseenter="hoveredTrendIdx = rect.index"
                                @mouseleave="hoveredTrendIdx = null"
                            />
                        </svg>

                        <!-- HTML Hover Floating Tooltip inside SVG container -->
                        <div
                            v-if="hoveredTrendPoint"
                            class="pointer-events-none absolute z-20 flex flex-col gap-1 rounded-xl border border-rose-500/20 bg-background/95 p-3 text-xs font-semibold text-foreground shadow-xl backdrop-blur-md transition-all duration-75"
                            :style="{
                                left: `${(hoveredTrendPoint.x / 500) * 100}%`,
                                top: `${((hoveredTrendPoint.y - 50) / 160) * 100}%`,
                                transform: 'translateX(-50%)',
                            }"
                        >
                            <div
                                class="mb-1 flex items-center gap-1.5 border-b border-border/50 pb-1"
                            >
                                <Calendar
                                    class="size-3 text-muted-foreground"
                                />
                                <span
                                    class="font-mono text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >{{ hoveredTrendPoint.raw.month }}</span
                                >
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span
                                    class="text-[9px] leading-none text-muted-foreground"
                                    >Tổng hao hụt</span
                                >
                                <span
                                    class="text-sm leading-none font-extrabold text-rose-500"
                                >
                                    {{
                                        hoveredTrendPoint.raw.total_cost.toLocaleString()
                                    }}đ
                                </span>
                            </div>
                            <div
                                class="mt-1 flex items-center gap-1 text-[10px] font-medium text-muted-foreground"
                            >
                                <Activity class="size-3 text-rose-400" />
                                <span
                                    >{{ hoveredTrendPoint.raw.count }} lần ghi
                                    nhận</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="flex w-full flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/5 px-4 py-10 text-center"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground/60"
                        >
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">
                            Chưa có dữ liệu xu hướng
                        </p>
                        <p
                            class="mt-1 max-w-[200px] text-[10px] text-muted-foreground"
                        >
                            Không tìm thấy số liệu xu hướng cho 6 tháng vừa qua.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Top nguyên liệu hao hụt (Leaderboard) -->
            <Card
                class="flex flex-col justify-between border border-border bg-card text-card-foreground shadow-sm"
            >
                <div class="border-b border-border/60 p-5">
                    <h3
                        class="flex items-center gap-2 text-base font-bold text-foreground"
                    >
                        <Award class="size-4 animate-pulse text-amber-500" />
                        Top nguyên liệu hao hụt
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Nguyên liệu lãng phí nhiều nhất.
                    </p>
                </div>
                <CardContent class="flex flex-1 flex-col justify-center p-5">
                    <div
                        v-if="dashboard.top_ingredients.length"
                        class="space-y-3"
                    >
                        <div
                            v-for="(
                                item, idx
                            ) in dashboard.top_ingredients.slice(0, 6)"
                            :key="item.ingredient_id"
                            class="group relative flex flex-col gap-1.5 rounded-xl border border-border/40 bg-muted/5 p-2.5 transition-all duration-200 hover:bg-muted/15"
                        >
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <!-- Rank Index -->
                                    <span
                                        :class="[
                                            'flex h-5 w-5 shrink-0 items-center justify-center rounded-lg border text-[10px] font-extrabold',
                                            idx === 0
                                                ? 'border-amber-500/20 bg-amber-500/10 text-amber-500 shadow-xs'
                                                : idx === 1
                                                  ? 'border-slate-300/20 bg-slate-300/10 text-slate-400'
                                                  : idx === 2
                                                    ? 'border-amber-700/20 bg-amber-700/10 text-amber-700 dark:text-amber-600'
                                                    : 'border-border bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        <span
                                            v-if="idx < 3"
                                            class="relative flex items-center justify-center"
                                            >🏆</span
                                        >
                                        <span v-else>{{ idx + 1 }}</span>
                                    </span>
                                    <span
                                        class="truncate font-bold text-foreground"
                                        >{{ item.name }}</span
                                    >
                                </div>
                                <span
                                    class="ml-2 shrink-0 font-black text-rose-500 dark:text-rose-400"
                                >
                                    {{ item.total_cost.toLocaleString() }}đ
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div
                                class="flex items-center justify-between text-[10px] text-muted-foreground"
                            >
                                <span
                                    >Lượng hủy:
                                    <span
                                        class="font-semibold text-foreground/85"
                                        >{{ item.total_qty }} đơn vị</span
                                    ></span
                                >
                                <span>{{ item.waste_count }} lần ghi nhận</span>
                            </div>

                            <div
                                class="mt-0.5 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500 ease-out"
                                    :class="[
                                        idx === 0
                                            ? 'bg-gradient-to-r from-rose-500 to-orange-500 shadow-[0_0_8px_rgba(244,63,94,0.3)]'
                                            : idx === 1
                                              ? 'bg-rose-500/80'
                                              : 'bg-rose-500/60',
                                    ]"
                                    :style="{
                                        width: `${(item.total_cost / maxIngredientCost) * 100}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="flex w-full flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/5 px-4 py-10 text-center"
                    >
                        <div
                            class="mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-muted text-muted-foreground/60"
                        >
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">
                            Không có nguyên liệu hao hụt
                        </p>
                        <p
                            class="mt-1 max-w-[200px] text-[10px] text-muted-foreground"
                        >
                            Hệ thống chưa ghi nhận bất kỳ nguyên liệu bị hủy
                            nào.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- AI Suggestions -->
        <Card
            class="border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="flex items-center gap-3 border-b border-border/60 p-5">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 ring-4 ring-amber-500/5"
                >
                    <Sparkles class="size-5 animate-pulse" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">
                        AI Gợi ý giảm hao hụt
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Khuyến nghị tự động từ hệ thống dựa trên phân tích dữ
                        liệu kho.
                    </p>
                </div>
            </div>
            <CardContent class="p-5">
                <div
                    v-if="suggestions.length"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <div
                        v-for="(s, idx) in suggestions"
                        :key="idx"
                        class="group relative overflow-hidden rounded-xl border border-border bg-muted/10 p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-amber-500/30 hover:bg-muted/15 hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.08)]"
                    >
                        <div
                            class="absolute -right-6 -bottom-6 h-20 w-20 rounded-full bg-amber-500/5 blur-xl transition-colors duration-300 group-hover:bg-amber-500/10"
                        ></div>

                        <div class="relative z-10 flex items-start gap-4">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 transition-transform duration-300 group-hover:scale-110"
                            >
                                <Sparkles
                                    v-if="s.type === 'ai' || idx === 0"
                                    class="size-5"
                                />
                                <Lightbulb v-else class="size-5" />
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <p
                                    class="flex items-center gap-1.5 text-sm leading-snug font-bold text-foreground"
                                >
                                    {{ s.title }}
                                    <Badge
                                        variant="outline"
                                        class="border-amber-500/20 bg-amber-500/5 px-1.5 py-0 font-mono text-[9px] font-bold text-amber-500"
                                    >
                                        {{
                                            s.type === 'ai'
                                                ? 'AI SUGGESTION'
                                                : 'GỢI Ý'
                                        }}
                                    </Badge>
                                </p>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ s.description }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Empty State -->
                <div
                    v-else
                    class="flex w-full flex-col items-center justify-center py-8 text-center text-muted-foreground/60"
                >
                    <Lightbulb class="mb-2 size-6 text-muted-foreground/30" />
                    <p class="text-xs font-semibold">
                        Chưa có gợi ý nào khả dụng.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Expiring Soon -->
        <Card
            v-if="expiring.length"
            class="border border-border bg-card text-card-foreground shadow-sm"
        >
            <div class="flex items-center gap-3 border-b border-border/60 p-5">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500 ring-4 ring-rose-500/5"
                >
                    <Clock class="size-5 animate-pulse" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">
                        Nguyên liệu sắp hết hạn (Trong 3 ngày tới)
                    </h3>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Hãy ưu tiên sử dụng hoặc ghi nhận hao hụt để tránh lãng
                        phí chi phí.
                    </p>
                </div>
            </div>
            <CardContent class="p-5">
                <div
                    class="overflow-hidden rounded-xl border border-border/80 bg-muted/5 shadow-xs"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-border bg-muted/30 font-semibold text-muted-foreground"
                                >
                                    <th class="px-5 py-3.5">Nguyên liệu</th>
                                    <th class="px-5 py-3.5">Lô hàng</th>
                                    <th class="px-5 py-3.5 text-right">
                                        Số lượng còn lại
                                    </th>
                                    <th class="px-5 py-3.5">Hạn sử dụng</th>
                                    <th class="px-5 py-3.5 text-right">
                                        Khẩn cấp
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr
                                    v-for="item in expiring"
                                    :key="item.id"
                                    class="transition-all duration-150 hover:bg-muted/15"
                                >
                                    <td
                                        class="px-5 py-3.5 font-bold text-foreground"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                :class="[
                                                    'h-2.5 w-2.5 shrink-0 rounded-full',
                                                    item.days_left <= 1
                                                        ? 'animate-ping bg-rose-500'
                                                        : 'bg-amber-500',
                                                ]"
                                            ></span>
                                            <span>{{
                                                item.ingredient_name
                                            }}</span>
                                        </div>
                                    </td>
                                    <td
                                        class="px-5 py-3.5 font-mono font-medium text-muted-foreground"
                                    >
                                        {{ item.batch_number || '—' }}
                                    </td>
                                    <td
                                        class="px-5 py-3.5 text-right font-bold text-foreground"
                                    >
                                        {{ item.quantity_remaining }}
                                    </td>
                                    <td
                                        class="px-5 py-3.5 text-muted-foreground"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <Clock
                                                class="size-3 text-muted-foreground/60"
                                            />
                                            <span>{{ item.expiry_date }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <Badge
                                            :variant="
                                                item.days_left <= 1
                                                    ? 'destructive'
                                                    : 'secondary'
                                            "
                                            class="shrink-0 border-0 px-2.5 py-0.5 font-mono text-[10px] font-semibold"
                                            :class="
                                                item.days_left <= 1
                                                    ? 'animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.2)]'
                                                    : ''
                                            "
                                        >
                                            {{
                                                item.days_left <= 0
                                                    ? 'Hết hạn hôm nay'
                                                    : item.days_left === 1
                                                      ? 'Còn 1 ngày'
                                                      : `Còn ${item.days_left} ngày`
                                            }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>
        </template>

        <!-- ══ TAB: GHI NHẬN & LỊCH SỬ HAO HỤT ════════════════════════════════ -->
        <template v-else-if="activeTab === 'record'">
            <div class="grid gap-6 lg:grid-cols-5">
                <!-- Form Ghi nhận hao hụt -->
                <Card class="border border-border bg-card text-card-foreground shadow-sm lg:col-span-2">
                    <CardHeader class="border-b border-border pb-3">
                        <CardTitle class="flex items-center gap-2 text-sm font-bold">
                            <Trash2 class="size-4 text-rose-500" />
                            Ghi nhận đổ vỡ & hỏng hóc
                        </CardTitle>
                        <CardDescription class="text-[11px] leading-relaxed">
                            Khai báo các sự cố mất mát thực tế ngoài ý muốn (sữa đổ, rau héo, cháy khét). Nguyên liệu bán hàng đã được hệ thống tự động trừ theo món.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-5">
                        <form @submit.prevent="submitWaste" class="space-y-4">
                            <!-- Nguyên liệu -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">
                                    Nguyên liệu <span class="text-rose-500">*</span>
                                </Label>
                                <select
                                    v-model="wasteForm.ingredient_id"
                                    required
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                                >
                                    <option value="" disabled>
                                        Chọn nguyên liệu...
                                    </option>
                                    <option
                                        v-for="ing in ingredients"
                                        :key="ing.id"
                                        :value="ing.id"
                                    >
                                        {{ ing.name }} (tồn:
                                        {{ ing.stock?.toFixed(1) ?? '—' }}
                                        {{ ing.unit?.symbol ?? '' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Nguyên nhân hao hụt -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Nguyên nhân</Label>
                                <select
                                    v-model="wasteForm.waste_category"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                                >
                                    <option value="spoilage">
                                        Hư hỏng / xuống chất lượng
                                    </option>
                                    <option value="expired">
                                        Hết hạn sử dụng
                                    </option>
                                    <option value="damaged">
                                        Hư hỏng bao bì
                                    </option>
                                    <option value="cooking_loss">
                                        Hao hụt chế biến
                                    </option>
                                    <option value="theft">Thất thoát</option>
                                    <option value="other">Khác</option>
                                </select>
                                <p class="text-[10px] text-muted-foreground">
                                    Chọn “Hết hạn” để hệ thống ưu tiên loại bỏ các lô đã quá HSD.
                                </p>
                            </div>

                            <!-- Số lượng -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">
                                    Số lượng hao hụt <span class="text-rose-500">*</span>
                                </Label>
                                <Input
                                    v-model="wasteForm.quantity"
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    placeholder="0"
                                    required
                                />
                            </div>

                            <!-- Chi phí ước tính -->
                            <div
                                v-if="estimatedWasteCost > 0"
                                class="flex items-center justify-between rounded-xl border border-rose-500/20 bg-rose-500/10 px-4 py-2.5 text-sm"
                            >
                                <span class="text-muted-foreground">Chi phí thiệt hại ước tính</span>
                                <span class="font-bold text-rose-600 dark:text-rose-400">
                                    {{ vnd(estimatedWasteCost) }}
                                </span>
                            </div>

                            <!-- Nhân viên chịu trách nhiệm -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">
                                    Nhân viên chịu trách nhiệm (nếu có)
                                </Label>
                                <select
                                    v-model="wasteForm.employee_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:ring-2 focus:ring-rose-500/20 focus:outline-none"
                                >
                                    <option value="">
                                        Không quy trách nhiệm
                                    </option>
                                    <option
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        :value="emp.id"
                                    >
                                        {{ emp.full_name }}{{ emp.job_title ? ' — ' + emp.job_title : '' }}
                                    </option>
                                </select>
                                <p class="text-[11px] text-muted-foreground">
                                    Nếu chọn nhân viên, hệ thống sẽ tự tạo khoản khấu trừ lương tháng này.
                                </p>
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">Ghi chú / lý do</Label>
                                <Input
                                    v-model="wasteForm.notes"
                                    placeholder="Ví dụ: Hư hỏng trong quá trình chế biến..."
                                />
                            </div>

                            <!-- Ảnh hàng hủy (BẮT BUỘC — bằng chứng chống gian lận) -->
                            <div class="space-y-1.5">
                                <Label class="text-xs">
                                    Ảnh hàng hủy <span class="text-rose-500">*</span>
                                </Label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    required
                                    @input="
                                        wasteForm.photo =
                                            ($event.target as HTMLInputElement)
                                                .files?.[0] ?? null
                                    "
                                    class="w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-rose-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-rose-700 dark:file:bg-rose-950/40 dark:file:text-rose-300"
                                />
                                <p
                                    v-if="wasteForm.errors.photo"
                                    class="text-[11px] font-semibold text-rose-500"
                                >
                                    {{ wasteForm.errors.photo }}
                                </p>
                                <p
                                    v-else
                                    class="text-[10px] text-muted-foreground"
                                >
                                    Chụp ảnh hàng thực tế bị hủy để chủ/quản lý đối chiếu khi duyệt.
                                </p>
                            </div>

                            <Button
                                type="submit"
                                class="w-full bg-rose-600 text-white hover:bg-rose-700 cursor-pointer"
                                :disabled="wasteForm.processing"
                            >
                                <Trash2 class="mr-2 size-4" />
                                {{
                                    wasteForm.processing
                                        ? 'Đang lưu...'
                                        : 'Xác nhận hao hụt'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Lịch sử hao hụt & trạng thái -->
                <div class="space-y-4 lg:col-span-3">
                    <Card class="border border-border bg-card text-card-foreground shadow-sm">
                        <CardHeader class="border-b border-border pb-3">
                            <CardTitle class="flex items-center justify-between text-sm font-bold">
                                <span>Lịch sử hao hụt & Trạng thái duyệt</span>
                                <span class="text-xs font-normal text-muted-foreground">
                                    Tối đa 15 giao dịch gần đây
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                v-if="!recentWastes || recentWastes.length === 0"
                                class="flex flex-col items-center gap-2 py-16 text-sm text-muted-foreground"
                            >
                                <Info class="size-8 text-muted-foreground opacity-30" />
                                <p>Chưa có dữ liệu hao hụt nào</p>
                            </div>
                            <div
                                v-else
                                class="max-h-[520px] divide-y divide-border overflow-y-auto"
                            >
                                <div
                                    v-for="w in recentWastes"
                                    :key="w.id + '-' + w.is_approval"
                                    class="space-y-2 p-4 text-xs transition-colors hover:bg-muted/10"
                                >
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-bold text-slate-800 dark:text-slate-200">
                                                    {{ w.ingredient_name }}
                                                </span>
                                                <span class="font-mono font-bold text-rose-600 dark:text-rose-400">
                                                    -{{ w.quantity }} {{ w.unit_symbol }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-[10px] text-muted-foreground">
                                                <span>Thời gian: {{ w.occurred_at }}</span>
                                                <span class="mx-1.5">·</span>
                                                <span>Người yêu cầu: {{ w.performed_by }}</span>
                                            </p>
                                            <p class="text-[10px] text-muted-foreground">
                                                <span>Khấu trừ lương: <strong>{{ w.employee_name }}</strong></span>
                                                <span v-if="w.notes" class="italic">
                                                    · Ghi chú: "{{ w.notes }}"
                                                </span>
                                            </p>
                                            <p
                                                v-if="w.rejection_reason && w.status === 'rejected'"
                                                class="mt-1 text-[10px] font-semibold text-rose-600"
                                            >
                                                Lý do từ chối: "{{ w.rejection_reason }}"
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                                            <span class="font-bold text-slate-800 dark:text-slate-200">
                                                Thành tiền:
                                                <span class="font-mono text-rose-600 dark:text-rose-400">
                                                    {{ vnd(w.cost) }}
                                                </span>
                                            </span>
                                            <!-- Trạng thái badge -->
                                            <span
                                                v-if="w.status === 'pending'"
                                                class="rounded-full border border-amber-200 bg-amber-100 px-2 py-0.5 text-[9px] font-bold text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                                            >
                                                Chờ duyệt
                                            </span>
                                            <span
                                                v-else-if="w.status === 'approved'"
                                                class="rounded-full border border-emerald-200 bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300"
                                            >
                                                Đã duyệt
                                            </span>
                                            <span
                                                v-else-if="w.status === 'rejected'"
                                                class="rounded-full border border-rose-200 bg-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
                                            >
                                                Bị từ chối
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Auto deduct info card -->
                    <div class="flex items-start gap-3 rounded-xl border border-border bg-muted/20 p-4 text-xs">
                        <Sparkles class="size-4 shrink-0 text-amber-500 mt-0.5" />
                        <div class="space-y-1 text-muted-foreground">
                            <p class="font-semibold text-foreground">Tự động trừ kho khi bán hàng</p>
                            <p class="text-[11px] leading-relaxed">
                                Bạn <strong>KHÔNG CẦN</strong> nhập thủ công nguyên liệu đã bán tại đây. Khi mỗi đơn hàng hoàn thành, hệ thống sẽ tự động nhân số lượng bán với định lượng công thức từng món để <strong>tự động trừ sạch</strong> lượng sữa, cà phê... tiêu thụ trong kho.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
