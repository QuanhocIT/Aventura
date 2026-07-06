<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MessageSquare, Star, ThumbsUp, ThumbsDown, Search, Sparkles, TrendingUp, CheckCircle, XCircle, AlertCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, Pagination, AlertBanner, ProgressBar, SectionCard } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';
import AreaChart from '@/components/charts/AreaChart.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    feedbacks: {
        data: Array<{
            id: number; customer_name: string; restaurant: string; restaurant_code: string;
            order_number: string | null; rating: number; comment: string | null;
            sentiment: 'positive' | 'neutral' | 'negative' | null;
            is_anonymous: boolean; created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: {
        total: number; avg_rating: number; positive: number; negative: number;
        text_sentiment_positive: number; text_sentiment_neutral: number; text_sentiment_negative: number;
    };
    ratingDistribution: Record<string, number>;
    dailySentiment: Array<{ label: string; value: number }>;
    aiInsights: { summary: string; strengths: string[]; weaknesses: string[]; recommendations: string[] };
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; rating?: string; search?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id || 'all');
const rating = ref(props.filters.rating || 'all');
const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/feedback', {
        restaurant_id: restaurantId.value !== 'all' ? restaurantId.value : undefined,
        rating: rating.value !== 'all' ? rating.value : undefined,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

function renderStars(r: number) {
    return '★'.repeat(r) + '☆'.repeat(5 - r);
}

const ratingColor: Record<number, string> = {
    1: 'text-rose-500',
    2: 'text-rose-400',
    3: 'text-amber-500',
    4: 'text-emerald-500',
    5: 'text-emerald-400',
};

