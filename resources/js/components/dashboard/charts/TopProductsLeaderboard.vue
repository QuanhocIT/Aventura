<script setup lang="ts">
import { Package } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface TopProductStat {
    name: string;
    quantity: number;
    revenue: number;
}

const props = defineProps<{
    topProductsChartData: TopProductStat[] | undefined;
}>();

const topProductsList = computed(() => props.topProductsChartData ?? []);

const maxProductQty = computed(() => {
    const qtyList = topProductsList.value.map((p) => p.quantity);

    return Math.max(...qtyList, 1);
});

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}
</script>

<template>
    <Card class="border border-border bg-card text-card-foreground shadow-sm">
        <CardHeader class="border-b border-border/50 pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-bold">
                <Package class="size-4 text-emerald-500" />
                Top 5 món bán chạy nhất (30 ngày qua)
            </CardTitle>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Xếp hạng theo số lượng phần ăn phục vụ thành công
            </p>
        </CardHeader>

        <CardContent class="space-y-4 pt-6">
            <div v-if="topProductsList.length" class="space-y-4">
                <div
                    v-for="(p, idx) in topProductsList"
                    :key="p.name"
                    class="group space-y-1.5"
                >
                    <div
                        class="flex items-center justify-between text-xs font-semibold"
                    >
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold"
                                :class="{
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400':
                                        idx === 0,
                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400':
                                        idx === 1,
                                    'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400':
                                        idx === 2,
                                    'bg-muted text-muted-foreground': idx > 2,
                                }"
                            >
                                {{ idx + 1 }}
                            </span>
                            <span
                                class="truncate text-foreground transition-colors group-hover:text-primary"
                                >{{ p.name }}</span
                            >
                        </div>
                        <div
                            class="flex shrink-0 items-center gap-3 font-mono text-muted-foreground"
                        >
                            <span class="font-bold text-foreground"
                                >{{ p.quantity }} phần</span
                            >
                            <span>·</span>
                            <span
                                class="font-bold text-emerald-600 dark:text-emerald-400"
                                >{{ formatMoneyFull(p.revenue) }}</span
                            >
                        </div>
                    </div>
                    <!-- Sleek Progress Bar -->
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-gradient-to-r transition-all duration-500"
                            :class="{
                                'from-amber-400 to-amber-500': idx === 0,
                                'from-indigo-400 to-indigo-500': idx === 1,
                                'from-emerald-400 to-emerald-500': idx === 2,
                                'from-slate-400 to-slate-500': idx > 2,
                            }"
                            :style="{
                                width: `${(p.quantity / maxProductQty) * 100}%`,
                            }"
                        />
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center justify-center py-8 text-sm text-muted-foreground"
            >
                <Package class="mb-1.5 size-6 text-muted-foreground/30" />
                Chưa có dữ liệu sản phẩm bán chạy.
            </div>
        </CardContent>
    </Card>
</template>
