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
import { computed, ref, watch } from 'vue';
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
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
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
    max_auto_approve_variance_amount:
        props.rules?.max_auto_approve_variance_amount ?? 500000,
    max_auto_approve_variance_percent:
        props.rules?.max_auto_approve_variance_percent ?? 3,
    require_seal_code_on_dispatch:
        props.rules?.require_seal_code_on_dispatch ?? true,
    auto_dispute_on_discrepancy:
        props.rules?.auto_dispute_on_discrepancy ?? true,
    penalty_deduction_enabled: props.rules?.penalty_deduction_enabled ?? true,
});

// Resolution Form State
const resolutionForm = ref({
    responsible_type: 'transporter',
    responsible_user_id: null as number | null,
    resolution_notes: '',
});

const eligibleEmployees = computed(() => {
    const type = resolutionForm.value.responsible_type;
    const branchId = Number(selectedDispute.value?.supply_request?.to_branch_id ?? 0);

    if (type === 'transporter' || type === 'unknown') {
        return [];
    }

    return props.employees.filter((employee) => {
        const roleNames = (employee.roles ?? []).map((role: any) => role.name);

        if (type === 'warehouse_staff') {
            return roleNames.includes('warehouse_staff') && employee.warehouse_staff_status !== 'inactive';
        }

        return roleNames.some((role: string) => ['branch_staff', 'staff', 'manager'].includes(role))
            && (!branchId || Number(employee.branch_id) === branchId);
    });
});

const canResolveDispute = (status: string) =>
    ['open', 'investigating', 'appealed'].includes(status);

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'appealed':
            return 'Đang khiếu nại';
        case 'penalized':
            return 'Đã phân bổ bồi thường';
        case 'resolved':
            return 'Đã giải quyết';
        case 'investigating':
        case 'open':
            return 'Chờ xử lý';
        default:
            return status;
    }
};

watch(
    () => resolutionForm.value.responsible_type,
    () => {
        if (!eligibleEmployees.value.some((employee) => employee.id === resolutionForm.value.responsible_user_id)) {
            resolutionForm.value.responsible_user_id = null;
        }
    },
);

const openResolveModal = (dispute: any) => {
    selectedDispute.value = dispute;
    resolutionForm.value = {
        responsible_type:
            dispute.responsible_type === 'unassigned'
                ? 'unknown'
                : dispute.responsible_type || 'transporter',
        responsible_user_id: dispute.responsible_user_id || null,
        resolution_notes: '',
    };
    isResolveModalOpen.value = true;
};

const saveRules = async () => {
    isProcessing.value = true;

    try {
        const res = await axios.post(
            '/api/warehouse-governance/rules',
            rulesForm.value,
        );

        if (res.data.success) {
            toast.success(
                'Đã lưu Cấu hình Bộ Quy Tắc Siết Chặt Quản Lý Kho thành công!',
            );
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể lưu cấu hình quy tắc.',
        );
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
            toast.success(
                'Đã giải quyết biên bản bất đồng & quy trách nhiệm bồi thường!',
            );
            isResolveModalOpen.value = false;
            router.reload();
        }
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi xử lý biên bản.',
        );
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

const getResponsibleLabel = (type: string) => {
    switch (type) {
        case 'warehouse_staff':
            return 'Nhân viên xuất Kho Tổng';
        case 'transporter':
            return 'Đơn vị Vận chuyển / Tài xế';
        case 'branch_staff':
            return 'Nhân viên nhận Kho Chi nhánh';
        case 'unassigned':
            return 'Chưa phân công';
        default:
            return 'Chưa xác định';
    }
};
</script>

