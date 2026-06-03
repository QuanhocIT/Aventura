<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ShieldAlert, Banknote, Percent, XCircle, Package, BarChart3,
    ChevronDown, ChevronUp, AlertTriangle, Check, X, Plus,
    TrendingDown, Calendar, Users, List, Globe, Eye, CheckCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ──────────────────────────────────────────────────────────────────────

type Summary = {
    cash_shortfall_cashiers: number
    discount_flagged_days: number
    cancellation_cashiers: number
    waste_flagged_entries: number
    revenue_flagged_days: number
    ai_flagged_alerts?: number
    audit_logs_count?: number
}

type TabKey = 'ai' | 'audit' | 'cash' | 'discount' | 'cancel' | 'waste' | 'revenue'
type Severity = 'low' | 'medium' | 'high' | 'critical' | 'warning' | 'danger'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    period:    string
    activeTab: string
    summary:   Summary
    data:      any
    canAct:    boolean
    dateRange: { start: string; end: string }
}>()

// ── Period navigation ──────────────────────────────────────────────────────────

const activePeriod = ref(props.period)

function applyPeriod() {
    router.get('/fraud', { period: activePeriod.value, tab: props.activeTab }, { preserveScroll: true })
}

// ── Tab switching (server-side lazy load) ─────────────────────────────────────

function switchTab(tab: TabKey) {
    router.get('/fraud', { period: activePeriod.value, tab }, { preserveScroll: true })
}

// ── Tabs config ────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    { key: 'ai'       as TabKey, label: 'AI Cảnh báo', icon: ShieldAlert, count: props.summary.ai_flagged_alerts ?? 0,  color: 'rose' },
    { key: 'audit'    as TabKey, label: 'Kiểm toán',   icon: List,        count: props.summary.audit_logs_count ?? 0,   color: 'blue' },
    { key: 'cash'     as TabKey, label: 'Thiếu quỹ',   icon: Banknote,   count: props.summary.cash_shortfall_cashiers,  color: 'rose' },
    { key: 'discount' as TabKey, label: 'Chiết khấu',  icon: Percent,    count: props.summary.discount_flagged_days,    color: 'amber' },
    { key: 'cancel'   as TabKey, label: 'Hủy đơn',     icon: XCircle,    count: props.summary.cancellation_cashiers,   color: 'rose' },
    { key: 'waste'    as TabKey, label: 'Hao hụt kho', icon: Package,    count: props.summary.waste_flagged_entries,    color: 'amber' },
    { key: 'revenue'  as TabKey, label: 'Doanh thu',   icon: BarChart3,  count: props.summary.revenue_flagged_days,    color: 'orange' },
])

const totalFlags = computed(() =>
    (props.summary.ai_flagged_alerts ?? 0) +
    props.summary.cash_shortfall_cashiers +
    props.summary.discount_flagged_days +
    props.summary.cancellation_cashiers +
    props.summary.waste_flagged_entries +
    props.summary.revenue_flagged_days
)

// ── Severity badges ────────────────────────────────────────────────────────────

