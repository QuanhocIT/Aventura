<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowUpRight,
    Banknote,
    CheckCircle2,
    ChevronRight,
    CircleDollarSign,
    CircleHelp,
    Edit3,
    Filter,
    PauseCircle,
    PlayCircle,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    Trash2,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type CompensationType = 'hourly' | 'shift' | 'fixed';

interface BranchRow {
    branch_id: number;
    branch_name: string;
    branch_code: string | null;
    budget_amount: number | null;
    committed: number;
    remaining: number | null;
    over_budget: boolean;
    notes: string | null;
}

interface WageTier {
    id: number;
    branch_id: number | null;
    name: string;
    compensation_type: CompensationType;
    rate: number;
    is_active: boolean;
}

const props = defineProps<{
    month: string;
    branches: BranchRow[];
    wageTiers: WageTier[];
    payrollRules: {
        hours_per_month: number;
        shifts_per_month: number;
    };
}>();

const vnd = (value: number) => `${new Intl.NumberFormat('vi-VN').format(Math.round(value))}đ`;
const compactVnd = (value: number) => {
    if (Math.abs(value) >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 1)}tr`;
    }

    return vnd(value);
};
const compLabel: Record<CompensationType, string> = {
    hourly: 'Theo giờ',
    shift: 'Theo ca',
    fixed: 'Cố định tháng',
};

const search = ref('');
const statusFilter = ref('all');
const selectedBranchId = ref<number | null>(props.branches[0]?.branch_id ?? null);
const editingTierId = ref<number | null>(null);
const showBudgetModal = ref(false);

const budgetForm = useForm({
    branch_id: selectedBranchId.value,
    budget_amount: props.branches[0]?.budget_amount ?? 0,
    notes: props.branches[0]?.notes ?? '',
});
const tierForm = useForm({
    name: '',
    compensation_type: 'fixed' as CompensationType,
    rate: 0,
    revenue_percent: null as number | null,
    branch_id: null as number | null,
    is_active: true,
});

const selectedBranch = computed(() => props.branches.find((branch) => branch.branch_id === selectedBranchId.value) ?? null);

function usagePercent(branch: BranchRow): number {
    if (branch.budget_amount === null || branch.budget_amount <= 0) {
        return 0;
    }

    return Math.round((branch.committed / branch.budget_amount) * 100);
}

function branchStatusKey(branch: BranchRow): string {
    if (branch.budget_amount === null) {
        return 'missing';
    }

    if (branch.over_budget) {
        return 'over';
    }

    if (branch.budget_amount > 0 && usagePercent(branch) >= 90) {
        return 'warning';
    }

    return 'healthy';
}

function branchStatus(branch: BranchRow): { label: string; className: string; iconClass: string } {
    const status = branchStatusKey(branch);

    if (status === 'missing') {
        return { label: 'Chưa cấp quỹ', className: 'border-slate-700 bg-slate-800/60 text-slate-300', iconClass: 'text-slate-400' };
    }

    if (status === 'over') {
        return { label: 'Vượt quỹ', className: 'border-rose-500/30 bg-rose-500/10 text-rose-300', iconClass: 'text-rose-400' };
    }

    if (status === 'warning') {
        return { label: 'Sắp chạm quỹ', className: 'border-amber-500/30 bg-amber-500/10 text-amber-300', iconClass: 'text-amber-400' };
    }

    return { label: 'Trong hạn mức', className: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300', iconClass: 'text-emerald-400' };
}

const stats = computed(() => {
    const configured = props.branches.filter((branch) => branch.budget_amount !== null);
    const budgetTotal = configured.reduce((sum, branch) => sum + (branch.budget_amount ?? 0), 0);
    const committedTotal = props.branches.reduce((sum, branch) => sum + branch.committed, 0);
    const remainingTotal = configured.reduce((sum, branch) => sum + (branch.remaining ?? 0), 0);
    const overCount = props.branches.filter((branch) => branch.over_budget).length;
    const nearLimitCount = configured.filter(
        (branch) => !branch.over_budget && (branch.budget_amount ?? 0) > 0 && usagePercent(branch) >= 90,
    ).length;

    return {
        budgetTotal,
        committedTotal,
        remainingTotal,
        configuredCount: configured.length,
        unconfiguredCount: props.branches.length - configured.length,
        overCount,
        nearLimitCount,
    };
});

const filteredBranches = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase();

    return props.branches.filter((branch) => {
        const matchesSearch = !needle || `${branch.branch_name} ${branch.branch_code ?? ''}`.toLocaleLowerCase().includes(needle);
        const matchesStatus = statusFilter.value === 'all' || branchStatusKey(branch) === statusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const recommendedBudget = computed(() => {
    if (!selectedBranch.value?.committed) {
        return 0;
    }

    // Gợi ý thêm 10% dự phòng, làm tròn đến 100.000đ để dễ nhập và dễ kiểm soát.
    return Math.ceil((selectedBranch.value.committed * 1.1) / 100_000) * 100_000;
});

const activeTierCount = computed(() => props.wageTiers.filter((tier) => tier.is_active).length);

function selectBranch(branch: BranchRow) {
    selectedBranchId.value = branch.branch_id;
    budgetForm.branch_id = branch.branch_id;
    budgetForm.budget_amount = branch.budget_amount ?? 0;
    budgetForm.notes = branch.notes ?? '';
    budgetForm.clearErrors();
}

function openAdjustBudgetModal(branch: BranchRow) {
    selectBranch(branch);
    showBudgetModal.value = true;
}

function applyRecommendedBudget() {
    budgetForm.budget_amount = recommendedBudget.value;
}

function saveBudget() {
    if (!budgetForm.branch_id) {
        toast.error('Vui lòng chọn chi nhánh.');

        return;
    }

    budgetForm.post('/payroll-budget/budget', {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã cập nhật quỹ lương tháng.');
            showBudgetModal.value = false;
        },
        onError: (errors: Record<string, string>) => toast.error(String(Object.values(errors)[0] ?? 'Không thể lưu quỹ lương.')),
    });
}

function monthlyTierAmount(tier: Pick<WageTier, 'compensation_type' | 'rate'>): number {
    if (tier.compensation_type === 'hourly') {
        return tier.rate * props.payrollRules.hours_per_month;
    }

    if (tier.compensation_type === 'shift') {
        return tier.rate * props.payrollRules.shifts_per_month;
    }

    return tier.rate;
}

function branchName(branchId: number | null): string {
    return branchId ? props.branches.find((branch) => branch.branch_id === branchId)?.branch_name ?? 'Chi nhánh' : 'Toàn chuỗi';
}

function startEditTier(tier: WageTier) {
    editingTierId.value = tier.id;
    tierForm.name = tier.name;
    tierForm.compensation_type = tier.compensation_type;
    tierForm.rate = tier.rate;
    tierForm.revenue_percent = tier.revenue_percent ?? null;
    tierForm.branch_id = tier.branch_id;
    tierForm.is_active = tier.is_active;
    tierForm.clearErrors();
}

function resetTierForm() {
    editingTierId.value = null;
    tierForm.reset();
    tierForm.branch_id = null;
    tierForm.compensation_type = 'fixed';
    tierForm.revenue_percent = null;
    tierForm.is_active = true;
    tierForm.clearErrors();
}

function submitTier() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(editingTierId.value ? 'Đã cập nhật bậc lương.' : 'Đã thêm bậc lương.');
            resetTierForm();
        },
        onError: (errors: Record<string, string>) => toast.error(String(Object.values(errors)[0] ?? 'Không thể lưu bậc lương.')),
    };

    if (editingTierId.value) {
        tierForm.put(`/payroll-budget/wage-tiers/${editingTierId.value}`, options);
    } else {
        tierForm.post('/payroll-budget/wage-tiers', options);
    }
}

function toggleTier(tier: WageTier) {
    router.patch(`/payroll-budget/wage-tiers/${tier.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(tier.is_active ? 'Đã tạm dừng bậc lương.' : 'Đã kích hoạt bậc lương.'),
    });
}

