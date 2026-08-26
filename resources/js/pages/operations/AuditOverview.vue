<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    CalendarCheck2,
    CheckCircle2,
    ClipboardList,
    Clock3,
    FileWarning,
    Gavel,
    ListChecks,
    MapPin,
    Plus,
    ShieldCheck,
    Target,
    TrendingUp,
    UserRound,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader } from '@/components/ui/card';

defineOptions({ layout: AppLayout });

type Report = {
    id: number;
    report_code: string;
    branch?: { id: number; name: string } | null;
    inspector?: { id: number; name: string } | null;
    assignee?: { id: number; name: string } | null;
    severity_level: string;
    status: string;
    remediation_deadline?: string | null;
    is_overdue?: boolean;
    description?: string;
};

type Plan = {
    id: number;
    plan_code: string;
    title: string;
    inspection_type: string;
    scheduled_date?: string | null;
    due_date?: string | null;
    status: string;
    branch?: { id: number; name: string } | null;
    lead_inspector?: { id: number; name: string } | null;
    reports_count: number;
    open_reports_count: number;
    is_overdue?: boolean;
};

type BranchInsight = {
    id: number;
    name: string;
    open_reports: number;
    overdue_reports: number;
    critical_reports: number;
    risk_score: number;
    risk_level: 'stable' | 'warning' | 'critical';
    active_plans: number;
};

const props = defineProps<{
    roleLabel: string;
    reportStats: Record<string, number>;
    planStats: Record<string, number>;
    inspectionStats: Record<string, number>;
    activeInspections: Array<any>;
    branchInsights: BranchInsight[];
    trend: Array<{ label: string; total: number; closed: number; critical: number }>;
    upcomingPlans: Plan[];
    myQueue: Report[];
    focusReports: Report[];
    recentReports: Report[];
    capabilities: {
        create_report: boolean;
        manage_plans: boolean;
        reinspect: boolean;
    };
}>();

const trendMax = Math.max(1, ...props.trend.map((item) => Number(item.total || 0)));

const formatDate = (value?: string | null) => {
    if (!value) return 'Chưa đặt hạn';
    return new Date(`${value}T00:00:00`).toLocaleDateString('vi-VN');
};

const formatNumber = (value: number | undefined) =>
    new Intl.NumberFormat('vi-VN').format(Number(value || 0));

const riskLabel = (level: BranchInsight['risk_level']) => ({
    stable: 'Ổn định',
    warning: 'Cần theo dõi',
    critical: 'Ưu tiên cao',
}[level]);

const riskClass = (level: BranchInsight['risk_level']) => ({
    stable: 'text-emerald-600 dark:text-emerald-300',
    warning: 'text-amber-600 dark:text-amber-300',
    critical: 'text-rose-600 dark:text-rose-300',
}[level]);

const riskBarClass = (level: BranchInsight['risk_level']) => ({
    stable: 'bg-emerald-500',
    warning: 'bg-amber-500',
    critical: 'bg-rose-500',
}[level]);

const severityLabel = (severity: string) => ({
    critical: 'Nghiêm trọng',
    severe: 'Cao',
    moderate: 'Trung bình',
    minor: 'Nhẹ',
}[severity] || severity);

const statusLabel = (status: string) => ({
    pending_owner_approval: 'Chờ chủ duyệt',
    approved: 'Đã duyệt',
    remediation_in_progress: 'Đang khắc phục',
    reinspection_pending: 'Chờ tái kiểm',
    closed: 'Đã đóng',
    rejected: 'Từ chối',
}[status] || status);

const statusClass = (status: string) => ({
    pending_owner_approval: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    approved: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    remediation_in_progress: 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    reinspection_pending: 'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    closed: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    rejected: 'bg-muted text-muted-foreground',
}[status] || 'bg-muted text-muted-foreground');

const planTypeLabel = (type: string) => ({
    routine: 'Định kỳ',
    thematic: 'Chuyên đề',
    surprise: 'Đột xuất',
    follow_up: 'Tái kiểm',
}[type] || type);
</script>

