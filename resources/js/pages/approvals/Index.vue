<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ClipboardList,
    Clock,
    ShieldCheck,
    ShieldX,
    X,
    Zap,
    Package,
    Trash2,
    Coins,
    UserPlus,
    Timer,
    Bell,
    RotateCcw,
    LogIn,
    LogOut,
    Lock,
    Gift,
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
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ─────────────────────────────────────────────────────────────────────

type ApprovalStatus = 'pending' | 'approved' | 'rejected' | 'escalated';

type Approval = {
    id: number;
    operation_type: string;
    operation_label: string;
    operation_data: Record<string, unknown>;
    status: ApprovalStatus;
    branch_name: string | null;
    amount_involved: number | null;
    required_authority: string;
    requester_name: string;
    reviewer_name: string | null;
    reviewer_role: string | null;
    rejection_reason: string | null;
    escalation_reason: string | null;
    reviewed_at: string | null;
    created_at: string;
    /** Người đang xem có đủ thẩm quyền quyết định yêu cầu này không. */
    can_decide: boolean;
    /** Lý do bị khóa, hiển thị khi can_decide = false. */
    block_reason: string | null;
};

type Stats = {
    pending: number;
    escalated: number;
    approved_today: number;
    rejected_today: number;
};

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    approvals: Approval[];
    stats: Stats;
    statusFilter: string;
    /** 'chain' = Chủ xem toàn chuỗi, 'branch' = Quản lý xem chi nhánh mình. */
    viewerScope: 'chain' | 'branch';
}>();

// ── State ─────────────────────────────────────────────────────────────────────

const expandedId = ref<number | null>(null);
const rejectTarget = ref<Approval | null>(null);
const rejectForm = useForm({ rejection_reason: '' });
const processingId = ref<number | null>(null);

