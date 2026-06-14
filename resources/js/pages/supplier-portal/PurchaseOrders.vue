<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import {
    Package, Clock, CheckCircle, Truck, Archive,
    Search, X, ChevronRight, RefreshCw,
    Paperclip, Upload, Trash2, Eye, FileText, Image,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import echo from '@/lib/echo';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

// ── Types ────────────────────────────────────────────────────
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
interface Attachment {
    id: number;
    original_name: string;
    mime_type: string;
    size: string;
    document_type: 'invoice' | 'delivery_note' | 'other';
    document_label: string;
    note: string | null;
    is_image: boolean;
    download_url: string;
    uploader_name: string | null;
    uploaded_at: string;
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
    attachments?: Attachment[];
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

// ── Setup ─────────────────────────────────────────────────────
const props = defineProps<Props>();
const page = usePage();
const user = computed(() => (page.props.auth?.user as any) ?? null);

// ── Status display maps ───────────────────────────────────────
const statusIcons: Record<string, any> = {
    pending: Clock, received: CheckCircle,
    preparing: Package, in_transit: Truck,
    delivered: Archive, cancelled: X,
};
const statusColors: Record<string, string> = {
    pending:    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    received:   'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    preparing:  'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    in_transit: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
    delivered:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    cancelled:  'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
};

// ── Filters ───────────────────────────────────────────────────
const searchInput   = ref(props.filters.search);
const selectedStatus = ref(props.filters.status);

function applyFilters() {
    router.get('/supplier-portal/purchase-orders', {
        status: selectedStatus.value || undefined,
        search: searchInput.value || undefined,
    }, { preserveState: true, replace: true });
}

const statusTabs = computed(() => [
    { key: '', label: 'Tất cả' },
    ...Object.entries(props.statusLabels)
        .filter(([k]) => k !== 'cancelled')
        .map(([k, v]) => ({ key: k, label: v })),
]);

// ── Modal ─────────────────────────────────────────────────────
const selectedOrder   = ref<PurchaseOrder | null>(null);
const modalLoading    = ref(false);
const advanceForm     = useForm({ note: '' });

async function openOrder(order: PurchaseOrder) {
    selectedOrder.value = { ...order, attachments: [] };
    advanceForm.reset();
    uploadState.value = { file: null, documentType: 'invoice', note: '', uploading: false };

    // Fetch full detail + attachments
    modalLoading.value = true;
    try {
        const { data } = await axios.get(`/supplier-portal/purchase-orders/${order.id}`, {
            headers: { Accept: 'application/json' },
        });
        selectedOrder.value = data.order;
    } finally {
        modalLoading.value = false;
    }
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
        onError: () => toast.error('Không thể cập nhật trạng thái.'),
    });
}

// ── Attachment upload ─────────────────────────────────────────
const fileInputRef = ref<HTMLInputElement | null>(null);
const uploadState  = ref({
    file: null as File | null,
    documentType: 'invoice' as 'invoice' | 'delivery_note' | 'other',
    note: '',
    uploading: false,
});
const dragOver = ref(false);

const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

function onFilePick(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0];
    if (f) validateAndSetFile(f);
}

function onDrop(e: DragEvent) {
    dragOver.value = false;
    const f = e.dataTransfer?.files?.[0];
    if (f) validateAndSetFile(f);
}

function validateAndSetFile(f: File) {
    if (!ACCEPTED.includes(f.type)) {
        toast.error('Chỉ chấp nhận JPG, PNG, WebP hoặc PDF.');
        return;
    }
    if (f.size > MAX_BYTES) {
        toast.error('Tệp không được vượt quá 20 MB.');
        return;
    }
    uploadState.value.file = f;
}

async function uploadAttachment() {
    if (!selectedOrder.value || !uploadState.value.file) return;
    uploadState.value.uploading = true;

    const form = new FormData();
    form.append('file', uploadState.value.file);
    form.append('document_type', uploadState.value.documentType);
    if (uploadState.value.note) form.append('note', uploadState.value.note);

    try {
        const { data } = await axios.post(
            `/supplier-portal/purchase-orders/${selectedOrder.value.id}/attachments`,
            form,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        );
        selectedOrder.value.attachments = [data, ...(selectedOrder.value.attachments ?? [])];
        uploadState.value = { file: null, documentType: 'invoice', note: '', uploading: false };
        if (fileInputRef.value) fileInputRef.value.value = '';
        toast.success('Đã tải lên chứng từ thành công.');
    } catch (err: any) {
        const msg = err.response?.data?.message ?? 'Tải lên thất bại.';
        toast.error(msg);
        uploadState.value.uploading = false;
    }
}

