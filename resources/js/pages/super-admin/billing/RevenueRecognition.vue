<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Clock, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { PageHeader, StatCard, StatusBadge } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
        earned: (props.summary.total_earned_raw / total) * 100,
        deferred: (props.summary.total_deferred_raw / total) * 100,
    };
});
</script>

<template>
    <Head title="Revenue Recognition" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Revenue Recognition"
            subtitle="Phân biệt doanh thu đã ghi nhận (earned) vs chưa ghi nhận (deferred)."
            :icon="Wallet"
        >
            <template #actions>
                <Link href="/super-admin/billing/analytics">
                    <Button variant="outline" size="sm"
                        ><ArrowLeft class="mr-1.5 size-4" /> Analytics</Button
                    >
                </Link>
            </template>
        </PageHeader>

        <!-- Summary Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <StatCard
                label="Tổng đã thu (Cash)"
                :value="`${summary.total_cash}₫`"
                :icon="TrendingUp"
                color="sky"
                :change="`${summary.subscription_count} subscriptions`"
                class=""
            />
            <StatCard
                label="Đã ghi nhận (Earned)"
                :value="`${summary.total_earned}₫`"
                :icon="TrendingUp"
                color="emerald"
                :change="`${summary.earn_rate}% tổng thu`"
                class=""
            />
            <StatCard
                label="Chưa ghi nhận (Deferred)"
                :value="`${summary.total_deferred}₫`"
                :icon="Clock"
                color="amber"
                :change="`${(100 - summary.earn_rate).toFixed(1)}% tổng thu`"
                class=""
            />
            <Card>
                <CardContent class="p-5">
                    <p class="mb-3 text-xs text-muted-foreground">
                        Tỷ lệ ghi nhận
                    </p>
                    <div class="flex h-6 w-full overflow-hidden rounded-full">
                        <div
                            class="flex items-center justify-center bg-emerald-500 transition-all duration-700"
                            :style="{ width: `${barWidth.earned}%` }"
                        >
                            <span
                                v-if="barWidth.earned > 15"
                                class="text-[10px] font-bold text-white"
                                >{{ summary.earn_rate }}%</span
                            >
                        </div>
                        <div
                            class="flex items-center justify-center bg-amber-400"
                            :style="{ width: `${barWidth.deferred}%` }"
                        >
                            <span
                                v-if="barWidth.deferred > 15"
                                class="text-[10px] font-bold text-amber-900"
                                >{{
                                    (100 - summary.earn_rate).toFixed(0)
                                }}%</span
                            >
                        </div>
                    </div>
                    <div class="mt-2 flex gap-3 text-xs">
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block size-2.5 rounded-full bg-emerald-500"
                            />
                            Earned</span
                        >
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block size-2.5 rounded-full bg-amber-400"
                            />
                            Deferred</span
                        >
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Detail Table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">
                    Chi tiết theo Subscription
                    <span class="text-sm font-normal text-muted-foreground"
                        >({{ details.length }} subscriptions active)</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="details.length === 0"
                    class="py-16 text-center text-sm text-muted-foreground"
                >
                    Không có subscription đang active.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">
                                    Nhà hàng
                                </th>
                                <th class="pb-3 text-left font-medium">Gói</th>
                                <th class="pb-3 text-left font-medium">Kỳ</th>
                                <th class="pb-3 text-right font-medium">Giá</th>
                                <th class="pb-3 text-right font-medium">
                                    Đã ghi nhận
                                </th>
                                <th class="pb-3 text-right font-medium">
                                    Chưa ghi nhận
                                </th>
                                <th class="w-32 pb-3 text-left font-medium">
                                    Tiến độ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(row, idx) in details"
                                :key="idx"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="py-3 pr-4 font-medium">
                                    {{ row.restaurant }}
                                </td>
                                <td
                                    class="py-3 pr-4 text-xs text-muted-foreground"
                                >
                                    {{ row.plan }}
                                </td>
                                <td
                                    class="py-3 pr-4 text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ row.started_at }} → {{ row.ended_at }}
                                </td>
                                <td class="py-3 pr-4 text-right">
                                    {{ row.price }}₫
                                </td>
                                <td
                                    class="py-3 pr-4 text-right font-semibold text-emerald-600"
                                >
                                    {{ row.earned_revenue }}₫
                                </td>
                                <td
                                    class="py-3 pr-4 text-right font-semibold text-amber-600"
                                >
                                    {{ row.deferred_revenue }}₫
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                class="h-full rounded-full bg-emerald-500 transition-all duration-700"
                                                :style="{
                                                    width: `${row.earn_percent}%`,
                                                }"
                                            />
                                        </div>
                                        <span
                                            class="w-8 text-xs text-muted-foreground"
                                            >{{ row.earn_percent }}%</span
                                        >
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
