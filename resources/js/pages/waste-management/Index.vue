<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
    Inbox
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
        by_category: { category: string; label: string; count: number; total_cost: number }[];
        top_ingredients: { ingredient_id: number; name: string; total_qty: number; total_cost: number; waste_count: number }[];
    };
    trend: { month: string; total_cost: number; count: number }[];
    suggestions: { type: string; icon: string; title: string; description: string }[];
    expiring: { id: number; ingredient_name: string; batch_number: string; quantity_remaining: number; expiry_date: string; days_left: number }[];
    days: number;
}>();

const benchmarkColor: Record<string, string> = {
    excellent: 'text-emerald-500 dark:text-emerald-400',
    normal: 'text-amber-500 dark:text-amber-400',
    high: 'text-rose-500 dark:text-rose-400',
};

const benchmarkBg: Record<string, string> = {
    excellent: 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20 dark:border-emerald-500/30',
    normal: 'bg-amber-500/10 text-amber-500 border-amber-500/20 dark:border-amber-500/30',
    high: 'bg-rose-500/10 text-rose-500 border-rose-500/20 dark:border-rose-500/30',
};

const benchmarkBorderGlow: Record<string, string> = {
    excellent: 'hover:shadow-[0_0_20px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-500/30 transition-all duration-300',
    normal: 'hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.15)] hover:border-amber-500/30 transition-all duration-300',
    high: 'hover:shadow-[0_0_20px_-3px_rgba(239,68,68,0.2)] hover:border-rose-500/30 transition-all duration-300',
};

const categoryColor: Record<string, string> = {
    spoilage: '#f97316',     // orange
    expired: '#ef4444',       // red
    damaged: '#eab308',       // yellow
    cooking_loss: '#3b82f6',  // blue
    theft: '#a855f7',         // purple
    other: '#94a3b8',         // gray
};

// Doughnut calculations
const hoveredCategoryIdx = ref<number | null>(null);

const totalCategoryCost = computed(() => {
    return props.dashboard.by_category.reduce((sum, cat) => sum + cat.total_cost, 0);
});

const doughnutPaths = computed(() => {
    const list = props.dashboard.by_category;
    const total = totalCategoryCost.value;
    if (total === 0) return [];
    
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
            index
        };
    });
});

const centerLabel = computed(() => {
    if (hoveredCategoryIdx.value !== null && doughnutPaths.value[hoveredCategoryIdx.value]) {
        return doughnutPaths.value[hoveredCategoryIdx.value].label;
    }
    return 'Tổng hao hụt';
});

const centerValue = computed(() => {
    if (hoveredCategoryIdx.value !== null && doughnutPaths.value[hoveredCategoryIdx.value]) {
        return doughnutPaths.value[hoveredCategoryIdx.value].total_cost.toLocaleString() + 'đ';
    }
    return props.dashboard.total_waste_cost.toLocaleString() + 'đ';
});

const centerSub = computed(() => {
    if (hoveredCategoryIdx.value !== null && doughnutPaths.value[hoveredCategoryIdx.value]) {
        return doughnutPaths.value[hoveredCategoryIdx.value].percentage + '% tổng chi phí';
    }
    return props.dashboard.waste_count + ' lần ghi nhận';
});

// Area chart calculations for monthly trend
const hoveredTrendIdx = ref<number | null>(null);

const maxTrend = computed(() => {
    const list = props.trend;
    if (list.length === 0) return 1;
    return Math.max(...list.map(t => t.total_cost), 1);
});

const chartWidth = 420;
const chartHeight = 110;

const trendPoints = computed(() => {
    const list = props.trend;
    if (list.length === 0) return [];
    
    return list.map((item, idx) => {
        const x = 60 + (list.length > 1 ? (idx / (list.length - 1)) * chartWidth : 0);
        const y = 20 + chartHeight - (item.total_cost / maxTrend.value) * chartHeight;
        return {
            x,
            y,
            raw: item,
            index: idx
        };
    });
});

const trendLinePath = computed(() => {
    return trendPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(' ');
});

const trendAreaPath = computed(() => {
    const pts = trendPoints.value;
    if (pts.length === 0) return '';
    const line = trendLinePath.value;
    const firstX = pts[0].x.toFixed(1);
    const lastX = pts[pts.length - 1].x.toFixed(1);
    const bottomY = (20 + chartHeight).toFixed(1);
    return `${line} L ${lastX} ${bottomY} L ${firstX} ${bottomY} Z`;
});

