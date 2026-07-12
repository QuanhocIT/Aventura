<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Wallet,
    ArrowDownRight,
    ArrowUpRight,
    ChevronRight,
    Activity,
} from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

const registerStatusLabel = computed(() => {
    return props.cashFlowSummary?.active_register_status === 'open'
        ? 'Két đang mở'
        : 'Két đang đóng';
});

const maxVal = computed(() => {
    if (!props.cashFlowSummary?.chart) return 1;
    let max = 0;
    props.cashFlowSummary.chart.forEach((d) => {
        if (d.in > max) max = d.in;
        if (d.out > max) max = d.out;
    });
    return max || 1;
});
</script>

<template>
    <Card
        class="group relative overflow-hidden rounded-2xl border border-slate-100 shadow-sm transition-all duration-200 hover:translate-y-[-2px] dark:border-slate-800"
    >
        <CardHeader
            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
        >
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Wallet
                            class="size-4 text-indigo-600 dark:text-indigo-400"
                        />
                        Quỹ Tiền Mặt & Dòng Tiền
                    </CardTitle>
                    <CardDescription class="mt-0.5 text-[11px]"
                        >Dòng tiền mặt lưu chuyển thực tế tại
                        quầy</CardDescription
                    >
                </div>
                <span
                    :class="[
                        'rounded-full px-2 py-0.5 text-[9px] font-black tracking-wider uppercase',
                        cashFlowSummary?.active_register_status === 'open'
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400'
                            : 'text-slate-650 bg-slate-100 dark:bg-slate-800 dark:text-slate-400',
                    ]"
                >
                    {{ registerStatusLabel }}
                </span>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 p-5">
            <!-- Balance Card -->
            <div
                class="flex items-center justify-between rounded-xl border border-indigo-100/30 bg-indigo-50/30 p-4 dark:border-indigo-900/20 dark:bg-indigo-950/20"
            >
                <div>
                    <p
                        class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                    >
                        Tiền mặt trong két
                    </p>
                    <p
                        class="mt-1 text-2xl font-black text-slate-800 dark:text-slate-100"
                    >
                        {{ vnd(cashFlowSummary?.current_cash ?? 0) }}
                    </p>
                </div>
                <div
                    class="text-indigo-650 flex h-10 w-10 items-center justify-center rounded-xl border border-indigo-100/50 bg-indigo-500/10 dark:border-indigo-900/30 dark:bg-indigo-950/40 dark:text-indigo-400"
                >
                    <Wallet class="size-5" />
                </div>
            </div>

            <!-- In/Out flow stats -->
            <div class="grid grid-cols-2 gap-3">
                <div
                    class="space-y-1 rounded-xl border bg-white p-3 dark:bg-slate-950"
                >
                    <p
                        class="flex items-center gap-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                    >
                        <span class="size-1.5 rounded-full bg-emerald-500" />
                        Thu 7 ngày qua
                    </p>
                    <p
                        class="mt-1 flex items-center font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        <ArrowUpRight
                            class="mr-0.5 size-3 shrink-0 text-emerald-500"
                        />
                        {{ vnd(cashFlowSummary?.seven_days_in ?? 0) }}
                    </p>
                </div>
                <div
                    class="space-y-1 rounded-xl border bg-white p-3 dark:bg-slate-950"
                >
                    <p
                        class="flex items-center gap-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                    >
                        <span class="size-1.5 rounded-full bg-rose-500" /> Chi 7
                        ngày qua
                    </p>
                    <p
                        class="mt-1 flex items-center font-mono text-xs font-bold text-rose-600 dark:text-rose-400"
                    >
                        <ArrowDownRight
                            class="mr-0.5 size-3 shrink-0 text-rose-500"
                        />
                        {{ vnd(cashFlowSummary?.seven_days_out ?? 0) }}
                    </p>
                </div>
            </div>

            <!-- Mini Daily Chart -->
            <div
                v-if="
                    cashFlowSummary?.chart && cashFlowSummary.chart.length > 0
                "
                class="space-y-2"
            >
                <p
                    class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                >
                    <Activity class="size-3 text-slate-400" /> Lưu lượng quỹ
                    hàng ngày
                </p>
                <div class="flex h-14 items-end justify-between px-1 pt-2">
                    <div
                        v-for="d in cashFlowSummary.chart"
                        :key="d.date"
                        class="group relative flex w-[12%] flex-col items-center gap-0.5"
                    >
                        <!-- Inflow Bar -->
                        <div
                            class="w-1.5 rounded-t-xs bg-emerald-500/80 transition-all duration-300"
                            :style="`height: ${Math.max(2, (d.in / maxVal) * 35)}px`"
                        />
                        <!-- Outflow Bar -->
                        <div
                            class="w-1.5 rounded-t-xs bg-rose-500/80 transition-all duration-300"
                            :style="`height: ${Math.max(2, (d.out / maxVal) * 35)}px`"
                        />
                        <!-- Tooltip -->
                        <div
                            class="absolute bottom-full left-1/2 z-10 hidden -translate-x-1/2 rounded bg-slate-900 px-2 py-1 text-[9px] font-bold whitespace-nowrap text-white shadow group-hover:block"
                        >
                            Ngày {{ d.date }} <br />
                            <span class="text-emerald-400"
                                >Thu: +{{ vnd(d.in) }}</span
                            >
                            <br />
                            <span class="text-rose-450"
                                >Chi: -{{ vnd(d.out) }}</span
                            >
                        </div>
                        <span
                            class="mt-1 shrink-0 scale-90 font-mono text-[8px] text-slate-400"
                            >{{ d.date.split('/')[0] }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- View Management Button -->
            <Button
                as-child
                variant="ghost"
                class="h-9 w-full justify-between text-xs text-indigo-600 hover:bg-slate-50 hover:text-indigo-700 dark:text-indigo-400 dark:hover:bg-slate-900/30"
            >
                <Link
                    href="/cash-flow"
                    class="flex w-full items-center justify-between font-bold"
                >
                    <span>Quản lý dòng tiền mặt chi tiết</span>
                    <ChevronRight class="size-4" />
                </Link>
            </Button>
        </CardContent>
    </Card>
</template>
