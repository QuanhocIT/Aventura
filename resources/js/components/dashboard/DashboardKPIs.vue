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
import { computed, ref, watch } from 'vue';
import AnimatedNumber from '@/components/AnimatedNumber.vue';
import { useFeatureGate } from '@/composables/useFeatureGate';

const props = defineProps<{
    stats: any;
    healthScore: number | null | undefined;
}>();

const { can, planCode } = useFeatureGate();

// ── Health Score helpers ─────────────────────────────────────────────────────
const healthScoreColor = computed(() => {
    const s = props.healthScore ?? 0;

    if (s >= 70) {
        return { 
            bar: 'bg-gradient-to-r from-emerald-400 to-teal-500', 
            text: 'text-emerald-600 dark:text-emerald-400', 
            bg: 'bg-emerald-500/10 dark:bg-emerald-950/20 border-emerald-500/20 dark:border-emerald-500/10', 
            label: 'Tốt' 
        };
    }

    if (s >= 40) {
        return { 
            bar: 'bg-gradient-to-r from-amber-400 to-orange-500',   
            text: 'text-amber-600 dark:text-amber-400',   
            bg: 'bg-amber-500/10 dark:bg-amber-950/20 border-amber-500/20 dark:border-amber-500/10',   
            label: 'Trung bình' 
        };
    }

    return { 
        bar: 'bg-gradient-to-r from-rose-500 to-red-600',    
        text: 'text-rose-600 dark:text-rose-455',     
        bg: 'bg-rose-500/10 dark:bg-rose-950/20 border-rose-500/20 dark:border-rose-500/10',     
        label: 'Cần cải thiện' 
    };
});

// ── Count-up animated values (AnimatedNumber animate cả lúc mount) ──
const orders    = computed(() => Number(props.stats?.orders_today ?? 0));
const completed = computed(() => Number(props.stats?.orders_completed ?? 0));
const products  = computed(() => Number(props.stats?.products_count ?? 0));
const employees = computed(() => Number(props.stats?.employees_count ?? 0));
const health    = computed(() => Number(props.healthScore ?? 0));
const revenue   = computed(() => Number(props.stats?.revenue_today ?? 0));
const tables    = computed(() => Number(props.stats?.tables_count ?? 0));
const branches  = computed(() => Number(props.stats?.branches_count ?? 0));
const profitMargin = computed(() => Number(props.stats?.profit_margin_today ?? 0));
const completionRate = computed(() => Number(props.stats?.completion_rate ?? 0));

// Pulse trigger khi health score vừa load
const healthLoaded = ref(false);
watch(() => props.healthScore, (v) => {
 if (v != null) {
 setTimeout(() => {
  healthLoaded.value = true; 
 }, 300);
} 
});
</script>

