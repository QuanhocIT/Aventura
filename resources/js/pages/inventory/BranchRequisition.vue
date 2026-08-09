<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Building2,
    Eye,
    PackageCheck,
    Plus,
    Send,
    ShoppingCart,
    Trash2,
    X,
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
    activeBranchId: number;
    supplyRequests: Array<any>;
    ingredients: Array<any>;
    canCreateRequests: boolean;
    canReceiveRequests: boolean;
}>();

const isCreateModalOpen = ref(false);
const isDetailModalOpen = ref(false);
const isProcessing = ref(false);
const selectedRequest = ref<any>(null);

// Create Form State
const newRequestForm = ref({
    to_branch_id: props.activeBranchId,
    requested_delivery_date: '',
    notes: '',
    items: [] as Array<{
        ingredient_id: number;
        name: string;
        unit_symbol: string;
        quantity: number;
        unit_cost: number;
    }>,
});

const activeBranch = computed(() =>
    props.branches.find((b) => b.id === props.activeBranchId),
);

const openCreateModal = () => {
    newRequestForm.value = {
        to_branch_id: props.activeBranchId,
        requested_delivery_date: new Date().toISOString().split('T')[0],
        notes: '',
        items: [],
    };
    addItemRow();
    isCreateModalOpen.value = true;
};

const addItemRow = () => {
    if (props.ingredients.length > 0) {
        const firstIng = props.ingredients[0];

        newRequestForm.value.items.push({
            ingredient_id: firstIng.id,
            name: firstIng.name,
            unit_symbol: firstIng.unit?.symbol || 'kg',
            quantity: 1,
            unit_cost: firstIng.average_cost || 0,
        });
    }
};

const removeItemRow = (index: number) => {
    newRequestForm.value.items.splice(index, 1);
};

const onIngredientSelect = (index: number, ingId: number) => {
    const ing = props.ingredients.find((i) => i.id === ingId);

    if (ing) {
        newRequestForm.value.items[index].name = ing.name;
        newRequestForm.value.items[index].unit_symbol =
            ing.unit?.symbol || 'kg';
        newRequestForm.value.items[index].unit_cost = ing.average_cost || 0;
    }
};

const calculatedFormTotal = computed(() => {
    return newRequestForm.value.items.reduce(
        (sum, item) => sum + item.quantity * item.unit_cost,
        0,
    );
});

const submitRequisition = async () => {
    if (newRequestForm.value.items.length === 0) {
        toast.error('Vui lòng thêm ít nhất 1 nguyên liệu vào đơn đặt hàng.');

        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            '/api/supply-requests',
            newRequestForm.value,
        );

        if (res.data.success) {
            toast.success('Đã gửi yêu cầu cấp phát đến Kho Tổng thành công!');
            isCreateModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi gửi đơn.');
    } finally {
        isProcessing.value = false;
    }
};

const openDetailModal = (req: any) => {
    selectedRequest.value = JSON.parse(JSON.stringify(req));
    isDetailModalOpen.value = true;
};

