<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    DollarSign,
    FileText,
    Gavel,
    Lock,
    Save,
    ShieldAlert,
    ShieldCheck,
    UserCheck,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    summary: any;
    rules: any;
    recentDisputes: Array<any>;
    employees: Array<any>;
}>();

const activeTab = ref<'disputes' | 'rules' | 'analytics'>('disputes');
const isProcessing = ref(false);
const selectedDispute = ref<any>(null);
const isResolveModalOpen = ref(false);

// Governance Form State
const rulesForm = ref({
    max_auto_approve_variance_amount: props.rules?.max_auto_approve_variance_amount || 500000,
    max_auto_approve_variance_percent: props.rules?.max_auto_approve_variance_percent || 3,
    require_seal_code_on_dispatch: props.rules?.require_seal_code_on_dispatch ?? true,
    auto_dispute_on_discrepancy: props.rules?.auto_dispute_on_discrepancy ?? true,
    penalty_deduction_enabled: props.rules?.penalty_deduction_enabled ?? true,
});

// Resolution Form State
const resolutionForm = ref({
    responsible_type: 'transporter',
    responsible_user_id: null as number | null,
    resolution_notes: '',
});

const openResolveModal = (dispute: any) => {
    selectedDispute.value = dispute;
    resolutionForm.value = {
        responsible_type: dispute.responsible_type || 'transporter',
        responsible_user_id: dispute.responsible_user_id || null,
        resolution_notes: '',
    };
    isResolveModalOpen.value = true;
};

