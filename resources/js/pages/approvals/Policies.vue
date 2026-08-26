<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Ban,
    CalendarClock,
    Check,
    CircleDollarSign,
    Clock3,
    FileCheck2,
    GitBranch,
    History,
    LockKeyhole,
    Plus,
    RotateCcw,
    Save,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Trash2,
    UserRound,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Policy = {
    id: number;
    operation_type: string;
    operation_label: string;
    branch_id: number | null;
    branch_name: string | null;
    manager_can_approve: boolean;
    manager_limit_amount: number | null;
    manager_daily_limit: number | null;
    manager_monthly_limit: number | null;
    requires_owner_countersign: boolean;
    auto_escalate_after_minutes: number | null;
    conditions: Record<string, unknown> | null;
    is_active: boolean;
    updated_at: string | null;
    updated_by_name: string | null;
};

type PolicyRow = Omit<Policy, 'id' | 'updated_at' | 'updated_by_name'> & {
    id: number;
};

type Delegation = {
    id: number;
    delegator_name: string | null;
    delegatee_id: number;
    delegatee_name: string | null;
    module: string;
    max_amount_limit: number | null;
    start_date: string;
    end_date: string;
    is_active: boolean;
    is_valid_now: boolean;
    reason: string | null;
};

type Summary = {
    total: number;
    active: number;
    manager_enabled: number;
    branch_overrides: number;
    countersign: number;
    auto_escalation: number;
    forbidden: number;
    last_updated_at: string | null;
};

const props = defineProps<{
    policies: Policy[];
    summary: Summary;
    branches: { id: number; name: string }[];
    delegations: Delegation[];
    eligibleManagers: { id: number; name: string; branch_id: number | null }[];
    delegationModules: { value: string; label: string }[];
    forbiddenForManager: { operation_type: string; operation_label: string }[];
}>();

const conditionLabels: Record<string, string> = {
    kitchen_not_started: 'Chỉ khi bếp chưa bắt đầu chế biến',
};

const groupDefinitions = [
    {
        key: 'sales',
        label: 'Bán hàng & hoàn tiền',
        description:
            'Hoàn tiền, hủy món và các quyết định tác động trực tiếp đến doanh thu.',
    },
    {
        key: 'inventory',
        label: 'Kho & tồn',
        description: 'Nhập, xuất, điều chỉnh, kiểm kê và công thức định lượng.',
    },
    {
        key: 'supply',
        label: 'Cung ứng & kho tổng',
        description:
            'Yêu cầu cung ứng, cấp phát và điều phối giữa các chi nhánh.',
    },
    {
        key: 'people',
        label: 'Nhân sự & ca làm',
        description:
            'Chấm công, lương và các thay đổi ảnh hưởng đến nhân viên.',
    },
    {
        key: 'other',
        label: 'Luồng khác',
        description: 'Các thao tác chưa được xếp vào nhóm nghiệp vụ chính.',
    },
];

function groupKey(operationType: string): string {
    if (operationType.startsWith('order_')) {
        return 'sales';
    }

    if (
        operationType.startsWith('inventory_') ||
        operationType.includes('recipe')
    ) {
        return 'inventory';
    }

    if (
        operationType.startsWith('warehouse_') ||
        operationType === 'supply_request'
    ) {
        return 'supply';
    }

    if (
        operationType.startsWith('shift_') ||
        operationType.startsWith('salary_') ||
        operationType.startsWith('employee_')
    ) {
        return 'people';
    }

    return 'other';
}

function rowFromPolicy(
    policy: Policy,
    branchId: number | null = policy.branch_id,
): PolicyRow {
    return {
        id: policy.id,
        operation_type: policy.operation_type,
        operation_label: policy.operation_label,
        branch_id: branchId,
        branch_name:
            branchId === policy.branch_id
                ? policy.branch_name
                : (props.branches.find((branch) => branch.id === branchId)
                      ?.name ?? null),
        manager_can_approve: policy.manager_can_approve,
        manager_limit_amount: policy.manager_limit_amount,
        manager_daily_limit: policy.manager_daily_limit,
        manager_monthly_limit: policy.manager_monthly_limit,
        requires_owner_countersign: policy.requires_owner_countersign,
        auto_escalate_after_minutes: policy.auto_escalate_after_minutes,
        conditions: policy.conditions,
        is_active: policy.is_active,
    };
}

const form = useForm<{ policies: PolicyRow[] }>({
    policies: props.policies.map((policy) => rowFromPolicy(policy)),
});
const initialSnapshot = ref(JSON.stringify(form.policies));
const activeTab = ref<'matrix' | 'delegations'>('matrix');
const scopeMode = ref<'chain' | 'branch'>('chain');
const selectedBranchId = ref<number | null>(null);
const search = ref('');
const riskFilter = ref('all');
const selectedOperation = ref(props.policies[0]?.operation_type ?? '');
const previewAmount = ref<number | null>(null);

