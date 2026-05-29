<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ────────────────────────────────────────────────────────────────────

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

function toggleExpand(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ── Status config ─────────────────────────────────────────────────────────────

const statusConfig: Record<ApprovalStatus, { label: string; badgeClass: string; dotClass: string }> = {
    pending:  { label: 'Chờ duyệt',  badgeClass: 'bg-amber-100 text-amber-700 border border-amber-200',  dotClass: 'bg-amber-500 animate-pulse' },
    approved: { label: 'Đã duyệt',   badgeClass: 'bg-emerald-100 text-emerald-700 border border-emerald-200', dotClass: 'bg-emerald-500' },
    rejected: { label: 'Từ chối',    badgeClass: 'bg-rose-100 text-rose-700 border border-rose-200',      dotClass: 'bg-rose-500' },
};

const operationIcon: Record<string, string> = {
    inventory_purchase: '📦',
    inventory_waste:    '🗑️',
    salary_adjustment:  '💰',
    employee_create:    '👤',
};

// ── Filter ────────────────────────────────────────────────────────────────────

function applyFilter(status: string) {
    router.get('/approvals', { status }, { preserveState: true, replace: true });
}

// ── Approve ───────────────────────────────────────────────────────────────────

function approveRequest(approval: Approval) {
    router.patch(`/approvals/${approval.id}/approve`, {}, {
        onSuccess: () => toast.success('Đã phê duyệt yêu cầu.'),
        onError:   () => toast.error('Có lỗi khi phê duyệt.'),
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
        onSuccess: () => {
            toast.success('Đã từ chối yêu cầu.');
            closeReject();
        },
        onError: () => toast.error('Vui lòng nhập lý do từ chối.'),
    });
}

// ── Operation data display ─────────────────────────────────────────────────────

function formatDataEntry(key: string, value: unknown): string {
    const labels: Record<string, string> = {
        ingredient_id:  'Nguyên liệu ID',
        quantity:       'Số lượng',
        unit_cost:      'Đơn giá',
        supplier_id:    'Nhà cung cấp ID',
        notes:          'Ghi chú',
        occurred_at:    'Ngày nhập',
        employee_id:    'Nhân viên ID',
        type:           'Loại điều chỉnh',
        amount:         'Số tiền',
        reason:         'Lý do',
        salary_id:      'Bảng lương ID',
    };
    const label = labels[key] ?? key;
    if (value === null || value === undefined || value === '') return `${label}: —`;
    if (typeof value === 'number' && key.includes('cost') || key === 'amount') {
        return `${label}: ${Number(value).toLocaleString('vi-VN')}đ`;
    }
    return `${label}: ${value}`;
}
</script>

<template>
    <Head title="Phê duyệt" />

    <div class="space-y-6 p-4 md:p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <ShieldCheck class="h-7 w-7 text-violet-600" />
                    Kiểm duyệt chéo
                </h1>
                <p class="text-sm text-gray-500 mt-1">Phê duyệt các thao tác tài chính từ nhân viên trước khi có hiệu lực</p>
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
                v-for="f in [{ value: 'pending', label: 'Chờ duyệt' }, { value: 'approved', label: 'Đã duyệt' }, { value: 'rejected', label: 'Từ chối' }, { value: 'all', label: 'Tất cả' }]"
                :key="f.value"
                @click="applyFilter(f.value)"
                :class="[
                    'px-4 py-1.5 rounded-full text-sm font-medium transition-colors',
                    statusFilter === f.value
                        ? 'bg-violet-600 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300',
                ]"
            >{{ f.label }}</button>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div v-if="approvals.length === 0" class="flex flex-col items-center justify-center py-16 text-gray-400">
                <ClipboardList class="h-12 w-12 mb-3 opacity-40" />
                <p class="text-sm">Không có yêu cầu nào</p>
            </div>

            <template v-else>
                <div
                    v-for="approval in approvals"
                    :key="approval.id"
                    class="border-b border-gray-100 dark:border-gray-700 last:border-0"
                >
                    <!-- Row -->
                    <div
                        class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                        @click="toggleExpand(approval.id)"
                    >
                        <!-- Icon -->
                        <span class="text-xl w-8 text-center">{{ operationIcon[approval.operation_type] ?? '📋' }}</span>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white text-sm">{{ approval.operation_label }}</p>
                            <p class="text-xs text-gray-500">{{ approval.requester_name }} · {{ approval.created_at }}</p>
                        </div>

                        <!-- Status badge -->
                        <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium', statusConfig[approval.status].badgeClass]">
                            <span :class="['h-1.5 w-1.5 rounded-full', statusConfig[approval.status].dotClass]" />
                            {{ statusConfig[approval.status].label }}
                        </span>

                        <!-- Expand icon -->
                        <ChevronDown v-if="expandedId !== approval.id" class="h-4 w-4 text-gray-400 flex-shrink-0" />
                        <ChevronUp  v-else                             class="h-4 w-4 text-gray-400 flex-shrink-0" />
                    </div>

                    <!-- Expanded detail -->
                    <div v-if="expandedId === approval.id" class="px-4 pb-4 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-100 dark:border-gray-700">
                        <!-- Operation data -->
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            <div
                                v-for="(value, key) in approval.operation_data"
                                :key="key"
                                class="text-xs text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 rounded px-3 py-1.5 border border-gray-100 dark:border-gray-600"
                            >
                                {{ formatDataEntry(String(key), value) }}
                            </div>
                        </div>

                        <!-- Reviewer info -->
                        <div v-if="approval.reviewer_name" class="mt-3 text-xs text-gray-500">
                            <template v-if="approval.status === 'approved'">
                                <CheckCircle2 class="inline h-3.5 w-3.5 text-emerald-500 mr-1" />
                                Duyệt bởi <strong>{{ approval.reviewer_name }}</strong> lúc {{ approval.reviewed_at }}
                            </template>
                            <template v-else-if="approval.status === 'rejected'">
                                <AlertTriangle class="inline h-3.5 w-3.5 text-rose-500 mr-1" />
                                Từ chối bởi <strong>{{ approval.reviewer_name }}</strong> lúc {{ approval.reviewed_at }}
                                <span v-if="approval.rejection_reason" class="block mt-1 text-rose-600 dark:text-rose-400">Lý do: {{ approval.rejection_reason }}</span>
                            </template>
                        </div>

                        <!-- Actions (only for pending) -->
                        <div v-if="approval.status === 'pending'" class="mt-4 flex gap-2">
                            <button
                                @click.stop="approveRequest(approval)"
                                class="flex items-center gap-1.5 px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm rounded-lg font-medium transition-colors"
                            >
                                <ShieldCheck class="h-4 w-4" /> Phê duyệt
                            </button>
                            <button
                                @click.stop="openReject(approval)"
                                class="flex items-center gap-1.5 px-4 py-1.5 bg-white dark:bg-gray-800 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800 text-sm rounded-lg font-medium transition-colors"
                            >
                                <ShieldX class="h-4 w-4" /> Từ chối
                            </button>
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
                        <p class="text-sm text-gray-500 mt-0.5">{{ rejectTarget.operation_label }} từ {{ rejectTarget.requester_name }}</p>
                    </div>
                    <button @click="closeReject" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lý do từ chối <span class="text-rose-500">*</span></label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        placeholder="Nhập lý do từ chối..."
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm px-3 py-2 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none resize-none"
                        :class="{ 'border-rose-400': rejectForm.errors.rejection_reason }"
                    />
                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-rose-500">{{ rejectForm.errors.rejection_reason }}</p>
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
