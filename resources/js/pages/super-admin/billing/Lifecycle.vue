<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart2,
    LayoutGrid,
    TrendingUp,
    Users,
    Brain,
    Sparkles,
    AlertTriangle,
    DollarSign,
    Activity,
    Percent,
    ArrowRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    PageHeader,
    StatCard,
    StatusBadge,
    LedIndicator,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface AvgLifetime {
    plan_name: string;
    plan_code: string;
    avg_days: number;
    avg_months: number;
    subscription_count: number;
    avg_price: number;
    estimated_ltv: number;
}

interface RenewalRate {
    label: string;
    month: string;
    due: number;
    renewed: number;
    rate: number | null;
}

interface PlanStat {
    plan_name: string;
    plan_code: string;
    plan_price: number;
    active_count: number;
}

interface Funnel {
    total_registered: number;
    trial_completed: number;
    active_paid: number;
    renewed_at_least_once: number;
}

const props = defineProps<{
    avg_lifetimes: AvgLifetime[];
    renewal_rates: RenewalRate[];
    plan_stats: PlanStat[];
    funnel: Funnel;
}>();

const maxLtv = computed(() =>
    Math.max(...props.avg_lifetimes.map((l) => l.estimated_ltv), 1),
);
const maxPlanCount = computed(() =>
    Math.max(...props.plan_stats.map((p) => p.active_count), 1),
);

const avgLtv = computed(() => {
    if (props.avg_lifetimes.length === 0) {
        return 0;
    }

    const sum = props.avg_lifetimes.reduce(
        (acc, curr) => acc + curr.estimated_ltv,
        0,
    );

    return Math.round(sum / props.avg_lifetimes.length);
});

const targetCacLimit = computed(() => {
    return Math.round(avgLtv.value / 3);
});

const trialToPaidRate = computed(() => {
    if (props.funnel.total_registered === 0) {
        return 0;
    }

    return Math.round(
        (props.funnel.active_paid / props.funnel.total_registered) * 100,
    );
});

const renewalChartMax = computed(() => {
    const vals = props.renewal_rates.flatMap((r) => [r.due, r.renewed]);

    return Math.max(...vals, 1);
});

// SVG bar chart for renewal rates
const BAR_W = 600;
const BAR_H = 150;
const barCount = computed(() => props.renewal_rates.length);
const barGroupW = computed(() =>
    barCount.value > 0 ? BAR_W / barCount.value : 60,
);
const barPad = 8;

function barX(i: number): number {
    return i * barGroupW.value + barPad / 2;
}
function barWidth(): number {
    return (barGroupW.value - barPad) / 2;
}
function barH(val: number): number {
    return (val / renewalChartMax.value) * BAR_H;
}
function barY(val: number): number {
    return BAR_H - barH(val);
}

function formatVnd(val: number): string {
    if (val >= 1_000_000) {
        return (val / 1_000_000).toFixed(1) + 'M';
    }

    if (val >= 1_000) {
        return (val / 1_000).toFixed(0) + 'K';
    }

    return String(Math.round(val));
}
</script>

