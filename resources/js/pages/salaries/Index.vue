<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Wallet, Users, TrendingDown, TrendingUp, Check, ChevronDown,
    ChevronUp, Plus, BadgeDollarSign, AlertCircle, Clock, X, Sparkles
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    draft:    { label: 'Bản nháp',   cls: 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700/50' },
    approved: { label: 'Đã duyệt',   cls: 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/30' },
    paid:     { label: 'Đã trả',     cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/30' },
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
        onSuccess: () => toast.success(`Đã duyệt lương cho ${salary.employee_name}.`),
        onError:   () => toast.error('Có lỗi khi duyệt lương.'),
    });
}

function markPaid(salary: SalaryRow) {
    router.patch(`/salaries/${salary.id}/paid`, {}, {
        onSuccess: () => toast.success(`Đã đánh dấu đã trả lương cho ${salary.employee_name}.`),
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
            const msg = (page.props.flash as any)?.success ?? 'Đã thêm điều chỉnh lương thành công.';
            toast.success(msg);
            adjTarget.value = null;
        },
        onError: () => toast.error('Có lỗi khi thêm điều chỉnh lương.'),
    });
}

// ── Formatting ─────────────────────────────────────────────────────────────────

const vnd = (v: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);
const compact = (v: number) => new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
</script>

