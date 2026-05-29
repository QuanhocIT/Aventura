<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Wallet, Users, TrendingDown, TrendingUp, Check, ChevronDown,
    ChevronUp, Plus, BadgeDollarSign, AlertTriangle, Clock, X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ─────────────────────────────────────────────────────────────────────

type AdjType = 'bonus' | 'penalty' | 'cash_shortage' | 'inventory_loss' | 'violation';

type Adjustment = { id: number; type: AdjType; amount: number; reason: string };

type SalaryRow = {
    id: number;
    employee_name: string;
    job_title: string;
    employment_type: string;
    base_salary: number;
    bonus_amount: number;
    deduction_amount: number;
    net_salary: number;
    status: 'draft' | 'approved' | 'paid';
    paid_at: string | null;
    adjustments: Adjustment[];
};

type Totals = { total_payroll: number; total_deductions: number; total_bonuses: number; headcount: number };

const props = defineProps<{
    salaries:   SalaryRow[];
    totals:     Totals;
    period:     string;
    canApprove: boolean;
}>();

// ── Status config ─────────────────────────────────────────────────────────────

const statusConfig = {
    draft:    { label: 'Bản nháp',   cls: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' },
    approved: { label: 'Đã duyệt',   cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
    paid:     { label: 'Đã trả',     cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' },
};

const adjTypeLabel: Record<AdjType, string> = {
    bonus:          'Thưởng',
    penalty:        'Phạt',
    cash_shortage:  'Thiếu quỹ',
    inventory_loss: 'Mất hàng',
    violation:      'Vi phạm',
};

const adjTypeColor: Record<AdjType, string> = {
    bonus:          'text-emerald-600 dark:text-emerald-400',
    penalty:        'text-rose-600 dark:text-rose-400',
    cash_shortage:  'text-rose-600 dark:text-rose-400',
    inventory_loss: 'text-amber-600 dark:text-amber-400',
    violation:      'text-rose-600 dark:text-rose-400',
};

// ── Filter / navigation ───────────────────────────────────────────────────────

const activePeriod = ref(props.period);

function applyPeriod() {
    router.get('/salaries', { period: activePeriod.value }, { preserveScroll: true });
}

// ── Generate drafts ───────────────────────────────────────────────────────────

const generating = ref(false);
const generateForm = useForm({ period: props.period });

function generateDrafts() {
    generating.value = true;
    generateForm.period = activePeriod.value;
    generateForm.post('/salaries/generate', {
        onSuccess: () => toast.success('Đã tạo bảng lương bản nháp!'),
        onError:   () => toast.error('Có lỗi khi tạo bảng lương.'),
        onFinish:  () => { generating.value = false; },
    });
}

// ── Approve / Paid ────────────────────────────────────────────────────────────

function approveSalary(salary: SalaryRow) {
    router.patch(`/salaries/${salary.id}/approve`, {}, {
        onSuccess: () => toast.success(`Đã duyệt lương ${salary.employee_name}.`),
        onError:   () => toast.error('Có lỗi khi duyệt lương.'),
    });
}

function markPaid(salary: SalaryRow) {
    router.patch(`/salaries/${salary.id}/paid`, {}, {
        onSuccess: () => toast.success(`Đã đánh dấu đã trả lương ${salary.employee_name}.`),
        onError:   () => toast.error('Có lỗi khi cập nhật trạng thái.'),
    });
}

// ── Expanded row ──────────────────────────────────────────────────────────────

const expandedId = ref<number | null>(null);
const toggleExpand = (id: number) => { expandedId.value = expandedId.value === id ? null : id; };

// ── Add adjustment dialog ─────────────────────────────────────────────────────

const adjTarget = ref<SalaryRow | null>(null);
const adjForm = useForm({ type: 'bonus', amount: '', reason: '' });

function openAdjDialog(salary: SalaryRow) {
    adjTarget.value = salary;
    adjForm.reset();
}

const page = usePage();

function submitAdj() {
    if (!adjTarget.value) return;
    adjForm.post(`/salaries/${adjTarget.value.id}/adjustments`, {
        onSuccess: () => {
            const msg = (page.props.flash as any)?.success ?? 'Đã thêm điều chỉnh lương.';
            toast.success(msg);
            adjTarget.value = null;
        },
        onError: () => toast.error('Có lỗi khi thêm điều chỉnh.'),
    });
}

// ── Formatting ─────────────────────────────────────────────────────────────────

const vnd = (v: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);
const compact = (v: number) => new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
</script>

<template>
    <Head title="Bảng Lương" />

    <div class="mx-auto max-w-7xl space-y-6 p-4 lg:p-6">

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-500">
                    <Wallet class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Bảng Lương</h1>
                    <p class="text-sm text-muted-foreground">Quản lý lương, thưởng và khấu trừ nhân viên</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Month picker -->
                <input v-model="activePeriod" type="month" @change="applyPeriod"
                    class="rounded-xl border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" />

                <!-- Generate drafts -->
                <button v-if="canApprove" @click="generateDrafts" :disabled="generating"
                    class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                    <Plus class="size-4" :class="generating ? 'animate-spin' : ''" />
                    {{ generating ? 'Đang tạo...' : 'Tạo bảng lương' }}
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Nhân viên</span>
                    <Users class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ totals.headcount }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">có bảng lương tháng này</p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng lương net</span>
                    <BadgeDollarSign class="size-4 text-blue-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ compact(totals.total_payroll) }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">sau khấu trừ</p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng khấu trừ</span>
                    <TrendingDown class="size-4 text-rose-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ compact(totals.total_deductions) }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">thiếu quỹ + mất hàng + phạt</p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng thưởng</span>
                    <TrendingUp class="size-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ compact(totals.total_bonuses) }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">bonus tháng này</p>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <div v-if="salaries.length === 0" class="flex flex-col items-center gap-3 py-16 text-center text-muted-foreground">
                <Wallet class="size-12 opacity-30" />
                <p class="font-medium">Chưa có bảng lương tháng này</p>
                <p class="text-sm">Nhấn "Tạo bảng lương" để tạo bản nháp cho tất cả nhân viên active</p>
            </div>

            <template v-else>
                <!-- Table header -->
                <div class="hidden grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                    <div></div>
                    <div>Nhân viên</div>
                    <div class="text-right">Lương cơ bản</div>
                    <div class="text-right">Thưởng</div>
                    <div class="text-right">Khấu trừ</div>
                    <div class="text-right">Lương net</div>
                    <div class="text-right">Trạng thái</div>
                </div>

                <div v-for="s in salaries" :key="s.id" class="border-b border-border last:border-0">
                    <!-- Main row -->
                    <div class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5"
                        @click="toggleExpand(s.id)">

                        <component :is="expandedId === s.id ? ChevronUp : ChevronDown"
                            class="size-4 shrink-0 text-muted-foreground" />

                        <!-- Nhân viên -->
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-sm">{{ s.employee_name }}</p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ s.job_title || s.employment_type || '—' }}</p>
                        </div>

                        <!-- Lương cơ bản -->
                        <div class="hidden text-right lg:block">
                            <p class="text-sm">{{ compact(s.base_salary) }}</p>
                        </div>

                        <!-- Thưởng -->
                        <div class="hidden text-right lg:block">
                            <p class="text-sm font-semibold" :class="s.bonus_amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">
                                {{ s.bonus_amount > 0 ? '+' + compact(s.bonus_amount) : '—' }}
                            </p>
                        </div>

                        <!-- Khấu trừ -->
                        <div class="hidden text-right lg:block">
                            <p class="text-sm font-semibold" :class="s.deduction_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'">
                                {{ s.deduction_amount > 0 ? '-' + compact(s.deduction_amount) : '—' }}
                            </p>
                        </div>

                        <!-- Lương net -->
                        <div class="hidden text-right lg:block">
                            <p class="font-bold text-sm text-blue-600 dark:text-blue-400">{{ compact(s.net_salary) }}</p>
                        </div>

                        <!-- Status + mobile amount -->
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="statusConfig[s.status].cls">
                                {{ statusConfig[s.status].label }}
                            </span>
                            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 lg:hidden">
                                {{ compact(s.net_salary) }}
                            </span>
                        </div>
                    </div>

                    <!-- Expanded detail -->
                    <Transition enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[600px]"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 max-h-[600px]" leave-to-class="opacity-0 max-h-0">
                        <div v-if="expandedId === s.id" class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4">
                            <div class="grid gap-5 sm:grid-cols-2">

                                <!-- Salary breakdown -->
                                <div class="space-y-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Chi tiết lương</p>
                                    <div class="space-y-1.5 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Lương cơ bản</span>
                                            <span>{{ vnd(s.base_salary) }}</span>
                                        </div>
                                        <div v-if="s.bonus_amount > 0" class="flex justify-between">
                                            <span class="text-muted-foreground">Tổng thưởng</span>
                                            <span class="text-emerald-600 font-semibold">+{{ vnd(s.bonus_amount) }}</span>
                                        </div>
                                        <div v-if="s.deduction_amount > 0" class="flex justify-between">
                                            <span class="text-muted-foreground">Tổng khấu trừ</span>
                                            <span class="text-rose-600 font-semibold">-{{ vnd(s.deduction_amount) }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border pt-1.5">
                                            <span class="font-semibold">Lương thực nhận</span>
                                            <span class="font-bold text-blue-600 dark:text-blue-400">{{ vnd(s.net_salary) }}</span>
                                        </div>
                                        <div v-if="s.paid_at" class="flex items-center gap-1.5 text-xs text-muted-foreground mt-1">
                                            <Clock class="size-3" /> Đã trả lúc {{ s.paid_at }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Adjustments list -->
                                <div class="space-y-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Các khoản điều chỉnh ({{ s.adjustments.length }})</p>
                                    <div v-if="s.adjustments.length === 0" class="text-xs text-muted-foreground py-2">Không có điều chỉnh</div>
                                    <div v-else class="space-y-1.5">
                                        <div v-for="a in s.adjustments" :key="a.id"
                                            class="flex items-start justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2 text-xs">
                                            <div class="min-w-0">
                                                <span class="font-semibold" :class="adjTypeColor[a.type as AdjType]">
                                                    {{ adjTypeLabel[a.type as AdjType] }}
                                                </span>
                                                <p class="text-muted-foreground mt-0.5 truncate">{{ a.reason }}</p>
                                            </div>
                                            <span class="shrink-0 font-bold" :class="adjTypeColor[a.type as AdjType]">
                                                {{ a.type === 'bonus' ? '+' : '-' }}{{ vnd(a.amount) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div v-if="canApprove" class="mt-4 flex flex-wrap gap-2">
                                <button v-if="s.status === 'draft'" @click.stop="approveSalary(s)"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 active:scale-95">
                                    <Check class="size-3.5" /> Duyệt lương
                                </button>
                                <button v-if="s.status === 'approved'" @click.stop="markPaid(s)"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700 active:scale-95">
                                    <Check class="size-3.5" /> Đánh dấu đã trả
                                </button>
                                <button v-if="s.status !== 'paid'" @click.stop="openAdjDialog(s)"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted active:scale-95">
                                    <Plus class="size-3.5" /> Thêm điều chỉnh
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </template>
        </div>
    </div>

    <!-- ══ Add Adjustment Dialog ═══════════════════════════════════════════════ -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="adjTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="adjTarget = null">
            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <div>
                        <p class="font-semibold">Thêm điều chỉnh lương</p>
                        <p class="text-xs text-muted-foreground">{{ adjTarget.employee_name }}</p>
                    </div>
                    <button @click="adjTarget = null" class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted">
                        <X class="size-4" />
                    </button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium">Loại điều chỉnh</label>
                        <select v-model="adjForm.type"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="bonus">Thưởng</option>
                            <option value="penalty">Phạt</option>
                            <option value="violation">Vi phạm</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium">Số tiền (đ) <span class="text-rose-500">*</span></label>
                        <input v-model="adjForm.amount" type="number" min="0.01" step="1000" placeholder="0"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                            :class="{'border-rose-400': adjForm.errors.amount}" />
                        <p v-if="adjForm.errors.amount" class="text-xs text-rose-500">{{ adjForm.errors.amount }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm font-medium">Lý do <span class="text-rose-500">*</span></label>
                        <textarea v-model="adjForm.reason" rows="2" maxlength="500" placeholder="Mô tả lý do..."
                            class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
                            :class="{'border-rose-400': adjForm.errors.reason}" />
                        <p v-if="adjForm.errors.reason" class="text-xs text-rose-500">{{ adjForm.errors.reason }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                    <button @click="adjTarget = null"
                        class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted">
                        Hủy
                    </button>
                    <button @click="submitAdj" :disabled="adjForm.processing"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 active:scale-95 disabled:opacity-50">
                        Xác nhận
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