<template>
    <Head title="Quản Trị Siết Chặt Kho & Quy Trách Nhiệm" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-rose-950 via-slate-900 to-slate-950 p-6 text-white shadow-xl md:flex-row md:items-center md:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="rounded-xl border border-rose-400/30 bg-rose-500/20 p-3"
                >
                    <ShieldAlert class="h-8 w-8 text-rose-400" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Quản Trị Siết Chặt Kho & Quy Trách Nhiệm
                    </h1>
                    <p class="text-sm text-rose-200/80">
                        Bộ quy tắc siết chặt tài chính, xử lý bất đồng giao nhận
                        & bồi thường thất thoát
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="flex items-center gap-1.5 rounded-full border border-rose-400/30 bg-rose-500/20 px-3 py-1 text-xs font-semibold text-rose-300"
                >
                    <Lock class="h-3.5 w-3.5" /> Chế độ Trưởng Kho
                </span>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <Card class="border-rose-500/20 bg-rose-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-rose-300">
                            Biên Bản Chờ Xử Lý
                        </p>
                        <p class="mt-1 text-2xl font-bold text-rose-100">
                            {{ summary.open_disputes_count }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-rose-950/50 p-2.5 text-rose-300">
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-amber-500/20 bg-amber-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-amber-300">
                            Thiệt hại Bất đồng Giao nhận
                        </p>
                        <p class="mt-1 text-xl font-bold text-amber-100">
                            {{ formatCurrency(summary.total_discrepancy_loss) }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-amber-950/50 p-2.5 text-amber-300"
                    >
                        <DollarSign class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Thiệt hại Hủy hàng / Rác
                        </p>
                        <p class="mt-1 text-xl font-bold text-foreground">
                            {{ formatCurrency(summary.total_waste_loss) }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-muted p-2.5 text-muted-foreground"
                    >
                        <FileText class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card class="border-indigo-500/20 bg-indigo-950/10 shadow-sm">
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs font-medium text-indigo-300">
                            Tổng Thất Thoát Tài Chính
                        </p>
                        <p class="mt-1 text-xl font-bold text-indigo-100">
                            {{ formatCurrency(summary.total_combined_loss) }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-indigo-950/50 p-2.5 text-indigo-300"
                    >
                        <Gavel class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <WarehouseAiRecommendations context="receiving" :max="3" />

        <!-- Navigation Tabs -->
        <Card class="border-border">
            <CardContent class="flex items-center justify-between p-3">
                <div class="flex gap-2">
                    <button
                        @click="activeTab = 'disputes'"
                        :class="[
                            'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition',
                            activeTab === 'disputes'
                                ? 'bg-indigo-600 text-white shadow'
                                : 'text-muted-foreground hover:bg-muted',
                        ]"
                    >
                        <AlertTriangle class="h-4 w-4 text-rose-400" /> Biên Bản
                        Bất Đồng Giao Nhận ({{ summary.open_disputes_count }})
                    </button>
                    <button
                        @click="activeTab = 'rules'"
                        :class="[
                            'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition',
                            activeTab === 'rules'
                                ? 'bg-indigo-600 text-white shadow'
                                : 'text-muted-foreground hover:bg-muted',
                        ]"
                    >
                        <ShieldCheck class="h-4 w-4 text-indigo-400" /> Bộ Quy
                        Tắc & Hạn Mức Kiểm Soát
                    </button>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 1: Disputes List -->
        <Card v-if="activeTab === 'disputes'" class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <CardTitle class="text-base font-bold text-foreground"
                    >Danh Sách Biên Bản Bất Đồng & Sai Lệch Giao Nhận</CardTitle
                >
                <CardDescription class="text-xs"
                    >Truy vết trách nhiệm giữa Kho Tổng, Đơn vị vận chuyển và
                    Kho Chi nhánh khi phát sinh hàng thiếu/hỏng</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 font-semibold text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3 pl-4">Mã Biên Bản</th>
                                <th class="p-3">Nguyên Liệu</th>
                                <th class="p-3 text-right">Xuất Kho</th>
                                <th class="p-3 text-right">Thực Nhận</th>
                                <th class="p-3 text-right">Chênh Lệch</th>
                                <th class="p-3 text-right">Thiệt hại (VND)</th>
                                <th class="p-3">Đối Tượng Bồi Thường</th>
                                <th class="p-3">Trạng Thái</th>
                                <th class="p-3 pr-4 text-right">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="recentDisputes.length === 0">
                                <td
                                    colspan="9"
                                    class="p-8 text-center text-muted-foreground"
                                >
                                    Không có biên bản bất đồng giao nhận nào
                                    phát sinh.
                                </td>
                            </tr>
                            <tr
                                v-for="disp in recentDisputes"
                                :key="disp.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td
                                    class="p-3 pl-4 font-mono font-bold text-rose-600"
                                >
                                    {{ disp.dispute_code }}
                                </td>
                                <td class="p-3 font-semibold text-foreground">
                                    {{ disp.ingredient?.name }}
                                </td>
                                <td
                                    class="p-3 text-right font-medium text-muted-foreground"
                                >
                                    {{ disp.dispatched_quantity }}
                                </td>
                                <td
                                    class="p-3 text-right font-medium text-muted-foreground"
                                >
                                    {{ disp.received_quantity }}
                                </td>
                                <td
                                    class="p-3 text-right font-bold text-rose-600"
                                >
                                    -{{ disp.discrepancy_quantity }}
                                </td>
                                <td
                                    class="p-3 text-right font-bold text-amber-700"
                                >
                                    {{
                                        formatCurrency(
                                            disp.financial_loss_amount,
                                        )
                                    }}
                                </td>
                                <td class="p-3 text-muted-foreground">
                                    {{
                                        disp.responsible_user
                                            ? disp.responsible_user.name
                                            : getResponsibleLabel(
                                                  disp.responsible_type,
                                              )
                                    }}
                                </td>
                                <td class="p-3">
                                    <span
                                        v-if="disp.status === 'open' || disp.status === 'investigating'"
                                        class="rounded-full border border-rose-300 bg-rose-100 px-2.5 py-1 text-[11px] font-semibold text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300"
                                    >
                                        Chờ Xử Lý
                                    </span>
                                    <span
                                        v-else-if="disp.status === 'appealed'"
                                        class="rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                                    >
                                        {{ getStatusLabel(disp.status) }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300"
                                    >
                                        {{ getStatusLabel(disp.status) }}
                                    </span>
                                </td>
                                <td class="p-3 pr-4 text-right">
                                    <Button
                                        v-if="canResolveDispute(disp.status)"
                                        @click="openResolveModal(disp)"
                                        size="sm"
                                        variant="outline"
                                        class="h-8 gap-1.5 text-xs"
                                    >
                                        <Gavel class="h-3.5 w-3.5" /> Quy Trách
                                        Nhiệm
                                    </Button>
                                    <span v-else class="text-[11px] text-muted-foreground">Đã khóa xử lý</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Tab 2: Rules Governance Config -->
        <Card
            v-if="activeTab === 'rules'"
            class="mx-auto max-w-4xl border-border shadow-sm"
        >
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <CardTitle class="text-base font-bold text-foreground"
                    >Thiết Lập Bộ Quy Tắc & Hạn Mức Kiểm Soát Hàng
                    Hóa</CardTitle
                >
                <CardDescription class="text-xs"
                    >Cấu hình các rào cản tự động ngăn chặn thất thoát tài chính
                    và gian lận trong kho</CardDescription
                >
            </CardHeader>
            <CardContent class="space-y-6 p-6 text-xs">
                <!-- Rule 1: Threshold Amount -->
                <div
                    class="space-y-2 rounded-xl border border-border bg-muted/20 p-4"
                >
                    <label class="block text-sm font-bold text-foreground"
                        >1. Hạn Mức Tiền Chênh Lệch Tối Đa Tự Chốt (VNĐ)</label
                    >
                    <p class="text-muted-foreground">
                        Phiếu kiểm kê hoặc hủy hàng có giá trị chênh lệch vượt
                        ngưỡng này sẽ bị KHÓA TỰ ĐỘNG và bắt buộc Trưởng Kho
                        Tổng duyệt.
                    </p>
                    <Input
                        v-model.number="
                            rulesForm.max_auto_approve_variance_amount
                        "
                        type="number"
                        step="50000"
                        class="max-w-xs text-xs font-bold text-indigo-300"
                    />
                </div>

                <!-- Rule 2: Threshold Percent -->
                <div
                    class="space-y-2 rounded-xl border border-border bg-muted/20 p-4"
                >
                    <label class="block text-sm font-bold text-foreground"
                        >2. Tỷ Lệ % Sai Lệch Tối Đa Cho Phép (%)</label
                    >
                    <p class="text-muted-foreground">
                        Tỷ lệ sai lệch giữa Tồn thực tế vs Tồn lý thuyết vượt
                        quá phần trăm này sẽ được cảnh báo rủi ro cao.
                    </p>
                    <Input
                        v-model.number="
                            rulesForm.max_auto_approve_variance_percent
                        "
                        type="number"
                        step="0.5"
                        class="max-w-xs text-xs font-bold text-indigo-300"
                    />
                </div>

                <!-- Rule 3: Seal Code Required -->
                <div
                    class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4"
                >
                    <div>
                        <label class="block text-sm font-bold text-foreground"
                            >3. Bắt Buộc Mã Niêm Phong (Seal Code) Khi Xuất
                            Kho</label
                        >
                        <p class="text-muted-foreground">
                            Yêu cầu Nhân viên Kho Tổng phải nhập Mã Seal niêm
                            phong thùng hàng trước khi giao xe.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.require_seal_code_on_dispatch"
                        class="h-5 w-5 rounded accent-indigo-500"
                    />
                </div>

                <!-- Rule 4: Auto Dispute -->
                <div
                    class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4"
                >
                    <div>
                        <label class="block text-sm font-bold text-foreground"
                            >4. Tự Động Khởi Tạo Biên Bản Bất Đồng Khi Chi Nhánh
                            Nhận Thiếu</label
                        >
                        <p class="text-muted-foreground">
                            Hệ thống sẽ lập tức tạo Biên bản khiếu nại để điều
                            tra khi số lượng thực nhận nhỏ hơn số lượng xuất.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.auto_dispute_on_discrepancy"
                        class="h-5 w-5 rounded accent-indigo-500"
                    />
                </div>

                <!-- Rule 5: Penalty Deduction -->
                <div
                    class="flex items-center justify-between gap-4 rounded-xl border border-border bg-muted/20 p-4"
                >
                    <div>
                        <label class="block text-sm font-bold text-foreground"
                            >5. Cho Phép Phân Bổ Thiệt Hại Bồi Thường Vào Bảng
                            Lương</label
                        >
                        <p class="text-muted-foreground">
                            Cho phép Trưởng Kho chỉ định Nhân viên chịu trách
                            nhiệm và đẩy khoản tiền phạt bồi thường sang Phân hệ
                            Lương.
                        </p>
                    </div>
                    <input
                        type="checkbox"
                        v-model="rulesForm.penalty_deduction_enabled"
                        class="h-5 w-5 rounded accent-indigo-500"
                    />
                </div>

                <div class="flex justify-end pt-4">
                    <Button
                        @click="saveRules"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-2 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        <Save class="h-4 w-4" /> Lưu Bộ Quy Tắc Kiểm Soát
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Resolve Dispute Modal -->
        <Teleport to="body">
        <div
            v-if="isResolveModalOpen && selectedDispute"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-slate-950 p-5 text-white"
                >
                    <div class="flex items-center gap-2">
                        <Gavel class="h-6 w-6 text-rose-400" />
                        <div>
                            <h3 class="text-base font-bold">
                                Xử Lý Biên Bản Bất Đồng Giao Nhận
                            </h3>
                            <p class="text-xs text-slate-300">
                                Mã biên bản:
                                <span
                                    class="font-mono font-bold text-rose-300"
                                    >{{ selectedDispute.dispute_code }}</span
                                >
                            </p>
                        </div>
                    </div>
                    <button
                        @click="isResolveModalOpen = false"
                        class="rounded-lg p-1 text-slate-400 hover:text-white"
                    >
                        <X class="h-6 w-6" />
                    </button>
                </div>

                <div class="space-y-5 p-6 text-xs">
                    <div
                        class="space-y-1 rounded-xl border border-rose-900/50 bg-rose-950/20 p-3 text-rose-200"
                    >
                        <div>
                            <strong>Nguyên liệu:</strong>
                            {{ selectedDispute.ingredient?.name }}
                        </div>
                        <div>
                            <strong>Số lượng chênh lệch:</strong> Thiếu
                            {{ selectedDispute.discrepancy_quantity }} (Xuất
                            {{ selectedDispute.dispatched_quantity }} - Nhận
                            {{ selectedDispute.received_quantity }})
                        </div>
                        <div>
                            <strong>Giá trị thiệt hại:</strong>
                            <strong
                                class="ml-1 text-sm font-bold text-rose-100"
                                >{{
                                    formatCurrency(
                                        selectedDispute.financial_loss_amount,
                                    )
                                }}</strong
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block font-medium text-foreground"
                            >Xác định Bên Chịu Trách Nhiệm</label
                        >
                        <select
                            v-model="resolutionForm.responsible_type"
                            class="w-full rounded-lg border border-input bg-background p-2 font-medium text-foreground focus:outline-none"
                        >
                            <option value="transporter">
                                Đơn vị Vận chuyển / Tài xế giao hàng
                            </option>
                            <option value="warehouse_staff">
                                Nhân viên xuất Kho Tổng (Sai sót khi đóng gói)
                            </option>
                            <option value="branch_staff">
                                Nhân viên nhận Kho Chi nhánh (Khai báo sai)
                            </option>
                            <option value="unknown">
                                Hao hụt rủi ro bất khả kháng
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block font-medium text-foreground"
                            >Chọn Cá Nhân Bồi Thường Thiệt Hại (Nếu có)</label
                        >
                        <select
                            v-model="resolutionForm.responsible_user_id"
                            class="w-full rounded-lg border border-input bg-background p-2 font-medium text-foreground focus:outline-none"
                        >
                            <option :value="null">
                                -- Không gán cá nhân cụ thể --
                            </option>
                            <option
                                v-for="emp in eligibleEmployees"
                                :key="emp.id"
                                :value="emp.id"
                            >
                                {{ emp.name }} ({{ emp.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block font-medium text-foreground"
                            >Kết luận Điều tra & Biện Pháp Xử Lý (*)</label
                        >
                        <textarea
                            v-model="resolutionForm.resolution_notes"
                            rows="3"
                            placeholder="Nhập nội dung kết luận xử lý, lý do bồi thường hoặc biên bản làm việc..."
                            class="w-full rounded-lg border border-input bg-background p-2 text-xs text-foreground focus:outline-none"
                        ></textarea>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isResolveModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitResolution"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-1.5 bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700"
                    >
                        <UserCheck class="h-4 w-4" /> Xác Nhận Quyết Định Xử Lý
                    </Button>
                </div>
            </div>
        </div>
        </Teleport>
    </div>
</template>
