<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Package, Clock, CheckCircle, Truck, Archive, Search, X, ChevronRight, RefreshCw } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import echo from '@/lib/echo';
import { usePage } from '@inertiajs/vue3';

interface Unit { id: number; name: string; symbol: string }
interface CatalogItem { id: number; name: string; packaging_spec: string | null }
interface PurchaseOrderItem {
    id: number;
    catalog_item: CatalogItem | null;
    quantity: string;
    unit_price: string;
    total_price: string;
    unit: Unit | null;
}
interface PurchaseOrder {
    id: number;
    po_number: string;
    status: string;
    total_amount: string;
    expected_delivery_date: string | null;
    note: string | null;
    supplier_note: string | null;
    created_at: string;
    received_at: string | null;
    preparing_at: string | null;
    in_transit_at: string | null;
    delivered_at: string | null;
    items: PurchaseOrderItem[];
    branch: { id: number; name: string } | null;
}
interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}
interface Props {
    orders: Paginator<PurchaseOrder>;
    statusCounts: Record<string, number>;
    statusLabels: Record<string, string>;
    transitions: Record<string, string>;
    filters: { status: string; search: string };
}

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => (page.props.auth?.user as any) ?? null);

const statusIcons: Record<string, any> = {
    pending: Clock,
    received: CheckCircle,
    preparing: Package,
    in_transit: Truck,
    delivered: Archive,
    cancelled: X,
};

const statusColors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    received: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    preparing: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    in_transit: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    delivered: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

// Filters
const searchInput = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);

