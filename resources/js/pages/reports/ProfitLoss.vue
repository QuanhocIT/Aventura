<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Banknote, FileBarChart, Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { computed } from 'vue';
import AnimatedNumber from '@/components/AnimatedNumber.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Revenue = {
    order_count: number; gross_revenue: number; discount_total: number;
    service_charge_total: number; net_revenue: number;
};
type Monthly = {
    period: string; year: number; month: number;
    revenue: Revenue; cogs: number; gross_profit: number; gross_margin: number;
    labor_cost: number;
    operating_expenses: { total: number; by_category: Record<string, number> };
    net_profit: number; net_margin: number;
};
type TrendPoint = { period: string; net_revenue: number; net_profit: number };

const props = defineProps<{
    report: {
        current: Monthly;
        previous: { period: string; net_revenue: number; gross_profit: number; net_profit: number };
        trend: TrendPoint[];
    };
    filters: { year: number; month: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Báo cáo & AI', href: '/reports' },
            { title: 'Báo cáo Lãi/Lỗ', href: '/reports/profit-loss' },
        ],
    },
});

const cur = computed(() => props.report.current);

function vnd(n: number): string {
    return n.toLocaleString('vi-VN') + 'đ';
}

function changePercent(now: number, before: number): number | null {
    if (before === 0) {
        return null;
    }

    return Math.round(((now - before) / Math.abs(before)) * 100);
}

const revenueChange = computed(() => changePercent(cur.value.revenue.net_revenue, props.report.previous.net_revenue));
const profitChange = computed(() => changePercent(cur.value.net_profit, props.report.previous.net_profit));

// ── Bộ chọn kỳ ──
const now = new Date();
const yearOptions = Array.from({ length: 4 }, (_, i) => now.getFullYear() - i);
const monthOptions = Array.from({ length: 12 }, (_, i) => i + 1);

function changePeriod(year: number, month: number) {
    router.get('/reports/profit-loss', { year, month }, { preserveState: false });
}

// ── Sparkline 6 tháng (SVG thuần, không cần thư viện) ──
const spark = computed(() => {
    const points = props.report.trend;
    const values = points.flatMap(p => [p.net_revenue, p.net_profit]);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const w = 600;
    const h = 120;
    const step = w / Math.max(points.length - 1, 1);

    const toPath = (key: 'net_revenue' | 'net_profit') =>
        points.map((p, i) => `${i === 0 ? 'M' : 'L'}${(i * step).toFixed(1)},${(h - ((p[key] - min) / range) * (h - 10) - 5).toFixed(1)}`).join(' ');

    return { w, h, step, revenuePath: toPath('net_revenue'), profitPath: toPath('net_profit'), zeroY: h - ((0 - min) / range) * (h - 10) - 5 };
});

// Các dòng P&L theo bố cục báo cáo kế toán
const lines = computed(() => [
    { label: 'Doanh thu bán hàng (gộp)', value: cur.value.revenue.gross_revenue, kind: 'plus' },
    { label: 'Giảm giá / khuyến mãi', value: -cur.value.revenue.discount_total, kind: 'minus' },
    { label: 'Phí dịch vụ / giao hàng', value: cur.value.revenue.service_charge_total, kind: 'plus' },
    { label: 'Doanh thu thuần', value: cur.value.revenue.net_revenue, kind: 'subtotal' },
    { label: 'Giá vốn hàng bán (COGS)', value: -cur.value.cogs, kind: 'minus' },
    { label: 'Lợi nhuận gộp', value: cur.value.gross_profit, kind: 'subtotal', badge: `${cur.value.gross_margin}%` },
    { label: 'Chi phí nhân sự (lương)', value: -cur.value.labor_cost, kind: 'minus' },
    { label: 'Chi phí vận hành', value: -cur.value.operating_expenses.total, kind: 'minus' },
    { label: 'LỢI NHUẬN RÒNG', value: cur.value.net_profit, kind: 'total', badge: `${cur.value.net_margin}%` },
]);
</script>

