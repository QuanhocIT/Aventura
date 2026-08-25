<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowDownRight,
    ArrowUpRight,
    Award,
    ChevronDown,
    ChevronUp,
    Download,
    Flame,
    Layers,
    Loader2,
    Minus,
    Search,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Trophy,
    Utensils,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    analytics as analyticsRoute,
    dish as dishRoute,
    exportMethod as exportRoute,
} from '@/routes/best-sellers';

defineOptions({ layout: AppLayout });

// ─── Kiểu dữ liệu ────────────────────────────────────────────────────────────

interface RankingRow {
    product_id: number;
    name: string;
    category_id: number | null;
    category_name: string;
    price: number;
    cost_price: number;
    is_retired: boolean;
    qty: number;
    revenue: number;
    cogs: number;
    gross_profit: number;
    margin_percent: number;
    order_count: number;
    attach_rate: number;
    avg_qty_per_order: number;
    metric_value: number;
    previous_qty: number;
    previous_revenue: number;
    previous_metric_value: number;
    change_value: number;
    change_percent: number;
    trend: 'up' | 'down' | 'stable';
    is_new: boolean;
    is_dropped: boolean;
    rank: number;
    share_percent: number;
    cumulative_percent: number;
    abc_class: 'A' | 'B' | 'C';
    previous_rank: number | null;
    rank_delta: number | null;
}

interface BreakdownRow {
    key: string;
    label: string;
    qty: number;
    revenue: number;
    share_percent: number;
    orders?: number;
}

interface BranchRow {
    branch_id: number | null;
    branch_name: string;
    qty: number;
    revenue: number;
    share_percent: number;
    top_dish: string | null;
    top_dish_qty: number;
}

interface CategoryRow {
    category: string;
    dishes: number;
    qty: number;
    revenue: number;
    gross_profit: number;
    revenue_share: number;
    top_dish: string | null;
    top_dish_qty: number;
}

interface SeriesPoint {
    date: string;
    qty: number;
    revenue: number;
}

interface Analytics {
    period: {
        from: string;
        to: string;
        days: number;
        previous_from: string;
        previous_to: string;
    };
    filters: {
        metric: string;
        metric_label: string;
        category_id: number | null;
        limit: number;
        branch_id: number | null;
    };
    summary: {
        metric: string;
        metric_label: string;
        total_qty: number;
        total_revenue: number;
        total_cogs: number;
        total_gross_profit: number;
        gross_margin_percent: number;
        orders: number;
        avg_items_per_order: number;
        dishes_sold: number;
        catalog_size: number;
        catalog_coverage: number;
        never_sold: number;
        top_dish: {
            product_id: number;
            name: string;
            qty: number;
            revenue: number;
            gross_profit: number;
            share_percent: number;
        } | null;
        top1_share: number;
        top3_share: number;
        top5_share: number;
        top10_share: number;
        hhi: number;
        concentration: { level: string; label: string; hint: string };
        previous: {
            total_qty: number;
            total_revenue: number;
            total_gross_profit: number;
            orders: number;
            dishes_sold: number;
        };
        change: {
            qty_percent: number;
            revenue_percent: number;
            profit_percent: number;
            orders_percent: number;
        };
    };
    ranking: RankingRow[];
    pareto: {
        classes: {
            class: 'A' | 'B' | 'C';
            label: string;
            hint: string;
            dishes: number;
            qty: number;
            revenue: number;
            gross_profit: number;
            share_percent: number;
            dish_share_percent: number;
        }[];
        dishes_for_80: number;
        dishes_for_80_share: number;
        dishes_sold: number;
    };
    movers: {
        rising: RankingRow[];
        falling: RankingRow[];
        newcomers: RankingRow[];
        dropouts: RankingRow[];
    };
    daily_series: {
        dates: string[];
        total: SeriesPoint[];
        products: { product_id: number; name: string; points: SeriesPoint[] }[];
    };
    categories: CategoryRow[];
    dayparts: BreakdownRow[];
    weekdays: BreakdownRow[];
    channels: BreakdownRow[];
    branches: BranchRow[];
}

interface DishDetail {
    product: {
        id: number;
        name: string;
        price: number;
        cost_price: number;
        image_url: string | null;
        is_active: boolean;
        is_retired: boolean;
        category_name: string;
    };
    period: { from: string; to: string; days: number };
    summary: {
        qty: number;
        revenue: number;
        gross_profit: number;
        margin_percent: number;
        order_count: number;
        attach_rate: number;
        avg_qty_per_order: number;
        daily_avg_qty: number;
        previous_qty: number;
        previous_revenue: number;
        qty_change_percent: number;
        revenue_change_percent: number;
    };
    daily_series: SeriesPoint[];
    dayparts: BreakdownRow[];
    weekdays: BreakdownRow[];
    channels: BreakdownRow[];
    branches: BranchRow[];
    paired_with: {
        product_id: number;
        name: string;
        qty: number;
        orders: number;
        confidence: number;
    }[];
}