const severityConfig: Record<Severity, { label: string; cls: string }> = {
    low:      { label: 'Thấp',        cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    medium:   { label: 'Trung bình',  cls: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300' },
    high:     { label: 'Cao',         cls: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' },
    critical: { label: 'Nghiêm trọng',cls: 'bg-red-200 text-red-800 dark:bg-red-900/60 dark:text-red-200' },
    warning:  { label: 'Cảnh báo',   cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' },
    danger:   { label: 'Nguy hiểm',  cls: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300' },
}

// ── Expandable rows ────────────────────────────────────────────────────────────

const expandedKey = ref<string | null>(null)
function toggleExpand(key: string) {
    expandedKey.value = expandedKey.value === key ? null : key
}

// ── Override Split Penalty ─────────────────────────────────────────────────────

function overrideSplit(orderId: number) {
    if (!confirm('Bạn có chắc chắn muốn phê duyệt đối soát và gỡ bỏ khoản phạt âm tiền cho đơn hàng này?')) return;
    router.patch(`/orders/${orderId}/override-split-penalty`, {}, {
        onSuccess: () => toast.success('Đã phê duyệt đối soát đơn tách thành công!'),
        onError: () => toast.error('Lỗi khi phê duyệt đối soát.'),
    });
}

// ── Violation modal ────────────────────────────────────────────────────────────

type ViolationTarget = {
    employee_id: number
    employee_name: string
    violation_type: string
    description: string
    penalty_amount: number
    occurred_at: string
}

const violationTarget = ref<ViolationTarget | null>(null)

const violationForm = useForm({
    employee_id:     0,
    violation_type:  '',
    severity:        'medium',
    description:     '',
    penalty_amount:  '',
    occurred_at:     '',
    apply_deduction: false,
})

function openViolation(target: ViolationTarget) {
    violationTarget.value = target
    violationForm.employee_id    = target.employee_id
    violationForm.violation_type = target.violation_type
    violationForm.description    = target.description
    violationForm.penalty_amount = target.penalty_amount > 0 ? String(target.penalty_amount) : ''
    violationForm.occurred_at    = target.occurred_at
    violationForm.severity       = 'medium'
    violationForm.apply_deduction = false
}

function submitViolation() {
    violationForm.post('/fraud/violation', {
        onSuccess: () => {
            toast.success('Đã ghi nhận vi phạm!')
            violationTarget.value = null
            violationForm.reset()
        },
        onError: () => toast.error('Có lỗi khi ghi vi phạm.'),
    })
}

// ── Formatting ─────────────────────────────────────────────────────────────────

const vnd = (v: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v)
const compact = (v: number) => new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(Math.abs(v)) + 'đ'
const pct = (v: number) => v.toFixed(1) + '%'
</script>

<template>
    <Head title="Kiểm toán Gian lận" />

    <div class="mx-auto max-w-7xl space-y-6 p-4 lg:p-6">

        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-500/10 text-rose-500">
                    <ShieldAlert class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight">Kiểm toán Gian lận</h1>
                        <span v-if="totalFlags > 0"
                            class="flex items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">
                            {{ totalFlags }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ dateRange.start }} — {{ dateRange.end }}</p>
                </div>
            </div>

            <input v-model="activePeriod" type="month" @change="applyPeriod"
                class="rounded-xl border border-border bg-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400" />
        </div>

        <!-- ── KPI Cards ───────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-7">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="switchTab(tab.key)"
                class="cursor-pointer rounded-2xl border border-border bg-card p-4 shadow-sm text-left transition hover:shadow-md active:scale-95"
                :class="activeTab === tab.key ? 'border-rose-400 bg-rose-50/40 dark:bg-rose-900/10' : 'hover:border-border/80'"
            >
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">{{ tab.label }}</span>
                    <component :is="tab.icon" class="size-4"
                        :class="tab.count > 0 ? 'text-rose-500' : 'text-muted-foreground'" />
                </div>
                <p class="mt-2 text-2xl font-bold"
                    :class="tab.count > 0 ? 'text-rose-600 dark:text-rose-400' : ''">
                    {{ tab.count }}
                </p>
                <p class="mt-0.5 text-[11px] text-muted-foreground line-clamp-1">
                    {{ tab.key === 'ai' ? 'phát hiện rủi ro'
                     : tab.key === 'audit' ? 'nhật ký kiểm toán'
                     : tab.key === 'cash' ? 'thu ngân bị gắn cờ'
                     : tab.key === 'discount' ? 'ngày bất thường'
                     : tab.key === 'cancel' ? 'thu ngân đáng ngờ'
                     : tab.key === 'waste' ? 'hao hụt lớn'
                     : 'chênh lệch doanh thu' }}
                </p>
            </button>
        </div>

        <!-- ── Tab bar ─────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap gap-1 rounded-xl border border-border bg-muted/30 p-1">
            <button
                v-for="tab in tabs" :key="tab.key"
                @click="switchTab(tab.key)"
                class="relative cursor-pointer flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-card shadow-sm text-foreground'
                    : 'text-muted-foreground hover:text-foreground'"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
                <span v-if="tab.count > 0"
                    class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[9px] font-bold text-white">
                    {{ tab.count > 9 ? '9+' : tab.count }}
                </span>
            </button>
        </div>

        <!-- ══ TAB: AI CẢNH BÁO (ai) ═══════════════════════════════════════ -->
        <template v-if="activeTab === 'ai'">
            <div v-if="!data?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <ShieldAlert class="size-12 opacity-25 text-emerald-500" />
                <p class="font-medium text-emerald-600 dark:text-emerald-400">Hệ thống an toàn. Không phát hiện bất thường nào.</p>
            </div>

            <div v-else class="space-y-4">
                <div class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/50 px-4 py-2.5 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300">
                    <ShieldAlert class="size-4 shrink-0 animate-pulse text-rose-500" />
                    <span>Thuật toán AI phát hiện <strong>{{ data.length }} cảnh báo rủi ro cao</strong> dựa trên phân tích nhật ký tĩnh audit_logs.</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div v-for="alert in data" :key="alert.id"
                        class="relative overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-sm hover:shadow-md transition duration-200">
                        <div class="absolute top-0 right-0 h-1.5 w-full"
                            :class="alert.severity === 'critical' ? 'bg-red-500' : 'bg-rose-500'"></div>
                        
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                    AI Flagged
                                </span>
                                <h3 class="mt-2 text-base font-bold text-foreground">{{ alert.violation_type }}</h3>
                                <p class="text-xs text-muted-foreground mt-0.5">Nhân viên: <strong class="text-foreground">{{ alert.employee_name }}</strong></p>
                            </div>
                            
                            <div class="text-right">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-black shadow-sm"
                                    :class="alert.severity === 'critical' ? 'bg-red-500/10 text-red-600' : 'bg-rose-500/10 text-rose-600'">
                                    {{ alert.risk_score }}% Risk
                                </span>
                                <p class="text-[10px] text-muted-foreground mt-1">{{ alert.occurred_at }}</p>
                            </div>
                        </div>

                        <p class="mt-3.5 text-sm text-muted-foreground leading-relaxed">{{ alert.description }}</p>

                        <div class="mt-4 rounded-xl bg-muted/40 p-3 text-xs text-muted-foreground border border-border/40">
                            <span class="font-bold text-rose-600 dark:text-rose-400">AI Reasoning:</span> {{ alert.reason }}
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-border/40 pt-3">
                            <div class="text-xs text-muted-foreground">
                                Phạt đề xuất: <span class="font-semibold text-rose-600">{{ alert.penalty_amount > 0 ? vnd(alert.penalty_amount) : 'Cảnh cáo' }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button v-if="alert.id.includes('log') && alert.violation_type.includes('Tách bàn')"
                                    @click="overrideSplit(alert.id.replace('ai-log-', ''))"
                                    class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl border border-emerald-500 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 px-3 py-1.5 text-xs font-semibold transition active:scale-95">
                                    Duyệt đối soát
                                </button>
                                <button @click="openViolation({
                                    employee_id: alert.employee_id,
                                    employee_name: alert.employee_name,
                                    violation_type: alert.violation_type,
                                    description: alert.description + ' (Phát hiện tự động bởi AI Fraud engine với độ tin cậy ' + alert.risk_score + '%)',
                                    penalty_amount: alert.penalty_amount,
                                    occurred_at: alert.occurred_at,
                                })"
                                    class="cursor-pointer inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700 active:scale-95">
                                    <Plus class="size-3" /> Ghi vi phạm
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: NHẬT KÝ KIỂM TOÁN (audit) ═══════════════════════════════ -->
        <template v-else-if="activeTab === 'audit'">
            <div v-if="!data?.logs?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <List class="size-12 opacity-25" />
                <p class="font-medium">Không có nhật ký kiểm toán nào trong kỳ này</p>
            </div>

            <div v-else class="space-y-3">
                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="hidden grid-cols-[auto_1.5fr_1fr_1.5fr_auto_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                        <div></div><div>Thời gian / Thiết bị</div><div>Người thực hiện</div><div>Hành động</div><div>Đối tượng</div><div class="text-right">Sự kiện</div>
                    </div>

                    <div v-for="log in data.logs" :key="log.id" class="border-b border-border last:border-0">
                        <div class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:grid-cols-[auto_1.5fr_1fr_1.5fr_auto_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand('audit-' + log.id)">
                            <component :is="expandedKey === 'audit-' + log.id ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-semibold text-sm">{{ log.created_at }}</p>
                                <p class="text-xs text-muted-foreground flex items-center gap-1">
                                    <Globe class="size-3" /> {{ log.ip_address }}
                                </p>
                            </div>
                            <div class="hidden lg:block">
                                <p class="font-medium text-sm">{{ log.user_name }}</p>
                                <p class="text-xs text-muted-foreground uppercase tracking-wider font-semibold">{{ log.user_role }}</p>
                            </div>
                            <div class="hidden lg:block">
                                <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-bold bg-muted text-muted-foreground">
                                    {{ log.action_label }}
                                </span>
                            </div>
                            <div class="hidden lg:block text-xs font-mono text-muted-foreground">
                                {{ log.subject_type }} #{{ log.subject_id }}
                            </div>
                            <div class="text-right">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                    :class="log.event === 'created' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                           : log.event === 'updated' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                           : 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300'">
                                    {{ log.event }}
                                </span>
                            </div>
                        </div>

                        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[800px]"
                            leave-active-class="transition-all duration-150" leave-from-class="opacity-100 max-h-[800px]" leave-to-class="opacity-0 max-h-0">
                            <div v-if="expandedKey === 'audit-' + log.id" class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4">
                                <div class="grid gap-4 md:grid-cols-2">
                                    <!-- Old values -->
                                    <div class="rounded-xl border border-border bg-card p-3 shadow-sm">
                                        <p class="text-xs font-semibold text-rose-600 dark:text-rose-400 mb-2 flex items-center gap-1">
                                            <XCircle class="size-3.5" /> Dữ liệu trước (old_values)
                                        </p>
                                        <pre class="text-[11px] font-mono whitespace-pre-wrap bg-muted/40 p-2.5 rounded-lg border border-border/50 text-muted-foreground max-h-[250px] overflow-y-auto">{{ log.old_values ? JSON.stringify(log.old_values, null, 2) : '— (N/A)' }}</pre>
                                    </div>
                                    
                                    <!-- New values -->
                                    <div class="rounded-xl border border-border bg-card p-3 shadow-sm">
                                        <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 mb-2 flex items-center gap-1">
                                            <CheckCircle class="size-3.5" /> Dữ liệu sau (new_values)
                                        </p>
                                        <pre class="text-[11px] font-mono whitespace-pre-wrap bg-muted/40 p-2.5 rounded-lg border border-border/50 text-foreground max-h-[250px] overflow-y-auto">{{ log.new_values ? JSON.stringify(log.new_values, null, 2) : '— (N/A)' }}</pre>
                                    </div>
                                </div>
                                <div class="mt-3 text-[11px] text-muted-foreground italic flex items-center gap-1">
                                    User Agent: {{ log.user_agent }}
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: THIẾU QUỸ (cash) ═══════════════════════════════════════ -->
        <template v-else-if="activeTab === 'cash'">
            <div v-if="!data.cashiers?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <Banknote class="size-12 opacity-25" />
                <p class="font-medium">Không có thu ngân nào thiếu quỹ trong kỳ này</p>
            </div>

            <div v-else class="space-y-3">
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
                    <AlertTriangle class="size-4 shrink-0" />
                    <span>Tổng thiếu quỹ: <strong>{{ vnd(Math.abs(data.total_shortage)) }}</strong> từ {{ data.flagged_count }} thu ngân</span>
                </div>

                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="hidden grid-cols-[auto_1fr_auto_auto_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                        <div></div><div>Thu ngân</div><div class="text-right">Số lần</div><div class="text-right">Tổng thiếu</div><div class="text-right">Mức độ</div>
                    </div>

                    <div v-for="cashier in data.cashiers" :key="cashier.cashier_user_id" class="border-b border-border last:border-0">
                        <div class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:grid-cols-[auto_1fr_auto_auto_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand('cash-' + cashier.cashier_user_id)">
                            <component :is="expandedKey === 'cash-' + cashier.cashier_user_id ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-semibold text-sm">{{ cashier.cashier_name }}</p>
                                <p class="text-xs text-muted-foreground lg:hidden">{{ cashier.shortfall_count }} lần · {{ vnd(Math.abs(cashier.total_difference)) }}</p>
                            </div>
                            <div class="hidden text-right lg:block">
                                <p class="font-semibold text-sm text-rose-600 dark:text-rose-400">{{ cashier.shortfall_count }} lần</p>
                            </div>
                            <div class="hidden text-right lg:block">
                                <p class="font-bold text-sm text-rose-600 dark:text-rose-400">{{ vnd(Math.abs(cashier.total_difference)) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                    :class="severityConfig[cashier.severity as Severity].cls">
                                    {{ severityConfig[cashier.severity as Severity].label }}
                                </span>
                            </div>
                        </div>

                        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150" leave-from-class="opacity-100 max-h-[600px]" leave-to-class="opacity-0 max-h-0">
                            <div v-if="expandedKey === 'cash-' + cashier.cashier_user_id" class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4">
                                <div class="mb-3 grid grid-cols-3 gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground bg-muted/30 px-3 py-2 rounded-lg">
                                    <span>Ngày</span><span class="text-right">Kỳ vọng</span><span class="text-right">Thực tế / Chênh lệch</span>
                                </div>
                                <div v-for="c in cashier.closings" :key="c.id" class="grid grid-cols-3 gap-2 px-3 py-2 text-sm border-b border-border/30 last:border-0">
                                    <div>
                                        <p class="font-medium">{{ c.closing_date }}</p>
                                        <p class="text-xs text-muted-foreground">{{ c.shift_name }}</p>
                                    </div>
                                    <div class="text-right text-muted-foreground">{{ compact(c.expected_cash) }}</div>
                                    <div class="text-right">
                                        <p class="font-semibold">{{ compact(c.actual_cash) }}</p>
                                        <p class="text-xs font-bold text-rose-600 dark:text-rose-400">{{ vnd(c.cash_difference) }}</p>
                                    </div>
                                </div>

                                <div v-if="canAct" class="mt-3">
                                    <button @click="openViolation({
                                        employee_id: cashier.cashier_user_id,
                                        employee_name: cashier.cashier_name,
                                        violation_type: 'Thiếu quỹ thu ngân',
                                        description: cashier.shortfall_count + ' lần thiếu quỹ, tổng ' + vnd(Math.abs(cashier.total_difference)),
                                        penalty_amount: Math.abs(cashier.total_difference),
                                        occurred_at: cashier.closings[0]?.closing_date ?? '',
                                    })"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700 active:scale-95">
                                        <Plus class="size-3.5" /> Ghi vi phạm
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: CHIẾT KHẤU (discount) ══════════════════════════════════ -->
        <template v-else-if="activeTab === 'discount'">
            <div v-if="!data.days?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <Percent class="size-12 opacity-25" />
                <p class="font-medium">Không có chiết khấu bất thường trong kỳ này</p>
            </div>

            <div v-else class="space-y-3">
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/20 dark:text-amber-300">
                    <AlertTriangle class="size-4 shrink-0" />
                    <span>Tổng chiết khấu bất thường: <strong>{{ vnd(data.total_excess_discount) }}</strong> trong {{ data.flagged_days }} ngày</span>
                </div>

                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="hidden grid-cols-[auto_1fr_auto_auto_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                        <div></div><div>Ngày</div><div class="text-right">Số đơn</div><div class="text-right">Tổng CK</div><div class="text-right">Tỷ lệ CK</div>
                    </div>

                    <div v-for="day in data.days" :key="day.order_date" class="border-b border-border last:border-0">
                        <div class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:grid-cols-[auto_1fr_auto_auto_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand('disc-' + day.order_date)">
                            <component :is="expandedKey === 'disc-' + day.order_date ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-semibold text-sm">{{ day.order_date }}</p>
                                <p class="text-xs text-muted-foreground lg:hidden">{{ day.order_count }} đơn · CK {{ pct(day.discount_rate_pct) }}</p>
                            </div>
                            <div class="hidden text-right lg:block text-muted-foreground text-sm">{{ day.order_count }}</div>
                            <div class="hidden text-right lg:block">
                                <p class="font-bold text-sm text-amber-600 dark:text-amber-400">{{ vnd(day.discount_total) }}</p>
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                    {{ pct(day.discount_rate_pct) }}
                                </span>
                            </div>
                        </div>

                        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150" leave-from-class="opacity-100 max-h-[600px]" leave-to-class="opacity-0 max-h-0">
                            <div v-if="expandedKey === 'disc-' + day.order_date" class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4">
                                <p v-if="!day.orders.length" class="text-xs text-muted-foreground">Không có đơn đơn lẻ vượt ngưỡng 15%</p>
                                <div v-else>
                                    <p class="text-xs font-semibold text-muted-foreground mb-2 uppercase tracking-wider">Đơn hàng chiết khấu cao (>15%)</p>
                                    <div class="space-y-1.5">
                                        <div v-for="o in day.orders" :key="o.id"
                                            class="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                            <div>
                                                <p class="font-medium">{{ o.order_number }}</p>
                                                <p class="text-xs text-muted-foreground">{{ o.cashier_name }}</p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-bold text-amber-600">-{{ vnd(o.discount_amount) }}</p>
                                                <p class="text-xs text-muted-foreground">{{ pct(o.discount_rate_pct) }} của {{ vnd(o.subtotal) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: HỦY ĐƠN (cancel) ═══════════════════════════════════════ -->
        <template v-else-if="activeTab === 'cancel'">
            <div v-if="!data.cashiers?.length && !data.standalone_high_value?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <XCircle class="size-12 opacity-25" />
                <p class="font-medium">Không có mẫu hủy đơn đáng ngờ trong kỳ này</p>
            </div>

            <div v-else class="space-y-4">
                <!-- Flagged cashiers -->
                <div v-if="data.cashiers?.length" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Thu ngân có tỷ lệ hủy cao
                    </div>
                    <div v-for="cashier in data.cashiers" :key="cashier.cancelled_by ?? 'unknown'" class="border-b border-border last:border-0">
                        <div class="grid cursor-pointer grid-cols-[auto_1fr_auto_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:gap-4 lg:px-5"
                            @click="toggleExpand('cancel-' + cashier.cancelled_by)">
                            <component :is="expandedKey === 'cancel-' + cashier.cancelled_by ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-muted-foreground" />
                            <div>
                                <p class="font-semibold text-sm">{{ cashier.cashier_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ cashier.cancelled_count }} đơn hủy · {{ vnd(cashier.total_value_cancelled) }}</p>
                            </div>
                            <span class="text-sm font-bold text-rose-600 dark:text-rose-400">{{ cashier.cancelled_count }} đơn</span>
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">
                                {{ cashier.flag_reason === 'both' ? 'Số lượng & Giá trị' : cashier.flag_reason === 'count' ? 'Số lượng' : 'Giá trị cao' }}
                            </span>
                        </div>

                        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 max-h-0" enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150" leave-from-class="opacity-100 max-h-[600px]" leave-to-class="opacity-0 max-h-0">
                            <div v-if="expandedKey === 'cancel-' + cashier.cancelled_by" class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4">
                                <div v-if="cashier.orders.length" class="space-y-1.5 mb-3">
                                    <div v-for="o in cashier.orders" :key="o.id"
                                        class="flex items-center justify-between rounded-lg border border-border bg-card px-3 py-2 text-sm">
                                        <div>
                                            <p class="font-medium">{{ o.order_number }}</p>
                                            <p class="text-xs text-muted-foreground">{{ o.cancelled_date }}</p>
                                        </div>
                                        <p class="font-bold text-rose-600 dark:text-rose-400">{{ vnd(o.total_amount) }}</p>
                                    </div>
                                </div>
                                <div v-if="canAct">
                                    <button @click="openViolation({
                                        employee_id: cashier.cancelled_by ?? 0,
                                        employee_name: cashier.cashier_name,
                                        violation_type: 'Hủy đơn bất thường',
                                        description: cashier.cancelled_count + ' đơn hủy, tổng ' + vnd(cashier.total_value_cancelled),
                                        penalty_amount: 0,
                                        occurred_at: cashier.orders[0]?.cancelled_date ?? '',
                                    })"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700 active:scale-95">
                                        <Plus class="size-3.5" /> Ghi vi phạm
                                    </button>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>

                <!-- Standalone high-value cancellations -->
                <div v-if="data.standalone_high_value?.length" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Đơn giá trị lớn bị hủy (>500k)
                    </div>
                    <div v-for="o in data.standalone_high_value" :key="o.id"
                        class="flex items-center justify-between border-b border-border px-5 py-3 last:border-0 hover:bg-muted/20 transition">
                        <div>
                            <p class="font-medium text-sm">{{ o.order_number }}</p>
                            <p class="text-xs text-muted-foreground">{{ o.cancelled_by_name }} · {{ o.cancelled_date }}</p>
                        </div>
                        <p class="font-bold text-rose-600 dark:text-rose-400">{{ vnd(o.total_amount) }}</p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: HAO HỤT KHO (waste) ════════════════════════════════════ -->
        <template v-else-if="activeTab === 'waste'">
            <div v-if="!data.large_entries?.length && !data.flagged_days?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <Package class="size-12 opacity-25" />
                <p class="font-medium">Không có hao hụt bất thường trong kỳ này</p>
            </div>

            <div v-else class="space-y-4">
                <!-- Summary -->
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs text-muted-foreground uppercase tracking-wider">Tổng chi phí hao hụt</p>
                        <p class="mt-1 text-xl font-bold text-amber-600 dark:text-amber-400">{{ compact(data.total_waste_cost) }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs text-muted-foreground uppercase tracking-wider">Entries lớn bị gắn cờ</p>
                        <p class="mt-1 text-xl font-bold">{{ data.flagged_entries_count }}</p>
                    </div>
                    <div class="rounded-xl border border-border bg-card p-4">
                        <p class="text-xs text-muted-foreground uppercase tracking-wider">Ngày hao hụt cao</p>
                        <p class="mt-1 text-xl font-bold">{{ data.flagged_days_count }}</p>
                    </div>
                </div>

                <!-- Large entries -->
                <div v-if="data.large_entries?.length" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Giao dịch hao hụt lớn (>500k)
                    </div>
                    <div v-for="entry in data.large_entries" :key="entry.id"
                        class="grid grid-cols-[1fr_auto_auto] items-center gap-3 border-b border-border px-5 py-3 last:border-0 hover:bg-muted/20 transition">
                        <div>
                            <p class="font-medium text-sm">{{ entry.ingredient_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ entry.employee_name }}
                                <span v-if="entry.occurred_at"> · {{ entry.occurred_at }}</span>
                                <span v-if="entry.notes" class="italic"> · {{ entry.notes }}</span>
                            </p>
                        </div>
                        <p class="text-sm text-muted-foreground font-mono">{{ entry.quantity.toFixed(3) }}</p>
                        <p class="text-right font-bold text-amber-600 dark:text-amber-400">{{ vnd(entry.total_cost) }}</p>
                    </div>
                </div>

                <!-- Flagged days -->
                <div v-if="data.flagged_days?.length" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Ngày có hao hụt tổng >1,000,000đ
                    </div>
                    <div v-for="day in data.flagged_days" :key="day.waste_date"
                        class="flex items-center justify-between border-b border-border px-5 py-3 last:border-0 hover:bg-muted/20 transition">
                        <div>
                            <p class="font-medium text-sm">{{ day.waste_date }}</p>
                            <p class="text-xs text-muted-foreground">{{ day.entry_count }} lần ghi nhận</p>
                        </div>
                        <p class="font-bold text-amber-600 dark:text-amber-400">{{ vnd(day.daily_waste_cost) }}</p>
                    </div>
                </div>

                <!-- Top employees by waste -->
                <div v-if="data.top_employees?.length" class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                        Nhân viên ghi nhận hao hụt nhiều nhất
                    </div>
                    <div v-for="emp in data.top_employees" :key="emp.performed_by ?? 'unknown'"
                        class="flex items-center justify-between border-b border-border px-5 py-3 last:border-0 hover:bg-muted/20 transition">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/10 text-xs font-bold text-amber-600">
                                {{ emp.employee_name?.slice(0, 1) ?? '?' }}
                            </div>
                            <div>
                                <p class="font-medium text-sm">{{ emp.employee_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ emp.waste_entry_count }} lần</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-amber-600 dark:text-amber-400">{{ vnd(emp.total_waste_cost) }}</p>
                            <button v-if="canAct" @click="openViolation({
                                employee_id: emp.performed_by,
                                employee_name: emp.employee_name,
                                violation_type: 'Hao hụt nguyên liệu bất thường',
                                description: emp.waste_entry_count + ' lần hao hụt, tổng ' + vnd(emp.total_waste_cost),
                                penalty_amount: 0,
                                occurred_at: new Date().toISOString().slice(0, 10),
                            })"
                                class="mt-1 inline-flex cursor-pointer items-center gap-1 rounded text-[11px] font-semibold text-rose-600 hover:underline dark:text-rose-400">
                                <Plus class="size-3" /> Ghi vi phạm
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ══ TAB: BẤT THƯỜNG DOANH THU (revenue) ═════════════════════════ -->
        <template v-else-if="activeTab === 'revenue'">
            <div v-if="!data.days?.length"
                class="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card py-16 text-center text-muted-foreground">
                <BarChart3 class="size-12 opacity-25" />
                <p class="font-medium">Không có bất thường doanh thu trong kỳ này</p>
                <p class="text-sm">Hệ thống đối chiếu báo cáo tự động với tổng chốt ca đã xác nhận</p>
            </div>

            <div v-else class="space-y-3">
                <div class="flex items-center gap-3 rounded-xl border border-orange-200 bg-orange-50/50 px-4 py-2.5 text-sm text-orange-800 dark:border-orange-900 dark:bg-orange-900/20 dark:text-orange-300">
                    <AlertTriangle class="size-4 shrink-0" />
                    <span>{{ data.flagged_days_count }} ngày có chênh lệch >5% · Lớn nhất: <strong>{{ pct(data.max_discrepancy_pct) }}</strong></span>
                </div>

                <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                    <div class="hidden grid-cols-[1fr_1fr_1fr_auto_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                        <div>Ngày</div><div class="text-right">Báo cáo tự động</div><div class="text-right">Tổng chốt ca</div><div class="text-right">Chênh lệch</div><div class="text-right">Mức độ</div>
                    </div>

                    <div v-for="day in data.days" :key="day.summary_date"
                        class="grid grid-cols-[1fr_auto] items-center gap-3 border-b border-border px-4 py-3.5 last:border-0 hover:bg-muted/20 transition lg:grid-cols-[1fr_1fr_1fr_auto_auto] lg:gap-4 lg:px-5">
                        <div>
                            <p class="font-semibold text-sm">{{ day.summary_date }}</p>
                        </div>
                        <div class="hidden text-right lg:block text-muted-foreground text-sm">{{ compact(day.summary_net_revenue) }}</div>
                        <div class="hidden text-right lg:block text-sm">
                            <p>{{ compact(day.shift_total) }}</p>
                            <p v-if="day.shift_total === 0" class="text-xs text-amber-500">Chưa có chốt ca</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-sm" :class="day.difference >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ day.difference >= 0 ? '+' : '' }}{{ compact(day.difference) }}
                            </p>
                            <p class="text-xs text-muted-foreground">{{ pct(day.difference_pct) }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                            :class="severityConfig[day.severity as Severity].cls">
                            {{ severityConfig[day.severity as Severity].label }}
                        </span>
                    </div>
                </div>
            </div>
        </template>

    </div>

    <!-- ══ Violation Modal ══════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="violationTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                @click.self="violationTarget = null">
                <div class="w-full max-w-md overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                    <div class="flex items-center justify-between border-b border-border px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600">
                                <ShieldAlert class="size-5" />
                            </div>
                            <div>
                                <p class="font-semibold">Ghi vi phạm</p>
                                <p class="text-xs text-muted-foreground">{{ violationTarget.employee_name }}</p>
                            </div>
                        </div>
                        <button @click="violationTarget = null" class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted">
                            <X class="size-4" />
                        </button>
                    </div>

                    <div class="space-y-4 px-6 py-5">
                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">Loại vi phạm</label>
                            <input v-model="violationForm.violation_type" type="text" maxlength="100"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">Mức độ</label>
                            <select v-model="violationForm.severity"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400">
                                <option value="low">Thấp</option>
                                <option value="medium">Trung bình</option>
                                <option value="high">Cao</option>
                                <option value="critical">Nghiêm trọng</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">Mô tả <span class="text-rose-500">*</span></label>
                            <textarea v-model="violationForm.description" rows="3" maxlength="2000"
                                class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">Ngày xảy ra</label>
                            <input v-model="violationForm.occurred_at" type="date"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400" />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-sm font-medium">Số tiền phạt (nếu có)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">₫</span>
                                <input v-model="violationForm.penalty_amount" type="number" min="0" step="10000"
                                    class="w-full rounded-xl border border-border bg-background py-2.5 pl-8 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-400" />
                            </div>
                        </div>

                        <label v-if="canAct" class="flex items-center gap-3 cursor-pointer">
                            <input v-model="violationForm.apply_deduction" type="checkbox"
                                class="h-4 w-4 rounded border-border text-rose-600 focus:ring-rose-500" />
                            <span class="text-sm">Trừ vào lương tháng này</span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                        <button @click="violationTarget = null"
                            class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted">
                            Hủy
                        </button>
                        <button @click="submitViolation" :disabled="violationForm.processing"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 active:scale-95 disabled:opacity-50">
                            <Check class="size-4" />
                            Xác nhận vi phạm
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
