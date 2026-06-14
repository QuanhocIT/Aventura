<script setup lang="ts">
import { computed } from 'vue';
import { Package } from 'lucide-vue-next';
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
    const qtyList = topProductsList.value.map(p => p.quantity);
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
    <Card class="bg-card text-card-foreground border border-border shadow-sm">
        <CardHeader class="pb-2 border-b border-border/50">
            <CardTitle class="text-base font-bold flex items-center gap-2">
                <Package class="size-4 text-emerald-500" />
                Top 5 món bán chạy nhất (30 ngày qua)
            </CardTitle>
            <p class="text-xs text-muted-foreground mt-0.5">Xếp hạng theo số lượng phần ăn phục vụ thành công</p>
        </CardHeader>
        
        <CardContent class="pt-6 space-y-4">
            <div v-if="topProductsList.length" class="space-y-4">
                <div v-for="(p, idx) in topProductsList" :key="p.name" class="space-y-1.5 group">
                    <div class="flex items-center justify-between text-xs font-semibold">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold"
                                  :class="{
                                      'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400': idx === 0,
                                      'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': idx === 1,
                                      'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400': idx === 2,
                                      'bg-muted text-muted-foreground': idx > 2
                                  }"
                            >
                                {{ idx + 1 }}
                            </span>
                            <span class="truncate text-foreground group-hover:text-primary transition-colors">{{ p.name }}</span>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 font-mono text-muted-foreground">
                            <span class="font-bold text-foreground">{{ p.quantity }} phần</span>
                            <span>·</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ formatMoneyFull(p.revenue) }}</span>
                        </div>
                    </div>
                    <!-- Sleek Progress Bar -->
                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r"
                             :class="{
                                 'from-amber-400 to-amber-500': idx === 0,
                                 'from-indigo-400 to-indigo-500': idx === 1,
                                 'from-emerald-400 to-emerald-500': idx === 2,
                                 'from-slate-400 to-slate-500': idx > 2
                             }"
                             :style="{ width: `${(p.quantity / maxProductQty) * 100}%` }"
                        />
                    </div>
                </div>
            </div>
            
            <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground text-sm">
                <Package class="size-8 text-muted-foreground/30 mb-2" />
                Chưa có dữ liệu sản phẩm bán chạy.
            </div>
        </CardContent>
    </Card>
</template>
