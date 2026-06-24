<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface RevDetail {
    restaurant: string;
    plan: string;
    status: string;
    price: string;
    started_at: string;
    ended_at: string;
    earned_revenue: string;
    deferred_revenue: string;
    earn_percent: number;
}

interface Summary {
    total_cash: string;
    total_earned: string;
    total_deferred: string;
    earn_rate: number;
    subscription_count: number;
    total_cash_raw: number;
    total_earned_raw: number;
    total_deferred_raw: number;
}

const props = defineProps<{
    summary: Summary;
    details: RevDetail[];
}>();

// Stacked bar chart data
const barWidth = computed(() => {
    const total = props.summary.total_cash_raw || 1;
    return {
        earned:   (props.summary.total_earned_raw / total) * 100,
        deferred: (props.summary.total_deferred_raw / total) * 100,
    };
});
</script>

<template>
    <Head title="Revenue Recognition" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link href="/super-admin/billing/analytics">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="mr-1.5 size-4" /> Analytics
                </Button>
            </Link>
            <div>
                <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                    <Wallet class="size-6 text-violet-600" />
                    Revenue Recognition
                </h1>
                <p class="text-sm text-muted-foreground">Phân biệt doanh thu đã ghi nhận (earned) vs chưa ghi nhận (deferred).</p>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <Card class="border-sky-200 dark:border-sky-900/40">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Tổng đã thu (Cash)</p>
                            <p class="mt-1 text-2xl font-bold text-sky-600">{{ summary.total_cash }}₫</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ summary.subscription_count }} subscriptions</p>
                        </div>
                        <div class="rounded-xl bg-sky-100 dark:bg-sky-900/30 p-2.5">
                            <TrendingUp class="size-5 text-sky-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-emerald-200 dark:border-emerald-900/40">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Đã ghi nhận (Earned)</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ summary.total_earned }}₫</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ summary.earn_rate }}% tổng tiền thu</p>
                        </div>
                        <div class="rounded-xl bg-emerald-100 dark:bg-emerald-900/30 p-2.5">
                            <TrendingUp class="size-5 text-emerald-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card class="border-amber-200 dark:border-amber-900/40">
                <CardContent class="p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Chưa ghi nhận (Deferred)</p>
                            <p class="mt-1 text-2xl font-bold text-amber-600">{{ summary.total_deferred }}₫</p>
                            <p class="text-xs text-muted-foreground mt-1">{{ (100 - summary.earn_rate).toFixed(1) }}% tổng tiền thu</p>
                        </div>
                        <div class="rounded-xl bg-amber-100 dark:bg-amber-900/30 p-2.5">
                            <Clock class="size-5 text-amber-600" />
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-5">
                    <p class="text-xs text-muted-foreground mb-3">Tỷ lệ ghi nhận</p>
                    <div class="flex h-6 rounded-full overflow-hidden w-full">
                        <div
                            class="bg-emerald-500 transition-all duration-700 flex items-center justify-center"
                            :style="{ width: `${barWidth.earned}%` }"
                        >
                            <span v-if="barWidth.earned > 15" class="text-[10px] font-bold text-white">{{ summary.earn_rate }}%</span>
                        </div>
                        <div
                            class="bg-amber-400 flex items-center justify-center"
                            :style="{ width: `${barWidth.deferred}%` }"
                        >
                            <span v-if="barWidth.deferred > 15" class="text-[10px] font-bold text-amber-900">{{ (100 - summary.earn_rate).toFixed(0) }}%</span>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-2 text-xs">
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-emerald-500 inline-block" /> Earned</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-full bg-amber-400 inline-block" /> Deferred</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Detail Table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">
                    Chi tiết theo Subscription
                    <span class="text-sm font-normal text-muted-foreground">({{ details.length }} subscriptions active)</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="details.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    Không có subscription đang active.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">Nhà hàng</th>
                                <th class="pb-3 text-left font-medium">Gói</th>
                                <th class="pb-3 text-left font-medium">Kỳ</th>
                                <th class="pb-3 text-right font-medium">Giá</th>
                                <th class="pb-3 text-right font-medium">Đã ghi nhận</th>
                                <th class="pb-3 text-right font-medium">Chưa ghi nhận</th>
                                <th class="pb-3 text-left font-medium w-32">Tiến độ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(row, idx) in details"
                                :key="idx"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="py-3 pr-4 font-medium">{{ row.restaurant }}</td>
                                <td class="py-3 pr-4 text-muted-foreground text-xs">{{ row.plan }}</td>
                                <td class="py-3 pr-4 text-xs text-muted-foreground whitespace-nowrap">{{ row.started_at }} → {{ row.ended_at }}</td>
                                <td class="py-3 pr-4 text-right">{{ row.price }}₫</td>
                                <td class="py-3 pr-4 text-right font-semibold text-emerald-600">{{ row.earned_revenue }}₫</td>
                                <td class="py-3 pr-4 text-right font-semibold text-amber-600">{{ row.deferred_revenue }}₫</td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-muted overflow-hidden">
                                            <div
                                                class="h-full rounded-full bg-emerald-500 transition-all duration-700"
                                                :style="{ width: `${row.earn_percent}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-muted-foreground w-8">{{ row.earn_percent }}%</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
