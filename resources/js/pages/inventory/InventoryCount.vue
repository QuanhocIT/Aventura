<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    Clock,
    Eye,
    EyeOff,
    FileSpreadsheet,
    Layers,
    PackageCheck,
    Plus,
    RefreshCw,
    RotateCcw,
    Search,
    ShieldAlert,
    UploadCloud,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import WarehouseAiRecommendations from '@/components/WarehouseAiRecommendations.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Branch {
    id: number;
    name: string;
    code?: string;
    is_central?: boolean;
}

interface CountItem {
    id: number;
    count_session_id: number;
    ingredient_id: number;
    expected_quantity: number;
    counted_quantity_1: number | null;
    counted_quantity_2: number | null;
    final_quantity: number | null;
    variance_quantity: number;
    variance_percent: number;
    variance_value: number;
    notes?: string;
    reconciliation_status?: 'not_required' | 'pending' | 'resolved' | string;
    reconciliation_notes?: string | null;
    reconciled_by?: { id: number; name: string } | null;
    ingredient?: {
        id: number;
        name: string;
        sku?: string;
        average_cost?: number;
        unit?: {
            id: number;
            name: string;
            symbol: string;
        };
    };
}

interface CountSession {
    id: number;
    restaurant_id: number;
    branch_id: number;
    type: 'periodic' | 'spot_check' | 'abc_cycle';
    status:
        | 'draft'
        | 'in_progress'
        | 'pending_approval'
        | 'approved'
        | 'rejected'
        | 'cancelled';
    blind_count: boolean;
    requires_owner_approval: boolean;
    total_variance_value: number;
    counted_by: number;
    second_counted_by?: number;
    approved_by?: number;
    started_at?: string;
    completed_at?: string;
    approved_at?: string;
    variance_photo_path?: string;
    notes?: string;
    rejection_reason?: string | null;
    rejected_by?: { id: number; name: string } | null;
    rejectedBy?: { id: number; name: string } | null;
    rejected_at?: string | null;
    cancel_reason?: string | null;
    cancelled_by?: { id: number; name: string } | null;
    cancelledBy?: { id: number; name: string } | null;
    cancelled_at?: string | null;
    created_at?: string;
    branch?: Branch;
    counted_by_user?: { id: number; name: string };
    countedBy?: { id: number; name: string };
    secondCountedBy?: { id: number; name: string };
    approver?: { id: number; name: string };
    items?: CountItem[];
}

interface CounterCandidate {
    id: number;
    name: string;
    email?: string;
}

const props = defineProps<{
    branches: Branch[];
    activeBranchId: number | null;
    countSessions: CountSession[];
    counterCandidates: CounterCandidate[];
    authUserId: number;
    canStartCount: boolean;
    canApprove: boolean;
    isCentralWarehouseScope: boolean;
    scopeLabel?: string | null;
    scopeMessage?: string | null;
}>();

// State bộ lọc & tìm kiếm
const selectedBranch = ref<number | string>(
    props.activeBranchId ? String(props.activeBranchId) : 'all',
);
const statusFilter = ref<string>('all');
const typeFilter = ref<string>('all');
const searchQuery = ref('');

// Modals
const showCreateModal = ref(false);
const showCountModal = ref(false);
const showApprovalModal = ref(false);
const showDecisionModal = ref(false);
const showReconcileModal = ref(false);
const isSubmitting = ref(false);

// Form Tạo phiên mới
const createForm = ref({
    branch_id: props.activeBranchId
        ? String(props.activeBranchId)
        : String(props.branches[0]?.id || ''),
    type: 'periodic' as 'periodic' | 'spot_check' | 'abc_cycle',
    blind_count: false,
});

// Form Thao tác phiên hiện tại
const activeSession = ref<CountSession | null>(null);
const countRows = ref<
    Array<{
        id: number;
        counted_quantity: number | undefined;
        counted_quantity_2?: number | null;
        notes: string;
    }>
>([]);
const isSecondCounter = ref(false);

// Form Gửi duyệt
const approvalSubmitForm = ref({
    variance_photo_path: '',
    notes: '',
});

const decisionType = ref<'reject' | 'cancel'>('reject');
const decisionSession = ref<CountSession | null>(null);
const decisionReason = ref('');
const reconciliationItem = ref<CountItem | null>(null);
const reconciliationForm = ref({
    final_quantity: 0,
    notes: '',
});

const countRoleLabel = computed(() =>
    isSecondCounter.value ? 'Lần đếm 2' : 'Lần đếm 1',
);

// Format tiền tệ
const formatCurrency = (val: number | string | null | undefined) => {
    const num = Number(val || 0);

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(num);
};

// Format ngày giờ
const formatDate = (dateStr: string | null | undefined) => {
    if (!dateStr) {
        return '—';
    }

    const d = new Date(dateStr);

    return d.toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Danh sách phiên kiểm kê đã lọc
const filteredSessions = computed(() => {
    return props.countSessions.filter((session) => {
        if (
            selectedBranch.value !== 'all' &&
            session.branch_id !== Number(selectedBranch.value)
        ) {
            return false;
        }

        if (
            statusFilter.value !== 'all' &&
            session.status !== statusFilter.value
        ) {
            return false;
        }

        if (typeFilter.value !== 'all' && session.type !== typeFilter.value) {
            return false;
        }

        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase();
            const idMatch =
                `#${session.id}`.includes(q) || String(session.id).includes(q);
            const branchMatch = session.branch?.name?.toLowerCase().includes(q);
            const noteMatch = session.notes?.toLowerCase().includes(q);
            const itemsMatch = session.items?.some(
                (i) =>
                    i.ingredient?.name?.toLowerCase().includes(q) ||
                    i.ingredient?.sku?.toLowerCase().includes(q),
            );

            if (!idMatch && !branchMatch && !noteMatch && !itemsMatch) {
                return false;
            }
        }

        return true;
    });
});

// Thống kê nhanh
const stats = computed(() => {
    const total = props.countSessions.length;
    const inProgress = props.countSessions.filter(
        (s) => s.status === 'in_progress' || s.status === 'draft',
    ).length;
    const pendingApproval = props.countSessions.filter(
        (s) => s.status === 'pending_approval',
    ).length;
    const totalVariance = props.countSessions
        .filter((s) => s.status === 'approved')
        .reduce(
            (sum, s) => sum + Math.abs(Number(s.total_variance_value || 0)),
            0,
        );

    return { total, inProgress, pendingApproval, totalVariance };
});

