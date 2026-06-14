<script setup lang="ts">
import {
    ShoppingCart,
    Banknote,
    ArrowUp,
    ArrowDown,
    CheckCircle2,
    Package,
    Users,
    Building2,
    Percent,
    Target
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    stats: any;
    healthScore: number | null | undefined;
}>();

// ── Health Score helpers ─────────────────────────────────────────────────────
const healthScoreColor = computed(() => {
    const s = props.healthScore ?? 0;

    if (s >= 70) {
return { bar: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/20', label: 'Tốt' };
}

    if (s >= 40) {
return { bar: 'bg-amber-500',   text: 'text-amber-600 dark:text-amber-400',   bg: 'bg-amber-50 dark:bg-amber-950/20',   label: 'Trung bình' };
}

    return           { bar: 'bg-rose-500',    text: 'text-rose-600 dark:text-rose-400',     bg: 'bg-rose-50 dark:bg-rose-950/20',     label: 'Cần cải thiện' };
});

function formatMoney(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
}
</script>

<template>
    <div class="space-y-4">
        <!-- Today's KPI row -->
        <div v-if="stats" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2">
            <!-- Đơn hàng -->
            <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                <ShoppingCart class="size-4 text-violet-500 shrink-0" />
                <div class="min-w-0">
                    <p class="text-lg font-bold leading-none">{{ stats.orders_today }}</p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">Đơn hôm nay</p>
                </div>
            </div>
            <!-- Doanh thu + xu hướng -->
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/20 px-3 py-2.5 flex items-center gap-2">
                <Banknote class="size-4 text-emerald-600 shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1">
                        <p class="text-lg font-bold leading-none text-emerald-700 dark:text-emerald-400 truncate">
                            {{ formatMoney(stats.revenue_today) }}
                        </p>
                        <span v-if="stats.revenue_trend !== null"
                            :class="stats.revenue_trend >= 0 ? 'text-emerald-600' : 'text-rose-500'"
                            class="text-[9px] font-bold shrink-0 flex items-center gap-0.5"
                        >
                            <component :is="stats.revenue_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                            {{ Math.abs(stats.revenue_trend) }}%
                        </span>
                    </div>
                    <p class="text-[10px] text-emerald-600/70 dark:text-emerald-500 mt-0.5">Doanh thu</p>
                </div>
            </div>
            <!-- Đơn hoàn thành + xu hướng -->
            <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                <CheckCircle2 class="size-4 text-sky-500 shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1">
                        <p class="text-lg font-bold leading-none">{{ stats.orders_completed }}</p>
                        <span v-if="stats.order_trend !== null"
                            :class="stats.order_trend >= 0 ? 'text-emerald-600' : 'text-rose-500'"
                            class="text-[9px] font-bold shrink-0 flex items-center gap-0.5"
                        >
                            <component :is="stats.order_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                            {{ Math.abs(stats.order_trend) }}%
                        </span>
                    </div>
                    <p class="text-[10px] text-muted-foreground mt-0.5">Hoàn thành</p>
                </div>
            </div>
            <!-- Sản phẩm -->
            <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                <Package class="size-4 text-amber-500 shrink-0" />
                <div>
                    <p class="text-lg font-bold leading-none">{{ stats.products_count }}</p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">Sản phẩm</p>
                </div>
            </div>
            <!-- Nhân viên -->
            <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                <Users class="size-4 text-indigo-500 shrink-0" />
                <div>
                    <p class="text-lg font-bold leading-none">{{ stats.employees_count }}</p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">Nhân viên</p>
                </div>
            </div>
            <!-- Chi nhánh / Bàn -->
            <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                <Building2 class="size-4 text-rose-500 shrink-0" />
                <div>
                    <p class="text-lg font-bold leading-none">{{ stats.branches_count }}/{{ stats.tables_count }}</p>
                    <p class="text-[10px] text-muted-foreground mt-0.5">CN / Bàn</p>
                </div>
            </div>
            <!-- Biên lợi nhuận hôm nay -->
            <div class="rounded-xl border border-violet-200 bg-violet-50 dark:border-violet-900/40 dark:bg-violet-950/20 px-3 py-2.5 flex items-center gap-2">
                <Percent class="size-4 text-violet-600 shrink-0" />
                <div>
                    <p class="text-lg font-bold leading-none text-violet-700 dark:text-violet-400">
                        {{ stats.profit_margin_today }}%
                    </p>
                    <p class="text-[10px] text-violet-600/70 mt-0.5">Biên LN</p>
                </div>
            </div>
            <!-- Tỉ lệ hoàn thành -->
            <div :class="[
                'rounded-xl border px-3 py-2.5 flex items-center gap-2',
                stats.completion_rate >= 80
                    ? 'border-teal-200 bg-teal-50 dark:border-teal-900/40 dark:bg-teal-950/20'
                    : stats.completion_rate >= 50
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20'
                        : 'border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20'
            ]">
                <Target class="size-4 shrink-0"
                    :class="stats.completion_rate >= 80 ? 'text-teal-600' : stats.completion_rate >= 50 ? 'text-amber-600' : 'text-rose-600'" />
                <div>
                    <p class="text-lg font-bold leading-none"
                       :class="stats.completion_rate >= 80 ? 'text-teal-700 dark:text-teal-400' : stats.completion_rate >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400'">
                        {{ stats.completion_rate }}%
                    </p>
                    <p class="text-[10px] mt-0.5"
                       :class="stats.completion_rate >= 80 ? 'text-teal-600/70' : stats.completion_rate >= 50 ? 'text-amber-600/70' : 'text-rose-600/70'">
                        Tỉ lệ HT
                    </p>
                </div>
            </div>
        </div>

        <!-- Business Health Score -->
        <div v-if="healthScore !== null && healthScore !== undefined"
            :class="['rounded-xl border px-4 py-3 flex items-center gap-4', healthScoreColor.bg]">
            <div class="shrink-0">
                <div :class="['text-3xl font-black', healthScoreColor.text]">{{ healthScore }}</div>
                <div class="text-[10px] text-muted-foreground">/ 100</div>
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold">Sức khoẻ kinh doanh hôm nay</span>
                    <span :class="['text-[10px] font-bold px-2 py-0.5 rounded-full', healthScoreColor.text, healthScoreColor.bg]">
                        {{ healthScoreColor.label }}
                    </span>
                </div>
                <div class="h-2 bg-white/50 dark:bg-black/20 rounded-full overflow-hidden">
                    <div :class="['h-full rounded-full transition-all duration-700', healthScoreColor.bar]"
                         :style="`width: ${healthScore}%`" />
                </div>
                <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[10px] text-muted-foreground">
                    <span>✓ Tỉ lệ HT: {{ stats?.completion_rate ?? 0 }}%</span>
                    <span>✓ Biên LN: {{ stats?.profit_margin_today ?? 0 }}%</span>
                    <span v-if="stats?.revenue_trend !== null">
                        {{ (stats?.revenue_trend ?? 0) >= 0 ? '↑' : '↓' }} Doanh thu {{ stats?.revenue_trend }}% so hôm qua
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
