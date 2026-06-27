<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { MessageSquare, Star, ThumbsUp, ThumbsDown, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, Pagination, AlertBanner } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    feedbacks: {
        data: Array<{
            id: number; customer_name: string; restaurant: string; restaurant_code: string;
            order_number: string | null; rating: number; comment: string | null;
            is_anonymous: boolean; created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: { total: number; avg_rating: number; positive: number; negative: number };
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; rating?: string; search?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id ?? '');
const rating = ref(props.filters.rating ?? '');
const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/feedback', {
        restaurant_id: restaurantId.value || undefined,
        rating: rating.value || undefined,
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
                    <SelectItem value="">Tất cả nhà hàng</SelectItem>
                    <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="rating" @update:model-value="applyFilter">
                <SelectTrigger class="w-[130px]">
                    <SelectValue placeholder="Tất cả sao" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
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
                <span :class="['text-sm tracking-wider', ratingColor[row.rating] ?? 'text-muted-foreground']">
                    {{ renderStars(row.rating) }}
                </span>
            </template>

            <template #cell-comment="{ row }">
                <p class="max-w-xs truncate text-sm text-muted-foreground">{{ row.comment || '—' }}</p>
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
