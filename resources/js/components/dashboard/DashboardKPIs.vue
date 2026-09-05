<script setup lang="ts">
import {
    ShoppingCart,
    Banknote,
    ArrowUp,
    ArrowDown,
    CheckCircle2,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AnimatedNumber from '@/components/AnimatedNumber.vue';
import { useFeatureGate } from '@/composables/useFeatureGate';

const props = defineProps<{
    stats: any;
    healthScore: number | null | undefined;
}>();

const { can } = useFeatureGate();

// ── Health Score helpers ─────────────────────────────────────────────────────
const healthScoreColor = computed(() => {
    const s = props.healthScore ?? 0;

    if (s >= 70) {
        return {
            bar: 'bg-gradient-to-r from-emerald-400 to-teal-500',
            text: 'text-emerald-600 dark:text-emerald-400',
            bg: 'bg-emerald-500/10 dark:bg-emerald-950/20 border-emerald-500/20 dark:border-emerald-500/10',
            label: 'Tốt',
        };
    }

    if (s >= 40) {
        return {
            bar: 'bg-gradient-to-r from-amber-400 to-orange-500',
            text: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-500/10 dark:bg-amber-950/20 border-amber-500/20 dark:border-amber-500/10',
            label: 'Trung bình',
        };
    }

    return {
        bar: 'bg-gradient-to-r from-rose-500 to-red-600',
        text: 'text-rose-600 dark:text-rose-455',
        bg: 'bg-rose-500/10 dark:bg-rose-950/20 border-rose-500/20 dark:border-rose-500/10',
        label: 'Cần cải thiện',
    };
});

// ── Count-up animated values (AnimatedNumber animate cả lúc mount) ──
const orders = computed(() => Number(props.stats?.orders_today ?? 0));
const completed = computed(() => Number(props.stats?.orders_completed ?? 0));
const products = computed(() => Number(props.stats?.products_count ?? 0));
const health = computed(() => Number(props.healthScore ?? 0));
const revenue = computed(() => Number(props.stats?.revenue_today ?? 0));
const completionRate = computed(() =>
    Number(props.stats?.completion_rate ?? 0),
);

// Pulse trigger khi health score vừa load
const healthLoaded = ref(false);
watch(
    () => props.healthScore,
    (v) => {
        if (v != null) {
            setTimeout(() => {
                healthLoaded.value = true;
            }, 300);
        }
    },
);
</script>

<template>
    <div class="dashboard-home-kpis space-y-4">
        <!-- Today's 4 Primary Executive KPI Cards -->
        <div
            v-if="stats"
            class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4"
        >
            <!-- 1. ĐƠN HÀNG HÔM NAY -->
            <div
                class="group relative flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50 dark:hover:border-slate-700/80"
            >
                <!-- Top row: Icon + Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-600 transition-colors group-hover:border-primary/30 group-hover:bg-primary/5 group-hover:text-primary dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                    >
                        <ShoppingCart class="size-4.5" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200/70 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400"
                    >
                        <span class="size-1.5 rounded-full bg-slate-400 dark:bg-slate-500" />
                        Hôm nay
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Đơn hàng hôm nay
                    </p>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-slate-100">
                            <AnimatedNumber :value="orders" />
                        </p>
                        <span class="text-xs font-normal text-slate-400 dark:text-slate-500">đơn phát sinh</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Đang phục vụ & Mới tạo</span>
                    <span class="font-medium text-slate-700 dark:text-slate-300">Thời gian thực</span>
                </div>
            </div>

            <!-- 2. DOANH THU HÔM NAY -->
            <div
                class="group relative flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50 dark:hover:border-slate-700/80"
            >
                <!-- Top row: Icon + Trend Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-600 transition-colors group-hover:border-primary/30 group-hover:bg-primary/5 group-hover:text-primary dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                    >
                        <Banknote class="size-4.5" />
                    </div>
                    <span
                        v-if="stats.revenue_trend !== null"
                        :class="[
                            'inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[10px] font-semibold',
                            stats.revenue_trend >= 0
                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'border-rose-500/20 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                        ]"
                    >
                        <component :is="stats.revenue_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                        {{ Math.abs(stats.revenue_trend) }}% so hôm qua
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center rounded-md border border-slate-200/70 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400"
                    >
                        Thực thu
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Doanh thu hôm nay
                    </p>
                    <p class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-slate-100">
                        <AnimatedNumber
                            v-if="revenue > 0"
                            :value="revenue"
                            suffix=" ₫"
                        />
                        <template v-else>0 ₫</template>
                    </p>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Tổng tiền đã chốt</span>
                    <span class="font-medium text-slate-700 dark:text-slate-300">Sau chiết khấu</span>
                </div>
            </div>

            <!-- 3. ĐƠN ĐÃ HOÀN TẤT -->
            <div
                class="group relative flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50 dark:hover:border-slate-700/80"
            >
                <!-- Top row: Icon + Completion Rate Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-600 transition-colors group-hover:border-primary/30 group-hover:bg-primary/5 group-hover:text-primary dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                    >
                        <CheckCircle2 class="size-4.5" />
                    </div>
                    <span
                        class="inline-flex items-center rounded-md border border-slate-200/70 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400"
                    >
                        Tỉ lệ: {{ completionRate }}%
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Đơn đã hoàn tất
                    </p>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-slate-100">
                            <AnimatedNumber :value="completed" />
                        </p>
                        <span class="text-xs font-normal text-slate-400 dark:text-slate-500">/ {{ orders }} đơn thành công</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Đã phục vụ xong</span>
                    <span class="font-medium text-slate-700 dark:text-slate-300">Đã thanh toán</span>
                </div>
            </div>

            <!-- 4. THỰC ĐƠN KINH DOANH -->
            <div
                class="group relative flex flex-col justify-between rounded-xl border border-slate-200/80 bg-white p-4 shadow-xs transition-all duration-200 hover:border-slate-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/50 dark:hover:border-slate-700/80"
            >
                <!-- Top row: Icon + Menu Status Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-slate-200/60 bg-slate-100/70 text-slate-600 transition-colors group-hover:border-primary/30 group-hover:bg-primary/5 group-hover:text-primary dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300"
                    >
                        <UtensilsCrossed class="size-4.5" />
                    </div>
                    <span
                        class="inline-flex items-center rounded-md border border-slate-200/70 bg-slate-50 px-2 py-0.5 text-[10px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400"
                    >
                        Sẵn sàng bán
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Thực đơn kinh doanh
                    </p>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <p class="text-2xl font-bold tracking-tight text-slate-900 tabular-nums dark:text-slate-100">
                            <AnimatedNumber :value="products" />
                        </p>
                        <span class="text-xs font-normal text-slate-400 dark:text-slate-500">món đang mở bán</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2.5 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Món ăn & Đồ uống</span>
                    <span class="font-medium text-slate-700 dark:text-slate-300">Sẵn sàng phục vụ</span>
                </div>
            </div>
        </div>

        <!-- Business Health Score với Popover Chẩn đoán chi tiết -->
        <div
            v-if="
                healthScore !== null &&
                healthScore !== undefined &&
                (can('advanced_analytics') || can('hr_timekeeping'))
            "
            :class="[
                'group relative flex flex-col gap-5 rounded-2xl border px-5 py-4 transition-all duration-500 hover:shadow-lg md:flex-row md:items-center',
                healthScoreColor.bg,
            ]"
        >
            <div class="flex shrink-0 items-center gap-4">
                <div class="relative">
                    <div
                        :class="[
                            'text-4xl leading-none font-black tracking-tight tabular-nums',
                            healthScoreColor.text,
                        ]"
                    >
                        <AnimatedNumber :value="health" />
                    </div>
                    <!-- Pulse ring khi health score vừa load -->
                    <span
                        v-if="healthLoaded"
                        class="absolute -inset-2 animate-ping rounded-full opacity-25"
                        :class="healthScoreColor.bar"
                    />
                </div>
                <div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-muted-foreground"
                            >Điểm sức khỏe</span
                        >
                        <span
                            class="py-0.2 inline-flex items-center rounded-full bg-slate-200/60 px-1.5 text-[9px] font-extrabold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            AI Diagnostic
                        </span>
                    </div>
                    <div class="mt-0.5 text-[10px] text-muted-foreground">
                        / 100 tối đa • Dựa trên 3 chỉ số chính
                    </div>
                </div>
            </div>

            <div class="w-full flex-1">
                <div class="mb-1.5 flex items-center justify-between">
                    <span
                        class="text-xs font-extrabold tracking-wide text-slate-700 uppercase dark:text-slate-300"
                        >Sức khoẻ kinh doanh hôm nay</span
                    >
                    <span
                        :class="[
                            'cursor-help rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold shadow-sm transition-transform hover:scale-105',
                            healthScoreColor.text,
                            healthScoreColor.bg,
                        ]"
                        title="Điểm sức khỏe vận hành tính theo hiệu suất hoàn thành đơn, lợi nhuận và doanh thu"
                    >
                        Vận hành: {{ healthScoreColor.label }}
                    </span>
                </div>
                <div
                    class="h-2.5 overflow-hidden rounded-full bg-slate-200/50 dark:bg-black/30"
                >
                    <div
                        :class="[
                            'h-full rounded-full transition-all duration-700',
                            healthScoreColor.bar,
                        ]"
                        :style="`width: ${healthScore}%`"
                    />
                </div>
                <div
                    class="mt-2.5 flex flex-wrap items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                >
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                        <span class="flex items-center gap-1"
                            >🟢 Tỉ lệ hoàn thành:
                            <strong class="text-slate-700 dark:text-slate-200"
                                >{{ stats?.completion_rate ?? 0 }}%</strong
                            ></span
                        >
                        <span class="flex items-center gap-1"
                            >🟣 Biên LN:
                            <strong class="text-slate-700 dark:text-slate-200"
                                >{{ stats?.profit_margin_today ?? 0 }}%</strong
                            ></span
                        >
                        <span
                            v-if="stats?.revenue_trend !== null"
                            class="flex items-center gap-1"
                        >
                            {{ (stats?.revenue_trend ?? 0) >= 0 ? '📈' : '📉' }}
                            Doanh thu:
                            <strong
                                :class="
                                    (stats?.revenue_trend ?? 0) >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-500 dark:text-rose-400'
                                "
                            >
                                {{ (stats?.revenue_trend ?? 0) >= 0 ? '+' : ''
                                }}{{ stats?.revenue_trend }}%
                            </strong>
                            so với hôm qua
                        </span>
                    </div>
                    <span
                        class="text-[10px] font-normal text-slate-400 dark:text-slate-500"
                    >
                        💡 Cập nhật theo thời gian thực
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