<template>
    <Head title="Tổng quan thanh tra" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <section class="relative overflow-hidden rounded-3xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50 via-background to-rose-50 px-5 py-6 shadow-sm dark:border-indigo-500/20 dark:from-indigo-950/50 dark:via-background dark:to-rose-950/20 md:px-7">
            <div class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-indigo-500/10 blur-3xl dark:bg-indigo-500/20" />
            <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                        <ShieldCheck class="size-6" />
                    </div>
                    <div>
                        <p class="mb-1 text-[10px] font-bold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-300">Trung tâm điều hành tuân thủ</p>
                        <h1 class="text-2xl font-black tracking-tight text-foreground md:text-3xl">Tổng quan Thanh tra</h1>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">{{ props.roleLabel }} · Theo dõi rủi ro, kế hoạch kiểm tra, SLA khắc phục và các việc cần xử lý.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="capabilities.create_report" href="/operations/audit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-rose-600 px-4 text-xs font-bold text-white shadow-md shadow-rose-600/20 transition hover:bg-rose-700">
                        <Plus class="size-4" /> Lập biên bản
                    </Link>
                    <Link href="/operations/audit" class="inline-flex h-10 items-center gap-2 rounded-xl border border-indigo-200 bg-background/80 px-4 text-xs font-bold text-indigo-700 transition hover:bg-indigo-500/10 dark:border-indigo-500/30 dark:text-indigo-300">
                        Quản lý hồ sơ <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_1.2fr]">
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-indigo-600 uppercase dark:text-indigo-300">Phiên hiện trường</p><h2 class="mt-1 text-lg font-bold text-foreground">Đang tác nghiệp</h2></div><Link href="/operations/inspection-workspace" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-300">Mở workspace</Link></div></CardHeader>
                <CardContent class="grid grid-cols-3 gap-2 p-5 text-center"><div class="rounded-xl bg-muted/40 p-3"><p class="text-2xl font-black text-amber-600">{{ formatNumber(inspectionStats.in_progress) }}</p><p class="text-[10px] text-muted-foreground">Đang kiểm tra</p></div><div class="rounded-xl bg-muted/40 p-3"><p class="text-2xl font-black text-rose-600">{{ formatNumber(inspectionStats.failed_checklist) }}</p><p class="text-[10px] text-muted-foreground">Mục không đạt</p></div><div class="rounded-xl bg-muted/40 p-3"><p class="text-2xl font-black text-indigo-600">{{ formatNumber(inspectionStats.open_actions) }}</p><p class="text-[10px] text-muted-foreground">CAPA mở</p></div></CardContent>
            </Card>
            <Card class="overflow-hidden border-border/70"><CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-emerald-600 uppercase dark:text-emerald-300">Lịch tác nghiệp</p><h2 class="mt-1 text-lg font-bold text-foreground">Phiên cần thực hiện</h2></div><CalendarCheck2 class="size-5 text-emerald-600" /></div></CardHeader><CardContent class="grid gap-2 p-4 sm:grid-cols-2"><div v-for="inspection in activeInspections" :key="inspection.id" class="rounded-xl border border-border/70 p-3"><div class="flex items-center justify-between gap-2"><span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-300">{{ inspection.inspection_code }}</span><span class="text-[10px] font-bold text-amber-600">{{ inspection.status === 'in_progress' ? 'Đang làm' : 'Chờ làm' }}</span></div><p class="mt-1 truncate text-xs font-bold text-foreground">{{ inspection.title }}</p><p class="mt-1 text-[10px] text-muted-foreground">{{ inspection.branch?.name }} · {{ inspection.scheduled_at || 'Chưa đặt lịch' }}</p></div><div v-if="!activeInspections.length" class="col-span-full p-4 text-center text-xs text-muted-foreground">Không có phiên đang chờ thực hiện.</div></CardContent></Card>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300">Hồ sơ mở</span><FileWarning class="size-4 text-indigo-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(reportStats.open) }}</p><p class="mt-1 text-[11px] text-muted-foreground">Tổng {{ formatNumber(reportStats.total) }} hồ sơ</p></CardContent>
            </Card>
            <Card class="border-rose-200/60 dark:border-rose-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-rose-600 uppercase dark:text-rose-300">Quá SLA</span><AlertTriangle class="size-4 text-rose-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(reportStats.overdue) }}</p><p class="mt-1 text-[11px] text-muted-foreground">{{ formatNumber(reportStats.critical) }} hồ sơ mức cao</p></CardContent>
            </Card>
            <Card class="border-amber-200/60 dark:border-amber-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-amber-600 uppercase dark:text-amber-300">Chờ chủ duyệt</span><Clock3 class="size-4 text-amber-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(reportStats.pending_approval) }}</p><p class="mt-1 text-[11px] text-muted-foreground">Cần theo dõi phê duyệt</p></CardContent>
            </Card>
            <Card class="border-sky-200/60 dark:border-sky-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-sky-600 uppercase dark:text-sky-300">Sắp quá hạn</span><Target class="size-4 text-sky-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(reportStats.due_soon) }}</p><p class="mt-1 text-[11px] text-muted-foreground">Trong 3 ngày tới</p></CardContent>
            </Card>
            <Card class="border-emerald-200/60 dark:border-emerald-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-300">Đã đóng</span><CheckCircle2 class="size-4 text-emerald-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(reportStats.closed) }}</p><p class="mt-1 text-[11px] text-muted-foreground">Tỷ lệ {{ reportStats.closure_rate }}%</p></CardContent>
            </Card>
            <Card class="border-violet-200/60 dark:border-violet-500/20">
                <CardHeader class="flex flex-row items-center justify-between pb-2"><span class="text-[10px] font-bold tracking-wider text-violet-600 uppercase dark:text-violet-300">Kế hoạch</span><CalendarCheck2 class="size-4 text-violet-600" /></CardHeader>
                <CardContent><p class="text-3xl font-black text-foreground">{{ formatNumber(planStats.in_progress + planStats.planned) }}</p><p class="mt-1 text-[11px] text-muted-foreground">{{ formatNumber(planStats.overdue) }} kế hoạch quá hạn</p></CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.35fr_1fr]">
            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="text-[10px] font-bold tracking-[0.16em] text-rose-600 uppercase dark:text-rose-300">Bảng điều hành rủi ro</p><h2 class="mt-1 text-lg font-bold text-foreground">Điểm nóng theo chi nhánh</h2></div>
                        <BarChart3 class="size-5 text-rose-600" />
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="!branchInsights.length" class="p-8 text-center text-sm text-muted-foreground">Chưa có dữ liệu chi nhánh.</div>
                    <div v-for="branch in branchInsights" :key="branch.id" class="border-b border-border/50 px-5 py-4 last:border-0">
                        <div class="flex items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-2"><MapPin class="size-4 shrink-0 text-muted-foreground" /><p class="truncate text-sm font-bold text-foreground">{{ branch.name }}</p></div><span :class="['text-[10px] font-bold', riskClass(branch.risk_level)]">{{ riskLabel(branch.risk_level) }}</span></div>
                        <div class="mt-2 flex items-center gap-3"><div class="h-2 flex-1 overflow-hidden rounded-full bg-muted"><div :class="['h-full rounded-full', riskBarClass(branch.risk_level)]" :style="{ width: `${Math.max(branch.risk_score, branch.open_reports ? 5 : 0)}%` }" /></div><span class="w-8 text-right text-xs font-black text-foreground">{{ branch.risk_score }}</span></div>
                        <p class="mt-1.5 text-[11px] text-muted-foreground">{{ branch.open_reports }} hồ sơ mở · {{ branch.overdue_reports }} quá SLA · {{ branch.active_plans }} kế hoạch đang theo dõi</p>
                    </div>
                </CardContent>
            </Card>

            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-indigo-600 uppercase dark:text-indigo-300">Xu hướng</p><h2 class="mt-1 text-lg font-bold text-foreground">6 tháng gần nhất</h2></div><TrendingUp class="size-5 text-indigo-600" /></div></CardHeader>
                <CardContent class="space-y-4 p-5">
                    <div v-for="month in trend" :key="month.label" class="grid grid-cols-[48px_1fr_28px] items-center gap-2 text-[11px]"><span class="font-semibold text-muted-foreground">{{ month.label }}</span><div class="h-2.5 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-indigo-500" :style="{ width: `${month.total ? Math.max((month.total / trendMax) * 100, 7) : 0}%` }" /></div><span class="text-right font-bold text-foreground">{{ month.total }}</span></div>
                    <div class="grid grid-cols-3 gap-2 border-t border-border/60 pt-4 text-center"><div><p class="text-xl font-black text-foreground">{{ trend.reduce((sum, month) => sum + month.closed, 0) }}</p><p class="text-[10px] text-muted-foreground">Đã đóng</p></div><div><p class="text-xl font-black text-rose-600">{{ trend.reduce((sum, month) => sum + month.critical, 0) }}</p><p class="text-[10px] text-muted-foreground">Nghiêm trọng</p></div><div><p class="text-xl font-black text-indigo-600">{{ formatNumber(reportStats.unassigned) }}</p><p class="text-[10px] text-muted-foreground">Chưa giao xử lý</p></div></div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-amber-600 uppercase dark:text-amber-300">Công việc của tôi</p><h2 class="mt-1 text-lg font-bold text-foreground">Hàng đợi cần xử lý</h2></div><Link href="/operations/audit" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-300">Xem tất cả</Link></div></CardHeader>
                <CardContent class="p-0">
                    <div v-if="!myQueue.length" class="flex flex-col items-center gap-2 p-10 text-center"><CheckCircle2 class="size-8 text-emerald-500" /><p class="font-semibold text-foreground">Không có việc tồn đọng</p><p class="text-xs text-muted-foreground">Các hồ sơ do bạn lập hoặc được giao đều đã được cập nhật.</p></div>
                    <div v-for="report in myQueue" :key="report.id" class="flex items-center gap-3 border-b border-border/50 px-5 py-4 last:border-0"><div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600"><ClipboardList class="size-4" /></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-300">{{ report.report_code }}</span><span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold', statusClass(report.status)]">{{ statusLabel(report.status) }}</span></div><p class="mt-1 truncate text-xs font-semibold text-foreground">{{ report.branch?.name || 'Toàn chuỗi' }} · {{ severityLabel(report.severity_level) }}</p><p class="mt-1 text-[10px] text-muted-foreground">Hạn khắc phục: <span :class="report.is_overdue ? 'font-bold text-rose-600 dark:text-rose-300' : ''">{{ formatDate(report.remediation_deadline) }}</span></p></div><Link href="/operations/audit" class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"><ArrowRight class="size-4" /></Link></div>
                </CardContent>
            </Card>

            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-violet-600 uppercase dark:text-violet-300">Lịch kiểm tra</p><h2 class="mt-1 text-lg font-bold text-foreground">Kế hoạch sắp tới</h2></div><Link href="/operations/audit" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-300">Quản lý</Link></div></CardHeader>
                <CardContent class="space-y-3 p-5">
                    <div v-if="!upcomingPlans.length" class="rounded-xl border border-dashed border-border p-8 text-center text-xs text-muted-foreground">Chưa có kế hoạch đang chờ hoặc đang thực hiện.</div>
                    <div v-for="plan in upcomingPlans" :key="plan.id" class="rounded-2xl border border-border/70 bg-muted/10 p-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-300">{{ plan.plan_code }}</p><p class="mt-1 truncate text-xs font-bold text-foreground">{{ plan.title }}</p></div><span class="shrink-0 rounded-full bg-indigo-500/10 px-2 py-1 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">{{ planTypeLabel(plan.inspection_type) }}</span></div><p class="mt-2 text-[10px] text-muted-foreground">{{ plan.branch?.name || 'Toàn chuỗi' }} · {{ formatDate(plan.scheduled_date) }} · {{ plan.open_reports_count }} hồ sơ mở</p></div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1fr_1fr]">
            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-rose-600 uppercase dark:text-rose-300">Cảnh báo ưu tiên</p><h2 class="mt-1 text-lg font-bold text-foreground">Hồ sơ cần can thiệp</h2></div><AlertTriangle class="size-5 text-rose-600" /></div></CardHeader>
                <CardContent class="p-0"><div v-if="!focusReports.length" class="p-10 text-center text-xs text-muted-foreground">Chưa có hồ sơ rủi ro cao hoặc quá SLA.</div><div v-for="report in focusReports" :key="report.id" class="flex items-center gap-3 border-b border-border/50 px-5 py-3 last:border-0"><div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600"><Gavel class="size-4" /></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-foreground">{{ report.report_code }} · {{ report.branch?.name || 'Toàn chuỗi' }}</p><p class="mt-1 truncate text-[10px] text-muted-foreground">{{ report.description || 'Chưa có mô tả' }}</p></div><span v-if="report.is_overdue" class="shrink-0 text-[10px] font-bold text-rose-600 dark:text-rose-300">Quá SLA</span></div></CardContent>
            </Card>
            <Card class="overflow-hidden border-border/70">
                <CardHeader class="border-b border-border/60 bg-muted/15"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold tracking-[0.16em] text-emerald-600 uppercase dark:text-emerald-300">Hoạt động gần đây</p><h2 class="mt-1 text-lg font-bold text-foreground">Dòng thời gian hồ sơ</h2></div><Link href="/audit-logs" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-300">Nhật ký kiểm toán</Link></div></CardHeader>
                <CardContent class="p-0"><div v-if="!recentReports.length" class="p-10 text-center text-xs text-muted-foreground">Chưa có biên bản thanh tra.</div><div v-for="report in recentReports.slice(0, 5)" :key="report.id" class="flex items-center gap-3 border-b border-border/50 px-5 py-3 last:border-0"><div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600"><UserRound class="size-4" /></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-foreground">{{ report.report_code }} · {{ report.inspector?.name || 'Thanh tra' }}</p><p class="mt-1 truncate text-[10px] text-muted-foreground">{{ report.branch?.name || 'Toàn chuỗi' }} · {{ statusLabel(report.status) }}</p></div><span :class="['rounded-full px-2 py-1 text-[10px] font-bold', statusClass(report.status)]">{{ severityLabel(report.severity_level) }}</span></div></CardContent>
            </Card>
        </section>

        <section class="rounded-3xl border border-border/70 bg-card/90 p-5 shadow-sm md:p-6">
            <div class="flex items-center gap-2"><ListChecks class="size-5 text-indigo-600" /><h2 class="text-lg font-bold text-foreground">Trung tâm nghiệp vụ</h2></div>
            <p class="mt-1 text-xs text-muted-foreground">Các luồng tác nghiệp dành cho tài khoản {{ props.roleLabel.toLowerCase() }}.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <Link href="/operations/audit" class="group rounded-2xl border border-border/70 bg-muted/10 p-4 transition hover:-translate-y-0.5 hover:border-indigo-400/60"><FileWarning class="size-5 text-rose-600" /><p class="mt-3 text-sm font-bold text-foreground">Kế hoạch & biên bản</p><p class="mt-1 text-[11px] text-muted-foreground">Lập hồ sơ, theo dõi SLA và tái kiểm.</p><ArrowRight class="mt-3 size-4 text-muted-foreground transition group-hover:translate-x-1" /></Link>
                <Link href="/operations/company-policies" class="group rounded-2xl border border-border/70 bg-muted/10 p-4 transition hover:-translate-y-0.5 hover:border-indigo-400/60"><ShieldCheck class="size-5 text-indigo-600" /><p class="mt-3 text-sm font-bold text-foreground">Quy định & tiêu chuẩn</p><p class="mt-1 text-[11px] text-muted-foreground">Tra cứu tiêu chuẩn áp dụng khi kiểm tra.</p><ArrowRight class="mt-3 size-4 text-muted-foreground transition group-hover:translate-x-1" /></Link>
                <Link href="/operations-checklist" class="group rounded-2xl border border-border/70 bg-muted/10 p-4 transition hover:-translate-y-0.5 hover:border-indigo-400/60"><ListChecks class="size-5 text-emerald-600" /><p class="mt-3 text-sm font-bold text-foreground">Checklist vận hành</p><p class="mt-1 text-[11px] text-muted-foreground">Kiểm tra theo mẫu và ghi nhận sai lệch.</p><ArrowRight class="mt-3 size-4 text-muted-foreground transition group-hover:translate-x-1" /></Link>
                <Link href="/incidents" class="group rounded-2xl border border-border/70 bg-muted/10 p-4 transition hover:-translate-y-0.5 hover:border-indigo-400/60"><AlertTriangle class="size-5 text-amber-600" /><p class="mt-3 text-sm font-bold text-foreground">Sự cố vận hành</p><p class="mt-1 text-[11px] text-muted-foreground">Tiếp nhận, phân loại và theo dõi sự cố.</p><ArrowRight class="mt-3 size-4 text-muted-foreground transition group-hover:translate-x-1" /></Link>
            </div>
        </section>
    </div>
</template>
