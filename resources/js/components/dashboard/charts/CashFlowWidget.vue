<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Wallet, ArrowDownRight, ArrowUpRight, ChevronRight, Activity } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface CashFlowSummary {
    active_register_status: 'open' | 'closed';
    current_cash: number;
    seven_days_in: number;
    seven_days_out: number;
    chart: { date: string; in: number; out: number }[];
}

const props = defineProps<{
    cashFlowSummary?: CashFlowSummary | null;
}>();

const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

const registerStatusLabel = computed(() => {
    return props.cashFlowSummary?.active_register_status === 'open' ? 'Két đang mở' : 'Két đang đóng';
});

const maxVal = computed(() => {
    if (!props.cashFlowSummary?.chart) return 1;
    let max = 0;
    props.cashFlowSummary.chart.forEach(d => {
        if (d.in > max) max = d.in;
        if (d.out > max) max = d.out;
    });
    return max || 1;
});
</script>

<template>
    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden hover:translate-y-[-2px] transition-all duration-200 relative group">
        <CardHeader class="pb-3 bg-slate-50/50 dark:bg-slate-900/10 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Wallet class="size-4 text-indigo-600 dark:text-indigo-400" />
                        Quỹ Tiền Mặt & Dòng Tiền
                    </CardTitle>
                    <CardDescription class="text-[11px] mt-0.5">Dòng tiền mặt lưu chuyển thực tế tại quầy</CardDescription>
                </div>
                <span 
                    :class="[
                        'px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider',
                        cashFlowSummary?.active_register_status === 'open' 
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400' 
                            : 'bg-slate-100 text-slate-650 dark:bg-slate-800 dark:text-slate-400'
                    ]"
                >
                    {{ registerStatusLabel }}
                </span>
            </div>
        </CardHeader>
        
        <CardContent class="p-5 space-y-4">
            <!-- Balance Card -->
            <div class="bg-indigo-50/30 dark:bg-indigo-950/20 border border-indigo-100/30 dark:border-indigo-900/20 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Tiền mặt trong két</p>
                    <p class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1">
                        {{ vnd(cashFlowSummary?.current_cash ?? 0) }}
                    </p>
                </div>
                <div class="h-10 w-10 bg-indigo-500/10 text-indigo-650 dark:bg-indigo-950/40 dark:text-indigo-400 rounded-xl flex items-center justify-center border border-indigo-100/50 dark:border-indigo-900/30">
                    <Wallet class="size-5" />
                </div>
            </div>

            <!-- In/Out flow stats -->
            <div class="grid grid-cols-2 gap-3">
                <div class="border rounded-xl p-3 space-y-1 dark:bg-slate-950 bg-white">
                    <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1">
                        <span class="size-1.5 bg-emerald-500 rounded-full" /> Thu 7 ngày qua
                    </p>
                    <p class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 flex items-center mt-1">
                        <ArrowUpRight class="size-3 text-emerald-500 mr-0.5 shrink-0" />
                        {{ vnd(cashFlowSummary?.seven_days_in ?? 0) }}
                    </p>
                </div>
                <div class="border rounded-xl p-3 space-y-1 dark:bg-slate-950 bg-white">
                    <p class="text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1">
                        <span class="size-1.5 bg-rose-500 rounded-full" /> Chi 7 ngày qua
                    </p>
                    <p class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400 flex items-center mt-1">
                        <ArrowDownRight class="size-3 text-rose-500 mr-0.5 shrink-0" />
                        {{ vnd(cashFlowSummary?.seven_days_out ?? 0) }}
                    </p>
                </div>
            </div>

            <!-- Mini Daily Chart -->
            <div v-if="cashFlowSummary?.chart && cashFlowSummary.chart.length > 0" class="space-y-2">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1">
                    <Activity class="size-3 text-slate-400" /> Lưu lượng quỹ hàng ngày
                </p>
                <div class="flex items-end justify-between h-14 pt-2 px-1">
                    <div 
                        v-for="d in cashFlowSummary.chart" 
                        :key="d.date"
                        class="flex flex-col gap-0.5 items-center w-[12%] group relative"
                    >
                        <!-- Inflow Bar -->
                        <div 
                            class="w-1.5 bg-emerald-500/80 rounded-t-xs transition-all duration-300"
                            :style="`height: ${Math.max(2, (d.in / maxVal) * 35)}px`"
                        />
                        <!-- Outflow Bar -->
                        <div 
                            class="w-1.5 bg-rose-500/80 rounded-t-xs transition-all duration-300"
                            :style="`height: ${Math.max(2, (d.out / maxVal) * 35)}px`"
                        />
                        <!-- Tooltip -->
                        <div class="absolute bottom-full left-1/2 -translate-x-1/2 bg-slate-900 text-white rounded px-2 py-1 text-[9px] hidden group-hover:block z-10 whitespace-nowrap shadow font-bold">
                            Ngày {{ d.date }} <br/>
                            <span class="text-emerald-400">Thu: +{{ vnd(d.in) }}</span> <br/>
                            <span class="text-rose-450">Chi: -{{ vnd(d.out) }}</span>
                        </div>
                        <span class="text-[8px] text-slate-400 font-mono mt-1 shrink-0 scale-90">{{ d.date.split('/')[0] }}</span>
                    </div>
                </div>
            </div>

            <!-- View Management Button -->
            <Button as-child variant="ghost" class="w-full text-xs h-9 justify-between text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 hover:bg-slate-50 dark:hover:bg-slate-900/30">
                <Link href="/cash-flow" class="flex w-full items-center justify-between font-bold">
                    <span>Quản lý dòng tiền mặt chi tiết</span>
                    <ChevronRight class="size-4" />
                </Link>
            </Button>
        </CardContent>
    </Card>
</template>
