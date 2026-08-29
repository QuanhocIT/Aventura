<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { X, Eye, Upload, FileText } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
import { updateStatus } from '@/routes/supplier/orders';

const props = defineProps<{
    orders: any[];
}>();

const page = usePage();
const supplierId = computed(() => (page.props.auth?.user as any)?.supplier_id);

// Realtime Echo Listener
onMounted(() => {
    if (window.Echo && supplierId.value) {
        window.Echo.private(`supplier.${supplierId.value}`).listen(
            '.purchase-order.placed',
            (e: any) => {
                toast.success(`Đơn đặt hàng PO mới về: ${e.po_number}!`, {
                    description: `Tổng giá trị: ${Number(e.total_amount).toLocaleString('vi-VN')}đ`,
                    duration: 10000,
                });
                router.reload({ only: ['orders'] });
            },
        );
    }
});

onUnmounted(() => {
    if (window.Echo && supplierId.value) {
        window.Echo.leaveChannel(`supplier.${supplierId.value}`);
    }
});

// Modal state
const showDetailModal = ref(false);
const showWorkflowModal = ref(false);
const selectedOrder = ref<any>(null);

const workflowForm = useForm({
    status: 'preparing',
    invoice_file: null as File | null,
});

const openDetailModal = (order: any) => {
    selectedOrder.value = order;
    showDetailModal.value = true;
};

const openWorkflowModal = (order: any) => {
    selectedOrder.value = order;
    workflowForm.status =
        order.status === 'approved'
            ? 'preparing'
            : order.status === 'preparing'
              ? 'shipping'
              : 'delivered';
    workflowForm.invoice_file = null;
    showWorkflowModal.value = true;
};

const handleFileUpload = (e: Event) => {
    const input = e.target as HTMLInputElement;

    if (input.files && input.files[0]) {
        workflowForm.invoice_file = input.files[0];
    }
};

const submitWorkflow = () => {
    const formData = new FormData();
    formData.append('status', workflowForm.status);

    if (workflowForm.invoice_file) {
        formData.append('invoice_file', workflowForm.invoice_file);
    }

    router.post(updateStatus.url(selectedOrder.value.id), formData as any, {
        onSuccess: () => {
            showWorkflowModal.value = false;
            toast.success('Đã cập nhật trạng thái vận đơn thành công.');
        },
    });
};

