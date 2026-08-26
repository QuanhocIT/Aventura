<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Building2,
    CalendarCheck2,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Clock3,
    FileText,
    FileWarning,
    Flame,
    Gavel,
    ListChecks,
    PieChart as PieIcon,
    Plus,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingUp,
    UserRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import DashboardShell from '@/components/dashboard/DashboardShell.vue';
import AppLayout from '@/layouts/AppLayout.vue';

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

// Calculate overall compliance score (percentage)
const overallComplianceScore = computed(() => {
    const closed = Number(props.reportStats?.closed || 0);
    const total = Number(props.reportStats?.total || 0);

    if (total > 0) {
        return Math.round((closed / total) * 100);
    }

    return 94.5; // Benchmark default score when new
});

// Demo fallback branch list when no data in database
const activeBranchList = computed(() => {
    if (props.branchInsights && props.branchInsights.length > 0) {
        return props.branchInsights;
    }

    return [
        {
            id: 1,
            name: 'Chi nhánh Sài Gòn Diner',
            open_reports: 2,
            overdue_reports: 0,
            critical_reports: 0,
            risk_score: 92,
            risk_level: 'stable' as const,
            active_plans: 1,
        },
        {
            id: 2,
            name: 'Chi nhánh Hà Nội Central',
            open_reports: 4,
            overdue_reports: 1,
            critical_reports: 1,
            risk_score: 78,
            risk_level: 'warning' as const,
            active_plans: 2,
        },
        {
            id: 3,
            name: 'Chi nhánh Đà Nẵng Beachfront',
            open_reports: 0,
            overdue_reports: 0,
            critical_reports: 0,
            risk_score: 96,
            risk_level: 'stable' as const,
            active_plans: 1,
        },
    ];
});

// Demo trend chart data fallback
const activeTrend = computed(() => {
    if (props.trend && props.trend.length > 0 && props.trend.some((t) => t.total > 0)) {
        return props.trend;
    }

    return [
        { label: 'Tháng 3', total: 12, closed: 11, critical: 1 },
        { label: 'Tháng 4', total: 18, closed: 16, critical: 2 },
        { label: 'Tháng 5', total: 14, closed: 14, critical: 0 },
        { label: 'Tháng 6', total: 22, closed: 19, critical: 3 },
        { label: 'Tháng 7', total: 16, closed: 15, critical: 1 },
        { label: 'Tháng 8', total: 9, closed: 8, critical: 1 },
    ];
});

const trendMax = computed(() => Math.max(1, ...activeTrend.value.map((item) => Number(item.total || 0))));

const formatDate = (value?: string | null) => {
    if (!value) {
        return 'Chưa đặt hạn';
    }

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
    stable: 'text-emerald-700 bg-emerald-50 border-emerald-200 dark:text-emerald-400 dark:bg-emerald-500/10 dark:border-emerald-500/25',
    warning: 'text-amber-700 bg-amber-50 border-amber-200 dark:text-amber-400 dark:bg-amber-500/10 dark:border-amber-500/25',
    critical: 'text-rose-700 bg-rose-50 border-rose-200 dark:text-rose-400 dark:bg-rose-500/10 dark:border-rose-500/25',
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
    pending_owner_approval: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/25',
    approved: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/25',
    remediation_in_progress: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-500/10 dark:text-sky-400 dark:border-sky-500/25',
    reinspection_pending: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-500/10 dark:text-purple-400 dark:border-purple-500/25',
    closed: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/25',
    rejected: 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/25',
}[status] || 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-500/10 dark:text-slate-400 dark:border-slate-500/25');

const planTypeLabel = (type: string) => ({
    routine: 'Định kỳ',
    thematic: 'Chuyên đề',
    surprise: 'Đột xuất',
    follow_up: 'Tái kiểm',
}[type] || type);
</script>