const sentimentConfig: Record<string, { text: string; class: string }> = {
    positive: { text: '😊 Tích cực', class: 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' },
    neutral: { text: '😐 Trung tính', class: 'bg-slate-100 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400' },
    negative: { text: '😟 Tiêu cực', class: 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' },
};

const columns: Column[] = [
    { key: 'customer_name', label: 'Khách hàng' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'rating', label: 'Đánh giá', align: 'center' },
    { key: 'comment', label: 'Nội dung' },
    { key: 'order_number', label: 'Đơn hàng' },
    { key: 'created_at', label: 'Thời gian' },
];
</script>

<template>
    <Head title="Feedback khách hàng toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Feedback khách hàng"
            subtitle="Tổng hợp đánh giá và phản hồi từ tất cả nhà hàng trong hệ thống."
            :icon="MessageSquare"
        />

        <AlertBanner
            v-if="stats.negative > 5"
            severity="warning"
            :title="`${stats.negative} đánh giá tiêu cực cần chú ý`"
            message="Có nhiều feedback 1-2 sao. Cần theo dõi và hỗ trợ các nhà hàng liên quan."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Tổng feedback" :value="stats.total" :icon="MessageSquare" color="sky" class="" />
            <StatCard label="Đánh giá TB" :value="`${stats.avg_rating}/5`" :icon="Star" color="amber" class="" />
            <StatCard label="Tích cực (4-5★)" :value="stats.positive" :icon="ThumbsUp" color="emerald" class="" />
            <StatCard label="Tiêu cực (1-2★)" :value="stats.negative" :icon="ThumbsDown" color="rose" class="" />
        </div>

        <!-- Analytics & AI Insights Row -->
        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <!-- Sentiment Trend & AI Insights -->
            <SectionCard accent-color="emerald" class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-border/40 pb-2 mb-4">
                        <div class="space-y-1">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-1.5">
                                <TrendingUp class="size-4 text-emerald-500" /> Xu hướng & Phân tích Đánh giá
                            </h3>
                            <p class="text-xs text-muted-foreground">Điểm số trung bình theo ngày và tóm tắt AI Insights</p>
                        </div>
                        <span class="rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                            <Sparkles class="size-3" /> AI Powered
                        </span>
                    </div>

                    <!-- Area Chart of Daily average rating -->
                    <div class="h-44 flex items-end">
                        <AreaChart
                            v-if="dailySentiment && dailySentiment.length > 0"
                            :series="dailySentiment"
                            gradient-id="sentimentTrendGrad"
                            color="#10b981"
                            class="w-full h-full"
                        >
                            <template #tooltip="{ point }">
                                <div class="flex flex-col gap-0.5 text-[10px] font-bold text-foreground">
                                    <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">{{ point.label }}</span>
                                    <span>Đánh giá TB: {{ point.value }}★</span>
                                </div>
                            </template>
                        </AreaChart>
                        <div v-else class="w-full text-center py-10 text-xs text-muted-foreground">
                            Không có dữ liệu xu hướng
                        </div>
                    </div>

                    <!-- AI Insights Block -->
                    <div class="mt-5 p-4 bg-secondary/15 rounded-xl border border-border/40 space-y-3.5">
                        <div class="flex items-start gap-2.5">
                            <Sparkles class="size-5 text-amber-500 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <h4 class="font-bold text-xs text-foreground">Tóm tắt AI (Phản hồi & Cảm xúc)</h4>
                                <p class="text-xs text-muted-foreground leading-relaxed">{{ aiInsights.summary }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 pt-2 border-t border-border/30">
                            <!-- Điểm mạnh -->
                            <div class="space-y-1.5">
                                <h5 class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                    <CheckCircle class="size-3.5" /> Điểm mạnh nổi bật
                                </h5>
                                <ul class="list-disc list-inside text-[10px] text-muted-foreground space-y-1">
                                    <li v-for="(str, idx) in aiInsights.strengths" :key="idx">{{ str }}</li>
                                </ul>
                            </div>
                            <!-- Điểm yếu -->
                            <div class="space-y-1.5">
                                <h5 class="text-[10px] font-bold uppercase tracking-wider text-rose-500 flex items-center gap-1">
                                    <XCircle class="size-3.5" /> Điểm phàn nàn chính
                                </h5>
                                <ul class="list-disc list-inside text-[10px] text-muted-foreground space-y-1">
                                    <li v-for="(weak, idx) in aiInsights.weaknesses" :key="idx">{{ weak }}</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Đề xuất CSKH -->
                        <div class="pt-2.5 border-t border-border/30 space-y-1">
                            <h5 class="text-[10px] font-bold uppercase tracking-wider text-amber-500 flex items-center gap-1">
                                <AlertCircle class="size-3.5" /> Đề xuất hành động CSKH gợi ý
                            </h5>
                            <ul class="list-inside text-[10px] text-muted-foreground space-y-1.5 pl-1">
                                <li v-for="(rec, idx) in aiInsights.recommendations" :key="idx" class="flex items-start gap-1.5">
                                    <span class="text-amber-500 shrink-0">•</span>
                                    <span>{{ rec }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Stars Rating Distribution Progress Bars -->
            <SectionCard accent-color="violet" class="flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-border/40 pb-2 mb-4">
                        <div class="space-y-1">
                            <h3 class="font-bold text-sm text-foreground flex items-center gap-1.5">
                                <Star class="size-4 text-violet-500" /> Phân bổ Sao Đánh giá
                            </h3>
                            <p class="text-xs text-muted-foreground">Tỷ lệ số sao được đánh giá trong hệ thống</p>
                        </div>
                    </div>

                    <div class="space-y-4.5 mt-6">
                        <div v-for="star in ['5', '4', '3', '2', '1']" :key="star" class="space-y-1">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span class="text-foreground flex items-center gap-1 font-bold">
                                    {{ star }} ★
                                </span>
                                <span class="text-muted-foreground tabular-nums">
                                    {{ ratingDistribution[star] || 0 }} phản hồi 
                                    ({{ stats.total > 0 ? Math.round(((ratingDistribution[star] || 0) / stats.total) * 100) : 0 }}%)
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-secondary overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-emerald-500': star === '5',
                                        'bg-teal-400': star === '4',
                                        'bg-amber-400': star === '3',
                                        'bg-rose-400': star === '2',
                                        'bg-rose-600': star === '1'
                                    }"
                                    :style="{ width: `${stats.total > 0 ? ((ratingDistribution[star] || 0) / stats.total) * 100 : 0}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm nội dung feedback..." class="pl-9" />
            </div>
            <Select v-model="restaurantId" @update:model-value="applyFilter">
                <SelectTrigger class="w-[200px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                    <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="rating" @update:model-value="applyFilter">
                <SelectTrigger class="w-[130px]">
                    <SelectValue placeholder="Tất cả sao" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả sao</SelectItem>
                    <SelectItem value="5">5 ★</SelectItem>
                    <SelectItem value="4">4 ★</SelectItem>
                    <SelectItem value="3">3 ★</SelectItem>
                    <SelectItem value="2">2 ★</SelectItem>
                    <SelectItem value="1">1 ★</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="feedbacks.data"
            :empty-icon="MessageSquare"
            empty-title="Chưa có feedback nào"
            empty-description="Khi khách hàng gửi đánh giá, chúng sẽ hiển thị ở đây."
            class=""
        >
            <template #cell-customer_name="{ row }">
                <span :class="row.is_anonymous ? 'italic text-muted-foreground' : 'font-medium'">
                    {{ row.customer_name }}
                </span>
            </template>

            <template #cell-restaurant="{ row }">
                <div>
                    <p class="text-sm font-medium">{{ row.restaurant }}</p>
                    <p class="font-mono text-[10px] text-muted-foreground">{{ row.restaurant_code }}</p>
                </div>
            </template>

            <template #cell-rating="{ row }">
                <span :class="['text-sm tracking-wider font-bold', ratingColor[row.rating] ?? 'text-muted-foreground']">
                    {{ renderStars(row.rating) }}
                </span>
            </template>

            <template #cell-comment="{ row }">
                <p class="max-w-md break-words text-sm text-foreground/90">
                    {{ row.comment || '—' }}
                    <span
                        v-if="row.sentiment"
                        class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold align-middle"
                        :class="sentimentConfig[row.sentiment]?.class"
                    >
                        {{ sentimentConfig[row.sentiment]?.text }}
                    </span>
                </p>
            </template>

            <template #cell-order_number="{ row }">
                <span v-if="row.order_number" class="font-mono text-xs">#{{ row.order_number }}</span>
                <span v-else class="text-xs text-muted-foreground">—</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.created_at }}</span>
            </template>

            <template #pagination>
                <Pagination v-if="feedbacks.last_page > 1" :links="feedbacks.links" />
            </template>
        </DataTable>
    </div>
</template>