function toggleExpand(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function timeAgo(dateStr: string): string {
    const diffMs = Date.now() - new Date(dateStr).getTime();
    const diffH = Math.floor(diffMs / 3_600_000);
    const diffD = Math.floor(diffH / 24);

    if (diffD >= 2) {
        return `${diffD} ngày trước`;
    }

    if (diffD === 1) {
        return '1 ngày trước';
    }

    if (diffH >= 1) {
        return `${diffH} giờ trước`;
    }

    const diffM = Math.floor(diffMs / 60_000);

    if (diffM >= 1) {
        return `${diffM} phút trước`;
    }

    return 'Vừa xong';
}

function formatExactDateTime(dateStr: string | null | undefined): string {
    if (!dateStr) {
        return '';
    }

    const d = new Date(dateStr);

    if (isNaN(d.getTime())) {
        return dateStr;
    }

    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes} ${day}/${month}/${year}`;
}

function pendingHours(dateStr: string): number {
    return (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
}

function isOpen(status: ApprovalStatus): boolean {
    return status === 'pending' || status === 'escalated';
}

function slaClass(dateStr: string, status: ApprovalStatus): string {
    if (!isOpen(status)) {
        return 'text-slate-400 dark:text-slate-500 font-semibold';
    }

    const h = pendingHours(dateStr);

    if (h >= 48) {
        return 'text-rose-600 dark:text-rose-450 font-bold';
    }

    if (h >= 24) {
        return 'text-amber-600 dark:text-amber-400 font-semibold';
    }

    return 'text-slate-500 dark:text-slate-400 font-semibold';
}

function slaIcon(dateStr: string, status: ApprovalStatus) {
    if (!isOpen(status)) {
        return null;
    }

    const h = pendingHours(dateStr);

    if (h >= 48) {
        return {
            icon: Zap,
            cls: 'text-rose-500 dark:text-rose-400 size-3 shrink-0',
        };
    }

    if (h >= 24) {
        return {
            icon: Timer,
            cls: 'text-amber-500 dark:text-amber-400 size-3 shrink-0',
        };
    }

    return null;
}

// ── Urgency computed ──────────────────────────────────────────────────────────

const urgentPending = computed(() =>
    props.approvals.filter(
        (a) => isOpen(a.status) && pendingHours(a.created_at) >= 24,
    ),
);

// ── Status & operation config ─────────────────────────────────────────────────

const statusConfig: Record<
    ApprovalStatus,
    { label: string; badgeClass: string; dotClass: string }
> = {
    pending: {
        label: 'Chờ duyệt',
        badgeClass:
            'bg-amber-50 text-amber-700 border border-amber-250/50 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/30',
        dotClass: 'bg-amber-500 animate-pulse',
    },
    approved: {
        label: 'Đã duyệt',
        badgeClass:
            'bg-emerald-50 text-emerald-700 border border-emerald-250/50 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/30',
        dotClass: 'bg-emerald-500',
    },
    rejected: {
        label: 'Từ chối',
        badgeClass:
            'bg-rose-50 text-rose-700 border border-rose-250/50 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/30',
        dotClass: 'bg-rose-500',
    },
    escalated: {
        label: 'Chờ Chủ quyết',
        badgeClass:
            'bg-violet-50 text-violet-700 border border-violet-250/50 dark:bg-violet-950/30 dark:text-violet-400 dark:border-violet-900/30',
        dotClass: 'bg-violet-500 animate-pulse',
    },
};

const currencyFormatter = new Intl.NumberFormat('vi-VN');

function formatAmount(value: number | null): string {
    return value === null ? '' : `${currencyFormatter.format(value)}đ`;
}

const operationConfig: Record<
    string,
    { icon: any; color: string; bg: string }
> = {
    inventory_create: {
        icon: Package,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900/20',
    },
    inventory_update: {
        icon: Package,
        color: 'text-indigo-600 dark:text-indigo-400',
        bg: 'bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/20',
    },
    inventory_delete: {
        icon: Trash2,
        color: 'text-rose-600 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-900/20',
    },
    inventory_adjustment: {
        icon: Coins,
        color: 'text-sky-600 dark:text-sky-400',
        bg: 'bg-sky-50 dark:bg-sky-950/40 border border-sky-100 dark:border-sky-900/20',
    },
    inventory_purchase: {
        icon: Package,
        color: 'text-blue-600 dark:text-blue-400',
        bg: 'bg-blue-50 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900/20',
    },
    inventory_waste: {
        icon: Trash2,
        color: 'text-orange-600 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/40 border border-orange-100 dark:border-orange-900/20',
    },
    salary_adjustment: {
        icon: Coins,
        color: 'text-violet-600 dark:text-violet-400',
        bg: 'bg-violet-50 dark:bg-violet-950/40 border border-violet-100 dark:border-violet-900/20',
    },
    employee_create: {
        icon: UserPlus,
        color: 'text-teal-600 dark:text-teal-400',
        bg: 'bg-teal-50 dark:bg-teal-950/40 border border-teal-100 dark:border-teal-900/20',
    },
    order_refund: {
        icon: RotateCcw,
        color: 'text-rose-600 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/40 border border-rose-100 dark:border-rose-900/20',
    },
    order_item_cancel: {
        icon: Trash2,
        color: 'text-orange-600 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/40 border border-orange-100 dark:border-orange-900/20',
    },
    employee_bonus: {
        icon: Gift,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900/20',
    },
    shift_checkin: {
        icon: LogIn,
        color: 'text-emerald-600 dark:text-emerald-400',
        bg: 'bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900/20',
    },
    shift_checkout: {
        icon: LogOut,
        color: 'text-amber-600 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/40 border border-amber-100 dark:border-amber-900/20',
    },
};

function opConfig(type: string) {
    return (
        operationConfig[type] ?? {
            icon: ClipboardList,
            color: 'text-slate-500 dark:text-slate-400',
            bg: 'bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800',
        }
    );
}

// ── Operation data labels ─────────────────────────────────────────────────────

const dataLabels: Record<string, string> = {
    ingredient_id: 'ID nguyên liệu',
    ingredient_name: 'Nguyên liệu',
    quantity: 'Số lượng',
    unit_symbol: 'Đơn vị',
    waste_category: 'Nguyên nhân hao hụt',
    estimated_cost: 'Chi phí ước tính',
    unit_cost: 'Đơn giá',
    supplier_id: 'Nhà cung cấp ID',
    notes: 'Ghi chú',
    occurred_at: 'Ngày thực hiện',
    employee_id: 'ID nhân viên',
    type: 'Loại điều chỉnh',
    amount: 'Số tiền',
    reason: 'Lý do',
    salary_id: 'ID bảng lương',
    order_id: 'ID đơn hàng',
    order_number: 'Mã đơn hàng',
    table_name: 'Bàn',
    refund_type: 'Hình thức hoàn tiền',
    refund_amount: 'Số tiền hoàn',
    refund_reason: 'Lý do hoàn tiền',
    product_name: 'Món ăn',
    was_started: 'Đã bắt đầu chế biến',
    assignment_id: 'ID Ca trực',
    shift_name: 'Tên ca trực',
    requested_at: 'Thời gian yêu cầu',
};

const typeAdjLabels: Record<string, string> = {
    bonus: 'Thưởng',
    penalty: 'Phạt',
    cash_shortage: 'Hụt két',
    inventory_loss: 'Hao hụt kho',
    violation: 'Vi phạm',
};

const wasteCategoryLabels: Record<string, string> = {
    spoilage: 'Hư hỏng / xuống chất lượng',
    expired: 'Hết hạn sử dụng',
    damaged: 'Hư hỏng bao bì',
    cooking_loss: 'Hao hụt chế biến',
    theft: 'Thất thoát',
    other: 'Khác',
};

function formatDataEntry(
    key: string,
    value: unknown,
): { label: string; display: string; highlight: boolean } {
    const label = dataLabels[key] ?? key;

    if (value === null || value === undefined || value === '') {
        return { label, display: '—', highlight: false };
    }

    // Hide raw IDs if name is present
    if (key === 'ingredient_id') {
        return { label, display: String(value), highlight: false };
    }

    if (key === 'ingredient_name') {
        return { label, display: String(value), highlight: true };
    }

    if (
        (key.includes('cost') || key === 'amount') &&
        typeof value === 'number'
    ) {
        return {
            label,
            display: Number(value).toLocaleString('vi-VN') + 'đ',
            highlight: true,
        };
    }

    if (key === 'type') {
        return {
            label,
            display: typeAdjLabels[String(value)] ?? String(value),
            highlight: false,
        };
    }

    if (key === 'refund_type') {
        return {
            label,
            display: value === 'full' ? 'Hoàn toàn phần' : 'Hoàn một phần',
            highlight: false,
        };
    }

    if (key === 'waste_category') {
        return {
            label,
            display: wasteCategoryLabels[String(value)] ?? String(value),
            highlight: false,
        };
    }

    if (key === 'occurred_at') {
        const d = new Date(String(value));

        return {
            label,
            display: isNaN(d.getTime())
                ? String(value)
                : d.toLocaleDateString('vi-VN'),
            highlight: false,
        };
    }

    return { label, display: String(value), highlight: false };
}

function visibleDataEntries(data: Record<string, unknown>) {
    const skip = new Set(['ingredient_id']); // hide raw ID if ingredient_name exists

    if (data['ingredient_name']) {
        skip.add('ingredient_id');
    }

    if (data['order_number']) {
        skip.add('order_id');
    }

    return Object.entries(data)
        .filter(([k]) => !skip.has(k))
        .map(([k, v]) => ({ ...formatDataEntry(k, v), key: k }));
}

// ── Filter ────────────────────────────────────────────────────────────────────

function applyFilter(status: string) {
    router.get(
        '/approvals',
        { status },
        { preserveState: true, replace: true },
    );
}

// ── Approve ───────────────────────────────────────────────────────────────────

function approveRequest(approval: Approval) {
    processingId.value = approval.id;
    router.patch(
        `/approvals/${approval.id}/approve`,
        {},
        {
            onSuccess: () => {
                toast.success('Đã phê duyệt yêu cầu.');
                processingId.value = null;
            },
            onError: (errors: Record<string, string>) => {
                toast.error(errors.error ?? 'Có lỗi khi phê duyệt.');
                processingId.value = null;
            },
        },
    );
}

// ── Reject ────────────────────────────────────────────────────────────────────

function openReject(approval: Approval) {
    rejectTarget.value = approval;
    rejectForm.reset();
}

function closeReject() {
    rejectTarget.value = null;
    rejectForm.reset();
}

function submitReject() {
    if (!rejectTarget.value) {
        return;
    }

    rejectForm.patch(`/approvals/${rejectTarget.value.id}/reject`, {
        onSuccess: () => {
            toast.success('Đã từ chối yêu cầu.');
            closeReject();
        },
        onError: (errors: Record<string, string>) =>
            toast.error(
                errors.error ??
                    errors.rejection_reason ??
                    'Không thể từ chối yêu cầu.',
            ),
    });
}
</script>

<template>
    <Head title="Phê duyệt" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <ShieldCheck class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Kiểm duyệt chéo
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Phê duyệt các thao tác tài chính từ nhân viên trước khi
                        có hiệu lực.
                    </p>
                </div>
            </div>
        </div>

        <!-- Urgency Alert -->
        <div
            v-if="urgentPending.length > 0"
            class="flex animate-pulse items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50 p-4 dark:border-amber-900/30 dark:bg-amber-950/20"
        >
            <div
                class="rounded-lg bg-amber-100 p-1 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
            >
                <Bell class="h-5 w-5" />
            </div>
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
                    {{ urgentPending.length }} yêu cầu đang chờ quá 24 giờ!
                </p>
                <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                    Các yêu cầu chờ duyệt lâu có thể làm gián đoạn vận hành. Hãy
                    xử lý ngay.
                </p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Stats: Pending -->
            <Card
                class="border-amber-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-amber-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-amber-500 uppercase"
                        >Chờ phê duyệt</CardDescription
                    >
                    <Clock class="size-4 text-amber-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-amber-600 dark:text-amber-400"
                    >
                        {{ stats.pending }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        yêu cầu cần xử lý
                    </p>
                </CardContent>
            </Card>

            <!-- Stats: Approved -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Đã duyệt hôm nay</CardDescription
                    >
                    <CheckCircle2 class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ stats.approved_today }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        yêu cầu được thông qua
                    </p>
                </CardContent>
            </Card>

            <!-- Stats: Rejected -->
            <Card
                class="border-rose-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-rose-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >Đã từ chối hôm nay</CardDescription
                    >
                    <X class="size-4 text-rose-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        {{ stats.rejected_today }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        yêu cầu bị từ chối
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Table Card -->
        <Card class="overflow-hidden shadow-sm">
            <CardHeader
                class="flex flex-col gap-4 border-b bg-slate-50/50 pb-3 sm:flex-row sm:items-center sm:justify-between dark:bg-slate-900/50"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-1.5 text-base font-bold"
                    >
                        <ShieldCheck
                            class="text-indigo-655 size-5 text-indigo-600 dark:text-indigo-400"
                        />
                        Danh Sách Yêu Cầu Kiểm Duyệt
                    </CardTitle>
                    <CardDescription
                        >Các giao dịch và điều chỉnh phát sinh cần quản trị viên
                        xem xét và duyệt.</CardDescription
                    >
                </div>

                <!-- Filter tabs (Segmented button control) -->
                <div
                    class="flex shrink-0 items-center gap-1 self-start rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 sm:self-center dark:border-slate-800 dark:bg-slate-900"
                >
                    <button
                        v-for="f in [
                            {
                                value: 'open',
                                label: 'Cần xử lý',
                                count: stats.pending + stats.escalated,
                            },
                            {
                                value: 'escalated',
                                label: 'Vượt thẩm quyền',
                                count: stats.escalated,
                            },
                            {
                                value: 'approved',
                                label: 'Đã duyệt',
                                count: stats.approved_today,
                            },
                            {
                                value: 'rejected',
                                label: 'Từ chối',
                                count: null,
                            },
                            {
                                value: 'all',
                                label: 'Tất cả',
                                count: approvals.length,
                            },
                        ]"
                        :key="f.value"
                        type="button"
                        @click="applyFilter(f.value)"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[10px] font-bold whitespace-nowrap transition-colors',
                            statusFilter === f.value
                                ? 'border border-slate-200/10 bg-white text-slate-800 shadow-sm dark:border-slate-700/20 dark:bg-slate-800 dark:text-slate-100'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                        ]"
                    >
                        {{ f.label }}
                        <span
                            v-if="f.count !== null"
                            :class="[
                                'inline-flex h-4.5 w-4.5 items-center justify-center rounded-full text-[9px] font-black',
                                statusFilter === f.value
                                    ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300'
                                    : 'text-slate-650 bg-slate-200/60 dark:bg-slate-800 dark:text-slate-400',
                            ]"
                            >{{ f.count }}</span
                        >
                    </button>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Empty state -->
                <div
                    v-if="approvals.length === 0"
                    class="flex flex-col items-center justify-center gap-3 py-20 text-gray-400"
                >
                    <div
                        class="rounded-2xl border border-slate-100 bg-slate-50 p-4 dark:border-slate-800/60 dark:bg-slate-900"
                    >
                        <ClipboardList
                            class="h-10 w-10 text-indigo-600 opacity-30 dark:text-indigo-400"
                        />
                    </div>
                    <div class="text-center">
                        <p
                            class="text-sm font-semibold text-slate-700 dark:text-slate-300"
                        >
                            Không có yêu cầu nào
                        </p>
                        <p class="mt-1 text-xs text-slate-400">
                            Tất cả yêu cầu trong bộ lọc này đã được xử lý
                        </p>
                    </div>
                </div>

                <template v-else>
                    <!-- Table header Desktop -->
                    <div
                        class="hidden grid-cols-[auto_1.5fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 bg-slate-50/50 px-5 py-3 text-[10px] font-bold tracking-wider text-slate-500 uppercase lg:grid dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <div class="w-6"></div>
                        <!-- Arrow spacing -->
                        <div>Yêu cầu / Thao tác</div>
                        <div>Người tạo</div>
                        <div>Thời gian nhận / SLA</div>
                        <div class="text-center">Trạng thái</div>
                        <div class="text-right">Hành động</div>
                    </div>

                    <!-- Row Item -->
                    <div
                        v-for="approval in approvals"
                        :key="approval.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                    >
                        <!-- Main Row -->
                        <div
                            class="grid cursor-pointer grid-cols-[1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-slate-50/60 lg:grid-cols-[auto_1.5fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5 dark:hover:bg-slate-900/30"
                            @click="toggleExpand(approval.id)"
                        >
                            <!-- Expand Arrow -->
                            <div
                                class="hidden w-6 items-center justify-center lg:flex"
                            >
                                <component
                                    :is="
                                        expandedId === approval.id
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="size-4 shrink-0 text-slate-400 transition-transform duration-200"
                                />
                            </div>

                            <!-- Mobile layout (left) + Desktop col 1 (Yêu cầu) -->
                            <div class="flex min-w-0 items-center gap-3">
                                <!-- Operation icon -->
                                <div
                                    :class="[
                                        'shrink-0 rounded-xl border p-2 dark:border-slate-800',
                                        opConfig(approval.operation_type).bg,
                                    ]"
                                >
                                    <component
                                        :is="
                                            opConfig(approval.operation_type)
                                                .icon
                                        "
                                        :class="[
                                            'h-4.5 w-4.5',
                                            opConfig(approval.operation_type)
                                                .color,
                                        ]"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm leading-snug font-semibold text-slate-950 dark:text-slate-50"
                                    >
                                        {{ approval.operation_label }}
                                    </p>
                                    <!-- Mobile details -->
                                    <div
                                        class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] font-medium text-slate-400 lg:hidden"
                                    >
                                        <span>{{
                                            approval.requester_name
                                        }}</span>
                                        <span>·</span>
                                        <span class="font-mono font-bold text-slate-700 dark:text-slate-300">
                                            {{ formatExactDateTime(approval.created_at) }}
                                        </span>
                                        <span>·</span>
                                        <span
                                            :class="
                                                slaClass(
                                                    approval.created_at,
                                                    approval.status,
                                                )
                                            "
                                        >
                                            {{ timeAgo(approval.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Col 2: Requester + chi nhánh + số tiền -->
                            <div class="hidden lg:block">
                                <span
                                    class="block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    {{ approval.requester_name }}
                                </span>
                                <span
                                    v-if="
                                        viewerScope === 'chain' &&
                                        approval.branch_name
                                    "
                                    class="block text-[10px] font-medium text-slate-400 dark:text-slate-500"
                                >
                                    {{ approval.branch_name }}
                                </span>
                                <span
                                    v-if="approval.amount_involved"
                                    class="mt-0.5 inline-block rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 tabular-nums dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ formatAmount(approval.amount_involved) }}
                                </span>
                            </div>

                            <!-- Desktop Col 3: SLA / Time -->
                            <div class="hidden flex-col justify-center lg:flex">
                                <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
                                    {{ formatExactDateTime(approval.created_at) }}
                                </span>
                                <div class="mt-0.5 flex items-center gap-1">
                                    <component
                                        v-if="
                                            slaIcon(
                                                approval.created_at,
                                                approval.status,
                                            )
                                        "
                                        :is="
                                            slaIcon(
                                                approval.created_at,
                                                approval.status,
                                            )!.icon
                                        "
                                        :class="
                                            slaIcon(
                                                approval.created_at,
                                                approval.status,
                                            )!.cls
                                        "
                                    />
                                    <span
                                        class="text-[10px] font-semibold"
                                        :class="
                                            slaClass(
                                                approval.created_at,
                                                approval.status,
                                            )
                                        "
                                    >
                                        {{ timeAgo(approval.created_at) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Desktop Col 4: Status -->
                            <div
                                class="flex items-center justify-end lg:justify-center"
                            >
                                <span
                                    :class="[
                                        'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase',
                                        statusConfig[approval.status]
                                            .badgeClass,
                                    ]"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            statusConfig[approval.status]
                                                .dotClass,
                                        ]"
                                    />
                                    {{ statusConfig[approval.status].label }}
                                </span>
                            </div>

                            <!-- Desktop Col 5: Actions / Chevron toggle -->
                            <div
                                class="flex items-center justify-end gap-2"
                                @click.stop
                            >
                                <!-- Hành động nhanh cho yêu cầu còn mở -->
                                <div
                                    v-if="
                                        isOpen(approval.status) &&
                                        approval.can_decide
                                    "
                                    class="flex items-center gap-1.5"
                                >
                                    <Button
                                        @click="approveRequest(approval)"
                                        :disabled="processingId === approval.id"
                                        size="sm"
                                        class="flex h-8 shrink-0 items-center gap-1 bg-emerald-600 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700"
                                        title="Phê duyệt ngay"
                                    >
                                        <ShieldCheck class="h-3.5 w-3.5" />
                                        <span class="hidden sm:inline"
                                            >Duyệt</span
                                        >
                                    </Button>
                                    <Button
                                        @click="openReject(approval)"
                                        size="sm"
                                        variant="outline"
                                        class="dark:text-rose-455 flex h-8 shrink-0 items-center gap-1 border-rose-200 text-xs font-semibold text-rose-600 shadow-sm hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/20"
                                        title="Từ chối"
                                    >
                                        <ShieldX class="h-3.5 w-3.5" />
                                        <span class="hidden sm:inline"
                                            >Từ chối</span
                                        >
                                    </Button>
                                </div>

                                <!-- Vượt thẩm quyền: nói rõ lý do thay vì để
                                     người dùng bấm rồi mới nhận lỗi -->
                                <div
                                    v-else-if="isOpen(approval.status)"
                                    class="flex max-w-[15rem] items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 dark:border-slate-800 dark:bg-slate-900/60"
                                    :title="approval.block_reason ?? ''"
                                >
                                    <Lock
                                        class="h-3.5 w-3.5 shrink-0 text-slate-400"
                                    />
                                    <span
                                        class="line-clamp-2 text-[10px] leading-tight font-semibold text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            approval.block_reason ??
                                            'Ngoài thẩm quyền của bạn'
                                        }}
                                    </span>
                                </div>

                                <!-- Chevron toggle on Mobile -->
                                <button
                                    class="rounded p-1 text-slate-400 hover:bg-slate-100 lg:hidden dark:hover:bg-slate-800"
                                    @click="toggleExpand(approval.id)"
                                >
                                    <component
                                        :is="
                                            expandedId === approval.id
                                                ? ChevronUp
                                                : ChevronDown
                                        "
                                        class="size-4 shrink-0 transition-transform duration-200"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Expanded detail with Vue Transition -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-[600px]"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-if="expandedId === approval.id"
                                class="overflow-hidden border-t border-slate-100 bg-slate-50/50 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/10"
                            >
                                <div class="grid gap-6 sm:grid-cols-2">
                                    <!-- Left component: Operation data as structured cards/tables -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Chi tiết dữ liệu thao tác
                                        </p>
                                        <div class="grid grid-cols-1 gap-2">
                                            <div
                                                v-for="entry in visibleDataEntries(
                                                    approval.operation_data,
                                                )"
                                                :key="entry.key"
                                                :class="[
                                                    'flex items-center justify-between rounded-lg border px-3 py-2 text-xs font-semibold transition-colors',
                                                    entry.highlight
                                                        ? 'border-violet-100 bg-violet-50/50 dark:border-violet-900/30 dark:bg-violet-950/20'
                                                        : 'border-slate-100 bg-slate-50/40 dark:border-slate-800 dark:bg-slate-900/20',
                                                ]"
                                            >
                                                <span
                                                    class="font-medium text-slate-500 dark:text-slate-400"
                                                    >{{ entry.label }}</span
                                                >
                                                <template v-if="entry.key === 'photo_url' || entry.key === 'invoice_file_url'">
                                                    <a :href="String(entry.display)" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 underline font-bold hover:text-indigo-800 dark:text-indigo-400">
                                                        🖼️ Xem ảnh chứng từ / bằng chứng
                                                    </a>
                                                </template>
                                                <span
                                                    v-else
                                                    :class="[
                                                        'font-mono font-bold',
                                                        entry.highlight
                                                            ? 'text-violet-750 dark:text-violet-400'
                                                            : 'text-slate-800 dark:text-slate-200',
                                                    ]"
                                                >
                                                    {{ entry.display }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right component: Reviewer logs / Rejection details -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Trạng thái phê duyệt & Lịch sử
                                        </p>

                                        <div
                                            v-if="approval.status === 'pending'"
                                            class="flex items-start gap-2 py-2 text-xs font-medium text-slate-500 dark:text-slate-400"
                                        >
                                            <Clock
                                                class="mt-0.5 size-4 shrink-0 text-amber-500"
                                            />
                                            <div>
                                                <p
                                                    class="font-bold text-slate-700 dark:text-slate-300"
                                                >
                                                    Yêu cầu đang chờ phê duyệt
                                                </p>
                                                <p
                                                    class="mt-1 text-[11px] text-slate-400"
                                                >
                                                    Được tạo bởi nhân sự
                                                    <strong>{{
                                                        approval.requester_name
                                                    }}</strong
                                                    >. Vui lòng kiểm tra kỹ chi
                                                    tiết trước khi xác nhận.
                                                </p>
                                            </div>
                                        </div>

                                        <div
                                            v-else-if="approval.reviewer_name"
                                            class="space-y-2 text-xs"
                                        >
                                            <div
                                                v-if="
                                                    approval.status ===
                                                    'approved'
                                                "
                                                class="flex items-start gap-2.5 rounded-lg border border-emerald-100/50 bg-emerald-50/40 p-3 dark:border-emerald-900/20 dark:bg-emerald-950/20"
                                            >
                                                <CheckCircle2
                                                    class="text-emerald-505 mt-0.5 h-4.5 w-4.5 shrink-0 text-emerald-500"
                                                />
                                                <div>
                                                    <span
                                                        class="font-bold text-slate-700 dark:text-slate-300"
                                                        >Yêu cầu đã được phê
                                                        duyệt</span
                                                    >
                                                    <p
                                                        class="text-slate-505 mt-1 text-[11px] font-medium dark:text-slate-400"
                                                    >
                                                        Người duyệt:
                                                        <strong
                                                            class="text-slate-700 dark:text-slate-200"
                                                            >{{
                                                                approval.reviewer_name
                                                            }}</strong
                                                        >
                                                        ·
                                                        {{
                                                            approval.reviewed_at
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <div
                                                v-else-if="
                                                    approval.status ===
                                                    'rejected'
                                                "
                                                class="space-y-3 rounded-lg border border-rose-100/50 bg-rose-50/40 p-3 dark:border-rose-900/20 dark:bg-rose-950/20"
                                            >
                                                <div
                                                    class="flex items-start gap-2.5"
                                                >
                                                    <AlertTriangle
                                                        class="mt-0.5 h-4.5 w-4.5 shrink-0 text-rose-500"
                                                    />
                                                    <div>
                                                        <span
                                                            class="font-bold text-slate-700 dark:text-slate-300"
                                                            >Yêu cầu bị từ
                                                            chối</span
                                                        >
                                                        <p
                                                            class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                                        >
                                                            Người duyệt:
                                                            <strong
                                                                class="text-slate-700 dark:text-slate-200"
                                                                >{{
                                                                    approval.reviewer_name
                                                                }}</strong
                                                            >
                                                            ·
                                                            {{
                                                                approval.reviewed_at
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div
                                                    v-if="
                                                        approval.rejection_reason
                                                    "
                                                    class="rounded-lg border border-rose-100 bg-white p-2.5 text-rose-600 dark:border-rose-950 dark:bg-slate-900 dark:text-rose-400"
                                                >
                                                    <p
                                                        class="mb-0.5 text-[10px] font-bold tracking-wider text-rose-500/80 uppercase"
                                                    >
                                                        Lý do từ chối:
                                                    </p>
                                                    <p
                                                        class="text-xs leading-relaxed font-semibold"
                                                    >
                                                        {{
                                                            approval.rejection_reason
                                                        }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </CardContent>
        </Card>
    </div>

    <!-- Reject Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            
            <div
                v-if="rejectTarget"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
                @click.self="closeReject"
            >
                <Card
                    class="w-full max-w-md animate-in overflow-hidden border shadow-2xl duration-150 zoom-in-95 fade-in dark:border-slate-800"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base font-bold text-rose-600"
                            >
                                <ShieldX class="size-5" />
                                Từ Chối Yêu Cầu Phê Duyệt
                            </CardTitle>
                            <CardDescription class="mt-1">
                                <span
                                    :class="[
                                        'mr-1 inline-flex items-center gap-1 text-[11px] font-bold tracking-wide uppercase',
                                        opConfig(rejectTarget.operation_type)
                                            .color,
                                    ]"
                                >
                                    <component
                                        :is="
                                            opConfig(
                                                rejectTarget.operation_type,
                                            ).icon
                                        "
                                        class="h-3.5 w-3.5"
                                    />
                                    {{ rejectTarget.operation_label }}
                                </span>
                                từ
                                <strong>{{
                                    rejectTarget.requester_name
                                }}</strong
                                >.
                            </CardDescription>
                        </div>
                        <button
                            @click="closeReject"
                            class="hover:text-slate-650 rounded-lg p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </CardHeader>

                    <CardContent class="space-y-4 pt-4">
                        <div class="grid gap-1.5">
                            <Label
                                for="reject-reason"
                                class="text-xs font-bold tracking-wide text-slate-500 uppercase dark:text-slate-400"
                            >
                                Lý do từ chối cụ thể
                                <span class="text-rose-500">*</span>
                            </Label>
                            <textarea
                                id="reject-reason"
                                v-model="rejectForm.rejection_reason"
                                rows="3"
                                placeholder="Nhập lý do từ chối cụ thể để nhân viên hiểu và sửa đổi..."
                                class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:outline-none dark:border-slate-700 dark:text-slate-300"
                                :class="{
                                    'border-rose-400 focus-visible:ring-rose-400':
                                        rejectForm.errors.rejection_reason,
                                }"
                            />
                            <p
                                v-if="rejectForm.errors.rejection_reason"
                                class="text-[10px] font-bold text-rose-500"
                            >
                                {{ rejectForm.errors.rejection_reason }}
                            </p>
                        </div>

                        <!-- Quick reason chips -->
                        <div class="space-y-1.5">
                            <Label
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >Chọn nhanh lý do mẫu:</Label
                            >
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="reason in [
                                        'Thiếu chứng từ',
                                        'Số lượng không hợp lý',
                                        'Sai đơn giá',
                                        'Không đúng thời điểm',
                                    ]"
                                    :key="reason"
                                    type="button"
                                    @click="
                                        rejectForm.rejection_reason = reason
                                    "
                                    class="text-slate-650 rounded-full border border-slate-200/60 bg-slate-50 px-2.5 py-1 text-xs font-semibold transition-colors hover:bg-rose-50 hover:text-rose-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                >
                                    {{ reason }}
                                </button>
                            </div>
                        </div>

                        <div
                            class="flex gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                        >
                            <Button
                                @click="submitReject"
                                :disabled="rejectForm.processing"
                                class="flex-1 rounded-lg bg-rose-600 py-2 text-xs font-semibold text-white transition-colors hover:bg-rose-700 disabled:opacity-60"
                            >
                                {{
                                    rejectForm.processing
                                        ? 'Đang xử lý...'
                                        : 'Xác nhận từ chối'
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                @click="closeReject"
                                class="h-9 px-4 text-xs font-semibold"
                            >
                                Hủy
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
            
        </Transition>
    </Teleport>
</template>
