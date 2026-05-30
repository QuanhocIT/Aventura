<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle, CheckCircle2, ChevronDown, ChevronUp,
    ClipboardList, Clock, ShieldCheck, ShieldX, X, Zap,
    Package, Trash2, Coins, UserPlus, Timer, Bell,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ─────────────────────────────────────────────────────────────────────

type ApprovalStatus = 'pending' | 'approved' | 'rejected';

type Approval = {
    id: number;
    operation_type: string;
    operation_label: string;
    operation_data: Record<string, unknown>;
    status: ApprovalStatus;
    requester_name: string;
    reviewer_name: string | null;
    rejection_reason: string | null;
    reviewed_at: string | null;
    created_at: string;
};

type Stats = {
    pending: number;
    approved_today: number;
    rejected_today: number;
};

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    approvals: Approval[];
    stats: Stats;
    statusFilter: string;
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
    const diffH  = Math.floor(diffMs / 3_600_000);
    const diffD  = Math.floor(diffH / 24);
    if (diffD >= 2) return `${diffD} ngày trước`;
    if (diffD === 1) return '1 ngày trước';
    if (diffH >= 1) return `${diffH} giờ trước`;
    const diffM = Math.floor(diffMs / 60_000);
    if (diffM >= 1) return `${diffM} phút trước`;
    return 'Vừa xong';
}

