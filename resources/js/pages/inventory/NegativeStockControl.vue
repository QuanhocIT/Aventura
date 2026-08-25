<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    Clock3,
    FileWarning,
    ShieldCheck,
    UserRound,
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

const editingId = ref<number | null>(null);
const busyId = ref<number | null>(null);
const plans = ref<Record<number, string>>({});
const causes = ref<Record<number, string>>({});
const causeCodes = ref<Record<number, string>>({});
const containmentActions = ref<Record<number, string>>({});
const correctiveActions = ref<Record<number, string>>({});
const dates = ref<Record<number, string>>({});
const responsibleIds = ref<Record<number, number | null>>({});
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
    ['1', 'Phát hiện', 'Hệ thống ghi nhận tồn < 0'],
    ['2', 'Điều tra', 'Phân loại nguyên nhân & giao việc'],
    ['3', 'Phê duyệt', 'Cao/Critical cần Chủ duyệt'],
    ['4', 'Khắc phục', 'Nhập bù/điều chỉnh bằng giao dịch thật'],
    ['5', 'Đối chiếu', 'Người độc lập kiểm tra'],
    ['6', 'Chốt', 'Đóng hồ sơ và lưu dấu vết'],
];

const statusLabel = (value: string): string =>
    ({
        open: 'Chưa lập phương án',
        in_progress: 'Đang xử lý',
        pending_owner_approval: 'Chờ Chủ doanh nghiệp duyệt',
        pending_verification: 'Chờ đối chiếu độc lập',
        resolved: 'Đã chốt',
    })[value] || value;

const severityClass = (value: string): string =>
    ({
        critical:
            'border-red-300 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300',
        high: 'border-orange-300 bg-orange-100 text-orange-800 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-300',
        medium: 'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        low: 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
    })[value] || 'border-border bg-muted text-foreground';

const statusClass = (value: string): string =>
    value === 'resolved'
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
        : value === 'pending_owner_approval'
          ? 'bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300'
          : value === 'pending_verification'
            ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-300'
            : value === 'in_progress'
              ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
              : 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300';

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        value || 0,
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function planDraft(item: NegativeCase): string {
    return plans.value[item.id] ?? item.handling_plan ?? item.auto_plan ?? '';
}

