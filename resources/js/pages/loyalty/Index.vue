<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Award, Gift, Settings, TrendingUp, Users, ArrowUpRight, ArrowDownRight, History } from 'lucide-vue-next';
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
        tierDistribution: { name: string; color: string | null; count: number }[];
        recentTransactions: any[];
    };
}>();

const typeLabel: Record<string, string> = {
    earn: 'Tích điểm', redeem: 'Đổi điểm', expire: 'Hết hạn',
    adjust: 'Điều chỉnh', birthday: 'Sinh nhật', refund: 'Hoàn trả',
};
const typeColor: Record<string, string> = {
    earn: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    redeem: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    expire: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    adjust: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    birthday: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-300',
    refund: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
</script>

<template>
    <Head title="Khách hàng thân thiết" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/60 text-amber-600">
                    <Award class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Chương trình Khách hàng Thân thiết</h1>
                    <p class="text-sm text-muted-foreground">Quản lý tích điểm, hạng thành viên, và phần thưởng.</p>
                </div>
            </div>
            <div class="flex gap-2">
                <Link href="/loyalty/settings">
                    <Button variant="outline" class="gap-2"><Settings class="size-4" /> Cấu hình</Button>
                </Link>
            </div>
        </div>

        <!-- Status banner -->
        <div v-if="program && !program.is_active" class="rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 p-4 text-sm text-amber-800 dark:text-amber-300">
            Chương trình đang tạm ngừng. Vào <Link href="/loyalty/settings" class="underline font-semibold">Cấu hình</Link> để kích hoạt.
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
                <CardContent class="flex items-center gap-4 p-5">
                    <Users class="size-8 text-blue-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Thành viên tích cực</p>
                        <p class="text-2xl font-bold">{{ metrics.totalMembers.toLocaleString() }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-5">
                    <ArrowUpRight class="size-8 text-green-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Điểm đã phát</p>
                        <p class="text-2xl font-bold text-green-600">{{ metrics.totalPointsIssued.toLocaleString() }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-5">
                    <ArrowDownRight class="size-8 text-indigo-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Điểm đã đổi</p>
                        <p class="text-2xl font-bold text-indigo-600">{{ metrics.totalPointsRedeemed.toLocaleString() }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-5">
                    <TrendingUp class="size-8 text-amber-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Tỷ lệ quy đổi</p>
                        <p class="text-2xl font-bold text-amber-600">{{ metrics.redemptionRate }}%</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tier Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold">Phân bố hạng thành viên</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="tier in metrics.tierDistribution" :key="tier.name" class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: tier.color || '#94a3b8' }"></span>
                            <span class="text-sm font-medium">{{ tier.name }}</span>
                        </div>
                        <span class="text-sm font-bold">{{ tier.count }}</span>
                    </div>
                    <div v-if="!metrics.tierDistribution.length" class="text-sm text-muted-foreground text-center py-4">
                        Chưa có hạng thành viên nào. <Link href="/loyalty/settings" class="underline">Thiết lập ngay</Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Rewards summary -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold">Phần thưởng ({{ rewards.length }})</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div v-for="r in rewards.slice(0, 5)" :key="r.id" class="flex items-center justify-between text-sm">
                        <span class="truncate">{{ r.name }}</span>
                        <Badge variant="secondary">{{ r.points_cost }} điểm</Badge>
                    </div>
                    <div v-if="!rewards.length" class="text-sm text-muted-foreground text-center py-4">
                        Chưa có phần thưởng nào.
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Transactions -->
            <Card class="lg:col-span-1">
                <CardHeader>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <History class="size-4" /> Giao dịch gần đây
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 max-h-64 overflow-y-auto">
                    <div v-for="tx in metrics.recentTransactions.slice(0, 10)" :key="tx.id" class="flex items-center justify-between text-xs border-b pb-2 last:border-0">
                        <div>
                            <span :class="['inline-flex rounded-full px-1.5 py-0.5 font-medium', typeColor[tx.type] ?? '']">
                                {{ typeLabel[tx.type] ?? tx.type }}
                            </span>
                            <span class="ml-1 text-muted-foreground">{{ tx.customer?.full_name ?? '—' }}</span>
                        </div>
                        <span :class="['font-bold', tx.points > 0 ? 'text-green-600' : 'text-red-500']">
                            {{ tx.points > 0 ? '+' : '' }}{{ tx.points }}
                        </span>
                    </div>
                    <div v-if="!metrics.recentTransactions.length" class="text-sm text-muted-foreground text-center py-4">
                        Chưa có giao dịch nào.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