const saveRules = async () => {
    isProcessing.value = true;

    try {
        const res = await axios.post('/api/warehouse-governance/rules', rulesForm.value);

        if (res.data.success) {
            toast.success('Đã lưu Cấu hình Bộ Quy Tắc Siết Chặt Quản Lý Kho thành công!');
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể lưu cấu hình quy tắc.');
    } finally {
        isProcessing.value = false;
    }
};

const submitResolution = async () => {
    if (!selectedDispute.value) {
        return;
    }

    if (!resolutionForm.value.resolution_notes.trim()) {
        toast.error('Vui lòng nhập Biện pháp xử lý & Ghi chú quy trách nhiệm.');

        return;
    }

    isProcessing.value = true;

    try {
        const res = await axios.post(
            `/api/warehouse-governance/disputes/${selectedDispute.value.id}/resolve`,
            resolutionForm.value,
        );

        if (res.data.success) {
            toast.success('Đã giải quyết biên bản bất đồng & quy trách nhiệm bồi thường!');
            isResolveModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra khi xử lý biên bản.');
    } finally {
        isProcessing.value = false;
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
};

const getResponsibleLabel = (type: string) => {
    switch (type) {
        case 'warehouse_staff':
            return 'Nhân viên xuất Kho Tổng';
        case 'transporter':
            return 'Đơn vị Vận chuyển / Tài xế';
        case 'branch_staff':
            return 'Nhân viên nhận Kho Chi nhánh';
        default:
            return 'Chưa xác định';
    }
};
</script>

<template>
    <Head title="Quản Trị Siết Chặt Kho & Quy Trách Nhiệm" />

    <div class="p-6 space-y-6 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-rose-950 via-slate-900 to-slate-950 p-6 rounded-2xl text-white shadow-xl">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-rose-500/20 rounded-xl border border-rose-400/30">
                    <ShieldAlert class="w-8 h-8 text-rose-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Quản Trị Siết Chặt Kho & Quy Trách Nhiệm</h1>
                    <p class="text-sm text-rose-200/80">Bộ quy tắc siết chặt tài chính, xử lý bất đồng giao nhận & bồi thường thất thoát</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-rose-500/20 border border-rose-400/30 text-rose-300 text-xs rounded-full font-semibold flex items-center gap-1.5">
                    <Lock class="w-3.5 h-3.5" /> Chế độ Trưởng Kho
                </span>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card class="border-rose-200 bg-rose-50/30 shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-rose-700">Biên Bản Chờ Xử Lý</p>
                        <p class="text-2xl font-bold text-rose-900 mt-1">{{ summary.open_disputes_count }}</p>
                    </div>
                    <div class="p-2.5 bg-rose-100 rounded-lg text-rose-700">
                        <AlertTriangle class="w-5 h-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-amber-200 bg-amber-50/30 shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-amber-700">Thiệt hại Bất đồng Giao nhận</p>
                        <p class="text-xl font-bold text-amber-900 mt-1">{{ formatCurrency(summary.total_discrepancy_loss) }}</p>
                    </div>
                    <div class="p-2.5 bg-amber-100 rounded-lg text-amber-700">
                        <DollarSign class="w-5 h-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-slate-200 shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Thiệt hại Hủy hàng / Rác</p>
                        <p class="text-xl font-bold text-slate-900 mt-1">{{ formatCurrency(summary.total_waste_loss) }}</p>
                    </div>
                    <div class="p-2.5 bg-slate-100 rounded-lg text-slate-600">
                        <FileText class="w-5 h-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-indigo-200 bg-indigo-50/30 shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-indigo-700">Tổng Thất Thoát Tài Chính</p>
                        <p class="text-xl font-bold text-indigo-950 mt-1">{{ formatCurrency(summary.total_combined_loss) }}</p>
                    </div>
                    <div class="p-2.5 bg-indigo-100 rounded-lg text-indigo-700">
                        <Gavel class="w-5 h-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Navigation Tabs -->
        <Card class="border-slate-200">
            <CardContent class="p-3 flex items-center justify-between">
                <div class="flex gap-2">
                    <button
                        @click="activeTab = 'disputes'"
                        :class="['px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-2', activeTab === 'disputes' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        <AlertTriangle class="w-4 h-4 text-rose-400" /> Biên Bản Bất Đồng Giao Nhận ({{ summary.open_disputes_count }})
                    </button>
                    <button
                        @click="activeTab = 'rules'"
                        :class="['px-4 py-2 text-xs font-bold rounded-xl transition flex items-center gap-2', activeTab === 'rules' ? 'bg-slate-900 text-white shadow' : 'text-slate-600 hover:bg-slate-100']"
                    >
                        <ShieldCheck class="w-4 h-4 text-indigo-400" /> Bộ Quy Tắc & Hạn Mức Kiểm Soát
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 1: Disputes List -->
        <Card v-if="activeTab === 'disputes'" class="border-slate-200 shadow-sm">
            <CardHeader class="border-b bg-slate-50/50 py-4">
                <CardTitle class="text-base font-bold text-slate-900">Danh Sách Biên Bản Bất Đồng & Sai Lệch Giao Nhận</CardTitle>
                <CardDescription class="text-xs">Truy vết trách nhiệm giữa Kho Tổng, Đơn vị vận chuyển và Kho Chi nhánh khi phát sinh hàng thiếu/hỏng</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 text-slate-600 font-semibold border-b">
                            <tr>
                                <th class="p-3 pl-4">Mã Biên Bản</th>
                                <th class="p-3">Nguyên Liệu</th>
                                <th class="p-3 text-right">Xuất Kho</th>
                                <th class="p-3 text-right">Thực Nhận</th>
                                <th class="p-3 text-right">Chênh Lệch</th>
                                <th class="p-3 text-right">Thiệt hại (VND)</th>
                                <th class="p-3">Đối Tượng Bồi Thường</th>
                                <th class="p-3">Trạng Thái</th>
                                <th class="p-3 text-right pr-4">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="recentDisputes.length === 0">
                                <td colspan="9" class="p-8 text-center text-slate-400">
                                    Không có biên bản bất đồng giao nhận nào phát sinh.
                                </td>
                            </tr>
                            <tr v-for="disp in recentDisputes" :key="disp.id" class="hover:bg-slate-50 transition">
                                <td class="p-3 pl-4 font-mono font-bold text-rose-600">{{ disp.dispute_code }}</td>
                                <td class="p-3 font-semibold text-slate-800">{{ disp.ingredient?.name }}</td>
                                <td class="p-3 text-right font-medium text-slate-600">{{ disp.dispatched_quantity }}</td>
                                <td class="p-3 text-right font-medium text-slate-600">{{ disp.received_quantity }}</td>
                                <td class="p-3 text-right font-bold text-rose-600">-{{ disp.discrepancy_quantity }}</td>
                                <td class="p-3 text-right font-bold text-amber-700">{{ formatCurrency(disp.financial_loss_amount) }}</td>
                                <td class="p-3 text-slate-700">
                                    {{ disp.responsible_user ? disp.responsible_user.name : getResponsibleLabel(disp.responsible_type) }}
                                </td>
                                <td class="p-3">
                                    <span v-if="disp.status === 'open'" class="px-2.5 py-1 rounded-full border text-[11px] font-semibold bg-rose-100 text-rose-800 border-rose-300">
                                        Chờ Xử Lý
                                    </span>
                                    <span v-else class="px-2.5 py-1 rounded-full border text-[11px] font-semibold bg-emerald-100 text-emerald-800 border-emerald-300">
                                        Đã Giải Quyết
                                    </span>
                                </td>
                                <td class="p-3 text-right pr-4">
                                    <Button @click="openResolveModal(disp)" size="sm" variant="outline" class="h-8 gap-1.5 text-xs">
                                        <Gavel class="w-3.5 h-3.5" /> Quy Trách Nhiệm
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 2: Rules Governance Config -->
        <Card v-if="activeTab === 'rules'" class="border-slate-200 shadow-sm max-w-3xl mx-auto">
            <CardHeader class="border-b bg-slate-50/50 py-4">
                <CardTitle class="text-base font-bold text-slate-900">Thiết Lập Bộ Quy Tắc & Hạn Mức Kiểm Soát Hàng Hóa</CardTitle>
                <CardDescription class="text-xs">Cấu hình các rào cản tự động ngăn chặn thất thoát tài chính và gian lận trong kho</CardDescription>
            </CardHeader>
            <CardContent class="p-6 space-y-6 text-xs">
                <!-- Rule 1: Threshold Amount -->
                <div class="p-4 bg-slate-50 border rounded-xl space-y-2">
                    <label class="font-bold text-slate-800 text-sm block">1. Hạn Mức Tiền Chênh Lệch Tối Đa Tự Chốt (VNĐ)</label>
                    <p class="text-slate-500">Phiếu kiểm kê hoặc hủy hàng có giá trị chênh lệch vượt ngưỡng này sẽ bị KHÓA TỰ ĐỘNG và bắt buộc Trưởng Kho Tổng duyệt.</p>
                    <Input v-model.number="rulesForm.max_auto_approve_variance_amount" type="number" step="50000" class="text-xs font-bold text-indigo-700 max-w-xs" />
                </div>

                <!-- Rule 2: Threshold Percent -->
                <div class="p-4 bg-slate-50 border rounded-xl space-y-2">
                    <label class="font-bold text-slate-800 text-sm block">2. Tỷ Lệ % Sai Lệch Tối Đa Cho Phép (%)</label>
                    <p class="text-slate-500">Tỷ lệ sai lệch giữa Tồn thực tế vs Tồn lý thuyết vượt quá phần trăm này sẽ được cảnh báo rủi ro cao.</p>
                    <Input v-model.number="rulesForm.max_auto_approve_variance_percent" type="number" step="0.5" class="text-xs font-bold text-indigo-700 max-w-xs" />
                </div>

                <!-- Rule 3: Seal Code Required -->
                <div class="p-4 bg-slate-50 border rounded-xl flex items-center justify-between">
                    <div>
                        <label class="font-bold text-slate-800 text-sm block">3. Bắt Buộc Mã Niêm Phong (Seal Code) Khi Xuất Kho</label>
                        <p class="text-slate-500">Yêu cầu Nhân viên Kho Tổng phải nhập Mã Seal niêm phong thùng hàng trước khi giao xe.</p>
                    </div>
                    <input type="checkbox" v-model="rulesForm.require_seal_code_on_dispatch" class="w-5 h-5 accent-indigo-600 rounded" />
                </div>

                <!-- Rule 4: Auto Dispute -->
                <div class="p-4 bg-slate-50 border rounded-xl flex items-center justify-between">
                    <div>
                        <label class="font-bold text-slate-800 text-sm block">4. Tự Động Khởi Tạo Biên Bản Bất Đồng Khi Chi Nhánh Nhận Thiếu</label>
                        <p class="text-slate-500">Hệ thống sẽ lập tức tạo Biên bản khiếu nại để điều tra khi số lượng thực nhận nhỏ hơn số lượng xuất.</p>
                    </div>
                    <input type="checkbox" v-model="rulesForm.auto_dispute_on_discrepancy" class="w-5 h-5 accent-indigo-600 rounded" />
                </div>

                <!-- Rule 5: Penalty Deduction -->
                <div class="p-4 bg-slate-50 border rounded-xl flex items-center justify-between">
                    <div>
                        <label class="font-bold text-slate-800 text-sm block">5. Cho Phép Phân Bổ Thiệt Hại Bồi Thường Vào Bảng Lương</label>
                        <p class="text-slate-500">Cho phép Trưởng Kho chỉ định Nhân viên chịu trách nhiệm và đẩy khoản tiền phạt bồi thường sang Phân hệ Lương.</p>
                    </div>
                    <input type="checkbox" v-model="rulesForm.penalty_deduction_enabled" class="w-5 h-5 accent-indigo-600 rounded" />
                </div>

                <div class="pt-4 flex justify-end">
                    <Button @click="saveRules" size="sm" :disabled="isProcessing" class="bg-indigo-600 hover:bg-indigo-700 text-white gap-2 font-semibold text-xs">
                        <Save class="w-4 h-4" /> Lưu Bộ Quy Tắc Kiểm Soát
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Resolve Dispute Modal -->
        <div v-if="isResolveModalOpen && selectedDispute" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-xl w-full flex flex-col overflow-hidden border border-slate-200">
                <div class="p-5 border-b bg-slate-900 text-white flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <Gavel class="w-6 h-6 text-rose-400" />
                        <div>
                            <h3 class="font-bold text-base">Xử Lý Biên Bản Bất Đồng Giao Nhận</h3>
                            <p class="text-xs text-slate-300">Mã biên bản: <span class="font-mono text-rose-300 font-bold">{{ selectedDispute.dispute_code }}</span></p>
                        </div>
                    </div>
                    <button @click="isResolveModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg">
                        <X class="w-6 h-6" />
                    </button>
                </div>

                <div class="p-6 space-y-5 text-xs">
                    <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 space-y-1">
                        <div><strong>Nguyên liệu:</strong> {{ selectedDispute.ingredient?.name }}</div>
                        <div><strong>Số lượng chênh lệch:</strong> Thiếu {{ selectedDispute.discrepancy_quantity }} (Xuất {{ selectedDispute.dispatched_quantity }} - Nhận {{ selectedDispute.received_quantity }})</div>
                        <div><strong>Giá trị thiệt hại:</strong> <strong class="text-rose-900 text-sm font-bold ml-1">{{ formatCurrency(selectedDispute.financial_loss_amount) }}</strong></div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Xác định Bên Chịu Trách Nhiệm</label>
                        <select v-model="resolutionForm.responsible_type" class="w-full border rounded-lg p-2 font-medium bg-white text-slate-800 focus:outline-none">
                            <option value="transporter">Đơn vị Vận chuyển / Tài xế giao hàng</option>
                            <option value="warehouse_staff">Nhân viên xuất Kho Tổng (Sai sót khi đóng gói)</option>
                            <option value="branch_staff">Nhân viên nhận Kho Chi nhánh (Khai báo sai)</option>
                            <option value="unknown">Hao hụt rủi ro bất khả kháng</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Chọn Cá Nhân Bồi Thường Thiệt Hại (Nếu có)</label>
                        <select v-model="resolutionForm.responsible_user_id" class="w-full border rounded-lg p-2 font-medium bg-white text-slate-800 focus:outline-none">
                            <option :value="null">-- Không gán cá nhân cụ thể --</option>
                            <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                {{ emp.name }} ({{ emp.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 mb-1">Kết luận Điều tra & Biện Pháp Xử Lý (*)</label>
                        <textarea
                            v-model="resolutionForm.resolution_notes"
                            rows="3"
                            placeholder="Nhập nội dung kết luận xử lý, lý do bồi thường hoặc biên bản làm việc..."
                            class="w-full border rounded-lg p-2 text-xs focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div class="p-4 border-t bg-slate-50 flex items-center justify-between">
                    <Button @click="isResolveModalOpen = false" variant="ghost" size="sm" class="text-xs">Hủy</Button>
                    <Button @click="submitResolution" size="sm" :disabled="isProcessing" class="bg-rose-600 hover:bg-rose-700 text-white gap-1.5 font-semibold text-xs">
                        <UserCheck class="w-4 h-4" /> Xác Nhận Quyết Định Xử Lý
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
