<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Users, UserCheck, Star, ShoppingCart, Search, Crown } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, StatusBadge, Pagination } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    customers: {
        data: Array<{
            id: number; name: string; phone: string | null; email: string | null;
            restaurant: string; restaurant_code: string;
            is_vip: boolean; loyalty_points: number;
            total_spent: number; last_order_at: string | null; created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: { total: number; vip: number; new_this_month: number; has_spent: number };
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; search?: string; vip?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id ?? '');
const search = ref(props.filters.search ?? '');
const vip = ref(props.filters.vip ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/customers', {
        restaurant_id: restaurantId.value || undefined,
        search: search.value || undefined,
        vip: vip.value || undefined,
    }, { preserveState: true, replace: true });
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN').format(val);
}

const columns: Column[] = [
    { key: 'name', label: 'Khách hàng' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'vip', label: 'VIP' },
    { key: 'total_spent', label: 'Tổng chi tiêu', align: 'right' },
    { key: 'loyalty_points', label: 'Điểm', align: 'center' },
    { key: 'last_order_at', label: 'Đơn gần nhất' },
    { key: 'created_at', label: 'Ngày tạo' },
];
</script>

<template>
    <Head title="Khách hàng toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Khách hàng toàn hệ thống"
            subtitle="Tổng quan khách hàng cross-restaurant, phân tích VIP và loyalty."
            :icon="Users"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Tổng khách hàng" :value="stats.total" :icon="Users" color="sky" class="" />
            <StatCard label="Khách VIP" :value="stats.vip" :icon="Crown" color="amber" class="" />
            <StatCard label="Mới tháng này" :value="stats.new_this_month" :icon="UserCheck" color="emerald" class="" />
            <StatCard label="Đã chi tiêu" :value="stats.has_spent" :icon="ShoppingCart" color="violet" class="" />
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm tên, SĐT, email..." class="pl-9" />
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
            <Select v-model="vip" @update:model-value="applyFilter">
                <SelectTrigger class="w-[130px]">
                    <SelectValue placeholder="Tất cả" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
                    <SelectItem value="1">Chỉ VIP</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="customers.data"
            :empty-icon="Users"
            empty-title="Không tìm thấy khách hàng nào"
            empty-description="Thử thay đổi bộ lọc."
            class=""
        >
            <template #cell-name="{ row }">
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p v-if="row.phone" class="text-xs text-muted-foreground">{{ row.phone }}</p>
                    <p v-if="row.email" class="text-xs text-muted-foreground">{{ row.email }}</p>
                </div>
            </template>

            <template #cell-restaurant="{ row }">
                <div>
                    <p class="text-sm font-medium">{{ row.restaurant }}</p>
                    <p class="font-mono text-[10px] text-muted-foreground">{{ row.restaurant_code }}</p>
                </div>
            </template>

            <template #cell-vip="{ row }">
                <StatusBadge v-if="row.is_vip" status="active" size="sm">VIP</StatusBadge>
                <span v-else class="text-xs text-muted-foreground">—</span>
            </template>

            <template #cell-total_spent="{ row }">
                <span class="font-mono font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ formatVND(row.total_spent) }}₫</span>
            </template>

            <template #cell-loyalty_points="{ row }">
                <span class="font-mono text-xs tabular-nums text-amber-600 dark:text-amber-400">{{ row.loyalty_points }}</span>
            </template>

            <template #cell-last_order_at="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.last_order_at ?? '—' }}</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.created_at }}</span>
            </template>

            <template #pagination>
                <Pagination v-if="customers.last_page > 1" :links="customers.links" />
            </template>
        </DataTable>
    </div>
</template>