<template>
    <Head title="Subscription Lifecycle" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Subscription Lifecycle"
            subtitle="Phân tích vòng đời, LTV và tỷ lệ gia hạn."
            :icon="LayoutGrid"
        >
            <template #actions>
                <Link href="/super-admin/billing/analytics">
                    <Button
                        variant="outline"
                        size="sm"
                        class="shadow-3xs h-9 cursor-pointer rounded-xl border-border/80 px-4 text-xs font-bold"
                    >
                        <ArrowLeft class="mr-1.5 size-4" /> Billing Analytics
                    </Button>
                </Link>
            </template>
        </PageHeader>

        <!-- ── AI SUBSCRIPTION ADVISOR ── -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardContent
                class="flex flex-col items-start justify-between gap-4 p-5 md:flex-row md:items-center"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-600"
                    >
                        <Brain class="size-5" />
                    </div>
                    <div>
                        <h4
                            class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            <Sparkles
                                class="size-3.5 animate-pulse text-indigo-500"
                            />
                            AI Subscription Advisor
                        </h4>
                        <p
                            class="mt-1 text-[11px] leading-relaxed font-semibold text-muted-foreground"
                        >
                            <span v-if="funnel.renewed_at_least_once === 0">
                                Cảnh báo: Tỷ lệ khách hàng gia hạn dịch vụ từ kỳ
                                tiếp theo hiện đạt 0%. Hệ thống đề xuất thiết
                                lập chiến dịch email tự động nhắc hạn kèm mã
                                giảm giá 10% để duy trì lòng trung thành của
                                nhóm đối tác này.
                            </span>
                            <span
                                v-else-if="
                                    funnel.active_paid <
                                    funnel.total_registered * 0.3
                                "
                            >
                                Tỷ lệ chuyển đổi dùng thử sang trả phí ở mức khá
                                thấp ({{
                                    (
                                        (funnel.active_paid /
                                            (funnel.total_registered || 1)) *
                                        100
                                    ).toFixed(1)
                                }}%). Hãy tối ưu hóa tài liệu hướng dẫn
                                (Onboarding) để giúp chủ nhà hàng làm quen nhanh
                                hơn với POS.
                            </span>
                            <span v-else>
                                Các chỉ số chuyển đổi phễu đang ở trạng thái
                                tốt. LTV bình quân ước tính đạt đỉnh ổn định. Đề
                                xuất: Khai thác thêm dịch vụ gia tăng để tăng
                                doanh thu ARPU bình quân.
                            </span>
                        </p>
                    </div>
                </div>
                <div
                    class="flex shrink-0 items-center justify-center gap-2 self-stretch rounded-xl border border-border/30 bg-muted/20 px-3 py-2 font-mono text-[10px] font-black tracking-wider text-muted-foreground uppercase md:self-auto"
                >
                    <AlertTriangle class="size-3.5 text-amber-500" />
                    <span>LTV = Giá TB x Kỳ hạn TB</span>
                </div>
            </CardContent>
        </Card>

        <!-- Conversion Funnel -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle class="flex items-center gap-2 text-sm font-bold">
                    <TrendingUp class="size-4 text-violet-500" />
                    Phễu Chuyển Đổi Doanh Nghiệp (Conversion Funnel)
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-6">
                <div class="relative grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Step 1: Registered -->
                    <div
                        class="shadow-3xs relative flex flex-col items-center justify-between rounded-2xl border border-border/30 bg-muted/20 p-4 transition-all duration-300 hover:shadow-2xs"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full border border-slate-500/20 bg-slate-500/10 text-slate-500"
                        >
                            <Users class="size-5" />
                        </div>
                        <h4
                            class="mt-3 text-xs font-black tracking-wider text-muted-foreground uppercase"
                        >
                            Đã đăng ký
                        </h4>
                        <div
                            class="mt-1 font-mono text-3xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                        >
                            {{ funnel.total_registered }}
                        </div>
                        <span
                            class="mt-1 text-[10px] font-bold text-slate-400 dark:text-slate-500"
                            >100% người dùng hệ thống</span
                        >

                        <!-- Connector Arrow pointing right on md screen -->
                        <div
                            class="shadow-3xs absolute top-1/2 -right-3.5 z-10 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full border border-border/80 bg-background text-muted-foreground md:flex"
                        >
                            <ArrowRight class="size-3.5" />
                        </div>
                    </div>

                    <!-- Step 2: Paid -->
                    <div
                        class="shadow-3xs relative flex flex-col items-center justify-between rounded-2xl border border-border/30 bg-muted/20 p-4 transition-all duration-300 hover:shadow-2xs"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full border border-violet-500/20 bg-violet-500/10 text-violet-500"
                        >
                            <DollarSign class="size-5" />
                        </div>
                        <h4
                            class="mt-3 text-xs font-black tracking-wider text-muted-foreground uppercase"
                        >
                            Dùng trả phí
                        </h4>
                        <div
                            class="mt-1 font-mono text-3xl font-black tracking-tight text-violet-500"
                        >
                            {{ funnel.active_paid }}
                        </div>
                        <!-- Conversion Rate -->
                        <Badge
                            class="mt-1 rounded-sm border-none bg-violet-500/10 text-[10px] font-black text-violet-600"
                        >
                            {{
                                funnel.total_registered > 0
                                    ? (
                                          (funnel.active_paid /
                                              funnel.total_registered) *
                                          100
                                      ).toFixed(1)
                                    : '0'
                            }}% chuyển đổi
                        </Badge>

                        <!-- Connector Arrow pointing right on md screen -->
                        <div
                            class="shadow-3xs absolute top-1/2 -right-3.5 z-10 hidden size-7 -translate-y-1/2 items-center justify-center rounded-full border border-border/80 bg-background text-muted-foreground md:flex"
                        >
                            <ArrowRight class="size-3.5" />
                        </div>
                    </div>

                    <!-- Step 3: Renewed -->
                    <div
                        class="shadow-3xs relative flex flex-col items-center justify-between rounded-2xl border border-border/30 bg-muted/20 p-4 transition-all duration-300 hover:shadow-2xs"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                        >
                            <Activity class="size-5" />
                        </div>
                        <h4
                            class="mt-3 text-xs font-black tracking-wider text-muted-foreground uppercase"
                        >
                            Gia hạn ≥1 lần
                        </h4>
                        <div
                            class="mt-1 font-mono text-3xl font-black tracking-tight text-emerald-500"
                        >
                            {{ funnel.renewed_at_least_once }}
                        </div>
                        <!-- Renewal Rate relative to registered -->
                        <Badge
                            class="mt-1 rounded-sm border-none bg-emerald-500/10 text-[10px] font-black text-emerald-600"
                        >
                            {{
                                funnel.total_registered > 0
                                    ? (
                                          (funnel.renewed_at_least_once /
                                              funnel.total_registered) *
                                          100
                                      ).toFixed(1)
                                    : '0'
                            }}% gia hạn ròng
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Average Lifetime & LTV per Plan -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <Users class="size-4 text-sky-500" />
                        Thời Gian Sống & LTV Theo Gói
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex-grow pt-5">
                    <div
                        v-if="avg_lifetimes.length === 0"
                        class="py-12 text-center text-xs font-semibold text-muted-foreground"
                    >
                        Chưa đủ dữ liệu (yêu cầu ghi nhận các chu kỳ đã kết
                        thúc).
                    </div>
                    <div v-else class="space-y-5">
                        <div
                            v-for="lt in avg_lifetimes"
                            :key="lt.plan_code"
                            class="space-y-2"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                <span>{{ lt.plan_name }}</span>
                                <span
                                    class="font-mono font-semibold text-muted-foreground"
                                    >{{ lt.avg_months }} tháng TB ·
                                    {{ lt.subscription_count }} sub</span
                                >
                            </div>
                            <!-- LTV bar with custom color mappings -->
                            <div class="flex items-center gap-3">
                                <div
                                    class="shadow-3xs h-3 flex-1 overflow-hidden rounded-full border border-border/30 bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r transition-all duration-700"
                                        :class="[
                                            lt.plan_code === 'free'
                                                ? 'from-slate-400 to-slate-500 shadow-slate-500/10'
                                                : '',
                                            lt.plan_code === 'starter'
                                                ? 'from-sky-400 to-blue-500 shadow-blue-500/10'
                                                : '',
                                            lt.plan_code === 'pro'
                                                ? 'from-amber-400 to-orange-500 shadow-orange-500/10'
                                                : '',
                                            lt.plan_code === 'enterprise'
                                                ? 'from-violet-400 to-indigo-500 shadow-indigo-500/10'
                                                : '',
                                        ]"
                                        :style="{
                                            width: `${(lt.estimated_ltv / maxLtv) * 100}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="w-24 text-right font-mono text-xs font-black text-indigo-500"
                                >
                                    LTV {{ formatVnd(lt.estimated_ltv) }}₫
                                </span>
                            </div>
                            <p
                                class="font-mono text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                Giá TB {{ formatVnd(lt.avg_price) }}₫/tháng
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Monthly Renewal Rate Chart -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <BarChart2 class="size-4 text-emerald-500" />
                        Tỷ Lệ Gia Hạn 6 Tháng
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex-grow pt-5">
                    <div
                        v-if="renewal_rates.length === 0"
                        class="py-12 text-center text-xs font-semibold text-muted-foreground"
                    >
                        Chưa có dữ liệu gia hạn của các tháng trước.
                    </div>
                    <div v-else class="space-y-4">
                        <!-- SVG grouped bar chart with gradients -->
                        <div
                            class="relative rounded-xl border border-border/20 bg-muted/10 p-3"
                        >
                            <svg
                                :viewBox="`0 0 ${BAR_W} ${BAR_H + 40}`"
                                class="w-full overflow-visible"
                                style="height: 180px"
                            >
                                <defs>
                                    <linearGradient
                                        id="renewalEmeraldGrad"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#10b981"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="#059669"
                                        />
                                    </linearGradient>
                                </defs>

                                <!-- Background dashed lines -->
                                <line
                                    x1="0"
                                    y1="30"
                                    :x2="BAR_W"
                                    y2="30"
                                    stroke="rgba(255,255,255,0.03)"
                                    stroke-dasharray="2 2"
                                />
                                <line
                                    x1="0"
                                    y1="75"
                                    :x2="BAR_W"
                                    y2="75"
                                    stroke="rgba(255,255,255,0.03)"
                                    stroke-dasharray="2 2"
                                />
                                <line
                                    x1="0"
                                    y1="120"
                                    :x2="BAR_W"
                                    y2="120"
                                    stroke="rgba(255,255,255,0.03)"
                                    stroke-dasharray="2 2"
                                />

                                <g
                                    v-for="(rate, i) in renewal_rates"
                                    :key="rate.month"
                                >
                                    <!-- Due bar (gray) -->
                                    <rect
                                        :x="barX(i)"
                                        :y="barY(rate.due)"
                                        :width="barWidth()"
                                        :height="barH(rate.due)"
                                        fill="currentColor"
                                        fill-opacity="0.08"
                                        rx="2"
                                    />
                                    <!-- Renewed bar (emerald) -->
                                    <rect
                                        :x="barX(i) + barWidth()"
                                        :y="barY(rate.renewed)"
                                        :width="barWidth()"
                                        :height="barH(rate.renewed)"
                                        fill="url(#renewalEmeraldGrad)"
                                        rx="2"
                                    />
                                    <!-- Label -->
                                    <text
                                        :x="barX(i) + barGroupW / 2"
                                        :y="BAR_H + 18"
                                        text-anchor="middle"
                                        font-size="10"
                                        fill="currentColor"
                                        fill-opacity="0.6"
                                        font-weight="bold"
                                    >
                                        {{ rate.label }}
                                    </text>
                                    <!-- Rate % -->
                                    <text
                                        v-if="rate.rate !== null"
                                        :x="barX(i) + barGroupW / 2"
                                        :y="BAR_H + 32"
                                        text-anchor="middle"
                                        font-size="9"
                                        :fill="
                                            rate.rate >= 80
                                                ? '#10b981'
                                                : rate.rate >= 60
                                                  ? '#f59e0b'
                                                  : '#ef4444'
                                        "
                                        font-weight="black"
                                    >
                                        {{ rate.rate }}%
                                    </text>
                                </g>
                            </svg>
                        </div>
                        <!-- Legend -->
                        <div
                            class="mt-1 flex justify-center gap-4 text-[10px] font-bold text-muted-foreground"
                        >
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="inline-block size-2 rounded-full border border-border/50 bg-muted"
                                />
                                Đến hạn gia hạn
                            </span>
                            <span class="flex items-center gap-1.5">
                                <span
                                    class="inline-block size-2 rounded-full bg-emerald-500"
                                />
                                Đã gia hạn
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- SaaS LTV/CAC Assessment & Solutions -->
            <Card
                class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                    <CardTitle
                        class="flex items-center gap-2 text-sm font-bold"
                    >
                        <Sparkles class="size-4 animate-pulse text-amber-500" />
                        Đo lường & Giải pháp SaaS
                    </CardTitle>
                </CardHeader>
                <CardContent
                    class="flex flex-grow flex-col justify-between gap-4 pt-5"
                >
                    <div class="space-y-3.5">
                        <!-- Key SaaS Stats -->
                        <div class="grid grid-cols-2 gap-3">
                            <div
                                class="rounded-xl border border-border/20 bg-muted/15 p-2.5 text-center"
                            >
                                <span
                                    class="block text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                                    >LTV Trung Bình</span
                                >
                                <span
                                    class="mt-1 block font-mono text-base font-black text-slate-800 dark:text-slate-100"
                                    >{{ formatVnd(avgLtv) }}₫</span
                                >
                            </div>
                            <div
                                class="rounded-xl border border-border/20 bg-muted/15 p-2.5 text-center"
                            >
                                <span
                                    class="block text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                                    >CAC Mục tiêu (Max)</span
                                >
                                <span
                                    class="mt-1 block font-mono text-base font-black text-indigo-500"
                                    >{{ formatVnd(targetCacLimit) }}₫</span
                                >
                            </div>
                        </div>

                        <!-- Solutions / Business Actions -->
                        <div
                            class="space-y-2 rounded-xl border border-amber-500/10 bg-amber-500/[0.03] p-3"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400"
                            >
                                <Brain class="size-3.5" />
                                Chiến lược tối ưu hóa LTV:CAC
                            </div>
                            <p
                                class="text-[11px] leading-relaxed font-semibold text-muted-foreground"
                            >
                                <span v-if="avgLtv === 0">
                                    Hệ thống chưa ghi nhận vòng đời hoàn tất của
                                    bất kỳ thuê bao nào để đưa ra khuyến nghị
                                    giá trị trọn đời.
                                </span>
                                <span v-else>
                                    Đảm bảo ngân sách chiêu mộ đối tác mới (CAC)
                                    luôn dưới
                                    <strong
                                        >{{
                                            formatVnd(targetCacLimit)
                                        }}₫</strong
                                    >
                                    (Tỷ lệ LTV:CAC &ge; 3:1). Khuyên dùng: Triển
                                    khai gói thanh toán nhiều tháng hoặc tặng
                                    thêm 1 tháng miễn phí khi mua gói Pro để
                                    tăng thời gian sống trung bình (LTV).
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Trust Level Indicator -->
                    <div
                        class="flex items-center justify-between rounded-xl border border-border/30 bg-muted/20 px-3 py-2 text-[10px] font-bold text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><Percent class="size-3 text-indigo-500" /> Tỷ lệ
                            chuyển đổi dùng thử</span
                        >
                        <span class="font-mono font-extrabold text-indigo-500"
                            >{{ trialToPaidRate }}%</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Plan Distribution -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle class="text-sm font-bold"
                    >Phân Phối Gói Hiện Tại</CardTitle
                >
            </CardHeader>
            <CardContent class="pt-4">
                <div
                    v-if="plan_stats.length === 0"
                    class="py-12 text-center text-xs font-semibold text-muted-foreground"
                >
                    Chưa có dữ liệu gói dịch vụ đang hoạt động.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr
                                class="border-b border-border/60 pb-3 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="pb-3 text-left font-black">Gói</th>
                                <th class="pb-3 text-right font-black">
                                    Giá/tháng
                                </th>
                                <th class="pb-3 text-right font-black">
                                    Nhà hàng đang dùng
                                </th>
                                <th class="pb-3 pl-6 text-left font-black">
                                    Phân phối tỷ lệ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr
                                v-for="plan in plan_stats"
                                :key="plan.plan_code"
                                class="text-slate-700 transition-all hover:bg-muted/30 dark:text-slate-300"
                            >
                                <td
                                    class="py-3.5 pr-4 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ plan.plan_name }}
                                </td>
                                <td
                                    class="py-3.5 pr-4 text-right font-mono font-bold text-slate-500 dark:text-slate-400"
                                >
                                    {{ formatVnd(plan.plan_price) }}₫
                                </td>
                                <td
                                    class="py-3.5 pr-4 text-right font-mono text-xs font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{ plan.active_count }}
                                </td>
                                <td class="py-3.5 pl-6">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="shadow-3xs h-2.5 w-44 overflow-hidden rounded-full border border-border/30 bg-muted"
                                        >
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-violet-500 to-pink-500 shadow-violet-500/10 transition-all duration-700"
                                                :style="{
                                                    width: `${(plan.active_count / maxPlanCount) * 100}%`,
                                                }"
                                            />
                                        </div>
                                        <span
                                            class="w-10 font-mono text-xs font-black text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                maxPlanCount > 0
                                                    ? (
                                                          (plan.active_count /
                                                              maxPlanCount) *
                                                          100
                                                      ).toFixed(0)
                                                    : 0
                                            }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
