<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Building2,
    CheckCircle2,
    PackageSearch,
    ShieldAlert,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Scorecard = {
    branch_id: number;
    branch_name: string;
    revenue: number;
    target_revenue: number;
    target_completion_percent: number;
    cash_discrepancy: number;
    low_stock_count: number;
    open_infringements: number;
    status: 'healthy' | 'warning';
};

const props = defineProps<{
    scorecards: Scorecard[];
    summary: {
        total_branches: number;
        total_revenue: number;
        total_open_infringements: number;
        total_low_stock: number;
    };
}>();

const currency = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

const summaryCards = computed(() => [
    {
        label: 'Chi nhánh đang theo dõi',
        value: props.summary.total_branches,
        icon: Building2,
        tone: 'indigo',
    },
    {
        label: 'Doanh thu tháng này',
        value: currency.format(props.summary.total_revenue),
        icon: TrendingUp,
        tone: 'emerald',
    },
    {
        label: 'Vi phạm đang mở',
        value: props.summary.total_open_infringements,
        icon: ShieldAlert,
        tone: 'rose',
    },
    {
        label: 'Mặt hàng cần bổ sung',
        value: props.summary.total_low_stock,
        icon: PackageSearch,
        tone: 'amber',
    },
]);

function toneClasses(tone: string) {
    return (
        {
            indigo: 'border-indigo-100 dark:border-indigo-950/30',
            emerald: 'border-emerald-100 dark:border-emerald-950/30',
            rose: 'border-rose-100 dark:border-rose-950/30',
            amber: 'border-amber-100 dark:border-amber-950/30',
        }[tone] ?? 'border-border'
    );
}

function iconClasses(tone: string) {
    return (
        {
            indigo: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400',
            emerald:
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
            rose: 'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400',
            amber: 'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400',
        }[tone] ?? 'bg-muted text-muted-foreground'
    );
}
</script>

<template>
    <Head title="Trung tâm điều hành chuỗi" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-950 p-6 text-white shadow-xl sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="rounded-2xl border border-indigo-300/20 bg-indigo-400/15 p-3"
                >
                    <BarChart3 class="size-7 text-indigo-200" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Trung tâm điều hành chuỗi
                    </h1>
                    <p class="mt-1 text-sm text-indigo-200/80">
                        Theo dõi sức khỏe, doanh thu, tiền mặt, tồn kho và vi
                        phạm của toàn bộ chi nhánh.
                    </p>
                </div>
            </div>
            <div class="text-right text-xs text-indigo-200/70">
                Cập nhật theo tháng hiện tại
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card
                v-for="card in summaryCards"
                :key="card.label"
                :class="[
                    'shadow-sm transition hover:-translate-y-0.5',
                    toneClasses(card.tone),
                ]"
            >
                <CardContent
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p
                            class="mt-1 text-xl font-black text-foreground tabular-nums"
                        >
                            {{ card.value }}
                        </p>
                    </div>
                    <div :class="['rounded-xl p-2.5', iconClasses(card.tone)]">
                        <component :is="card.icon" class="size-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="overflow-hidden shadow-sm">
            <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/50">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Building2
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                    Sức khỏe từng chi nhánh
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="scorecards.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-16 text-sm text-muted-foreground"
                >
                    <Building2 class="size-8 opacity-50" />
                    Chưa có chi nhánh để theo dõi.
                </div>

                <div v-else class="grid gap-3 p-4 lg:grid-cols-2">
                    <div
                        v-for="branch in scorecards"
                        :key="branch.branch_id"
                        class="rounded-xl border border-border/70 bg-background p-4 transition hover:border-indigo-300/70 hover:shadow-sm dark:bg-slate-950/20"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-foreground">
                                    {{ branch.branch_name }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Doanh thu
                                    {{ currency.format(branch.revenue) }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-bold',
                                    branch.status === 'healthy'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-400'
                                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-400',
                                ]"
                            >
                                <CheckCircle2
                                    v-if="branch.status === 'healthy'"
                                    class="size-3"
                                />
                                <AlertTriangle v-else class="size-3" />
                                {{
                                    branch.status === 'healthy'
                                        ? 'Ổn định'
                                        : 'Cần chú ý'
                                }}
                            </span>
                        </div>

                        <div class="mt-4">
                            <div
                                class="mb-1 flex justify-between text-[11px] text-muted-foreground"
                            >
                                <span>Hoàn thành mục tiêu doanh thu</span>
                                <span class="font-bold text-foreground"
                                    >{{
                                        branch.target_completion_percent
                                    }}%</span
                                >
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{
                                        width: `${Math.min(branch.target_completion_percent, 100)}%`,
                                    }"
                                />
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-lg bg-muted/60 p-2">
                                <p class="text-muted-foreground">
                                    Chênh lệch két
                                </p>
                                <p
                                    class="mt-1 font-bold tabular-nums"
                                    :class="
                                        branch.cash_discrepancy === 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                >
                                    {{
                                        currency.format(branch.cash_discrepancy)
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/60 p-2">
                                <p class="text-muted-foreground">
                                    Sắp hết hàng
                                </p>
                                <p class="mt-1 font-bold tabular-nums">
                                    {{ branch.low_stock_count }}
                                </p>
                            </div>
                            <div class="rounded-lg bg-muted/60 p-2">
                                <p class="text-muted-foreground">Vi phạm mở</p>
                                <p class="mt-1 font-bold tabular-nums">
                                    {{ branch.open_infringements }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