const delegationForm = useForm({
    delegatee_id: null as number | null,
    module: 'all',
    max_amount_limit: null as number | null,
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    reason: '',
});

const isDirty = computed(
    () => JSON.stringify(form.policies) !== initialSnapshot.value,
);
const chainRows = computed(() =>
    form.policies.filter((row: PolicyRow) => row.branch_id === null),
);
const currentBranch = computed(
    () =>
        props.branches.find((branch) => branch.id === selectedBranchId.value) ??
        null,
);
const branchRows = computed(() =>
    form.policies.filter(
        (row: PolicyRow) => row.branch_id === selectedBranchId.value,
    ),
);
const sourceRows = computed(() =>
    scopeMode.value === 'chain' ? chainRows.value : branchRows.value,
);

const visibleRows = computed(() =>
    sourceRows.value.filter((row: PolicyRow) => {
        const term = search.value.trim().toLowerCase();
        const matchesSearch =
            !term ||
            row.operation_label.toLowerCase().includes(term) ||
            row.operation_type.toLowerCase().includes(term);
        const matchesRisk =
            riskFilter.value === 'all' ||
            (riskFilter.value === 'manager' && row.manager_can_approve) ||
            (riskFilter.value === 'owner' && !row.manager_can_approve) ||
            (riskFilter.value === 'countersign' &&
                row.requires_owner_countersign) ||
            (riskFilter.value === 'escalation' &&
                row.auto_escalate_after_minutes !== null) ||
            (riskFilter.value === 'inactive' && !row.is_active);

        return matchesSearch && matchesRisk;
    }),
);

const groupedRows = computed(() =>
    groupDefinitions
        .map((group) => ({
            ...group,
            rows: visibleRows.value.filter(
                (row: PolicyRow) => groupKey(row.operation_type) === group.key,
            ),
        }))
        .filter((group) => group.rows.length > 0),
);

const previewPolicy = computed(() => {
    if (!selectedOperation.value) {
        return null;
    }

    if (scopeMode.value === 'branch' && selectedBranchId.value !== null) {
        return (
            form.policies.find(
                (row: PolicyRow) =>
                    row.operation_type === selectedOperation.value &&
                    row.branch_id === selectedBranchId.value,
            ) ??
            chainRows.value.find(
                (row: PolicyRow) =>
                    row.operation_type === selectedOperation.value,
            ) ??
            null
        );
    }

    return (
        chainRows.value.find(
            (row: PolicyRow) => row.operation_type === selectedOperation.value,
        ) ?? null
    );
});

const previewDecision = computed(() => {
    const policy = previewPolicy.value;

    if (!policy) {
        return {
            label: 'Chưa có chính sách',
            tone: 'muted',
            detail: 'Yêu cầu sẽ đi theo cơ chế an toàn: Chủ doanh nghiệp xử lý.',
        };
    }

    if (
        !policy.is_active ||
        isForbidden(policy.operation_type) ||
        !policy.manager_can_approve
    ) {
        return {
            label: 'Chủ doanh nghiệp duyệt',
            tone: 'owner',
            detail: 'Luồng này không giao trực tiếp cho Quản lý.',
        };
    }

    if (
        previewAmount.value !== null &&
        policy.manager_limit_amount !== null &&
        previewAmount.value > policy.manager_limit_amount
    ) {
        return {
            label: 'Tự động chuyển lên Chủ',
            tone: 'warning',
            detail: 'Giá trị vượt hạn mức mỗi lần của Quản lý.',
        };
    }

    return {
        label: 'Quản lý chi nhánh duyệt',
        tone: 'manager',
        detail: policy.requires_owner_countersign
            ? 'Sau khi duyệt sẽ tạo mục hậu kiểm cho Chủ.'
            : 'Trong phạm vi chi nhánh và hạn mức đã cấu hình.',
    };
});

const moduleLabel = (module: string) =>
    props.delegationModules.find((item) => item.value === module)?.label ??
    module;

function isForbidden(operationType: string): boolean {
    return props.forbiddenForManager.some(
        (item) => item.operation_type === operationType,
    );
}

function conditionText(row: PolicyRow): string | null {
    const active = Object.entries(row.conditions ?? {})
        .filter(([, value]) => value === true)
        .map(([key]) => conditionLabels[key] ?? key);

    return active.length > 0 ? active.join(' · ') : null;
}