<template>
    <div class="space-y-5">
        <!-- Today's KPI row -->
        <div v-if="stats" :class="['grid grid-cols-2 sm:grid-cols-4 gap-3', can('advanced_analytics') ? 'lg:grid-cols-8' : 'lg:grid-cols-7']">
            
            <!-- Đơn hàng -->
            <div class="group relative rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-violet-50 dark:bg-violet-950/30 text-violet-500 shrink-0 group-hover:scale-105 transition-all">
                    <ShoppingCart class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Đơn hàng</p>
                    <p class="text-xl font-black leading-none mt-1 text-slate-800 dark:text-slate-100 tabular-nums"><AnimatedNumber :value="orders" /></p>
                </div>
            </div>

            <!-- Doanh thu + xu hướng -->
            <div class="group relative rounded-2xl border border-emerald-100 dark:border-emerald-900/30 bg-emerald-500/5 dark:bg-emerald-950/10 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-emerald-200 dark:hover:border-emerald-800/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-emerald-500/10 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-455 shrink-0 group-hover:scale-105 transition-all">
                    <Banknote class="size-4.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold text-emerald-600/70 dark:text-emerald-500/80 uppercase tracking-wider">Doanh thu</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <p class="text-xl font-black leading-none text-emerald-700 dark:text-emerald-400 truncate">
                            <AnimatedNumber v-if="revenue > 0" :value="revenue" compact suffix="đ" />
                            <template v-else>—</template>
                        </p>
                        <span v-if="stats.revenue_trend !== null"
                            :class="stats.revenue_trend >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40' : 'bg-rose-50 text-rose-500 dark:bg-rose-950/40'"
                            class="text-[9px] font-extrabold px-1 py-0.5 rounded-md shrink-0 flex items-center gap-0.5"
                        >
                            <component :is="stats.revenue_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                            {{ Math.abs(stats.revenue_trend) }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Đơn hoàn thành + xu hướng -->
            <div class="group relative rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-sky-50 dark:bg-sky-950/30 text-sky-500 shrink-0 group-hover:scale-105 transition-all">
                    <CheckCircle2 class="size-4.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Hoàn thành</p>
                    <div class="flex items-center gap-1.5 mt-1">
                        <p class="text-xl font-black leading-none text-slate-800 dark:text-slate-100 tabular-nums"><AnimatedNumber :value="completed" /></p>
                        <span v-if="stats.order_trend !== null"
                            :class="stats.order_trend >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40' : 'bg-rose-50 text-rose-500 dark:bg-rose-950/40'"
                            class="text-[9px] font-extrabold px-1 py-0.5 rounded-md shrink-0 flex items-center gap-0.5"
                        >
                            <component :is="stats.order_trend >= 0 ? ArrowUp : ArrowDown" class="size-2.5" />
                            {{ Math.abs(stats.order_trend) }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sản phẩm -->
            <div class="group relative rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-amber-50 dark:bg-amber-950/30 text-amber-500 shrink-0 group-hover:scale-105 transition-all">
                    <Package class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Sản phẩm</p>
                    <p class="text-xl font-black leading-none mt-1 text-slate-800 dark:text-slate-100 tabular-nums"><AnimatedNumber :value="products" /></p>
                </div>
            </div>

            <!-- Nhân viên -->
            <div class="group relative rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 text-indigo-500 shrink-0 group-hover:scale-105 transition-all">
                    <Users class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Nhân viên</p>
                    <p class="text-xl font-black leading-none mt-1 text-slate-800 dark:text-slate-100 tabular-nums"><AnimatedNumber :value="employees" /></p>
                </div>
            </div>

            <!-- Chi nhánh / Bàn -->
            <div class="group relative rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/60 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-rose-50 dark:bg-rose-950/30 text-rose-500 shrink-0 group-hover:scale-105 transition-all">
                    <Building2 class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">{{ planCode() === 'free' ? 'Bàn ăn' : 'CN / Bàn' }}</p>
                    <p class="text-xl font-black leading-none mt-1 text-slate-800 dark:text-slate-100 tabular-nums">
                        <template v-if="planCode() === 'free'">
                            <AnimatedNumber :value="tables" /> bàn
                        </template>
                        <template v-else>
                            <AnimatedNumber :value="branches" />/<AnimatedNumber :value="tables" />
                        </template>
                    </p>
                </div>
            </div>

            <!-- Biên lợi nhuận hôm nay -->
            <div v-if="can('advanced_analytics')" class="group relative rounded-2xl border border-violet-100 dark:border-violet-900/30 bg-violet-500/5 dark:bg-violet-950/10 p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:border-violet-200 dark:hover:border-violet-850/80 hover:translate-y-[-2px]">
                <div class="p-2 rounded-xl bg-violet-500/10 dark:bg-violet-950/30 text-violet-600 dark:text-violet-400 shrink-0 group-hover:scale-105 transition-all">
                    <Percent class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold text-violet-600/70 dark:text-violet-500/80 uppercase tracking-wider">Biên LN</p>
                    <p class="text-xl font-black leading-none mt-1 text-violet-700 dark:text-violet-400">
                        <AnimatedNumber :value="profitMargin" suffix="%" />
                    </p>
                </div>
            </div>

            <!-- Tỉ lệ hoàn thành -->
            <div :class="[
                'group relative rounded-2xl border p-3 flex items-center gap-3 transition-all duration-300 hover:shadow-md hover:translate-y-[-2px]',
                stats.completion_rate >= 80
                    ? 'border-teal-100 bg-teal-500/5 dark:border-teal-900/30 dark:bg-teal-950/10'
                    : stats.completion_rate >= 50
                        ? 'border-amber-100 bg-amber-500/5 dark:border-amber-900/30 dark:bg-amber-950/10'
                        : 'border-rose-100 bg-rose-500/5 dark:border-rose-900/30 dark:bg-rose-950/10'
            ]">
                <div :class="[
                    'p-2 rounded-xl shrink-0 group-hover:scale-105 transition-all',
                    stats.completion_rate >= 80 
                        ? 'bg-teal-500/10 text-teal-600 dark:text-teal-400' 
                        : stats.completion_rate >= 50 
                            ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' 
                            : 'bg-rose-500/10 text-rose-600 dark:text-rose-455'
                ]">
                    <Target class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <p :class="[
                        'text-[10px] font-bold uppercase tracking-wider',
                        stats.completion_rate >= 80 ? 'text-teal-600/70' : stats.completion_rate >= 50 ? 'text-amber-600/70' : 'text-rose-600/70'
                    ]">Tỉ lệ HT</p>
                    <p :class="[
                        'text-xl font-black leading-none mt-1',
                        stats.completion_rate >= 80 ? 'text-teal-700 dark:text-teal-400' : stats.completion_rate >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400'
                    ]">
                        <AnimatedNumber :value="completionRate" suffix="%" />
                    </p>
                </div>
            </div>
        </div>

        <!-- Business Health Score -->
        <div v-if="healthScore !== null && healthScore !== undefined && (can('advanced_analytics') || can('hr_timekeeping'))"
            :class="['group relative rounded-2xl border px-5 py-4 flex flex-col md:flex-row md:items-center gap-5 transition-all duration-500 hover:shadow-lg', healthScoreColor.bg]">
            
            <div class="flex items-center gap-4 shrink-0">
                <div class="relative">
                    <div :class="['text-4xl font-black tracking-tight tabular-nums leading-none', healthScoreColor.text]"><AnimatedNumber :value="health" /></div>
                    <!-- Pulse ring khi health score vừa load -->
                    <span
                        v-if="healthLoaded"
                        class="absolute -inset-2 rounded-full animate-ping opacity-25"
                        :class="healthScoreColor.bar"
                    />
                </div>
                <div>
                    <span class="text-xs text-muted-foreground font-bold">Điểm sức khỏe</span>
                    <div class="text-[10px] text-muted-foreground mt-0.5">/ 100 tối đa</div>
                </div>
            </div>

            <div class="flex-1 w-full">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-extrabold tracking-wide uppercase text-slate-700 dark:text-slate-300">Sức khoẻ kinh doanh hôm nay</span>
                    <span :class="['text-[10px] font-extrabold px-2.5 py-0.5 rounded-full border', healthScoreColor.text, healthScoreColor.bg]">
                        Vận hành: {{ healthScoreColor.label }}
                    </span>
                </div>
                <div class="h-2 bg-slate-200/50 dark:bg-black/30 rounded-full overflow-hidden">
                    <div :class="['h-full rounded-full transition-all duration-700', healthScoreColor.bar]"
                         :style="`width: ${healthScore}%`" />
                </div>
                <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1">🟢 Hoàn thành: <strong class="text-slate-700 dark:text-slate-200">{{ stats?.completion_rate ?? 0 }}%</strong></span>
                    <span class="flex items-center gap-1">🟣 Biên LN: <strong class="text-slate-700 dark:text-slate-200">{{ stats?.profit_margin_today ?? 0 }}%</strong></span>
                    <span v-if="stats?.revenue_trend !== null" class="flex items-center gap-1">
                        {{ (stats?.revenue_trend ?? 0) >= 0 ? '📈' : '📉' }} Doanh thu: 
                        <strong :class="(stats?.revenue_trend ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-500'">
                            {{ (stats?.revenue_trend ?? 0) >= 0 ? '+' : '' }}{{ stats?.revenue_trend }}%
                        </strong> so với hôm qua
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