async function removeTier(tier: WageTier) {
    if (await confirmDialog({ title: 'Xóa bậc lương', description: `Bạn chắc chắn muốn xóa “${tier.name}”?`, variant: 'destructive' })) {
        router.delete(`/payroll-budget/wage-tiers/${tier.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xóa bậc lương.'),
        });
    }
}
</script>

<template>
    <Head title="Quỹ lương chi nhánh" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:space-y-8">
        <header class="flex flex-col gap-4 border-b border-border/70 pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="mb-3 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-primary">
                    <span class="rounded-full border border-primary/30 bg-primary/10 px-2.5 py-1">Chủ doanh nghiệp</span>
                    <span class="text-muted-foreground">Kiểm soát tài chính nhân sự</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Quỹ lương theo chi nhánh</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                    Cấp hạn mức, kiểm soát tổng lương nhân viên và quy định bậc lương trước khi quản lý tuyển người.
                    Kỳ áp dụng: <span class="font-semibold text-foreground">{{ month }}</span>.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 py-2 text-sm font-semibold transition hover:border-primary/50 hover:bg-accent"
                @click="router.reload({ preserveScroll: true })"
            >
                <RefreshCw class="size-4" />
                Làm mới số liệu
            </button>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tổng quỹ đã cấp</p>
                        <p class="mt-3 text-2xl font-bold tabular-nums">{{ compactVnd(stats.budgetTotal) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ stats.configuredCount }}/{{ branches.length }} chi nhánh</p>
                    </div>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary"><WalletCards class="size-5" /></div>
                </div>
            </article>

            <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tổng lương/tháng</p>
                        <p class="mt-3 text-2xl font-bold tabular-nums">{{ compactVnd(stats.committedTotal) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Nhân viên đang hoạt động</p>
                    </div>
                    <div class="rounded-xl bg-blue-500/10 p-2.5 text-blue-400"><UsersRound class="size-5" /></div>
                </div>
            </article>

            <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Quỹ còn lại</p>
                        <p class="mt-3 text-2xl font-bold tabular-nums" :class="stats.overCount ? 'text-rose-400' : 'text-emerald-400'">{{ compactVnd(stats.remainingTotal) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Chỉ tính chi nhánh đã cấp quỹ</p>
                    </div>
                    <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-400"><CircleDollarSign class="size-5" /></div>
                </div>
            </article>

            <article class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Cần chú ý</p>
                        <p class="mt-3 text-2xl font-bold tabular-nums" :class="stats.overCount ? 'text-rose-400' : stats.unconfiguredCount ? 'text-amber-400' : 'text-emerald-400'">
                            {{ stats.overCount + stats.unconfiguredCount }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ stats.overCount }} vượt · {{ stats.nearLimitCount }} sắp chạm · {{ stats.unconfiguredCount }} chưa cấp</p>
                    </div>
                    <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-400"><AlertTriangle class="size-5" /></div>
                </div>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex flex-col gap-4 border-b border-border/70 p-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-5 text-primary" />
                        <h2 class="font-bold">Tình hình quỹ theo chi nhánh</h2>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">“Tổng lương/tháng” là chi phí lương dự kiến của nhân viên đang hoạt động.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative block">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="search" type="search" placeholder="Tìm chi nhánh..." class="h-9 w-full rounded-lg border border-border bg-background pl-9 pr-3 text-sm outline-none transition focus:border-primary sm:w-56" />
                    </label>
                    <label class="relative block">
                        <Filter class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <select v-model="statusFilter" class="h-9 w-full appearance-none rounded-lg border border-border bg-background pl-9 pr-8 text-sm outline-none transition focus:border-primary sm:w-44">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="missing">Chưa cấp quỹ</option>
                            <option value="warning">Sắp chạm quỹ</option>
                            <option value="over">Vượt quỹ</option>
                            <option value="healthy">Trong hạn mức</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-sm">
                    <thead class="bg-muted/30 text-left text-xs text-muted-foreground">
                        <tr class="border-b border-border/70">
                            <th class="px-5 py-3 font-semibold">Chi nhánh</th>
                            <th class="px-5 py-3 text-right font-semibold">Quỹ tháng</th>
                            <th class="px-5 py-3 text-right font-semibold">Tổng lương/tháng</th>
                            <th class="w-56 px-5 py-3 font-semibold">Mức sử dụng</th>
                            <th class="px-5 py-3 text-right font-semibold">Còn lại</th>
                            <th class="px-5 py-3 font-semibold">Trạng thái</th>
                            <th class="px-5 py-3 text-right font-semibold"> </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="branch in filteredBranches"
                            :key="branch.branch_id"
                            class="cursor-pointer border-b border-border/60 transition hover:bg-accent/40"
                            :class="selectedBranchId === branch.branch_id ? 'bg-primary/[0.04]' : ''"
                            @click="selectBranch(branch)"
                        >
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ branch.branch_name }}</div>
                                <div v-if="branch.branch_code" class="mt-1 text-xs text-muted-foreground">Mã {{ branch.branch_code }}</div>
                            </td>
                            <td class="px-5 py-4 text-right font-medium tabular-nums">
                                {{ branch.budget_amount === null ? 'Chưa cấp' : vnd(branch.budget_amount) }}
                            </td>
                            <td class="px-5 py-4 text-right tabular-nums">{{ vnd(branch.committed) }}</td>
                            <td class="px-5 py-4">
                                <div v-if="branch.budget_amount !== null" class="space-y-1.5">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted-foreground">{{ usagePercent(branch) }}%</span>
                                        <span class="text-muted-foreground">{{ compactVnd(branch.budget_amount) }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="branch.over_budget ? 'bg-rose-500' : usagePercent(branch) >= 90 ? 'bg-amber-500' : 'bg-emerald-500'"
                                            :style="{ width: `${Math.min(100, usagePercent(branch))}%` }"
                                        />
                                    </div>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">Chưa có hạn mức để tính</span>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold tabular-nums" :class="branch.over_budget ? 'text-rose-400' : branch.remaining !== null ? 'text-emerald-400' : 'text-muted-foreground'">
                                {{ branch.remaining === null ? '—' : branch.over_budget ? `Vượt ${vnd(Math.abs(branch.remaining))}` : vnd(branch.remaining) }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold" :class="branchStatus(branch).className">
                                    <span class="size-1.5 rounded-full bg-current" :class="branchStatus(branch).iconClass" />
                                    {{ branchStatus(branch).label }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline cursor-pointer" @click.stop="openAdjustBudgetModal(branch)">
                                    Điều chỉnh <ChevronRight class="size-3.5" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredBranches.length">
                            <td colspan="7" class="px-5 py-12 text-center text-sm text-muted-foreground">Không có chi nhánh phù hợp.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[1fr_1.25fr]">
            <div class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">Chi tiết đang chọn</p>
                        <h2 class="mt-1 text-lg font-bold">{{ selectedBranch?.branch_name ?? 'Chưa chọn chi nhánh' }}</h2>
                    </div>
                    <Banknote class="size-5 text-primary" />
                </div>
                <template v-if="selectedBranch">
                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-muted/35 p-3">
                            <p class="text-xs text-muted-foreground">Tổng lương/tháng</p>
                            <p class="mt-1 font-bold tabular-nums">{{ vnd(selectedBranch.committed) }}</p>
                        </div>
                        <div class="rounded-xl bg-muted/35 p-3">
                            <p class="text-xs text-muted-foreground">Mức sử dụng</p>
                            <p class="mt-1 font-bold tabular-nums" :class="selectedBranch.over_budget ? 'text-rose-400' : 'text-emerald-400'">
                                {{ selectedBranch.budget_amount === null ? 'Chưa cấp' : `${usagePercent(selectedBranch)}%` }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 flex items-start gap-2 rounded-xl border border-blue-500/20 bg-blue-500/5 p-3 text-xs leading-5 text-muted-foreground">
                        <CircleHelp class="mt-0.5 size-4 shrink-0 text-blue-400" />
                        <span>Quỹ cấp phải luôn lớn hơn hoặc bằng tổng lương hiện tại. Khi quỹ đã được cấp, hệ thống sẽ chặn việc thêm nhân sự làm vượt hạn mức.</span>
                    </div>
                </template>
                <p v-else class="mt-8 text-sm text-muted-foreground">Hãy chọn một chi nhánh trong bảng để xem chi tiết.</p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">Thiết lập hạn mức</p>
                        <h2 class="mt-1 text-lg font-bold">Cấp quỹ lương tháng</h2>
                    </div>
                    <WalletCards class="size-5 text-primary" />
                </div>
                <form class="mt-5 space-y-4" @submit.prevent="saveBudget">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium">
                            Chi nhánh
                            <select v-model="budgetForm.branch_id" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-primary" @change="selectBranch(branches.find((branch) => branch.branch_id === budgetForm.branch_id)!)">
                                <option v-for="branch in branches" :key="branch.branch_id" :value="branch.branch_id">{{ branch.branch_name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm font-medium">
                            Quỹ tháng (đ)
                            <input v-model.number="budgetForm.budget_amount" type="number" min="0" step="100000" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 text-sm tabular-nums outline-none focus:border-primary" />
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/30 px-3 py-2.5 text-xs">
                        <span class="text-muted-foreground">Gợi ý an toàn: tổng lương + 10% dự phòng</span>
                        <button type="button" class="font-semibold text-primary hover:underline" @click="applyRecommendedBudget">Dùng {{ vnd(recommendedBudget) }}</button>
                    </div>
                    <label class="block text-sm font-medium">
                        Ghi chú phê duyệt <span class="font-normal text-muted-foreground">(không bắt buộc)</span>
                        <textarea v-model="budgetForm.notes" rows="2" maxlength="500" placeholder="Ví dụ: đã duyệt theo kế hoạch vận hành tháng này" class="mt-1.5 block w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary" />
                    </label>
                    <p v-if="budgetForm.errors.budget_amount" class="text-xs font-medium text-rose-400">{{ budgetForm.errors.budget_amount }}</p>
                    <div class="flex items-center justify-end gap-3 border-t border-border/70 pt-4">
                        <span v-if="selectedBranch?.budget_amount !== null" class="mr-auto text-xs text-muted-foreground">Đang cập nhật quỹ {{ month }}</span>
                        <button type="submit" :disabled="budgetForm.processing || !budgetForm.branch_id" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                            <Banknote class="size-4" />
                            {{ budgetForm.processing ? 'Đang lưu...' : 'Lưu quỹ' }}
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/70 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div>
                    <div class="flex items-center gap-2">
                        <CircleDollarSign class="size-5 text-primary" />
                        <h2 class="font-bold">Bậc lương được phép sử dụng</h2>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">Quản lý chỉ được chọn bậc đang hoạt động khi tạo hoặc điều chỉnh nhân viên.</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 font-semibold text-emerald-400">{{ activeTierCount }} đang hoạt động</span>
                    <span>{{ wageTiers.length }} tổng cộng</span>
                </div>
            </div>

            <div class="grid lg:grid-cols-[1.15fr_0.85fr]">
                <div class="overflow-x-auto border-b border-border/70 lg:border-r lg:border-b-0">
                    <table class="w-full min-w-[650px] text-sm">
                        <thead class="bg-muted/30 text-left text-xs text-muted-foreground">
                            <tr class="border-b border-border/70">
                                <th class="px-5 py-3 font-semibold">Tên bậc lương</th>
                                <th class="px-5 py-3 font-semibold">Phạm vi</th>
                                <th class="px-5 py-3 text-right font-semibold">Mức tháng quy đổi</th>
                                <th class="px-5 py-3 text-right font-semibold"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tier in wageTiers" :key="tier.id" class="border-b border-border/60 transition hover:bg-accent/30" :class="!tier.is_active ? 'opacity-60' : ''">
                                <td class="px-5 py-4">
                                    <div class="font-semibold">{{ tier.name }}</div>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                        <span>{{ compLabel[tier.compensation_type] }} · {{ vnd(tier.rate) }}</span>
                                        <span v-if="tier.revenue_percent" class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 font-semibold text-emerald-600 dark:text-emerald-400">
                                            + {{ tier.revenue_percent }}% doanh thu
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-xs text-muted-foreground">{{ branchName(tier.branch_id) }}</td>
                                <td class="px-5 py-4 text-right font-semibold tabular-nums">{{ compactVnd(monthlyTierAmount(tier)) }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="rounded-md p-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground" :title="tier.is_active ? 'Tạm dừng' : 'Kích hoạt'" @click="toggleTier(tier)">
                                            <PauseCircle v-if="tier.is_active" class="size-4" />
                                            <PlayCircle v-else class="size-4" />
                                        </button>
                                        <button type="button" class="rounded-md p-1.5 text-muted-foreground transition hover:bg-accent hover:text-foreground" title="Chỉnh sửa" @click="startEditTier(tier)"><Edit3 class="size-4" /></button>
                                        <button type="button" class="rounded-md p-1.5 text-rose-400 transition hover:bg-rose-500/10" title="Xóa" @click="removeTier(tier)"><Trash2 class="size-4" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!wageTiers.length">
                                <td colspan="4" class="px-5 py-12 text-center text-sm text-muted-foreground">Chưa có bậc lương nào được thiết lập.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-5 sm:p-6">
                    <div class="flex items-center gap-2">
                        <Plus class="size-4 text-primary" />
                        <h3 class="font-bold">{{ editingTierId ? 'Chỉnh sửa bậc lương' : 'Thêm bậc lương' }}</h3>
                    </div>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">Mức theo giờ quy đổi {{ payrollRules.hours_per_month }}h/tháng; theo ca quy đổi {{ payrollRules.shifts_per_month }} ca/tháng.</p>
                    <form class="mt-5 space-y-3" @submit.prevent="submitTier">
                        <label class="block text-sm font-medium">
                            Tên bậc
                            <input v-model="tierForm.name" type="text" maxlength="120" placeholder="Ví dụ: Phục vụ ca ngày" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-primary" />
                        </label>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block text-sm font-medium">
                                Hình thức
                                <select v-model="tierForm.compensation_type" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-primary">
                                    <option value="hourly">Theo giờ</option>
                                    <option value="shift">Theo ca</option>
                                    <option value="fixed">Cố định tháng</option>
                                </select>
                            </label>
                            <label class="block text-sm font-medium">
                                Mức (đ)
                                <input v-model.number="tierForm.rate" type="number" min="0" step="1000" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm tabular-nums outline-none focus:border-primary" />
                            </label>
                            <label class="block text-sm font-medium">
                                % Doanh thu <span class="text-xs font-normal text-muted-foreground">(tùy chọn)</span>
                                <div class="relative mt-1.5">
                                    <input v-model.number="tierForm.revenue_percent" type="number" min="0" max="100" step="0.1" placeholder="Ví dụ: 2.5 (để trống nếu không có)" class="block h-10 w-full rounded-lg border border-border bg-background px-3 pr-7 text-sm tabular-nums outline-none focus:border-primary" />
                                    <span class="absolute right-2.5 top-2.5 text-xs font-semibold text-muted-foreground">%</span>
                                </div>
                            </label>
                        </div>
                        <label class="block text-sm font-medium">
                            Phạm vi áp dụng
                            <select v-model="tierForm.branch_id" class="mt-1.5 block h-10 w-full rounded-lg border border-border bg-background px-3 text-sm outline-none focus:border-primary">
                                <option :value="null">Toàn chuỗi</option>
                                <option v-for="branch in branches" :key="branch.branch_id" :value="branch.branch_id">{{ branch.branch_name }}</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="tierForm.is_active" type="checkbox" class="size-4 rounded border-border accent-primary" />
                            Cho phép sử dụng ngay
                        </label>
                        <div class="rounded-lg border border-border/70 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
                            Chi phí quy đổi: <span class="font-bold text-foreground">{{ vnd(monthlyTierAmount(tierForm)) }}/tháng</span>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button v-if="editingTierId" type="button" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold hover:bg-accent" @click="resetTierForm">Hủy</button>
                            <button type="submit" :disabled="tierForm.processing" class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:opacity-90 disabled:opacity-50">
                                <CheckCircle2 class="size-4" />
                                {{ tierForm.processing ? 'Đang lưu...' : editingTierId ? 'Lưu thay đổi' : 'Thêm bậc lương' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div class="flex items-start gap-3 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-xs leading-5 text-muted-foreground">
            <ArrowUpRight class="mt-0.5 size-4 shrink-0 text-amber-400" />
            <p><span class="font-semibold text-foreground">Nguyên tắc kiểm soát:</span> Quỹ do chủ doanh nghiệp cấp. Quản lý chỉ được chọn bậc lương đã phê duyệt và không thể thêm nhân sự nếu tổng lương mới vượt phần quỹ còn lại.</p>
        </div>

        <!-- Modal Điều chỉnh Hạn mức Quỹ lương -->
        <Dialog v-model:open="showBudgetModal">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-lg font-bold">
                        <WalletCards class="size-5 text-primary" />
                        Điều chỉnh Quỹ Lương: {{ selectedBranch?.branch_name }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground">
                        Kỳ áp dụng tháng <strong class="text-foreground">{{ month }}</strong>. Cấp hạn mức ngân sách chi trả lương cho chi nhánh.
                    </DialogDescription>
                </DialogHeader>

                <form class="mt-2 space-y-4" @submit.prevent="saveBudget">
                    <div class="space-y-1.5 rounded-xl bg-muted/40 p-3 text-xs">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Chi phí lương hiện tại (dự kiến):</span>
                            <span class="font-bold text-foreground">{{ selectedBranch ? vnd(selectedBranch.committed) : '0đ' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Hạn mức hiện tại:</span>
                            <span class="font-semibold">{{ selectedBranch?.budget_amount !== null ? vnd(selectedBranch.budget_amount) : 'Chưa cấp' }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium">
                            Quỹ lương tháng (VND)
                        </label>
                        <input
                            v-model.number="budgetForm.budget_amount"
                            type="number"
                            min="0"
                            step="100000"
                            placeholder="Nhập số tiền..."
                            class="h-10 w-full rounded-lg border border-border bg-background px-3 text-sm tabular-nums outline-none focus:border-primary"
                        />
                        <p v-if="budgetForm.errors.budget_amount" class="text-xs font-medium text-rose-400">
                            {{ budgetForm.errors.budget_amount }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-xs">
                        <span class="text-muted-foreground">Gợi ý an toàn (+10% dự phòng):</span>
                        <button type="button" class="cursor-pointer font-semibold text-primary hover:underline" @click="applyRecommendedBudget">
                            Dùng {{ vnd(recommendedBudget) }}
                        </button>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium">
                            Ghi chú phê duyệt <span class="font-normal text-muted-foreground">(tùy chọn)</span>
                        </label>
                        <textarea
                            v-model="budgetForm.notes"
                            rows="2"
                            maxlength="500"
                            placeholder="Ví dụ: Đã duyệt tăng quỹ phục vụ vận hành tháng này"
                            class="w-full resize-none rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                        />
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" variant="outline" @click="showBudgetModal = false">
                            Hủy
                        </Button>
                        <Button type="submit" :disabled="budgetForm.processing">
                            <Banknote class="mr-1.5 size-4" />
                            {{ budgetForm.processing ? 'Đang lưu...' : 'Lưu quỹ lương' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
