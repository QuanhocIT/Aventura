<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart2, LayoutGrid, TrendingUp, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
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

const maxLtv = computed(() => Math.max(...props.avg_lifetimes.map(l => l.estimated_ltv), 1));
const maxPlanCount = computed(() => Math.max(...props.plan_stats.map(p => p.active_count), 1));

const renewalChartMax = computed(() => {
    const vals = props.renewal_rates.flatMap(r => [r.due, r.renewed]);
    return Math.max(...vals, 1);
});

// SVG bar chart for renewal rates
const BAR_W = 600;
const BAR_H = 150;
const barCount = computed(() => props.renewal_rates.length);
const barGroupW = computed(() => barCount.value > 0 ? (BAR_W / barCount.value) : 60);
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
    if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + 'M';
    if (val >= 1_000) return (val / 1_000).toFixed(0) + 'K';
    return String(Math.round(val));
}
</script>

<template>
    <Head title="Subscription Lifecycle" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/super-admin/billing/analytics">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="mr-1.5 size-4" /> Analytics
                </Button>
            </Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <LayoutGrid class="size-6 text-violet-600" />
                    Subscription Lifecycle
                </h1>
                <p class="text-sm text-muted-foreground">Phân tích vòng đời, LTV và tỷ lệ gia hạn.</p>
            </div>
        </div>

        <!-- Conversion Funnel -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base">
                    <TrendingUp class="size-4 text-violet-500" />
                    Phễu Chuyển Đổi
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-4 overflow-x-auto pb-2">
                    <div
                        v-for="(step, i) in [
                            { label: 'Đã đăng ký', value: funnel.total_registered, color: 'bg-slate-400' },
                            { label: 'Dùng trả phí', value: funnel.active_paid, color: 'bg-violet-500' },
                            { label: 'Gia hạn ≥1 lần', value: funnel.renewed_at_least_once, color: 'bg-emerald-500' },
                        ]"
                        :key="i"
                        class="flex flex-col items-center gap-2 min-w-[120px]"
                    >
                        <span class="text-2xl font-bold">{{ step.value }}</span>
                        <div
                            class="w-20 rounded-t-lg transition-all duration-700"
                            :class="step.color"
                            :style="{ height: `${Math.max((step.value / (funnel.total_registered || 1)) * 120, 12)}px` }"
                        />
                        <p class="text-xs text-muted-foreground text-center">{{ step.label }}</p>
                        <p v-if="i > 0" class="text-xs text-muted-foreground">
                            {{ funnel.total_registered > 0 ? ((step.value / funnel.total_registered) * 100).toFixed(1) : '0' }}%
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Average Lifetime & LTV per Plan -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Users class="size-4 text-sky-500" />
                        Thời Gian Sống & LTV Theo Gói
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="avg_lifetimes.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        Chưa đủ dữ liệu (cần subscription đã kết thúc).
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="lt in avg_lifetimes" :key="lt.plan_code" class="space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ lt.plan_name }}</span>
                                <div class="text-right text-xs text-muted-foreground">
                                    <span>{{ lt.avg_months }} tháng TB · {{ lt.subscription_count }} sub</span>
                                </div>
                            </div>
                            <!-- LTV bar -->
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 flex-1 rounded-full bg-muted overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-gradient-to-r from-violet-500 to-sky-500 transition-all duration-700"
                                        :style="{ width: `${(lt.estimated_ltv / maxLtv) * 100}%` }"
                                    />
                                </div>
                                <span class="text-xs font-semibold w-20 text-right text-violet-600">
                                    LTV {{ formatVnd(lt.estimated_ltv) }}₫
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">Giá TB {{ formatVnd(lt.avg_price) }}₫/tháng</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Monthly Renewal Rate Chart -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <BarChart2 class="size-4 text-emerald-500" />
                        Tỷ Lệ Gia Hạn 6 Tháng
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="renewal_rates.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                        Chưa có dữ liệu gia hạn.
                    </div>
                    <div v-else>
                        <!-- SVG grouped bar chart -->
                        <svg :viewBox="`0 0 ${BAR_W} ${BAR_H + 40}`" class="w-full" style="height: 180px;">
                            <g v-for="(rate, i) in renewal_rates" :key="rate.month">
                                <!-- Due bar (gray) -->
                                <rect
                                    :x="barX(i)"
                                    :y="barY(rate.due)"
                                    :width="barWidth()"
                                    :height="barH(rate.due)"
                                    fill="currentColor"
                                    fill-opacity="0.15"
                                    rx="3"
                                />
                                <!-- Renewed bar (emerald) -->
                                <rect
                                    :x="barX(i) + barWidth()"
                                    :y="barY(rate.renewed)"
                                    :width="barWidth()"
                                    :height="barH(rate.renewed)"
                                    fill="#10b981"
                                    rx="3"
                                />
                                <!-- Label -->
                                <text
                                    :x="barX(i) + barGroupW / 2"
                                    :y="BAR_H + 18"
                                    text-anchor="middle"
                                    font-size="10"
                                    fill="currentColor"
                                    fill-opacity="0.6"
                                >{{ rate.label }}</text>
                                <!-- Rate % -->
                                <text
                                    v-if="rate.rate !== null"
                                    :x="barX(i) + barGroupW / 2"
                                    :y="BAR_H + 32"
                                    text-anchor="middle"
                                    font-size="9"
                                    :fill="rate.rate >= 80 ? '#10b981' : rate.rate >= 60 ? '#f59e0b' : '#ef4444'"
                                    font-weight="bold"
                                >{{ rate.rate }}%</text>
                            </g>
                        </svg>
                        <!-- Legend -->
                        <div class="flex gap-4 text-xs text-muted-foreground mt-1">
                            <span class="flex items-center gap-1">
                                <span class="size-2.5 rounded-sm bg-muted-foreground/20 inline-block" /> Đến hạn gia hạn
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="size-2.5 rounded-sm bg-emerald-500 inline-block" /> Đã gia hạn
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Plan Distribution -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Phân Phối Gói Hiện Tại</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="plan_stats.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    Chưa có dữ liệu gói.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">Gói</th>
                                <th class="pb-3 text-right font-medium">Giá/tháng</th>
                                <th class="pb-3 text-right font-medium">Nhà hàng đang dùng</th>
                                <th class="pb-3 text-left font-medium">Phân phối</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="plan in plan_stats" :key="plan.plan_code" class="hover:bg-muted/30">
                                <td class="py-3 pr-4 font-medium">{{ plan.plan_name }}</td>
                                <td class="py-3 pr-4 text-right text-muted-foreground">{{ formatVnd(plan.plan_price) }}₫</td>
                                <td class="py-3 pr-4 text-right font-semibold">{{ plan.active_count }}</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 w-36 rounded-full bg-muted overflow-hidden">
                                            <div
                                                class="h-full rounded-full bg-gradient-to-r from-violet-500 to-sky-500 transition-all duration-700"
                                                :style="{ width: `${(plan.active_count / maxPlanCount) * 100}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-muted-foreground">
                                            {{ maxPlanCount > 0 ? ((plan.active_count / maxPlanCount) * 100).toFixed(0) : 0 }}%
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
