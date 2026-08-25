<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    MessageSquare,
    Star,
    ThumbsUp,
    ThumbsDown,
    Search,
    Sparkles,
    TrendingUp,
    CheckCircle,
    XCircle,
    AlertCircle,
    Building2,
    Crown,
    Users,
    Tag,
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import AreaChart from '@/components/charts/AreaChart.vue';
import {
    PageHeader,
    StatCard,
    FilterBar,
    DataTable,
    Pagination,
    AlertBanner,
    SectionCard,
} from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    activeTab: 'platform' | 'customer';
    feedbacks: {
        data: Array<{
            id: number;
            user_name?: string;
            user_email?: string;
            user_role?: string;
            customer_name?: string;
            restaurant: string;
            restaurant_code: string;
            plan_name?: string;
            plan_code?: string;
            category?: string;
            category_label?: string;
            order_number?: string | null;
            rating: number;
            comment: string | null;
            sentiment: 'positive' | 'neutral' | 'negative' | null;
            is_anonymous?: boolean;
            created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: {
        total: number;
        avg_rating: number;
        positive: number;
        negative: number;
        text_sentiment_positive: number;
        text_sentiment_neutral: number;
        text_sentiment_negative: number;
    };
    ratingDistribution: Record<string, number>;
    dailySentiment: Array<{ label: string; value: number }>;
    aiInsights: {
        summary: string;
        strengths: string[];
        weaknesses: string[];
        recommendations: string[];
    };
    restaurants: Array<{ id: number; name: string; code: string }>;
    plans?: Array<{ id: number; code: string; name: string }>;
    filters: {
        restaurant_id?: string;
        rating?: string;
        search?: string;
        category?: string;
        plan_code?: string;
    };
}>();

const currentTab = ref<'platform' | 'customer'>(props.activeTab || 'platform');
const restaurantId = ref(props.filters.restaurant_id || 'all');
const rating = ref(props.filters.rating || 'all');
const category = ref(props.filters.category || 'all');
const planCode = ref(props.filters.plan_code || 'all');
const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 400);
});

function switchTab(tab: 'platform' | 'customer') {
    currentTab.value = tab;
    router.get(
        '/super-admin/feedback',
        {
            tab: tab,
        },
        { preserveState: false, replace: true },
    );
}

