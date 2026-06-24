<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, BarChart3, Clock, Lightbulb, TrendingDown, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    dashboard: {
        total_waste_cost: number; total_revenue: number; waste_ratio: number;
        waste_count: number; benchmark_status: string; benchmark_label: string;
        by_category: { category: string; label: string; count: number; total_cost: number }[];
        top_ingredients: { ingredient_id: number; name: string; total_qty: number; total_cost: number; waste_count: number }[];
    };
    trend: { month: string; total_cost: number; count: number }[];
    suggestions: { type: string; icon: string; title: string; description: string }[];
    expiring: { id: number; ingredient_name: string; batch_number: string; quantity_remaining: number; expiry_date: string; days_left: number }[];
    days: number;
}>();

const benchmarkColor: Record<string, string> = {
    excellent: 'text-green-600', normal: 'text-amber-500', high: 'text-red-600',
};
const benchmarkBg: Record<string, string> = {
    excellent: 'bg-green-100 dark:bg-green-900/30', normal: 'bg-amber-100 dark:bg-amber-900/30', high: 'bg-red-100 dark:bg-red-900/30',
};
const categoryColor: Record<string, string> = {
    spoilage: 'bg-orange-500', expired: 'bg-red-500', damaged: 'bg-yellow-500',
    cooking_loss: 'bg-blue-500', theft: 'bg-purple-500', other: 'bg-gray-400',
};

const maxTrend = Math.max(...props.trend.map(t => t.total_cost), 1);
</script>

<template>
    <Head title="Hao hụt & Lãng phí" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600">
                <Trash2 class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Quản Lý Hao Hụt & Lãng Phí</h1>
                <p class="text-sm text-muted-foreground">Dashboard phân tích, benchmark ngành F&B, và AI gợi ý giảm lãng phí.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Tổng giá trị hao hụt ({{ days }}d)</p>
                    <p class="text-2xl font-bold text-rose-600">{{ dashboard.total_waste_cost.toLocaleString() }}đ</p>
                    <p class="text-xs text-muted-foreground">{{ dashboard.waste_count }} lần ghi nhận</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Tỷ lệ hao hụt / doanh thu</p>
                    <p :class="['text-2xl font-bold', benchmarkColor[dashboard.benchmark_status]]">{{ dashboard.waste_ratio }}%</p>
                    <Badge :class="benchmarkBg[dashboard.benchmark_status]" variant="secondary" class="mt-1 text-xs">
                        {{ dashboard.benchmark_label }}
                    </Badge>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-xs text-muted-foreground">Doanh thu ({{ days }}d)</p>
                    <p class="text-2xl font-bold">{{ dashboard.total_revenue.toLocaleString() }}đ</p>
                    <p class="text-xs text-muted-foreground">Chuẩn F&B: 5-10%</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4 flex items-center gap-3">
                    <Clock class="size-8 text-amber-500 shrink-0" />
                    <div>
                        <p class="text-xs text-muted-foreground">Sắp hết hạn (3 ngày)</p>
                        <p class="text-2xl font-bold text-amber-600">{{ expiring.length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Phân loại nguyên nhân -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold">Phân loại nguyên nhân hao hụt</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="cat in dashboard.by_category" :key="cat.category" class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 rounded-full" :class="categoryColor[cat.category] ?? 'bg-gray-400'"></span>
                            <span class="text-sm">{{ cat.label }}</span>
                            <span class="text-xs text-muted-foreground">({{ cat.count }})</span>
                        </div>
                        <span class="text-sm font-bold">{{ cat.total_cost.toLocaleString() }}đ</span>
                    </div>
                    <p v-if="!dashboard.by_category.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu</p>
                </CardContent>
            </Card>

            <!-- Trend chart (simple bar) -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2">
                        <TrendingDown class="size-4" /> Xu hướng hao hụt (6 tháng)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-1 h-32">
                        <div v-for="t in trend" :key="t.month" class="flex-1 flex flex-col items-center gap-1">
                            <div class="w-full bg-rose-400 dark:bg-rose-600 rounded-t"
                                :style="{ height: (t.total_cost / maxTrend * 100) + '%', minHeight: '4px' }"></div>
                            <span class="text-[9px] text-muted-foreground">{{ t.month.slice(5) }}</span>
                        </div>
                    </div>
                    <p v-if="!trend.length" class="text-sm text-muted-foreground text-center py-8">Chưa có dữ liệu trend</p>
                </CardContent>
            </Card>

            <!-- Top waste ingredients -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold">Top nguyên liệu hao hụt</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div v-for="(item, idx) in dashboard.top_ingredients.slice(0, 7)" :key="item.ingredient_id"
                        class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground w-4">{{ idx + 1 }}</span>
                            <span>{{ item.name }}</span>
                        </div>
                        <span class="font-bold text-rose-600">{{ item.total_cost.toLocaleString() }}đ</span>
                    </div>
                    <p v-if="!dashboard.top_ingredients.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu</p>
                </CardContent>
            </Card>
        </div>

        <!-- AI Suggestions -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2">
                    <Lightbulb class="size-5 text-amber-500" /> AI Gợi ý giảm hao hụt
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="(s, idx) in suggestions" :key="idx"
                        class="rounded-xl border p-4 space-y-1 hover:bg-muted/30 transition-colors">
                        <p class="font-semibold">{{ s.icon }} {{ s.title }}</p>
                        <p class="text-sm text-muted-foreground">{{ s.description }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Expiring Soon -->
        <Card v-if="expiring.length">
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2 text-amber-600">
                    <AlertTriangle class="size-5" /> Nguyên liệu sắp hết hạn (2-3 ngày tới)
                </CardTitle>
                <CardDescription>Ưu tiên sử dụng hoặc ghi nhận hao hụt để tránh lãng phí.</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-4 py-2">Nguyên liệu</th>
                            <th class="px-4 py-2">Lô hàng</th>
                            <th class="px-4 py-2 text-right">Còn lại</th>
                            <th class="px-4 py-2">Hết hạn</th>
                            <th class="px-4 py-2 text-right">Còn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in expiring" :key="item.id" class="border-b last:border-0">
                            <td class="px-4 py-2 font-medium">{{ item.ingredient_name }}</td>
                            <td class="px-4 py-2 text-xs text-muted-foreground">{{ item.batch_number || '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ item.quantity_remaining }}</td>
                            <td class="px-4 py-2 text-xs">{{ item.expiry_date }}</td>
                            <td class="px-4 py-2 text-right">
                                <Badge :variant="item.days_left <= 1 ? 'destructive' : 'secondary'" class="text-xs">
                                    {{ item.days_left }} ngày
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
