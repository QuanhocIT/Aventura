<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertCircle,
    Boxes,
    Building2,
    Check,
    CheckCircle2,
    Clock,
    DollarSign,
    Eye,
    PackageCheck,
    Save,
    Search,
    Truck,
    Warehouse,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch: any;
    branches: Array<any>;
    supplyRequests: Array<any>;
    ingredients: Array<any>;
    canManageWarehouse: boolean;
    canApproveRequests: boolean;
    canDispatchRequests: boolean;
}>();

const activeTab = ref<
    'all' | 'pending' | 'approved' | 'dispatched' | 'completed'
>('all');
const searchQuery = ref('');
const selectedBranchFilter = ref<number | string>('all');
const selectedRequest = ref<any>(null);
const isDetailModalOpen = ref(false);
const isProcessing = ref(false);
const isSavingPrices = ref(false);
const priceRows = ref(
    props.ingredients.map((ing) => ({
        ingredient_id: ing.id,
        name: ing.name,
        sku: ing.sku,
        unit_symbol: ing.unit?.symbol || 'đv',
        average_cost: Number(ing.average_cost || 0),
    })),
);

// Filtered Requests
const filteredRequests = computed(() => {
    return props.supplyRequests.filter((req) => {
        const matchesTab =
            activeTab.value === 'all' || req.status === activeTab.value;
        const matchesBranch =
            selectedBranchFilter.value === 'all' ||
            req.to_branch_id === Number(selectedBranchFilter.value);
        const matchesSearch =
            req.request_code
                .toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            req.to_branch?.name
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase()) ||
            req.creator?.name
                ?.toLowerCase()
                .includes(searchQuery.value.toLowerCase());

        return matchesTab && matchesBranch && matchesSearch;
    });
});

// Stats Counters
const stats = computed(() => ({
    total: props.supplyRequests.length,
    pending: props.supplyRequests.filter((r) => r.status === 'pending').length,
    approved: props.supplyRequests.filter((r) => r.status === 'approved')
        .length,
    dispatched: props.supplyRequests.filter((r) => r.status === 'dispatched')
        .length,
    completed: props.supplyRequests.filter((r) => r.status === 'completed')
        .length,
}));

const openDetailModal = (req: any) => {
    selectedRequest.value = JSON.parse(JSON.stringify(req));
    isDetailModalOpen.value = true;
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const formatDate = (dt: string) => {
    if (!dt) {
        return '-';
    }

    return new Date(dt).toLocaleString('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pending':
            return {
                label: 'Chờ duyệt',
                color: 'bg-amber-100 text-amber-800 border-amber-300',
            };
        case 'approved':
            return {
                label: 'Đã duyệt (Chờ xuất)',
                color: 'bg-blue-100 text-blue-800 border-blue-300',
            };
        case 'dispatched':
            return {
                label: 'Đang giao hàng',
                color: 'bg-purple-100 text-purple-800 border-purple-300',
            };
        case 'completed':
            return {
                label: 'Hoàn thành',
                color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
            };
        case 'rejected':
            return {
                label: 'Đã từ chối',
                color: 'bg-rose-100 text-rose-800 border-rose-300',
            };
        default:
            return {
                label: status,
                color: 'bg-gray-100 text-gray-800 border-gray-300',
            };
    }
};

// Handlers
const approveRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const payload = {
            items: selectedRequest.value.items.map((item: any) => ({
                id: item.id,
                approved_quantity: Number(
                    item.approved_quantity ?? item.requested_quantity,
                ),
            })),
        };

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/approve`,
            payload,
        );

        if (res.data.success) {
            toast.success('Đã duyệt đơn cấp phát thành công.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi duyệt đơn.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const dispatchRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    const sealCode = prompt(
        'Nhập Mã Niêm Phong (Seal Code) của kiện hàng (Nếu có):',
    );

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/dispatch`,
            {
                seal_code: sealCode || null,
            },
        );

        if (res.data.success) {
            toast.success('Đã xuất kho Tổng và chuyển hàng đến chi nhánh!');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi xuất kho.');
    } finally {
        isProcessing.value = false;
    }
};

