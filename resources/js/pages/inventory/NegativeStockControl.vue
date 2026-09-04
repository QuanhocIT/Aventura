<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    FileWarning,
    History,
    Layers,
    ShieldAlert,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type NegativeCase = {
    id: number;
    branch_id?: number | null;
    branch_name?: string | null;
    ingredient_name?: string | null;
    unit_symbol?: string | null;
    status:
        | 'open'
        | 'in_progress'
        | 'pending_owner_approval'
        | 'pending_verification'
        | 'resolved';
    severity: 'low' | 'medium' | 'high' | 'critical';
    severity_label?: string;
    source_type?: string;
    source_label?: string;
    negative_quantity: number;
    on_hand: number;
    case_code?: string;
    detected_quantity?: number;
    detected_value?: number;
    estimated_value: number;
    detected_at?: string | null;
    due_at?: string | null;
    age_hours?: number | null;
    is_overdue?: boolean;
    workflow_step?: string;
    sla_hours?: number;
    auto_plan?: string | null;
    handling_plan?: string | null;
    root_cause?: string | null;
    root_cause_code?: string | null;
    root_cause_label?: string | null;
    containment_action?: string | null;
    corrective_action?: string | null;
    responsible_user_id?: number | null;
    responsible_user_name?: string | null;
    expected_restock_at?: string | null;
    owner_approval_required?: boolean;
    owner_approval_status?: string | null;
    approval_note?: string | null;
    approved_by_name?: string | null;
    approved_at?: string | null;
    verification_status?: string | null;
    verification_requested_at?: string | null;
    verification_requested_by_name?: string | null;
    verification_note?: string | null;
    verification_transaction_id?: number | null;
    verified_quantity?: number | null;
    verified_at?: string | null;
    verified_by_name?: string | null;
    reopen_count?: number;
    resolution_type?: string | null;
    resolution_note?: string | null;
    resolved_at?: string | null;
    resolved_by_name?: string | null;
};

type Summary = {
    active_cases: number;
    open_cases: number;
    in_progress_cases: number;
    negative_cases: number;
    negative_quantity: number;
    estimated_value: number;
    critical_cases: number;
    high_cases: number;
    pending_owner_approval: number;
    pending_verification: number;
    overdue_cases: number;
    due_today: number;
    resolved_last_30_days: number;
    resolved_value_last_30_days: number;
};

const props = defineProps<{
    cases: NegativeCase[];
    summary: Summary;
    branches: Array<{ id: number; name: string; is_central?: boolean }>;
    responsibleUsers: Array<{ id: number; name: string }>;
    filters: {
        branch_id?: number | null;
        status?: string;
        severity?: string | null;
    };
    scopeLabel: string;
    scopeType: 'all' | 'central' | 'branch';
    canManage: boolean;
    canApprove: boolean;
    canViewAllBranches: boolean;
    centralBranchId?: number | null;
    rootCauseOptions: Array<{ value: string; label: string }>;
}>();

const busyId = ref<number | null>(null);
const errorMessage = ref('');
const selectedBranchId = ref<number | null>(props.filters.branch_id ?? null);
const selectedStatus = ref(props.filters.status || 'active');
const selectedSeverity = ref(props.filters.severity || '');
const expandedId = ref<number | null>(null);
const detailLoadingId = ref<number | null>(null);
const details = ref<Record<number, any>>({});

const status = computed(() => selectedStatus.value || 'active');
const severity = computed(() => selectedSeverity.value || '');

const processSteps = [
    { num: '1', name: 'Phát hiện', desc: 'Hệ thống tự động ghi nhận tồn kho < 0' },
    { num: '2', name: 'Cảnh báo', desc: 'Thông báo đến bộ phận kho & chi nhánh' },
    { num: '3', name: 'Nhập bù kho', desc: 'Tạo đơn nhập hàng hoặc nhận điều chuyển' },
    { num: '4', name: 'Tự động chốt', desc: 'Tồn kho >= 0 hệ thống tự động đóng hồ sơ' },
];