const receiveGoods = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const payload = {
            items: selectedRequest.value.items.map((item: any) => ({
                id: item.id,
                received_quantity: Number(
                    item.received_quantity ??
                        item.approved_quantity ??
                        item.requested_quantity,
                ),
            })),
        };

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/receive`,
            payload,
        );

        if (res.data.success) {
            toast.success(
                'Đã nhận hàng thành công và cập nhật vào Tồn Kho Chi Nhánh!',
            );
            isDetailModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Lỗi khi nhận hàng.');
    } finally {
        isProcessing.value = false;
    }
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
                label: 'Đã gửi (Chờ Kho duyệt)',
                color: 'bg-amber-100 text-amber-800 border-amber-300',
            };
        case 'approved':
            return {
                label: 'Kho đã duyệt (Đang chuẩn bị)',
                color: 'bg-blue-100 text-blue-800 border-blue-300',
            };
        case 'dispatched':
            return {
                label: 'Kho đã xuất - Đang giao',
                color: 'bg-purple-100 text-purple-800 border-purple-300',
            };
        case 'completed':
            return {
                label: 'Đã nhận hàng',
                color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
            };
        case 'rejected':
            return {
                label: 'Kho từ chối',
                color: 'bg-rose-100 text-rose-800 border-rose-300',
            };
        default:
            return {
                label: status,
                color: 'bg-gray-100 text-gray-800 border-gray-300',
            };
    }
};
</script>

<template>
    <Head title="Đặt Hàng Cấp Phát Kho Tổng" />

    <div class="mx-auto max-w-7xl space-y-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-900 to-indigo-900 p-6 text-white shadow-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="rounded-xl border border-indigo-400/30 bg-indigo-500/20 p-3"
                >
                    <ShoppingCart class="h-8 w-8 text-indigo-300" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Đặt Hàng Cấp Phát Kho Tổng
                    </h1>
                    <p class="text-sm text-indigo-200">
                        Gửi đơn yêu cầu bổ sung nguyên liệu daily đến Kho Tổng
                        cho Chi nhánh
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div
                    class="flex items-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-xs backdrop-blur"
                >
                    <Building2 class="h-4 w-4 text-indigo-300" />
                    <span
                        >Chi nhánh:
                        <strong class="text-white">{{
                            activeBranch?.name || 'Tất cả'
                        }}</strong></span
                    >
                </div>
                <Button
                    v-if="canCreateRequests"
                    @click="openCreateModal"
                    class="gap-2 bg-emerald-500 font-semibold text-white shadow-lg hover:bg-emerald-600"
                >
                    <Plus class="h-4 w-4" /> Lập Đơn Đặt Hàng Mới
                </Button>
            </div>
        </div>

        <!-- Orders Table -->
        <Card class="border-slate-200 shadow-sm">
            <CardHeader class="border-b bg-slate-50/50 py-4">
                <CardTitle class="text-base font-bold text-slate-900"
                    >Lịch Sử Đơn Đặt Hàng Kho Tổng</CardTitle
                >
                <CardDescription class="text-xs"
                    >Theo dõi tiến độ duyệt, giao vận và xác nhận nhận
                    hàng</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b bg-slate-100 font-semibold text-slate-600"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã Đơn</th>
                                <th class="p-3">Kho Xuất</th>
                                <th class="p-3">Số Mặt Hàng</th>
                                <th class="p-3">Tổng Tiền Dự Kiến</th>
                                <th class="p-3">Trạng Thái Kho</th>
                                <th class="p-3">Ngày Lập</th>
                                <th class="p-3 pr-4 text-right">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="supplyRequests.length === 0">
                                <td
                                    colspan="7"
                                    class="p-8 text-center text-slate-400"
                                >
                                    Chi nhánh chưa tạo đơn xin cấp phát nào.
                                </td>
                            </tr>
                            <tr
                                v-for="req in supplyRequests"
                                :key="req.id"
                                class="transition hover:bg-slate-50"
                            >
                                <td
                                    class="p-3 pl-4 font-mono font-bold text-indigo-600"
                                >
                                    {{ req.request_code }}
                                </td>
                                <td class="p-3 font-medium text-slate-700">
                                    {{ req.from_branch?.name || 'Kho Tổng' }}
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
                                        <Eye class="h-3.5 w-3.5" /> Xem & Nhận
                                        hàng
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Create Requisition Modal -->
        <div
            v-if="isCreateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b bg-indigo-900 p-5 text-white"
                >
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="h-6 w-6 text-indigo-300" />
                        <div>
                            <h3 class="text-base font-bold">
                                Lập Đơn Đặt Hàng Gửi Kho Tổng
                            </h3>
                            <p class="text-xs text-indigo-200">
                                Kho Tổng sẽ duyệt và giao nguyên liệu tới Chi
                                nhánh {{ activeBranch?.name }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isCreateModalOpen = false"
                        class="rounded-lg p-1 text-slate-400 hover:text-white"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <!-- Form Body -->
                <div class="flex-1 space-y-5 overflow-y-auto p-6 text-xs">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700"
                                >Ngày cần giao hàng</label
                            >
                            <Input
                                v-model="newRequestForm.requested_delivery_date"
                                type="date"
                                class="text-xs"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700"
                                >Ghi chú cho Kho Tổng</label
                            >
                            <Input
                                v-model="newRequestForm.notes"
                                placeholder="VD: Giao trước 8h sáng..."
                                class="text-xs"
                            />
                        </div>
                    </div>

                    <!-- Items selection table -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <h4 class="font-bold text-slate-800">
                                Danh Sách Nguyên Liệu Cần Nhập
                            </h4>
                            <Button
                                @click="addItemRow"
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 border-indigo-200 text-xs text-indigo-700"
                            >
                                <Plus class="h-3.5 w-3.5" /> Thêm Nguyên Liệu
                            </Button>
                        </div>

                        <div class="overflow-hidden rounded-xl border">
                            <table class="w-full text-left">
                                <thead
                                    class="border-b bg-slate-100 font-semibold text-slate-600"
                                >
                                    <tr>
                                        <th class="p-2.5 pl-3">
                                            Chọn Nguyên Liệu
                                        </th>
                                        <th class="p-2.5 text-center">
                                            Đơn Vị
                                        </th>
                                        <th class="p-2.5 text-right">
                                            Số Lượng Đặt
                                        </th>
                                        <th class="p-2.5 text-right">
                                            Đơn Giá Kho
                                        </th>
                                        <th class="p-2.5 text-right">
                                            Thành Tiền
                                        </th>
                                        <th class="w-12 p-2.5 text-center">
                                            Xóa
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="(
                                            item, idx
                                        ) in newRequestForm.items"
                                        :key="idx"
                                        class="hover:bg-slate-50"
                                    >
                                        <td class="p-2.5 pl-3">
                                            <select
                                                v-model="item.ingredient_id"
                                                @change="
                                                    onIngredientSelect(
                                                        idx,
                                                        Number(
                                                            (
                                                                $event.target as HTMLSelectElement
                                                            ).value,
                                                        ),
                                                    )
                                                "
                                                class="w-full rounded border border-slate-300 bg-white px-2 py-1 font-medium text-slate-800 focus:outline-none"
                                            >
                                                <option
                                                    v-for="ing in ingredients"
                                                    :key="ing.id"
                                                    :value="ing.id"
                                                >
                                                    {{ ing.name }} ({{
                                                        ing.sku || 'No SKU'
                                                    }})
                                                </option>
                                            </select>
                                        </td>
                                        <td
                                            class="p-2.5 text-center font-mono text-slate-500"
                                        >
                                            {{ item.unit_symbol }}
                                        </td>
                                        <td class="p-2.5 text-right">
                                            <input
                                                type="number"
                                                step="0.5"
                                                min="0.1"
                                                v-model.number="item.quantity"
                                                class="w-20 rounded border border-slate-300 px-2 py-1 text-right font-bold text-indigo-600 focus:outline-none"
                                            />
                                        </td>
                                        <td
                                            class="p-2.5 text-right text-slate-600"
                                        >
                                            {{ formatCurrency(item.unit_cost) }}
                                        </td>
                                        <td
                                            class="p-2.5 text-right font-bold text-emerald-700"
                                        >
                                            {{
                                                formatCurrency(
                                                    item.quantity *
                                                        item.unit_cost,
                                                )
                                            }}
                                        </td>
                                        <td class="p-2.5 text-center">
                                            <button
                                                @click="removeItemRow(idx)"
                                                class="p-1 text-rose-500 hover:text-rose-700"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-between border-t bg-slate-50 p-4"
                >
                    <div class="text-xs text-slate-500">
                        Tổng tiền tạm tính:
                        <strong
                            class="ml-1 text-sm font-bold text-emerald-700"
                            >{{ formatCurrency(calculatedFormTotal) }}</strong
                        >
                    </div>

                    <div class="flex items-center gap-2">
                        <Button
                            @click="isCreateModalOpen = false"
                            variant="ghost"
                            size="sm"
                            class="text-xs"
                            >Hủy</Button
                        >
                        <Button
                            @click="submitRequisition"
                            size="sm"
                            :disabled="isProcessing"
                            class="gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        >
                            <Send class="h-4 w-4" /> Gửi Đơn Đến Kho Tổng
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail / Receive Goods Modal -->
        <div
            v-if="isDetailModalOpen && selectedRequest"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
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
                            Xuất từ:
                            {{
                                selectedRequest.from_branch?.name || 'Kho Tổng'
                            }}
                        </p>
                    </div>
                    <button
                        @click="isDetailModalOpen = false"
                        class="rounded-lg p-1 text-slate-400 hover:text-white"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <div class="flex-1 space-y-6 overflow-y-auto p-6 text-xs">
                    <!-- Rejection reason -->
                    <div
                        v-if="selectedRequest.status === 'rejected'"
                        class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-rose-800"
                    >
                        <strong>Lý do từ chối:</strong>
                        {{ selectedRequest.rejection_reason }}
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-bold text-slate-800">
                            Chi Tiết Hàng Hóa Cấp Phát
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
                                            Số Lượng Đặt
                                        </th>
                                        <th class="p-3 text-right">
                                            Kho Duyệt
                                        </th>
                                        <th class="p-3 text-right">
                                            Thực Nhận
                                        </th>
                                        <th class="p-3 pr-4 text-right">
                                            Đơn Giá Kho
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
                                        <td
                                            class="p-3 text-right font-bold text-indigo-600"
                                        >
                                            {{
                                                item.approved_quantity ??
                                                item.requested_quantity
                                            }}
                                        </td>
                                        <td class="p-3 text-right">
                                            <input
                                                v-if="
                                                    selectedRequest.status ===
                                                        'dispatched' &&
                                                    canReceiveRequests
                                                "
                                                type="number"
                                                step="0.1"
                                                min="0"
                                                v-model.number="
                                                    item.received_quantity
                                                "
                                                class="w-20 rounded border border-slate-300 px-2 py-1 text-right font-bold text-emerald-600 focus:outline-none"
                                            />
                                            <span
                                                v-else
                                                class="font-bold text-emerald-600"
                                                >{{
                                                    item.received_quantity ??
                                                    item.approved_quantity ??
                                                    item.requested_quantity
                                                }}</span
                                            >
                                        </td>
                                        <td
                                            class="p-3 pr-4 text-right text-slate-600"
                                        >
                                            {{ formatCurrency(item.unit_cost) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t bg-slate-50 p-4"
                >
                    <div class="text-xs text-slate-500">
                        Tổng tiền:
                        <strong
                            class="ml-1 text-sm font-bold text-emerald-700"
                            >{{
                                formatCurrency(selectedRequest.total_amount)
                            }}</strong
                        >
                    </div>

                    <Button
                        v-if="
                            selectedRequest.status === 'dispatched' &&
                            canReceiveRequests
                        "
                        @click="receiveGoods"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-1.5 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                    >
                        <PackageCheck class="h-4 w-4" /> Xác Nhận Đã Nhận Hàng &
                        Nhập Kho
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