const props = defineProps<{
    analytics: Analytics;
    filters: {
        from: string;
        to: string;
        preset: string;
        metric: string;
        category_id: number | null;
        limit: number;
    };
    categories: { id: number; name: string }[];
    branchContext: { scope: string; active_branch_id: number | null };
}>();

// ─── Bộ lọc ──────────────────────────────────────────────────────────────────

const data = ref<Analytics>(props.analytics);
const loading = ref(false);

const preset = ref(props.filters.preset);
const dateFrom = ref(props.filters.from);
const dateTo = ref(props.filters.to);
const metric = ref(props.filters.metric);
const categoryId = ref(
    props.filters.category_id ? String(props.filters.category_id) : 'all',
);
const topLimit = ref(String(props.filters.limit));

const presetOptions = [
    { value: '7', label: '7 ngày qua' },
    { value: '30', label: '30 ngày qua' },
    { value: '90', label: '90 ngày qua' },
    { value: '365', label: '12 tháng qua' },
    { value: 'custom', label: 'Tự chọn ngày' },
];

const metricOptions = [
    { value: 'quantity', label: 'Số lượng bán' },
    { value: 'revenue', label: 'Doanh thu' },
    { value: 'profit', label: 'Lợi nhuận gộp' },
];

function currentParams() {
    return {
        preset: preset.value,
        from: preset.value === 'custom' ? dateFrom.value : undefined,
        to: preset.value === 'custom' ? dateTo.value : undefined,
        metric: metric.value,
        category_id: categoryId.value === 'all' ? undefined : categoryId.value,
        limit: topLimit.value,
    };
}

async function reload() {
    if (preset.value === 'custom' && (!dateFrom.value || !dateTo.value)) {
        return;
    }

    loading.value = true;

    try {
        const response = await axios.get(analyticsRoute.url(), {
            params: currentParams(),
        });
        data.value = response.data as Analytics;
    } catch (error: unknown) {
        const message =
            (error as { response?: { data?: { error?: string } } })?.response
                ?.data?.error ??
            'Không tải được số liệu phân tích. Vui lòng thử lại.';
        toast.error(message);
    } finally {
        loading.value = false;
    }
}

watch([preset, metric, categoryId, topLimit], reload);
watch([dateFrom, dateTo], () => {
    if (preset.value === 'custom') {
        reload();
    }
});

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(currentParams()).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.append(key, String(value));
        }
    });
    window.location.href = `${exportRoute.url()}?${params.toString()}`;
}

// ─── Định dạng ───────────────────────────────────────────────────────────────

const numberFormat = new Intl.NumberFormat('vi-VN');

const formatNumber = (value: number) => numberFormat.format(Number(value ?? 0));
const formatMoney = (value: number) =>
    `${numberFormat.format(Math.round(Number(value ?? 0)))}đ`;
const formatPercent = (value: number) => `${Number(value ?? 0).toFixed(1)}%`;

function formatSigned(value: number) {
    const rounded = Number(value ?? 0);

    return `${rounded > 0 ? '+' : ''}${rounded.toFixed(1)}%`;
}

function formatDay(date: string) {
    const [, month, day] = date.split('-');

    return `${day}/${month}`;
}

const metricLabel = computed(() => data.value.summary.metric_label);

function metricValueOf(row: { metric_value: number }) {
    return metric.value === 'quantity'
        ? formatNumber(row.metric_value)
        : formatMoney(row.metric_value);
}

function seriesValueOf(point: SeriesPoint) {
    return metric.value === 'quantity' ? point.qty : point.revenue;
}

const changeClass = (value: number) =>
    value > 0
        ? 'text-emerald-600 dark:text-emerald-400'
        : value < 0
          ? 'text-rose-600 dark:text-rose-400'
          : 'text-muted-foreground';

const abcClass = (klass: string) =>
    klass === 'A'
        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : klass === 'B'
          ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
          : 'bg-slate-500/10 text-slate-600 dark:text-slate-300';

const concentrationClass = computed(() => {
    const level = data.value.summary.concentration.level;

    return level === 'high'
        ? 'text-rose-600 dark:text-rose-400'
        : level === 'medium'
          ? 'text-amber-600 dark:text-amber-400'
          : 'text-emerald-600 dark:text-emerald-400';
});

// ─── Biểu đồ xu hướng ────────────────────────────────────────────────────────

const CHART_WIDTH = 900;
const CHART_HEIGHT = 240;
const CHART_PAD = { top: 16, right: 16, bottom: 26, left: 56 };

const LINE_COLORS = [
    '#0ea5e9',
    '#f97316',
    '#8b5cf6',
    '#10b981',
    '#ef4444',
    '#eab308',
    '#ec4899',
    '#14b8a6',
];

/** Món đang được ẩn khỏi biểu đồ (bấm vào chú giải để bật/tắt). */
const hiddenSeries = ref<Set<number>>(new Set());

function toggleSeries(productId: number) {
    const next = new Set(hiddenSeries.value);

    if (next.has(productId)) {
        next.delete(productId);
    } else {
        next.add(productId);
    }

    hiddenSeries.value = next;
}

