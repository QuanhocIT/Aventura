<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Banknote, Clock, Lightbulb, TrendingDown, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
    excellent: 'text-emerald-600 dark:text-emerald-400',
    normal: 'text-amber-500 dark:text-amber-400',
    high: 'text-rose-600 dark:text-rose-400',
};
const benchmarkBg: Record<string, string> = {
    excellent: 'bg-emerald-100/80 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300',
    normal: 'bg-amber-100/80 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300',
    high: 'bg-rose-100/80 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300',
};
const categoryColor: Record<string, string> = {
    spoilage: 'bg-orange-500', expired: 'bg-red-500', damaged: 'bg-yellow-500',
    cooking_loss: 'bg-blue-500', theft: 'bg-purple-500', other: 'bg-gray-400',
};

const maxTrend = Math.max(...props.trend.map(t => t.total_cost), 1);
</script>

<template>
    <Head title="Hao hụt & Lãng phí" />

    <div class="flex flex-col gap-5 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-border pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:text-rose-400">
                    <Trash2 class="size-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Quản lý Hao hụt & Lãng phí</h1>
                    <p class="text-sm text-muted-foreground">Dashboard phân tích, benchmark ngành F&B, và AI gợi ý giảm lãng phí.</p>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- 1. Tổng hao hụt -->
            <Card>
                <CardContent class="pt-4 pb-4 px-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng hao hụt ({{ days }}d)</span>
                        <AlertTriangle class="size-4 text-rose-500" />
                    </div>
                    <p class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ dashboard.total_waste_cost.toLocaleString() }}đ</p>
                    <p class="text-[10px] text-muted-foreground mt-1.5">{{ dashboard.waste_count }} lần ghi nhận hao hụt</p>
                </CardContent>
            </Card>

            <!-- 2. Tỷ lệ hao hụt -->
            <Card>
                <CardContent class="pt-4 pb-4 px-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tỷ lệ hao hụt</span>
                        <TrendingDown class="size-4 text-amber-500" />
                    </div>
                    <p :class="['text-xl font-bold', benchmarkColor[dashboard.benchmark_status] || 'text-foreground']">
                        {{ dashboard.waste_ratio }}%
                    </p>
                    <div class="mt-1.5 flex items-center">
                        <Badge :class="[benchmarkBg[dashboard.benchmark_status], 'text-[9px] px-1.5 py-0.5 font-semibold border-0']" variant="secondary">
                            {{ dashboard.benchmark_label }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- 3. Doanh thu -->
            <Card>
                <CardContent class="pt-4 pb-4 px-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Doanh thu ({{ days }}d)</span>
                        <Banknote class="size-4 text-emerald-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">{{ dashboard.total_revenue.toLocaleString() }}đ</p>
                    <p class="text-[10px] text-muted-foreground mt-1.5">Chuẩn F&B: 5-10% doanh thu</p>
                </CardContent>
            </Card>

            <!-- 4. Sắp hết hạn -->
            <Card>
                <CardContent class="pt-4 pb-4 px-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Sắp hết hạn</span>
                        <Clock class="size-4 text-amber-500" />
                    </div>
                    <p class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ expiring.length }} nguyên liệu</p>
                    <p class="text-[10px] text-muted-foreground mt-1.5">Trong vòng 3 ngày tới</p>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Phân loại nguyên nhân -->
            <Card>
                <CardContent class="pt-5 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Phân loại nguyên nhân</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Hao hụt theo danh mục phân loại.</p>
                    </div>
                    <div class="space-y-3 border-t border-border pt-4">
                        <div v-for="cat in dashboard.by_category" :key="cat.category" class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full" :class="categoryColor[cat.category] ?? 'bg-gray-400'"></span>
                                <span class="text-sm text-foreground font-medium">{{ cat.label }}</span>
                                <span class="text-xs text-muted-foreground">({{ cat.count }})</span>
                            </div>
                            <span class="text-sm font-bold text-foreground">{{ cat.total_cost.toLocaleString() }}đ</span>
                        </div>
                        <p v-if="!dashboard.by_category.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Trend chart (simple bar) -->
            <Card>
                <CardContent class="pt-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-foreground flex items-center gap-1.5">
                                <TrendingDown class="size-4 text-rose-500" />
                                Xu hướng hao hụt
                            </h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Biểu đồ tổng hợp 6 tháng gần nhất.</p>
                        </div>
                    </div>
                    <div class="border-t border-border pt-4">
                        <div class="flex items-end gap-1.5 h-32 px-1">
                            <div v-for="t in trend" :key="t.month" class="flex-1 flex flex-col items-center gap-2">
                                <div class="w-full bg-rose-500/80 dark:bg-rose-500/40 hover:bg-rose-500 transition-all rounded-t cursor-pointer"
                                    :style="{ height: (t.total_cost / maxTrend * 100) + '%', minHeight: '4px' }"
                                    :title="`${t.month}: ${t.total_cost.toLocaleString()}đ`"
                                ></div>
                                <span class="text-[9px] text-muted-foreground font-semibold whitespace-nowrap">{{ t.month.slice(5) }}</span>
                            </div>
                        </div>
                        <p v-if="!trend.length" class="text-sm text-muted-foreground text-center py-8">Chưa có dữ liệu trend</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Top waste ingredients -->
            <Card>
                <CardContent class="pt-5 space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Top nguyên liệu hao hụt</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">Nguyên liệu lãng phí nhiều nhất.</p>
                    </div>
                    <div class="space-y-2 border-t border-border pt-4">
                        <div v-for="(item, idx) in dashboard.top_ingredients.slice(0, 7)" :key="item.ingredient_id"
                            class="flex items-center justify-between text-sm py-0.5"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold text-muted-foreground w-4">{{ idx + 1 }}</span>
                                <span class="text-foreground font-medium">{{ item.name }}</span>
                            </div>
                            <span class="font-bold text-rose-500 dark:text-rose-400">{{ item.total_cost.toLocaleString() }}đ</span>
                        </div>
                        <p v-if="!dashboard.top_ingredients.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- AI Suggestions -->
        <Card>
            <CardContent class="pt-5 space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                        <Lightbulb class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">AI Gợi ý giảm hao hụt</h3>
                        <p class="text-xs text-muted-foreground">Khuyến nghị tự động từ hệ thống.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-border pt-4">
                    <div v-for="(s, idx) in suggestions" :key="idx"
                        class="rounded-xl border border-border p-4 space-y-1 hover:bg-muted/20 transition-all hover:-translate-y-0.5 duration-200"
                    >
                        <p class="font-semibold text-sm text-foreground flex items-center gap-1.5">
                            <span>{{ s.icon }}</span>
                            <span>{{ s.title }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground leading-relaxed">{{ s.description }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Expiring Soon -->
        <Card v-if="expiring.length">
            <CardContent class="pt-5 space-y-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
                        <AlertTriangle class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Nguyên liệu sắp hết hạn (2-3 ngày tới)</h3>
                        <p class="text-xs text-muted-foreground">Ưu tiên sử dụng hoặc ghi nhận hao hụt để tránh lãng phí.</p>
                    </div>
                </div>
                <div class="border-t border-border overflow-hidden rounded-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead>
                                <tr class="border-b bg-muted/40 text-muted-foreground font-semibold">
                                    <th class="px-4 py-3">Nguyên liệu</th>
                                    <th class="px-4 py-3">Lô hàng</th>
                                    <th class="px-4 py-3 text-right">Còn lại</th>
                                    <th class="px-4 py-3">Hết hạn</th>
                                    <th class="px-4 py-3 text-right">Còn lại</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="item in expiring" :key="item.id" class="hover:bg-muted/10 transition-colors">
                                    <td class="px-4 py-3 font-semibold text-foreground">{{ item.ingredient_name }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ item.batch_number || '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-foreground">{{ item.quantity_remaining }}</td>
                                    <td class="px-4 py-3 text-muted-foreground">{{ item.expiry_date }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <Badge :variant="item.days_left <= 1 ? 'destructive' : 'secondary'" class="text-[10px] px-2 py-0.5 font-semibold">
                                            {{ item.days_left }} ngày
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