const printOrderSlip = () => {
    if (!selectedOrder.value) {
        return;
    }

    const printWindow = window.open('', '_blank');

    if (!printWindow) {
        toast.error(
            'Vui lòng cho phép trình duyệt hiển thị pop-up để in vận đơn.',
        );

        return;
    }

    const itemsHtml = selectedOrder.value.items
        .map(
            (item: any) => `
        <tr>
            <td style="padding: 8px; border-bottom: 1px solid #ddd;">${item.ingredient_name}</td>
            <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: center;">${item.quantity_ordered} ${item.unit_symbol}</td>
            <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;">${item.price_per_unit.toLocaleString('vi-VN')}đ</td>
            <td style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right; font-weight: bold;">${(item.quantity_ordered * item.price_per_unit).toLocaleString('vi-VN')}đ</td>
        </tr>
    `,
        )
        .join('');

    printWindow.document.write(`
        <html>
        <head>
            <title>Vận đơn giao nhận PO #${selectedOrder.value.po_number}</title>
            <style>
                body { font-family: sans-serif; color: #333; margin: 40px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background-color: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
                .header { text-align: center; margin-bottom: 30px; }
                .details { margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #777; }
                .signature-section { margin-top: 50px; display: flex; justify-content: space-between; }
                .signature-box { width: 200px; text-align: center; border-top: 1px dashed #333; padding-top: 5px; }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            <div class="header">
                <h2>PHIẾU BÀN GIAO & ĐÓNG GÓI VẬT TƯ</h2>
                <p>Số đơn hàng: <strong>${selectedOrder.value.po_number}</strong></p>
            </div>

            <div class="details">
                <p><strong>Ngày đặt hàng:</strong> ${selectedOrder.value.created_at}</p>
                <p><strong>Trạng thái vận đơn:</strong> ${selectedOrder.value.status.toUpperCase()}</p>
                <p><strong>Trạng thái thanh toán:</strong> ${selectedOrder.value.payment_status.toUpperCase()}</p>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Tên nguyên vật liệu</th>
                        <th style="text-align: center;">Số lượng</th>
                        <th style="text-align: right;">Đơn giá</th>
                        <th style="text-align: right;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>

            <h3 style="text-align: right; margin-top: 20px;">Tổng tiền thanh toán: ${selectedOrder.value.total_amount.toLocaleString('vi-VN')}đ</h3>

            <div class="signature-section">
                <div class="signature-box">Nhân viên kho giao hàng<br><small>(Ký và ghi rõ họ tên)</small></div>
                <div class="signature-box">Thủ kho tiếp nhận<br><small>(Ký và ghi rõ họ tên)</small></div>
            </div>

            <div class="footer">
                <p>Cổng chuỗi cung ứng tự động Aventura SaaS — Giải pháp quản trị nhà hàng chuyên sâu</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
};
</script>

<template>
    <Head title="Supplier Order Fulfillment" />

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8 text-slate-100">
        <!-- Header -->
        <div class="border-b border-slate-800 pb-6">
            <BackButton
                fallback-href="/supplier/dashboard"
                label="Trang chủ"
                class="mb-3"
            />
            <h1
                class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-3xl font-extrabold tracking-tight text-transparent"
            >
                Xử lý đơn đặt hàng (PO Fulfillment)
            </h1>
            <p class="mt-1 text-sm text-slate-400">
                Tiếp nhận đơn hàng realtime từ nhà hàng, xử lý vận đơn và tải
                hóa đơn điện tử bàn giao hàng.
            </p>
        </div>

        <!-- Orders Table -->
        <div
            class="overflow-hidden rounded-xl border border-slate-800/80 bg-slate-900/40 shadow-xl backdrop-blur-sm"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead
                        class="border-b border-slate-800 bg-slate-950/60 text-xs text-slate-400 uppercase"
                    >
                        <tr>
                            <th class="px-6 py-4">Mã đơn PO</th>
                            <th class="px-6 py-4">Ngày đặt</th>
                            <th class="px-6 py-4">Tổng tiền gốc</th>
                            <th class="px-6 py-4">Hóa đơn bàn giao</th>
                            <th class="px-6 py-4">Ký quỹ B2B</th>
                            <th class="px-6 py-4">Trạng thái vận đơn</th>
                            <th class="px-6 py-4 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <tr
                            v-for="order in orders"
                            :key="order.id"
                            class="transition-colors hover:bg-slate-900/20"
                        >
                            <td
                                class="px-6 py-4 font-mono font-bold text-emerald-400"
                            >
                                {{ order.po_number }}
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-400">
                                {{ order.created_at }}
                            </td>
                            <td class="px-6 py-4 font-semibold">
                                {{
                                    Number(order.total_amount).toLocaleString(
                                        'vi-VN',
                                    )
                                }}đ
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    v-if="order.invoice_file_url"
                                    class="inline-flex items-center gap-1 text-xs text-emerald-400 hover:underline"
                                >
                                    <FileText class="h-3.5 w-3.5" /> Đã đính kèm
                                </span>
                                <span v-else class="text-xs text-slate-500"
                                    >Chưa tải lên</span
                                >
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span
                                        v-if="order.payment_status === 'unpaid'"
                                        class="border-slate-850 inline-flex items-center rounded border bg-slate-950 px-2 py-0.5 text-[10px] font-semibold text-slate-400"
                                    >
                                        Chưa thanh toán
                                    </span>
                                    <span
                                        v-else-if="
                                            order.payment_status ===
                                            'escrow_locked'
                                        "
                                        class="inline-flex items-center rounded border border-amber-900/50 bg-amber-950 px-2 py-0.5 text-[10px] font-semibold text-amber-400"
                                    >
                                        Ký quỹ (Khóa)
                                    </span>
                                    <span
                                        v-else-if="
                                            order.payment_status === 'paid'
                                        "
                                        class="inline-flex items-center rounded border border-emerald-900/50 bg-emerald-950 px-2 py-0.5 text-[10px] font-semibold text-emerald-400"
                                    >
                                        Đã nhận (Paid)
                                    </span>
                                    <span
                                        v-else-if="
                                            order.payment_status === 'refunded'
                                        "
                                        class="inline-flex items-center rounded border border-rose-900/50 bg-rose-950 px-2 py-0.5 text-[10px] font-semibold text-rose-400"
                                    >
                                        Đã hoàn tiền
                                    </span>
                                    <p
                                        v-if="order.escrow_transaction_id"
                                        class="mt-0.5 font-mono text-[9px] leading-none text-slate-500"
                                    >
                                        {{ order.escrow_transaction_id }}
                                    </p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    v-if="order.status === 'approved'"
                                    class="inline-flex items-center rounded border border-blue-900 bg-blue-950 px-2 py-0.5 text-xs font-semibold text-blue-400"
                                >
                                    Đơn đặt mới/Chờ chuẩn bị
                                </span>
                                <span
                                    v-else-if="order.status === 'preparing'"
                                    class="inline-flex items-center rounded border border-purple-900 bg-purple-950 px-2 py-0.5 text-xs font-semibold text-purple-400"
                                >
                                    Đang chuẩn bị hàng
                                </span>
                                <span
                                    v-else-if="order.status === 'shipping'"
                                    class="inline-flex animate-pulse items-center rounded border border-indigo-900 bg-indigo-950 px-2 py-0.5 text-xs font-semibold text-indigo-400"
                                >
                                    Đang giao hàng
                                </span>
                                <span
                                    v-else-if="order.status === 'delivered'"
                                    class="inline-flex items-center rounded border border-emerald-900 bg-emerald-950 px-2 py-0.5 text-xs font-semibold text-emerald-400"
                                >
                                    Đã hạ hàng tại kho
                                </span>
                                <span
                                    v-else-if="order.status === 'frozen'"
                                    class="inline-flex items-center rounded border border-rose-900 bg-rose-950 px-2 py-0.5 text-xs font-semibold text-rose-400"
                                >
                                    Đóng băng/Lệch đối soát
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400"
                                >
                                    Đã hủy
                                </span>
                            </td>
                            <td class="space-x-2 px-6 py-4 text-right">
                                <button
                                    @click="openDetailModal(order)"
                                    class="rounded border border-slate-800 bg-slate-950 p-1.5 text-slate-300 transition-colors hover:bg-slate-900 hover:text-slate-100"
                                    title="Xem chi tiết"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>
                                <button
                                    v-if="
                                        [
                                            'approved',
                                            'preparing',
                                            'shipping',
                                        ].includes(order.status)
                                    "
                                    @click="openWorkflowModal(order)"
                                    class="rounded bg-emerald-700 px-2.5 py-1.5 text-xs font-bold text-white transition-colors hover:bg-emerald-600"
                                >
                                    Cập nhật lộ trình
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.length === 0">
                            <td
                                colspan="6"
                                class="py-12 text-center font-medium text-slate-500"
                            >
                                Chưa có đơn đặt hàng nào gửi tới bạn.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <div
                v-if="showDetailModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-xl animate-in overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-6 py-4"
                    >
                        <div>
                            <h3 class="text-lg font-bold text-slate-200">
                                Chi tiết đơn đặt hàng PO
                            </h3>
                            <p class="mt-0.5 text-[10px] text-slate-500">
                                Mã đơn: {{ selectedOrder?.po_number }}
                            </p>
                        </div>
                        <button
                            @click="showDetailModal = false"
                            class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-200"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="space-y-4 p-6">
                        <!-- Items table -->
                        <div
                            class="border-slate-850 overflow-hidden rounded-xl border"
                        >
                            <table class="w-full text-xs text-slate-300">
                                <thead
                                    class="border-slate-850 border-b bg-slate-950/40 text-[10px] text-slate-400 uppercase"
                                >
                                    <tr>
                                        <th class="px-4 py-2 text-left">
                                            Tên nguyên liệu
                                        </th>
                                        <th class="px-4 py-2 text-center">
                                            Số lượng
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            Đơn giá niêm yết
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            Thành tiền
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-slate-850 divide-y">
                                    <tr
                                        v-for="item in selectedOrder?.items"
                                        :key="item.id"
                                        class="hover:bg-slate-900/10"
                                    >
                                        <td
                                            class="px-4 py-2.5 font-semibold text-slate-200"
                                        >
                                            {{ item.ingredient_name }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-center font-bold text-emerald-400"
                                        >
                                            {{ item.quantity_ordered }}
                                            {{ item.unit_symbol }}
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right text-slate-400"
                                        >
                                            {{
                                                Number(
                                                    item.price_per_unit,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                        <td
                                            class="px-4 py-2.5 text-right font-semibold text-slate-200"
                                        >
                                            {{
                                                Number(
                                                    item.quantity_ordered *
                                                        item.price_per_unit,
                                                ).toLocaleString('vi-VN')
                                            }}đ
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Notes -->
                        <div
                            v-if="selectedOrder?.notes"
                            class="border-slate-850 rounded-xl border bg-slate-950/40 p-3.5"
                        >
                            <label
                                class="mb-1 block text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >Ghi chú từ nhà hàng</label
                            >
                            <p class="text-xs leading-relaxed text-slate-300">
                                {{ selectedOrder.notes }}
                            </p>
                        </div>

                        <div
                            class="flex justify-end gap-2 border-t border-slate-800 pt-4"
                        >
                            <button
                                type="button"
                                @click="printOrderSlip"
                                class="rounded-lg border border-indigo-900 bg-indigo-950 px-4 py-2 text-sm font-semibold text-indigo-400 transition-colors hover:text-indigo-300"
                            >
                                In phiếu giao hàng
                            </button>
                            <button
                                type="button"
                                @click="showDetailModal = false"
                                class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-semibold text-slate-400 transition-colors hover:text-slate-200"
                            >
                                Đóng
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Workflow Update Modal -->
        <Teleport to="body">
            <div
                v-if="showWorkflowModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            >
                <div
                    class="w-full max-w-md animate-in overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-6 py-4"
                    >
                        <h3 class="text-lg font-bold text-slate-200">
                            Cập nhật trạng thái vận đơn
                        </h3>
                        <button
                            @click="showWorkflowModal = false"
                            class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-200"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <form
                        @submit.prevent="submitWorkflow"
                        class="space-y-5 p-6"
                    >
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Trạng thái lộ trình</label
                            >
                            <select
                                v-model="workflowForm.status"
                                required
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="preparing">
                                    Đang chuẩn bị hàng
                                </option>
                                <option value="shipping">
                                    Đang vận chuyển
                                </option>
                                <option value="delivered">
                                    Đã hạ hàng tại kho (Giao thành công)
                                </option>
                            </select>
                        </div>

                        <!-- Invoice Upload (when status is delivered) -->
                        <div
                            v-if="workflowForm.status === 'delivered'"
                            class="animate-in space-y-2 duration-200 fade-in slide-in-from-top-3"
                        >
                            <label
                                class="block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Đính kèm hóa đơn điện tử / hóa đơn giấy biên
                                nhận <span class="text-rose-500">*</span></label
                            >
                            <div
                                class="border-slate-850 relative flex flex-col items-center justify-center rounded-xl border border-dashed bg-slate-950/40 p-4 transition-colors hover:bg-slate-950/70"
                            >
                                <Upload class="mb-2 h-8 w-8 text-slate-500" />
                                <span
                                    class="text-xs font-semibold text-slate-400"
                                    >{{
                                        workflowForm.invoice_file
                                            ? workflowForm.invoice_file.name
                                            : 'Nhấp để chọn tệp hóa đơn giao hàng'
                                    }}</span
                                >
                                <span class="mt-1 text-[10px] text-slate-500"
                                    >Định dạng JPG, PNG, PDF tối đa 4MB.</span
                                >
                                <input
                                    type="file"
                                    @change="handleFileUpload"
                                    required
                                    class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                />
                            </div>
                        </div>

                        <div
                            class="mt-6 flex justify-end gap-2 border-t border-slate-800 pt-4"
                        >
                            <button
                                type="button"
                                @click="showWorkflowModal = false"
                                class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-semibold text-slate-400 transition-colors hover:text-slate-200"
                            >
                                Hủy bỏ
                            </button>
                            <button
                                type="submit"
                                :disabled="workflowForm.processing"
                                class="rounded-lg bg-gradient-to-r from-emerald-600 to-teal-500 px-4 py-2 text-sm font-semibold text-white transition-all hover:from-emerald-500 hover:to-teal-400"
                            >
                                {{
                                    workflowForm.processing
                                        ? 'Đang lưu...'
                                        : 'Xác nhận cập nhật'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
