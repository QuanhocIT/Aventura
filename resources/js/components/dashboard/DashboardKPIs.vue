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
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-indigo-100/90 bg-gradient-to-br from-white via-indigo-50/20 to-purple-50/30 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-lg hover:shadow-indigo-500/10 dark:border-indigo-900/40 dark:bg-gradient-to-br dark:from-slate-900 dark:via-indigo-950/20 dark:to-slate-900 dark:hover:border-indigo-700/60"
            >
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-indigo-500/10 blur-xl transition-all duration-500 group-hover:scale-150" />

                <!-- Top row: Icon + Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-md shadow-indigo-500/25 transition-transform duration-300 group-hover:scale-110"
                    >
                        <ShoppingCart class="size-5" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full border border-indigo-200/60 bg-indigo-50/80 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:border-indigo-800/60 dark:bg-indigo-950/60 dark:text-indigo-300"
                    >
                        <span class="size-1.5 rounded-full bg-indigo-500 animate-pulse" />
                        Hôm nay
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Đơn hàng hôm nay
                    </p>
                    <div class="mt-1 flex items-baseline gap-1">
                        <p class="text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white">
                            <AnimatedNumber :value="orders" />
                        </p>
                        <span class="text-xs font-bold text-slate-400">đơn phát sinh</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100/80 pt-2 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Đang phục vụ & Mới tạo</span>
                    <span class="font-bold text-indigo-600 dark:text-indigo-400">Thời gian thực</span>
                </div>
            </div>

            <!-- 2. DOANH THU HÔM NAY -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-emerald-100/90 bg-gradient-to-br from-white via-emerald-50/20 to-teal-50/30 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-500/10 dark:border-emerald-900/40 dark:bg-gradient-to-br dark:from-slate-900 dark:via-emerald-950/20 dark:to-slate-900 dark:hover:border-emerald-700/60"
            >
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-emerald-500/10 blur-xl transition-all duration-500 group-hover:scale-150" />

                <!-- Top row: Icon + Trend Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md shadow-emerald-500/25 transition-transform duration-300 group-hover:scale-110"
                    >
                        <Banknote class="size-5" />
                    </div>
                    <span
                        v-if="stats.revenue_trend !== null"
                        :class="[
                            'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-extrabold shadow-xs',
                            stats.revenue_trend >= 0
                                ? 'border-emerald-200/80 bg-emerald-50/90 text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'border-rose-200/80 bg-rose-50/90 text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/60 dark:text-rose-300',
                        ]"
                    >
                        <component :is="stats.revenue_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                        {{ Math.abs(stats.revenue_trend) }}% so hôm qua
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full border border-emerald-200/60 bg-emerald-50/80 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        Thực thu
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-extrabold tracking-wider text-emerald-700 uppercase dark:text-emerald-400">
                        Doanh thu hôm nay
                    </p>
                    <p class="mt-1 truncate text-2xl font-black tracking-tight text-emerald-700 tabular-nums dark:text-emerald-400">
                        <AnimatedNumber
                            v-if="revenue > 0"
                            :value="revenue"
                            suffix=" ₫"
                        />
                        <template v-else>0 ₫</template>
                    </p>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100/80 pt-2 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Tổng tiền đã chốt</span>
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">Sau chiết khấu</span>
                </div>
            </div>

            <!-- 3. ĐƠN ĐÃ HOÀN TẤT -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-sky-100/90 bg-gradient-to-br from-white via-sky-50/20 to-blue-50/30 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-sky-300 hover:shadow-lg hover:shadow-sky-500/10 dark:border-sky-900/40 dark:bg-gradient-to-br dark:from-slate-900 dark:via-sky-950/20 dark:to-slate-900 dark:hover:border-sky-700/60"
            >
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-sky-500/10 blur-xl transition-all duration-500 group-hover:scale-150" />

                <!-- Top row: Icon + Completion Rate Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-md shadow-sky-500/25 transition-transform duration-300 group-hover:scale-110"
                    >
                        <CheckCircle2 class="size-5" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full border border-sky-200/60 bg-sky-50/80 px-2 py-0.5 text-[10px] font-bold text-sky-700 dark:border-sky-800/60 dark:bg-sky-950/60 dark:text-sky-300"
                    >
                        Tỉ lệ: {{ completionRate }}%
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Đơn đã hoàn tất
                    </p>
                    <div class="mt-1 flex items-baseline gap-1">
                        <p class="text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white">
                            <AnimatedNumber :value="completed" />
                        </p>
                        <span class="text-xs font-bold text-slate-400">/ {{ orders }} đơn thành công</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100/80 pt-2 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Đã phục vụ xong</span>
                    <span class="font-bold text-sky-600 dark:text-sky-400">Đã thanh toán</span>
                </div>
            </div>

            <!-- 4. THỰC ĐƠN KINH DOANH -->
            <div
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-amber-100/90 bg-gradient-to-br from-white via-amber-50/20 to-orange-50/30 p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-amber-300 hover:shadow-lg hover:shadow-amber-500/10 dark:border-amber-900/40 dark:bg-gradient-to-br dark:from-slate-900 dark:via-amber-950/20 dark:to-slate-900 dark:hover:border-amber-700/60"
            >
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-amber-500/10 blur-xl transition-all duration-500 group-hover:scale-150" />

                <!-- Top row: Icon + Menu Status Badge -->
                <div class="flex items-center justify-between">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-md shadow-amber-500/25 transition-transform duration-300 group-hover:scale-110"
                    >
                        <UtensilsCrossed class="size-5" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full border border-amber-200/60 bg-amber-50/80 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:border-amber-800/60 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        Sẵn sàng bán
                    </span>
                </div>

                <!-- Middle row: Value & Title -->
                <div class="mt-3">
                    <p class="text-[11px] font-extrabold tracking-wider text-slate-500 uppercase dark:text-slate-400">
                        Thực đơn kinh doanh
                    </p>
                    <div class="mt-1 flex items-baseline gap-1">
                        <p class="text-2xl font-black tracking-tight text-slate-900 tabular-nums dark:text-white">
                            <AnimatedNumber :value="products" />
                        </p>
                        <span class="text-xs font-bold text-slate-400">món đang mở bán</span>
                    </div>
                </div>

                <!-- Bottom row: Subtitle context -->
                <div class="mt-3 flex items-center justify-between border-t border-slate-100/80 pt-2 text-[11px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                    <span class="truncate">Món ăn & Đồ uống</span>
                    <span class="font-bold text-amber-600 dark:text-amber-400">Sẵn sàng phục vụ</span>
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