// Loại kiểm kê label & color
const getTypeLabel = (type: string) => {
    switch (type) {
        case 'periodic':
            return {
                label: 'Định kỳ',
                class: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            };
        case 'spot_check':
            return {
                label: 'Đột xuất',
                class: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
            };
        case 'abc_cycle':
            return {
                label: 'Chu kỳ ABC',
                class: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
            };
        default:
            return {
                label: type,
                class: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
            };
    }
};

// Trạng thái label & color
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'draft':
            return {
                label: 'Bản nháp',
                class: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
            };
        case 'in_progress':
            return {
                label: 'Đang kiểm đếm',
                class: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            };
        case 'pending_approval':
            return {
                label: 'Chờ phê duyệt',
                class: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
            };
        case 'approved':
            return {
                label: 'Đã phê duyệt',
                class: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            };
        case 'rejected':
            return {
                label: 'Đã từ chối',
                class: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            };
        case 'cancelled':
            return {
                label: 'Đã hủy',
                class: 'bg-zinc-500/20 text-zinc-400 border-zinc-500/30',
            };
        default:
            return {
                label: status,
                class: 'bg-slate-500/20 text-slate-300 border-slate-500/30',
            };
    }
};

// 1. Tạo phiên mới
const handleCreateSession = async () => {
    const branchId = props.isCentralWarehouseScope
        ? props.activeBranchId
        : Number(createForm.value.branch_id);

    if (!branchId) {
        toast.error('Vui lòng chọn chi nhánh thực hiện kiểm kê.');

        return;
    }

    isSubmitting.value = true;

    try {
        const res = await axios.post('/api/inventory/count-sessions', {
            branch_id: Number(branchId),
            type: createForm.value.type,
            blind_count: createForm.value.blind_count,
        });

        toast.success(res.data.message || 'Khởi tạo phiên kiểm kê thành công!');
        showCreateModal.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể tạo phiên kiểm kê.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

// 2. Tạo kiểm kê nhanh theo preset
const handleQuickPreset = async (
    preset: 'low_stock' | 'high_value' | 'expiring_soon' | 'used_today',
) => {
    const branchId = props.isCentralWarehouseScope
        ? props.activeBranchId || 0
        : selectedBranch.value === 'all'
          ? props.branches[0]?.id || 1
          : Number(selectedBranch.value);

    isSubmitting.value = true;

    try {
        const res = await axios.post('/inventory/counts/quick-preset', {
            branch_id: branchId,
            preset: preset,
            blind_count: false,
        });

        toast.success(res.data.message || 'Đã tạo phiên kiểm kê nhanh!');
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể tạo kiểm kê nhanh.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

// 3. Mở modal nhập đếm
const openCountModal = (session: CountSession) => {
    activeSession.value = session;
    const isCounter1 = Number(session.counted_by) === Number(props.authUserId);
    const isAssignedCounter2 =
        Number(session.second_counted_by) === Number(props.authUserId);

    // Người không phải người đếm 1 sẽ đảm nhiệm lần đếm 2 khi phiên chưa
    // được gán người đếm 2; backend vẫn là nơi xác thực cuối cùng.
    isSecondCounter.value = !isCounter1 && isAssignedCounter2;

    const isEditable = ['draft', 'in_progress'].includes(session.status);
    countRows.value = (session.items || []).map((item) => ({
        id: item.id,
        counted_quantity: (() => {
            const currentCount = isSecondCounter.value
                ? item.counted_quantity_2
                : item.counted_quantity_1;

            if (isEditable) {
                return currentCount !== null && currentCount !== undefined
                    ? Number(currentCount)
                    : undefined;
            }

            return item.final_quantity !== null &&
                item.final_quantity !== undefined
                ? Number(item.final_quantity)
                : currentCount !== null && currentCount !== undefined
                  ? Number(currentCount)
                  : undefined;
        })(),
        counted_quantity_2:
            item.counted_quantity_2 !== null
                ? Number(item.counted_quantity_2)
                : null,
        notes: item.notes || '',
    }));
    showCountModal.value = true;
};

const handleAssignSecondCounter = async (
    session: CountSession,
    event: Event,
) => {
    const userId = Number((event.target as HTMLSelectElement).value);

    if (!userId) {
        return;
    }

    try {
        await axios.post(
            `/api/inventory/count-sessions/${session.id}/second-counter`,
            { user_id: userId },
        );
        toast.success('Đã phân công người đếm 2.');
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể phân công người đếm 2.',
        );
    } finally {
        (event.target as HTMLSelectElement).value = '';
    }
};

// 4. Lưu kết quả kiểm đếm
const handleSaveCounts = async () => {
    if (!activeSession.value) {
        return;
    }

    const rowsToSubmit = countRows.value.filter(
        (row) =>
            row.counted_quantity !== null && row.counted_quantity !== undefined,
    );

    if (rowsToSubmit.length === 0) {
        toast.error(
            'Hãy nhập ít nhất một dòng. Ô để trống sẽ được giữ ở trạng thái chưa đếm.',
        );

        return;
    }

    isSubmitting.value = true;

    try {
        const payload = rowsToSubmit.map((r) => ({
            id: r.id,
            counted_quantity: Number(r.counted_quantity),
            notes: r.notes || null,
        }));

        const res = await axios.post(
            `/api/inventory/count-sessions/${activeSession.value.id}/counts`,
            {
                items: payload,
                is_second_counter: isSecondCounter.value,
            },
        );

        toast.success(res.data.message || 'Đã lưu số lượng kiểm đếm!');
        showCountModal.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Lỗi khi lưu kết quả kiểm đếm.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

const isUploadingProof = ref(false);

// 5. Mở modal gửi duyệt
const openApprovalSubmitModal = (session: CountSession) => {
    activeSession.value = session;
    approvalSubmitForm.value = {
        variance_photo_path: session.variance_photo_path || '',
        notes: '',
    };
    showApprovalModal.value = true;
};

// 5.1 Upload file chứng từ
const handleUploadProof = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file || !activeSession.value) {
        return;
    }

    isUploadingProof.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await axios.post(
            `/api/inventory/count-sessions/${activeSession.value.id}/upload-proof`,
            formData,
            {
                headers: { 'Content-Type': 'multipart/form-data' },
            },
        );
        approvalSubmitForm.value.variance_photo_path =
            res.data.url || res.data.path;
        toast.success('Đã tải lên ảnh chứng từ thành công!');
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể tải lên ảnh chứng từ.',
        );
    } finally {
        isUploadingProof.value = false;
    }
};

// 6. Gửi duyệt phiên kiểm kê
const handleSubmitForApproval = async () => {
    if (!activeSession.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const res = await axios.post(
            `/api/inventory/count-sessions/${activeSession.value.id}/submit-approval`,
            {
                variance_photo_path:
                    approvalSubmitForm.value.variance_photo_path || null,
                notes: approvalSubmitForm.value.notes || null,
            },
        );

        toast.success(
            res.data.message || 'Đã gửi phiên kiểm kê lên cấp duyệt!',
        );
        showApprovalModal.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể gửi duyệt phiên kiểm kê.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

// 7. Phê duyệt phiên kiểm kê & điều chỉnh tồn kho
const handleApproveSession = async (session: CountSession) => {
    if (
        !confirm(
            `Bạn có chắc chắn muốn phê duyệt phiên kiểm kê #${session.id}? Hệ thống sẽ tự động cập nhật số tồn thực tế vào sổ cái tồn kho.`,
        )
    ) {
        return;
    }

    isSubmitting.value = true;

    try {
        const res = await axios.post(
            `/api/inventory/count-sessions/${session.id}/approve`,
        );
        toast.success(
            res.data.message ||
                'Đã phê duyệt và điều chỉnh tồn kho thành công!',
        );
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể phê duyệt kiểm kê.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

const openDecisionModal = (
    session: CountSession,
    type: 'reject' | 'cancel',
) => {
    decisionSession.value = session;
    decisionType.value = type;
    decisionReason.value = '';
    showDecisionModal.value = true;
};

const handleDecision = async () => {
    if (!decisionSession.value || !decisionReason.value.trim()) {
        toast.error('Vui lòng nhập lý do để lưu dấu vết kiểm kê.');

        return;
    }

    isSubmitting.value = true;

    try {
        const endpoint = decisionType.value === 'reject' ? 'reject' : 'cancel';
        await axios.post(
            `/api/inventory/count-sessions/${decisionSession.value.id}/${endpoint}`,
            {
                reason: decisionReason.value.trim(),
            },
        );
        toast.success(
            decisionType.value === 'reject'
                ? 'Đã từ chối và trả phiên về xử lý.'
                : 'Đã hủy phiên kiểm kê.',
        );
        showDecisionModal.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message ||
                'Không thể cập nhật trạng thái phiên kiểm kê.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

const handleReopenSession = async (session: CountSession) => {
    if (
        !confirm(`Mở lại phiên kiểm kê #${session.id} để tiếp tục điều chỉnh?`)
    ) {
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(`/api/inventory/count-sessions/${session.id}/reopen`);
        toast.success('Đã mở lại phiên kiểm kê.');
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể mở lại phiên kiểm kê.',
        );
    } finally {
        isSubmitting.value = false;
    }
};

const openReconcileModal = (item: CountItem | undefined) => {
    if (!item) {
        return;
    }

    reconciliationItem.value = item;
    reconciliationForm.value = {
        final_quantity: Number(
            item.counted_quantity_2 ?? item.counted_quantity_1 ?? 0,
        ),
        notes: item.reconciliation_notes || '',
    };
    showReconcileModal.value = true;
};

const handleReconcile = async () => {
    if (
        !activeSession.value ||
        !reconciliationItem.value ||
        !reconciliationForm.value.notes.trim()
    ) {
        toast.error('Cần nhập kết quả chốt và lý do đối soát.');

        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(
            `/api/inventory/count-sessions/${activeSession.value.id}/items/${reconciliationItem.value.id}/reconcile`,
            {
                final_quantity: Number(reconciliationForm.value.final_quantity),
                notes: reconciliationForm.value.notes.trim(),
            },
        );
        toast.success('Đã chốt dòng kiểm kê sau đối soát.');
        showReconcileModal.value = false;
        showCountModal.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể chốt kết quả đối soát.',
        );
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head title="Kiểm kê & Điều chỉnh Tồn kho" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
        <!-- Hero Header -->
        <div
            class="relative flex flex-col justify-between gap-4 overflow-hidden rounded-3xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/70 via-slate-50 to-emerald-100/40 p-6 text-slate-900 shadow-sm sm:p-8 dark:border-white/10 dark:bg-gradient-to-r dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950 dark:text-white dark:shadow-2xl"
        >
            <div
                class="pointer-events-none absolute -right-10 -bottom-10 opacity-10"
            >
                <FileSpreadsheet class="h-72 w-72 text-emerald-600 dark:text-emerald-400" />
            </div>

            <div class="relative z-10">
                <div class="mb-3 flex items-center gap-2">
                    <Link
                        href="/inventory/central-warehouse"
                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-700 transition-colors hover:bg-emerald-500/20 dark:border-white/10 dark:bg-white/5 dark:text-emerald-300 dark:hover:bg-white/10 dark:hover:text-white"
                    >
                        <ArrowLeft class="h-3.5 w-3.5" /> Tổng quan Kho
                    </Link>
                    <span class="text-xs text-muted-foreground/60">•</span>
                    <span class="font-mono text-xs text-emerald-700 dark:text-emerald-200/80"
                        >WMS Inventory Control</span
                    >
                </div>

                <div class="flex items-center gap-3.5">
                    <div
                        class="rounded-2xl border border-emerald-200 bg-emerald-500/10 p-3 text-emerald-600 shadow-xs dark:border-emerald-500/30 dark:bg-emerald-500/20 dark:text-emerald-300 dark:shadow-inner"
                    >
                        <FileSpreadsheet class="h-7 w-7" />
                    </div>
                    <div>
                        <h1
                            class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white"
                        >
                            Kiểm kê & Điều chỉnh Tồn kho
                        </h1>
                        <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                            Khởi tạo phiên kiểm đếm thực tế (định kỳ, đột xuất,
                            blind count), tính toán sai lệch và đối soát điều
                            chỉnh vào sổ cái bất biến.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="relative z-10 flex flex-wrap items-center gap-3 pt-2">
                <!-- Quick Preset Dropdown / Group -->
                <div
                    v-if="canStartCount"
                    class="flex items-center gap-1.5 rounded-xl border border-slate-700/80 bg-slate-800/80 p-1 text-xs"
                >
                    <span
                        class="flex items-center gap-1 px-2 font-medium text-slate-400"
                    >
                        <Zap class="h-3.5 w-3.5 text-amber-400" /> Kiểm nhanh:
                    </span>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="handleQuickPreset('low_stock')"
                        class="h-7 px-2.5 text-xs text-slate-200 hover:bg-slate-700 hover:text-white"
                    >
                        Tồn thấp
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="handleQuickPreset('expiring_soon')"
                        class="h-7 px-2.5 text-xs text-amber-300 hover:bg-amber-500/20 hover:text-amber-200"
                    >
                        Cận HSD
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="handleQuickPreset('high_value')"
                        class="h-7 px-2.5 text-xs text-emerald-300 hover:bg-emerald-500/20 hover:text-emerald-200"
                    >
                        Giá trị cao
                    </Button>
                </div>

                <Button
                    v-if="canStartCount"
                    @click="showCreateModal = true"
                    class="h-9 gap-2 bg-emerald-500 font-semibold text-slate-950 shadow-lg shadow-emerald-500/20 hover:bg-emerald-400"
                >
                    <Plus class="h-4 w-4" /> Bắt đầu phiên kiểm kê
                </Button>
            </div>
        </div>

        <!-- 4 Summary KPI Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                class="border-border bg-card/60 shadow-sm backdrop-blur-sm transition-colors hover:border-emerald-500/40"
            >
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Tổng số phiên
                        </p>
                        <h3 class="mt-1 text-2xl font-bold">
                            {{ stats.total }}
                        </h3>
                    </div>
                    <div
                        class="rounded-2xl border border-blue-500/20 bg-blue-500/10 p-3 text-blue-400"
                    >
                        <Layers class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-border bg-card/60 shadow-sm backdrop-blur-sm transition-colors hover:border-amber-500/40"
            >
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Đang kiểm đếm
                        </p>
                        <h3 class="mt-1 text-2xl font-bold text-amber-400">
                            {{ stats.inProgress }}
                        </h3>
                    </div>
                    <div
                        class="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-3 text-amber-400"
                    >
                        <Clock class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-border bg-card/60 shadow-sm backdrop-blur-sm transition-colors hover:border-blue-500/40"
            >
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Chờ phê duyệt
                        </p>
                        <h3 class="mt-1 text-2xl font-bold text-blue-400">
                            {{ stats.pendingApproval }}
                        </h3>
                    </div>
                    <div
                        class="rounded-2xl border border-blue-500/20 bg-blue-500/10 p-3 text-blue-400"
                    >
                        <ShieldAlert class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>

            <Card
                class="border-border bg-card/60 shadow-sm backdrop-blur-sm transition-colors hover:border-emerald-500/40"
            >
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs font-medium text-muted-foreground">
                            Tổng chênh lệch đã xử lý
                        </p>
                        <h3 class="mt-1 text-xl font-bold text-emerald-400">
                            {{ formatCurrency(stats.totalVariance) }}
                        </h3>
                    </div>
                    <div
                        class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-emerald-400"
                    >
                        <PackageCheck class="h-5 w-5" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <WarehouseAiRecommendations context="stock" :max="3" />

        <!-- Filter & Search Toolbar -->
        <Card class="border-border shadow-sm">
            <CardContent class="p-4">
                <div
                    class="flex flex-col items-center justify-between gap-3 lg:flex-row"
                >
                    <!-- Search & Selects -->
                    <div
                        class="flex w-full flex-wrap items-center gap-3 lg:w-auto"
                    >
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Tìm mã phiên, nguyên liệu, ghi chú..."
                                class="h-9 pl-9 text-xs"
                            />
                        </div>

                        <!-- Branch Filter -->
                        <div
                            v-if="!isCentralWarehouseScope"
                            class="w-full sm:w-48"
                        >
                            <Select v-model="selectedBranch">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue
                                        placeholder="Tất cả chi nhánh"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Tất cả chi nhánh</SelectItem
                                    >
                                    <SelectItem
                                        v-for="b in branches"
                                        :key="b.id"
                                        :value="String(b.id)"
                                    >
                                        {{ b.name }}
                                        {{ b.is_central ? '(Kho Tổng)' : '' }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-else
                            class="flex h-9 items-center gap-2 rounded-md border border-emerald-500/20 bg-emerald-500/5 px-3 text-xs text-emerald-300"
                        >
                            <ShieldAlert class="h-3.5 w-3.5" />
                            {{ scopeLabel }} · Phạm vi cố định
                        </div>

                        <!-- Status Filter -->
                        <div class="w-full sm:w-44">
                            <Select v-model="statusFilter">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Trạng thái" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Tất cả trạng thái</SelectItem
                                    >
                                    <SelectItem value="draft"
                                        >Bản nháp</SelectItem
                                    >
                                    <SelectItem value="in_progress"
                                        >Đang kiểm đếm</SelectItem
                                    >
                                    <SelectItem value="pending_approval"
                                        >Chờ duyệt</SelectItem
                                    >
                                    <SelectItem value="approved"
                                        >Đã phê duyệt</SelectItem
                                    >
                                    <SelectItem value="rejected"
                                        >Đã từ chối</SelectItem
                                    >
                                    <SelectItem value="cancelled"
                                        >Đã hủy</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Type Filter -->
                        <div class="w-full sm:w-40">
                            <Select v-model="typeFilter">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Loại kiểm kê" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all"
                                        >Tất cả loại</SelectItem
                                    >
                                    <SelectItem value="periodic"
                                        >Định kỳ</SelectItem
                                    >
                                    <SelectItem value="spot_check"
                                        >Đột xuất</SelectItem
                                    >
                                    <SelectItem value="abc_cycle"
                                        >Chu kỳ ABC</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <!-- Reset button -->
                    <Button
                        variant="outline"
                        size="sm"
                        @click="
                            () => {
                                statusFilter = 'all';
                                typeFilter = 'all';
                                searchQuery = '';
                                selectedBranch = 'all';
                            }
                        "
                        class="h-9 shrink-0 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <RefreshCw class="mr-1 h-3.5 w-3.5" /> Đặt lại lọc
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Sessions Data Table -->
        <Card class="overflow-hidden border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base">
                            Danh sách Phiên kiểm kê
                            <Badge
                                variant="secondary"
                                class="text-xs font-normal"
                            >
                                {{ filteredSessions.length }} phiên
                            </Badge>
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-xs">
                            Quản lý các đợt đếm tồn kho, kiểm duyệt chênh lệch
                            và cập nhật số dư thực tế.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/50 font-medium text-muted-foreground"
                        >
                            <tr>
                                <th class="p-3.5 pl-6">Mã phiên</th>
                                <th class="p-3.5">Chi nhánh</th>
                                <th class="p-3.5">Phân loại & Chế độ</th>
                                <th class="p-3.5 text-center">Số mặt hàng</th>
                                <th class="p-3.5">Người thực hiện</th>
                                <th class="p-3.5 text-right">
                                    Chênh lệch giá trị
                                </th>
                                <th class="p-3.5 text-center">Trạng thái</th>
                                <th class="p-3.5">Thời gian</th>
                                <th class="p-3.5 pr-6 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-if="filteredSessions.length === 0">
                                <td
                                    colspan="9"
                                    class="p-12 text-center text-muted-foreground"
                                >
                                    <div
                                        class="mx-auto max-w-xs space-y-2 text-center"
                                    >
                                        <FileSpreadsheet
                                            class="mx-auto h-10 w-10 text-muted-foreground/40"
                                        />
                                        <p class="font-medium text-foreground">
                                            Không có phiên kiểm kê nào
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Chưa có dữ liệu kiểm kê phù hợp với
                                            điều kiện lọc hiện tại.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="session in filteredSessions"
                                :key="session.id"
                                class="group transition-colors hover:bg-muted/20"
                            >
                                <td
                                    class="p-3.5 pl-6 font-mono font-bold text-foreground"
                                >
                                    #{{ session.id }}
                                </td>
                                <td class="p-3.5 font-medium text-foreground">
                                    {{
                                        session.branch?.name ||
                                        'Chi nhánh #' + session.branch_id
                                    }}
                                </td>
                                <td class="p-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                getTypeLabel(session.type).class
                                            "
                                            class="text-[10px]"
                                        >
                                            {{
                                                getTypeLabel(session.type).label
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="session.blind_count"
                                            variant="outline"
                                            class="flex items-center gap-1 border-slate-500/20 bg-slate-500/10 text-[10px] text-slate-300"
                                        >
                                            <EyeOff class="h-3 w-3" /> Đếm mù
                                        </Badge>
                                    </div>
                                </td>
                                <td class="p-3.5 text-center font-semibold">
                                    {{ session.items?.length || 0 }}
                                </td>
                                <td class="p-3.5 text-muted-foreground">
                                    <div>
                                        Đếm 1:
                                        <strong class="text-foreground">{{
                                            session.countedBy?.name ||
                                            session.counted_by_user?.name ||
                                            '—'
                                        }}</strong>
                                    </div>
                                    <div
                                        v-if="session.secondCountedBy"
                                        class="text-[11px]"
                                    >
                                        Đếm 2:
                                        {{ session.secondCountedBy.name }}
                                    </div>
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-bold"
                                >
                                    <span
                                        v-if="
                                            Number(
                                                session.total_variance_value,
                                            ) !== 0
                                        "
                                        :class="
                                            Number(
                                                session.total_variance_value,
                                            ) < 0
                                                ? 'text-rose-400'
                                                : 'text-emerald-400'
                                        "
                                    >
                                        {{
                                            formatCurrency(
                                                session.total_variance_value,
                                            )
                                        }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >Khớp 100%</span
                                    >
                                </td>
                                <td class="p-3.5 text-center">
                                    <Badge
                                        variant="outline"
                                        :class="
                                            getStatusBadge(session.status).class
                                        "
                                        class="text-[10px]"
                                    >
                                        {{
                                            getStatusBadge(session.status).label
                                        }}
                                    </Badge>
                                </td>
                                <td
                                    class="p-3.5 text-[11px] text-muted-foreground"
                                >
                                    <div>
                                        Tạo:
                                        {{
                                            formatDate(
                                                session.started_at ||
                                                    (session as any).created_at,
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-if="session.approved_at"
                                        class="font-medium text-emerald-400/90"
                                    >
                                        Duyệt:
                                        {{ formatDate(session.approved_at) }}
                                    </div>
                                </td>
                                <td class="p-3.5 pr-6 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <select
                                            v-if="
                                                canStartCount &&
                                                [
                                                    'draft',
                                                    'in_progress',
                                                ].includes(session.status) &&
                                                !session.second_counted_by
                                            "
                                            class="h-7 max-w-[150px] rounded-md border border-border bg-background px-1.5 text-[10px] text-muted-foreground"
                                            @change="
                                                handleAssignSecondCounter(
                                                    session,
                                                    $event,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Phân công đếm 2
                                            </option>
                                            <option
                                                v-for="candidate in counterCandidates.filter(
                                                    (candidate) =>
                                                        candidate.id !==
                                                        session.counted_by,
                                                )"
                                                :key="candidate.id"
                                                :value="candidate.id"
                                            >
                                                {{ candidate.name }}
                                            </option>
                                        </select>
                                        <!-- Nút Nhập đếm -->
                                        <Button
                                            v-if="
                                                canStartCount &&
                                                [
                                                    'draft',
                                                    'in_progress',
                                                ].includes(session.status)
                                            "
                                            size="sm"
                                            variant="outline"
                                            @click="openCountModal(session)"
                                            class="h-7 border-amber-500/20 bg-amber-500/10 px-2.5 text-xs text-amber-300 hover:bg-amber-500/20"
                                        >
                                            Nhập đếm
                                        </Button>

                                        <!-- Nút Gửi duyệt -->
                                        <Button
                                            v-if="
                                                canStartCount &&
                                                [
                                                    'draft',
                                                    'in_progress',
                                                ].includes(session.status)
                                            "
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                openApprovalSubmitModal(session)
                                            "
                                            class="h-7 border-blue-500/20 bg-blue-500/10 px-2.5 text-xs text-blue-300 hover:bg-blue-500/20"
                                        >
                                            Gửi duyệt
                                        </Button>

                                        <!-- Nút Phê duyệt -->
                                        <Button
                                            v-if="
                                                session.status ===
                                                    'pending_approval' &&
                                                canApprove
                                            "
                                            size="sm"
                                            @click="
                                                handleApproveSession(session)
                                            "
                                            class="h-7 bg-emerald-500 px-2.5 text-xs font-semibold text-slate-950 hover:bg-emerald-400"
                                        >
                                            <CheckCircle2
                                                class="mr-1 h-3.5 w-3.5"
                                            />
                                            Duyệt & Khóa tồn
                                        </Button>

                                        <!-- Nút Xem chi tiết -->
                                        <Button
                                            v-if="
                                                session.status ===
                                                    'pending_approval' &&
                                                canApprove
                                            "
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                openDecisionModal(
                                                    session,
                                                    'reject',
                                                )
                                            "
                                            class="h-7 border-rose-500/20 px-2 text-xs text-rose-300 hover:bg-rose-500/10"
                                        >
                                            <Ban class="mr-1 h-3.5 w-3.5" /> Từ
                                            chối
                                        </Button>

                                        <Button
                                            v-if="
                                                canStartCount &&
                                                [
                                                    'draft',
                                                    'in_progress',
                                                    'pending_approval',
                                                ].includes(session.status)
                                            "
                                            size="sm"
                                            variant="ghost"
                                            @click="
                                                openDecisionModal(
                                                    session,
                                                    'cancel',
                                                )
                                            "
                                            class="h-7 px-2 text-xs text-muted-foreground hover:text-rose-300"
                                        >
                                            Hủy
                                        </Button>

                                        <Button
                                            v-if="
                                                canStartCount &&
                                                session.status === 'rejected'
                                            "
                                            size="sm"
                                            variant="outline"
                                            @click="
                                                handleReopenSession(session)
                                            "
                                            class="h-7 border-amber-500/20 px-2 text-xs text-amber-300 hover:bg-amber-500/10"
                                        >
                                            <RotateCcw
                                                class="mr-1 h-3.5 w-3.5"
                                            />
                                            Mở lại
                                        </Button>

                                        <Button
                                            v-if="
                                                ![
                                                    'draft',
                                                    'in_progress',
                                                ].includes(session.status)
                                            "
                                            size="sm"
                                            variant="ghost"
                                            @click="openCountModal(session)"
                                            class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            <Eye class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- MODAL 1: Khởi tạo phiên kiểm kê mới -->
        <Dialog :open="showCreateModal" @update:open="showCreateModal = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Plus class="h-5 w-5 text-emerald-400" /> Khởi tạo Phiên
                        Kiểm Kê
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        <template v-if="isCentralWarehouseScope">
                            Tạo phiên kiểm đếm nguyên liệu và tồn kho riêng cho
                            <strong class="text-foreground">{{
                                scopeLabel
                            }}</strong
                            >. Phạm vi này không bao gồm các chi nhánh.
                        </template>
                        <template v-else>
                            Tạo phiên kiểm đếm thực tế cho kho chi nhánh được
                            chọn.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Chi nhánh kiểm kê</Label
                        >
                        <div
                            v-if="isCentralWarehouseScope"
                            class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-3 py-2 text-xs text-emerald-200"
                        >
                            <div class="font-semibold">{{ scopeLabel }}</div>
                            <div class="mt-0.5 text-[11px] text-emerald-300/70">
                                Chi nhánh được khóa theo phạm vi tài khoản Kho
                                Tổng.
                            </div>
                        </div>
                        <Select v-else v-model="createForm.branch_id">
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue placeholder="Chọn chi nhánh" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="b in branches"
                                    :key="b.id"
                                    :value="String(b.id)"
                                >
                                    {{ b.name }}
                                    {{ b.is_central ? '(Kho Tổng)' : '' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Hình thức kiểm kê</Label
                        >
                        <Select v-model="createForm.type">
                            <SelectTrigger class="h-9 text-xs">
                                <SelectValue placeholder="Chọn loại kiểm kê" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="periodic"
                                    >Kiểm kê định kỳ (Toàn bộ nguyên
                                    liệu)</SelectItem
                                >
                                <SelectItem value="spot_check"
                                    >Kiểm tra đột xuất (Spot check)</SelectItem
                                >
                                <SelectItem value="abc_cycle"
                                    >Chu kỳ phân loại ABC</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <Checkbox
                            id="blind_count"
                            :checked="createForm.blind_count"
                            @update:checked="createForm.blind_count = $event"
                        />
                        <label
                            for="blind_count"
                            class="cursor-pointer text-xs leading-none font-medium"
                        >
                            Chế độ Kiểm kê Mù (Blind Count)
                            <span
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                Ẩn số tồn sổ sách khi nhân viên đếm nhằm chống
                                gian lận/sao chép số liệu.
                            </span>
                        </label>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showCreateModal = false"
                        :disabled="isSubmitting"
                    >
                        Hủy
                    </Button>
                    <Button
                        size="sm"
                        @click="handleCreateSession"
                        :disabled="isSubmitting"
                        class="bg-emerald-500 font-semibold text-slate-950 hover:bg-emerald-400"
                    >
                        {{ isSubmitting ? 'Đang tạo...' : 'Khởi tạo phiên' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL 2: Nhập số lượng đếm thực tế -->
        <Dialog :open="showCountModal" @update:open="showCountModal = $event">
            <DialogContent class="flex max-h-[90vh] flex-col p-0 sm:max-w-4xl">
                <DialogHeader class="border-b border-border p-6 pb-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <DialogTitle class="flex items-center gap-2">
                                <FileSpreadsheet
                                    class="h-5 w-5 text-emerald-400"
                                />
                                Nhập Kết Quả Đếm — Phiên #{{
                                    activeSession?.id
                                }}
                            </DialogTitle>
                            <DialogDescription class="mt-1 text-xs">
                                Chi nhánh:
                                <strong class="text-foreground">{{
                                    activeSession?.branch?.name
                                }}</strong>
                                | Loại:
                                <span class="capitalize">{{
                                    activeSession?.type
                                }}</span>
                                <span
                                    v-if="activeSession?.blind_count"
                                    class="ml-2 font-semibold text-amber-400"
                                    >(Chế độ Kiểm Mù)</span
                                >
                                <span
                                    v-if="
                                        ['draft', 'in_progress'].includes(
                                            activeSession?.status || '',
                                        )
                                    "
                                    class="ml-2 font-semibold text-emerald-300"
                                >
                                    • {{ countRoleLabel }}
                                </span>
                            </DialogDescription>
                        </div>
                        <Badge
                            variant="outline"
                            :class="
                                getStatusBadge(activeSession?.status || '')
                                    .class
                            "
                        >
                            {{
                                getStatusBadge(activeSession?.status || '')
                                    .label
                            }}
                        </Badge>
                    </div>
                </DialogHeader>

                <div
                    v-if="
                        activeSession?.rejection_reason ||
                        activeSession?.cancel_reason
                    "
                    class="mx-6 rounded-lg border border-amber-500/20 bg-amber-500/5 p-3 text-xs"
                >
                    <p class="font-semibold text-amber-300">Lịch sử xử lý</p>
                    <p
                        v-if="activeSession?.rejection_reason"
                        class="mt-1 text-muted-foreground"
                    >
                        Từ chối:
                        <span class="text-foreground">{{
                            activeSession.rejection_reason
                        }}</span>
                        <span v-if="activeSession.rejectedBy">
                            · {{ activeSession.rejectedBy.name }}
                        </span>
                    </p>
                    <p
                        v-if="activeSession?.cancel_reason"
                        class="mt-1 text-muted-foreground"
                    >
                        Hủy:
                        <span class="text-foreground">{{
                            activeSession.cancel_reason
                        }}</span>
                        <span v-if="activeSession.cancelledBy">
                            · {{ activeSession.cancelledBy.name }}
                        </span>
                    </p>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-6">
                    <div
                        class="overflow-x-auto rounded-xl border border-border"
                    >
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-border bg-muted/50 font-medium text-muted-foreground"
                            >
                                <tr>
                                    <th class="p-3">Nguyên liệu</th>
                                    <th class="p-3">SKU</th>
                                    <th class="p-3 text-center">Đơn vị</th>
                                    <th
                                        v-if="!activeSession?.blind_count"
                                        class="p-3 text-right"
                                    >
                                        Tồn sổ sách
                                    </th>
                                    <th class="p-3 text-right">
                                        Số đếm thực tế
                                    </th>
                                    <th
                                        v-if="!activeSession?.blind_count"
                                        class="p-3 text-right"
                                    >
                                        Chênh lệch
                                    </th>
                                    <th class="p-3">Ghi chú dòng</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-if="countRows.length === 0">
                                    <td
                                        colspan="7"
                                        class="p-8 text-center text-muted-foreground"
                                    >
                                        Không có mặt hàng nào trong phiên này.
                                    </td>
                                </tr>
                                <tr
                                    v-for="(row, idx) in countRows"
                                    :key="row.id"
                                    class="transition-colors hover:bg-muted/10"
                                >
                                    <td
                                        class="p-3 font-semibold text-foreground"
                                    >
                                        {{
                                            activeSession?.items?.[idx]
                                                ?.ingredient?.name ||
                                            'Mặt hàng #' +
                                                activeSession?.items?.[idx]
                                                    ?.ingredient_id
                                        }}
                                    </td>
                                    <td
                                        class="p-3 font-mono text-muted-foreground"
                                    >
                                        {{
                                            activeSession?.items?.[idx]
                                                ?.ingredient?.sku || '—'
                                        }}
                                    </td>
                                    <td
                                        class="p-3 text-center text-muted-foreground"
                                    >
                                        {{
                                            activeSession?.items?.[idx]
                                                ?.ingredient?.unit?.symbol ||
                                            'đv'
                                        }}
                                    </td>
                                    <td
                                        v-if="!activeSession?.blind_count"
                                        class="p-3 text-right font-mono font-medium text-muted-foreground"
                                    >
                                        {{
                                            activeSession?.items?.[idx]
                                                ?.expected_quantity || 0
                                        }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <Input
                                            v-if="
                                                [
                                                    'draft',
                                                    'in_progress',
                                                ].includes(
                                                    activeSession?.status || '',
                                                )
                                            "
                                            v-model.number="
                                                row.counted_quantity
                                            "
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            placeholder="0.00"
                                            class="ml-auto h-8 max-w-[120px] text-right text-xs font-bold text-emerald-400"
                                        />
                                        <span
                                            v-else
                                            class="font-mono font-bold text-foreground"
                                        >
                                            {{ row.counted_quantity ?? '—' }}
                                        </span>
                                        <div
                                            v-if="
                                                !activeSession?.blind_count &&
                                                (activeSession?.items?.[idx]
                                                    ?.counted_quantity_1 !==
                                                    null ||
                                                    activeSession?.items?.[idx]
                                                        ?.counted_quantity_2 !==
                                                        null)
                                            "
                                            class="mt-1 text-[10px] font-normal text-muted-foreground"
                                        >
                                            L1:
                                            {{
                                                activeSession?.items?.[idx]
                                                    ?.counted_quantity_1 ?? '—'
                                            }}
                                            · L2:
                                            {{
                                                activeSession?.items?.[idx]
                                                    ?.counted_quantity_2 ?? '—'
                                            }}
                                        </div>
                                    </td>
                                    <td
                                        v-if="!activeSession?.blind_count"
                                        class="p-3 text-right font-mono font-bold"
                                    >
                                        <span
                                            v-if="row.counted_quantity != null"
                                            :class="
                                                (row.counted_quantity || 0) -
                                                    Number(
                                                        activeSession?.items?.[
                                                            idx
                                                        ]?.expected_quantity ||
                                                            0,
                                                    ) <
                                                0
                                                    ? 'text-rose-400'
                                                    : 'text-emerald-400'
                                            "
                                        >
                                            {{
                                                (row.counted_quantity || 0) -
                                                    Number(
                                                        activeSession?.items?.[
                                                            idx
                                                        ]?.expected_quantity ||
                                                            0,
                                                    ) >
                                                0
                                                    ? '+'
                                                    : ''
                                            }}
                                            {{
                                                (
                                                    (row.counted_quantity ||
                                                        0) -
                                                    Number(
                                                        activeSession?.items?.[
                                                            idx
                                                        ]?.expected_quantity ||
                                                            0,
                                                    )
                                                ).toFixed(2)
                                            }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td class="p-3">
                                        <Input
                                            v-if="
                                                [
                                                    'draft',
                                                    'in_progress',
                                                ].includes(
                                                    activeSession?.status || '',
                                                )
                                            "
                                            v-model="row.notes"
                                            placeholder="Lý do chênh lệch..."
                                            class="h-8 max-w-[200px] text-xs"
                                        />
                                        <Button
                                            v-if="
                                                activeSession?.items?.[idx]
                                                    ?.reconciliation_status ===
                                                'pending'
                                            "
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="mt-1 h-7 border-amber-500/30 px-2 text-[11px] text-amber-300"
                                            @click="
                                                openReconcileModal(
                                                    activeSession.items[idx],
                                                )
                                            "
                                        >
                                            Đối soát 2 lần đếm
                                        </Button>
                                        <span
                                            v-if="
                                                ![
                                                    'draft',
                                                    'in_progress',
                                                ].includes(
                                                    activeSession?.status || '',
                                                )
                                            "
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{ row.notes || '—' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter class="border-t border-border bg-muted/10 p-4">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showCountModal = false"
                        :disabled="isSubmitting"
                    >
                        Đóng
                    </Button>
                    <Button
                        v-if="
                            ['draft', 'in_progress'].includes(
                                activeSession?.status || '',
                            )
                        "
                        size="sm"
                        @click="handleSaveCounts"
                        :disabled="isSubmitting"
                        class="bg-emerald-500 font-semibold text-slate-950 hover:bg-emerald-400"
                    >
                        {{ isSubmitting ? 'Đang lưu...' : 'Lưu kết quả đếm' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- MODAL 3: Gửi duyệt phiên kiểm kê -->
        <Dialog
            :open="showApprovalModal"
            @update:open="showApprovalModal = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-blue-400">
                        <UploadCloud class="h-5 w-5" /> Gửi Duyệt Phiên Kiểm Kê
                        #{{ activeSession?.id }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Gửi kết quả kiểm đếm lên cấp quản lý / Chủ nhà hàng phê
                        duyệt điều chỉnh sổ cái tồn kho.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-2">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Tải lên ảnh chứng từ / biên bản giải trình</Label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                type="file"
                                accept="image/*,.pdf"
                                class="cursor-pointer text-xs file:mr-2 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary-foreground hover:file:opacity-80"
                                @change="handleUploadProof"
                                :disabled="isUploadingProof || isSubmitting"
                            />
                            <span
                                v-if="isUploadingProof"
                                class="animate-pulse text-xs text-muted-foreground"
                                >Đang tải...</span
                            >
                        </div>
                        <Input
                            v-model="approvalSubmitForm.variance_photo_path"
                            placeholder="Hoặc dán URL/đường dẫn ảnh: storage/..."
                            class="mt-1.5 h-9 text-xs"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Ghi chú giải trình kiểm kê</Label
                        >
                        <Input
                            v-model="approvalSubmitForm.notes"
                            placeholder="Ghi chú nguyên nhân hao hụt hoặc kiểm đếm bù..."
                            class="h-9 text-xs"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showApprovalModal = false"
                        :disabled="isSubmitting"
                    >
                        Hủy
                    </Button>
                    <Button
                        size="sm"
                        @click="handleSubmitForApproval"
                        :disabled="isSubmitting"
                        class="bg-blue-500 font-semibold text-white hover:bg-blue-600"
                    >
                        {{ isSubmitting ? 'Đang gửi...' : 'Gửi phê duyệt' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="showDecisionModal"
            @update:open="showDecisionModal = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Ban
                            v-if="decisionType === 'reject'"
                            class="h-5 w-5 text-rose-400"
                        />
                        <RotateCcw v-else class="h-5 w-5 text-amber-400" />
                        {{
                            decisionType === 'reject'
                                ? 'Từ chối phiên kiểm kê'
                                : 'Hủy phiên kiểm kê'
                        }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Phiên #{{ decisionSession?.id }} — lý do sẽ được lưu
                        trong nhật ký vận hành.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-1.5 py-2">
                    <Label class="text-xs font-semibold">Lý do</Label>
                    <textarea
                        v-model="decisionReason"
                        rows="4"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                        placeholder="Nêu rõ nguyên nhân và hướng xử lý tiếp theo..."
                    />
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showDecisionModal = false"
                        :disabled="isSubmitting"
                        >Đóng</Button
                    >
                    <Button
                        size="sm"
                        @click="handleDecision"
                        :disabled="isSubmitting"
                        :class="
                            decisionType === 'reject'
                                ? 'bg-rose-500 text-white hover:bg-rose-400'
                                : 'bg-slate-700 text-white hover:bg-slate-600'
                        "
                    >
                        {{ isSubmitting ? 'Đang lưu...' : 'Xác nhận' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showReconcileModal"
            @update:open="showReconcileModal = $event"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-amber-300">
                        <ShieldAlert class="h-5 w-5" /> Đối soát hai lần đếm
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Hai kết quả không khớp. Người có thẩm quyền phải chốt số
                        cuối cùng và ghi nhận lý do.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div
                        class="grid grid-cols-2 gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3 text-sm"
                    >
                        <div>
                            <p class="text-[11px] text-muted-foreground">
                                Lần đếm 1
                            </p>
                            <p class="mt-1 font-bold">
                                {{
                                    reconciliationItem?.counted_quantity_1 ??
                                    '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] text-muted-foreground">
                                Lần đếm 2
                            </p>
                            <p class="mt-1 font-bold">
                                {{
                                    reconciliationItem?.counted_quantity_2 ??
                                    '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Số lượng chốt cuối</Label
                        >
                        <Input
                            v-model.number="reconciliationForm.final_quantity"
                            type="number"
                            min="0"
                            step="0.001"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold"
                            >Biên bản đối soát</Label
                        >
                        <textarea
                            v-model="reconciliationForm.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Ví dụ: kiểm đếm lại tại khu A, thống nhất theo số lượng thực tế..."
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="showReconcileModal = false"
                        :disabled="isSubmitting"
                        >Đóng</Button
                    >
                    <Button
                        size="sm"
                        @click="handleReconcile"
                        :disabled="isSubmitting"
                        class="bg-amber-500 text-slate-950 hover:bg-amber-400"
                    >
                        {{ isSubmitting ? 'Đang chốt...' : 'Chốt kết quả' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