function pendingHours(dateStr: string): number {
    return (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
}

function slaClass(dateStr: string, status: ApprovalStatus): string {
    if (status !== 'pending') return 'text-gray-400';
    const h = pendingHours(dateStr);
    if (h >= 48) return 'text-rose-600 font-bold';
    if (h >= 24) return 'text-amber-600 font-semibold';
    return 'text-slate-500';
}

function slaIcon(dateStr: string, status: ApprovalStatus) {
    if (status !== 'pending') return null;
    const h = pendingHours(dateStr);
    if (h >= 48) return { icon: Zap, cls: 'text-rose-500' };
    if (h >= 24) return { icon: Timer, cls: 'text-amber-500' };
    return null;
}

// ── Urgency computed ──────────────────────────────────────────────────────────

const urgentPending = computed(() =>
    props.approvals.filter(a => a.status === 'pending' && pendingHours(a.created_at) >= 24)
);

// ── Status & operation config ─────────────────────────────────────────────────

const statusConfig: Record<ApprovalStatus, { label: string; badgeClass: string; dotClass: string }> = {
    pending:  { label: 'Chờ duyệt',  badgeClass: 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-800',   dotClass: 'bg-amber-500 animate-pulse' },
    approved: { label: 'Đã duyệt',   badgeClass: 'bg-emerald-100 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400', dotClass: 'bg-emerald-500' },
    rejected: { label: 'Từ chối',    badgeClass: 'bg-rose-100 text-rose-700 border border-rose-200 dark:bg-rose-950/30 dark:text-rose-400', dotClass: 'bg-rose-500' },
};

const operationConfig: Record<string, { icon: any; color: string; bg: string }> = {
    inventory_purchase: { icon: Package,  color: 'text-blue-600 dark:text-blue-400',   bg: 'bg-blue-100 dark:bg-blue-950/40' },
    inventory_waste:    { icon: Trash2,   color: 'text-orange-600 dark:text-orange-400', bg: 'bg-orange-100 dark:bg-orange-950/40' },
    salary_adjustment:  { icon: Coins,    color: 'text-violet-600 dark:text-violet-400', bg: 'bg-violet-100 dark:bg-violet-950/40' },
    employee_create:    { icon: UserPlus, color: 'text-teal-600 dark:text-teal-400',    bg: 'bg-teal-100 dark:bg-teal-950/40' },
};

function opConfig(type: string) {
    return operationConfig[type] ?? { icon: ClipboardList, color: 'text-gray-500', bg: 'bg-gray-100' };
}

// ── Operation data labels ─────────────────────────────────────────────────────

const dataLabels: Record<string, string> = {
    ingredient_id:    'ID nguyên liệu',
    ingredient_name:  'Nguyên liệu',
    quantity:         'Số lượng',
    unit_cost:        'Đơn giá',
    supplier_id:      'Nhà cung cấp ID',
    notes:            'Ghi chú',
    occurred_at:      'Ngày thực hiện',
    employee_id:      'ID nhân viên',
    type:             'Loại điều chỉnh',
    amount:           'Số tiền',
    reason:           'Lý do',
    salary_id:        'ID bảng lương',
};

const typeAdjLabels: Record<string, string> = {
    bonus: 'Thưởng',
    penalty: 'Phạt',
    cash_shortage: 'Hụt két',
    inventory_loss: 'Hao hụt kho',
    violation: 'Vi phạm',
};

function formatDataEntry(key: string, value: unknown): { label: string; display: string; highlight: boolean } {
    const label = dataLabels[key] ?? key;
    if (value === null || value === undefined || value === '') return { label, display: '—', highlight: false };

    // Hide raw IDs if name is present
    if (key === 'ingredient_id') return { label, display: String(value), highlight: false };

    if (key === 'ingredient_name') return { label, display: String(value), highlight: true };
    if ((key.includes('cost') || key === 'amount') && typeof value === 'number') {
        return { label, display: Number(value).toLocaleString('vi-VN') + 'đ', highlight: true };
    }
    if (key === 'type') return { label, display: typeAdjLabels[String(value)] ?? String(value), highlight: false };
    if (key === 'occurred_at') {
        const d = new Date(String(value));
        return { label, display: isNaN(d.getTime()) ? String(value) : d.toLocaleDateString('vi-VN'), highlight: false };
    }
    return { label, display: String(value), highlight: false };
}

function visibleDataEntries(data: Record<string, unknown>) {
    const skip = new Set(['ingredient_id']); // hide raw ID if ingredient_name exists
    if (data['ingredient_name']) skip.add('ingredient_id');
    return Object.entries(data)
        .filter(([k]) => !skip.has(k))
        .map(([k, v]) => ({ ...formatDataEntry(k, v), key: k }));
}

// ── Filter ────────────────────────────────────────────────────────────────────

function applyFilter(status: string) {
    router.get('/approvals', { status }, { preserveState: true, replace: true });
}

// ── Approve ───────────────────────────────────────────────────────────────────

function approveRequest(approval: Approval) {
    processingId.value = approval.id;
    router.patch(`/approvals/${approval.id}/approve`, {}, {
        onSuccess: () => { toast.success('Đã phê duyệt yêu cầu.'); processingId.value = null; },
        onError:   () => { toast.error('Có lỗi khi phê duyệt.');   processingId.value = null; },
    });
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
    if (!rejectTarget.value) return;
    rejectForm.patch(`/approvals/${rejectTarget.value.id}/reject`, {
        onSuccess: () => { toast.success('Đã từ chối yêu cầu.'); closeReject(); },
        onError:   () => toast.error('Vui lòng nhập lý do từ chối.'),
    });
}
</script>

<template>
    <Head title="Phê duyệt" />

    <div class="space-y-6 p-4 md:p-6 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 shadow-sm">
                    <ShieldCheck class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Kiểm duyệt chéo</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Phê duyệt các thao tác tài chính từ nhân viên trước khi có hiệu lực</p>
                </div>
            </div>
        </div>

        <!-- Urgency Alert -->
        <div
            v-if="urgentPending.length > 0"
            class="flex items-start gap-3 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 animate-pulse"
        >
            <Bell class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
            <div>
                <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
                    {{ urgentPending.length }} yêu cầu đang chờ quá 24 giờ!
                </p>
                <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                    Các yêu cầu chờ duyệt lâu có thể làm gián đoạn vận hành. Hãy xử lý ngay.
                </p>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
                <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                    <Clock class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.pending }}</p>
                    <p class="text-xs text-gray-500">Chờ phê duyệt</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
                <div class="p-3 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                    <CheckCircle2 class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.approved_today }}</p>
                    <p class="text-xs text-gray-500">Đã duyệt hôm nay</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
                <div class="p-3 rounded-lg bg-rose-100 dark:bg-rose-900/30">
                    <X class="h-5 w-5 text-rose-600 dark:text-rose-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.rejected_today }}</p>
                    <p class="text-xs text-gray-500">Đã từ chối hôm nay</p>
                </div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-2 flex-wrap">
            <button
                v-for="f in [
                    { value: 'pending',  label: 'Chờ duyệt',  count: stats.pending },
                    { value: 'approved', label: 'Đã duyệt',   count: stats.approved_today },
                    { value: 'rejected', label: 'Từ chối',    count: null },
                    { value: 'all',      label: 'Tất cả',     count: approvals.length },
                ]"
                :key="f.value"
                @click="applyFilter(f.value)"
                :class="[
                    'inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                    statusFilter === f.value
                        ? 'bg-violet-600 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
                ]"
            >
                {{ f.label }}
                <span
                    v-if="f.count !== null"
                    :class="[
                        'inline-flex items-center justify-center rounded-full text-xs font-bold w-5 h-5',
                        statusFilter === f.value ? 'bg-white/20 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'
                    ]"
                >{{ f.count }}</span>
            </button>
        </div>

        <!-- List -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

            <!-- Empty state -->
            <div v-if="approvals.length === 0" class="flex flex-col items-center justify-center py-20 text-gray-400 gap-3">
                <div class="p-4 rounded-2xl bg-gray-100 dark:bg-gray-700">
                    <ClipboardList class="h-10 w-10 opacity-40" />
                </div>
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">Không có yêu cầu nào</p>
                    <p class="text-xs text-gray-400 mt-1">Tất cả yêu cầu đã được xử lý</p>
                </div>
            </div>

            <template v-else>
                <div
                    v-for="approval in approvals"
                    :key="approval.id"
                    class="border-b border-gray-100 dark:border-gray-700 last:border-0"
                >
                    <!-- Row -->
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">

                        <!-- Operation icon -->
                        <div :class="['p-2 rounded-lg shrink-0', opConfig(approval.operation_type).bg]">
                            <component :is="opConfig(approval.operation_type).icon" :class="['h-4 w-4', opConfig(approval.operation_type).color]" />
                        </div>

                        <!-- Info — clickable to expand -->
                        <div class="flex-1 min-w-0 cursor-pointer" @click="toggleExpand(approval.id)">
                            <p class="font-medium text-gray-900 dark:text-white text-sm truncate">{{ approval.operation_label }}</p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="text-xs text-gray-500">{{ approval.requester_name }}</span>
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <!-- SLA indicator -->
                                <span class="flex items-center gap-0.5 text-xs" :class="slaClass(approval.created_at, approval.status)">
                                    <component v-if="slaIcon(approval.created_at, approval.status)" :is="slaIcon(approval.created_at, approval.status)!.icon" class="h-3 w-3" :class="slaIcon(approval.created_at, approval.status)!.cls" />
                                    {{ timeAgo(approval.created_at) }}
                                </span>
                            </div>
                        </div>

                        <!-- Status badge -->
                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium shrink-0', statusConfig[approval.status].badgeClass]">
                            <span :class="['h-1.5 w-1.5 rounded-full', statusConfig[approval.status].dotClass]" />
                            {{ statusConfig[approval.status].label }}
                        </span>

                        <!-- Inline quick actions for pending -->
                        <div v-if="approval.status === 'pending'" class="flex items-center gap-1.5 shrink-0">
                            <button
                                @click.stop="approveRequest(approval)"
                                :disabled="processingId === approval.id"
                                class="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-xs rounded-lg font-medium transition-colors"
                                title="Phê duyệt ngay"
                            >
                                <ShieldCheck class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Duyệt</span>
                            </button>
                            <button
                                @click.stop="openReject(approval)"
                                class="flex items-center gap-1 px-3 py-1.5 bg-white dark:bg-gray-700 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 text-xs rounded-lg font-medium transition-colors"
                                title="Từ chối"
                            >
                                <ShieldX class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Từ chối</span>
                            </button>
                        </div>

                        <!-- Expand toggle -->
                        <button class="text-gray-400 shrink-0" @click="toggleExpand(approval.id)">
                            <ChevronDown v-if="expandedId !== approval.id" class="h-4 w-4" />
                            <ChevronUp  v-else                             class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Expanded detail -->
                    <div v-if="expandedId === approval.id" class="px-4 pb-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700">

                        <!-- Operation data as structured table -->
                        <div class="mt-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Chi tiết thao tác</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                <div
                                    v-for="entry in visibleDataEntries(approval.operation_data)"
                                    :key="entry.key"
                                    :class="[
                                        'flex items-center justify-between px-3 py-2 rounded-lg border text-xs',
                                        entry.highlight
                                            ? 'bg-violet-50 dark:bg-violet-950/30 border-violet-100 dark:border-violet-900'
                                            : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-600'
                                    ]"
                                >
                                    <span class="text-gray-500 dark:text-gray-400">{{ entry.label }}</span>
                                    <span :class="['font-semibold', entry.highlight ? 'text-violet-700 dark:text-violet-300' : 'text-gray-800 dark:text-gray-200']">
                                        {{ entry.display }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Reviewer info -->
                        <div v-if="approval.reviewer_name" class="mt-3 flex items-start gap-2 text-xs">
                            <template v-if="approval.status === 'approved'">
                                <CheckCircle2 class="h-4 w-4 text-emerald-500 mt-0.5 shrink-0" />
                                <span class="text-gray-600 dark:text-gray-300">
                                    Duyệt bởi <strong>{{ approval.reviewer_name }}</strong> · {{ approval.reviewed_at }}
                                </span>
                            </template>
                            <template v-else-if="approval.status === 'rejected'">
                                <AlertTriangle class="h-4 w-4 text-rose-500 mt-0.5 shrink-0" />
                                <div>
                                    <span class="text-gray-600 dark:text-gray-300">
                                        Từ chối bởi <strong>{{ approval.reviewer_name }}</strong> · {{ approval.reviewed_at }}
                                    </span>
                                    <p v-if="approval.rejection_reason" class="mt-1 text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/30 px-2 py-1 rounded-lg border border-rose-100 dark:border-rose-900">
                                        Lý do: {{ approval.rejection_reason }}
                                    </p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Reject Modal -->
    <Teleport to="body">
        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeReject">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Từ chối yêu cầu</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            <span :class="['inline-flex items-center gap-1 mr-1', opConfig(rejectTarget.operation_type).color]">
                                <component :is="opConfig(rejectTarget.operation_type).icon" class="h-3.5 w-3.5" />
                                {{ rejectTarget.operation_label }}
                            </span>
                            từ {{ rejectTarget.requester_name }}
                        </p>
                    </div>
                    <button @click="closeReject" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Lý do từ chối <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        placeholder="Nhập lý do từ chối cụ thể để nhân viên hiểu và khắc phục..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none resize-none"
                        :class="{ 'border-rose-400': rejectForm.errors.rejection_reason }"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-rose-500">{{ rejectForm.errors.rejection_reason }}</p>
                </div>

                <!-- Quick reason chips -->
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <button
                        v-for="reason in ['Thiếu chứng từ', 'Số lượng không hợp lý', 'Sai đơn giá', 'Không đúng thời điểm']"
                        :key="reason"
                        type="button"
                        @click="rejectForm.rejection_reason = reason"
                        class="px-2.5 py-1 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-rose-50 dark:hover:bg-rose-900/30 hover:text-rose-600 transition-colors border border-gray-200 dark:border-gray-600"
                    >{{ reason }}</button>
                </div>

                <div class="flex gap-2 mt-5">
                    <button
                        @click="submitReject"
                        :disabled="rejectForm.processing"
                        class="flex-1 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-60 text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        {{ rejectForm.processing ? 'Đang xử lý...' : 'Xác nhận từ chối' }}
                    </button>
                    <button @click="closeReject" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Hủy
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