const hoveredTrendPoint = computed(() => {
    if (hoveredTrendIdx.value !== null && trendPoints.value[hoveredTrendIdx.value]) {
        return trendPoints.value[hoveredTrendIdx.value];
    }
    return null;
});

const hoverRects = computed(() => {
    const pts = trendPoints.value;
    if (pts.length === 0) return [];
    const len = pts.length;
    const rectWidth = len > 1 ? chartWidth / (len - 1) : chartWidth;
    return pts.map(p => {
        return {
            x: p.x - rectWidth / 2,
            width: rectWidth,
            index: p.index
        };
    });
});

// Top ingredients max
const maxIngredientCost = computed(() => {
    const list = props.dashboard.top_ingredients;
    if (list.length === 0) return 1;
    return Math.max(...list.map(item => item.total_cost), 1);
});
</script>

<template>
    <Head title="Hao hụt & Lãng phí" />

    <div class="flex flex-col gap-6 p-4 lg:p-8 max-w-7xl mx-auto w-full">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border/80 pb-6">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-4 ring-rose-500/5">
                    <Trash2 class="size-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-foreground flex items-center gap-2">
                        Quản lý Hao hụt & Lãng phí
                    </h1>
                    <p class="text-sm text-muted-foreground">Dashboard phân tích, benchmark ngành F&B, và AI gợi ý giảm lãng phí.</p>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Tổng hao hụt -->
            <Card class="bg-card text-card-foreground border border-border shadow-xs hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 group">
                <CardContent class="p-5 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Tổng hao hụt ({{ days }}D)</span>
                            <div class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 group-hover:scale-110 transition-transform">
                                <AlertTriangle class="size-4" />
                            </div>
                        </div>
                        <p class="text-2xl font-black text-rose-600 dark:text-rose-400 tracking-tight">
                            {{ dashboard.total_waste_cost.toLocaleString() }}đ
                        </p>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-[10px] text-muted-foreground font-semibold">
                        <Activity class="size-3 text-rose-400" />
                        <span>{{ dashboard.waste_count }} lần ghi nhận hao hụt</span>
                    </div>
                </CardContent>
            </Card>

            <!-- 2. Tỷ lệ hao hụt -->
            <Card :class="[
                'bg-card text-card-foreground border shadow-xs hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 group',
                benchmarkBorderGlow[dashboard.benchmark_status] || 'border-border'
            ]">
                <CardContent class="p-5 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Tỷ lệ hao hụt</span>
                            <div :class="['p-1.5 rounded-lg group-hover:scale-110 transition-transform', benchmarkBg[dashboard.benchmark_status]]">
                                <TrendingDown v-if="dashboard.benchmark_status === 'excellent' || dashboard.benchmark_status === 'normal'" class="size-4" />
                                <TrendingUp v-else class="size-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <p :class="['text-2xl font-black tracking-tight', benchmarkColor[dashboard.benchmark_status] || 'text-foreground']">
                                {{ dashboard.waste_ratio }}%
                            </p>
                            <Badge :class="[benchmarkBg[dashboard.benchmark_status], 'text-[9px] px-2 py-0.5 font-bold border rounded-full shrink-0']">
                                {{ dashboard.benchmark_label }}
                            </Badge>
                        </div>
                    </div>
                    <!-- Small Progress Indicator for Gold F&B benchmark standard (5%) -->
                    <div class="mt-4 space-y-1">
                        <div class="flex justify-between text-[9px] text-muted-foreground font-semibold">
                            <span>Mục tiêu ngành: &le; 5%</span>
                            <span>{{ dashboard.waste_ratio }}% / 5%</span>
                        </div>
                        <div class="h-1 w-full bg-muted rounded-full overflow-hidden">
                            <div 
                                class="h-full rounded-full transition-all duration-500"
                                :class="[
                                    dashboard.benchmark_status === 'excellent' ? 'bg-emerald-500' :
                                    dashboard.benchmark_status === 'normal' ? 'bg-amber-500' : 'bg-rose-500'
                                ]"
                                :style="{ width: `${Math.min((dashboard.waste_ratio / 5) * 100, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Doanh thu -->
            <Card class="bg-card text-card-foreground border border-border shadow-xs hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 group">
                <CardContent class="p-5 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Doanh thu ({{ days }}D)</span>
                            <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-500 group-hover:scale-110 transition-transform">
                                <Banknote class="size-4" />
                            </div>
                        </div>
                        <p class="text-2xl font-black text-foreground tracking-tight">
                            {{ dashboard.total_revenue.toLocaleString() }}đ
                        </p>
                    </div>
                    <div class="mt-4 flex items-center gap-1.5 text-[10px] text-muted-foreground font-semibold">
                        <Info class="size-3.5 text-emerald-400" />
                        <span>Chuẩn F&B: Lãng phí 5-10% DT</span>
                    </div>
                </CardContent>
            </Card>

            <!-- 4. Sắp hết hạn -->
            <Card class="bg-card text-card-foreground border border-border shadow-xs hover:shadow-md transition-all duration-300 hover:-translate-y-0.5 group">
                <CardContent class="p-5 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Nguyên liệu cận date</span>
                            <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-500 group-hover:scale-110 transition-transform">
                                <Clock class="size-4" />
                            </div>
                        </div>
                        <p class="text-2xl font-black text-amber-600 dark:text-amber-400 tracking-tight">
                            {{ expiring.length }} nguyên liệu
                        </p>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-[10px] text-muted-foreground font-semibold">
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                        </span>
                        <span>Cần kiểm kê trong 3 ngày tới</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Phân loại nguyên nhân (SVG Doughnut Chart) -->
            <Card class="bg-card text-card-foreground border border-border shadow-sm flex flex-col justify-between">
                <div class="p-5 border-b border-border/60">
                    <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                        <Activity class="size-4 text-orange-500" />
                        Phân loại nguyên nhân
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Cơ cấu chi phí hao hụt theo danh mục phân loại.</p>
                </div>
                <CardContent class="p-5 flex-1 flex flex-col justify-center">
                    <div v-if="doughnutPaths.length" class="flex flex-col gap-6">
                        <!-- Doughnut Chart Container -->
                        <div class="relative w-44 h-44 mx-auto flex items-center justify-center">
                            <svg viewBox="0 0 200 200" class="w-full h-full transform -rotate-90">
                                <g v-for="slice in doughnutPaths" :key="slice.category">
                                    <path 
                                        :d="slice.path" 
                                        :fill="slice.color" 
                                        class="stroke-card stroke-2 transition-all duration-300 cursor-pointer"
                                        :class="{ 'opacity-100 filter drop-shadow-[0_0_12px_rgba(0,0,0,0.3)] scale-[1.02]': hoveredCategoryIdx === slice.index, 'opacity-85': hoveredCategoryIdx !== null && hoveredCategoryIdx !== slice.index }"
                                        @mouseenter="hoveredCategoryIdx = slice.index"
                                        @mouseleave="hoveredCategoryIdx = null"
                                    />
                                </g>
                                <!-- Central mask hole -->
                                <circle cx="100" cy="100" r="62" class="fill-card" />
                            </svg>
                            <!-- Center Data display -->
                            <div class="absolute flex flex-col items-center justify-center text-center px-4 pointer-events-none select-none max-w-full">
                                <span class="text-[9px] font-bold tracking-wider uppercase text-muted-foreground truncate max-w-[110px]">{{ centerLabel }}</span>
                                <span class="text-sm font-extrabold text-foreground tracking-tight mt-0.5 truncate max-w-[120px]">{{ centerValue }}</span>
                                <span class="text-[8px] text-muted-foreground mt-0.5 font-semibold truncate max-w-[110px]">{{ centerSub }}</span>
                            </div>
                        </div>

                        <!-- Custom Legend Rows -->
                        <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                            <div v-for="slice in doughnutPaths" :key="slice.category"
                                class="flex items-center justify-between p-2 rounded-xl border border-border/40 bg-muted/5 hover:bg-muted/15 transition-all duration-200 cursor-pointer"
                                :class="{ 'border-primary/20 bg-muted/20': hoveredCategoryIdx === slice.index }"
                                @mouseenter="hoveredCategoryIdx = slice.index"
                                @mouseleave="hoveredCategoryIdx = null"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ backgroundColor: slice.color }"></span>
                                    <span class="text-xs font-semibold text-foreground truncate">{{ slice.label }}</span>
                                    <span class="text-[10px] text-muted-foreground">({{ slice.count }})</span>
                                </div>
                                <div class="flex flex-col items-end shrink-0 ml-2">
                                    <span class="text-xs font-bold text-foreground">{{ slice.total_cost.toLocaleString() }}đ</span>
                                    <span class="text-[9px] text-muted-foreground font-bold font-mono">{{ slice.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-10 px-4 text-center border border-dashed border-border/60 rounded-xl bg-muted/5 w-full">
                        <div class="h-10 w-10 rounded-full bg-muted flex items-center justify-center mb-3 text-muted-foreground/60">
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">Không có dữ liệu phân loại</p>
                        <p class="text-[10px] text-muted-foreground mt-1 max-w-[200px]">Hệ thống chưa ghi nhận bất kỳ dữ liệu hao hụt nào.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Xu hướng hao hụt (SVG Area/Line Chart) -->
            <Card class="bg-card text-card-foreground border border-border shadow-sm flex flex-col justify-between">
                <div class="p-5 border-b border-border/60">
                    <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                        <TrendingDown class="size-4 text-rose-500" />
                        Xu hướng hao hụt
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Biểu đồ tổng hợp 6 tháng gần nhất.</p>
                </div>
                <CardContent class="p-5 flex-1 flex flex-col justify-center">
                    <div v-if="trend.length" class="relative w-full h-44">
                        <svg viewBox="0 0 500 160" class="w-full h-full overflow-visible select-none" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="trendGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.25" />
                                    <stop offset="100%" stop-color="#f43f5e" stop-opacity="0" />
                                </linearGradient>
                            </defs>

                            <!-- Horizontal Grid Lines -->
                            <g opacity="0.06" class="stroke-foreground" stroke-width="1" stroke-dasharray="3 3">
                                <line x1="60" y1="20" x2="480" y2="20" />
                                <line x1="60" y1="56.6" x2="480" y2="56.6" />
                                <line x1="60" y1="93.3" x2="480" y2="93.3" />
                                <line x1="60" y1="130" x2="480" y2="130" />
                            </g>

                            <!-- Axes lines -->
                            <line x1="60" y1="130" x2="480" y2="130" class="stroke-border" stroke-width="1" />
                            <line x1="60" y1="20" x2="60" y2="130" class="stroke-border" stroke-width="1" />

                            <!-- Y Axis ticks -->
                            <text x="52" y="24" text-anchor="end" class="fill-muted-foreground text-[8px] font-semibold font-mono">{{ maxTrend.toLocaleString() }}đ</text>
                            <text x="52" y="79" text-anchor="end" class="fill-muted-foreground text-[8px] font-semibold font-mono">{{ Math.round(maxTrend / 2).toLocaleString() }}đ</text>
                            <text x="52" y="134" text-anchor="end" class="fill-muted-foreground text-[8px] font-semibold font-mono">0đ</text>

                            <!-- Area & Line Paths -->
                            <path :d="trendAreaPath" fill="url(#trendGrad)" />
                            <path :d="trendLinePath" fill="none" stroke="#f43f5e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

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
                                    :class="{ 'r-5 stroke-2 brightness-110': hoveredTrendIdx === pt.index }"
                                />
                                <circle 
                                    v-if="hoveredTrendIdx === pt.index" 
                                    :cx="pt.x" 
                                    :cy="pt.y" 
                                    r="8" 
                                    fill="#f43f5e" 
                                    opacity="0.3" 
                                    class="animate-ping pointer-events-none" 
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
                                class="fill-muted-foreground text-[8px] font-bold font-mono transition-colors"
                                :class="{ 'fill-foreground font-extrabold': hoveredTrendIdx === pt.index }"
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
                            class="absolute z-20 pointer-events-none rounded-xl border border-rose-500/20 bg-background/95 backdrop-blur-md p-3 shadow-xl text-xs font-semibold flex flex-col gap-1 transition-all duration-75 text-foreground"
                            :style="{
                                left: `${(hoveredTrendPoint.x) / 500 * 100}%`,
                                top: `${(hoveredTrendPoint.y - 50) / 160 * 100}%`,
                                transform: 'translateX(-50%)',
                            }"
                        >
                            <div class="flex items-center gap-1.5 border-b border-border/50 pb-1 mb-1">
                                <Calendar class="size-3 text-muted-foreground" />
                                <span class="text-[9px] uppercase tracking-wider text-muted-foreground font-mono font-bold">{{ hoveredTrendPoint.raw.month }}</span>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span class="text-[9px] text-muted-foreground leading-none">Tổng hao hụt</span>
                                <span class="font-extrabold text-rose-500 text-sm leading-none">
                                    {{ hoveredTrendPoint.raw.total_cost.toLocaleString() }}đ
                                </span>
                            </div>
                            <div class="flex items-center gap-1 mt-1 text-[10px] text-muted-foreground font-medium">
                                <Activity class="size-3 text-rose-400" />
                                <span>{{ hoveredTrendPoint.raw.count }} lần ghi nhận</span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-10 px-4 text-center border border-dashed border-border/60 rounded-xl bg-muted/5 w-full">
                        <div class="h-10 w-10 rounded-full bg-muted flex items-center justify-center mb-3 text-muted-foreground/60">
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">Chưa có dữ liệu xu hướng</p>
                        <p class="text-[10px] text-muted-foreground mt-1 max-w-[200px]">Không tìm thấy số liệu xu hướng cho 6 tháng vừa qua.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Top nguyên liệu hao hụt (Leaderboard) -->
            <Card class="bg-card text-card-foreground border border-border shadow-sm flex flex-col justify-between">
                <div class="p-5 border-b border-border/60">
                    <h3 class="text-base font-bold text-foreground flex items-center gap-2">
                        <Award class="size-4 text-amber-500 animate-pulse" />
                        Top nguyên liệu hao hụt
                    </h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Nguyên liệu lãng phí nhiều nhất.</p>
                </div>
                <CardContent class="p-5 flex-1 flex flex-col justify-center">
                    <div v-if="dashboard.top_ingredients.length" class="space-y-3">
                        <div v-for="(item, idx) in dashboard.top_ingredients.slice(0, 6)" :key="item.ingredient_id"
                            class="group relative flex flex-col gap-1.5 p-2.5 rounded-xl border border-border/40 bg-muted/5 hover:bg-muted/15 transition-all duration-200"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 min-w-0">
                                    <!-- Rank Index -->
                                    <span :class="[
                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-lg text-[10px] font-extrabold border',
                                        idx === 0 ? 'bg-amber-500/10 text-amber-500 border-amber-500/20 shadow-xs' :
                                        idx === 1 ? 'bg-slate-300/10 text-slate-400 border-slate-300/20' :
                                        idx === 2 ? 'bg-amber-700/10 text-amber-700 border-amber-700/20 dark:text-amber-600' :
                                        'bg-muted text-muted-foreground border-border'
                                    ]">
                                        <span v-if="idx < 3" class="relative flex items-center justify-center">🏆</span>
                                        <span v-else>{{ idx + 1 }}</span>
                                    </span>
                                    <span class="font-bold text-foreground truncate">{{ item.name }}</span>
                                </div>
                                <span class="font-black text-rose-500 dark:text-rose-400 shrink-0 ml-2">
                                    {{ item.total_cost.toLocaleString() }}đ
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                                <span>Lượng hủy: <span class="font-semibold text-foreground/85">{{ item.total_qty }} đơn vị</span></span>
                                <span>{{ item.waste_count }} lần ghi nhận</span>
                            </div>

                            <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden mt-0.5">
                                <div 
                                    class="h-full rounded-full transition-all duration-500 ease-out"
                                    :class="[
                                        idx === 0 ? 'bg-gradient-to-r from-rose-500 to-orange-500 shadow-[0_0_8px_rgba(244,63,94,0.3)]' :
                                        idx === 1 ? 'bg-rose-500/80' : 
                                        'bg-rose-500/60'
                                    ]"
                                    :style="{ width: `${(item.total_cost / maxIngredientCost) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-10 px-4 text-center border border-dashed border-border/60 rounded-xl bg-muted/5 w-full">
                        <div class="h-10 w-10 rounded-full bg-muted flex items-center justify-center mb-3 text-muted-foreground/60">
                            <Inbox class="size-5" />
                        </div>
                        <p class="text-xs font-bold text-foreground/80">Không có nguyên liệu hao hụt</p>
                        <p class="text-[10px] text-muted-foreground mt-1 max-w-[200px]">Hệ thống chưa ghi nhận bất kỳ nguyên liệu bị hủy nào.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- AI Suggestions -->
        <Card class="bg-card text-card-foreground border border-border shadow-sm">
            <div class="p-5 border-b border-border/60 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 ring-4 ring-amber-500/5 shrink-0">
                    <Sparkles class="size-5 animate-pulse" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">AI Gợi ý giảm hao hụt</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Khuyến nghị tự động từ hệ thống dựa trên phân tích dữ liệu kho.</p>
                </div>
            </div>
            <CardContent class="p-5">
                <div v-if="suggestions.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="(s, idx) in suggestions" :key="idx"
                        class="group relative overflow-hidden rounded-xl border border-border bg-muted/10 p-5 transition-all duration-300 hover:bg-muted/15 hover:border-amber-500/30 hover:shadow-[0_0_20px_-3px_rgba(245,158,11,0.08)] hover:-translate-y-0.5"
                    >
                        <div class="absolute -right-6 -bottom-6 h-20 w-20 rounded-full bg-amber-500/5 blur-xl group-hover:bg-amber-500/10 transition-colors duration-300"></div>
                        
                        <div class="flex gap-4 items-start relative z-10">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 group-hover:scale-110 transition-transform duration-300">
                                <Sparkles v-if="s.type === 'ai' || idx === 0" class="size-5" />
                                <Lightbulb v-else class="size-5" />
                            </div>
                            <div class="space-y-1.5 min-w-0">
                                <p class="font-bold text-sm text-foreground flex items-center gap-1.5 leading-snug">
                                    {{ s.title }}
                                    <Badge variant="outline" class="text-[9px] px-1.5 py-0 border-amber-500/20 bg-amber-500/5 text-amber-500 font-mono font-bold">
                                        {{ s.type === 'ai' ? 'AI SUGGESTION' : 'GỢI Ý' }}
                                    </Badge>
                                </p>
                                <p class="text-xs text-muted-foreground leading-relaxed">{{ s.description }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground/60 w-full">
                    <Lightbulb class="size-6 text-muted-foreground/30 mb-2" />
                    <p class="text-xs font-semibold">Chưa có gợi ý nào khả dụng.</p>
                </div>
            </CardContent>
        </Card>

        <!-- Expiring Soon -->
        <Card v-if="expiring.length" class="bg-card text-card-foreground border border-border shadow-sm">
            <div class="p-5 border-b border-border/60 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500 ring-4 ring-rose-500/5 shrink-0">
                    <Clock class="size-5 animate-pulse" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">Nguyên liệu sắp hết hạn (Trong 3 ngày tới)</h3>
                    <p class="text-xs text-muted-foreground mt-0.5">Hãy ưu tiên sử dụng hoặc ghi nhận hao hụt để tránh lãng phí chi phí.</p>
                </div>
            </div>
            <CardContent class="p-5">
                <div class="border border-border/80 overflow-hidden rounded-xl bg-muted/5 shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="border-b border-border bg-muted/30 text-muted-foreground font-semibold">
                                    <th class="px-5 py-3.5">Nguyên liệu</th>
                                    <th class="px-5 py-3.5">Lô hàng</th>
                                    <th class="px-5 py-3.5 text-right">Số lượng còn lại</th>
                                    <th class="px-5 py-3.5">Hạn sử dụng</th>
                                    <th class="px-5 py-3.5 text-right">Khẩn cấp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="item in expiring" :key="item.id" class="hover:bg-muted/15 transition-all duration-150">
                                    <td class="px-5 py-3.5 font-bold text-foreground">
                                        <div class="flex items-center gap-2">
                                            <span :class="[
                                                'h-2.5 w-2.5 rounded-full shrink-0',
                                                item.days_left <= 1 ? 'bg-rose-500 animate-ping' : 'bg-amber-500'
                                            ]"></span>
                                            <span>{{ item.ingredient_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-muted-foreground font-mono font-medium">{{ item.batch_number || '—' }}</td>
                                    <td class="px-5 py-3.5 text-right font-bold text-foreground">{{ item.quantity_remaining }}</td>
                                    <td class="px-5 py-3.5 text-muted-foreground">
                                        <div class="flex items-center gap-1.5">
                                            <Clock class="size-3 text-muted-foreground/60" />
                                            <span>{{ item.expiry_date }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <Badge 
                                            :variant="item.days_left <= 1 ? 'destructive' : 'secondary'" 
                                            class="text-[10px] px-2.5 py-0.5 font-semibold font-mono border-0 shrink-0"
                                            :class="item.days_left <= 1 ? 'shadow-[0_0_8px_rgba(239,68,68,0.2)] animate-pulse' : ''"
                                        >
                                            {{ item.days_left <= 0 ? 'Hết hạn hôm nay' : item.days_left === 1 ? 'Còn 1 ngày' : `Còn ${item.days_left} ngày` }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