function formatMoney(value: number | null): string {
    if (value === null || value === undefined) {
        return 'Không giới hạn';
    }

    return `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Chưa ghi nhận';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(new Date(value));
}

function toggleManager(row: PolicyRow): void {
    if (isForbidden(row.operation_type)) {
        row.manager_can_approve = false;
    }

    if (!row.manager_can_approve) {
        row.manager_limit_amount = null;
        row.manager_daily_limit = null;
        row.manager_monthly_limit = null;
        row.requires_owner_countersign = false;
        row.auto_escalate_after_minutes = null;
    }
}

function createBranchOverride(): void {
    if (!selectedBranchId.value) {
        toast.error('Hãy chọn chi nhánh trước khi tạo cấu hình ghi đè.');

        return;
    }

    const existing = new Set(
        branchRows.value.map((row: PolicyRow) => row.operation_type),
    );
    chainRows.value.forEach((baseRow: PolicyRow) => {
        if (!existing.has(baseRow.operation_type)) {
            form.policies.push(
                rowFromPolicy(
                    { ...baseRow, id: 0 } as Policy,
                    selectedBranchId.value,
                ),
            );
        }
    });
    scopeMode.value = 'branch';
    toast.success(
        `Đã tạo bản nháp cấu hình cho ${currentBranch.value?.name ?? 'chi nhánh'}.`,
    );
}

function removeBranchOverride(row: PolicyRow): void {
    if (!row.id || row.branch_id === null) {
        const index = form.policies.indexOf(row);

        if (index >= 0) {
            form.policies.splice(index, 1);
        }

        return;
    }

    router.delete(`/approvals/policies/${row.id}`, {
        preserveScroll: true,
        onSuccess: () =>
            toast.success(
                'Đã xóa cấu hình ghi đè; chi nhánh sẽ dùng chính sách toàn chuỗi.',
            ),
        onError: () => toast.error('Không thể xóa cấu hình ghi đè.'),
    });
}

function resetChanges(): void {
    form.policies = props.policies.map((policy) => rowFromPolicy(policy));
    toast.info('Đã hoàn tác các thay đổi chưa lưu.');
}

function save(): void {
    form.put('/approvals/policies', {
        preserveScroll: true,
        onSuccess: () => {
            initialSnapshot.value = JSON.stringify(form.policies);
            toast.success('Đã cập nhật ma trận thẩm quyền và ghi audit.');
        },
        onError: () =>
            toast.error(
                'Không lưu được. Kiểm tra lại các hạn mức và phạm vi chi nhánh.',
            ),
    });
}

function storeDelegation(): void {
    delegationForm.post('/approvals/delegations', {
        preserveScroll: true,
        onSuccess: () => {
            delegationForm.reset();
            toast.success('Đã tạo ủy quyền phê duyệt có thời hạn.');
        },
        onError: () =>
            toast.error(
                'Không tạo được ủy quyền. Kiểm tra người nhận và thời hạn.',
            ),
    });
}

function revokeDelegation(delegation: Delegation): void {
    router.delete(`/approvals/delegations/${delegation.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã thu hồi ủy quyền.'),
        onError: () => toast.error('Không thể thu hồi ủy quyền.'),
    });
}
</script>