<template>
    <Head title="Báo cáo Lãi/Lỗ" />

    <div class="px-6 py-6 max-w-5xl mx-auto space-y-6 w-full">
        <!-- Header + bộ chọn kỳ -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 rounded-xl">
                    <FileBarChart class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-lg font-black">Báo cáo Lãi/Lỗ (P&L)</h1>
                    <p class="text-xs text-muted-foreground">Tự động tổng hợp từ đơn hàng, định lượng BOM, bảng lương và chi phí vận hành</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Select :model-value="String(filters.month)" @update:model-value="(v: any) => changePeriod(filters.year, Number(v))">
                    <SelectTrigger class="h-9 w-28 text-xs"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="m in monthOptions" :key="m" :value="String(m)" class="text-xs">Tháng {{ m }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select :model-value="String(filters.year)" @update:model-value="(v: any) => changePeriod(Number(v), filters.month)">
                    <SelectTrigger class="h-9 w-24 text-xs"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in yearOptions" :key="y" :value="String(y)" class="text-xs">{{ y }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- 3 chỉ số chính -->
        <div class="grid gap-3 sm:grid-cols-3">
            <Card class="rounded-2xl">
                <CardContent class="p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Doanh thu thuần</p>
                    <p class="text-2xl font-black mt-1 tabular-nums"><AnimatedNumber :value="cur.revenue.net_revenue" suffix="đ" /></p>
                    <p v-if="revenueChange !== null" class="mt-1 flex items-center gap-1 text-[11px] font-semibold" :class="revenueChange >= 0 ? 'text-emerald-600' : 'text-rose-500'">
                        <component :is="revenueChange >= 0 ? ArrowUp : ArrowDown" class="w-3 h-3" />
                        {{ Math.abs(revenueChange) }}% so với {{ report.previous.period }}
                    </p>
                </CardContent>
            </Card>
            <Card class="rounded-2xl">
                <CardContent class="p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Lợi nhuận gộp</p>
                    <p class="text-2xl font-black mt-1 tabular-nums"><AnimatedNumber :value="cur.gross_profit" suffix="đ" /></p>
                    <p class="mt-1 text-[11px] font-semibold text-muted-foreground">Biên gộp {{ cur.gross_margin }}%</p>
                </CardContent>
            </Card>
            <Card class="rounded-2xl" :class="cur.net_profit >= 0 ? 'border-emerald-200 dark:border-emerald-900/40' : 'border-rose-200 dark:border-rose-900/40'">
                <CardContent class="p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Lợi nhuận ròng</p>
                    <p class="text-2xl font-black mt-1 tabular-nums" :class="cur.net_profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        <AnimatedNumber :value="cur.net_profit" suffix="đ" />
                    </p>
                    <p v-if="profitChange !== null" class="mt-1 flex items-center gap-1 text-[11px] font-semibold" :class="profitChange >= 0 ? 'text-emerald-600' : 'text-rose-500'">
                        <component :is="profitChange >= 0 ? TrendingUp : TrendingDown" class="w-3 h-3" />
                        {{ Math.abs(profitChange) }}% so với {{ report.previous.period }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Xu hướng 6 tháng -->
        <Card class="rounded-2xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-black flex items-center gap-2"><Banknote class="w-4 h-4" /> Xu hướng 6 tháng</CardTitle>
                <CardDescription class="text-xs">
                    <span class="inline-flex items-center gap-1.5 mr-4"><span class="w-2.5 h-0.5 rounded bg-primary inline-block" /> Doanh thu thuần</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-0.5 rounded bg-emerald-500 inline-block" /> Lợi nhuận ròng</span>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <svg :viewBox="`0 0 ${spark.w} ${spark.h}`" class="w-full h-28" preserveAspectRatio="none">
                    <line :x1="0" :y1="spark.zeroY" :x2="spark.w" :y2="spark.zeroY" class="stroke-border" stroke-dasharray="4 4" stroke-width="1" />
                    <path :d="spark.revenuePath" fill="none" class="stroke-primary" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                    <path :d="spark.profitPath" fill="none" class="stroke-emerald-500" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="flex justify-between mt-1">
                    <span v-for="p in report.trend" :key="p.period" class="text-[10px] text-muted-foreground font-semibold">{{ p.period }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Bảng P&L -->
        <Card class="rounded-2xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-black">Kết quả kinh doanh — kỳ {{ cur.period }}</CardTitle>
                <CardDescription class="text-xs">{{ cur.revenue.order_count.toLocaleString('vi-VN') }} đơn hoàn thành trong kỳ</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="divide-y divide-border">
                    <div
                        v-for="line in lines"
                        :key="line.label"
                        class="flex items-center justify-between py-2.5"
                        :class="{
                            'font-bold bg-neutral-50 dark:bg-neutral-900/60 -mx-3 px-3 rounded-lg': line.kind === 'subtotal',
                            'font-black text-sm bg-primary/5 -mx-3 px-3 rounded-lg': line.kind === 'total',
                        }"
                    >
                        <span class="text-xs flex items-center gap-2">
                            <component
                                :is="line.value > 0 ? ArrowUp : line.value < 0 ? ArrowDown : Minus"
                                v-if="line.kind === 'plus' || line.kind === 'minus'"
                                class="w-3 h-3"
                                :class="line.kind === 'plus' ? 'text-emerald-500' : 'text-rose-400'"
                            />
                            {{ line.label }}
                            <span v-if="line.badge" class="rounded-full bg-muted border border-border px-2 py-0.5 text-[10px] font-bold">{{ line.badge }}</span>
                        </span>
                        <span
                            class="text-xs tabular-nums"
                            :class="{
                                'text-rose-500': line.value < 0 && line.kind !== 'total',
                                'text-emerald-600 dark:text-emerald-400 text-sm': line.kind === 'total' && line.value >= 0,
                                'text-rose-600 dark:text-rose-400 text-sm': line.kind === 'total' && line.value < 0,
                            }"
                        >{{ vnd(line.value) }}</span>
                    </div>
                </div>

                <!-- Chi tiết chi phí vận hành theo danh mục -->
                <div v-if="Object.keys(cur.operating_expenses.by_category).length" class="mt-5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground mb-2">Chi phí vận hành theo danh mục</p>
                    <div class="space-y-1.5">
                        <div v-for="(amount, name) in cur.operating_expenses.by_category" :key="name" class="flex items-center gap-3">
                            <span class="text-[11px] w-44 truncate shrink-0">{{ name }}</span>
                            <div class="flex-1 h-2 rounded-full bg-muted overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-primary/70 transition-all duration-700"
                                    :style="`width: ${Math.min(100, (amount / Math.max(cur.operating_expenses.total, 1)) * 100)}%`"
                                />
                            </div>
                            <span class="text-[11px] tabular-nums w-24 text-right shrink-0">{{ vnd(amount) }}</span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <p class="text-[11px] text-muted-foreground">
            * COGS tính theo định lượng BOM × giá vốn trung bình nguyên liệu; chi phí nhân sự lấy từ bảng lương đã duyệt có kỳ lương kết thúc trong tháng.
            Số liệu mang tính quản trị, không thay thế báo cáo kế toán thuế.
        </p>
    </div>
</template>