const chartSeries = computed(() =>
    data.value.daily_series.products.slice(0, 8).map((product, index) => ({
        product_id: product.product_id,
        name: product.name,
        color: LINE_COLORS[index % LINE_COLORS.length],
        points: product.points,
        visible: !hiddenSeries.value.has(product.product_id),
    })),
);

const chartMax = computed(() => {
    const values = chartSeries.value
        .filter((series) => series.visible)
        .flatMap((series) => series.points.map(seriesValueOf));

    return Math.max(...values, 1);
});

const chartDates = computed(() => data.value.daily_series.dates);

function chartX(index: number) {
    const count = chartDates.value.length;
    const usable = CHART_WIDTH - CHART_PAD.left - CHART_PAD.right;

    if (count <= 1) {
        return CHART_PAD.left + usable / 2;
    }

    return CHART_PAD.left + (index / (count - 1)) * usable;
}

function chartY(value: number) {
    const usable = CHART_HEIGHT - CHART_PAD.top - CHART_PAD.bottom;

    return CHART_PAD.top + usable - (value / chartMax.value) * usable;
}

function linePath(points: SeriesPoint[]) {
    return points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${chartX(index).toFixed(1)},${chartY(seriesValueOf(point)).toFixed(1)}`,
        )
        .join(' ');
}

/** Nhãn trục X: chỉ hiện tối đa 8 mốc để không chồng chữ. */
const axisTicks = computed(() => {
    const dates = chartDates.value;
    const step = Math.max(1, Math.ceil(dates.length / 8));

    return dates
        .map((date, index) => ({ date, index }))
        .filter((tick) => tick.index % step === 0);
});

const gridLines = computed(() =>
    [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
        ratio,
        y: chartY(chartMax.value * ratio),
        label:
            metric.value === 'quantity'
                ? formatNumber(Math.round(chartMax.value * ratio))
                : formatMoney(chartMax.value * ratio),
    })),
);

// ─── Bảng xếp hạng ───────────────────────────────────────────────────────────

const search = ref('');
const abcFilter = ref('all');
const showAllRows = ref(false);

const filteredRanking = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return data.value.ranking.filter((row) => {
        if (row.metric_value <= 0 && !row.is_dropped) {
            return false;
        }

        if (abcFilter.value !== 'all' && row.abc_class !== abcFilter.value) {
            return false;
        }

        if (!keyword) {
            return true;
        }

        return (
            row.name.toLowerCase().includes(keyword) ||
            row.category_name.toLowerCase().includes(keyword)
        );
    });
});

const visibleRanking = computed(() =>
    showAllRows.value
        ? filteredRanking.value
        : filteredRanking.value.slice(0, 20),
);

// ─── Drill-down một món ──────────────────────────────────────────────────────

const detail = ref<DishDetail | null>(null);
const detailLoading = ref(false);
const detailOpen = ref(false);

async function openDish(productId: number) {
    detailOpen.value = true;
    detailLoading.value = true;
    detail.value = null;

    try {
        const response = await axios.get(dishRoute.url(productId), {
            params: currentParams(),
        });
        detail.value = response.data as DishDetail;
    } catch (error: unknown) {
        const message =
            (error as { response?: { data?: { error?: string } } })?.response
                ?.data?.error ?? 'Không tải được chi tiết món.';
        toast.error(message);
        detailOpen.value = false;
    } finally {
        detailLoading.value = false;
    }
}

/** Sparkline cho hộp thoại chi tiết món. */
function sparklinePath(points: SeriesPoint[]) {
    if (points.length === 0) {
        return '';
    }

    const values = points.map(seriesValueOf);
    const max = Math.max(...values, 1);

    return points
        .map((point, index) => {
            const x =
                points.length <= 1 ? 0 : (index / (points.length - 1)) * 200;
            const y = 44 - (seriesValueOf(point) / max) * 40;

            return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
}

const hasData = computed(() => data.value.summary.dishes_sold > 0);

/** Phạm vi dữ liệu do bộ chọn chi nhánh toàn cục quyết định. */
const scopeLabel = computed(() =>
    props.branchContext.active_branch_id ? 'Chi nhánh đang chọn' : 'Toàn chuỗi',
);
</script>

<template>
    <Head title="Phân tích món bán chạy" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-500/10"
                >
                    <Flame
                        class="size-6 text-orange-600 dark:text-orange-400"
                    />
                </div>
                <div>
                    <h1
                        class="flex items-center gap-2 text-xl font-bold tracking-tight"
                    >
                        Phân tích món bán chạy
                        <Badge variant="secondary" class="text-[10px]">
                            {{ scopeLabel }}
                        </Badge>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Xếp hạng đầy đủ thực đơn, phân nhóm ABC theo Pareto và
                        truy nguyên món bán chạy nhờ đâu.
                    </p>
                </div>
            </div>

            <Button variant="outline" size="sm" @click="exportCsv">
                <Download class="mr-2 size-4" />
                Xuất CSV
            </Button>
        </div>

        <!-- ── Bộ lọc ──────────────────────────────────────────────────────── -->
        <Card>
            <CardContent class="flex flex-wrap items-end gap-3 p-4">
                <div class="w-full sm:w-40">
                    <Label class="mb-1.5 block text-xs">Kỳ phân tích</Label>
                    <Select v-model="preset">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in presetOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <template v-if="preset === 'custom'">
                    <div class="w-full sm:w-40">
                        <Label class="mb-1.5 block text-xs">Từ ngày</Label>
                        <Input
                            v-model="dateFrom"
                            type="date"
                            class="h-9 text-xs"
                        />
                    </div>
                    <div class="w-full sm:w-40">
                        <Label class="mb-1.5 block text-xs">Đến ngày</Label>
                        <Input
                            v-model="dateTo"
                            type="date"
                            class="h-9 text-xs"
                        />
                    </div>
                </template>

                <div class="w-full sm:w-40">
                    <Label class="mb-1.5 block text-xs">Xếp hạng theo</Label>
                    <Select v-model="metric">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in metricOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full sm:w-44">
                    <Label class="mb-1.5 block text-xs">Danh mục</Label>
                    <Select v-model="categoryId">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue placeholder="Tất cả danh mục" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả danh mục</SelectItem>
                            <SelectItem
                                v-for="category in categories"
                                :key="category.id"
                                :value="String(category.id)"
                            >
                                {{ category.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="w-full sm:w-36">
                    <Label class="mb-1.5 block text-xs"
                        >Số món vẽ biểu đồ</Label
                    >
                    <Select v-model="topLimit">
                        <SelectTrigger class="h-9 text-xs">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="5">Top 5</SelectItem>
                            <SelectItem value="10">Top 10</SelectItem>
                            <SelectItem value="15">Top 15</SelectItem>
                            <SelectItem value="20">Top 20</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div
                    class="ml-auto flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <Loader2 v-if="loading" class="size-4 animate-spin" />
                    <span>
                        {{ data.period.from }} → {{ data.period.to }} ({{
                            data.period.days
                        }}
                        ngày)
                    </span>
                </div>
            </CardContent>
        </Card>

        <div v-if="!hasData" class="py-16 text-center">
            <Utensils class="mx-auto mb-3 size-10 text-muted-foreground/50" />
            <p class="text-sm text-muted-foreground">
                Chưa có đơn hoàn tất nào trong kỳ này. Hãy chọn kỳ phân tích
                khác.
            </p>
        </div>

        <template v-else>
            <!-- ── KPI ─────────────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Món bán chạy nhất</span
                            >
                            <Trophy class="size-4 text-amber-500" />
                        </div>
                        <p class="truncate text-lg font-bold">
                            {{ data.summary.top_dish?.name ?? '—' }}
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            {{ formatNumber(data.summary.top_dish?.qty ?? 0) }}
                            phần · chiếm
                            {{ formatPercent(data.summary.top1_share) }}
                            {{ metricLabel }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Tổng phần bán</span
                            >
                            <Utensils class="size-4 text-sky-500" />
                        </div>
                        <p class="text-xl font-bold">
                            {{ formatNumber(data.summary.total_qty) }}
                        </p>
                        <p
                            class="mt-1.5 text-[10px]"
                            :class="
                                changeClass(data.summary.change.qty_percent)
                            "
                        >
                            {{ formatSigned(data.summary.change.qty_percent) }}
                            so với kỳ trước
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Doanh thu món</span
                            >
                            <TrendingUp class="size-4 text-emerald-500" />
                        </div>
                        <p class="text-xl font-bold">
                            {{ formatMoney(data.summary.total_revenue) }}
                        </p>
                        <p
                            class="mt-1.5 text-[10px]"
                            :class="
                                changeClass(data.summary.change.revenue_percent)
                            "
                        >
                            {{
                                formatSigned(
                                    data.summary.change.revenue_percent,
                                )
                            }}
                            so với kỳ trước
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Lợi nhuận gộp</span
                            >
                            <Sparkles class="size-4 text-violet-500" />
                        </div>
                        <p class="text-xl font-bold">
                            {{ formatMoney(data.summary.total_gross_profit) }}
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Biên
                            {{
                                formatPercent(data.summary.gross_margin_percent)
                            }}
                            · COGS theo giá vốn hiện hành
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Mức tập trung</span
                            >
                            <Layers class="size-4 text-orange-500" />
                        </div>
                        <p
                            class="text-lg font-bold"
                            :class="concentrationClass"
                        >
                            {{ data.summary.concentration.label }}
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Top 5 chiếm
                            {{ formatPercent(data.summary.top5_share) }} · HHI
                            {{ formatNumber(data.summary.hhi) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Pareto / ABC ────────────────────────────────────────────── -->
            <Card>
                <CardContent class="p-4">
                    <div
                        class="mb-4 flex flex-col gap-1 border-b border-border pb-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-sm font-semibold"
                            >
                                <Award class="size-4 text-emerald-500" />
                                Phân nhóm ABC theo Pareto
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ data.pareto.dishes_for_80 }}/{{
                                    data.pareto.dishes_sold
                                }}
                                món ({{
                                    formatPercent(
                                        data.pareto.dishes_for_80_share,
                                    )
                                }}
                                thực đơn đang bán) tạo ra ~80%
                                {{ metricLabel }}.
                            </p>
                        </div>
                        <p class="max-w-md text-xs text-muted-foreground">
                            {{ data.summary.concentration.hint }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div
                            v-for="group in data.pareto.classes"
                            :key="group.class"
                            class="rounded-xl border border-border p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <span
                                    class="rounded-md px-2 py-0.5 text-xs font-semibold"
                                    :class="abcClass(group.class)"
                                >
                                    {{ group.label }}
                                </span>
                                <span class="text-sm font-bold">
                                    {{ group.dishes }} món
                                </span>
                            </div>
                            <div
                                class="mb-2 h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${Math.min(group.share_percent, 100)}%`,
                                    }"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Chiếm
                                <span class="font-semibold text-foreground">{{
                                    formatPercent(group.share_percent)
                                }}</span>
                                {{ metricLabel }} ·
                                {{ formatMoney(group.revenue) }}
                            </p>
                            <p class="mt-1.5 text-[11px] text-muted-foreground">
                                {{ group.hint }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Biểu đồ xu hướng theo ngày ──────────────────────────────── -->
            <Card>
                <CardContent class="p-4">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold">
                                Xu hướng theo ngày — top
                                {{ chartSeries.length }} món
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Bấm vào tên món trong chú giải để ẩn/hiện đường.
                            </p>
                        </div>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <svg
                            :viewBox="`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`"
                            class="h-60 w-full min-w-[640px]"
                            role="img"
                            aria-label="Biểu đồ xu hướng bán theo ngày"
                        >
                            <g>
                                <line
                                    v-for="line in gridLines"
                                    :key="line.ratio"
                                    :x1="CHART_PAD.left"
                                    :x2="CHART_WIDTH - CHART_PAD.right"
                                    :y1="line.y"
                                    :y2="line.y"
                                    class="stroke-border"
                                    stroke-width="1"
                                />
                                <text
                                    v-for="line in gridLines"
                                    :key="`label-${line.ratio}`"
                                    :x="CHART_PAD.left - 8"
                                    :y="line.y + 4"
                                    text-anchor="end"
                                    class="fill-muted-foreground text-[10px]"
                                >
                                    {{ line.label }}
                                </text>
                            </g>

                            <g>
                                <text
                                    v-for="tick in axisTicks"
                                    :key="tick.date"
                                    :x="chartX(tick.index)"
                                    :y="CHART_HEIGHT - 8"
                                    text-anchor="middle"
                                    class="fill-muted-foreground text-[10px]"
                                >
                                    {{ formatDay(tick.date) }}
                                </text>
                            </g>

                            <path
                                v-for="series in chartSeries.filter(
                                    (item) => item.visible,
                                )"
                                :key="series.product_id"
                                :d="linePath(series.points)"
                                fill="none"
                                :stroke="series.color"
                                stroke-width="2"
                                stroke-linejoin="round"
                                stroke-linecap="round"
                            />
                        </svg>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="series in chartSeries"
                            :key="series.product_id"
                            type="button"
                            class="flex items-center gap-1.5 rounded-full border border-border px-2.5 py-1 text-xs transition"
                            :class="
                                series.visible
                                    ? 'opacity-100'
                                    : 'line-through opacity-40'
                            "
                            @click="toggleSeries(series.product_id)"
                        >
                            <span
                                class="size-2.5 rounded-full"
                                :style="{ backgroundColor: series.color }"
                            />
                            {{ series.name }}
                        </button>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Món tăng / giảm ─────────────────────────────────────────── -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent class="p-4">
                        <h2
                            class="mb-3 flex items-center gap-2 text-sm font-semibold"
                        >
                            <TrendingUp class="size-4 text-emerald-500" />
                            Món đang tăng tốc
                        </h2>
                        <div
                            v-if="data.movers.rising.length"
                            class="flex flex-col gap-2"
                        >
                            <button
                                v-for="row in data.movers.rising"
                                :key="row.product_id"
                                type="button"
                                class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-left transition hover:bg-accent"
                                @click="openDish(row.product_id)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ row.name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ metricValueOf(row) }} · kỳ trước
                                        {{
                                            metric === 'quantity'
                                                ? formatNumber(
                                                      row.previous_metric_value,
                                                  )
                                                : formatMoney(
                                                      row.previous_metric_value,
                                                  )
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="flex shrink-0 items-center gap-1 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    <ArrowUpRight class="size-4" />
                                    {{ formatSigned(row.change_percent) }}
                                </span>
                            </button>
                        </div>
                        <p
                            v-else
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            Không có món nào tăng so với kỳ trước.
                        </p>

                        <div
                            v-if="data.movers.newcomers.length"
                            class="mt-4 border-t border-border pt-3"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground"
                            >
                                Món mới xuất hiện trong kỳ
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="row in data.movers.newcomers"
                                    :key="row.product_id"
                                    variant="secondary"
                                    class="cursor-pointer"
                                    @click="openDish(row.product_id)"
                                >
                                    {{ row.name }} · {{ metricValueOf(row) }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <h2
                            class="mb-3 flex items-center gap-2 text-sm font-semibold"
                        >
                            <TrendingDown class="size-4 text-rose-500" />
                            Món đang hụt hơi
                        </h2>
                        <div
                            v-if="data.movers.falling.length"
                            class="flex flex-col gap-2"
                        >
                            <button
                                v-for="row in data.movers.falling"
                                :key="row.product_id"
                                type="button"
                                class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-left transition hover:bg-accent"
                                @click="openDish(row.product_id)"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {{ row.name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ metricValueOf(row) }} · kỳ trước
                                        {{
                                            metric === 'quantity'
                                                ? formatNumber(
                                                      row.previous_metric_value,
                                                  )
                                                : formatMoney(
                                                      row.previous_metric_value,
                                                  )
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="flex shrink-0 items-center gap-1 text-sm font-semibold text-rose-600 dark:text-rose-400"
                                >
                                    <ArrowDownRight class="size-4" />
                                    {{ formatSigned(row.change_percent) }}
                                </span>
                            </button>
                        </div>
                        <p
                            v-else
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            Không có món nào giảm so với kỳ trước.
                        </p>

                        <div
                            v-if="data.movers.dropouts.length"
                            class="mt-4 border-t border-border pt-3"
                        >
                            <p
                                class="mb-2 text-xs font-semibold text-muted-foreground"
                            >
                                Kỳ trước có bán, kỳ này bằng 0
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="row in data.movers.dropouts"
                                    :key="row.product_id"
                                    variant="outline"
                                    class="cursor-pointer"
                                    @click="openDish(row.product_id)"
                                >
                                    {{ row.name }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Bối cảnh: khung giờ / thứ / kênh / chi nhánh ─────────────── -->
            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold">
                            Bán chạy vào khung giờ nào
                        </h2>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="row in data.dayparts"
                                :key="row.key"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="w-32 shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ row.label }}
                                </span>
                                <div
                                    class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-sky-500"
                                        :style="{
                                            width: `${row.share_percent}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-24 shrink-0 text-right text-xs font-medium"
                                >
                                    {{ formatNumber(row.qty) }} phần
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold">
                            Bán chạy vào thứ mấy
                        </h2>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="row in data.weekdays"
                                :key="row.key"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="w-32 shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ row.label }}
                                </span>
                                <div
                                    class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-violet-500"
                                        :style="{
                                            width: `${row.share_percent}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-24 shrink-0 text-right text-xs font-medium"
                                >
                                    {{ formatNumber(row.qty) }} phần
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold">
                            Theo kênh bán
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="text-muted-foreground">
                                    <tr class="border-b border-border">
                                        <th class="py-2 text-left font-medium">
                                            Kênh
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Phần
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Đơn
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Doanh thu
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Tỷ trọng
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in data.channels"
                                        :key="row.key"
                                        class="border-b border-border/50 last:border-0"
                                    >
                                        <td class="py-2">{{ row.label }}</td>
                                        <td class="py-2 text-right">
                                            {{ formatNumber(row.qty) }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ formatNumber(row.orders ?? 0) }}
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ formatMoney(row.revenue) }}
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{
                                                formatPercent(row.share_percent)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <h2 class="mb-3 text-sm font-semibold">
                            Theo chi nhánh — quán quân từng nơi
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="text-muted-foreground">
                                    <tr class="border-b border-border">
                                        <th class="py-2 text-left font-medium">
                                            Chi nhánh
                                        </th>
                                        <th class="py-2 text-left font-medium">
                                            Món bán chạy nhất
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Phần
                                        </th>
                                        <th class="py-2 text-right font-medium">
                                            Tỷ trọng
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in data.branches"
                                        :key="row.branch_id ?? 'none'"
                                        class="border-b border-border/50 last:border-0"
                                    >
                                        <td class="py-2">
                                            {{ row.branch_name }}
                                        </td>
                                        <td class="py-2">
                                            {{ row.top_dish ?? '—' }}
                                            <span class="text-muted-foreground">
                                                ({{
                                                    formatNumber(
                                                        row.top_dish_qty,
                                                    )
                                                }})
                                            </span>
                                        </td>
                                        <td class="py-2 text-right">
                                            {{ formatNumber(row.qty) }}
                                        </td>
                                        <td class="py-2 text-right font-medium">
                                            {{
                                                formatPercent(row.share_percent)
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Bảng xếp hạng đầy đủ ────────────────────────────────────── -->
            <Card>
                <CardContent class="p-4">
                    <div
                        class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <h2 class="text-sm font-semibold">
                            Bảng xếp hạng đầy đủ ({{ filteredRanking.length }}
                            món)
                        </h2>
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative">
                                <Search
                                    class="absolute top-2.5 left-2.5 size-3.5 text-muted-foreground"
                                />
                                <Input
                                    v-model="search"
                                    placeholder="Tìm món hoặc danh mục…"
                                    class="h-9 w-52 pl-8 text-xs"
                                />
                            </div>
                            <Select v-model="abcFilter">
                                <SelectTrigger class="h-9 w-36 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Tất cả nhóm</SelectItem
                                    >
                                    <SelectItem value="A">Nhóm A</SelectItem>
                                    <SelectItem value="B">Nhóm B</SelectItem>
                                    <SelectItem value="C">Nhóm C</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="text-muted-foreground">
                                <tr class="border-b border-border">
                                    <th class="py-2 text-left font-medium">
                                        #
                                    </th>
                                    <th class="py-2 text-left font-medium">
                                        Món
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Phần
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Doanh thu
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        LN gộp
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Biên
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Tỷ lệ đơn
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Tỷ trọng
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Luỹ kế
                                    </th>
                                    <th class="py-2 text-center font-medium">
                                        Nhóm
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        So kỳ trước
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in visibleRanking"
                                    :key="row.product_id"
                                    class="cursor-pointer border-b border-border/50 transition last:border-0 hover:bg-accent"
                                    @click="openDish(row.product_id)"
                                >
                                    <td class="py-2">
                                        <div class="flex items-center gap-1">
                                            <span class="font-semibold">{{
                                                row.rank
                                            }}</span>
                                            <ChevronUp
                                                v-if="
                                                    row.rank_delta &&
                                                    row.rank_delta > 0
                                                "
                                                class="size-3 text-emerald-500"
                                            />
                                            <ChevronDown
                                                v-else-if="
                                                    row.rank_delta &&
                                                    row.rank_delta < 0
                                                "
                                                class="size-3 text-rose-500"
                                            />
                                        </div>
                                    </td>
                                    <td class="max-w-[220px] py-2">
                                        <p class="truncate font-medium">
                                            {{ row.name }}
                                            <Badge
                                                v-if="row.is_retired"
                                                variant="outline"
                                                class="ml-1 text-[10px]"
                                                >đã ngừng bán</Badge
                                            >
                                        </p>
                                        <p
                                            class="truncate text-[11px] text-muted-foreground"
                                        >
                                            {{ row.category_name }}
                                        </p>
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatNumber(row.qty) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatMoney(row.revenue) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatMoney(row.gross_profit) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatPercent(row.margin_percent) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatPercent(row.attach_rate) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatPercent(row.share_percent) }}
                                    </td>
                                    <td
                                        class="py-2 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatPercent(
                                                row.cumulative_percent,
                                            )
                                        }}
                                    </td>
                                    <td class="py-2 text-center">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                            :class="abcClass(row.abc_class)"
                                        >
                                            {{ row.abc_class }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-2 text-right font-medium"
                                        :class="changeClass(row.change_percent)"
                                    >
                                        <span
                                            class="inline-flex items-center gap-0.5"
                                        >
                                            <ArrowUpRight
                                                v-if="row.trend === 'up'"
                                                class="size-3"
                                            />
                                            <ArrowDownRight
                                                v-else-if="row.trend === 'down'"
                                                class="size-3"
                                            />
                                            <Minus v-else class="size-3" />
                                            {{
                                                formatSigned(row.change_percent)
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="filteredRanking.length > 20"
                        class="mt-3 text-center"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="showAllRows = !showAllRows"
                        >
                            {{
                                showAllRows
                                    ? 'Thu gọn'
                                    : `Xem tất cả ${filteredRanking.length} món`
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Theo danh mục ───────────────────────────────────────────── -->
            <Card>
                <CardContent class="p-4">
                    <h2 class="mb-3 text-sm font-semibold">Theo danh mục</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="text-muted-foreground">
                                <tr class="border-b border-border">
                                    <th class="py-2 text-left font-medium">
                                        Danh mục
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Số món
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Phần
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Doanh thu
                                    </th>
                                    <th class="py-2 text-right font-medium">
                                        Tỷ trọng
                                    </th>
                                    <th class="py-2 text-left font-medium">
                                        Món dẫn đầu
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in data.categories"
                                    :key="row.category"
                                    class="border-b border-border/50 last:border-0"
                                >
                                    <td class="py-2">{{ row.category }}</td>
                                    <td class="py-2 text-right">
                                        {{ row.dishes }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatNumber(row.qty) }}
                                    </td>
                                    <td class="py-2 text-right">
                                        {{ formatMoney(row.revenue) }}
                                    </td>
                                    <td class="py-2 text-right font-medium">
                                        {{ formatPercent(row.revenue_share) }}
                                    </td>
                                    <td class="py-2">
                                        {{ row.top_dish ?? '—' }}
                                        <span class="text-muted-foreground">
                                            ({{
                                                formatNumber(row.top_dish_qty)
                                            }})
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ── Hộp thoại chi tiết món ──────────────────────────────────────── -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ detail?.product.name ?? 'Chi tiết món' }}
                    </DialogTitle>
                </DialogHeader>

                <div v-if="detailLoading" class="flex justify-center py-12">
                    <Loader2
                        class="size-6 animate-spin text-muted-foreground"
                    />
                </div>

                <div v-else-if="detail" class="flex flex-col gap-4">
                    <p class="text-xs text-muted-foreground">
                        {{ detail.product.category_name }} · giá bán
                        {{ formatMoney(detail.product.price) }} · giá vốn
                        {{ formatMoney(detail.product.cost_price) }}
                    </p>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg border border-border p-3">
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Đã bán
                            </p>
                            <p class="text-lg font-bold">
                                {{ formatNumber(detail.summary.qty) }}
                            </p>
                            <p
                                class="text-[10px]"
                                :class="
                                    changeClass(
                                        detail.summary.qty_change_percent,
                                    )
                                "
                            >
                                {{
                                    formatSigned(
                                        detail.summary.qty_change_percent,
                                    )
                                }}
                                so kỳ trước
                            </p>
                        </div>
                        <div class="rounded-lg border border-border p-3">
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Doanh thu
                            </p>
                            <p class="text-lg font-bold">
                                {{ formatMoney(detail.summary.revenue) }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                Biên
                                {{
                                    formatPercent(detail.summary.margin_percent)
                                }}
                            </p>
                        </div>
                        <div class="rounded-lg border border-border p-3">
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Tỷ lệ đơn có món
                            </p>
                            <p class="text-lg font-bold">
                                {{ formatPercent(detail.summary.attach_rate) }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                {{ formatNumber(detail.summary.order_count) }}
                                đơn
                            </p>
                        </div>
                        <div class="rounded-lg border border-border p-3">
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Trung bình/ngày
                            </p>
                            <p class="text-lg font-bold">
                                {{ detail.summary.daily_avg_qty }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                {{ detail.summary.avg_qty_per_order }} phần/đơn
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="mb-1.5 text-xs font-semibold">
                            Xu hướng theo ngày
                        </p>
                        <svg
                            viewBox="0 0 200 48"
                            preserveAspectRatio="none"
                            class="h-16 w-full rounded-lg bg-muted/40"
                        >
                            <path
                                :d="sparklinePath(detail.daily_series)"
                                fill="none"
                                stroke="#0ea5e9"
                                stroke-width="1.5"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-2 text-xs font-semibold">
                                Theo khung giờ
                            </p>
                            <div
                                v-for="row in detail.dayparts"
                                :key="row.key"
                                class="mb-1.5 flex items-center gap-2"
                            >
                                <span
                                    class="w-28 shrink-0 text-[11px] text-muted-foreground"
                                >
                                    {{ row.label }}
                                </span>
                                <div
                                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-sky-500"
                                        :style="{
                                            width: `${row.share_percent}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-12 shrink-0 text-right text-[11px]"
                                >
                                    {{ formatNumber(row.qty) }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold">
                                Theo thứ trong tuần
                            </p>
                            <div
                                v-for="row in detail.weekdays"
                                :key="row.key"
                                class="mb-1.5 flex items-center gap-2"
                            >
                                <span
                                    class="w-28 shrink-0 text-[11px] text-muted-foreground"
                                >
                                    {{ row.label }}
                                </span>
                                <div
                                    class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-violet-500"
                                        :style="{
                                            width: `${row.share_percent}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-12 shrink-0 text-right text-[11px]"
                                >
                                    {{ formatNumber(row.qty) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="detail.paired_with.length">
                        <p class="mb-2 text-xs font-semibold">
                            Thường được gọi kèm — gợi ý dựng combo
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="text-muted-foreground">
                                    <tr class="border-b border-border">
                                        <th
                                            class="py-1.5 text-left font-medium"
                                        >
                                            Món kèm
                                        </th>
                                        <th
                                            class="py-1.5 text-right font-medium"
                                        >
                                            Số đơn chung
                                        </th>
                                        <th
                                            class="py-1.5 text-right font-medium"
                                        >
                                            Tỷ lệ đi kèm
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in detail.paired_with"
                                        :key="row.product_id"
                                        class="border-b border-border/50 last:border-0"
                                    >
                                        <td class="py-1.5">{{ row.name }}</td>
                                        <td class="py-1.5 text-right">
                                            {{ formatNumber(row.orders) }}
                                        </td>
                                        <td
                                            class="py-1.5 text-right font-medium"
                                        >
                                            {{ formatPercent(row.confidence) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="detail.branches.length > 1">
                        <p class="mb-2 text-xs font-semibold">Theo chi nhánh</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge
                                v-for="row in detail.branches"
                                :key="row.branch_id ?? 'none'"
                                variant="secondary"
                            >
                                {{ row.branch_name }}:
                                {{ formatNumber(row.qty) }} phần ({{
                                    formatPercent(row.share_percent)
                                }})
                            </Badge>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