const statusLabel = (value: string): string =>
    ({
        open: 'Cần nhập bù',
        in_progress: 'Đang xử lý',
        pending_owner_approval: 'Chờ duyệt',
        pending_verification: 'Chờ kiểm tra',
        resolved: 'Đã chốt xong',
    })[value] || value;

const statusClass = (value: string): string =>
    ({
        open: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300',
        in_progress: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        pending_owner_approval: 'border-purple-500/30 bg-purple-500/10 text-purple-700 dark:text-purple-300',
        pending_verification: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-700 dark:text-cyan-300',
        resolved: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    })[value] || 'border-border bg-muted text-muted-foreground';

const severityClass = (value: string): string =>
    ({
        critical: 'border-rose-500/30 bg-rose-500/15 text-rose-700 dark:text-rose-400 font-extrabold',
        high: 'border-amber-500/30 bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold',
        medium: 'border-indigo-500/30 bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 font-medium',
        low: 'border-slate-500/30 bg-slate-500/15 text-slate-700 dark:text-slate-400 font-medium',
    })[value] || 'border-border bg-muted text-muted-foreground';

function formatQuantity(value: number | undefined): string {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        value || 0,
    );
}

function formatCurrency(value: number | undefined): string {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function applyFilters(): void {
    const params: Record<string, string | number> = {};

    if (props.canViewAllBranches && selectedBranchId.value) {
        params.branch_id = selectedBranchId.value;
    }

    if (status.value !== 'active') {
        params.status = status.value;
    }

    if (severity.value) {
        params.severity = severity.value;
    }

    router.get('/inventory/negative-stock', params, {
        preserveState: true,
        replace: true,
    });
}

async function submitVerification(item: NegativeCase): Promise<void> {
    const note = window.prompt(
        'Ghi chú đối chiếu (tối thiểu 10 ký tự):',
        'Đã kiểm tra giao dịch bù và xác nhận tồn hiện tại không còn âm.',
    );

    if (!note || note.trim().length < 10) {
        return;
    }

    busyId.value = item.id;
    errorMessage.value = '';

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/submit-verification`,
            { note },
        );
        await router.reload({ only: ['cases', 'summary'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Chưa thể gửi hồ sơ sang bước đối chiếu.';
    } finally {
        busyId.value = null;
    }
}

async function verifyCase(item: NegativeCase): Promise<void> {
    const note = window.prompt(
        'Ghi chú xác minh & chốt (tối thiểu 10 ký tự):',
        'Đã đối chiếu giao dịch, kiểm tra thực tế và xác nhận số dư đúng.',
    );

    if (!note || note.trim().length < 10) {
        return;
    }

    busyId.value = item.id;
    errorMessage.value = '';

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/verify`,
            {
                resolution_type: 'verified',
                resolution_note: note,
            },
        );
        await router.reload({ only: ['cases', 'summary'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Chưa thể xác minh hồ sơ.';
    } finally {
        busyId.value = null;
    }
}

async function toggleDetail(item: NegativeCase): Promise<void> {
    if (expandedId.value === item.id) {
        expandedId.value = null;

        return;
    }

    expandedId.value = item.id;

    if (details.value[item.id]) {
        return;
    }

    detailLoadingId.value = item.id;

    try {
        const response = await axios.get(
            `/api/inventory/negative-stock-cases/${item.id}`,
        );
        details.value[item.id] = response.data.case;
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message || 'Không tải được timeline hồ sơ.';
    } finally {
        detailLoadingId.value = null;
    }
}

async function approveCase(
    item: NegativeCase,
    decision: 'approve' | 'reject',
): Promise<void> {
    const note = window.prompt(
        decision === 'approve'
            ? 'Ghi chú phê duyệt (tối thiểu 10 ký tự):'
            : 'Nêu lý do từ chối (tối thiểu 10 ký tự):',
        decision === 'approve'
            ? 'Đã kiểm tra phương án và cho phép tiếp tục xử lý.'
            : '',
    );

    if (!note || note.trim().length < 10) {
        return;
    }

    busyId.value = item.id;
    errorMessage.value = '';

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/approve`,
            { decision, note },
        );
        await router.reload({ only: ['cases', 'summary'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message || 'Không thể cập nhật phê duyệt.';
    } finally {
        busyId.value = null;
    }
}

function canResolve(item: NegativeCase): boolean {
    return (
        props.canManage &&
        item.status === 'pending_verification' &&
        item.on_hand >= 0
    );
}

const summaryCards = computed(() => [
    {
        title: 'Hồ sơ đang mở',
        value: props.summary.active_cases,
        subtitle: `${props.summary.open_cases ?? 0} mặt hàng cần nhập bù`,
        colorClass: 'text-rose-600 dark:text-rose-400',
        badge: 'Cần xử lý',
        badgeClass: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
        icon: AlertTriangle,
        iconBg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20',
    },
    {
        title: 'Đang âm thực tế',
        value: props.summary.negative_cases,
        subtitle: `${formatQuantity(props.summary.negative_quantity)} đv nguyên liệu`,
        colorClass: 'text-amber-600 dark:text-amber-400',
        badge: 'Tồn kho âm',
        badgeClass: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
        icon: FileWarning,
        iconBg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
    },
    {
        title: 'Giá trị âm ước tính',
        value: formatCurrency(props.summary.estimated_value),
        subtitle: 'Thiệt hại/chênh lệch dự kiến',
        colorClass: 'text-foreground',
        badge: 'Tài chính',
        badgeClass: 'bg-muted text-muted-foreground border-border',
        icon: Layers,
        iconBg: 'bg-muted text-muted-foreground border border-border',
    },
    {
        title: 'Chờ Chủ duyệt',
        value: props.summary.pending_owner_approval,
        subtitle: `Cao ${props.summary.high_cases} · Critical ${props.summary.critical_cases}`,
        colorClass: 'text-purple-600 dark:text-purple-400',
        badge: 'Cấp thẩm quyền',
        badgeClass: 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border-purple-500/20',
        icon: ShieldAlert,
        iconBg: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20',
    },
    {
        title: 'Chờ đối chiếu',
        value: props.summary.pending_verification,
        subtitle: `Quá hạn: ${props.summary.overdue_cases} hồ sơ`,
        colorClass: 'text-cyan-600 dark:text-cyan-400',
        badge: 'Kiểm toán',
        badgeClass: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-400 border-cyan-500/20',
        icon: ShieldCheck,
        iconBg: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20',
    },
    {
        title: 'Đã chốt 30 ngày',
        value: props.summary.resolved_last_30_days,
        subtitle: formatCurrency(props.summary.resolved_value_last_30_days),
        colorClass: 'text-emerald-600 dark:text-emerald-400',
        badge: 'Hoàn tất',
        badgeClass: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
        icon: CheckCircle2,
        iconBg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
    },
]);
</script>

<template>
    <Head title="Trung tâm xử lý âm nguyên liệu" />

    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6 pb-16">
        <!-- ── 1. HEADER CHUẨN ENTERPRISE ─────────────────────────────── -->
        <header
            class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-muted/30 p-5.5 shadow-xs sm:flex-row sm:items-center"
        >
            <div class="pointer-events-none absolute -top-12 -right-12 size-48 rounded-full bg-amber-500/10 blur-2xl" />

            <div class="relative flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground mb-1.5">
                        <Link
                            href="/inventory"
                            class="inline-flex items-center gap-1 font-semibold text-muted-foreground hover:text-foreground transition"
                        >
                            <ArrowLeft class="size-3.5" /> Tồn kho
                        </Link>
                        <span>/</span>
                        <span class="font-medium text-foreground">Kiểm soát âm nguyên liệu</span>
                    </div>

                    <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl lg:text-3xl">
                        Trung tâm xử lý âm nguyên liệu
                    </h1>

                    <p class="mt-1 max-w-3xl text-xs text-muted-foreground leading-relaxed">
                        Theo dõi các nguyên liệu bị âm kho do xuất bán trước hoặc nhập trễ. Khi nhập hàng bù vào kho (tồn kho ≥ 0), hệ thống sẽ tự động hoàn tất và đóng hồ sơ.
                    </p>
                </div>

                <!-- Scope Pill -->
                <div
                    class="rounded-xl border border-border bg-background/80 px-4 py-2.5 text-xs shadow-xs backdrop-blur-xs"
                >
                    <div class="text-[10px] font-bold tracking-wider uppercase text-muted-foreground">
                        Phạm vi tài khoản
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 font-bold text-foreground">
                        <ShieldCheck class="size-4 text-primary" />
                        {{ scopeLabel }}
                    </div>
                </div>
            </div>
        </header>

        <!-- ── 2. PROCESS STEPPER CARD (QUY TRÌNH XỬ LÝ TỰ ĐỘNG) ───────── -->
        <Card class="overflow-hidden border-border/80 bg-gradient-to-b from-card to-card/90 shadow-xs">
            <CardHeader class="border-b border-border/60 bg-muted/20 py-3.5 px-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                        <Sparkles class="size-3.5 text-primary" />
                        Quy trình xử lý âm tồn kho tự động
                    </div>
                    <span class="text-[11px] font-medium text-muted-foreground">
                        Nhập hàng bù vào kho để hệ thống tự động đưa số tồn về bình thường và đóng hồ sơ
                    </span>
                </div>
            </CardHeader>

            <CardContent class="p-4 sm:p-5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="step in processSteps"
                        :key="step.num"
                        class="rounded-xl border border-border/70 bg-muted/20 p-3 shadow-xs transition hover:border-primary/40 hover:bg-muted/30"
                    >
                        <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-[10px] font-black text-primary-foreground"
                            >
                                {{ step.num }}
                            </span>
                            {{ step.name }}
                        </div>
                        <p class="mt-1.5 text-[11px] leading-snug text-muted-foreground font-medium">
                            {{ step.desc }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ── 3. 6 KPI METRICS SUMMARY CARDS ──────────────────────────── -->
        <section class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-6">
            <div
                v-for="card in summaryCards"
                :key="card.title"
                class="rounded-2xl border border-border/80 bg-card p-4 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
            >
                <div class="flex items-center justify-between gap-2">
                    <span
                        class="rounded-md border px-1.5 py-0.5 text-[9px] font-bold uppercase"
                        :class="card.badgeClass"
                    >
                        {{ card.badge }}
                    </span>
                    <div
                        class="flex size-7 items-center justify-center rounded-lg"
                        :class="card.iconBg"
                    >
                        <component :is="card.icon" class="size-3.5" />
                    </div>
                </div>

                <p class="mt-3 text-[11px] font-semibold text-muted-foreground">
                    {{ card.title }}
                </p>

                <p class="mt-1 text-2xl font-black tracking-tight" :class="card.colorClass">
                    {{ card.value }}
                </p>

                <p class="mt-1 truncate text-[11px] font-medium text-muted-foreground" :title="card.subtitle">
                    {{ card.subtitle }}
                </p>
            </div>
        </section>

        <!-- ── 4. DANH SÁCH HỒ SƠ ÂM TỒN ───────────────────────────────── -->
        <Card class="border-border/80 bg-card shadow-xs">
            <CardHeader class="border-b border-border/60 pb-3.5">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                        <FileWarning class="size-4 text-rose-500" />
                        Danh sách hồ sơ âm tồn
                    </CardTitle>

                    <!-- Filter Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-if="canViewAllBranches"
                            v-model="selectedBranchId"
                            class="h-9 rounded-xl border border-border bg-background px-3 text-xs text-foreground font-medium outline-none focus:border-primary"
                            @change="applyFilters"
                        >
                            <option :value="null">Toàn hệ thống</option>
                            <option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}{{ branch.is_central ? ' (Kho Tổng)' : '' }}
                            </option>
                        </select>

                        <select
                            v-model="selectedStatus"
                            class="h-9 rounded-xl border border-border bg-background px-3 text-xs text-foreground font-medium outline-none focus:border-primary"
                            @change="applyFilters"
                        >
                            <option value="active">Hồ sơ đang mở</option>
                            <option value="resolved">Đã chốt xong</option>
                            <option value="all">Tất cả hồ sơ</option>
                        </select>

                        <select
                            v-model="selectedSeverity"
                            class="h-9 rounded-xl border border-border bg-background px-3 text-xs text-foreground font-medium outline-none focus:border-primary"
                            @change="applyFilters"
                        >
                            <option value="">Tất cả mức độ</option>
                            <option value="critical">Critical (Nghiêm trọng)</option>
                            <option value="high">Cao</option>
                            <option value="medium">Trung bình</option>
                            <option value="low">Thấp</option>
                        </select>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 p-5">
                <!-- Empty state -->
                <div
                    v-if="!cases.length"
                    class="rounded-2xl border border-dashed border-emerald-500/30 bg-emerald-500/[0.03] p-10 text-center"
                >
                    <CheckCircle2 class="mx-auto size-9 text-emerald-500" />
                    <h3 class="mt-2 text-sm font-bold text-foreground">
                        Không có hồ sơ âm nguyên liệu
                    </h3>
                    <p class="mt-1 text-xs text-muted-foreground max-w-md mx-auto">
                        Hệ thống đang theo dõi tồn kho theo thời gian thực; khi bất kỳ chi nhánh hoặc kho nào xuống dưới 0, hồ sơ sẽ tự động xuất hiện tại đây.
                    </p>
                </div>

                <!-- Case items -->
                <div
                    v-for="item in cases"
                    :key="item.id"
                    class="rounded-2xl border bg-card p-4 transition-all duration-200 hover:shadow-xs"
                    :class="
                        item.severity === 'critical'
                            ? 'border-rose-500/40 bg-rose-500/[0.02]'
                            : 'border-border/80 hover:border-border'
                    "
                >
                    <!-- Header of case item -->
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-bold text-foreground text-sm">
                                    {{ item.ingredient_name || 'Nguyên liệu' }}
                                </h3>
                                <span class="font-mono text-[11px] text-muted-foreground font-semibold">
                                    {{ item.case_code }}
                                </span>
                                <span
                                    class="rounded-md border px-2 py-0.5 text-[10px]"
                                    :class="severityClass(item.severity)"
                                >
                                    {{ item.severity_label }}
                                </span>
                                <span
                                    class="rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                    :class="statusClass(item.status)"
                                >
                                    {{ statusLabel(item.status) }}
                                </span>
                            </div>

                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground font-medium">
                                <span class="font-semibold text-foreground">
                                    {{ item.branch_name || 'Kho chưa xác định' }}
                                </span>
                                <span>· {{ item.source_label }}</span>
                                <span>· Phát hiện {{ item.detected_at || '—' }}</span>
                                <span
                                    :class="item.is_overdue ? 'font-bold text-rose-600 dark:text-rose-400' : ''"
                                >
                                    · Hạn SLA: {{ item.due_at || '—' }}{{ item.is_overdue ? ' (QUÁ HẠN)' : '' }}
                                </span>
                            </div>
                        </div>

                        <!-- Right Stats -->
                        <div class="text-right text-xs">
                            <div
                                v-if="item.on_hand < 0"
                                class="text-sm font-black text-rose-600 dark:text-rose-400"
                            >
                                Âm {{ formatQuantity(item.negative_quantity) }} {{ item.unit_symbol || '' }}
                            </div>
                            <div
                                v-else
                                class="text-sm font-black text-emerald-600 dark:text-emerald-400"
                            >
                                Đã bù tồn, chờ chốt
                            </div>
                            <div class="mt-0.5 text-xs text-muted-foreground font-medium">
                                Giá trị: <strong class="text-foreground">{{ formatCurrency(item.estimated_value) }}</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Info Columns Grid -->
                    <div class="mt-3.5 grid gap-2.5 text-xs md:grid-cols-2">
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-3">
                            <div class="font-bold text-foreground">
                                Hướng xử lý bù tồn
                            </div>
                            <div class="mt-1 text-muted-foreground font-medium leading-relaxed">
                                Nhập hàng nhà cung cấp hoặc nhận điều chuyển từ Kho Tổng để bù đủ
                                <strong class="text-rose-600 dark:text-rose-400">{{ formatQuantity(item.negative_quantity) }} {{ item.unit_symbol || '' }}</strong>.
                                Khi tồn kho thực tế trở về mức ≥ 0, hệ thống sẽ tự động đóng hồ sơ.
                            </div>
                        </div>

                        <div class="rounded-xl border border-border/60 bg-muted/20 p-3 flex flex-col justify-between">
                            <div>
                                <div class="font-bold text-foreground">
                                    Thông tin nguồn & Chi nhánh
                                </div>
                                <div class="mt-1 text-muted-foreground font-medium">
                                    {{ item.branch_name || 'Kho chưa xác định' }} · Nguồn: {{ item.source_label }}
                                </div>
                                <div class="mt-0.5 text-muted-foreground">
                                    Phát hiện: {{ item.detected_at || '—' }} · Hạn xử lý: {{ item.due_at || '—' }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resolved Banner -->
                    <div
                        v-if="item.status === 'resolved'"
                        class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/[0.04] p-3 text-xs text-emerald-800 dark:text-emerald-300 font-medium"
                    >
                        Đã chốt xong {{ item.resolved_at || '' }}
                        <span v-if="item.resolved_by_name">bởi {{ item.resolved_by_name }}</span>. {{ item.resolution_note || '' }}
                    </div>

                    <!-- Timeline Details -->
                    <div
                        v-if="expandedId === item.id"
                        class="mt-3.5 rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs shadow-inner"
                    >
                        <div v-if="detailLoadingId === item.id" class="text-muted-foreground font-medium">
                            Đang tải lịch sử giao dịch & timeline...
                        </div>
                        <template v-else-if="details[item.id]">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <div class="font-bold text-foreground">
                                        Giao dịch nguồn làm âm tồn
                                    </div>
                                    <div
                                        v-if="details[item.id].source_transaction"
                                        class="mt-1.5 text-muted-foreground font-medium"
                                    >
                                        {{
                                            details[item.id].source_transaction.document_code ||
                                            `#${details[item.id].source_transaction.id}`
                                        }}
                                        · {{ details[item.id].source_transaction.type }}
                                        · {{ formatQuantity(details[item.id].source_transaction.quantity) }}
                                        · {{ details[item.id].source_transaction.occurred_at }}
                                    </div>
                                    <div v-else class="mt-1 text-muted-foreground">
                                        Chưa xác định được giao dịch nguồn.
                                    </div>

                                    <div class="mt-3.5 font-bold text-foreground">
                                        Giao dịch bù / điều chỉnh gần nhất
                                    </div>
                                    <div
                                        v-for="transaction in details[item.id].transactions?.slice(0, 5)"
                                        :key="transaction.id"
                                        class="mt-1 text-muted-foreground font-medium"
                                    >
                                        {{ transaction.document_code || `#${transaction.id}` }}
                                        · {{ transaction.direction === 'in' ? 'Nhập bù' : 'Xuất' }}
                                        {{ formatQuantity(transaction.quantity) }}
                                        · {{ transaction.occurred_at }}
                                    </div>
                                </div>

                                <div>
                                    <div class="font-bold text-foreground">
                                        Timeline nghiệp vụ & kiểm toán
                                    </div>
                                    <div
                                        v-for="event in details[item.id].timeline?.slice(0, 6)"
                                        :key="event.id"
                                        class="mt-2.5 border-l-2 border-primary/50 pl-2.5"
                                    >
                                        <div class="font-bold text-foreground">
                                            {{ event.event_label }}
                                        </div>
                                        <div class="text-[11px] text-muted-foreground">
                                            {{ event.created_at }} · {{ event.actor_name }}
                                        </div>
                                        <div v-if="event.note" class="mt-0.5 text-muted-foreground font-medium">
                                            {{ event.note }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Action Buttons Toolbar -->
                    <div
                        v-if="item.status !== 'resolved'"
                        class="mt-3.5 flex flex-wrap items-center gap-2"
                    >
                        <Link
                            v-if="canManage"
                            href="/inventory?tab=purchase"
                            class="inline-flex items-center h-8.5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 text-xs font-bold text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20 shadow-xs transition"
                        >
                            <ShoppingCart class="mr-1.5 size-3.5 text-emerald-500" />
                            Tạo đơn nhập bù kho
                        </Link>

                        <template
                            v-if="item.status === 'pending_owner_approval' && canApprove"
                        >
                            <Button
                                size="sm"
                                class="h-8.5 rounded-lg bg-primary text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90"
                                :disabled="busyId === item.id"
                                @click="approveCase(item, 'approve')"
                            >
                                <ShieldCheck class="mr-1.5 size-3.5" /> Phê duyệt
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                class="h-8.5 rounded-lg text-xs font-bold shadow-xs"
                                :disabled="busyId === item.id"
                                @click="approveCase(item, 'reject')"
                            >
                                Từ chối
                            </Button>
                        </template>

                        <Button
                            v-if="
                                canManage &&
                                item.status === 'in_progress' &&
                                item.on_hand >= 0
                            "
                            size="sm"
                            class="h-8.5 rounded-lg bg-emerald-600 text-xs font-bold text-white shadow-xs hover:bg-emerald-700"
                            :disabled="busyId === item.id"
                            @click="submitVerification(item)"
                        >
                            <ShieldCheck class="mr-1.5 size-3.5" /> Gửi đối chiếu
                        </Button>

                        <Button
                            v-if="canResolve(item)"
                            size="sm"
                            class="h-8.5 rounded-lg bg-primary text-xs font-bold text-primary-foreground shadow-xs hover:bg-primary/90"
                            :disabled="busyId === item.id"
                            @click="verifyCase(item)"
                        >
                            <CheckCircle2 class="mr-1.5 size-3.5" /> Xác minh & chốt
                        </Button>

                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8.5 rounded-lg text-xs text-muted-foreground hover:text-foreground"
                            :disabled="detailLoadingId === item.id"
                            @click="toggleDetail(item)"
                        >
                            <History class="mr-1.5 size-3.5" />
                            {{ expandedId === item.id ? 'Ẩn timeline' : 'Xem timeline & giao dịch' }}
                        </Button>

                        <span
                            v-if="item.status === 'pending_owner_approval' && !canApprove"
                            class="text-xs font-medium text-purple-700 dark:text-purple-300"
                        >
                            Đã gửi Chủ doanh nghiệp phê duyệt; chưa được tự chốt.
                        </span>
                    </div>

                    <div
                        v-else-if="item.status === 'resolved'"
                        class="mt-3.5 flex flex-wrap items-center gap-2"
                    >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8.5 rounded-lg text-xs text-muted-foreground hover:text-foreground"
                            :disabled="detailLoadingId === item.id"
                            @click="toggleDetail(item)"
                        >
                            <History class="mr-1.5 size-3.5" />
                            {{ expandedId === item.id ? 'Ẩn timeline' : 'Xem timeline & giao dịch' }}
                        </Button>
                        <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                            Đã xác minh{{ item.verified_by_name ? ` bởi ${item.verified_by_name}` : '' }}.
                        </span>
                    </div>
                </div>

                <p v-if="errorMessage" class="text-xs font-bold text-rose-600 dark:text-rose-400">
                    {{ errorMessage }}
                </p>
            </CardContent>
        </Card>

        <!-- ── 5. NGUYÊN TẮC CHỐT HỒ SƠ ────────────────────────────────── -->
        <Card class="rounded-2xl border-border/80 bg-gradient-to-br from-card via-card to-muted/20 shadow-xs">
            <CardContent class="flex flex-wrap items-start gap-3.5 p-4.5 text-xs text-muted-foreground">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 class="size-4" />
                </div>
                <div class="leading-relaxed">
                    <strong class="text-foreground">Cơ chế xử lý âm kho tự động:</strong> Khi phát sinh giao dịch nhập kho nhà cung cấp, kiểm kê hoặc nhận điều chuyển từ Kho Tổng đưa số lượng tồn của nguyên liệu về mức không còn âm (≥ 0), hệ thống sẽ tự động cập nhật và đóng hồ sơ sự vụ mà không cần phải lập phương án giải trình thủ công.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
