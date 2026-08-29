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
const receiveNotes = ref('');
const receiptPhoto = ref<File | null>(null);
const receiverSignature = ref<File | null>(null);
const receiveTemperatureMin = ref<number | string>('');
const receiveTemperatureMax = ref<number | string>('');

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
    receiveNotes.value = '';
    receiptPhoto.value = null;
    receiverSignature.value = null;
    receiveTemperatureMin.value = '';
    receiveTemperatureMax.value = '';
    selectedRequest.value.items.forEach((item: any) => {
        item.received_good_quantity = Number(
            item.received_good_quantity ??
                item.received_quantity ??
                item.approved_quantity ??
                item.requested_quantity ??
                0,
        );
        item.received_damaged_quantity = Number(
            item.received_damaged_quantity ?? 0,
        );
        item.received_expired_quantity = Number(
            item.received_expired_quantity ?? 0,
        );
    });
    isDetailModalOpen.value = true;
};

const setEvidenceFile = (type: 'photo' | 'signature', event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;

    if (type === 'photo') {
        receiptPhoto.value = file;
    } else {
        receiverSignature.value = file;
    }
};

const receiveGoods = async () => {
    if (!selectedRequest.value) {
        return;
    }

    isProcessing.value = true;

    try {
        const payload = new FormData();
        selectedRequest.value.items.forEach((item: any, index: number) => {
            payload.append(`items[${index}][id]`, String(item.id));
            const good = Number(
                item.received_good_quantity ?? item.received_quantity ?? 0,
            );
            const damaged = Number(item.received_damaged_quantity ?? 0);
            const expired = Number(item.received_expired_quantity ?? 0);
            const total = good + damaged + expired;
            payload.append(`items[${index}][received_quantity]`, String(total));
            payload.append(
                `items[${index}][received_good_quantity]`,
                String(good),
            );
            payload.append(
                `items[${index}][received_damaged_quantity]`,
                String(damaged),
            );
            payload.append(
                `items[${index}][received_expired_quantity]`,
                String(expired),
            );
            payload.append(
                `items[${index}][received_condition]`,
                damaged + expired > 0
                    ? 'damaged'
                    : total <
                        Number(
                            item.approved_quantity ??
                                item.requested_quantity ??
                                0,
                        )
                      ? 'shortage'
                      : 'good',
            );
        });

        if (receiveTemperatureMin.value !== '') {
            payload.append(
                'received_temperature_min_c',
                String(receiveTemperatureMin.value),
            );
        }

        if (receiveTemperatureMax.value !== '') {
            payload.append(
                'received_temperature_max_c',
                String(receiveTemperatureMax.value),
            );
        }

        if (receiveNotes.value.trim()) {
            payload.append('notes', receiveNotes.value.trim());
        }

        if (receiptPhoto.value) {
            payload.append('receipt_photo', receiptPhoto.value);
        }

        if (receiverSignature.value) {
            payload.append('receiver_signature', receiverSignature.value);
        }

        const res = await axios.post(
            `/api/supply-requests/${selectedRequest.value.id}/receive`,
            payload,
            { headers: { 'Content-Type': 'multipart/form-data' } },
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
        case 'preparing':
            return {
                label: 'Kho đang soạn hàng',
                color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
            };
        case 'dispatch_pending_approval':
            return {
                label: 'Chờ duyệt xuất kho',
                color: 'bg-cyan-100 text-cyan-800 border-cyan-300',
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
        case 'partial_received':
            return {
                label: 'Đã nhận một phần',
                color: 'bg-orange-100 text-orange-800 border-orange-300',
            };
        case 'disputed':
            return {
                label: 'Đang tranh chấp',
                color: 'bg-rose-100 text-rose-800 border-rose-300',
            };
        case 'rejected':
            return {
                label: 'Kho từ chối',
                color: 'bg-rose-100 text-rose-800 border-rose-300',
            };
        case 'cancelled':
            return {
                label: 'Đã hủy',
                color: 'bg-slate-100 text-slate-700 border-slate-300',
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
            class="flex flex-col gap-4 rounded-2xl border border-indigo-100/90 bg-gradient-to-r from-indigo-50/90 via-slate-50 to-purple-50/60 p-4 text-slate-900 shadow-xs backdrop-blur-md sm:p-5 md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-black/80 dark:from-[#080b12] dark:via-black dark:to-[#080b12] dark:text-white"
        >
            <div class="flex items-center gap-3.5">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm shadow-indigo-600/20 backdrop-blur-md dark:border dark:border-indigo-500/30 dark:bg-indigo-600/25 dark:text-indigo-400"
                >
                    <ShoppingCart class="size-5" />
                </div>
                <div>
                    <h1
                        class="text-lg font-black tracking-tight text-slate-900 md:text-xl lg:text-2xl dark:text-white"
                    >
                        Đặt Hàng Cấp Phát Kho Tổng
                    </h1>
                    <p
                        class="mt-0.5 text-xs leading-normal text-slate-600 dark:text-slate-400"
                    >
                        Gửi đơn yêu cầu bổ sung nguyên liệu daily đến Kho Tổng
                        cho Chi nhánh
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs shadow-2xs backdrop-blur-sm dark:border-white/10 dark:bg-black/50"
                >
                    <Building2 class="size-3.5 text-indigo-400" />
                    <span class="text-slate-600 dark:text-slate-300"
                        >Chi nhánh:
                        <strong class="text-slate-900 dark:text-white">{{
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
                                    Kho Tổng độc lập
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
        <Teleport to="body">
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
                                    Kho Tổng sẽ duyệt và giao nguyên liệu tới
                                    Chi nhánh {{ activeBranch?.name }}
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
                                <label
                                    class="mb-1 block font-medium text-slate-700"
                                    >Ngày cần giao hàng</label
                                >
                                <Input
                                    v-model="
                                        newRequestForm.requested_delivery_date
                                    "
                                    type="date"
                                    class="text-xs"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block font-medium text-slate-700"
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
                                    <Plus class="h-3.5 w-3.5" /> Thêm Nguyên
                                    Liệu
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
                                                    v-model.number="
                                                        item.quantity
                                                    "
                                                    class="w-20 rounded border border-slate-300 px-2 py-1 text-right font-bold text-indigo-600 focus:outline-none"
                                                />
                                            </td>
                                            <td
                                                class="p-2.5 text-right text-slate-600"
                                            >
                                                {{
                                                    formatCurrency(
                                                        item.unit_cost,
                                                    )
                                                }}
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
                                >{{
                                    formatCurrency(calculatedFormTotal)
                                }}</strong
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
        </Teleport>

        <!-- Detail / Receive Goods Modal -->
        <Teleport to="body">
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
                                        getStatusBadge(selectedRequest.status)
                                            .label
                                    }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-300">
                                Xuất từ: Kho Tổng độc lập
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

                        <div
                            v-if="
                                selectedRequest.status === 'dispatched' &&
                                canReceiveRequests
                            "
                            class="grid gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-slate-700 md:grid-cols-3"
                        >
                            <div class="space-y-1 md:col-span-3">
                                <label class="text-xs font-semibold"
                                    >Ghi chú nhận hàng / giải trình chênh
                                    lệch</label
                                >
                                <textarea
                                    v-model="receiveNotes"
                                    rows="2"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs"
                                    placeholder="Bắt buộc khi nhận thiếu hàng"
                                />
                            </div>
                            <label class="space-y-1 text-xs font-semibold">
                                Ảnh thực nhận
                                <input
                                    type="file"
                                    accept="image/*,.pdf"
                                    class="block w-full rounded-lg border border-slate-300 bg-white p-2 text-[11px] font-normal"
                                    @change="setEvidenceFile('photo', $event)"
                                />
                            </label>
                            <label class="space-y-1 text-xs font-semibold">
                                Chữ ký người nhận
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="block w-full rounded-lg border border-slate-300 bg-white p-2 text-[11px] font-normal"
                                    @change="
                                        setEvidenceFile('signature', $event)
                                    "
                                />
                            </label>
                            <label class="space-y-1 text-xs font-semibold">
                                Nhiệt độ thấp nhất (°C)
                                <input
                                    v-model="receiveTemperatureMin"
                                    type="number"
                                    step="0.1"
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-normal"
                                />
                            </label>
                            <label class="space-y-1 text-xs font-semibold">
                                Nhiệt độ cao nhất (°C)
                                <input
                                    v-model="receiveTemperatureMax"
                                    type="number"
                                    step="0.1"
                                    class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-normal"
                                />
                            </label>
                            <div
                                class="flex items-end text-[11px] text-slate-500"
                            >
                                Nếu nhận thiếu, bắt buộc có đủ ảnh và chữ ký để
                                tạo hồ sơ tranh chấp.
                            </div>
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
                                            <th class="p-3 text-center">
                                                Đơn Vị
                                            </th>
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
                                                <div
                                                    v-if="
                                                        selectedRequest.status ===
                                                            'dispatched' &&
                                                        canReceiveRequests
                                                    "
                                                    class="space-y-1"
                                                >
                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        v-model.number="
                                                            item.received_good_quantity
                                                        "
                                                        class="w-24 rounded border border-emerald-300 px-2 py-1 text-right font-bold text-emerald-600 focus:outline-none"
                                                        placeholder="Tốt"
                                                    />
                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        v-model.number="
                                                            item.received_damaged_quantity
                                                        "
                                                        class="w-24 rounded border border-rose-300 px-2 py-1 text-right font-bold text-rose-600 focus:outline-none"
                                                        placeholder="Hỏng"
                                                    />
                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        v-model.number="
                                                            item.received_expired_quantity
                                                        "
                                                        class="w-24 rounded border border-amber-300 px-2 py-1 text-right font-bold text-amber-600 focus:outline-none"
                                                        placeholder="Hết hạn"
                                                    />
                                                    <div
                                                        class="text-[10px] text-slate-500"
                                                    >
                                                        Tổng:
                                                        {{
                                                            Number(
                                                                item.received_good_quantity ||
                                                                    0,
                                                            ) +
                                                            Number(
                                                                item.received_damaged_quantity ||
                                                                    0,
                                                            ) +
                                                            Number(
                                                                item.received_expired_quantity ||
                                                                    0,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
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
                                                {{
                                                    formatCurrency(
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
                            <PackageCheck class="h-4 w-4" /> Xác Nhận Đã Nhận
                            Hàng & Nhập Kho
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
