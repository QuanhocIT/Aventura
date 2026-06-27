<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { DollarSign, TrendingUp, ShoppingCart, Building2, Crown } from 'lucide-vue-next';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, SectionCard, ProgressBar } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    revenueByRestaurant: Array<{ id: number; name: string; code: string; revenue: number; orders_count: number }>;
    dailyRevenue: Array<{ date: string; revenue: number; orders_count: number }>;
    stats: { total_revenue: number; total_orders: number; avg_order_value: number; active_restaurants: number };
    filters: { range: string };
}>();

const range = ref(props.filters.range);

function applyFilter() {
    router.get('/super-admin/revenue', { range: range.value }, { preserveState: true, replace: true });
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
}

const maxRevenue = Math.max(...(props.revenueByRestaurant.map(r => r.revenue) || [1]));
</script>

<template>
    <Head title="Doanh thu toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Doanh thu toàn hệ thống"
            subtitle="Phân tích doanh thu cross-restaurant, top performers và xu hướng."
            :icon="DollarSign"
        >
            <template #actions>
                <Select v-model="range" @update:model-value="applyFilter">
                    <SelectTrigger class="w-[140px]">
                        <SelectValue placeholder="Khoảng thời gian" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="7">7 ngày</SelectItem>
                        <SelectItem value="30">30 ngày</SelectItem>
                        <SelectItem value="90">90 ngày</SelectItem>
                    </SelectContent>
                </Select>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Tổng doanh thu" :value="formatVND(stats.total_revenue)" :icon="DollarSign" color="emerald" class="" />
            <StatCard label="Tổng đơn hàng" :value="stats.total_orders" :icon="ShoppingCart" color="sky" class="" />
            <StatCard label="Giá trị trung bình/đơn" :value="formatVND(stats.avg_order_value)" :icon="TrendingUp" color="violet" class="" />
            <StatCard label="Nhà hàng hoạt động" :value="stats.active_restaurants" :icon="Building2" color="amber" class="" />
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <!-- Top Restaurants -->
            <SectionCard accent-color="emerald" class="">
                <div class="flex items-center gap-2 text-sm font-bold">
                    <Crown class="size-4 text-amber-500" />
                    Top nhà hàng theo doanh thu
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(r, idx) in revenueByRestaurant"
                        :key="r.id"
                        class="flex items-center gap-3 rounded-xl border border-border/30 p-3 transition-all duration-200 hover:bg-muted/20"
                    >
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-black text-primary">
                            {{ idx + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-medium">{{ r.name }}</p>
                                <span class="shrink-0 font-mono text-xs font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                    {{ formatVND(r.revenue) }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <ProgressBar :value="r.revenue" :max="maxRevenue" color="emerald" class="flex-1" />
                                <span class="text-[10px] text-muted-foreground">{{ r.orders_count }} đơn</span>
                            </div>
                        </div>
                    </div>
                    <p v-if="!revenueByRestaurant.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có dữ liệu doanh thu.</p>
                </div>
            </SectionCard>

            <!-- Daily Revenue Chart -->
            <SectionCard accent-color="sky" class="">
                <div class="text-sm font-bold">Doanh thu 30 ngày gần nhất</div>
                <div class="space-y-1">
                    <div
                        v-for="day in dailyRevenue.slice(-15)"
                        :key="day.date"
                        class="flex items-center gap-2"
                    >
                        <span class="w-20 shrink-0 text-[10px] font-mono text-muted-foreground">{{ day.date }}</span>
                        <ProgressBar :value="day.revenue" :max="Math.max(...dailyRevenue.map(d => d.revenue), 1)" color="sky" class="flex-1" />
                        <span class="w-24 shrink-0 text-right font-mono text-[10px] tabular-nums text-muted-foreground">{{ formatVND(day.revenue) }}</span>
                    </div>
                </div>
                <p v-if="!dailyRevenue.length" class="py-8 text-center text-sm text-muted-foreground">Chưa có dữ liệu.</p>
            </SectionCard>
        </div>
    </div>
</template>
