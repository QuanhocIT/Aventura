<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    BrainCircuit,
    Boxes,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    Gauge,
    Lightbulb,
    PackageCheck,
    PackageSearch,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Warehouse,
} from 'lucide-vue-next';
import { computed } from 'vue';

import NegativeInventoryCases from '@/components/NegativeInventoryCases.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import centralWarehouseRoutes from '@/routes/inventory/central-warehouse';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch?: any;
    supplyAnalytics?: any;
    centralWarehouseAnalytics?: any;
    centralWarehouseAi?: any;
    receivingSummary?: any;
    inventorySummary?: any;
    supplyChainAlerts?: any;
    supplyChainReconciliation?: any;
    negativeStockCases?: any[];
}>();

const analytics = computed(() => props.supplyAnalytics ?? {});
const summary = computed(() => analytics.value.summary ?? {});
const inventory = computed(() => props.inventorySummary ?? {});
const receiving = computed(() => props.receivingSummary ?? {});
const warehouseKpi = computed(() => props.centralWarehouseAnalytics ?? {});
const aiAssessment = computed(() => props.centralWarehouseAi ?? {});
const supplyChainAlerts = computed(() => props.supplyChainAlerts ?? {});
const reconciliation = computed(() => props.supplyChainReconciliation ?? {});

const daily = computed(() => analytics.value.daily ?? []);
const branches = computed(() => analytics.value.branches ?? []);
const topIngredients = computed(() => analytics.value.top_ingredients ?? []);
const recommendations = computed(() => analytics.value.recommendations ?? []);
const insights = computed(() => analytics.value.insights ?? []);

const maxDailyItems = computed(() =>
    Math.max(...daily.value.map((day: any) => Number(day.items ?? 0)), 1),
);

const maxBranchRequests = computed(() =>
    Math.max(...branches.value.map((branch: any) => Number(branch.requests ?? 0)), 1),
);

const maxIngredientQuantity = computed(() =>
    Math.max(
        ...topIngredients.value.map((item: any) => Number(item.total_quantity ?? 0)),
        1,
    ),
);

const branchReport = computed(() => {
    const totalRequests = branches.value.reduce(
        (total: number, branch: any) => total + Number(branch.requests ?? 0),
        0,
    );

    return branches.value.map((branch: any) => ({
        ...branch,
        share: totalRequests > 0 ? (Number(branch.requests ?? 0) / totalRequests) * 100 : 0,
    }));
});

const healthMetrics = computed(() => [
    {
        label: 'Fill rate',
        value: Number(summary.value.fill_rate_percent ?? warehouseKpi.value.fill_rate_percent ?? 100),
        suffix: '%',
        description: 'Tỷ lệ số lượng đã đáp ứng',
        icon: PackageCheck,
        iconClass: 'text-indigo-300',
        inverted: false,
    },
    {
        label: 'OTIF tháng này',
        value: Number(warehouseKpi.value.otif_percent ?? 100),
        suffix: '%',
        description: 'Đúng hạn và đủ lượng',
        icon: Clock3,
        iconClass: 'text-emerald-300',
        inverted: false,
    },
    {
        label: 'FEFO tuân thủ',
        value: Number(warehouseKpi.value.fefo_compliance ?? 100),
        suffix: '%',
        description: 'Xuất theo hạn dùng trước',
        icon: ShieldCheck,
        iconClass: 'text-sky-300',
        inverted: false,
    },
    {
        label: 'Tỷ lệ hao hụt',
        value: Number(warehouseKpi.value.waste_ratio_percent ?? 0),
        suffix: '%',
        description: 'Mức hao hụt ước tính',
        icon: TrendingDown,
        iconClass: 'text-amber-300',
        inverted: true,
    },
]);

const formatQuantity = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const formatPercent = (value: number | string | null | undefined) =>
    `${new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 1 }).format(Number(value || 0))}%`;

