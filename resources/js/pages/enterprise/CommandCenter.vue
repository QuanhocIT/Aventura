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
import DashboardKpiCard from '@/components/dashboard/DashboardKpiCard.vue';
import DashboardListCard from '@/components/dashboard/DashboardListCard.vue';
import DashboardShell from '@/components/dashboard/DashboardShell.vue';
import DashboardSummaryCard from '@/components/dashboard/DashboardSummaryCard.vue';
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

</script>

<template>
    <Head title="Trung tâm điều hành chuỗi" />

    <DashboardShell
        title="Trung tâm điều hành chuỗi"
        eyebrow="TỔNG QUAN CHUỖI"
        description="Theo dõi sức khỏe, doanh thu, tiền mặt, tồn kho và vi phạm của toàn bộ chi nhánh."
        :icon="BarChart3"
    >
        <template #actions>
            <span class="dashboard-filterbar__period">
                Cập nhật theo tháng hiện tại
            </span>
        </template>

        <section class="dashboard-kpi-grid">
            <DashboardKpiCard
                v-for="card in summaryCards"
                :key="card.label"
                :label="card.label"
                :value="card.value"
                :icon="card.icon"
                :tone="card.tone as any"
            />
        </section>

        <section class="dashboard-grid">
            <DashboardListCard
                class="col-span-12 lg:col-span-8"
                title="Sức khỏe từng chi nhánh"
                description="Tối đa 10 chi nhánh quan trọng nhất; danh sách dài được giới hạn trong vùng cuộn."
                :icon="Building2"
                :empty="scorecards.length === 0"
                empty-title="Chưa có chi nhánh để theo dõi"
            >
                <div class="grid gap-3 p-4 md:grid-cols-2">
                    <div
                        v-for="branch in scorecards.slice(0, 10)"
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

                <template #footer>
                    <span v-if="scorecards.length > 10" class="text-xs text-muted-foreground">
                        Đang hiển thị 10/{{ scorecards.length }} chi nhánh. Cuộn để xem danh sách đầy đủ.
                    </span>
                </template>
            </DashboardListCard>

            <DashboardSummaryCard
                class="col-span-12 lg:col-span-4"
                title="Tóm tắt chuỗi"
                description="Các chỉ số tổng hợp trong phạm vi hiện tại."
                :icon="TrendingUp"
            >
                <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <div class="rounded-xl bg-muted/50 p-3">
                        <p class="dashboard-card-label">Doanh thu tháng này</p>
                        <p class="mt-1 text-lg font-black text-emerald-600 dark:text-emerald-300">
                            {{ currency.format(summary.total_revenue) }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/50 p-3">
                        <p class="dashboard-card-label">Vi phạm đang mở</p>
                        <p class="mt-1 text-lg font-black text-rose-600 dark:text-rose-300">
                            {{ summary.total_open_infringements }} hồ sơ
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/50 p-3">
                        <p class="dashboard-card-label">Mặt hàng cần bổ sung</p>
                        <p class="mt-1 text-lg font-black text-amber-600 dark:text-amber-300">
                            {{ summary.total_low_stock }} mặt hàng
                        </p>
                    </div>
                </div>
            </DashboardSummaryCard>
        </section>
    </DashboardShell>
</template>
