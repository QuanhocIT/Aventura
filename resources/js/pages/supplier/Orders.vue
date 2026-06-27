<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { 
    Clock, Truck, CheckCircle, AlertTriangle, 
    X, Eye, Upload, FileText
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    orders: any[];
}>();

const page = usePage();
const supplierId = computed(() => (page.props.auth?.user as any)?.supplier_id);

// Realtime Echo Listener
onMounted(() => {
    if (window.Echo && supplierId.value) {
        window.Echo.channel(`supplier.${supplierId.value}`)
            .listen('.purchase-order.placed', (e: any) => {
                toast.success(`Đơn đặt hàng PO mới về: ${e.po_number}!`, {
                    description: `Tổng giá trị: ${Number(e.total_amount).toLocaleString('vi-VN')}đ`,
                    duration: 10000,
                });
                router.reload({ only: ['orders'] });
            });
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
    workflowForm.status = order.status === 'approved' ? 'preparing' : order.status === 'preparing' ? 'shipping' : 'delivered';
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

    router.post(route('supplier.orders.update-status', selectedOrder.value.id), formData as any, {
        onSuccess: () => {
            showWorkflowModal.value = false;
            toast.success('Đã cập nhật trạng thái vận đơn thành công.');
        }
    });
};
</script>

<template>
    <Head title="Supplier Order Fulfillment" />

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-6 text-slate-100">
        <!-- Header -->
        <div class="border-b border-slate-800 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                Xử lý đơn đặt hàng (PO Fulfillment)
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Tiếp nhận đơn hàng realtime từ nhà hàng, xử lý vận đơn và tải hóa đơn điện tử bàn giao hàng.
            </p>
        </div>

        <!-- Orders Table -->
        <div class="bg-slate-900/40 border border-slate-800/80 rounded-xl overflow-hidden backdrop-blur-sm shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-slate-300">
                    <thead class="bg-slate-950/60 text-slate-400 uppercase text-xs border-b border-slate-800">
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
                        <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-900/20 transition-colors">
                            <td class="px-6 py-4 font-mono font-bold text-emerald-400">{{ order.po_number }}</td>
                            <td class="px-6 py-4 text-xs text-slate-400">{{ order.created_at }}</td>
                            <td class="px-6 py-4 font-semibold">{{ Number(order.total_amount).toLocaleString('vi-VN') }}đ</td>
                            <td class="px-6 py-4">
                                <span v-if="order.invoice_file_url" class="inline-flex items-center gap-1 text-emerald-400 text-xs hover:underline">
                                    <FileText class="w-3.5 h-3.5" /> Đã đính kèm
                                </span>
                                <span v-else class="text-slate-500 text-xs">Chưa tải lên</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-0.5">
                                    <span v-if="order.payment_status === 'unpaid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-950 text-slate-400 border border-slate-850">
                                        Chưa thanh toán
                                    </span>
                                    <span v-else-if="order.payment_status === 'escrow_locked'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-950 text-amber-400 border border-amber-900/50">
                                        Ký quỹ (Khóa)
                                    </span>
                                    <span v-else-if="order.payment_status === 'paid'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-950 text-emerald-400 border border-emerald-900/50">
                                        Đã nhận (Paid)
                                    </span>
                                    <span v-else-if="order.payment_status === 'refunded'" class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-950 text-rose-400 border border-rose-900/50">
                                        Đã hoàn tiền
                                    </span>
                                    <p v-if="order.escrow_transaction_id" class="text-[9px] text-slate-500 font-mono mt-0.5 leading-none">{{ order.escrow_transaction_id }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="order.status === 'approved'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-950 text-blue-400 border border-blue-900">
                                    Đơn đặt mới/Chờ chuẩn bị
                                </span>
                                <span v-else-if="order.status === 'preparing'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-950 text-purple-400 border border-purple-900">
                                    Đang chuẩn bị hàng
                                </span>
                                <span v-else-if="order.status === 'shipping'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-950 text-indigo-400 border border-indigo-900 animate-pulse">
                                    Đang giao hàng
                                </span>
                                <span v-else-if="order.status === 'delivered'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-950 text-emerald-400 border border-emerald-900">
                                    Đã hạ hàng tại kho
                                </span>
                                <span v-else-if="order.status === 'frozen'" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-950 text-rose-400 border border-rose-900">
                                    Đóng băng/Lệch đối soát
                                </span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-800 text-slate-400">
                                    Đã hủy
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <button 
                                    @click="openDetailModal(order)"
                                    class="p-1.5 bg-slate-950 border border-slate-800 rounded hover:bg-slate-900 text-slate-300 hover:text-slate-100 transition-colors"
                                    title="Xem chi tiết"
                                >
                                    <Eye class="w-4 h-4" />
                                </button>
                                <button 
                                    v-if="['approved', 'preparing', 'shipping'].includes(order.status)"
                                    @click="openWorkflowModal(order)"
                                    class="px-2.5 py-1.5 bg-emerald-700 hover:bg-emerald-600 text-xs font-bold rounded text-white transition-colors"
                                >
                                    Cập nhật lộ trình
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.length === 0">
                            <td colspan="6" class="text-center py-12 text-slate-500 font-medium">Chưa có đơn đặt hàng nào gửi tới bạn.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detail Modal -->
        <div v-if="showDetailModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-xl overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <div>
                        <h3 class="font-bold text-lg text-slate-200">Chi tiết đơn đặt hàng PO</h3>
                        <p class="text-[10px] text-slate-500 mt-0.5">Mã đơn: {{ selectedOrder?.po_number }}</p>
                    </div>
                    <button @click="showDetailModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Items table -->
                    <div class="border border-slate-850 rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-slate-300">
                            <thead class="bg-slate-950/40 text-slate-400 uppercase text-[10px] border-b border-slate-850">
                                <tr>
                                    <th class="px-4 py-2 text-left">Tên nguyên liệu</th>
                                    <th class="px-4 py-2 text-center">Số lượng</th>
                                    <th class="px-4 py-2 text-right">Đơn giá niêm yết</th>
                                    <th class="px-4 py-2 text-right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850">
                                <tr v-for="item in selectedOrder?.items" :key="item.id" class="hover:bg-slate-900/10">
                                    <td class="px-4 py-2.5 font-semibold text-slate-200">{{ item.ingredient_name }}</td>
                                    <td class="px-4 py-2.5 text-center font-bold text-emerald-400">{{ item.quantity_ordered }} {{ item.unit_symbol }}</td>
                                    <td class="px-4 py-2.5 text-right text-slate-400">{{ Number(item.price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-slate-200">{{ Number(item.quantity_ordered * item.price_per_unit).toLocaleString('vi-VN') }}đ</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Notes -->
                    <div v-if="selectedOrder?.notes" class="bg-slate-950/40 border border-slate-850 rounded-xl p-3.5">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Ghi chú từ nhà hàng</label>
                        <p class="text-xs text-slate-300 leading-relaxed">{{ selectedOrder.notes }}</p>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-800">
                        <button type="button" @click="showDetailModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow Update Modal -->
        <div v-if="showWorkflowModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <h3 class="font-bold text-lg text-slate-200">Cập nhật trạng thái vận đơn</h3>
                    <button @click="showWorkflowModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitWorkflow" class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Trạng thái lộ trình</label>
                        <select v-model="workflowForm.status" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500">
                            <option value="preparing">Đang chuẩn bị hàng</option>
                            <option value="shipping">Đang vận chuyển</option>
                            <option value="delivered">Đã hạ hàng tại kho (Giao thành công)</option>
                        </select>
                    </div>

                    <!-- Invoice Upload (when status is delivered) -->
                    <div v-if="workflowForm.status === 'delivered'" class="space-y-2 animate-in fade-in slide-in-from-top-3 duration-200">
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Đính kèm hóa đơn điện tử / hóa đơn giấy biên nhận <span class="text-rose-500">*</span></label>
                        <div class="border border-dashed border-slate-850 rounded-xl p-4 flex flex-col items-center justify-center bg-slate-950/40 hover:bg-slate-950/70 transition-colors relative">
                            <Upload class="w-8 h-8 text-slate-500 mb-2" />
                            <span class="text-xs text-slate-400 font-semibold">{{ workflowForm.invoice_file ? workflowForm.invoice_file.name : 'Nhấp để chọn tệp hóa đơn giao hàng' }}</span>
                            <span class="text-[10px] text-slate-500 mt-1">Định dạng JPG, PNG, PDF tối đa 4MB.</span>
                            <input type="file" @change="handleFileUpload" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showWorkflowModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="workflowForm.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all">
                            {{ workflowForm.processing ? 'Đang lưu...' : 'Xác nhận cập nhật' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