<template>
    <Head title="Quản Lý Bảng Lương" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Wallet class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hệ Thống Quản Lý Bảng Lương</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Quản lý tiền lương, phụ cấp, thưởng và các khoản phạt khấu trừ nhân sự hàng tháng.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Month picker -->
                <div class="flex items-center gap-1.5">
                    <Label for="sal-period" class="text-xs shrink-0 font-semibold text-slate-600">Chọn tháng:</Label>
                    <Input 
                        id="sal-period"
                        v-model="activePeriod" 
                        type="month" 
                        @change="applyPeriod"
                        class="h-9 w-36 text-xs font-semibold py-1 bg-white" 
                    />
                </div>

                <!-- Generate drafts -->
                <Button 
                    v-if="canApprove" 
                    @click="generateDrafts" 
                    :disabled="generating"
                    class="h-9 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5"
                >
                    <Plus class="size-4" :class="generating ? 'animate-spin' : ''" />
                    {{ generating ? 'Đang tạo...' : 'Tạo bảng lương' }}
                </Button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Headcount -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Nhân viên</CardDescription>
                    <Users class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ totals.headcount }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">có bảng lương tháng này</p>
                </CardContent>
            </Card>

            <!-- Net Salary -->
            <Card class="shadow-xs border-indigo-100 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Tổng lương Net</CardDescription>
                    <BadgeDollarSign class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ compact(totals.total_payroll) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">sau khi khấu trừ</p>
                </CardContent>
            </Card>

            <!-- Deductions -->
            <Card class="shadow-xs border-rose-100 dark:border-rose-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-rose-500">Tổng khấu trừ</CardDescription>
                    <TrendingDown class="size-4 text-rose-600 dark:text-rose-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ compact(totals.total_deductions) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">thiếu quỹ + mất hàng + phạt</p>
                </CardContent>
            </Card>

            <!-- Bonuses -->
            <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Tổng thưởng</CardDescription>
                    <TrendingUp class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ compact(totals.total_bonuses) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">tiền thưởng tháng này</p>
                </CardContent>
            </Card>
        </div>

        <!-- Table Card -->
        <Card class="shadow-sm overflow-hidden">
            <CardHeader class="pb-3 border-b flex flex-row items-center justify-between print:hidden">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5">
                        <Wallet class="size-5 text-indigo-600" />
                        Danh Sách Bảng Lương Chi Tiết
                    </CardTitle>
                    <CardDescription>Danh sách tổng hợp lương và các khoản thưởng phạt thực tế của toàn bộ nhân viên.</CardDescription>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div v-if="salaries.length === 0" class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600">
                        <Wallet class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">Chưa có dữ liệu bảng lương tháng này</p>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Vui lòng nhấn nút "Tạo bảng lương" ở trên để hệ thống tự động sinh dữ liệu nháp cho tất cả nhân sự đang hoạt động.</p>
                </div>

                <template v-else>
                    <!-- Table header -->
                    <div class="hidden grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-500 lg:grid">
                        <div></div>
                        <div>Nhân viên</div>
                        <div class="text-right">Lương cơ bản</div>
                        <div class="text-right">Thưởng</div>
                        <div class="text-right">Khấu trừ</div>
                        <div class="text-right">Lương Net thực nhận</div>
                        <div class="text-right">Trạng thái</div>
                    </div>

                    <div v-for="s in salaries" :key="s.id" class="border-b border-slate-100 dark:border-slate-800 last:border-0">
                        <!-- Main row -->
                        <div class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-muted/30 lg:grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand(s.id)">

                            <component :is="expandedId === s.id ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-slate-400" />

                            <!-- Nhân viên -->
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-sm">{{ s.employee_name }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ s.job_title || s.employment_type || '—' }}</p>
                            </div>

                            <!-- Lương cơ bản -->
                            <div class="hidden text-right lg:block font-mono text-slate-600 dark:text-slate-300">
                                <p class="text-sm font-semibold">{{ compact(s.base_salary) }}</p>
                            </div>

                            <!-- Thưởng -->
                            <div class="hidden text-right lg:block font-mono">
                                <p class="text-sm font-bold" :class="s.bonus_amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-300 dark:text-slate-700'">
                                    {{ s.bonus_amount > 0 ? '+' + compact(s.bonus_amount) : '—' }}
                                </p>
                            </div>

                            <!-- Khấu trừ -->
                            <div class="hidden text-right lg:block font-mono">
                                <p class="text-sm font-bold" :class="s.deduction_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-700'">
                                    {{ s.deduction_amount > 0 ? '-' + compact(s.deduction_amount) : '—' }}
                                </p>
                            </div>

                            <!-- Lương net -->
                            <div class="hidden text-right lg:block font-mono">
                                <p class="font-black text-sm text-indigo-600 dark:text-indigo-400">{{ compact(s.net_salary) }}</p>
                            </div>

                            <!-- Status + mobile amount -->
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                    :class="statusConfig[s.status].cls">
                                    {{ statusConfig[s.status].label }}
                                </span>
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 lg:hidden font-mono">
                                    {{ compact(s.net_salary) }}
                                </span>
                            </div>
                        </div>

                        <!-- Expanded detail -->
                        <Transition enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-[600px]" leave-to-class="opacity-0 max-h-0">
                            <div v-if="expandedId === s.id" class="overflow-hidden border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/20 px-5 py-5">
                                <div class="grid gap-6 sm:grid-cols-2">

                                    <!-- Salary breakdown -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Chi tiết cơ cấu lương thực nhận</p>
                                        <div class="space-y-2 text-xs font-semibold">
                                            <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                                <span class="font-medium">Lương cơ bản cố định</span>
                                                <span class="font-mono">{{ vnd(s.base_salary) }}</span>
                                            </div>
                                            <div v-if="s.bonus_amount > 0" class="flex justify-between text-emerald-600">
                                                <span class="font-medium">Tổng các khoản thưởng (+)</span>
                                                <span class="font-mono font-bold">+{{ vnd(s.bonus_amount) }}</span>
                                            </div>
                                            <div v-if="s.deduction_amount > 0" class="flex justify-between text-rose-600">
                                                <span class="font-medium">Tổng các khoản khấu trừ (-)</span>
                                                <span class="font-mono font-bold">-{{ vnd(s.deduction_amount) }}</span>
                                            </div>
                                            <div class="flex justify-between border-t border-slate-100 dark:border-slate-800 pt-2 text-slate-800 dark:text-slate-200">
                                                <span class="font-bold">Lương NET thực nhận</span>
                                                <span class="font-mono font-black text-sm text-indigo-600 dark:text-indigo-400">{{ vnd(s.net_salary) }}</span>
                                            </div>
                                            <div v-if="s.paid_at" class="flex items-center gap-1 text-[10px] text-slate-400 font-mono mt-2 border-t pt-2">
                                                <Clock class="size-3" /> Đã chi trả thành công lúc: {{ s.paid_at }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Adjustments list -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Danh sách các khoản điều chỉnh lương ({{ s.adjustments.length }})</p>
                                        <div v-if="s.adjustments.length === 0" class="text-xs text-slate-400 italic py-2">Không có khoản ghi nhận điều chỉnh nào phát sinh trong tháng.</div>
                                        <div v-else class="space-y-2 max-h-[160px] overflow-y-auto pr-1">
                                            <div v-for="a in s.adjustments" :key="a.id"
                                                class="flex items-start justify-between gap-3 rounded-lg border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 px-3 py-2 text-xs">
                                                <div class="min-w-0">
                                                    <span class="font-bold text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800" :class="adjTypeColor[a.type as AdjType]">
                                                        {{ adjTypeLabel[a.type as AdjType] }}
                                                    </span>
                                                    <p class="text-slate-500 mt-1 truncate" :title="a.reason">{{ a.reason }}</p>
                                                </div>
                                                <span class="shrink-0 font-bold font-mono" :class="adjTypeColor[a.type as AdjType]">
                                                    {{ a.type === 'bonus' ? '+' : '-' }}{{ vnd(a.amount) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div v-if="canApprove" class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 dark:border-slate-800 pt-3.5">
                                    <Button 
                                        v-if="s.status === 'draft'" 
                                        @click.stop="approveSalary(s)"
                                        class="h-8 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1"
                                    >
                                        <Check class="size-3.5" /> Duyệt bảng lương
                                    </Button>
                                    <Button 
                                        v-if="s.status === 'approved'" 
                                        @click.stop="markPaid(s)"
                                        class="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center gap-1"
                                    >
                                        <Check class="size-3.5" /> Đánh dấu đã trả lương
                                    </Button>
                                    <Button 
                                        v-if="s.status !== 'paid'" 
                                        @click.stop="openAdjDialog(s)"
                                        variant="outline"
                                        class="h-8 text-xs text-indigo-600 border-indigo-100 hover:bg-indigo-50 font-semibold flex items-center gap-1"
                                    >
                                        <Plus class="size-3.5" /> Thêm khoản điều chỉnh
                                    </Button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </CardContent>
        </Card>
    </div>

    <!-- ══ Add Adjustment Dialog ═══════════════════════════════════════════════ -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="adjTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="adjTarget = null">
            <Card class="w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Sparkles class="size-5" />
                            Thêm Khoản Điều Chỉnh Lương
                        </CardTitle>
                        <CardDescription>Thêm thưởng, phạt hành chính hoặc khấu trừ hao hụt kho cho nhân sự <strong>{{ adjTarget.employee_name }}</strong>.</CardDescription>
                    </div>
                    <button @click="adjTarget = null" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <!-- Type Selection -->
                    <div class="grid gap-1.5">
                        <Label for="adj-type" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Loại điều chỉnh</Label>
                        <select 
                            id="adj-type"
                            v-model="adjForm.type"
                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            <option value="bonus">Thưởng chuyên cần / Bonus</option>
                            <option value="penalty">Phạt hành chính / Kỷ luật</option>
                            <option value="violation">Khấu trừ hao hụt kho / Vi phạm</option>
                        </select>
                    </div>
                    
                    <!-- Amount Input -->
                    <div class="grid gap-1.5">
                        <Label for="adj-amount" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Số tiền (VNĐ) <span class="text-rose-500">*</span></Label>
                        <Input 
                            id="adj-amount"
                            v-model="adjForm.amount" 
                            type="number" 
                            min="0.01" 
                            step="1000" 
                            placeholder="Nhập số tiền..."
                            class="h-9 text-xs"
                            :class="{'border-rose-400': adjForm.errors.amount}" 
                        />
                        <p v-if="adjForm.errors.amount" class="text-[10px] text-rose-500 font-semibold">{{ adjForm.errors.amount }}</p>
                    </div>
                    
                    <!-- Reason Area -->
                    <div class="grid gap-1.5">
                        <Label for="adj-reason" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do điều chỉnh chi tiết <span class="text-rose-500">*</span></Label>
                        <textarea 
                            id="adj-reason"
                            v-model="adjForm.reason" 
                            rows="3" 
                            maxlength="500" 
                            placeholder="Mô tả lý do thưởng phạt cụ thể để ghi nhận pháp lý..."
                            class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            :class="{'border-rose-400': adjForm.errors.reason}" 
                        />
                        <p v-if="adjForm.errors.reason" class="text-[10px] text-rose-500 font-semibold">{{ adjForm.errors.reason }}</p>
                    </div>
                    
                    <!-- Warning banner -->
                    <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400">
                        <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                        <p><strong>Duyệt điều chỉnh:</strong> Quản lý chỉ được phép đề xuất điều chỉnh. Đơn điều chỉnh sẽ được tự động chuyển đến trạng thái chờ duyệt nếu bạn không phải là Chủ (Owner) nhà hàng.</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="adjTarget = null">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitAdj" 
                            :disabled="adjForm.processing"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                        >
                            {{ adjForm.processing ? 'Đang lưu...' : 'Xác nhận điều chỉnh' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </Transition>
</template>