const rejectRequest = async () => {
    if (!selectedRequest.value) {
        return;
    }

    const reason = prompt('Nhập lý do từ chối đơn yêu cầu này:');

    if (!reason) {
        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/reject`,
            { reason },
        );

        if (res.data.success) {
            toast.success('Đã từ chối đơn yêu cầu.');
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể từ chối đơn.');
    } finally {
        isProcessing.value = false;
    }
};

const changeCentralWarehouse = async (branchId: number) => {
    try {
        const res = await axios.post(
            '/api/supply-requests/set-central-branch',
            { branch_id: branchId },
        );

        if (res.data.success) {
            toast.success(res.data.message);
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi khi thiết lập Kho Tổng.');
    }
};

const saveIngredientPrices = async () => {
    isSavingPrices.value = true;

    try {
        const res = await axios.post('/api/warehouse/ingredient-prices', {
            prices: priceRows.value.map((row) => ({
                ingredient_id: row.ingredient_id,
                average_cost: Number(row.average_cost || 0),
            })),
        });

        if (res.data.success) {
            toast.success(res.data.message || 'Đã cập nhật đơn giá Kho Tổng.');
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ||
                'Không thể cập nhật đơn giá nguyên liệu.',
        );
    } finally {
        isSavingPrices.value = false;
    }
};
</script>

<template>
    <Head title="Trung tâm Điều phối Kho Tổng" />

    <div class="mx-auto max-w-7xl space-y-6 p-6">
        <!-- Header Section -->
        <div
            class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white shadow-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-3">
                    <div
                        class="rounded-xl border border-indigo-400/30 bg-indigo-500/20 p-3"
                    >
                        <Warehouse class="h-8 w-8 text-indigo-300" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Trung tâm Điều phối Kho Tổng
                        </h1>
                        <p class="text-sm text-indigo-200/80">
                            Quản lý cấp phát nguyên liệu đồng bộ cho toàn bộ
                            chuỗi nhà hàng
                        </p>
                    </div>
                </div>
            </div>

            <!-- Warehouse Selector -->
            <div
                v-if="canManageWarehouse"
                class="flex items-center gap-3 rounded-xl border border-white/20 bg-white/10 p-3 backdrop-blur-md"
            >
                <Building2 class="h-5 w-5 text-indigo-300" />
                <div class="text-xs">
                    <div class="text-indigo-200">Chi nhánh Kho Tổng:</div>
                    <select
                        :value="centralBranch?.id"
                        @change="
                            changeCentralWarehouse(
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ),
                            )
                        "
                        class="rounded border border-indigo-400/40 bg-slate-800 px-2 py-1 text-sm font-semibold text-white focus:outline-none"
                    >
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                            {{ b.is_central_warehouse ? '(Hiện tại)' : '' }}
                        </option>
                    </select>
                </div>
            </div>
            <div
                v-else
                class="flex items-center gap-3 rounded-xl border border-white/20 bg-white/10 p-3 backdrop-blur-md"
            >
                <Building2 class="h-5 w-5 text-indigo-300" />
                <div class="text-xs">
                    <div class="text-indigo-200">Chi nhÃ¡nh Kho Tá»•ng:</div>
                    <div class="font-semibold text-white">
                        {{ centralBranch?.name || 'ChÆ°a thiáº¿t láº­p' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <Card class="border-slate-200/80 shadow-sm transition hover:shadow">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-slate-500">
                            Tổng Đơn
                        </p>
                        <p class="mt-1 text-2xl font-bold text-slate-900">
                            {{ stats.total }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-slate-100 p-2.5 text-slate-600">
                        <Boxes class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-amber-200/80 bg-amber-50/40 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-amber-700">
                            Chờ Kho Duyệt
                        </p>
                        <p class="mt-1 text-2xl font-bold text-amber-900">
                            {{ stats.pending }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-amber-100 p-2.5 text-amber-700">
                        <Clock class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-blue-200/80 bg-blue-50/40 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-blue-700">
                            Đã Duyệt (Chờ Xuất)
                        </p>
                        <p class="mt-1 text-2xl font-bold text-blue-900">
                            {{ stats.approved }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-blue-100 p-2.5 text-blue-700">
                        <PackageCheck class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-purple-200/80 bg-purple-50/40 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-purple-700">
                            Đang Giao Hàng
                        </p>
                        <p class="mt-1 text-2xl font-bold text-purple-900">
                            {{ stats.dispatched }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-purple-100 p-2.5 text-purple-700">
                        <Truck class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-emerald-200/80 bg-emerald-50/40 shadow-sm transition hover:shadow"
            >
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-emerald-700">
                            Hoàn Thành
                        </p>
                        <p class="mt-1 text-2xl font-bold text-emerald-900">
                            {{ stats.completed }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-emerald-100 p-2.5 text-emerald-700"
                    >
                        <CheckCircle2 class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-if="canManageWarehouse" class="border-slate-200 shadow-sm">
            <CardHeader class="border-b bg-slate-50/50 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <CardTitle class="text-base font-bold text-slate-900"
                            >Đơn giá nguyên liệu đồng bộ toàn chuỗi</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Kho Tổng thiết lập giá dùng để tính giá trị cấp
                            phát cho mọi chi nhánh.</CardDescription
                        >
                    </div>
                    <Button
                        @click="saveIngredientPrices"
                        size="sm"
                        :disabled="isSavingPrices"
                        class="gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        <Save class="h-4 w-4" /> Lưu đơn giá
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="sticky top-0 border-b bg-slate-100 font-semibold text-slate-600"
                        >
                            <tr>
                                <th class="p-3 pl-4">Nguyên liệu</th>
                                <th class="p-3">SKU</th>
                                <th class="p-3 text-center">Đơn vị</th>
                                <th class="p-3 pr-4 text-right">
                                    Đơn giá Kho Tổng
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="priceRows.length === 0">
                                <td
                                    colspan="4"
                                    class="p-6 text-center text-slate-400"
                                >
                                    Chưa có nguyên liệu để thiết lập đơn giá.
                                </td>
                            </tr>
                            <tr
                                v-for="row in priceRows"
                                :key="row.ingredient_id"
                                class="hover:bg-slate-50/80"
                            >
                                <td
                                    class="p-3 pl-4 font-semibold text-slate-800"
                                >
                                    {{ row.name }}
                                </td>
                                <td class="p-3 font-mono text-slate-500">
                                    {{ row.sku || '-' }}
                                </td>
                                <td class="p-3 text-center text-slate-500">
                                    {{ row.unit_symbol }}
                                </td>
                                <td class="p-3 pr-4">
                                    <div
                                        class="ml-auto flex max-w-[190px] items-center gap-2"
                                    >
                                        <DollarSign
                                            class="h-4 w-4 text-emerald-600"
                                        />
                                        <Input
                                            v-model.number="row.average_cost"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            class="h-8 text-right text-xs font-bold text-emerald-700"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Filter & Search Bar -->
        <Card class="border-slate-200">
            <CardContent
                class="flex flex-col items-center justify-between gap-4 p-4 md:flex-row"
            >
                <!-- Status Tabs -->
                <div
                    class="flex w-full flex-wrap gap-1 rounded-xl bg-slate-100 p-1 md:w-auto"
                >
                    <button
                        @click="activeTab = 'all'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            activeTab === 'all'
                                ? 'bg-white text-slate-900 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Tất cả ({{ stats.total }})
                    </button>
                    <button
                        @click="activeTab = 'pending'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            activeTab === 'pending'
                                ? 'bg-white text-amber-700 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Chờ duyệt ({{ stats.pending }})
                    </button>
                    <button
                        @click="activeTab = 'approved'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            activeTab === 'approved'
                                ? 'bg-white text-blue-700 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Đã duyệt ({{ stats.approved }})
                    </button>
                    <button
                        @click="activeTab = 'dispatched'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            activeTab === 'dispatched'
                                ? 'bg-white text-purple-700 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Đang giao ({{ stats.dispatched }})
                    </button>
                    <button
                        @click="activeTab = 'completed'"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                            activeTab === 'completed'
                                ? 'bg-white text-emerald-700 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                    >
                        Hoàn thành ({{ stats.completed }})
                    </button>
                </div>

                <!-- Controls -->
                <div class="flex w-full items-center gap-3 md:w-auto">
                    <select
                        v-model="selectedBranchFilter"
                        class="rounded-lg border bg-white px-3 py-2 text-xs text-slate-700 focus:outline-none"
                    >
                        <option value="all">Tất cả chi nhánh đặt</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>

                    <div class="relative w-full md:w-64">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-slate-400"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Tìm mã đơn, tên chi nhánh..."
                            class="pl-9 text-xs"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Requests Table -->
        <Card class="border-slate-200 shadow-sm">
            <CardHeader class="border-b bg-slate-50/50 py-4">
                <CardTitle class="text-base font-bold text-slate-900"
                    >Danh sách Đơn xin Cấp phát từ các Chi nhánh</CardTitle
                >
                <CardDescription class="text-xs"
                    >Duyệt đơn, chốt số lượng thực xuất và tạo lệnh xuất kho cho
                    chi nhánh</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b bg-slate-100/80 font-semibold text-slate-600"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã Đơn</th>
                                <th class="p-3">Chi Nhánh Yêu Cầu</th>
                                <th class="p-3">Số Mặt Hàng</th>
                                <th class="p-3">Tổng Giá Trị</th>
                                <th class="p-3">Trạng Thái</th>
                                <th class="p-3">Người Lập Đơn</th>
                                <th class="p-3">Ngày Tạo</th>
                                <th class="p-3 pr-4 text-right">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredRequests.length === 0">
                                <td
                                    colspan="8"
                                    class="p-8 text-center text-slate-400"
                                >
                                    Không tìm thấy đơn xin cấp phát nào phù hợp.
                                </td>
                            </tr>
                            <tr
                                v-for="req in filteredRequests"
                                :key="req.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <td
                                    class="p-3 pl-4 font-mono font-bold text-indigo-600"
                                >
                                    {{ req.request_code }}
                                </td>
                                <td class="p-3 font-semibold text-slate-800">
                                    {{ req.to_branch?.name }}
                                </td>
                                <td class="p-3 text-slate-600">
                                    {{ req.items?.length || 0 }} nguyên liệu
                                </td>
                                <td class="p-3 font-semibold text-emerald-700">
                                    {{ formatCurrency(req.total_amount) }}
                                </td>
                                <td class="p-3">
                                    <span
                                        :class="[
                                            'rounded-full border px-2.5 py-1 text-[11px] font-medium',
                                            getStatusBadge(req.status).color,
                                        ]"
                                    >
                                        {{ getStatusBadge(req.status).label }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-600">
                                    {{ req.creator?.name || '-' }}
                                </td>
                                <td class="p-3 text-slate-500">
                                    {{ formatDate(req.created_at) }}
                                </td>
                                <td class="p-3 pr-4 text-right">
                                    <Button
                                        @click="openDetailModal(req)"
                                        size="sm"
                                        variant="outline"
                                        class="h-8 gap-1.5 text-xs"
                                    >
                                        <Eye class="h-3.5 w-3.5" /> Chi tiết &
                                        Xử lý
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Detail & Action Modal -->
        <div
            v-if="isDetailModalOpen && selectedRequest"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex items-center justify-between border-b bg-slate-900 p-5 text-white"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono text-lg font-bold text-indigo-300"
                                >{{ selectedRequest.request_code }}</span
                            >
                            <span
                                :class="[
                                    'rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                    getStatusBadge(selectedRequest.status)
                                        .color,
                                ]"
                            >
                                {{
                                    getStatusBadge(selectedRequest.status).label
                                }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-300">
                            Chi nhánh nhận:
                            <strong class="text-white">{{
                                selectedRequest.to_branch?.name
                            }}</strong>
                            | Lập bởi: {{ selectedRequest.creator?.name }}
                        </p>
                    </div>
                    <button
                        @click="isDetailModalOpen = false"
                        class="rounded-lg p-1 text-slate-400 hover:text-white"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="flex-1 space-y-6 overflow-y-auto p-6 text-xs">
                    <!-- Notes -->
                    <div
                        v-if="selectedRequest.notes"
                        class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-amber-800"
                    >
                        <AlertCircle
                            class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                        />
                        <div>
                            <strong>Ghi chú đơn hàng:</strong>
                            <p class="mt-0.5 whitespace-pre-line">
                                {{ selectedRequest.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div>
                        <h4
                            class="mb-3 flex items-center justify-between text-sm font-bold text-slate-800"
                        >
                            <span>Danh sách Nguyên Liệu Yêu Cầu</span>
                            <span class="text-xs text-slate-500"
                                >Giá niêm yết đồng bộ tại Kho Tổng</span
                            >
                        </h4>

                        <div class="overflow-hidden rounded-xl border">
                            <table class="w-full text-left">
                                <thead
                                    class="border-b bg-slate-100 font-semibold text-slate-600"
                                >
                                    <tr>
                                        <th class="p-3">Nguyên Liệu</th>
                                        <th class="p-3 text-center">Đơn Vị</th>
                                        <th class="p-3 text-right">
                                            Chi Nhánh Xin
                                        </th>
                                        <th class="p-3 text-right">
                                            Kho Tổng Duyệt
                                        </th>
                                        <th class="p-3 text-right">
                                            Đơn Giá Kho
                                        </th>
                                        <th class="p-3 pr-4 text-right">
                                            Thành Tiền
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="item in selectedRequest.items"
                                        :key="item.id"
                                        class="hover:bg-slate-50"
                                    >
                                        <td
                                            class="p-3 font-semibold text-slate-800"
                                        >
                                            {{ item.ingredient?.name }}
                                        </td>
                                        <td
                                            class="p-3 text-center font-mono text-slate-500"
                                        >
                                            {{ item.unit_symbol || 'kg' }}
                                        </td>
                                        <td
                                            class="p-3 text-right font-bold text-slate-700"
                                        >
                                            {{ item.requested_quantity }}
                                        </td>
                                        <td class="p-3 text-right">
                                            <input
                                                v-if="
                                                    selectedRequest.status ===
                                                        'pending' &&
                                                    canApproveRequests
                                                "
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                v-model.number="
                                                    item.approved_quantity
                                                "
                                                class="w-20 rounded border border-slate-300 px-2 py-1 text-right font-bold text-indigo-600 focus:border-indigo-500 focus:outline-none"
                                            />
                                            <span
                                                v-else
                                                class="font-bold text-indigo-600"
                                                >{{
                                                    item.approved_quantity ??
                                                    item.requested_quantity
                                                }}</span
                                            >
                                        </td>
                                        <td
                                            class="p-3 text-right text-slate-600"
                                        >
                                            {{ formatCurrency(item.unit_cost) }}
                                        </td>
                                        <td
                                            class="p-3 pr-4 text-right font-bold text-emerald-700"
                                        >
                                            {{
                                                formatCurrency(
                                                    (item.approved_quantity ??
                                                        item.requested_quantity) *
                                                        item.unit_cost,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer Actions -->
                <div
                    class="flex items-center justify-between border-t bg-slate-50 p-4"
                >
                    <div class="text-xs text-slate-500">
                        Tổng giá trị cấp phát:
                        <strong
                            class="ml-1 text-sm font-bold text-emerald-700"
                            >{{
                                formatCurrency(selectedRequest.total_amount)
                            }}</strong
                        >
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            v-if="
                                selectedRequest.status === 'pending' &&
                                canApproveRequests
                            "
                            @click="rejectRequest"
                            variant="destructive"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1 text-xs"
                        >
                            <XCircle class="h-4 w-4" /> Từ chối
                        </Button>

                        <Button
                            v-if="
                                selectedRequest.status === 'pending' &&
                                canApproveRequests
                            "
                            @click="approveRequest"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1 bg-indigo-600 text-xs text-white hover:bg-indigo-700"
                        >
                            <Check class="h-4 w-4" /> Duyệt đơn hàng
                        </Button>

                        <Button
                            v-if="
                                selectedRequest.status === 'approved' &&
                                canDispatchRequests
                            "
                            @click="dispatchRequest"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1 bg-purple-600 text-xs text-white hover:bg-purple-700"
                        >
                            <Truck class="h-4 w-4" /> Xuất kho & Giao hàng
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
