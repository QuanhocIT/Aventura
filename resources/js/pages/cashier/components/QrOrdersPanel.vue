<script setup lang="ts">
import axios from 'axios';
import { ref } from 'vue';
import { Sparkles, Clock, Pencil, Ban, Plus, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardHeader,
    CardTitle,
    CardDescription,
    CardContent,
} from '@/components/ui/card';

const emit = defineEmits<{
    (e: 'confirmQrOrder', orderId: number): void;
    (
        e: 'updateExternalOrderStatus',
        payload: { orderId: number; status: string },
    ): void;
    (e: 'refreshQrOrders', payload: { message: string; type: 'success' | 'error' }): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);

const props = defineProps<{
    qrOrders: any[];
    externalOrders: any[];
    products: Array<{ id: number; name: string; price: number }>;
    confirmingOrderId?: number | null;
    updatingExternalId?: number | null;
}>();

const editingOrder = ref<any | null>(null);
const editingItems = ref<Array<{ product_id: number; name: string; quantity: number; notes: string }>>([]);
const revisionMessage = ref('');
const selectedProductId = ref<number | null>(null);
const rejectingOrder = ref<any | null>(null);
const rejectionReason = ref('');
const isSavingRevision = ref(false);
const isRejecting = ref(false);

const itemName = (item: any) => item.product_name || item.product?.name || item.name || 'Món';

function openEdit(order: any) {
    editingOrder.value = order;
    editingItems.value = (order.items || []).map((item: any) => ({
        product_id: Number(item.product_id || item.product?.id),
        name: itemName(item),
        quantity: Number(item.quantity || 1),
        notes: item.notes || '',
    }));
    revisionMessage.value = '';
    selectedProductId.value = null;
}

function addProduct() {
    if (!selectedProductId.value) return;
    const product = props.products.find((item) => item.id === selectedProductId.value);
    if (!product) return;

    const existing = editingItems.value.find((item) => item.product_id === product.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        editingItems.value.push({ product_id: product.id, name: product.name, quantity: 1, notes: '' });
    }
    selectedProductId.value = null;
}

function removeItem(index: number) {
    editingItems.value.splice(index, 1);
}

async function saveRevision() {
    if (!editingOrder.value || !revisionMessage.value.trim() || editingItems.value.length === 0) return;

    isSavingRevision.value = true;
    try {
        const response = await axios.patch(`/api/temporary-orders/${editingOrder.value.id}/revise`, {
            message: revisionMessage.value.trim(),
            items: editingItems.value.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
                notes: item.notes || null,
            })),
        });
        editingOrder.value = null;
        emit('refreshQrOrders', { message: response.data.message || 'Đã gửi thay đổi tới khách.', type: 'success' });
    } catch (error: any) {
        emit('refreshQrOrders', { message: error.response?.data?.message || 'Không thể chỉnh đơn.', type: 'error' });
    } finally {
        isSavingRevision.value = false;
    }
}

function openReject(order: any) {
    rejectingOrder.value = order;
    rejectionReason.value = '';
}