function beginEdit(item: NegativeCase): void {
    editingId.value = item.id;
    plans.value[item.id] = planDraft(item);
    causes.value[item.id] = item.root_cause ?? '';
    causeCodes.value[item.id] = item.root_cause_code ?? 'unknown';
    containmentActions.value[item.id] = item.containment_action ?? '';
    correctiveActions.value[item.id] = item.corrective_action ?? '';
    dates.value[item.id] = item.expected_restock_at ?? '';
    responsibleIds.value[item.id] = item.responsible_user_id ?? null;
    errorMessage.value = '';
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

async function savePlan(item: NegativeCase): Promise<void> {
    const handlingPlan = planDraft(item).trim();

    if (handlingPlan.length < 10) {
        errorMessage.value = 'Phương án xử lý cần tối thiểu 10 ký tự.';

        return;
    }

    errorMessage.value = '';
    busyId.value = item.id;

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/plan`,
            {
                handling_plan: handlingPlan,
                root_cause: causes.value[item.id] || null,
                responsible_user_id: responsibleIds.value[item.id] || null,
                expected_restock_at: dates.value[item.id] || null,
                root_cause_code: causeCodes.value[item.id] || null,
                containment_action: containmentActions.value[item.id] || null,
                corrective_action: correctiveActions.value[item.id] || null,
            },
        );
        editingId.value = null;
        await router.reload({ only: ['cases', 'summary'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Không thể lưu phương án xử lý.';
    } finally {
        busyId.value = null;
    }
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
</script>

<template>
    <Head title="Trung tâm xử lý âm nguyên liệu" />

    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <Link
                        href="/inventory"
                        class="inline-flex items-center gap-1 hover:text-foreground"
                    >
                        <ArrowLeft class="size-3.5" /> Tồn kho
                    </Link>
                    <span>/</span>
                    <span>Kiểm soát âm nguyên liệu</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    Trung tâm xử lý âm nguyên liệu
                </h1>
                <p class="mt-1 max-w-3xl text-sm text-muted-foreground">
                    Một hồ sơ cho mọi trường hợp âm: xác định nguyên nhân, lập
                    phương án bù/điều chỉnh, phê duyệt theo mức độ và chỉ chốt
                    sau khi tồn thực tế không còn âm.
                </p>
            </div>
            <div
                class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-200"
            >
                <div
                    class="text-[11px] font-semibold tracking-wider uppercase opacity-70"
                >
                    Phạm vi tài khoản
                </div>
                <div class="mt-1 flex items-center gap-2 font-semibold">
                    <ShieldCheck class="size-4" /> {{ scopeLabel }}
                </div>
            </div>
        </div>

        <Card
            class="border-indigo-200 bg-indigo-50/50 dark:border-indigo-900 dark:bg-indigo-950/20"
        >
            <CardContent class="p-4">
                <div
                    class="mb-3 flex flex-wrap items-center justify-between gap-2"
                >
                    <div
                        class="text-sm font-semibold text-indigo-950 dark:text-indigo-100"
                    >
                        Quy trình chuẩn 6 bước
                    </div>
                    <div
                        class="text-xs text-indigo-700/80 dark:text-indigo-300/80"
                    >
                        Không được bỏ qua bước giao dịch kho và đối chiếu
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <div
                        v-for="step in processSteps"
                        :key="step[0]"
                        class="rounded-lg border border-indigo-200/80 bg-background/70 p-3 dark:border-indigo-800"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-bold text-indigo-700 dark:text-indigo-300"
                        >
                            <span
                                class="flex size-5 items-center justify-center rounded-full bg-indigo-600 text-[10px] text-white"
                                >{{ step[0] }}</span
                            >{{ step[1] }}
                        </div>
                        <div
                            class="mt-1 text-[11px] leading-4 text-muted-foreground"
                        >
                            {{ step[2] }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <Card
                class="border-red-200 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Hồ sơ đang mở
                    </div>
                    <div
                        class="mt-1 text-2xl font-bold text-red-700 dark:text-red-300"
                    >
                        {{ summary.active_cases }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-orange-200 bg-orange-50/60 dark:border-orange-900 dark:bg-orange-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Đang âm thực tế
                    </div>
                    <div
                        class="mt-1 text-2xl font-bold text-orange-700 dark:text-orange-300"
                    >
                        {{ summary.negative_cases }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ formatQuantity(summary.negative_quantity) }} đơn vị
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-amber-200 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Giá trị âm ước tính
                    </div>
                    <div
                        class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300"
                    >
                        {{ formatCurrency(summary.estimated_value) }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-purple-200 bg-purple-50/60 dark:border-purple-900 dark:bg-purple-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Chờ Chủ duyệt
                    </div>
                    <div
                        class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300"
                    >
                        {{ summary.pending_owner_approval }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Cao {{ summary.high_cases }} · Critical
                        {{ summary.critical_cases }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-cyan-200 bg-cyan-50/60 dark:border-cyan-900 dark:bg-cyan-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Chờ đối chiếu
                    </div>
                    <div
                        class="mt-1 text-2xl font-bold text-cyan-700 dark:text-cyan-300"
                    >
                        {{ summary.pending_verification }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        Quá hạn {{ summary.overdue_cases }}
                    </div></CardContent
                >
            </Card>
            <Card
                class="border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20"
            >
                <CardContent class="p-4"
                    ><div class="text-xs text-muted-foreground">
                        Đã chốt 30 ngày
                    </div>
                    <div
                        class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300"
                    >
                        {{ summary.resolved_last_30_days }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{
                            formatCurrency(summary.resolved_value_last_30_days)
                        }}
                    </div></CardContent
                >
            </Card>
        </div>

        <Card>
            <CardHeader
                class="gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <CardTitle class="flex items-center gap-2 text-base"
                    ><FileWarning class="size-4 text-red-500" /> Danh sách hồ
                    sơ</CardTitle
                >
                <div class="flex flex-wrap gap-2">
                    <select
                        v-if="canViewAllBranches"
                        v-model="selectedBranchId"
                        class="h-9 rounded-md border bg-background px-3 text-xs"
                        @change="applyFilters"
                    >
                        <option :value="null">Toàn hệ thống</option>
                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name
                            }}{{ branch.is_central ? ' (Kho Tổng)' : '' }}
                        </option>
                    </select>
                    <select
                        v-model="selectedStatus"
                        class="h-9 rounded-md border bg-background px-3 text-xs"
                        @change="applyFilters"
                    >
                        <option value="active">Hồ sơ đang mở</option>
                        <option value="resolved">Đã chốt</option>
                        <option value="all">Tất cả</option>
                    </select>
                    <select
                        v-model="selectedSeverity"
                        class="h-9 rounded-md border bg-background px-3 text-xs"
                        @change="applyFilters"
                    >
                        <option value="">Tất cả mức độ</option>
                        <option value="critical">Critical</option>
                        <option value="high">Cao</option>
                        <option value="medium">Trung bình</option>
                        <option value="low">Thấp</option>
                    </select>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="!cases.length"
                    class="rounded-xl border border-dashed border-emerald-300 bg-emerald-50 p-8 text-center dark:border-emerald-800 dark:bg-emerald-950/20"
                >
                    <CheckCircle2 class="mx-auto size-8 text-emerald-500" />
                    <div
                        class="mt-2 font-semibold text-emerald-800 dark:text-emerald-300"
                    >
                        Không có hồ sơ phù hợp
                    </div>
                    <p
                        class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80"
                    >
                        Hệ thống vẫn theo dõi tồn thực tế; khi bất kỳ kho nào
                        xuống dưới 0, hồ sơ sẽ tự động xuất hiện tại đây.
                    </p>
                </div>

                <div
                    v-for="item in cases"
                    :key="item.id"
                    class="rounded-xl border bg-card p-4 shadow-sm"
                    :class="
                        item.severity === 'critical'
                            ? 'border-red-300 dark:border-red-800'
                            : 'border-border'
                    "
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold">
                                    {{ item.ingredient_name || 'Nguyên liệu' }}
                                </h3>
                                <span
                                    class="font-mono text-[11px] text-muted-foreground"
                                    >{{ item.case_code }}</span
                                >
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                    :class="severityClass(item.severity)"
                                    >{{ item.severity_label }}</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusClass(item.status)"
                                    >{{ statusLabel(item.status) }}</span
                                >
                            </div>
                            <div
                                class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span>{{
                                    item.branch_name || 'Kho chưa xác định'
                                }}</span>
                                <span>{{ item.source_label }}</span>
                                <span
                                    >Phát hiện
                                    {{ item.detected_at || '—' }}</span
                                >
                                <span
                                    :class="
                                        item.is_overdue
                                            ? 'font-semibold text-red-600 dark:text-red-400'
                                            : ''
                                    "
                                    >Hạn SLA {{ item.due_at || '—'
                                    }}{{
                                        item.is_overdue ? ' · QUÁ HẠN' : ''
                                    }}</span
                                >
                            </div>
                        </div>
                        <div class="text-right text-xs">
                            <div
                                v-if="item.on_hand < 0"
                                class="font-bold text-red-600 dark:text-red-400"
                            >
                                Âm {{ formatQuantity(item.negative_quantity) }}
                                {{ item.unit_symbol || '' }}
                            </div>
                            <div
                                v-else
                                class="font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                Đã bù tồn, chờ chốt
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                Giá trị
                                {{ formatCurrency(item.estimated_value) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 text-xs md:grid-cols-4">
                        <div class="rounded-lg bg-muted/50 p-3">
                            <div class="font-semibold text-foreground">
                                Cách xử lý bắt buộc
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                {{ item.auto_plan }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <div class="font-semibold text-foreground">
                                Người phụ trách
                            </div>
                            <div
                                class="mt-1 flex items-center gap-1 text-muted-foreground"
                            >
                                <UserRound class="size-3.5" />
                                {{
                                    item.responsible_user_name ||
                                    'Chưa giao người phụ trách'
                                }}
                            </div>
                            <div
                                v-if="item.expected_restock_at"
                                class="mt-1 flex items-center gap-1 text-muted-foreground"
                            >
                                <Clock3 class="size-3.5" /> Bù dự kiến
                                {{ item.expected_restock_at }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <div class="font-semibold text-foreground">
                                Nguyên nhân & kiểm soát
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                {{
                                    item.root_cause_label ||
                                    'Chưa phân loại nguyên nhân'
                                }}
                            </div>
                            <div v-if="item.root_cause" class="mt-1">
                                {{ item.root_cause }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-muted/50 p-3">
                            <div class="font-semibold text-foreground">
                                Phê duyệt / đối chiếu
                            </div>
                            <div class="mt-1 text-muted-foreground">
                                {{
                                    item.owner_approval_status === 'approved'
                                        ? `Đã duyệt${item.approved_by_name ? ` bởi ${item.approved_by_name}` : ''}`
                                        : item.owner_approval_required
                                          ? 'Bắt buộc Chủ doanh nghiệp duyệt'
                                          : 'Không yêu cầu duyệt cấp Chủ'
                                }}
                            </div>
                            <div
                                v-if="item.status === 'pending_verification'"
                                class="mt-1 font-medium text-cyan-700 dark:text-cyan-300"
                            >
                                Đã gửi
                                {{ item.verification_requested_at || '' }}, chờ
                                người khác xác minh
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="editingId === item.id"
                        class="mt-4 space-y-2 rounded-lg border border-indigo-200 bg-indigo-50/40 p-3 dark:border-indigo-900 dark:bg-indigo-950/20"
                    >
                        <div class="grid gap-2 md:grid-cols-2">
                            <textarea
                                v-model="plans[item.id]"
                                rows="4"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                placeholder="Phương án bù/điều chỉnh, giao dịch cần thực hiện và cách đối chiếu..."
                            />
                            <div class="space-y-2">
                                <select
                                    v-model="causeCodes[item.id]"
                                    class="h-9 w-full rounded-md border bg-background px-3 text-xs"
                                >
                                    <option value="">
                                        Chọn nguyên nhân gốc bắt buộc
                                    </option>
                                    <option
                                        v-for="option in rootCauseOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <textarea
                                    v-model="causes[item.id]"
                                    rows="3"
                                    class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                    placeholder="Mô tả bằng chứng/nguyên nhân cụ thể..."
                                />
                            </div>
                        </div>
                        <div class="grid gap-2 md:grid-cols-2">
                            <textarea
                                v-model="containmentActions[item.id]"
                                rows="2"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                placeholder="Hành động tức thời: khóa bán, kiểm tra lô, tạm dừng xuất..."
                            />
                            <textarea
                                v-model="correctiveActions[item.id]"
                                rows="2"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring"
                                placeholder="Hành động phòng ngừa tái diễn: sửa BOM, đào tạo, chỉnh quy trình..."
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                v-model="responsibleIds[item.id]"
                                class="h-9 rounded-md border bg-background px-3 text-xs"
                            >
                                <option :value="null">Tôi phụ trách</option>
                                <option
                                    v-for="candidate in responsibleUsers"
                                    :key="candidate.id"
                                    :value="candidate.id"
                                >
                                    {{ candidate.name }}
                                </option>
                            </select>
                            <label
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                                >Ngày bù dự kiến
                                <input
                                    v-model="dates[item.id]"
                                    type="date"
                                    class="h-9 rounded border bg-background px-2 text-xs"
                            /></label>
                            <Button
                                size="sm"
                                :disabled="busyId === item.id"
                                @click="savePlan(item)"
                                ><ClipboardCheck class="mr-1 size-3.5" /> Lưu
                                phương án</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="editingId = null"
                                >Hủy</Button
                            >
                        </div>
                    </div>

                    <div
                        v-if="item.status === 'resolved'"
                        class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/20 dark:text-emerald-300"
                    >
                        Đã chốt {{ item.resolved_at || ''
                        }}<span v-if="item.resolved_by_name">
                            bởi {{ item.resolved_by_name }}</span
                        >. {{ item.resolution_note || '' }}
                    </div>

                    <div
                        v-if="expandedId === item.id"
                        class="mt-4 rounded-lg border border-slate-200 bg-slate-50/80 p-3 text-xs dark:border-slate-800 dark:bg-slate-950/40"
                    >
                        <div
                            v-if="detailLoadingId === item.id"
                            class="text-muted-foreground"
                        >
                            Đang tải timeline...
                        </div>
                        <template v-else-if="details[item.id]">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div>
                                    <div class="font-semibold">
                                        Giao dịch nguồn
                                    </div>
                                    <div
                                        v-if="
                                            details[item.id].source_transaction
                                        "
                                        class="mt-1 text-muted-foreground"
                                    >
                                        {{
                                            details[item.id].source_transaction
                                                .document_code ||
                                            `#${details[item.id].source_transaction.id}`
                                        }}
                                        ·
                                        {{
                                            details[item.id].source_transaction
                                                .type
                                        }}
                                        ·
                                        {{
                                            formatQuantity(
                                                details[item.id]
                                                    .source_transaction
                                                    .quantity,
                                            )
                                        }}
                                        ·
                                        {{
                                            details[item.id].source_transaction
                                                .occurred_at
                                        }}
                                    </div>
                                    <div
                                        v-else
                                        class="mt-1 text-muted-foreground"
                                    >
                                        Chưa xác định được giao dịch nguồn.
                                    </div>
                                    <div class="mt-3 font-semibold">
                                        Giao dịch bù/điều chỉnh gần nhất
                                    </div>
                                    <div
                                        v-for="transaction in details[
                                            item.id
                                        ].transactions?.slice(0, 5)"
                                        :key="transaction.id"
                                        class="mt-1 text-muted-foreground"
                                    >
                                        {{
                                            transaction.document_code ||
                                            `#${transaction.id}`
                                        }}
                                        ·
                                        {{
                                            transaction.direction === 'in'
                                                ? 'Nhập'
                                                : 'Xuất'
                                        }}
                                        {{
                                            formatQuantity(transaction.quantity)
                                        }}
                                        · {{ transaction.occurred_at }}
                                    </div>
                                </div>
                                <div>
                                    <div class="font-semibold">
                                        Timeline nghiệp vụ
                                    </div>
                                    <div
                                        v-for="event in details[
                                            item.id
                                        ].timeline?.slice(0, 6)"
                                        :key="event.id"
                                        class="mt-2 border-l-2 border-indigo-300 pl-2 dark:border-indigo-700"
                                    >
                                        <div class="font-medium">
                                            {{ event.event_label }}
                                        </div>
                                        <div class="text-muted-foreground">
                                            {{ event.created_at }} ·
                                            {{ event.actor_name }}
                                        </div>
                                        <div
                                            v-if="event.note"
                                            class="text-muted-foreground"
                                        >
                                            {{ event.note }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        v-if="
                            editingId !== item.id && item.status !== 'resolved'
                        "
                        class="mt-4 flex flex-wrap items-center gap-2"
                    >
                        <Button
                            v-if="canManage"
                            size="sm"
                            variant="outline"
                            @click="beginEdit(item)"
                            ><ClipboardCheck class="mr-1 size-3.5" />
                            {{
                                item.handling_plan
                                    ? 'Cập nhật phương án'
                                    : 'Lập phương án'
                            }}</Button
                        >
                        <template
                            v-if="
                                item.status === 'pending_owner_approval' &&
                                canApprove
                            "
                        >
                            <Button
                                size="sm"
                                :disabled="busyId === item.id"
                                @click="approveCase(item, 'approve')"
                                ><ShieldCheck class="mr-1 size-3.5" /> Phê
                                duyệt</Button
                            >
                            <Button
                                size="sm"
                                variant="destructive"
                                :disabled="busyId === item.id"
                                @click="approveCase(item, 'reject')"
                                >Từ chối</Button
                            >
                        </template>
                        <Button
                            v-if="
                                canManage &&
                                item.status === 'in_progress' &&
                                item.on_hand >= 0
                            "
                            size="sm"
                            variant="outline"
                            :disabled="busyId === item.id"
                            @click="submitVerification(item)"
                            ><ShieldCheck class="mr-1 size-3.5" /> Gửi đối
                            chiếu</Button
                        >
                        <Button
                            v-if="canResolve(item)"
                            size="sm"
                            variant="ghost"
                            :disabled="busyId === item.id"
                            @click="verifyCase(item)"
                            ><CheckCircle2 class="mr-1 size-3.5" /> Xác minh &
                            chốt</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="detailLoadingId === item.id"
                            @click="toggleDetail(item)"
                            >{{
                                expandedId === item.id
                                    ? 'Ẩn chi tiết'
                                    : 'Xem giao dịch & timeline'
                            }}</Button
                        >
                        <span
                            v-if="
                                item.status === 'pending_owner_approval' &&
                                !canApprove
                            "
                            class="text-xs font-medium text-purple-700 dark:text-purple-300"
                            >Đã gửi Chủ doanh nghiệp phê duyệt; chưa được tự
                            chốt.</span
                        >
                    </div>
                    <div
                        v-else-if="item.status === 'resolved'"
                        class="mt-3 flex flex-wrap items-center gap-2"
                    >
                        <Button
                            size="sm"
                            variant="ghost"
                            :disabled="detailLoadingId === item.id"
                            @click="toggleDetail(item)"
                            >{{
                                expandedId === item.id
                                    ? 'Ẩn timeline'
                                    : 'Xem timeline & giao dịch'
                            }}</Button
                        >
                        <span
                            class="text-xs text-emerald-700 dark:text-emerald-300"
                            >Đã xác minh
                            {{
                                item.verified_by_name
                                    ? `bởi ${item.verified_by_name}`
                                    : ''
                            }}.</span
                        >
                    </div>
                </div>

                <p v-if="errorMessage" class="text-xs font-medium text-red-600">
                    {{ errorMessage }}
                </p>
            </CardContent>
        </Card>

        <Card
            class="border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-950/20"
        >
            <CardContent
                class="flex flex-wrap items-start gap-3 p-4 text-xs text-muted-foreground"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <div>
                    <strong class="text-foreground">Nguyên tắc chốt:</strong> hệ
                    thống không tự xóa hồ sơ khi có phiếu nhập bù. Người phụ
                    trách phải phân loại nguyên nhân, thực hiện giao dịch kho
                    thật, gửi đối chiếu; một người khác xác minh giao dịch và
                    tồn thực tế rồi mới chốt. Hồ sơ Cao/Critical cần Chủ doanh
                    nghiệp phê duyệt.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