const formatDateTime = (value: string | null | undefined) => {
    if (!value) {
        return 'Chưa có dữ liệu';
    }

    return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const barHeight = (value: number | string | null | undefined, max: number) =>
    `${Math.max(Number(value ?? 0) > 0 ? 8 : 2, (Number(value ?? 0) / max) * 100)}%`;

const metricBarWidth = (metric: any) => {
    if (metric.inverted) {
        return `${Math.max(8, Math.min(100, 100 - metric.value * 10))}%`;
    }

    return `${Math.max(8, Math.min(100, metric.value))}%`;
};

const metricStatus = (metric: any) => {
    if (metric.inverted) {
        return metric.value <= 2 ? 'Ổn định' : metric.value <= 5 ? 'Cần theo dõi' : 'Rủi ro';
    }

    return metric.value >= 95 ? 'Tốt' : metric.value >= 85 ? 'Cần theo dõi' : 'Rủi ro';
};

const metricStatusClass = (metric: any) => {
    const status = metricStatus(metric);

    return status === 'Tốt' || status === 'Ổn định'
        ? 'text-emerald-300'
        : status === 'Cần theo dõi'
          ? 'text-amber-300'
          : 'text-rose-300';
};

const priorityMeta = (priority: string) =>
    ({
        urgent: {
            label: 'Cần nhập gấp',
            class: 'border-rose-500/30 bg-rose-500/10 text-rose-300',
        },
        watch: {
            label: 'Nên lên kế hoạch',
            class: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
        },
        stable: {
            label: 'Đang ổn định',
            class: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        },
    })[priority as 'urgent' | 'watch' | 'stable'] ?? {
        label: 'Chưa phân loại',
        class: 'border-border bg-muted/40 text-muted-foreground',
    };

const insightClass = (type: string) =>
    ({
        danger: 'border-rose-500/25 bg-rose-500/10',
        warning: 'border-amber-500/25 bg-amber-500/10',
        success: 'border-emerald-500/25 bg-emerald-500/10',
        info: 'border-indigo-500/25 bg-indigo-500/10',
    })[type] ?? 'border-border bg-muted/20';

const insightIconClass = (type: string) =>
    ({
        danger: 'bg-rose-500/15 text-rose-300',
        warning: 'bg-amber-500/15 text-amber-300',
        success: 'bg-emerald-500/15 text-emerald-300',
        info: 'bg-indigo-500/15 text-indigo-300',
    })[type] ?? 'bg-muted text-muted-foreground';

const aiLevelClass = computed(() =>
    ({
        stable: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-200',
        watch: 'border-amber-500/25 bg-amber-500/10 text-amber-200',
        risk: 'border-orange-500/25 bg-orange-500/10 text-orange-200',
        critical: 'border-rose-500/25 bg-rose-500/10 text-rose-200',
    })[aiAssessment.value.level as string] ?? 'border-indigo-500/25 bg-indigo-500/10 text-indigo-200',
);

const aiSignalClass = (severity: string) =>
    ({
        critical: 'border-rose-500/25 bg-rose-500/10',
        high: 'border-orange-500/25 bg-orange-500/10',
        medium: 'border-amber-500/25 bg-amber-500/10',
        low: 'border-indigo-500/25 bg-indigo-500/10',
    })[severity] ?? 'border-border bg-muted/20';
</script>

<template>
    <Head title="Tổng quan Kho Tổng" />

    <div class="mx-auto w-full max-w-[1500px] space-y-6 p-4 sm:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-indigo-500/20 bg-gradient-to-br from-slate-950 via-indigo-950/90 to-slate-900 p-6 text-white shadow-xl sm:p-8"
        >
            <div class="pointer-events-none absolute -top-24 right-8 h-64 w-64 rounded-full bg-indigo-500/15 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-violet-500/10 blur-3xl" />
            <div class="relative flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
                <div>
                    <div class="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-indigo-300">
                        <Warehouse class="h-4 w-4" /> Bảng điều hành dữ liệu
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">Tổng quan Kho Tổng</h1>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-100/75">
                        Góc nhìn quản trị chỉ đọc về nhu cầu cấp phát, sức khỏe tồn kho và các tín hiệu cần lưu ý.
                        Mọi chỉ số được tổng hợp từ dữ liệu vận hành thực tế.
                    </p>
                </div>
                <div class="flex shrink-0 flex-col items-stretch gap-3 sm:flex-row sm:items-end">
                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm backdrop-blur-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-indigo-200/70">Phạm vi dữ liệu</p>
                        <p class="mt-1 font-semibold text-white">{{ centralBranch?.name || 'Kho Tổng' }}</p>
                        <p class="mt-1 text-xs text-indigo-100/60">Cập nhật {{ formatDateTime(analytics.generated_at) }}</p>
                    </div>
                    <Link
                        href="/inventory/central-warehouse/receiving?create=1"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-orange-950/30 transition hover:bg-orange-400"
                    >
                        <PackageCheck class="h-4 w-4" />
                        Nhập nguyên liệu
                    </Link>
                    <Link
                        href="/inventory/central-warehouse/material-closing"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-300/30 bg-amber-400/10 px-4 py-3 text-sm font-bold text-amber-100 transition hover:bg-amber-400/20"
                    >
                        <ClipboardCheck class="h-4 w-4" />
                        Chốt nguyên liệu
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <Card class="border-indigo-500/20 bg-indigo-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-indigo-300">Cấp phát hôm nay</p>
                        <CalendarDays class="h-4 w-4 text-indigo-300" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-indigo-100">{{ summary.today_requests ?? 0 }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ formatQuantity(summary.today_items) }} đơn vị nguyên liệu</p>
                </CardContent>
            </Card>
            <Card class="border-violet-500/20 bg-violet-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-violet-300">Nhu cầu 7 ngày</p>
                        <TrendingUp class="h-4 w-4 text-violet-300" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-violet-100">{{ formatQuantity(summary.last7_items) }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ summary.last7_requests ?? 0 }} đơn · {{ formatCurrency(summary.last7_value) }}</p>
                </CardContent>
            </Card>
            <Card class="border-amber-500/20 bg-amber-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-300">Đơn đang mở</p>
                        <Clock3 class="h-4 w-4 text-amber-300" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-amber-100">{{ summary.open_requests ?? 0 }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ summary.due_today_requests ?? 0 }} đơn đến hạn hôm nay</p>
                </CardContent>
            </Card>
            <Card class="border-emerald-500/20 bg-emerald-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-300">OTIF tháng này</p>
                        <Gauge class="h-4 w-4 text-emerald-300" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-emerald-100">{{ formatPercent(warehouseKpi.otif_percent ?? 100) }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">Đúng hạn và đủ lượng</p>
                </CardContent>
            </Card>
            <Card class="border-sky-500/20 bg-sky-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-sky-300">Giá trị tồn kho</p>
                        <Boxes class="h-4 w-4 text-sky-300" />
                    </div>
                    <p class="mt-3 text-lg font-bold text-sky-100">{{ formatCurrency(inventory.on_hand_value) }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">{{ inventory.ingredient_count ?? 0 }} mặt hàng · {{ formatQuantity(inventory.on_hand_quantity) }} đơn vị</p>
                </CardContent>
            </Card>
            <Card class="border-rose-500/20 bg-rose-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-rose-300">Cần nhập gấp</p>
                        <AlertTriangle class="h-4 w-4 text-rose-300" />
                    </div>
                    <p class="mt-3 text-2xl font-bold text-rose-100">{{ summary.urgent_recommendations ?? 0 }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">Nguyên liệu rủi ro thiếu trong 7 ngày</p>
                </CardContent>
            </Card>
        </section>

        <NegativeInventoryCases
            :cases="negativeStockCases"
            title="Âm nguyên liệu tại Kho Tổng"
        />

        <section class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <Card class="border-amber-500/25 bg-amber-950/5 shadow-sm">
                <CardHeader class="border-b border-amber-500/15 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base"><AlertTriangle class="h-5 w-5 text-amber-300" /> Cảnh báo chuỗi cung ứng</CardTitle>
                            <CardDescription class="mt-1 text-xs">Thiếu hàng, thiếu nhà cung cấp dự phòng hoặc PO trễ hạn.</CardDescription>
                        </div>
                        <span class="rounded-full border border-amber-400/25 bg-amber-500/10 px-2.5 py-1 text-[10px] font-bold text-amber-200">{{ supplyChainAlerts.critical ?? 0 }} khẩn</span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-2 p-4">
                    <div v-if="!(supplyChainAlerts.items ?? []).length" class="rounded-xl border border-dashed border-border p-5 text-center text-xs text-muted-foreground">Chưa có cảnh báo chuỗi cung ứng.</div>
                    <div v-for="item in (supplyChainAlerts.items ?? []).slice(0, 5)" :key="`${item.type}-${item.ingredient_id ?? item.purchase_order_id}`" class="flex items-start justify-between gap-3 rounded-xl border border-border bg-background/40 p-3">
                        <div>
                            <p class="text-xs font-bold text-foreground">{{ item.ingredient_name || item.po_number || 'Cảnh báo kho' }}</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">{{ item.message }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] font-bold uppercase" :class="item.severity === 'critical' ? 'text-rose-300' : 'text-amber-300'">{{ item.severity }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-sky-500/25 bg-sky-950/5 shadow-sm">
                <CardHeader class="border-b border-sky-500/15 py-4">
                    <CardTitle class="flex items-center gap-2 text-base"><ShieldCheck class="h-5 w-5 text-sky-300" /> Đối soát tồn kho</CardTitle>
                    <CardDescription class="mt-1 text-xs">So sánh số dư kho, lô và sổ giao dịch.</CardDescription>
                </CardHeader>
                <CardContent class="p-4">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-3xl font-black" :class="reconciliation.has_variance ? 'text-rose-300' : 'text-emerald-300'">{{ reconciliation.review_count ?? 0 }}</p>
                            <p class="text-xs text-muted-foreground">mặt hàng cần rà soát</p>
                        </div>
                        <span class="rounded-full border px-2 py-1 text-[10px] font-bold" :class="reconciliation.has_variance ? 'border-rose-400/25 bg-rose-500/10 text-rose-300' : 'border-emerald-400/25 bg-emerald-500/10 text-emerald-300'">{{ reconciliation.has_variance ? 'Cần xử lý' : 'Đã khớp' }}</span>
                    </div>
                    <div v-if="(reconciliation.items ?? []).length" class="mt-4 space-y-2">
                        <div v-for="item in reconciliation.items.slice(0, 3)" :key="item.ingredient_id" class="flex justify-between gap-3 text-xs">
                            <span class="truncate text-muted-foreground">{{ item.ingredient_name }}</span>
                            <span class="font-bold text-rose-300">{{ formatQuantity(item.variance) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.9fr_1.6fr]">
            <Card class="border-indigo-500/25 bg-gradient-to-br from-indigo-950/30 to-violet-950/10 shadow-sm">
                <CardHeader class="border-b border-indigo-500/15 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <BrainCircuit class="h-5 w-5 text-indigo-300" /> AI đánh giá Kho Tổng
                            </CardTitle>
                            <CardDescription class="mt-1 text-xs">Tổng hợp rủi ro từ tồn kho, cấp phát, tiếp nhận và năng lực xử lý.</CardDescription>
                        </div>
                        <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold" :class="aiLevelClass">
                            {{ aiAssessment.label || 'Chưa đánh giá' }}
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 p-5">
                    <div class="flex items-end gap-4">
                        <p class="text-5xl font-black tracking-tight text-foreground">{{ aiAssessment.score ?? '--' }}</p>
                        <div class="pb-1 text-xs text-muted-foreground">
                            <p>Điểm sức khỏe vận hành</p>
                            <p class="mt-1">Độ tin cậy {{ formatPercent(Number(aiAssessment.confidence ?? 0) * 100) }}</p>
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed text-muted-foreground">{{ aiAssessment.summary || 'Chưa đủ dữ liệu để tạo đánh giá.' }}</p>
                    <a
                        :href="centralWarehouseRoutes.aiAdvisor.url()"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-indigo-300 transition hover:text-indigo-200"
                    >
                        Mở Trợ lý AI Kho Tổng <ArrowRight class="h-3.5 w-3.5" />
                    </a>
                </CardContent>
            </Card>

            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="text-base">Ưu tiên hành động</CardTitle>
                            <CardDescription class="mt-1 text-xs">AI giải thích tín hiệu, bằng chứng và bước xử lý đề xuất.</CardDescription>
                        </div>
                        <span class="text-xs font-semibold text-muted-foreground">{{ aiAssessment.signal_count ?? 0 }} tín hiệu</span>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-3 p-4 md:grid-cols-2">
                    <div v-if="!aiAssessment.signals?.length" class="rounded-xl border border-dashed border-border p-5 text-center text-xs text-muted-foreground md:col-span-2">
                        Chưa phát hiện tín hiệu cần ưu tiên.
                    </div>
                    <div v-for="signal in (aiAssessment.signals ?? []).slice(0, 4)" :key="`${signal.metric}-${signal.title}`" class="rounded-xl border p-3" :class="aiSignalClass(signal.severity)">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-xs font-bold text-foreground">{{ signal.title }}</p>
                            <span class="shrink-0 text-[10px] font-bold uppercase text-muted-foreground">{{ signal.severity }}</span>
                        </div>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-muted-foreground">{{ signal.evidence }}</p>
                        <p class="mt-2 text-[11px] font-semibold leading-relaxed text-foreground">Khuyến nghị: {{ signal.advice }}</p>
                        <p class="mt-2 text-[10px] leading-relaxed text-muted-foreground">Bước tiếp theo: {{ signal.next_step }}</p>
                        <a v-if="signal.action_url" :href="signal.action_url" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold text-indigo-300 hover:underline">
                            {{ signal.action_label || 'Mở chi tiết' }} <ArrowRight class="h-3 w-3" />
                        </a>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.45fr_0.85fr]">
            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <BarChart3 class="h-5 w-5 text-indigo-300" /> Nhịp nhu cầu 7 ngày gần nhất
                            </CardTitle>
                            <CardDescription class="mt-1 text-xs">Số lượng nguyên liệu được yêu cầu theo ngày.</CardDescription>
                        </div>
                        <span class="hidden text-xs text-muted-foreground sm:block">{{ formatQuantity(summary.last7_items) }} đơn vị</span>
                    </div>
                </CardHeader>
                <CardContent class="p-4 sm:p-6">
                    <div v-if="daily.length" class="flex h-56 items-end gap-2 border-b border-border px-1 pb-1 sm:gap-4">
                        <div v-for="day in daily" :key="day.date" class="group flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <div class="relative flex h-full w-full max-w-14 items-end justify-center">
                                <div class="absolute -top-1 hidden rounded-md bg-slate-900 px-2 py-1 text-[10px] font-semibold text-white shadow-lg group-hover:block">
                                    {{ day.requests }} đơn · {{ formatQuantity(day.items) }} đơn vị
                                </div>
                                <div
                                    class="w-full rounded-t-lg bg-gradient-to-t from-indigo-600 to-violet-400 transition-all duration-300 group-hover:from-indigo-500 group-hover:to-fuchsia-400"
                                    :style="{ height: barHeight(day.items, maxDailyItems) }"
                                />
                            </div>
                            <span class="text-[10px] font-semibold text-muted-foreground">{{ day.label }}</span>
                        </div>
                    </div>
                    <div v-else class="flex h-56 items-center justify-center text-sm text-muted-foreground">Chưa đủ dữ liệu để lập biểu đồ.</div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                        <div class="rounded-xl border border-border bg-muted/20 p-3">
                            <p class="text-muted-foreground">Trung bình/ngày</p>
                            <p class="mt-1 font-bold text-foreground">{{ formatQuantity(summary.average_daily_requests) }} đơn</p>
                        </div>
                        <div class="rounded-xl border border-border bg-muted/20 p-3">
                            <p class="text-muted-foreground">Giá trị yêu cầu</p>
                            <p class="mt-1 font-bold text-foreground">{{ formatCurrency(summary.last7_value) }}</p>
                        </div>
                        <div class="rounded-xl border border-border bg-muted/20 p-3">
                            <p class="text-muted-foreground">Đang tiếp nhận</p>
                            <p class="mt-1 font-bold text-foreground">{{ summary.receiving_requests ?? 0 }} đơn</p>
                        </div>
                        <div class="rounded-xl border border-border bg-muted/20 p-3">
                            <p class="text-muted-foreground">Tranh chấp mở</p>
                            <p class="mt-1 font-bold text-foreground">{{ summary.disputed_requests ?? 0 }} đơn</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <CardTitle class="flex items-center gap-2 text-base"><ShieldCheck class="h-5 w-5 text-emerald-300" /> Sức khỏe Kho Tổng</CardTitle>
                    <CardDescription class="mt-1 text-xs">Đánh giá chất lượng phục vụ và kiểm soát tồn kho.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5 p-5">
                    <div v-for="metric in healthMetrics" :key="metric.label" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2">
                                <component :is="metric.icon" class="h-4 w-4" :class="metric.iconClass" />
                                <span class="font-semibold text-foreground">{{ metric.label }}</span>
                            </div>
                            <span class="font-bold" :class="metricStatusClass(metric)">{{ metric.value }}{{ metric.suffix }} · {{ metricStatus(metric) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-emerald-400 transition-all" :style="{ width: metricBarWidth(metric) }" />
                        </div>
                        <p class="text-[11px] text-muted-foreground">{{ metric.description }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 border-t border-border pt-4 text-xs">
                        <div>
                            <p class="text-muted-foreground">GRN cần xác minh</p>
                            <p class="mt-1 text-lg font-bold text-orange-300">{{ receiving.pending_review ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Lô sắp hết hạn</p>
                            <p class="mt-1 text-lg font-bold text-amber-300">{{ inventory.expiring_soon_count ?? 0 }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
            <Card class="border-amber-500/20 bg-amber-950/5 shadow-sm">
                <CardHeader class="border-b border-amber-500/15 py-4">
                    <CardTitle class="flex items-center gap-2 text-base"><Lightbulb class="h-5 w-5 text-amber-300" /> Đánh giá & lời khuyên</CardTitle>
                    <CardDescription class="mt-1 text-xs">Các tín hiệu được suy ra từ nhu cầu, tồn kho và trạng thái đơn.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 p-4">
                    <div v-if="insights.length === 0" class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">Chưa có tín hiệu đáng chú ý.</div>
                    <div v-for="insight in insights" :key="`${insight.type}-${insight.title}`" class="flex gap-3 rounded-xl border p-3" :class="insightClass(insight.type)">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" :class="insightIconClass(insight.type)">
                            <AlertTriangle v-if="insight.type === 'danger' || insight.type === 'warning'" class="h-4 w-4" />
                            <CheckCircle2 v-else-if="insight.type === 'success'" class="h-4 w-4" />
                            <Sparkles v-else class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-xs font-bold text-foreground">{{ insight.title }}</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted-foreground">{{ insight.message }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-sm">
                <CardHeader class="border-b border-border bg-muted/20 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <CardTitle class="flex items-center gap-2 text-base"><PackageSearch class="h-5 w-5 text-violet-300" /> Nguyên liệu có nhu cầu cao</CardTitle>
                            <CardDescription class="mt-1 text-xs">Xếp theo tổng lượng yêu cầu trong {{ analytics.period_days ?? 28 }} ngày gần nhất.</CardDescription>
                        </div>
                        <TrendingUp class="h-5 w-5 text-violet-300" />
                    </div>
                </CardHeader>
                <CardContent class="space-y-3 p-4">
                    <div v-if="topIngredients.length === 0" class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">Chưa đủ dữ liệu để phân tích nhu cầu.</div>
                    <div v-for="(item, index) in topIngredients" :key="item.ingredient_id" class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-xs font-bold text-violet-300">{{ index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <p class="truncate font-bold text-foreground">{{ item.name }}</p>
                                <p class="shrink-0 font-bold text-foreground">{{ formatQuantity(item.total_quantity) }} {{ item.unit_symbol }}</p>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-400" :style="{ width: barHeight(item.total_quantity, maxIngredientQuantity) }" />
                            </div>
                            <p class="mt-1 text-[10px] text-muted-foreground">{{ item.request_count }} đơn · {{ formatCurrency(item.total_value) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base"><BarChart3 class="h-5 w-5 text-sky-300" /> Báo cáo phân bổ theo chi nhánh</CardTitle>
                        <CardDescription class="mt-1 text-xs">So sánh nhu cầu cấp phát trong 7 ngày gần nhất, không bao gồm thao tác xử lý.</CardDescription>
                    </div>
                    <span class="text-[11px] text-muted-foreground">{{ branches.length }} chi nhánh đang hoạt động</span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="branchReport.length === 0" class="p-8 text-center text-xs text-muted-foreground">Chưa có dữ liệu yêu cầu theo chi nhánh.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="p-4 pl-5">Chi nhánh</th>
                                <th class="p-4">Tỷ trọng</th>
                                <th class="p-4 text-right">Số đơn</th>
                                <th class="p-4 text-right">Số lượng</th>
                                <th class="p-4 text-right">Giá trị yêu cầu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="branch in branchReport" :key="branch.id" class="transition hover:bg-muted/20">
                                <td class="p-4 pl-5 font-semibold text-foreground">{{ branch.name }}</td>
                                <td class="w-[34%] p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div class="h-full rounded-full bg-sky-400" :style="{ width: barHeight(branch.requests, maxBranchRequests) }" />
                                        </div>
                                        <span class="w-12 text-right font-semibold text-sky-300">{{ formatPercent(branch.share) }}</span>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-bold text-foreground">{{ branch.requests }}</td>
                                <td class="p-4 text-right text-muted-foreground">{{ formatQuantity(branch.items) }}</td>
                                <td class="p-4 text-right font-semibold text-emerald-300">{{ formatCurrency(branch.value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card class="border-rose-500/20 shadow-sm">
            <CardHeader class="border-b border-rose-500/15 bg-rose-950/10 py-4">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base"><ShieldAlert class="h-5 w-5 text-rose-300" /> Khuyến nghị tồn kho 7 ngày tới</CardTitle>
                        <CardDescription class="mt-1 text-xs">Dự báo từ nhu cầu 28 ngày, đơn đang mở và mức tồn tối thiểu.</CardDescription>
                    </div>
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-300"><Sparkles class="h-3.5 w-3.5" /> Khuyến nghị tham khảo</span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="recommendations.length === 0" class="p-8 text-center text-xs text-muted-foreground">Tồn kho hiện đáp ứng được nhu cầu dự kiến.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="p-4 pl-5">Nguyên liệu</th>
                                <th class="p-4 text-right">Tồn hiện tại</th>
                                <th class="p-4 text-right">Đơn đang mở</th>
                                <th class="p-4 text-right">Dự báo 7 ngày</th>
                                <th class="p-4 text-right">Nên nhập thêm</th>
                                <th class="p-4">Đánh giá</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="item in recommendations" :key="item.ingredient_id" class="transition hover:bg-rose-950/10">
                                <td class="p-4 pl-5">
                                    <p class="font-bold text-foreground">{{ item.name }}</p>
                                    <p class="mt-1 text-[10px] text-muted-foreground">{{ item.sku || 'Chưa có SKU' }} · {{ item.coverage_days === null ? 'Chưa đủ lịch sử' : `Đủ khoảng ${item.coverage_days} ngày` }}</p>
                                </td>
                                <td class="p-4 text-right font-semibold text-foreground">{{ formatQuantity(item.current_stock) }} {{ item.unit_symbol }}</td>
                                <td class="p-4 text-right text-amber-300">{{ formatQuantity(item.open_quantity) }} {{ item.unit_symbol }}</td>
                                <td class="p-4 text-right text-indigo-300">{{ formatQuantity(item.forecast_7d) }} {{ item.unit_symbol }}</td>
                                <td class="p-4 text-right">
                                    <p class="font-bold text-rose-300">{{ formatQuantity(item.recommended_quantity) }} {{ item.unit_symbol }}</p>
                                    <p class="mt-1 text-[10px] text-muted-foreground">{{ formatCurrency(item.estimated_cost) }}</p>
                                </td>
                                <td class="max-w-[280px] p-4">
                                    <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-semibold" :class="priorityMeta(item.priority).class">{{ priorityMeta(item.priority).label }}</span>
                                    <p class="mt-2 text-[10px] leading-relaxed text-muted-foreground">{{ item.advice }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <Card class="border-border bg-muted/10"><CardContent class="p-4"><p class="text-[11px] text-muted-foreground">Vị trí hoạt động</p><p class="mt-1 text-xl font-bold text-foreground">{{ inventory.location_count ?? 0 }}</p></CardContent></Card>
            <Card class="border-border bg-muted/10"><CardContent class="p-4"><p class="text-[11px] text-muted-foreground">Tồn dưới ngưỡng</p><p class="mt-1 text-xl font-bold text-amber-300">{{ inventory.low_stock_count ?? 0 }}</p></CardContent></Card>
            <Card class="border-border bg-muted/10"><CardContent class="p-4"><p class="text-[11px] text-muted-foreground">Lô bị khóa / thu hồi</p><p class="mt-1 text-xl font-bold text-rose-300">{{ inventory.locked_batch_count ?? 0 }}</p></CardContent></Card>
            <Card class="border-border bg-muted/10"><CardContent class="p-4"><p class="text-[11px] text-muted-foreground">Chênh lệch tiếp nhận</p><p class="mt-1 text-xl font-bold text-orange-300">{{ formatQuantity(receiving.discrepancy_quantity) }}</p></CardContent></Card>
        </section>

        <p class="flex items-center justify-center gap-2 pb-2 text-center text-[11px] text-muted-foreground">
            <Sparkles class="h-3.5 w-3.5 text-indigo-300" /> Báo cáo mang tính tham khảo quản trị; các khuyến nghị cần được xác nhận trước khi ra quyết định nhập hàng.
        </p>
    </div>
</template>
