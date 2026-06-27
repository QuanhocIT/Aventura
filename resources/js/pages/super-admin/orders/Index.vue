<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShoppingCart, DollarSign, CheckCircle, XCircle, Search, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, StatusBadge, Pagination } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    orders: {
        data: Array<{
            id: number; order_number: string; restaurant: string; restaurant_code: string;
            status: string; payment_status: string; total_amount: string; total_raw: number;
            channel: string; note: string | null; created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    stats: { total_today: number; revenue_today: number; completed_today: number; cancelled_today: number };
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; status?: string; payment_status?: string; search?: string; date_from?: string; date_to?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id ?? '');
const status = ref(props.filters.status ?? '');
const paymentStatus = ref(props.filters.payment_status ?? '');
const search = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/orders', {
        restaurant_id: restaurantId.value || undefined,
        status: status.value || undefined,
        payment_status: paymentStatus.value || undefined,
        search: search.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
}

const columns: Column[] = [
    { key: 'order_number', label: 'Mã đơn' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'channel', label: 'Kênh' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'payment_status', label: 'Thanh toán' },
    { key: 'total_amount', label: 'Tổng tiền', align: 'right' },
    { key: 'created_at', label: 'Thời gian' },
];

const statusLabel: Record<string, string> = {
    pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', preparing: 'Đang chuẩn bị',
    ready: 'Sẵn sàng', completed: 'Hoàn thành', cancelled: 'Đã hủy',
};

const channelLabel: Record<string, string> = {
    pos: 'POS', qr: 'QR Order', online: 'Online', delivery: 'Giao hàng',
};
</script>

<template>
    <Head title="Đơn hàng toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Đơn hàng toàn hệ thống"
            subtitle="Xem và theo dõi tất cả đơn hàng từ mọi nhà hàng trong hệ thống."
            :icon="ShoppingCart"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Đơn hôm nay" :value="stats.total_today" :icon="ShoppingCart" color="sky" class="" />
            <StatCard label="Doanh thu hôm nay" :value="formatVND(stats.revenue_today)" :icon="DollarSign" color="emerald" class="" />
            <StatCard label="Hoàn thành" :value="stats.completed_today" :icon="CheckCircle" color="violet" class="" />
            <StatCard label="Đã hủy" :value="stats.cancelled_today" :icon="XCircle" color="rose" class="" />
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm mã đơn..." class="pl-9" />
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
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
                    <SelectItem value="pending">Chờ xử lý</SelectItem>
                    <SelectItem value="confirmed">Đã xác nhận</SelectItem>
                    <SelectItem value="preparing">Đang chuẩn bị</SelectItem>
                    <SelectItem value="completed">Hoàn thành</SelectItem>
                    <SelectItem value="cancelled">Đã hủy</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="paymentStatus" @update:model-value="applyFilter">
                <SelectTrigger class="w-[150px]">
                    <SelectValue placeholder="Thanh toán" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
                    <SelectItem value="paid">Đã thanh toán</SelectItem>
                    <SelectItem value="unpaid">Chưa thanh toán</SelectItem>
                </SelectContent>
            </Select>
            <Input v-model="dateFrom" type="date" class="w-[150px]" @change="applyFilter" />
            <Input v-model="dateTo" type="date" class="w-[150px]" @change="applyFilter" />
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="orders.data"
            :empty-icon="ShoppingCart"
            empty-title="Không tìm thấy đơn hàng nào"
            empty-description="Thử thay đổi bộ lọc."
            class=""
        >
            <template #cell-order_number="{ row }">
                <span class="font-mono font-bold text-foreground">#{{ row.order_number }}</span>
            </template>

            <template #cell-restaurant="{ row }">
                <div>
                    <p class="font-medium text-sm">{{ row.restaurant }}</p>
                    <p class="font-mono text-[10px] text-muted-foreground">{{ row.restaurant_code }}</p>
                </div>
            </template>

            <template #cell-channel="{ row }">
                <StatusBadge :status="row.channel" size="sm">
                    {{ channelLabel[row.channel] ?? row.channel }}
                </StatusBadge>
            </template>

            <template #cell-status="{ row }">
                <StatusBadge :status="row.status">
                    {{ statusLabel[row.status] ?? row.status }}
                </StatusBadge>
            </template>

            <template #cell-payment_status="{ row }">
                <StatusBadge :status="row.payment_status === 'paid' ? 'active' : 'pending'">
                    {{ row.payment_status === 'paid' ? 'Đã TT' : 'Chưa TT' }}
                </StatusBadge>
            </template>

            <template #cell-total_amount="{ row }">
                <span class="font-mono font-bold tabular-nums text-foreground">{{ row.total_amount }}₫</span>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.created_at }}</span>
            </template>

            <template #pagination>
                <Pagination v-if="orders.last_page > 1" :links="orders.links" />
            </template>
        </DataTable>
    </div>
</template>