function applyFilter() {
    router.get(
        '/super-admin/feedback',
        {
            tab: currentTab.value,
            restaurant_id:
                restaurantId.value !== 'all' ? restaurantId.value : undefined,
            rating: rating.value !== 'all' ? rating.value : undefined,
            category: category.value !== 'all' ? category.value : undefined,
            plan_code: planCode.value !== 'all' ? planCode.value : undefined,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
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
    positive: {
        text: '😊 Tích cực',
        class: 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
    },
    neutral: {
        text: '😐 Trung tính',
        class: 'bg-slate-100 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 border border-slate-500/20',
    },
    negative: {
        text: '😟 Tiêu cực',
        class: 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-500/20',
    },
};

const categoryBadgeClass: Record<string, string> = {
    service_plan:
        'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
    pos_system:
        'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/20',
    customer_support:
        'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    feature_request:
        'bg-violet-500/10 text-violet-600 dark:text-violet-400 border-violet-500/20',
    general:
        'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
};

const platformColumns: Column[] = [
    { key: 'user_name', label: 'Chủ doanh nghiệp' },
    { key: 'restaurant', label: 'Nhà hàng / Doanh nghiệp' },
    { key: 'plan_name', label: 'Gói dịch vụ' },
    { key: 'category_label', label: 'Danh mục' },
    { key: 'rating', label: 'Đánh giá', align: 'center' },
    { key: 'comment', label: 'Nội dung phản hồi' },
    { key: 'created_at', label: 'Thời gian' },
];

const customerColumns: Column[] = [
    { key: 'customer_name', label: 'Khách hàng' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'rating', label: 'Đánh giá', align: 'center' },
    { key: 'comment', label: 'Nội dung' },
    { key: 'order_number', label: 'Đơn hàng' },
    { key: 'created_at', label: 'Thời gian' },
];

const activeColumns = computed(() =>
    currentTab.value === 'platform' ? platformColumns : customerColumns,
);
</script>

<template>
    <Head title="Phản hồi & Đánh giá toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Phản hồi & Đánh giá dịch vụ"
            subtitle="Tổng hợp đánh giá trải nghiệm từ các Chủ doanh nghiệp, Người dùng gói cước và Thực khách."
            :icon="MessageSquare"
        >
            <template #actions>
                <!-- Tab Selector Switcher -->
                <div
                    class="flex items-center rounded-xl border border-border/80 bg-muted/40 p-1 text-xs font-bold shadow-2xs"
                >
                    <button
                        type="button"
                        @click="switchTab('platform')"
                        :class="[
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 transition-all duration-200',
                            currentTab === 'platform'
                                ? 'scale-[1.02] bg-background text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        <Crown class="size-4 text-amber-500" />
                        Chủ doanh nghiệp & Gói dịch vụ
                        <Badge
                            variant="secondary"
                            class="h-4 rounded-sm border-none bg-amber-500/10 px-1 text-[9px] font-black text-amber-600"
                            >Mới</Badge
                        >
                    </button>

                    <button
                        type="button"
                        @click="switchTab('customer')"
                        :class="[
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-1.5 transition-all duration-200',
                            currentTab === 'customer'
                                ? 'scale-[1.02] bg-background text-foreground shadow-2xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        <Users class="size-4 text-sky-500" />
                        Thực khách tại nhà hàng
                    </button>
                </div>
            </template>
        </PageHeader>

        <AlertBanner
            v-if="stats.negative > 5"
            severity="warning"
            :title="`${stats.negative} đánh giá tiêu cực cần chú ý`"
            :message="
                currentTab === 'platform'
                    ? 'Có một số phản hồi không hài lòng từ chủ doanh nghiệp về gói dịch vụ hoặc kỹ thuật. Cần kiểm tra và liên hệ chăm sóc.'
                    : 'Có nhiều feedback 1-2 sao từ thực khách. Cần theo dõi và hỗ trợ các nhà hàng liên quan.'
            "
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                :label="
                    currentTab === 'platform'
                        ? 'Đánh giá từ Doanh nghiệp'
                        : 'Phản hồi thực khách'
                "
                :value="stats.total"
                :icon="MessageSquare"
                color="sky"
            />
            <StatCard
                label="Đánh giá trung bình"
                :value="`${stats.avg_rating}/5`"
                :icon="Star"
                color="amber"
            />
            <StatCard
                label="Hài lòng (4-5★)"
                :value="stats.positive"
                :icon="ThumbsUp"
                color="emerald"
            />
            <StatCard
                label="Cần cải thiện (1-2★)"
                :value="stats.negative"
                :icon="ThumbsDown"
                color="rose"
            />
        </div>

        <!-- Analytics & AI Insights Row -->
        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
            <!-- Sentiment Trend & AI Insights -->
            <SectionCard
                accent-color="emerald"
                class="flex flex-col justify-between"
            >
                <div>
                    <div
                        class="mb-4 flex items-center justify-between border-b border-border/40 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <TrendingUp class="size-4 text-emerald-500" />
                                {{
                                    currentTab === 'platform'
                                        ? 'Phân tích Đánh giá Nền tảng & Gói cước'
                                        : 'Xu hướng Phản hồi Thực khách'
                                }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Điểm số trung bình theo ngày và tóm tắt AI
                                Insights từ phản hồi thực tế
                            </p>
                        </div>
                        <span
                            class="flex items-center gap-1 rounded bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            <Sparkles class="size-3" /> AI Powered
                        </span>
                    </div>

                    <!-- Area Chart of Daily average rating -->
                    <div class="flex h-44 items-end">
                        <AreaChart
                            v-if="dailySentiment && dailySentiment.length > 0"
                            :series="dailySentiment"
                            gradient-id="sentimentTrendGrad"
                            color="#10b981"
                            class="h-full w-full"
                        >
                            <template #tooltip="{ point }">
                                <div
                                    class="flex flex-col gap-0.5 text-[10px] font-bold text-foreground"
                                >
                                    <span
                                        class="font-mono text-[8px] tracking-wider text-muted-foreground uppercase"
                                        >{{ point.label }}</span
                                    >
                                    <span>Đánh giá TB: {{ point.value }}★</span>
                                </div>
                            </template>
                        </AreaChart>
                        <div
                            v-else
                            class="w-full py-10 text-center text-xs text-muted-foreground"
                        >
                            Chưa có dữ liệu xu hướng
                        </div>
                    </div>

                    <!-- AI Insights Block -->
                    <div
                        class="mt-5 space-y-3.5 rounded-xl border border-border/40 bg-secondary/15 p-4"
                    >
                        <div class="flex items-start gap-2.5">
                            <Sparkles
                                class="mt-0.5 size-5 shrink-0 animate-pulse text-amber-500"
                            />
                            <div class="space-y-1">
                                <h4 class="text-xs font-bold text-foreground">
                                    Tóm tắt AI ({{
                                        currentTab === 'platform'
                                            ? 'Hài lòng Chủ Doanh Nghiệp'
                                            : 'Cảm xúc Thực khách'
                                    }})
                                </h4>
                                <p
                                    class="text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ aiInsights.summary }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid gap-3 border-t border-border/30 pt-2 sm:grid-cols-2"
                        >
                            <!-- Điểm mạnh -->
                            <div class="space-y-1.5">
                                <h5
                                    class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    <CheckCircle class="size-3.5" /> Điểm đánh
                                    giá cao
                                </h5>
                                <ul
                                    class="list-inside list-disc space-y-1 text-[10px] text-muted-foreground"
                                >
                                    <li
                                        v-for="(
                                            str, idx
                                        ) in aiInsights.strengths"
                                        :key="idx"
                                    >
                                        {{ str }}
                                    </li>
                                </ul>
                            </div>
                            <!-- Điểm yếu -->
                            <div class="space-y-1.5">
                                <h5
                                    class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-rose-500 uppercase"
                                >
                                    <XCircle class="size-3.5" /> Yêu cầu / Phàn
                                    nàn chính
                                </h5>
                                <ul
                                    class="list-inside list-disc space-y-1 text-[10px] text-muted-foreground"
                                >
                                    <li
                                        v-for="(
                                            weak, idx
                                        ) in aiInsights.weaknesses"
                                        :key="idx"
                                    >
                                        {{ weak }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Đề xuất CSKH -->
                        <div class="space-y-1 border-t border-border/30 pt-2.5">
                            <h5
                                class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-amber-500 uppercase"
                            >
                                <AlertCircle class="size-3.5" /> Đề xuất tối ưu
                                dịch vụ
                            </h5>
                            <ul
                                class="list-inside space-y-1.5 pl-1 text-[10px] text-muted-foreground"
                            >
                                <li
                                    v-for="(
                                        rec, idx
                                    ) in aiInsights.recommendations"
                                    :key="idx"
                                    class="flex items-start gap-1.5"
                                >
                                    <span class="shrink-0 text-amber-500"
                                        >•</span
                                    >
                                    <span>{{ rec }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </SectionCard>

            <!-- Stars Rating Distribution Progress Bars -->
            <SectionCard
                accent-color="violet"
                class="flex flex-col justify-between"
            >
                <div>
                    <div
                        class="mb-4 flex items-center justify-between border-b border-border/40 pb-2"
                    >
                        <div class="space-y-1">
                            <h3
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <Star class="size-4 text-violet-500" /> Phân bổ
                                Sao Đánh giá
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Tỷ lệ mức độ hài lòng từ 1 đến 5 sao
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-4.5">
                        <div
                            v-for="star in ['5', '4', '3', '2', '1']"
                            :key="star"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-semibold"
                            >
                                <span
                                    class="flex items-center gap-1 font-bold text-foreground"
                                >
                                    {{ star }} ★
                                </span>
                                <span
                                    class="text-muted-foreground tabular-nums"
                                >
                                    {{ ratingDistribution[star] || 0 }} phản hồi
                                    ({{
                                        stats.total > 0
                                            ? Math.round(
                                                  ((ratingDistribution[star] ||
                                                      0) /
                                                      stats.total) *
                                                      100,
                                              )
                                            : 0
                                    }}%)
                                </span>
                            </div>
                            <div
                                class="h-2 w-full overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="{
                                        'bg-emerald-500': star === '5',
                                        'bg-teal-400': star === '4',
                                        'bg-amber-400': star === '3',
                                        'bg-rose-400': star === '2',
                                        'bg-rose-600': star === '1',
                                    }"
                                    :style="{
                                        width: `${stats.total > 0 ? ((ratingDistribution[star] || 0) / stats.total) * 100 : 0}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </SectionCard>
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search
                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Tìm theo nội dung đánh giá..."
                    class="pl-9"
                />
            </div>

            <!-- Category filter for Platform Feedback -->
            <Select
                v-if="currentTab === 'platform'"
                v-model="category"
                @update:model-value="applyFilter"
            >
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Tất cả danh mục" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả danh mục</SelectItem>
                    <SelectItem value="service_plan"
                        >Gói dịch vụ & Giá cước</SelectItem
                    >
                    <SelectItem value="pos_system"
                        >Vận hành POS & QR</SelectItem
                    >
                    <SelectItem value="customer_support"
                        >Hỗ trợ & Kỹ thuật</SelectItem
                    >
                    <SelectItem value="feature_request"
                        >Yêu cầu tính năng</SelectItem
                    >
                    <SelectItem value="general">Góp ý chung</SelectItem>
                </SelectContent>
            </Select>

            <!-- Plan filter for Platform Feedback -->
            <Select
                v-if="currentTab === 'platform' && plans"
                v-model="planCode"
                @update:model-value="applyFilter"
            >
                <SelectTrigger class="w-[150px]">
                    <SelectValue placeholder="Tất cả gói cước" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả gói cước</SelectItem>
                    <SelectItem v-for="p in plans" :key="p.id" :value="p.code">
                        Gói {{ p.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="restaurantId" @update:model-value="applyFilter">
                <SelectTrigger class="w-[180px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Tất cả nhà hàng</SelectItem>
                    <SelectItem
                        v-for="r in restaurants"
                        :key="r.id"
                        :value="String(r.id)"
                        >{{ r.name }}</SelectItem
                    >
                </SelectContent>
            </Select>

            <Select v-model="rating" @update:model-value="applyFilter">
                <SelectTrigger class="w-[120px]">
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
            :columns="activeColumns"
            :rows="feedbacks.data"
            :empty-icon="MessageSquare"
            empty-title="Chưa có đánh giá nào"
            empty-description="Khi có đánh giá mới từ chủ doanh nghiệp hoặc khách hàng, chúng sẽ hiển thị ở đây."
        >
            <!-- User / Owner Column (Platform Tab) -->
            <template #cell-user_name="{ row }">
                <div class="flex flex-col">
                    <span
                        class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                    >
                        <Users class="size-3.5 text-indigo-500" />
                        {{ row.user_name }}
                    </span>
                    <span class="font-mono text-[11px] text-muted-foreground">
                        {{ row.user_email }}
                    </span>
                </div>
            </template>

            <!-- Customer Column (Customer Tab) -->
            <template #cell-customer_name="{ row }">
                <span
                    :class="
                        row.is_anonymous
                            ? 'text-muted-foreground italic'
                            : 'font-medium'
                    "
                >
                    {{ row.customer_name }}
                </span>
            </template>

            <!-- Restaurant Column -->
            <template #cell-restaurant="{ row }">
                <div>
                    <p class="flex items-center gap-1 text-sm font-semibold">
                        <Building2 class="size-3.5 text-muted-foreground" />
                        {{ row.restaurant }}
                    </p>
                    <p
                        v-if="row.restaurant_code"
                        class="pl-4 font-mono text-[10px] text-muted-foreground"
                    >
                        {{ row.restaurant_code }}
                    </p>
                </div>
            </template>

            <!-- Plan Name Column (Platform Tab) -->
            <template #cell-plan_name="{ row }">
                <Badge
                    variant="outline"
                    class="rounded-full px-2.5 py-0.5 font-mono text-[10px] font-extrabold uppercase"
                    :class="{
                        'border-indigo-500/30 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400':
                            row.plan_code === 'pro',
                        'border-violet-500/30 bg-violet-500/10 text-violet-600 dark:text-violet-400':
                            row.plan_code === 'enterprise' ||
                            row.plan_code === 'ultra',
                        'border-slate-500/30 bg-slate-500/10 text-slate-600 dark:text-slate-400':
                            row.plan_code === 'free',
                    }"
                >
                    Gói {{ row.plan_name }}
                </Badge>
            </template>

            <!-- Category Label Column (Platform Tab) -->
            <template #cell-category_label="{ row }">
                <span
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[10px] font-bold"
                    :class="categoryBadgeClass[row.category || 'general']"
                >
                    <Tag class="size-3" />
                    {{ row.category_label }}
                </span>
            </template>

            <!-- Rating Column -->
            <template #cell-rating="{ row }">
                <span
                    :class="[
                        'text-sm font-bold tracking-wider',
                        ratingColor[row.rating] ?? 'text-muted-foreground',
                    ]"
                >
                    {{ renderStars(row.rating) }}
                </span>
            </template>

            <!-- Comment Column -->
            <template #cell-comment="{ row }">
                <p
                    class="max-w-md text-sm leading-relaxed break-words text-foreground/90"
                >
                    {{ row.comment || '—' }}
                    <span
                        v-if="row.sentiment"
                        class="ml-1.5 inline-flex items-center rounded-full px-2 py-0.5 align-middle text-[9px] font-extrabold tracking-wider uppercase"
                        :class="sentimentConfig[row.sentiment]?.class"
                    >
                        {{ sentimentConfig[row.sentiment]?.text }}
                    </span>
                </p>
            </template>

            <template #cell-order_number="{ row }">
                <span
                    v-if="row.order_number"
                    class="font-mono text-xs font-bold"
                    >#{{ row.order_number }}</span
                >
                <span v-else class="text-xs text-muted-foreground">—</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="font-mono text-xs text-muted-foreground">{{
                    row.created_at
                }}</span>
            </template>

            <template #pagination>
                <Pagination
                    v-if="feedbacks.last_page > 1"
                    :links="feedbacks.links"
                />
            </template>
        </DataTable>
    </div>
</template>
