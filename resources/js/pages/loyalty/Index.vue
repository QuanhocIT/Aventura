<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    Gift,
    Settings,
    TrendingUp,
    Users,
    ArrowUpRight,
    ArrowDownRight,
    History,
} from 'lucide-vue-next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    program: any;
    tiers: any[];
    rewards: any[];
    metrics: {
        totalMembers: number;
        totalPointsIssued: number;
        totalPointsRedeemed: number;
        redemptionRate: number;
        tierDistribution: {
            name: string;
            color: string | null;
            count: number;
        }[];
        recentTransactions: any[];
    };
}>();

const typeLabel: Record<string, string> = {
    earn: 'Tích điểm',
    redeem: 'Đổi điểm',
    expire: 'Hết hạn',
    adjust: 'Điều chỉnh',
    birthday: 'Sinh nhật',
    refund: 'Hoàn trả',
};
const typeColor: Record<string, string> = {
    earn: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    redeem: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    expire: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    adjust: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    birthday:
        'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
    refund: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
import { computed } from 'vue';

const totalTierMembers = computed(() => {
    return props.metrics.tierDistribution.reduce((acc, t) => acc + t.count, 0) || 1;
});

const tierSegments = computed(() => {
    const total = totalTierMembers.value || 1;
    let accumulatedPercent = 0;
    return props.metrics.tierDistribution.map(t => {
        const percent = (t.count / total) * 100;
        const segment = {
            name: t.name,
            count: t.count,
            percent,
            color: t.color || '#94a3b8',
            dashOffset: 175.9 * (1 - (t.count / total)),
            rotation: (accumulatedPercent / 100) * 360,
        };
        accumulatedPercent += percent;
        return segment;
    });
});
</script>

<template>
    <Head title="Khách hàng thân thiết" />

    <div class="mx-auto flex max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/60"
                >
                    <Award class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Chương trình Khách hàng Thân thiết
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Quản lý tích điểm, hạng thành viên, và phần thưởng.
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <Link href="/loyalty/settings">
                    <Button variant="outline" class="gap-2"
                        ><Settings class="size-4" /> Cấu hình</Button
                    >
                </Link>
            </div>
        </div>

        <!-- Status banner -->
        <div
            v-if="program && !program.is_active"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300"
        >
            Chương trình đang tạm ngừng. Vào
            <Link href="/loyalty/settings" class="font-semibold underline"
                >Cấu hình</Link
            >
            để kích hoạt.
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Total Members Profiles -->
            <Card class="shadow-xs transition-transform hover:translate-y-[-2px]">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-slate-400 uppercase">Thành viên tích cực</CardDescription>
                    <Users class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-slate-800 dark:text-slate-100">{{ metrics.totalMembers.toLocaleString() }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">hội viên đang hoạt động trong hệ thống</p>
                </CardContent>
            </Card>

            <!-- Total Points Issued -->
            <Card class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-emerald-500 uppercase">Điểm tích lũy đã phát</CardDescription>
                    <ArrowUpRight class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ metrics.totalPointsIssued.toLocaleString() }} pt</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">đã được cộng qua các đơn hàng</p>
                </CardContent>
            </Card>

            <!-- Total Points Redeemed -->
            <Card class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-indigo-500 uppercase">Điểm tích lũy đã đổi</CardDescription>
                    <ArrowDownRight class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ metrics.totalPointsRedeemed.toLocaleString() }} pt</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">đổi lấy ưu đãi & quà tặng thành công</p>
                </CardContent>
            </Card>
        </div>

        <!-- Retention & Loyalty Metrics Row -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
            <!-- 1. Donut chart: Tỉ lệ quy đổi (Redemption Rate) -->
            <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-800 dark:bg-slate-900/40">
                <svg width="80" height="80" viewBox="0 0 80 80">
                    <circle
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#e2e8f0"
                        stroke-width="10"
                        class="dark:stroke-slate-800"
                    />
                    <circle
                        cx="40"
                        cy="40"
                        r="28"
                        fill="none"
                        stroke="#6366f1"
                        stroke-width="10"
                        stroke-dasharray="175.9"
                        :stroke-dashoffset="175.9 * (1 - (metrics.redemptionRate ?? 0) / 100)"
                        stroke-linecap="round"
                        transform="rotate(-90 40 40)"
                    />
                    <text
                        x="40"
                        y="44"
                        text-anchor="middle"
                        class="fill-slate-700 text-xs font-black dark:fill-slate-200"
                        font-size="14"
                        font-weight="900"
                    >
                        {{ metrics.redemptionRate }}%
                    </text>
                </svg>
                <p class="text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                    Tỷ lệ quy đổi
                </p>
            </div>

            <!-- 2. Donut chart: Phân hạng hội viên (Tier distribution donut) -->
            <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-800 dark:bg-slate-900/40">
                <div class="relative w-[80px] h-[80px] flex items-center justify-center">
                    <svg width="80" height="80" viewBox="0 0 80 80" class="overflow-visible transform -rotate-90">
                        <circle
                            cx="40"
                            cy="40"
                            r="28"
                            fill="none"
                            stroke="#e2e8f0"
                            stroke-width="10"
                            class="dark:stroke-slate-800"
                        />
                        <circle
                            v-for="seg in tierSegments"
                            :key="seg.name"
                            cx="40"
                            cy="40"
                            r="28"
                            fill="none"
                            :stroke="seg.color"
                            stroke-width="10"
                            stroke-dasharray="175.9"
                            :stroke-dashoffset="seg.dashOffset"
                            :transform="`rotate(${seg.rotation} 40 40)`"
                        />
                    </svg>
                    <div class="absolute flex flex-col items-center justify-center text-center">
                        <span class="text-[10px] font-black text-slate-800 dark:text-slate-100">{{ metrics.tierDistribution.length }}</span>
                        <span class="text-[6px] text-muted-foreground uppercase">Hạng</span>
                    </div>
                </div>
                <p class="text-center text-[10px] font-bold tracking-wider text-slate-500 uppercase">
                    Hạng hội viên
                </p>
            </div>

            <!-- 3. Hạng nhiều nhất -->
            <div class="flex flex-col items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-800 dark:bg-slate-900/40">
                <Award class="size-6 text-indigo-500" />
                <span class="text-sm font-black text-slate-850 dark:text-slate-200 text-center truncate w-full px-1">
                    {{ metrics.tierDistribution.length ? [...metrics.tierDistribution].sort((a,b) => b.count - a.count)[0].name : '—' }}
                </span>
                <p class="text-center text-[9px] font-bold tracking-wider text-slate-500 uppercase">
                    Hạng đông nhất
                </p>
            </div>

            <!-- 4. Quà tặng sẵn có -->
            <div class="flex flex-col items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-800 dark:bg-slate-900/40">
                <Gift class="size-6 text-pink-500" />
                <span class="text-xl font-black text-slate-850 dark:text-slate-200">
                    {{ rewards.length }}
                </span>
                <p class="text-center text-[9px] font-bold tracking-wider text-slate-500 uppercase">
                    Phần thưởng
                </p>
            </div>

            <!-- 5. Giao dịch gần đây -->
            <div class="flex flex-col items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white p-4 sm:col-span-1 dark:border-slate-800 dark:bg-slate-900/40">
                <History class="size-6 text-blue-500" />
                <span class="text-xl font-black text-slate-850 dark:text-slate-200">
                    {{ metrics.recentTransactions.length }}
                </span>
                <p class="text-center text-[9px] font-bold tracking-wider text-slate-500 uppercase">
                    Số giao dịch
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Tier Distribution -->
            <Card class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="border-b pb-3 border-slate-100 dark:border-slate-800">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Award class="size-4.5 text-indigo-500" /> Phân bố hạng thành viên
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-5 space-y-4">
                    <!-- Stacked Horizontal Bar for Tier Distribution -->
                    <div v-if="metrics.tierDistribution.length" class="h-3.5 w-full rounded-full bg-slate-100 dark:bg-slate-800 flex overflow-hidden">
                        <div 
                            v-for="tier in metrics.tierDistribution" 
                            :key="tier.name" 
                            :style="{
                                width: `${(tier.count / totalTierMembers) * 100}%`,
                                backgroundColor: tier.color || '#94a3b8'
                            }"
                             class="h-full first:rounded-l-full last:rounded-r-full transition-all"
                             :title="`${tier.name}: ${tier.count} (${((tier.count / totalTierMembers) * 100).toFixed(0)}%)`"
                        ></div>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="tier in metrics.tierDistribution"
                            :key="tier.name"
                            class="flex items-center justify-between"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-3 w-3 rounded-full"
                                    :style="{
                                        backgroundColor: tier.color || '#94a3b8',
                                    }"
                                ></span>
                                <span class="text-xs font-semibold">{{ tier.name }}</span>
                            </div>
                            <span class="text-xs font-black text-slate-700 dark:text-slate-300">
                                {{ tier.count }} TV <span class="text-[10px] text-muted-foreground font-normal">({{ ((tier.count / totalTierMembers) * 100).toFixed(0) }}%)</span>
                            </span>
                        </div>
                    </div>
                    
                    <div
                        v-if="!metrics.tierDistribution.length"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa có hạng thành viên nào. 
                        <Link href="/loyalty/settings" class="text-indigo-600 dark:text-indigo-400 font-bold underline ml-1">Thiết lập ngay</Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Rewards summary -->
            <Card class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="border-b pb-3 border-slate-100 dark:border-slate-800">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Gift class="size-4.5 text-pink-500" /> Danh sách phần thưởng
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-5 space-y-3">
                    <div
                        v-for="r in rewards.slice(0, 5)"
                        :key="r.id"
                        class="flex items-center justify-between text-xs border-b pb-2 last:border-0 last:pb-0"
                    >
                        <span class="font-medium truncate mr-2">{{ r.name }}</span>
                        <Badge variant="secondary" class="bg-pink-50 text-pink-600 dark:bg-pink-950/30 dark:text-pink-400 border-none font-bold"
                            >{{ r.points_cost }} điểm</Badge
                        >
                    </div>
                    <div
                        v-if="!rewards.length"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa có phần thưởng nào để đổi điểm.
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Transactions -->
            <Card class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40 overflow-hidden">
                <CardHeader class="border-b pb-3 border-slate-100 dark:border-slate-800">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <History class="size-4.5 text-indigo-500" /> Nhật ký giao dịch gần đây
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-5 max-h-64 space-y-2.5 overflow-y-auto">
                    <div
                        v-for="tx in metrics.recentTransactions.slice(0, 10)"
                        :key="tx.id"
                        class="flex items-center justify-between border-b pb-2 text-xs last:border-0 last:pb-0"
                    >
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span
                                    :class="[
                                        'inline-flex rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider',
                                        typeColor[tx.type] ?? '',
                                    ]"
                                >
                                    {{ typeLabel[tx.type] ?? tx.type }}
                                </span>
                                <span class="font-bold text-slate-700 dark:text-slate-350">{{
                                    tx.customer?.full_name ?? '—'
                                }}</span>
                            </div>
                        </div>
                        <span
                            :class="[
                                'font-black font-mono',
                                tx.points > 0
                                    ? 'text-emerald-500'
                                    : 'text-rose-500',
                            ]"
                        >
                            {{ tx.points > 0 ? '+' : '' }}{{ tx.points }}
                        </span>
                    </div>
                    <div
                        v-if="!metrics.recentTransactions.length"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa phát sinh giao dịch tích đổi điểm nào.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