async function submitRejection() {
    if (!rejectingOrder.value || !rejectionReason.value.trim()) return;

    isRejecting.value = true;
    try {
        const response = await axios.post(`/api/temporary-orders/${rejectingOrder.value.id}/cancel`, {
            reason: rejectionReason.value.trim(),
        });
        rejectingOrder.value = null;
        emit('refreshQrOrders', { message: response.data.message || 'Đã từ chối đơn và thông báo tới khách.', type: 'success' });
    } catch (error: any) {
        emit('refreshQrOrders', { message: error.response?.data?.message || 'Không thể từ chối đơn.', type: 'error' });
    } finally {
        isRejecting.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Đơn QR Tại Bàn Chờ Xác Nhận -->
        <Card
            class="rounded-3xl border-slate-200 shadow-sm dark:border-slate-800"
        >
            <CardHeader
                class="border-b border-slate-100 pb-4 dark:border-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-left">
                        <Sparkles
                            class="size-5 animate-pulse text-indigo-600"
                        />
                        <div>
                            <CardTitle class="text-base font-black"
                                >Đơn QR tại bàn chờ duyệt</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Khách tự quét mã QR gọi món tại
                                bàn</CardDescription
                            >
                        </div>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-xl bg-indigo-50 font-mono text-xs font-bold text-indigo-600"
                    >
                        {{ qrOrders.length }} đơn mới
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <div
                    v-if="qrOrders.length === 0"
                    class="flex h-32 flex-col items-center justify-center text-slate-400"
                >
                    <Sparkles class="size-8 stroke-1" />
                    <p class="mt-2 text-xs font-bold">
                        Hiện không có đơn QR mới nào
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="order in qrOrders"
                        :key="order.id"
                        class="flex flex-col justify-between rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4 text-left dark:border-indigo-900/30 dark:bg-indigo-950/20"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-black text-slate-800 dark:text-slate-100"
                                >
                                    Bàn
                                    {{ order.table?.name || 'Chưa chọn bàn' }}
                                </span>
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                    >#{{ order.order_number }}</span
                                >
                            </div>

                            <div
                                class="mt-3 flex flex-col gap-1 border-t border-indigo-100/60 pt-2 text-xs dark:border-indigo-900/40"
                            >
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex justify-between"
                                >
                                    <span
                                        class="text-slate-600 dark:text-slate-300"
                                    >
                                        {{ item.quantity }}x
                                        {{ item.product_name || item.product?.name || item.name || 'Món' }}
                                    </span>
                                    <span class="font-mono font-bold"
                                        >{{
                                            numberFormat(
                                                item.line_total ?? item.unit_price * item.quantity,
                                            )
                                        }}đ</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex flex-col gap-3 border-t border-indigo-100/60 pt-3 dark:border-indigo-900/40"
                        >
                            <span
                                class="font-mono text-sm font-black text-indigo-600 dark:text-indigo-400"
                            >
                                {{ numberFormat(order.total_amount) }}đ
                            </span>
                            <div class="flex flex-wrap justify-end gap-2">
                                <Button
                                    v-if="order.is_temporary !== false"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl text-xs font-bold"
                                    @click="openEdit(order)"
                                >
                                    <Pencil class="mr-1 size-3.5" /> Chỉnh đơn
                                </Button>
                                <Button
                                    v-if="order.is_temporary !== false"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-xl border-red-200 text-xs font-bold text-red-600 hover:bg-red-50"
                                    @click="openReject(order)"
                                >
                                    <Ban class="mr-1 size-3.5" /> Từ chối
                                </Button>
                                <Button
                                    size="sm"
                                    class="rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                    :disabled="confirmingOrderId === order.id"
                                    @click="emit('confirmQrOrder', order.id)"
                                >
                                    {{
                                        confirmingOrderId === order.id
                                            ? 'Đang duyệt...'
                                            : 'Duyệt đơn QR'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Đơn hàng Ngoại sàn / Delivery -->
        <Card
            class="rounded-3xl border-slate-200 shadow-sm dark:border-slate-800"
        >
            <CardHeader
                class="border-b border-slate-100 pb-4 dark:border-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-left">
                        <Clock class="size-5 text-emerald-600" />
                        <div>
                            <CardTitle class="text-base font-black"
                                >Đơn Mang về & Giao hàng</CardTitle
                            >
                            <CardDescription class="text-xs"
                                >Quản lý trạng thái các đơn online, mang
                                về</CardDescription
                            >
                        </div>
                    </div>
                    <Badge
                        variant="secondary"
                        class="rounded-xl bg-emerald-50 font-mono text-xs font-bold text-emerald-600"
                    >
                        {{ externalOrders.length }} đơn đang chạy
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <div
                    v-if="externalOrders.length === 0"
                    class="flex h-32 flex-col items-center justify-center text-slate-400"
                >
                    <Clock class="size-8 stroke-1" />
                    <p class="mt-2 text-xs font-bold">
                        Không có đơn hàng mang về nào đang xử lý
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="order in externalOrders"
                        :key="order.id"
                        class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-black text-slate-800 dark:text-slate-100"
                                    >#{{ order.order_number }}</span
                                >
                                <Badge
                                    variant="outline"
                                    class="text-[10px] font-bold"
                                >
                                    {{
                                        order.order_type === 'takeaway'
                                            ? 'Mang về'
                                            : 'Giao hàng'
                                    }}
                                </Badge>
                            </div>

                            <div
                                class="mt-2 text-xs font-bold text-slate-600 dark:text-slate-300"
                            >
                                👤 {{ order.customer_name || 'Khách lẻ' }}
                            </div>

                            <div
                                class="mt-2 flex flex-col gap-1 border-t pt-2 text-xs"
                            >
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex justify-between"
                                >
                                    <span
                                        >{{ item.quantity }}x
                                        {{ item.product?.name || 'Món' }}</span
                                    >
                                    <span class="font-mono font-bold"
                                        >{{
                                            numberFormat(
                                                item.unit_price * item.quantity,
                                            )
                                        }}đ</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-between border-t pt-3"
                        >
                            <span
                                class="font-mono text-sm font-black text-emerald-600"
                            >
                                {{ numberFormat(order.total_amount) }}đ
                            </span>
                            <div class="flex gap-1.5">
                                <Button
                                    v-if="order.status === 'pending'"
                                    size="sm"
                                    class="rounded-xl bg-indigo-600 text-[11px] font-bold hover:bg-indigo-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'confirmed',
                                        })
                                    "
                                >
                                    Nhận đơn
                                </Button>
                                <Button
                                    v-else-if="order.status === 'confirmed'"
                                    size="sm"
                                    class="rounded-xl bg-amber-600 text-[11px] font-bold hover:bg-amber-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'preparing',
                                        })
                                    "
                                >
                                    Chuẩn bị
                                </Button>
                                <Button
                                    v-else-if="order.status === 'preparing'"
                                    size="sm"
                                    class="rounded-xl bg-emerald-600 text-[11px] font-bold hover:bg-emerald-700"
                                    :disabled="updatingExternalId === order.id"
                                    @click="
                                        emit('updateExternalOrderStatus', {
                                            orderId: order.id,
                                            status: 'completed',
                                        })
                                    "
                                >
                                    Hoàn thành
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="editingOrder"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            @click.self="editingOrder = null"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">
                            Chỉnh đơn {{ editingOrder.order_number }} - {{ editingOrder.table?.name }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Khách tại bàn sẽ nhận thông báo và cần xác nhận thay đổi.
                        </p>
                    </div>
                    <button class="rounded-full p-1 text-slate-400 hover:bg-slate-100" @click="editingOrder = null">
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-64 space-y-3 overflow-y-auto pr-1">
                    <div
                        v-for="(item, index) in editingItems"
                        :key="`${item.product_id}-${index}`"
                        class="rounded-2xl border border-slate-200 p-3 dark:border-slate-700"
                    >
                        <div class="flex items-center gap-3">
                            <span class="min-w-0 flex-1 truncate text-sm font-bold">{{ item.name }}</span>
                            <input
                                v-model.number="item.quantity"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-center text-sm dark:border-slate-700 dark:bg-slate-800"
                            />
                            <button class="rounded-lg p-1.5 text-red-500 hover:bg-red-50" @click="removeItem(index)">
                                <X class="size-4" />
                            </button>
                        </div>
                        <input
                            v-model="item.notes"
                            type="text"
                            placeholder="Ghi chú món (không bắt buộc)"
                            class="mt-2 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800"
                        />
                    </div>
                </div>

                <div class="mt-3 flex gap-2">
                    <select
                        v-model="selectedProductId"
                        class="min-w-0 flex-1 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"
                    >
                        <option :value="null">Thêm món...</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }} - {{ numberFormat(product.price) }}đ
                        </option>
                    </select>
                    <Button type="button" variant="outline" class="rounded-xl" @click="addProduct">
                        <Plus class="size-4" />
                    </Button>
                </div>

                <textarea
                    v-model="revisionMessage"
                    rows="3"
                    placeholder="Nhập lý do hoặc nội dung thay đổi để khách xác nhận..."
                    class="mt-4 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" class="rounded-xl" @click="editingOrder = null">Hủy</Button>
                    <Button
                        class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                        :disabled="isSavingRevision || !revisionMessage.trim() || editingItems.length === 0"
                        @click="saveRevision"
                    >
                        {{ isSavingRevision ? 'Đang gửi...' : 'Lưu & gửi khách xác nhận' }}
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="rejectingOrder"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
            @click.self="rejectingOrder = null"
        >
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-black text-slate-900 dark:text-white">Từ chối đơn QR</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Khách tại {{ rejectingOrder.table?.name || 'bàn này' }} sẽ được thông báo.
                        </p>
                    </div>
                    <button class="rounded-full p-1 text-slate-400 hover:bg-slate-100" @click="rejectingOrder = null">
                        <X class="size-5" />
                    </button>
                </div>
                <textarea
                    v-model="rejectionReason"
                    rows="4"
                    placeholder="Nhập lý do từ chối đơn..."
                    class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" class="rounded-xl" @click="rejectingOrder = null">Hủy</Button>
                    <Button
                        class="rounded-xl bg-red-600 text-white hover:bg-red-700"
                        :disabled="isRejecting || !rejectionReason.trim()"
                        @click="submitRejection"
                    >
                        {{ isRejecting ? 'Đang từ chối...' : 'Xác nhận từ chối' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