<template>
    <Head title="Thẩm quyền phê duyệt" />

    <div class="mx-auto flex w-full max-w-[1480px] flex-col gap-5 p-4 sm:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-indigo-200/80 bg-gradient-to-r from-indigo-50/70 via-slate-50 to-indigo-100/40 p-6 text-slate-900 shadow-sm dark:border-indigo-400/20 dark:bg-[radial-gradient(circle_at_top_right,_rgba(79,70,229,0.32),_transparent_48%),linear-gradient(135deg,_#101827,_#080b12_62%)] dark:text-white dark:shadow-2xl sm:p-8"
        >
            <div
                class="absolute -top-16 -right-12 size-48 rounded-full bg-indigo-500/10 blur-3xl"
            />
            <div
                class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-3xl">
                    <div
                        class="mb-4 flex items-center gap-3 text-xs font-bold tracking-[0.2em] uppercase text-indigo-700 dark:text-indigo-200/80"
                    >
                        <span
                            class="rounded-full border border-indigo-300/40 bg-indigo-500/10 px-3 py-1 dark:border-indigo-300/20 dark:bg-indigo-300/10"
                            >Governance cockpit</span
                        >
                        <span
                            class="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-300"
                            ><span
                                class="size-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"
                            />
                            Đang kiểm soát</span
                        >
                    </div>
                    <div class="flex items-start gap-4">
                        <span
                            class="flex size-14 shrink-0 items-center justify-center rounded-2xl border border-indigo-200 bg-indigo-500/10 text-indigo-600 shadow-sm dark:border-indigo-300/20 dark:bg-indigo-500/20 dark:text-indigo-200 dark:shadow-lg dark:shadow-indigo-950/50"
                        >
                            <SlidersHorizontal class="size-7" />
                        </span>
                        <div>
                            <h1
                                class="text-2xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                            >
                                Thẩm quyền phê duyệt
                            </h1>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300"
                            >
                                Thiết kế rõ ai được quyết định, trong phạm vi
                                nào, và khi nào yêu cầu phải chuyển lên Chủ
                                doanh nghiệp.
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    class="min-w-[250px] rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-black/20"
                >
                    <div
                        class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400"
                    >
                        <span>Cấu hình gần nhất</span><History class="size-4" />
                    </div>
                    <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">
                        {{ formatDate(summary.last_updated_at) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ summary.total }} luồng toàn chuỗi ·
                        {{ summary.branch_overrides }} ghi đè chi nhánh
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-border/80 bg-card p-4">
                <div
                    class="flex items-center justify-between text-xs font-semibold text-muted-foreground"
                >
                    <span>Luồng đang quản trị</span
                    ><FileCheck2 class="size-4 text-indigo-400" />
                </div>
                <p class="mt-3 text-2xl font-black">
                    {{ summary.active
                    }}<span class="text-sm font-medium text-muted-foreground">
                        / {{ summary.total }}</span
                    >
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Chính sách toàn chuỗi
                </p>
            </div>
            <div
                class="rounded-2xl border border-emerald-500/20 bg-emerald-500/[0.04] p-4"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-emerald-300"
                >
                    <span>Giao Quản lý</span><UsersRound class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-black text-emerald-300">
                    {{ summary.manager_enabled }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Có thể duyệt trực tiếp
                </p>
            </div>
            <div
                class="rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-amber-300"
                >
                    <span>Hậu kiểm Chủ</span><ShieldCheck class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-black text-amber-300">
                    {{ summary.countersign }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Duyệt xong phải ký hậu kiểm
                </p>
            </div>
            <div
                class="rounded-2xl border border-sky-500/20 bg-sky-500/[0.04] p-4"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-sky-300"
                >
                    <span>Tự động leo thang</span><Clock3 class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-black text-sky-300">
                    {{ summary.auto_escalation }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Có SLA chuyển lên Chủ
                </p>
            </div>
            <div
                class="rounded-2xl border border-rose-500/20 bg-rose-500/[0.04] p-4"
            >
                <div
                    class="flex items-center justify-between text-xs font-semibold text-rose-300"
                >
                    <span>Ranh giới cứng</span><Ban class="size-4" />
                </div>
                <p class="mt-3 text-2xl font-black text-rose-300">
                    {{ summary.forbidden }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Không thể giao cho Quản lý
                </p>
            </div>
        </section>

        <div
            class="flex flex-col gap-3 border-b border-border/70 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex gap-1 overflow-x-auto">
                <button
                    class="border-b-2 px-4 py-3 text-sm font-bold whitespace-nowrap transition"
                    :class="
                        activeTab === 'matrix'
                            ? 'border-indigo-500 text-indigo-300'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'matrix'"
                >
                    <SlidersHorizontal class="mr-2 inline size-4" /> Ma trận
                    thẩm quyền
                </button>
                <button
                    class="border-b-2 px-4 py-3 text-sm font-bold whitespace-nowrap transition"
                    :class="
                        activeTab === 'delegations'
                            ? 'border-indigo-500 text-indigo-300'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'delegations'"
                >
                    <CalendarClock class="mr-2 inline size-4" /> Ủy quyền có
                    thời hạn
                    <span
                        v-if="
                            delegations.filter((item) => item.is_valid_now)
                                .length
                        "
                        class="ml-1 rounded-full bg-indigo-500/20 px-1.5 py-0.5 text-[10px]"
                        >{{
                            delegations.filter((item) => item.is_valid_now)
                                .length
                        }}</span
                    >
                </button>
            </div>
            <div
                v-if="activeTab === 'matrix' && isDirty"
                class="mb-2 flex items-center gap-2 text-xs font-semibold text-amber-300 sm:mb-0"
            >
                <AlertTriangle class="size-4" /> Có thay đổi chưa lưu
            </div>
        </div>

        <template v-if="activeTab === 'matrix'">
            <section
                class="rounded-2xl border border-border/80 bg-card/70 p-4 shadow-sm sm:p-5"
            >
                <div
                    class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div>
                        <h2 class="text-base font-bold">Phạm vi áp dụng</h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Chính sách chi nhánh sẽ ghi đè dòng toàn chuỗi khi
                            hai cấu hình cùng tồn tại.
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div
                            class="flex rounded-xl border border-border bg-muted/40 p-1"
                        >
                            <button
                                class="rounded-lg px-3 py-2 text-xs font-bold transition"
                                :class="
                                    scopeMode === 'chain'
                                        ? 'bg-indigo-600 text-white shadow'
                                        : 'text-muted-foreground'
                                "
                                @click="scopeMode = 'chain'"
                            >
                                Toàn chuỗi
                            </button>
                            <button
                                class="rounded-lg px-3 py-2 text-xs font-bold transition"
                                :class="
                                    scopeMode === 'branch'
                                        ? 'bg-indigo-600 text-white shadow'
                                        : 'text-muted-foreground'
                                "
                                @click="scopeMode = 'branch'"
                            >
                                Theo chi nhánh
                            </button>
                        </div>
                        <select
                            v-model="selectedBranchId"
                            class="h-10 min-w-[190px] rounded-xl border border-border bg-background px-3 text-sm"
                            :disabled="scopeMode !== 'branch'"
                        >
                            <option :value="null">Chọn chi nhánh</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                        <button
                            v-if="scopeMode === 'branch' && selectedBranchId"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-3 text-xs font-bold text-indigo-300 hover:bg-indigo-500/20"
                            @click="createBranchOverride"
                        >
                            <Plus class="size-4" /> Tạo bản ghi đè
                        </button>
                    </div>
                </div>
                <div
                    v-if="
                        scopeMode === 'branch' &&
                        selectedBranchId &&
                        branchRows.length === 0
                    "
                    class="mt-4 flex items-center justify-between rounded-xl border border-dashed border-amber-500/30 bg-amber-500/[0.04] p-4 text-sm"
                >
                    <span class="text-muted-foreground"
                        >{{ currentBranch?.name }} đang dùng toàn bộ chính sách
                        toàn chuỗi.</span
                    ><button
                        class="font-bold text-amber-300 hover:text-amber-200"
                        @click="createBranchOverride"
                    >
                        Tạo cấu hình riêng
                        <ArrowUpRight class="ml-1 inline size-4" />
                    </button>
                </div>
            </section>

            <section
                class="flex flex-col gap-3 rounded-2xl border border-border/80 bg-card/70 p-4 shadow-sm lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="relative w-full lg:max-w-md">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    /><input
                        v-model="search"
                        class="h-10 w-full rounded-xl border border-border bg-background pr-3 pl-9 text-sm ring-indigo-500/30 transition outline-none focus:ring-2"
                        placeholder="Tìm theo tên thao tác..."
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <select
                        v-model="riskFilter"
                        class="h-10 rounded-xl border border-border bg-background px-3 text-xs font-semibold"
                    >
                        <option value="all">Tất cả luồng</option>
                        <option value="manager">Quản lý được duyệt</option>
                        <option value="owner">Chỉ Chủ duyệt</option>
                        <option value="countersign">Có hậu kiểm Chủ</option>
                        <option value="escalation">Có tự động leo thang</option>
                        <option value="inactive">Đang tắt</option>
                    </select>
                    <button
                        v-if="isDirty"
                        class="inline-flex h-10 items-center gap-2 rounded-xl border border-border px-3 text-xs font-bold text-muted-foreground hover:text-foreground"
                        @click="resetChanges"
                    >
                        <RotateCcw class="size-4" /> Hoàn tác
                    </button>
                    <button
                        class="inline-flex h-10 items-center gap-2 rounded-xl bg-indigo-600 px-4 text-xs font-black text-white shadow-lg shadow-indigo-950/30 hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || !isDirty"
                        @click="save"
                    >
                        <Save class="size-4" />
                        {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </button>
                </div>
            </section>

            <section
                v-if="visibleRows.length === 0"
                class="rounded-2xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
            >
                Không có luồng nào phù hợp với bộ lọc hiện tại.
            </section>

            <section
                v-for="group in groupedRows"
                :key="group.key"
                class="overflow-hidden rounded-2xl border border-border/80 bg-card shadow-sm"
            >
                <div class="border-b border-border/70 bg-muted/20 px-5 py-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-black">
                                {{ group.label }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ group.description }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2.5 py-1 text-xs font-bold text-muted-foreground"
                            >{{ group.rows.length }} luồng</span
                        >
                    </div>
                </div>
                <div
                    class="hidden grid-cols-[minmax(240px,1.6fr)_130px_repeat(3,minmax(120px,0.8fr))_140px_90px_42px] gap-3 border-b border-border/60 bg-muted/10 px-5 py-3 text-[10px] font-black tracking-widest text-muted-foreground uppercase xl:grid"
                >
                    <span>Loại thao tác</span><span>Quản lý duyệt</span
                    ><span>Mỗi lần</span><span>Mỗi ngày</span
                    ><span>Mỗi tháng</span><span>Tự chuyển Chủ</span
                    ><span>Hậu kiểm</span><span />
                </div>
                <div class="divide-y divide-border/60">
                    <div
                        v-for="row in group.rows"
                        :key="`${row.operation_type}-${row.branch_id ?? 'chain'}`"
                        class="px-4 py-4 transition hover:bg-muted/20 sm:px-5"
                    >
                        <div
                            class="grid gap-4 xl:grid-cols-[minmax(240px,1.6fr)_130px_repeat(3,minmax(120px,0.8fr))_140px_90px_42px] xl:items-center xl:gap-3"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-sm font-black">{{
                                        row.operation_label
                                    }}</span
                                    ><span
                                        v-if="!row.is_active"
                                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold text-muted-foreground"
                                        >Đang tắt</span
                                    ><span
                                        v-if="isForbidden(row.operation_type)"
                                        class="rounded-full bg-rose-500/10 px-2 py-0.5 text-[10px] font-bold text-rose-300"
                                        >Chủ only</span
                                    >
                                </div>
                                <p
                                    v-if="conditionText(row)"
                                    class="mt-1 text-[11px] font-medium text-amber-300"
                                >
                                    <AlertTriangle
                                        class="mr-1 inline size-3"
                                    />{{ conditionText(row) }}
                                </p>
                                <p
                                    v-if="scopeMode === 'branch'"
                                    class="mt-1 text-[11px] text-indigo-300"
                                >
                                    <GitBranch class="mr-1 inline size-3" />{{
                                        row.branch_name ?? currentBranch?.name
                                    }}
                                </p>
                                <p
                                    v-else
                                    class="mt-1 text-[11px] text-muted-foreground"
                                >
                                    Áp dụng mặc định toàn chuỗi
                                </p>
                            </div>
                            <label
                                class="flex items-center gap-2 text-xs font-bold text-muted-foreground xl:block"
                                ><span class="mr-2 xl:hidden"
                                    >Quản lý duyệt</span
                                ><input
                                    v-model="row.manager_can_approve"
                                    type="checkbox"
                                    :disabled="isForbidden(row.operation_type)"
                                    class="size-4 rounded border-border accent-emerald-500 disabled:opacity-40"
                                    @change="toggleManager(row)"
                                /><span class="ml-1 text-emerald-300">{{
                                    row.manager_can_approve ? 'Bật' : 'Tắt'
                                }}</span></label
                            >
                            <label class="xl:block"
                                ><span
                                    class="mb-1 block text-[10px] font-bold text-muted-foreground uppercase xl:hidden"
                                    >Mỗi lần</span
                                >
                                <div class="relative">
                                    <input
                                        v-model.number="
                                            row.manager_limit_amount
                                        "
                                        type="number"
                                        min="0"
                                        placeholder="Không giới hạn"
                                        :disabled="!row.manager_can_approve"
                                        class="h-9 w-full rounded-lg border border-border bg-background px-2 text-right text-xs tabular-nums outline-none focus:border-indigo-500 disabled:opacity-40"
                                    /><CircleDollarSign
                                        class="pointer-events-none absolute top-1/2 left-2 size-3 -translate-y-1/2 text-muted-foreground"
                                    /></div
                            ></label>
                            <label class="xl:block"
                                ><span
                                    class="mb-1 block text-[10px] font-bold text-muted-foreground uppercase xl:hidden"
                                    >Mỗi ngày</span
                                ><input
                                    v-model.number="row.manager_daily_limit"
                                    type="number"
                                    min="0"
                                    placeholder="Không giới hạn"
                                    :disabled="!row.manager_can_approve"
                                    class="h-9 w-full rounded-lg border border-border bg-background px-2 text-right text-xs tabular-nums outline-none focus:border-indigo-500 disabled:opacity-40"
                            /></label>
                            <label class="xl:block"
                                ><span
                                    class="mb-1 block text-[10px] font-bold text-muted-foreground uppercase xl:hidden"
                                    >Mỗi tháng</span
                                ><input
                                    v-model.number="row.manager_monthly_limit"
                                    type="number"
                                    min="0"
                                    placeholder="Không giới hạn"
                                    :disabled="!row.manager_can_approve"
                                    class="h-9 w-full rounded-lg border border-border bg-background px-2 text-right text-xs tabular-nums outline-none focus:border-indigo-500 disabled:opacity-40"
                            /></label>
                            <label class="xl:block"
                                ><span
                                    class="mb-1 block text-[10px] font-bold text-muted-foreground uppercase xl:hidden"
                                    >Tự chuyển Chủ sau (phút)</span
                                ><select
                                    v-model.number="
                                        row.auto_escalate_after_minutes
                                    "
                                    :disabled="!row.manager_can_approve"
                                    class="h-9 w-full rounded-lg border border-border bg-background px-2 text-xs outline-none focus:border-indigo-500 disabled:opacity-40"
                                >
                                    <option :value="null">Không đặt SLA</option>
                                    <option :value="15">15 phút</option>
                                    <option :value="30">30 phút</option>
                                    <option :value="60">1 giờ</option>
                                    <option :value="120">2 giờ</option>
                                    <option :value="240">4 giờ</option>
                                    <option :value="480">8 giờ</option>
                                    <option :value="1440">24 giờ</option>
                                </select></label
                            >
                            <label
                                class="flex items-center gap-2 text-xs font-bold text-muted-foreground xl:block"
                                ><span class="mr-2 xl:hidden">Hậu kiểm Chủ</span
                                ><input
                                    v-model="row.requires_owner_countersign"
                                    type="checkbox"
                                    :disabled="!row.manager_can_approve"
                                    class="size-4 rounded border-border accent-amber-500 disabled:opacity-40"
                                /><span class="ml-1 text-amber-300">{{
                                    row.requires_owner_countersign
                                        ? 'Có'
                                        : 'Không'
                                }}</span></label
                            >
                            <button
                                v-if="scopeMode === 'branch'"
                                class="inline-flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-rose-500/10 hover:text-rose-300"
                                title="Xóa cấu hình ghi đè"
                                @click="removeBranchOverride(row)"
                            >
                                <Trash2 class="size-4" /></button
                            ><span v-else />
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <div
                    class="rounded-2xl border border-rose-500/20 bg-rose-500/[0.03] p-5"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2
                                class="flex items-center gap-2 text-base font-black text-rose-200"
                            >
                                <Ban class="size-4" /> Ranh giới không thể giao
                            </h2>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                Các thao tác này luôn yêu cầu Chủ doanh nghiệp.
                                Backend cũng chặn lại nếu payload bị sửa ngoài
                                giao diện.
                            </p>
                        </div>
                        <LockKeyhole class="size-5 shrink-0 text-rose-300" />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="item in forbiddenForManager"
                            :key="item.operation_type"
                            class="rounded-lg border border-rose-500/20 bg-rose-950/20 px-2.5 py-1.5 text-xs font-semibold text-rose-200"
                            >{{ item.operation_label }}</span
                        >
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-indigo-500/20 bg-indigo-500/[0.04] p-5"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-black">
                                Quyết định sẽ đi đâu?
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Mô phỏng nhanh trước khi lưu.
                            </p>
                        </div>
                        <ArrowUpRight class="size-5 text-indigo-300" />
                    </div>
                    <div class="mt-4 grid gap-3">
                        <select
                            v-model="selectedOperation"
                            class="h-9 rounded-lg border border-border bg-background px-2 text-xs"
                        >
                            <option
                                v-for="row in chainRows"
                                :key="row.operation_type"
                                :value="row.operation_type"
                            >
                                {{ row.operation_label }}
                            </option></select
                        ><input
                            v-model.number="previewAmount"
                            type="number"
                            min="0"
                            placeholder="Giá trị giao dịch (để kiểm tra hạn mức)"
                            class="h-9 rounded-lg border border-border bg-background px-2 text-xs"
                        />
                        <div
                            class="rounded-xl border p-3"
                            :class="
                                previewDecision.tone === 'manager'
                                    ? 'border-emerald-500/25 bg-emerald-500/[0.06]'
                                    : previewDecision.tone === 'warning'
                                      ? 'border-amber-500/25 bg-amber-500/[0.06]'
                                      : 'border-rose-500/20 bg-rose-500/[0.04]'
                            "
                        >
                            <div
                                class="flex items-center gap-2 text-sm font-black"
                            >
                                <Check
                                    v-if="previewDecision.tone === 'manager'"
                                    class="size-4 text-emerald-300"
                                /><AlertTriangle
                                    v-else
                                    class="size-4 text-amber-300"
                                />{{ previewDecision.label }}
                            </div>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                {{ previewDecision.detail }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <template v-else>
            <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div
                    class="rounded-2xl border border-border/80 bg-card p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-black">
                                Ủy quyền tạm thời
                            </h2>
                            <p
                                class="mt-1 max-w-2xl text-xs leading-5 text-muted-foreground"
                            >
                                Dùng khi Chủ vắng mặt hoặc cần phân công người
                                thay thế. Ủy quyền vẫn chịu ranh giới cứng, phạm
                                vi chi nhánh và hạn mức ma trận.
                            </p>
                        </div>
                        <CalendarClock class="size-6 text-indigo-300" />
                    </div>
                    <div class="mt-5 overflow-x-auto">
                        <table class="w-full min-w-[680px] text-sm">
                            <thead
                                class="border-b border-border text-left text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="pb-3">Người nhận</th>
                                    <th class="pb-3">Phạm vi</th>
                                    <th class="pb-3">Thời hạn</th>
                                    <th class="pb-3">Giới hạn</th>
                                    <th class="pb-3">Trạng thái</th>
                                    <th class="pb-3" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/70">
                                <tr
                                    v-for="delegation in delegations"
                                    :key="delegation.id"
                                >
                                    <td class="py-4">
                                        <div class="font-bold">
                                            {{ delegation.delegatee_name }}
                                        </div>
                                        <div
                                            class="mt-1 text-[11px] text-muted-foreground"
                                        >
                                            Giao bởi
                                            {{ delegation.delegator_name }}
                                        </div>
                                    </td>
                                    <td class="py-4 text-xs">
                                        {{ moduleLabel(delegation.module) }}
                                    </td>
                                    <td class="py-4 text-xs tabular-nums">
                                        {{ formatDate(delegation.start_date) }}
                                        — {{ formatDate(delegation.end_date) }}
                                    </td>
                                    <td class="py-4 text-xs font-bold">
                                        {{
                                            formatMoney(
                                                delegation.max_amount_limit,
                                            )
                                        }}
                                    </td>
                                    <td class="py-4">
                                        <span
                                            class="rounded-full px-2 py-1 text-[10px] font-black"
                                            :class="
                                                delegation.is_valid_now
                                                    ? 'bg-emerald-500/10 text-emerald-300'
                                                    : 'bg-muted text-muted-foreground'
                                            "
                                            >{{
                                                delegation.is_valid_now
                                                    ? 'Đang hiệu lực'
                                                    : delegation.is_active
                                                      ? 'Chưa/đã hết hạn'
                                                      : 'Đã thu hồi'
                                            }}</span
                                        >
                                    </td>
                                    <td class="py-4 text-right">
                                        <button
                                            v-if="delegation.is_active"
                                            class="rounded-lg p-2 text-muted-foreground hover:bg-rose-500/10 hover:text-rose-300"
                                            title="Thu hồi"
                                            @click="
                                                revokeDelegation(delegation)
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="delegations.length === 0">
                                    <td
                                        colspan="6"
                                        class="py-10 text-center text-sm text-muted-foreground"
                                    >
                                        Chưa có ủy quyền nào. Các quyết định sẽ
                                        theo ma trận thẩm quyền hiện tại.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-indigo-500/20 bg-indigo-500/[0.04] p-5"
                >
                    <div class="flex items-center gap-2">
                        <Plus class="size-5 text-indigo-300" />
                        <h2 class="text-base font-black">Tạo ủy quyền</h2>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <label class="grid gap-1 text-xs font-bold"
                            >Người nhận<select
                                v-model="delegationForm.delegatee_id"
                                class="h-10 rounded-xl border border-border bg-background px-3 text-sm"
                            >
                                <option :value="null">
                                    Chọn Quản lý chi nhánh
                                </option>
                                <option
                                    v-for="manager in eligibleManagers"
                                    :key="manager.id"
                                    :value="manager.id"
                                >
                                    {{ manager.name }}
                                </option>
                            </select></label
                        ><label class="grid gap-1 text-xs font-bold"
                            >Phạm vi<select
                                v-model="delegationForm.module"
                                class="h-10 rounded-xl border border-border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="module in delegationModules"
                                    :key="module.value"
                                    :value="module.value"
                                >
                                    {{ module.label }}
                                </option>
                            </select></label
                        ><label class="grid gap-1 text-xs font-bold"
                            >Hạn mức ủy quyền<input
                                v-model.number="delegationForm.max_amount_limit"
                                type="number"
                                min="0"
                                placeholder="Không giới hạn riêng"
                                class="h-10 rounded-xl border border-border bg-background px-3 text-sm"
                        /></label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="grid gap-1 text-xs font-bold"
                                >Từ ngày<input
                                    v-model="delegationForm.start_date"
                                    type="date"
                                    class="h-10 rounded-xl border border-border bg-background px-3 text-sm" /></label
                            ><label class="grid gap-1 text-xs font-bold"
                                >Đến ngày<input
                                    v-model="delegationForm.end_date"
                                    type="date"
                                    class="h-10 rounded-xl border border-border bg-background px-3 text-sm"
                            /></label>
                        </div>
                        <label class="grid gap-1 text-xs font-bold"
                            >Lý do<textarea
                                v-model="delegationForm.reason"
                                rows="3"
                                maxlength="500"
                                placeholder="Ví dụ: Chủ đi công tác..."
                                class="rounded-xl border border-border bg-background px-3 py-2 text-sm"
                            /></label
                        ><button
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-black text-white hover:bg-indigo-500 disabled:opacity-50"
                            :disabled="delegationForm.processing"
                            @click="storeDelegation"
                        >
                            <CalendarClock class="size-4" /> Tạo ủy quyền
                        </button>
                        <p class="text-[11px] leading-5 text-muted-foreground">
                            <UserRound class="mr-1 inline size-3" /> Người nhận
                            vẫn phải là Quản lý chi nhánh và không thể tự duyệt
                            yêu cầu của chính mình.
                        </p>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