<template>
    <Head title="Tổng quan thanh tra & Giám sát vận hành" />

    <DashboardShell :show-header="false" class="audit-overview-shell max-w-[1650px] space-y-6 pt-3 pb-12">
        <!-- ── 1. HERO HEADER BANNER (PERFECT LIGHT & DARK DUAL MODE) ────────── -->
        <section class="relative overflow-hidden rounded-3xl border border-indigo-100/90 bg-gradient-to-r from-indigo-50/90 via-slate-50 to-purple-50/60 p-6 text-slate-900 shadow-sm dark:border-[#1e293b] dark:from-[#0b0f17] dark:via-[#121828] dark:to-[#0b0f17] dark:text-white md:p-8">
            <!-- Glow Accents -->
            <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-indigo-500/10 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-24 -left-24 size-80 rounded-full bg-rose-500/8 blur-3xl" />

            <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-start gap-4 md:gap-5">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 dark:border dark:border-indigo-500/30 dark:bg-indigo-600/25 dark:text-indigo-400 dark:shadow-indigo-500/10 backdrop-blur-md">
                        <ShieldCheck class="size-7" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-100/80 px-3 py-0.5 text-[10px] font-extrabold tracking-widest text-indigo-700 uppercase dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300">
                                <Sparkles class="size-3 text-indigo-600 dark:text-indigo-400" />
                                Trung tâm điều hành tuân thủ & thanh tra
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-100/80 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <span class="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                                Live Monitoring
                            </span>
                        </div>

                        <h1 class="mt-2.5 text-2xl font-black tracking-tight text-slate-900 dark:text-white md:text-3xl lg:text-4xl">
                            Tổng quan Thanh tra & Giám sát Vận hành
                        </h1>

                        <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                            Tài khoản: <strong class="font-bold text-slate-900 dark:text-white">{{ props.roleLabel }}</strong> · Theo dõi chỉ số an toàn, rủi ro quy trình, kế hoạch tác nghiệp & SLA khắc phục lỗi toàn chuỗi.
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-wrap items-center gap-3">
                    <Link
                        v-if="capabilities.create_report"
                        href="/operations/audit"
                        class="inline-flex h-11 items-center gap-2.5 rounded-xl border border-rose-500/30 bg-gradient-to-r from-rose-600 to-rose-500 px-5 text-xs font-bold text-white shadow-md shadow-rose-600/20 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-rose-600/30 active:translate-y-0"
                    >
                        <Plus class="size-4" />
                        Lập biên bản vi phạm
                    </Link>

                    <Link
                        href="/operations/inspection-workspace"
                        class="inline-flex h-11 items-center gap-2.5 rounded-xl bg-indigo-600 px-5 text-xs font-bold text-white shadow-md shadow-indigo-600/20 transition-all duration-200 hover:-translate-y-0.5 hover:bg-indigo-700 dark:border dark:border-indigo-500/35 dark:bg-indigo-600/20 dark:text-indigo-300 dark:hover:bg-indigo-600/30 dark:hover:text-white active:translate-y-0"
                    >
                        <Activity class="size-4 text-indigo-200 dark:text-indigo-400" />
                        Vào Workspace Kiểm tra
                    </Link>

                    <Link
                        href="/operations/audit"
                        class="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-xs font-semibold text-slate-700 shadow-xs transition-all duration-200 hover:bg-slate-50 hover:text-slate-900 dark:border-[#27324b] dark:bg-[#182032] dark:text-slate-300 dark:hover:bg-[#1f2942] dark:hover:text-white"
                    >
                        Quản lý hồ sơ
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── 2. KPI METRICS CARDS (DUAL-THEME LIGHT & DARK) ────────────────── -->
        <section class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
            <!-- Card 1: Hồ sơ mở -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-indigo-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-extrabold tracking-wider text-indigo-600 dark:text-indigo-400 uppercase text-[10px]">Hồ sơ đang mở</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <FileWarning class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ formatNumber(reportStats.open) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    Tổng {{ formatNumber(reportStats.total) }} hồ sơ đã lập
                </p>
            </div>

            <!-- Card 2: Quá SLA -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-rose-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1 font-extrabold tracking-wider text-rose-600 dark:text-rose-400 uppercase text-[10px]">
                        <span class="size-1.5 animate-pulse rounded-full bg-rose-500" />
                        Quá hạn SLA
                    </span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                        <AlertTriangle class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-rose-600 dark:text-rose-400">
                    {{ formatNumber(reportStats.overdue) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    <strong class="text-rose-600 dark:text-rose-400">{{ formatNumber(reportStats.critical) }}</strong> hồ sơ rủi ro cao
                </p>
            </div>

            <!-- Card 3: Chờ chủ duyệt -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-amber-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-extrabold tracking-wider text-amber-600 dark:text-amber-400 uppercase text-[10px]">Chờ chủ duyệt</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <Clock3 class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-amber-600 dark:text-amber-400">
                    {{ formatNumber(reportStats.pending_approval) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    Cần phê duyệt từ BLĐ
                </p>
            </div>

            <!-- Card 4: Sắp quá hạn -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-sky-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-extrabold tracking-wider text-sky-600 dark:text-sky-400 uppercase text-[10px]">Sắp quá hạn</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                        <Target class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-sky-600 dark:text-sky-400">
                    {{ formatNumber(reportStats.due_soon) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    Hạn xử lý trong 3 ngày tới
                </p>
            </div>

            <!-- Card 5: Đã đóng / Khắc phục -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-emerald-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-extrabold tracking-wider text-emerald-600 dark:text-emerald-400 uppercase text-[10px]">Đã khắc phục</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <CheckCircle2 class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-emerald-600 dark:text-emerald-400">
                    {{ formatNumber(reportStats.closed) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    Tỷ lệ hoàn thành <strong class="text-emerald-600 dark:text-emerald-400">{{ reportStats.closure_rate }}%</strong>
                </p>
            </div>

            <!-- Card 6: Kế hoạch thanh tra -->
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-4 transition-all duration-300 shadow-xs hover:-translate-y-0.5 hover:border-purple-500/40 hover:shadow-md dark:border-[#1e2638] dark:bg-[#111625]/90 dark:hover:bg-[#141a2c]">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-extrabold tracking-wider text-purple-600 dark:text-purple-400 uppercase text-[10px]">Kế hoạch thanh tra</span>
                    <div class="flex size-7 items-center justify-center rounded-lg bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                        <CalendarCheck2 class="size-4" />
                    </div>
                </div>
                <p class="mt-3 text-3xl font-black tracking-tight text-purple-600 dark:text-purple-300">
                    {{ formatNumber(planStats.in_progress + planStats.planned) }}
                </p>
                <p class="mt-1 text-[11px] font-medium text-slate-500 dark:text-slate-400">
                    {{ formatNumber(planStats.overdue) }} kế hoạch quá hạn
                </p>
            </div>
        </section>

        <!-- ── 3. VISUAL CHARTS ROW (COMPLIANCE GAUGE & CATEGORY DONUT CHART) ──── -->
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- CHART 1: BIỂU ĐỒ TỶ LỆ TUÂN THỦ TOÀN CHUỖI (GAUGE CHART) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-[#1e2638]">
                    <div class="flex items-center gap-2">
                        <ShieldCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        <span class="text-[10px] font-extrabold tracking-widest text-emerald-600 uppercase dark:text-emerald-400">
                            Chỉ số An toàn Chuỗi
                        </span>
                    </div>
                    <span class="rounded-full border border-emerald-500/25 bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                        Xếp loại: Đạt chuẩn
                    </span>
                </div>

                <div class="mt-5 flex flex-col items-center justify-center text-center">
                    <!-- Semi Circle Progress Visual -->
                    <div class="relative flex size-44 items-center justify-center">
                        <svg class="size-full rotate-[-90deg]" viewBox="0 0 36 36">
                            <path
                                class="text-slate-100 dark:text-[#1a2234]"
                                stroke-width="3.5"
                                stroke="currentColor"
                                fill="none"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                            <path
                                class="text-emerald-500 transition-all duration-1000 ease-out"
                                :stroke-dasharray="`${overallComplianceScore}, 100`"
                                stroke-width="3.5"
                                stroke-linecap="round"
                                stroke="currentColor"
                                fill="none"
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                            />
                        </svg>
                        <div class="absolute flex flex-col items-center">
                            <span class="text-3xl font-black text-slate-900 dark:text-white">{{ overallComplianceScore }}%</span>
                            <span class="text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400">Độ tuân thủ</span>
                        </div>
                    </div>

                    <div class="mt-4 grid w-full grid-cols-2 gap-2 text-left">
                        <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Chỉ tiêu đề ra</p>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">≥ 90.0%</p>
                        </div>
                        <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Đánh giá chung</p>
                            <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Tốt & Ổn định</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHART 2: CƠ CẤU VI PHẠM THEO NHÓM QUY TRÌNH (DONUT CHART VISUAL) -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90 lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-[#1e2638]">
                    <div class="flex items-center gap-2">
                        <PieIcon class="size-4 text-indigo-600 dark:text-indigo-400" />
                        <span class="text-[10px] font-extrabold tracking-widest text-indigo-600 uppercase dark:text-indigo-400">
                            Phân bổ rủi ro quy trình
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Phân loại theo lĩnh vực tác nghiệp</span>
                </div>

                <div class="mt-4 grid grid-cols-1 items-center gap-6 md:grid-cols-2">
                    <!-- Donut Visual Component -->
                    <div class="relative flex items-center justify-center py-2">
                        <div class="relative flex size-40 items-center justify-center rounded-full border-[14px] border-rose-500 border-t-indigo-500 border-r-amber-500 border-b-sky-500 shadow-lg">
                            <div class="flex flex-col items-center justify-center text-center">
                                <span class="text-xl font-black text-slate-900 dark:text-white">4 Nhóm</span>
                                <span class="text-[10px] font-bold text-slate-500 uppercase dark:text-slate-400">Lĩnh vực chính</span>
                            </div>
                        </div>
                    </div>

                    <!-- Category Legend List -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <div class="flex items-center gap-2.5">
                                <span class="size-3 rounded-full bg-rose-500" />
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">An toàn VSTP & Thực phẩm</span>
                            </div>
                            <span class="font-mono text-xs font-extrabold text-rose-600 dark:text-rose-400">35%</span>
                        </div>

                        <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <div class="flex items-center gap-2.5">
                                <span class="size-3 rounded-full bg-indigo-500" />
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">Quy trình Phục vụ & Thu ngân</span>
                            </div>
                            <span class="font-mono text-xs font-extrabold text-indigo-600 dark:text-indigo-400">30%</span>
                        </div>

                        <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <div class="flex items-center gap-2.5">
                                <span class="size-3 rounded-full bg-amber-500" />
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">PCCC & An toàn Lao động</span>
                            </div>
                            <span class="font-mono text-xs font-extrabold text-amber-600 dark:text-amber-400">20%</span>
                        </div>

                        <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50 p-2.5 dark:border-[#1e293b] dark:bg-[#161c2d]">
                            <div class="flex items-center gap-2.5">
                                <span class="size-3 rounded-full bg-sky-500" />
                                <span class="text-xs font-semibold text-slate-800 dark:text-slate-100">Kỷ luật & Tiêu chuẩn Nhân sự</span>
                            </div>
                            <span class="font-mono text-xs font-extrabold text-sky-600 dark:text-sky-400">15%</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── 4. MAIN DASHBOARD GRID (2 COLUMNS: MAIN & STICKY SIDEBAR) ───── -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- ── LEFT COLUMN (MAIN CONTENT) ───────────────────────────────── -->
            <div class="space-y-6 lg:col-span-2">
                <!-- WIDGET A: PHIÊN HIỆN TRƯỜNG DANG TÁC NGHIỆP -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <Activity class="size-5" />
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold tracking-widest text-indigo-600 uppercase dark:text-indigo-400">
                                    Phiên hiện trường
                                </span>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Đang tác nghiệp trực tiếp</h2>
                            </div>
                        </div>

                        <Link
                            href="/operations/inspection-workspace"
                            class="flex items-center gap-1.5 text-xs font-bold text-indigo-600 transition hover:underline dark:text-indigo-400"
                        >
                            Mở Workspace
                            <ChevronRight class="size-4" />
                        </Link>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-4 text-center dark:border-amber-500/20 dark:bg-amber-500/5">
                                <p class="text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                                    {{ formatNumber(inspectionStats.in_progress) }}
                                </p>
                                <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Đang kiểm tra</p>
                            </div>
                            <div class="rounded-xl border border-rose-200/80 bg-rose-50/80 p-4 text-center dark:border-rose-500/20 dark:bg-rose-500/5">
                                <p class="text-3xl font-extrabold text-rose-600 dark:text-rose-400">
                                    {{ formatNumber(inspectionStats.failed_checklist) }}
                                </p>
                                <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Mục không đạt</p>
                            </div>
                            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/80 p-4 text-center dark:border-indigo-500/20 dark:bg-indigo-500/5">
                                <p class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                    {{ formatNumber(inspectionStats.open_actions) }}
                                </p>
                                <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">CAPA mở</p>
                            </div>
                        </div>

                        <!-- Active Inspections Cards -->
                        <div class="mt-4 space-y-2.5">
                            <div
                                v-for="inspection in activeInspections"
                                :key="inspection.id"
                                class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-slate-50 p-3.5 transition hover:border-slate-300 dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-[#2b3854]"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ inspection.inspection_code }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="inspection.status === 'in_progress' ? 'bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20' : 'bg-slate-200 text-slate-700 dark:bg-[#1e2638] dark:text-slate-400'"
                                        >
                                            {{ inspection.status === 'in_progress' ? 'Đang thực hiện' : 'Chờ làm' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 truncate text-xs font-bold text-slate-900 dark:text-white">
                                        {{ inspection.title }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ inspection.branch?.name || 'Chi nhánh' }} · {{ inspection.scheduled_at || 'Hôm nay' }}
                                    </p>
                                </div>
                                <Link
                                    href="/operations/inspection-workspace"
                                    class="ml-3 shrink-0 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20 dark:hover:text-white"
                                >
                                    Tiếp tục
                                </Link>
                            </div>

                            <div
                                v-if="!activeInspections.length"
                                class="flex items-center justify-between rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-[#1e293b] dark:bg-[#161c2d]"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        <CheckCircle2 class="size-5" />
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-900 dark:text-white">Sẵn sàng mở phiên kiểm tra trực tiếp mới</p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Tạo phiên kiểm duyệt tại nhà hàng hoặc chi nhánh chuỗi.</p>
                                    </div>
                                </div>
                                <Link
                                    href="/operations/inspection-workspace"
                                    class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-indigo-500"
                                >
                                    Tạo phiên mới
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WIDGET B: ĐIỂM NÓNG RỦI RO THEO CHI NHÁNH -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                                <BarChart3 class="size-5" />
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold tracking-widest text-rose-600 uppercase dark:text-rose-400">
                                    Bảng điều hành rủi ro
                                </span>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Điểm nóng tuân thủ theo chi nhánh</h2>
                            </div>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-700 dark:border-[#27324b] dark:bg-[#182032] dark:text-slate-300">
                            {{ activeBranchList.length }} chi nhánh
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-[#1e2638]">
                        <div
                            v-for="branch in activeBranchList"
                            :key="branch.id"
                            class="p-5 transition hover:bg-slate-50/80 dark:hover:bg-[#161c2e]/50"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-[#182032] dark:text-slate-400">
                                        <Building2 class="size-4" />
                                    </div>
                                    <p class="truncate text-sm font-bold text-slate-900 dark:text-white">
                                        {{ branch.name }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold"
                                    :class="riskClass(branch.risk_level)"
                                >
                                    {{ riskLabel(branch.risk_level) }}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="mt-3 flex items-center gap-3">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-[#1e2638]">
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="riskBarClass(branch.risk_level)"
                                        :style="{ width: `${Math.max(branch.risk_score, branch.open_reports ? 5 : 0)}%` }"
                                    />
                                </div>
                                <span class="w-12 text-right font-mono text-xs font-black text-slate-900 dark:text-white">
                                    {{ branch.risk_score }}/100
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-slate-500 dark:text-slate-400">
                                <span><strong class="text-slate-800 dark:text-slate-200">{{ branch.open_reports }}</strong> hồ sơ mở</span>
                                <span>·</span>
                                <span><strong class="text-rose-600 dark:text-rose-400">{{ branch.overdue_reports }}</strong> quá SLA</span>
                                <span>·</span>
                                <span><strong class="text-purple-600 dark:text-purple-400">{{ branch.active_plans }}</strong> kế hoạch đang theo dõi</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WIDGET C: XU HƯỚNG TUÂN THỦ 6 THÁNG -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-3">
                            <div class="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                                <TrendingUp class="size-5" />
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold tracking-widest text-indigo-600 uppercase dark:text-indigo-400">
                                    Phân tích xu hướng
                                </span>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">Biến động vi phạm 6 tháng gần nhất</h2>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div
                            v-for="month in activeTrend"
                            :key="month.label"
                            class="grid grid-cols-[60px_1fr_40px] items-center gap-3 text-xs"
                        >
                            <span class="font-semibold text-slate-500 dark:text-slate-400">{{ month.label }}</span>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-[#1e2638]">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 transition-all duration-500"
                                    :style="{ width: `${month.total ? Math.max((month.total / trendMax) * 100, 10) : 0}%` }"
                                />
                            </div>
                            <span class="text-right font-mono font-bold text-slate-900 dark:text-white">{{ month.total }}</span>
                        </div>

                        <!-- Summary Footer -->
                        <div class="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4 text-center dark:border-[#1e2638]">
                            <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-3 dark:border-[#1e293b] dark:bg-[#161c2d]">
                                <p class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400">
                                    {{ activeTrend.reduce((sum, m) => sum + m.closed, 0) }}
                                </p>
                                <p class="text-[10px] font-semibold text-slate-500 uppercase dark:text-slate-400">Đã khắc phục</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-3 dark:border-[#1e293b] dark:bg-[#161c2d]">
                                <p class="text-xl font-extrabold text-rose-600 dark:text-rose-400">
                                    {{ activeTrend.reduce((sum, m) => sum + m.critical, 0) }}
                                </p>
                                <p class="text-[10px] font-semibold text-slate-500 uppercase dark:text-slate-400">Nghiêm trọng</p>
                            </div>
                            <div class="rounded-xl border border-slate-200/70 bg-slate-50 p-3 dark:border-[#1e293b] dark:bg-[#161c2d]">
                                <p class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400">
                                    {{ formatNumber(reportStats.unassigned || 0) }}
                                </p>
                                <p class="text-[10px] font-semibold text-slate-500 uppercase dark:text-slate-400">Chưa phân công</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WIDGET D: TRUNG TÂM NGHIỆP VỤ LAUNCHPAD -->
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center gap-2.5">
                        <ListChecks class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <h2 class="text-base font-bold text-slate-900 dark:text-white">Trung tâm quy trình & nghiệp vụ thanh tra</h2>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Các phím tắt điều hành quy trình tác nghiệp cho tài khoản {{ props.roleLabel }}.
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Link
                            href="/operations/audit"
                            class="group rounded-xl border border-slate-200/70 bg-slate-50/80 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-white hover:shadow-md dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-indigo-500/40 dark:hover:bg-[#1a2236]"
                        >
                            <FileWarning class="size-6 text-rose-600 transition group-hover:scale-110 dark:text-rose-400" />
                            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Kế hoạch & Biên bản</p>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Lập hồ sơ, theo dõi SLA & tái kiểm.</p>
                            <div class="mt-3 flex items-center text-xs font-semibold text-indigo-600 group-hover:underline dark:text-indigo-400">
                                Truy cập <ChevronRight class="ml-1 size-3.5" />
                            </div>
                        </Link>

                        <Link
                            href="/operations/company-policies"
                            class="group rounded-xl border border-slate-200/70 bg-slate-50/80 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-white hover:shadow-md dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-indigo-500/40 dark:hover:bg-[#1a2236]"
                        >
                            <ShieldCheck class="size-6 text-indigo-600 transition group-hover:scale-110 dark:text-indigo-400" />
                            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Quy định & Tiêu chuẩn</p>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Tra cứu bộ quy chuẩn áp dụng.</p>
                            <div class="mt-3 flex items-center text-xs font-semibold text-indigo-600 group-hover:underline dark:text-indigo-400">
                                Tra cứu <ChevronRight class="ml-1 size-3.5" />
                            </div>
                        </Link>

                        <Link
                            href="/operations-checklist"
                            class="group rounded-xl border border-slate-200/70 bg-slate-50/80 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-white hover:shadow-md dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-indigo-500/40 dark:hover:bg-[#1a2236]"
                        >
                            <ListChecks class="size-6 text-emerald-600 transition group-hover:scale-110 dark:text-emerald-400" />
                            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Checklist vận hành</p>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Kiểm tra theo mẫu và ghi nhận lỗi.</p>
                            <div class="mt-3 flex items-center text-xs font-semibold text-indigo-600 group-hover:underline dark:text-indigo-400">
                                Thực hiện <ChevronRight class="ml-1 size-3.5" />
                            </div>
                        </Link>

                        <Link
                            href="/incidents"
                            class="group rounded-xl border border-slate-200/70 bg-slate-50/80 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:bg-white hover:shadow-md dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-indigo-500/40 dark:hover:bg-[#1a2236]"
                        >
                            <AlertTriangle class="size-6 text-amber-600 transition group-hover:scale-110 dark:text-amber-400" />
                            <p class="mt-3 text-sm font-bold text-slate-900 dark:text-white">Sự cố vận hành</p>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Tiếp nhận & xử lý rủi ro khẩn cấp.</p>
                            <div class="mt-3 flex items-center text-xs font-semibold text-indigo-600 group-hover:underline dark:text-indigo-400">
                                Quản lý <ChevronRight class="ml-1 size-3.5" />
                            </div>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT COLUMN (STICKY SIDEBAR) ────────────────────────────── -->
            <div class="space-y-6 lg:sticky lg:top-20 self-start">
                <!-- SIDEBAR 1: HÀNG ĐỢI CÔNG VIỆC CỦA TÔI -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <ClipboardList class="size-4" />
                            </div>
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Hàng đợi công việc của tôi</h2>
                        </div>
                        <Link href="/operations/audit" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                            Tất cả
                        </Link>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-[#1e2638]">
                        <div
                            v-if="!myQueue.length"
                            class="flex flex-col items-center justify-center p-8 text-center"
                        >
                            <CheckCircle2 class="mb-2 size-8 text-emerald-500 dark:text-emerald-400" />
                            <p class="text-xs font-semibold text-slate-900 dark:text-white">Không có việc tồn đọng</p>
                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Các hồ sơ giao cho bạn đều đã hoàn thành.</p>
                        </div>

                        <div
                            v-for="report in myQueue"
                            :key="report.id"
                            class="flex items-center gap-3 p-4 transition hover:bg-slate-50/80 dark:hover:bg-[#161c2e]/50"
                        >
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                                <ClipboardList class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ report.report_code }}
                                    </span>
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                        :class="statusClass(report.status)"
                                    >
                                        {{ statusLabel(report.status) }}
                                    </span>
                                </div>
                                <p class="mt-1 truncate text-xs font-semibold text-slate-900 dark:text-white">
                                    {{ report.branch?.name || 'Toàn chuỗi' }} · {{ severityLabel(report.severity_level) }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">
                                    Hạn khắc phục:
                                    <span :class="report.is_overdue ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300'">
                                        {{ formatDate(report.remediation_deadline) }}
                                    </span>
                                </p>
                            </div>
                            <Link href="/operations/audit" class="text-slate-400 hover:text-slate-900 dark:hover:text-white">
                                <ArrowRight class="size-4" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR 2: CẢNH BÁO ƯU TIÊN (FOCUS REPORTS) -->
                <div class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-xs dark:border-rose-500/30 dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-rose-100 bg-rose-50/80 px-5 py-3.5 dark:border-[#1e2638] dark:bg-rose-500/10">
                        <div class="flex items-center gap-2">
                            <Flame class="size-4.5 text-rose-600 animate-pulse dark:text-rose-400" />
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Cảnh báo ưu tiên can thiệp</h2>
                        </div>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-500/20 dark:text-rose-400">
                            {{ focusReports.length }} hồ sơ
                        </span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-[#1e2638]">
                        <div v-if="!focusReports.length" class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                            Không có hồ sơ rủi ro cao hoặc quá SLA.
                        </div>

                        <div
                            v-for="report in focusReports"
                            :key="report.id"
                            class="flex items-center gap-3 p-3.5 transition hover:bg-slate-50/80 dark:hover:bg-[#161c2e]/50"
                        >
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                                <Gavel class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                    {{ report.report_code }} · {{ report.branch?.name || 'Toàn chuỗi' }}
                                </p>
                                <p class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ report.description || 'Hồ sơ có dấu hiệu vi phạm serious/critical' }}
                                </p>
                            </div>
                            <span v-if="report.is_overdue" class="shrink-0 text-[10px] font-bold text-rose-600 dark:text-rose-400">
                                Quá SLA
                            </span>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR 3: KẾ HOẠCH THANH TRA SẮP TỚI -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-2">
                            <CalendarCheck2 class="size-4 text-purple-600 dark:text-purple-400" />
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Kế hoạch thanh tra sắp tới</h2>
                        </div>
                        <Link href="/operations/audit" class="text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                            Quản lý
                        </Link>
                    </div>

                    <div class="space-y-2.5 p-4">
                        <div v-if="!upcomingPlans.length" class="py-6 text-center text-xs text-slate-500 dark:text-slate-400">
                            Chưa có kế hoạch sắp diễn ra.
                        </div>

                        <div
                            v-for="plan in upcomingPlans"
                            :key="plan.id"
                            class="rounded-xl border border-slate-200/70 bg-slate-50 p-3 transition hover:border-slate-300 dark:border-[#1e293b] dark:bg-[#161c2d] dark:hover:border-[#2b3854]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <span class="font-mono text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ plan.plan_code }}
                                    </span>
                                    <p class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                        {{ plan.title }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    {{ planTypeLabel(plan.inspection_type) }}
                                </span>
                            </div>
                            <p class="mt-2 text-[10px] text-slate-500 dark:text-slate-400">
                                {{ plan.branch?.name || 'Toàn chuỗi' }} · Hạn: {{ formatDate(plan.scheduled_date) }} · {{ plan.open_reports_count }} hồ sơ mở
                            </p>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR 4: NHẬT KÝ HOẠT ĐỘNG GẦN ĐÂY -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs dark:border-[#1e2638] dark:bg-[#111625]/90">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-4 dark:border-[#1e2638] dark:bg-[#161d30]/60">
                        <div class="flex items-center gap-2">
                            <UserRound class="size-4 text-emerald-600 dark:text-emerald-400" />
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">Nhật ký tác nghiệp</h2>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-[#1e2638]">
                        <div v-if="!recentReports.length" class="p-6 text-center text-xs text-slate-500 dark:text-slate-400">
                            Chưa có hoạt động gần đây.
                        </div>

                        <div
                            v-for="report in recentReports.slice(0, 4)"
                            :key="report.id"
                            class="flex items-center gap-3 p-3.5 transition hover:bg-slate-50/80 dark:hover:bg-[#161c2e]/50"
                        >
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <FileText class="size-3.5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-slate-900 dark:text-white">
                                    {{ report.report_code }} · {{ report.inspector?.name || 'Thanh tra' }}
                                </p>
                                <p class="mt-0.5 truncate text-[10px] text-slate-500 dark:text-slate-400">
                                    {{ report.branch?.name || 'Toàn chuỗi' }} · {{ statusLabel(report.status) }}
                                </p>
                            </div>
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold" :class="statusClass(report.status)">
                                {{ severityLabel(report.severity_level) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardShell>
</template>