async function deleteAttachment(attachment: Attachment) {
    if (!selectedOrder.value) return;
    if (!confirm(`Xóa chứng từ "${attachment.original_name}"?`)) return;

    try {
        await axios.delete(`/supplier-portal/attachments/${attachment.id}`);
        selectedOrder.value.attachments = selectedOrder.value.attachments?.filter(a => a.id !== attachment.id);
        toast.success('Đã xóa chứng từ.');
    } catch {
        toast.error('Không thể xóa chứng từ.');
    }
}

// ── Helpers ───────────────────────────────────────────────────
function formatCurrency(v: string | number) {
    return Number(v).toLocaleString('vi-VN') + ' ₫';
}
function formatDate(v: string | null) {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}
function formatDateTime(v: string) {
    return new Date(v).toLocaleString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

// ── Real-time (PO mới) ───────────────────────────────────────
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
    if (supplierId && channel) echo.leave(`supplier.${supplierId}`);
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
                    Tiếp nhận, xử lý và đính kèm chứng từ giao nhận điện tử.
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
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div
                v-for="(label, key) in statusLabels"
                :key="key"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center cursor-pointer hover:border-blue-400 transition-colors"
                :class="{ 'border-blue-500 ring-1 ring-blue-500': selectedStatus === key }"
                @click="selectedStatus = key; applyFilters()"
            >
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ statusCounts[key] ?? 0 }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-tight">{{ label }}</p>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="flex flex-wrap gap-3 items-center bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
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
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Số PO</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tổng tiền</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Giao dự kiến</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Ngày tạo</th>
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
                        <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">{{ formatCurrency(order.total_amount) }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ formatDate(order.expected_delivery_date) }}</td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ formatDate(order.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <ChevronRight class="size-4 text-gray-400 ml-auto" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="orders.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex gap-1 justify-end">
                <template v-for="link in orders.links" :key="link.label">
                    <button v-if="link.url" @click="router.get(link.url, {}, { preserveState: true })"
                        class="px-3 py-1 rounded text-sm"
                        :class="link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-600 dark:text-gray-400'"
                        v-html="link.label" />
                    <span v-else class="px-3 py-1 rounded text-sm text-gray-400" v-html="link.label" />
                </template>
            </div>
        </div>
    </div>

    <!-- ── Order Detail Modal ────────────────────────────────── -->
    <Teleport to="body">
        <div v-if="selectedOrder" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4" @click.self="closeOrder">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

                <!-- Modal header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900 z-10">
                    <div>
                        <h2 class="text-lg font-bold font-mono text-gray-900 dark:text-white">{{ selectedOrder.po_number }}</h2>
                        <span class="inline-flex items-center gap-1.5 mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusColors[selectedOrder.status]">
                            <component :is="statusIcons[selectedOrder.status]" class="size-3" />
                            {{ statusLabels[selectedOrder.status] }}
                        </span>
                    </div>
                    <button @click="closeOrder" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <X class="size-5 text-gray-500" />
                    </button>
                </div>

                <!-- Loading skeleton -->
                <div v-if="modalLoading" class="px-6 py-8 text-center text-gray-400 text-sm animate-pulse">
                    Đang tải chi tiết đơn hàng...
                </div>

                <div v-else class="px-6 py-4 space-y-6">

                    <!-- Basic info -->
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

                    <!-- Line items -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Danh sách hàng hoá</h3>
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="text-left px-3 py-2 text-xs text-gray-500 font-medium">Mặt hàng</th>
                                        <th class="text-right px-3 py-2 text-xs text-gray-500 font-medium">SL</th>
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
                                        <td class="px-3 py-2 text-right">{{ item.quantity }} {{ item.unit?.symbol }}</td>
                                        <td class="px-3 py-2 text-right">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ── E-Invoicing: Chứng từ giao nhận ─────────────── -->
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            <Paperclip class="size-4" />
                            Chứng từ giao nhận
                            <span v-if="selectedOrder.attachments?.length" class="text-xs font-normal text-gray-400">({{ selectedOrder.attachments.length }} tệp)</span>
                        </h3>

                        <!-- Attachment list -->
                        <div v-if="selectedOrder.attachments?.length" class="space-y-2 mb-4">
                            <div
                                v-for="att in selectedOrder.attachments"
                                :key="att.id"
                                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            >
                                <!-- Icon -->
                                <div class="shrink-0 w-8 h-8 rounded flex items-center justify-center" :class="att.is_image ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                                    <Image v-if="att.is_image" class="size-4 text-blue-600 dark:text-blue-400" />
                                    <FileText v-else class="size-4 text-red-600 dark:text-red-400" />
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ att.original_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ att.document_label }} · {{ att.size }} · {{ att.uploader_name }}
                                        · {{ formatDateTime(att.uploaded_at) }}
                                    </p>
                                    <p v-if="att.note" class="text-xs text-gray-400 dark:text-gray-500 italic mt-0.5">{{ att.note }}</p>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-1 shrink-0">
                                    <a
                                        :href="att.download_url"
                                        target="_blank"
                                        class="p-1.5 hover:bg-gray-200 dark:hover:bg-gray-700 rounded transition-colors"
                                        title="Xem / tải xuống"
                                    >
                                        <Eye class="size-4 text-gray-500" />
                                    </a>
                                    <button
                                        @click="deleteAttachment(att)"
                                        class="p-1.5 hover:bg-red-100 dark:hover:bg-red-900/30 rounded transition-colors"
                                        title="Xóa chứng từ"
                                    >
                                        <Trash2 class="size-4 text-red-500" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Upload area -->
                        <div
                            class="border-2 border-dashed rounded-xl transition-colors"
                            :class="dragOver
                                ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                                : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50'"
                            @dragover.prevent="dragOver = true"
                            @dragleave="dragOver = false"
                            @drop.prevent="onDrop"
                        >
                            <div v-if="!uploadState.file" class="p-6 text-center">
                                <Upload class="size-8 mx-auto text-gray-400 mb-2" />
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Kéo thả tệp vào đây hoặc
                                    <button @click="fileInputRef?.click()" class="text-blue-600 dark:text-blue-400 font-medium underline">chọn tệp</button>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP hoặc PDF · tối đa 20 MB</p>
                                <input ref="fileInputRef" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="onFilePick" />
                            </div>

                            <div v-else class="p-4 space-y-3">
                                <!-- File preview -->
                                <div class="flex items-center gap-3 p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <div class="w-8 h-8 rounded flex items-center justify-center shrink-0"
                                        :class="uploadState.file.type.startsWith('image/') ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-red-100 dark:bg-red-900/30'">
                                        <Image v-if="uploadState.file.type.startsWith('image/')" class="size-4 text-blue-600" />
                                        <FileText v-else class="size-4 text-red-600" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ uploadState.file.name }}</p>
                                        <p class="text-xs text-gray-400">{{ (uploadState.file.size / 1024).toFixed(1) }} KB</p>
                                    </div>
                                    <button @click="uploadState.file = null; if(fileInputRef) fileInputRef.value = ''" class="p-1 hover:bg-gray-100 dark:hover:bg-gray-800 rounded">
                                        <X class="size-4 text-gray-400" />
                                    </button>
                                </div>

                                <!-- Metadata -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Loại chứng từ</label>
                                        <select
                                            v-model="uploadState.documentType"
                                            class="w-full text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        >
                                            <option value="invoice">Hóa đơn</option>
                                            <option value="delivery_note">Phiếu giao nhận</option>
                                            <option value="other">Tài liệu khác</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400 block mb-1">Ghi chú (tuỳ chọn)</label>
                                        <input
                                            v-model="uploadState.note"
                                            type="text"
                                            maxlength="300"
                                            placeholder="Số hóa đơn, ngày lập..."
                                            class="w-full text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                                        />
                                    </div>
                                </div>

                                <button
                                    @click="uploadAttachment"
                                    :disabled="uploadState.uploading"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition-colors"
                                >
                                    <Upload class="size-4" />
                                    {{ uploadState.uploading ? 'Đang tải lên...' : 'Tải lên chứng từ' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- ── Advance workflow ───────────────────────────────── -->
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