function applyFilters() {
    router.get('/supplier-portal/purchase-orders', {
        status: selectedStatus.value || undefined,
        search: searchInput.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    searchInput.value = '';
    selectedStatus.value = '';
    router.get('/supplier-portal/purchase-orders', {}, { preserveState: false });
}

// Detail modal / advance status
const selectedOrder = ref<PurchaseOrder | null>(null);
const advanceForm = useForm({ note: '' });

function openOrder(order: PurchaseOrder) {
    selectedOrder.value = order;
    advanceForm.reset();
}

function closeOrder() {
    selectedOrder.value = null;
}

function advanceStatus() {
    if (!selectedOrder.value) return;
    advanceForm.post(`/supplier-portal/purchase-orders/${selectedOrder.value.id}/advance`, {
        onSuccess: () => {
            closeOrder();
            toast.success('Đã cập nhật trạng thái đơn hàng.');
            router.reload({ only: ['orders', 'statusCounts'] });
        },
        onError: () => {
            toast.error('Không thể cập nhật trạng thái.');
        },
    });
}

function formatCurrency(v: string | number) {
    return Number(v).toLocaleString('vi-VN') + ' ₫';
}

function formatDate(v: string | null) {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Real-time: lắng nghe PO mới từ nhà hàng
const orders = ref(props.orders);
let channel: ReturnType<typeof echo.private> | null = null;

onMounted(() => {
    const supplierId = user.value?.supplier_id;
    if (!supplierId) return;

    channel = echo.private(`supplier.${supplierId}`);
    channel.listen('.purchase-order.created', (data: any) => {
        toast.info(`Đơn hàng mới: ${data.po_number}`, {
            description: `Từ nhà hàng — ${formatCurrency(data.total_amount)}`,
        });
        router.reload({ only: ['orders', 'statusCounts'] });
    });
});

onUnmounted(() => {
    const supplierId = user.value?.supplier_id;
    if (supplierId && channel) {
        echo.leave(`supplier.${supplierId}`);
    }
});

const statusTabs = computed(() => {
    const all = [
        { key: '', label: 'Tất cả' },
        { key: 'pending', label: props.statusLabels['pending'] },
        { key: 'received', label: props.statusLabels['received'] },
        { key: 'preparing', label: props.statusLabels['preparing'] },
        { key: 'in_transit', label: props.statusLabels['in_transit'] },
        { key: 'delivered', label: props.statusLabels['delivered'] },
    ];
    return all;
});
</script>

<template>
    <Head title="Đơn đặt hàng | Supplier Portal" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Đơn đặt hàng</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Tiếp nhận và xử lý đơn hàng từ nhà hàng theo workflow chuẩn.
                </p>
            </div>
            <button
                @click="router.reload({ only: ['orders', 'statusCounts'] })"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors"
            >
                <RefreshCw class="size-4" />
                Làm mới
            </button>
        </div>

        <!-- Status count cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div
                v-for="(label, key) in statusLabels"
                :key="key"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center cursor-pointer hover:border-blue-400 transition-colors"
                :class="{ 'border-blue-500 ring-1 ring-blue-500': selectedStatus === key }"
                @click="selectedStatus = key; applyFilters()"
            >
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ statusCounts[key] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ label }}</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-3 items-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
            <!-- Status tabs -->
            <div class="flex gap-1 flex-wrap">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    @click="selectedStatus = tab.key; applyFilters()"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors"
                    :class="selectedStatus === tab.key
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                >{{ tab.label }}</button>
            </div>

            <!-- Search -->
            <div class="relative ml-auto">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" />
                <input
                    v-model="searchInput"
                    type="text"
                    placeholder="Tìm theo số PO..."
                    class="pl-9 pr-8 py-1.5 text-sm border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                    @keyup.enter="applyFilters"
                />
                <button v-if="searchInput" @click="searchInput=''; applyFilters()" class="absolute right-2 top-1/2 -translate-y-1/2">
                    <X class="size-4 text-gray-400" />
                </button>
            </div>
        </div>

        <!-- Orders table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Số PO</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Trạng thái</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tổng tiền</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày giao dự kiến</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ngày tạo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr v-if="orders.data.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                            Chưa có đơn hàng nào.
                        </td>
                    </tr>
                    <tr
                        v-for="order in orders.data"
                        :key="order.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors"
                        @click="openOrder(order)"
                    >
                        <td class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-white">{{ order.po_number }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium" :class="statusColors[order.status]">
                                <component :is="statusIcons[order.status]" class="size-3" />
                                {{ statusLabels[order.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                            {{ formatCurrency(order.total_amount) }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDate(order.expected_delivery_date) }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <ChevronRight class="size-4 text-gray-400 ml-auto" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="orders.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-1 justify-end">
                <template v-for="link in orders.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.get(link.url, {}, { preserveState: true })"
                        class="px-3 py-1 rounded text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400'"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1 rounded text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <Teleport to="body">
        <div
            v-if="selectedOrder"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            @click.self="closeOrder"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900 z-10">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white font-mono">{{ selectedOrder.po_number }}</h2>
                        <span class="inline-flex items-center gap-1.5 mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusColors[selectedOrder.status]">
                            <component :is="statusIcons[selectedOrder.status]" class="size-3" />
                            {{ statusLabels[selectedOrder.status] }}
                        </span>
                    </div>
                    <button @click="closeOrder" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X class="size-5 text-gray-500" />
                    </button>
                </div>

                <div class="px-6 py-4 space-y-5">
                    <!-- Info -->
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Chi nhánh</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ selectedOrder.branch?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Ngày giao dự kiến</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(selectedOrder.expected_delivery_date) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Ngày tạo</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(selectedOrder.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500 dark:text-gray-400">Tổng tiền</p>
                            <p class="font-bold text-blue-600 dark:text-blue-400">{{ formatCurrency(selectedOrder.total_amount) }}</p>
                        </div>
                    </div>

                    <!-- Note from restaurant -->
                    <div v-if="selectedOrder.note" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 text-sm text-yellow-800 dark:text-yellow-300">
                        <p class="font-semibold mb-1">Ghi chú từ nhà hàng:</p>
                        <p>{{ selectedOrder.note }}</p>
                    </div>

                    <!-- Items -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Danh sách hàng hoá</h3>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="text-left px-3 py-2 text-xs text-gray-500 font-medium">Mặt hàng</th>
                                        <th class="text-right px-3 py-2 text-xs text-gray-500 font-medium">Số lượng</th>
                                        <th class="text-right px-3 py-2 text-xs text-gray-500 font-medium">Đơn giá</th>
                                        <th class="text-right px-3 py-2 text-xs text-gray-500 font-medium">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <tr v-for="item in selectedOrder.items" :key="item.id">
                                        <td class="px-3 py-2 text-gray-900 dark:text-white">
                                            {{ item.catalog_item?.name ?? '—' }}
                                            <span v-if="item.catalog_item?.packaging_spec" class="text-xs text-gray-400 ml-1">({{ item.catalog_item.packaging_spec }})</span>
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                            {{ item.quantity }} {{ item.unit?.symbol }}
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(item.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Advance workflow -->
                    <div v-if="transitions[selectedOrder.status]" class="border border-blue-200 dark:border-blue-800 rounded-xl p-4 bg-blue-50 dark:bg-blue-900/20 space-y-3">
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                            Chuyển sang: <span class="font-bold">{{ statusLabels[transitions[selectedOrder.status]] }}</span>
                        </p>
                        <textarea
                            v-model="advanceForm.note"
                            placeholder="Ghi chú (tuỳ chọn)..."
                            rows="2"
                            class="w-full text-sm px-3 py-2 border border-blue-300 dark:border-blue-700 bg-white dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"
                        />
                        <button
                            @click="advanceStatus"
                            :disabled="advanceForm.processing"
                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition-colors"
                        >
                            <component :is="statusIcons[transitions[selectedOrder.status]]" class="size-4" />
                            Xác nhận: {{ statusLabels[transitions[selectedOrder.status]] }}
                        </button>
                    </div>

                    <!-- Terminal state message -->
                    <div v-else-if="selectedOrder.status === 'delivered'" class="text-center py-4 text-green-600 dark:text-green-400 font-semibold text-sm">
                        ✅ Đơn hàng đã hoàn thành giao nhận.
                    </div>
                    <div v-else-if="selectedOrder.status === 'cancelled'" class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        Đơn hàng đã bị hủy.
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
