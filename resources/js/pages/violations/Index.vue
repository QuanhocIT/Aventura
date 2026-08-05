<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ShieldCheck,
    ShieldAlert,
    FileText,
    Send,
    CheckCircle2,
    Search,
    Info,
    Clock,
    Scale,
    BadgeAlert,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Report {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string;
    job_title: string;
    reported_by_name: string;
    violation_type: string;
    severity: 'low' | 'medium' | 'high' | 'critical';
    description: string;
    penalty_amount: number;
    occurred_at: string;
    occurred_at_display: string;
    status: 'open' | 'reviewed' | 'resolved' | 'dismissed';
    is_anonymous: boolean;
    created_at: string;
}

interface Employee {
    id: number;
    full_name: string;
    job_title: string;
    employee_code: string;
}

const props = defineProps<{
    reports: Report[];
    employees: Employee[];
    currentUserRole: string;
}>();

// --- STATE ---
const activeTab = ref<'reports' | 'submit'>('reports');
const activeFilter = ref<'all' | 'open' | 'resolved' | 'dismissed'>('all');
const showResolveModal = ref(false);
const selectedReport = ref<Report | null>(null);
const searchQuery = ref('');

// Form Whistleblower Creation
const reportForm = useForm({
    employee_id: '',
    violation_type: '',
    description: '',
    is_anonymous: true,
    occurred_at: todayDateString(),
});

// Form Owner Resolve
const resolveForm = useForm({
    severity: 'low' as 'low' | 'medium' | 'high' | 'critical',
    penalty_amount: 0,
    status: 'resolved' as 'resolved' | 'dismissed',
    resolution_notes: '',
});

// --- COMPUTED ---
const isOwner = computed(() => {
    const authUser = usePage().props.auth?.user as any;

    return (
        authUser?.permissions?.includes('manage_violations') ||
        props.currentUserRole === 'owner'
    );
});

const filteredReports = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.reports.filter((r) => {
        const matchFilter =
            activeFilter.value === 'all' ||
            (activeFilter.value === 'open' && r.status === 'open') ||
            (activeFilter.value === 'resolved' && r.status === 'resolved') ||
            (activeFilter.value === 'dismissed' && r.status === 'dismissed');

        if (!matchFilter) {
            return false;
        }

        if (!q) {
            return true;
        }

        return (
            r.employee_name.toLowerCase().includes(q) ||
            r.violation_type.toLowerCase().includes(q) ||
            r.description.toLowerCase().includes(q) ||
            r.employee_code.toLowerCase().includes(q)
        );
    });
});

const criticalOpen = computed(() =>
    props.reports.filter(
        (r) =>
            r.status === 'open' &&
            (r.severity === 'critical' || r.severity === 'high'),
    ),
);

const reportStats = computed(() => ({
    open: props.reports.filter((r) => r.status === 'open').length,
    critical: props.reports.filter(
        (r) => r.severity === 'critical' && r.status === 'open',
    ).length,
    resolved: props.reports.filter((r) => r.status === 'resolved').length,
    dismissed: props.reports.filter((r) => r.status === 'dismissed').length,
}));

// --- ACTIONS ---
const submitReport = () => {
    if (reportForm.processing) {
        return;
    }

    reportForm.post('/violations', {
        onSuccess: () => {
            activeTab.value = 'reports';
            reportForm.reset();
        },
    });
};

const openResolveModal = (report: Report) => {
    selectedReport.value = report;
    resolveForm.severity = report.severity;
    resolveForm.penalty_amount = parseFloat(String(report.penalty_amount)) || 0;
    resolveForm.status = 'resolved';
    resolveForm.resolution_notes = '';
    showResolveModal.value = true;
};

const submitResolve = () => {
    if (resolveForm.processing) {
        return;
    }

    if (!selectedReport.value) {
        return;
    }

    resolveForm.post(`/violations/${selectedReport.value.id}/resolve`, {
        onSuccess: () => {
            showResolveModal.value = false;
            selectedReport.value = null;
            resolveForm.reset();
        },
    });
};

function todayDateString() {
    return new Date().toISOString().split('T')[0];
}

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

const severityConfig: Record<
    string,
    { label: string; text: string; bg: string; border: string }
> = {
    low: {
        label: 'Thấp',
        text: 'text-slate-600 dark:text-slate-400',
        bg: 'bg-slate-100 dark:bg-slate-800/60',
        border: 'border-slate-200/50',
    },
    medium: {
        label: 'Trung bình',
        text: 'text-amber-700 dark:text-amber-400',
        bg: 'bg-amber-50 dark:bg-amber-950/20',
        border: 'border-amber-200/40',
    },
    high: {
        label: 'Cao',
        text: 'text-orange-700 dark:text-orange-400',
        bg: 'bg-orange-50 dark:bg-orange-950/20',
        border: 'border-orange-200/40',
    },
    critical: {
        label: 'Nghiêm trọng',
        text: 'text-rose-700 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/20',
        border: 'border-rose-200/40 animate-pulse',
    },
};

const statusConfig: Record<
    string,
    { label: string; color: string; bg: string }
> = {
    open: {
        label: 'Chờ giải quyết',
        color: 'text-blue-700 dark:text-blue-400',
        bg: 'bg-blue-100 dark:bg-blue-950/30',
    },
    reviewed: {
        label: 'Đang xem xét',
        color: 'text-amber-700 dark:text-amber-400',
        bg: 'bg-amber-100 dark:bg-amber-950/30',
    },
    resolved: {
        label: 'Đã giải quyết',
        color: 'text-emerald-700 dark:text-emerald-400',
        bg: 'bg-emerald-100 dark:bg-emerald-950/30',
    },
    dismissed: {
        label: 'Đã bác bỏ',
        color: 'text-slate-500 dark:text-slate-400',
        bg: 'bg-slate-100 dark:bg-slate-800',
    },
};
</script>

<template>
    <Head title="Hòm thư Tố cáo Nội bộ & AI Integrity Control" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 shadow-sm dark:bg-rose-950/60 dark:text-rose-400"
                >
                    <Scale class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Giám Sát Nội Bộ & Sai Phạm
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Cơ chế giám sát chéo minh bạch, hòm thư tố cáo ẩn danh
                        bảo vệ nhân sự và quy trình cấn trừ phạt trực tiếp vào
                        bảng lương.
                    </p>
                </div>
            </div>

            <!-- Tab switcher -->
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="activeTab = 'reports'"
                    :class="[
                        'rounded-xl text-xs font-bold transition-all',
                        activeTab === 'reports'
                            ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:bg-indigo-950/20'
                            : '',
                    ]"
                >
                    <FileText class="mr-1.5 size-4" />
                    Danh sách tố cáo
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    @click="activeTab = 'submit'"
                    :class="[
                        'rounded-xl text-xs font-bold transition-all',
                        activeTab === 'submit'
                            ? 'border-indigo-200 bg-indigo-50 text-indigo-600 dark:bg-indigo-950/20'
                            : '',
                    ]"
                >
                    <Send class="mr-1.5 size-4" />
                    Gửi tố cáo ẩn danh
                </Button>
            </div>
        </div>

        <!-- TAB 1: LIST OF REPORTS -->
        <div
            v-if="activeTab === 'reports'"
            class="animate-fade-in flex flex-col gap-4"
        >
            <!-- Critical alert banner -->
            <div
                v-if="criticalOpen.length > 0"
                class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950/30"
            >
                <BadgeAlert
                    class="mt-0.5 size-5 shrink-0 text-rose-600 dark:text-rose-400"
                />
                <div class="flex-1">
                    <p
                        class="text-sm font-bold text-rose-800 dark:text-rose-300"
                    >
                        {{ criticalOpen.length }} tố cáo mức độ CAO / NGHIÊM
                        TRỌNG chưa xử lý!
                    </p>
                    <p class="mt-0.5 text-xs text-rose-600 dark:text-rose-400">
                        Các tố cáo này cần được phân xử kịp thời để bảo vệ tài
                        sản và uy tín nhà hàng.
                    </p>
                </div>
                <button
                    @click="activeFilter = 'open'"
                    class="shrink-0 text-xs font-bold text-rose-700 underline dark:text-rose-400"
                >
                    Xem ngay
                </button>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="rounded-xl border border-blue-100 bg-blue-50 p-3 dark:border-blue-900/30 dark:bg-blue-950/20"
                >
                    <p
                        class="text-2xl font-black text-blue-700 dark:text-blue-400"
                    >
                        {{ reportStats.open }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-blue-500 uppercase"
                    >
                        Đang xử lý
                    </p>
                </div>
                <div
                    class="rounded-xl border border-rose-100 bg-rose-50 p-3 dark:border-rose-900/30 dark:bg-rose-950/20"
                >
                    <p
                        class="text-2xl font-black text-rose-700 dark:text-rose-400"
                    >
                        {{ reportStats.critical }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-rose-500 uppercase"
                    >
                        Nghiêm trọng
                    </p>
                </div>
                <div
                    class="rounded-xl border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/30 dark:bg-emerald-950/20"
                >
                    <p
                        class="text-2xl font-black text-emerald-700 dark:text-emerald-400"
                    >
                        {{ reportStats.resolved }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-emerald-500 uppercase"
                    >
                        Đã giải quyết
                    </p>
                </div>
                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <p
                        class="text-2xl font-black text-slate-600 dark:text-slate-400"
                    >
                        {{ reportStats.dismissed }}
                    </p>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                    >
                        Đã bác bỏ
                    </p>
                </div>
            </div>

            <!-- Filter Workspace -->
            <Card
                class="overflow-hidden rounded-2xl border-slate-200 dark:border-slate-800"
            >
                <div
                    class="flex flex-col items-start justify-between gap-3 border-b bg-slate-50/50 p-4 sm:flex-row sm:items-center dark:bg-slate-900/20"
                >
                    <div
                        class="flex items-center gap-1.5 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="filter in [
                                {
                                    key: 'all',
                                    label: 'Tất cả',
                                    count: props.reports.length,
                                },
                                {
                                    key: 'open',
                                    label: 'Đang xử lý',
                                    count: reportStats.open,
                                },
                                {
                                    key: 'resolved',
                                    label: 'Đã giải quyết',
                                    count: reportStats.resolved,
                                },
                                {
                                    key: 'dismissed',
                                    label: 'Đã bác bỏ',
                                    count: reportStats.dismissed,
                                },
                            ]"
                            :key="filter.key"
                            type="button"
                            @click="activeFilter = filter.key as any"
                            :class="[
                                'flex items-center gap-1 rounded-lg px-3.5 py-1.5 text-[11px] font-bold whitespace-nowrap transition-colors',
                                activeFilter === filter.key
                                    ? 'border border-slate-200/20 bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            {{ filter.label }}
                            <span
                                class="rounded-full bg-slate-200 px-1 text-[9px] dark:bg-slate-700"
                                >{{ filter.count }}</span
                            >
                        </button>
                    </div>

                    <!-- Search input -->
                    <div class="relative w-full sm:w-64">
                        <Search
                            class="absolute top-2 left-2.5 size-3.5 text-slate-400"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm theo tên, mã NV, loại vi phạm..."
                            class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-7 text-xs focus:border-rose-400 focus:ring-2 focus:ring-rose-500/30 focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>
                </div>

                <!-- Violation list items -->
                <div class="dark:divide-slate-850 divide-y">
                    <div
                        v-for="report in filteredReports"
                        :key="report.id"
                        class="flex flex-col justify-between gap-5 p-5 transition-all duration-300 hover:bg-slate-50/30 md:flex-row dark:hover:bg-slate-900/10"
                    >
                        <!-- Left Info details -->
                        <div class="flex flex-grow items-start gap-4">
                            <div class="flex flex-grow flex-col gap-1.5">
                                <div
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <span
                                        class="rounded-lg border border-rose-100/40 bg-rose-50 px-2 py-0.5 text-xs font-black text-rose-600 dark:bg-rose-950/20 dark:text-rose-400"
                                    >
                                        #{{ report.id }}
                                    </span>
                                    <span
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Đối tượng bị tố cáo:
                                        {{ report.employee_name }}
                                    </span>
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-400 dark:bg-slate-800"
                                    >
                                        Mã: {{ report.employee_code }} ({{
                                            report.job_title
                                        }})
                                    </span>
                                    <span
                                        class="text-[9px] font-medium text-slate-400"
                                    >
                                        Gửi lúc: {{ report.created_at }}
                                    </span>
                                </div>

                                <!-- Whistleblower anonymity protection indicator -->
                                <div
                                    class="flex items-center gap-1.5 text-[10px] font-bold"
                                >
                                    <span class="text-slate-400"
                                        >Người tố giác:</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-md border px-2 py-0.5 text-[9px] font-extrabold shadow-sm',
                                            report.is_anonymous
                                                ? 'border-slate-200/50 bg-slate-100 text-slate-500 dark:bg-slate-800'
                                                : 'border-indigo-150/40 bg-indigo-50 text-indigo-600 dark:bg-indigo-950/20',
                                        ]"
                                    >
                                        {{ report.reported_by_name }}
                                    </span>
                                </div>

                                <div
                                    class="mt-1.5 rounded-xl border bg-slate-50/50 p-3 dark:bg-slate-900/40"
                                >
                                    <div
                                        class="dark:text-slate-350 text-xs font-bold text-slate-700"
                                    >
                                        Loại sai phạm:
                                        <span
                                            class="text-slate-800 dark:text-slate-200"
                                            >{{ report.violation_type }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500 italic dark:text-slate-400"
                                    >
                                        "{{ report.description }}"
                                    </p>
                                    <div
                                        class="mt-2 flex items-center gap-1 text-[9px] font-bold text-slate-400"
                                    >
                                        <Clock class="size-3" />
                                        <span
                                            >Ngày xảy ra:
                                            {{
                                                report.occurred_at_display
                                            }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Disciplinary resolution details if resolved -->
                                <div
                                    v-if="
                                        report.status === 'resolved' &&
                                        report.penalty_amount > 0
                                    "
                                    class="animate-fade-in mt-3.5 flex flex-col gap-1.5 rounded-xl border border-rose-100/60 bg-rose-50/50 p-3.5 dark:border-rose-900/20 dark:bg-rose-950/10"
                                >
                                    <div
                                        class="flex items-center gap-1.5 text-[10px] font-extrabold tracking-wider text-rose-700 uppercase dark:text-rose-400"
                                    >
                                        <ShieldCheck class="size-4 shrink-0" />
                                        <span
                                            >Đã Xử Lý Kỷ Luật & Cấn Trừ Phạt
                                            Lương</span
                                        >
                                    </div>
                                    <div
                                        class="dark:text-slate-350 flex items-center gap-1 text-xs font-bold text-slate-700"
                                    >
                                        <span>Khấu trừ lương:</span>
                                        <span
                                            class="font-black text-rose-600 dark:text-rose-400"
                                        >
                                            {{
                                                formatCurrency(
                                                    report.penalty_amount,
                                                )
                                            }}
                                        </span>
                                        <span
                                            class="text-[10px] font-normal text-slate-400"
                                            >(Đã tự động cấn trừ bảng lương
                                            tháng hiện tại)</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions and badging details -->
                        <div
                            class="dark:border-slate-850 flex w-full shrink-0 flex-col items-start justify-between gap-3 border-t border-slate-100 pt-4 md:w-56 md:items-end md:border-t-0 md:pt-0"
                        >
                            <!-- Badges -->
                            <div
                                class="flex flex-col items-start gap-1.5 select-none md:items-end"
                            >
                                <div class="flex items-center gap-1">
                                    <span
                                        class="text-[9px] font-bold text-slate-400"
                                        >Mức độ:</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-md border px-2 py-0.5 text-[9px] font-extrabold',
                                            severityConfig[report.severity]
                                                ?.text,
                                            severityConfig[report.severity]?.bg,
                                            severityConfig[report.severity]
                                                ?.border,
                                        ]"
                                    >
                                        {{
                                            severityConfig[report.severity]
                                                ?.label
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span
                                        class="text-[9px] font-bold text-slate-400"
                                        >Trạng thái:</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-0.5 text-[9px] font-extrabold',
                                            statusConfig[report.status]?.color,
                                            statusConfig[report.status]?.bg,
                                        ]"
                                    >
                                        {{ statusConfig[report.status]?.label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Disciplinary action trigger buttons -->
                            <div
                                v-if="report.status === 'open'"
                                class="flex w-full justify-end"
                            >
                                <!-- Owner can resolve -->
                                <Button
                                    v-if="isOwner"
                                    size="sm"
                                    @click="openResolveModal(report)"
                                    class="flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border-0 bg-gradient-to-r from-rose-600 to-indigo-600 text-[11px] font-bold text-white shadow-sm select-none hover:from-rose-700 hover:to-indigo-700 md:w-auto"
                                >
                                    <Scale class="size-3.5" />
                                    Phê duyệt kỷ luật
                                </Button>
                                <!-- Managers can see but cannot resolve (Authorization restrictions) -->
                                <div
                                    v-else
                                    class="w-full rounded-lg border bg-slate-50 p-2 text-center text-[9px] font-semibold text-slate-400 dark:bg-slate-900"
                                >
                                    Chỉ Owner có quyền quyết định áp phạt cấn
                                    trừ lương.
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex w-full justify-end select-none"
                            >
                                <span
                                    class="rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                >
                                    Giải quyết hoàn tất
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="filteredReports.length === 0"
                    class="flex flex-col items-center justify-center gap-2 py-20 text-center select-none"
                >
                    <CheckCircle2
                        class="text-slate-350 size-10 dark:text-slate-700"
                    />
                    <h3
                        class="text-sm font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Không có tố cáo nội bộ
                    </h3>
                    <p
                        class="max-w-[250px] text-xs text-slate-400 dark:text-slate-500"
                    >
                        Toàn bộ nhân sự hoạt động chuẩn chỉ, không phát hiện dấu
                        hiệu sai phạm!
                    </p>
                </div>
            </Card>
        </div>

        <!-- TAB 2: SUBMIT DISCIPLINARY REPORT (ANONYMOUS SAFEBOX) -->
        <div
            v-if="activeTab === 'submit'"
            class="animate-fade-in mx-auto w-full max-w-2xl"
        >
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader
                    class="border-b bg-slate-50/50 p-6 dark:bg-slate-900/10"
                >
                    <div
                        class="mb-1 flex items-center gap-2 text-sm font-extrabold tracking-wider text-rose-600 uppercase select-none dark:text-rose-500"
                    >
                        <ShieldAlert class="size-4 shrink-0" />
                        <span
                            >Hòm Thư Tố Cáo Ẩn Danh (Whistleblower
                            Safebox)</span
                        >
                    </div>
                    <CardTitle class="text-lg font-bold"
                        >Báo Cáo Sai Phạm & Gian Lận Nội Bộ</CardTitle
                    >
                    <CardDescription class="mt-1 text-xs leading-relaxed">
                        Hãy cung cấp thông tin chi tiết và chính xác. Chúng tôi
                        cam kết bảo mật tuyệt đối 100% danh tính của bạn để ngăn
                        chặn sự lạm dụng trù dập của cấp trên.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-6">
                    <form
                        @submit.prevent="submitReport"
                        class="flex flex-col gap-4"
                    >
                        <!-- Employee being reported -->
                        <div class="flex flex-col gap-1.5">
                            <Label
                                for="employee"
                                class="text-xs font-bold text-slate-600 dark:text-slate-400"
                            >
                                Chọn Nhân Sự Vi Phạm:
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="employee"
                                v-model="reportForm.employee_id"
                                required
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option value="" disabled>
                                    -- Vui lòng chọn nhân sự nghi ngờ vi phạm --
                                </option>
                                <option
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.full_name }} [Mã:
                                    {{ emp.employee_code }} -
                                    {{ emp.job_title }}]
                                </option>
                            </select>
                        </div>

                        <!-- Type of violation -->
                        <div class="flex flex-col gap-1.5">
                            <Label
                                for="type"
                                class="text-xs font-bold text-slate-600 dark:text-slate-400"
                            >
                                Loại Sai Phạm:
                                <span class="text-rose-500">*</span>
                            </Label>
                            <select
                                id="type"
                                v-model="reportForm.violation_type"
                                required
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                            >
                                <option value="" disabled>
                                    -- Vui lòng chọn loại sai phạm --
                                </option>
                                <option
                                    value="Bòn rút tiền mặt / Gian lận ngân quỹ"
                                >
                                    Bòn rút tiền mặt / Gian lận ngân quỹ
                                </option>
                                <option
                                    value="Bớt xén nguyên vật liệu kho / Ăn cắp tài sản"
                                >
                                    Bớt xén nguyên vật liệu kho / Ăn cắp tài sản
                                </option>
                                <option
                                    value="Thái độ phục vụ bạo lực / Gây gổ"
                                >
                                    Thái độ phục vụ bạo lực / Gây gổ
                                </option>
                                <option
                                    value="Đi muộn về sớm / Trốn ca làm việc"
                                >
                                    Đi muộn về sớm / Trốn ca làm việc
                                </option>
                                <option
                                    value="Cấu kết người ngoài / Tiết lộ thông tin kinh doanh"
                                >
                                    Cấu kết người ngoài / Tiết lộ thông tin kinh
                                    doanh
                                </option>
                                <option value="Hành vi không trung thực khác">
                                    Hành vi không trung thực khác
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <!-- Occurred Date -->
                            <div class="flex flex-col gap-1.5">
                                <Label
                                    for="occurred"
                                    class="text-xs font-bold text-slate-600 dark:text-slate-400"
                                >
                                    Thời Gian Xảy Ra Sự Việc:
                                    <span class="text-rose-500">*</span>
                                </Label>
                                <input
                                    id="occurred"
                                    type="date"
                                    v-model="reportForm.occurred_at"
                                    required
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                                />
                            </div>

                            <!-- Anonymity Toggle -->
                            <div class="flex flex-col gap-1.5">
                                <Label
                                    class="text-xs font-bold text-slate-600 dark:text-slate-400"
                                >
                                    Cấu Hình Ẩn Danh (Bảo Vệ Người Gửi):
                                </Label>
                                <div
                                    class="flex h-9 w-full items-center justify-between gap-2 rounded-lg border bg-slate-50 px-3 select-none dark:bg-slate-900"
                                >
                                    <span
                                        class="text-[11px] font-bold text-slate-500"
                                        >Tự động ẩn danh tính:</span
                                    >
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            @click="
                                                reportForm.is_anonymous = true
                                            "
                                            :class="[
                                                'rounded border px-2 py-0.5 text-[9px] font-bold transition-all',
                                                reportForm.is_anonymous
                                                    ? 'border-rose-500 bg-rose-500 font-black text-white shadow-sm'
                                                    : 'text-slate-400',
                                            ]"
                                        >
                                            Bật (Ẩn Danh)
                                        </button>
                                        <button
                                            type="button"
                                            @click="
                                                reportForm.is_anonymous = false
                                            "
                                            :class="[
                                                'rounded border px-2 py-0.5 text-[9px] font-bold transition-all',
                                                !reportForm.is_anonymous
                                                    ? 'border-indigo-600 bg-indigo-600 font-black text-white shadow-sm'
                                                    : 'text-slate-400',
                                            ]"
                                        >
                                            Tắt (Công Khai)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description details -->
                        <div class="flex flex-col gap-1.5">
                            <Label
                                for="desc"
                                class="text-xs font-bold text-slate-600 dark:text-slate-400"
                            >
                                Mô Tả Chi Tiết Hành Vi Vi Phạm:
                                <span class="text-rose-500">*</span>
                            </Label>
                            <textarea
                                id="desc"
                                v-model="reportForm.description"
                                placeholder="Hãy cung cấp các dữ kiện cụ thể: thời gian xảy ra, nguyên vật liệu hoặc số tiền nghi ngờ bị bòn rút, hành động cụ thể và bằng chứng đi kèm..."
                                rows="4"
                                required
                                class="w-full resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs transition-all placeholder:text-slate-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 focus:outline-none dark:border-slate-800 dark:bg-slate-900 dark:placeholder:text-slate-600"
                            ></textarea>
                        </div>

                        <!-- Submit Safely -->
                        <div class="mt-2 flex justify-end border-t pt-2">
                            <Button
                                type="submit"
                                :disabled="reportForm.processing"
                                class="to-orange-650 flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border-0 bg-gradient-to-r from-rose-600 px-5 text-xs font-bold text-white shadow-lg shadow-rose-100 select-none hover:from-rose-700 hover:to-orange-700 hover:shadow-xl sm:w-auto dark:shadow-none"
                            >
                                <Send class="size-4" />
                                Gửi Báo Cáo Bảo Mật
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- OWNER RESOLUTION MODAL -->
    <div
        v-if="showResolveModal && selectedReport"
        class="animate-fadeIn fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm dark:bg-slate-950/80"
    >
        <div
            class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200/60 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div
                class="mb-4 flex items-center gap-2 border-b pb-3 text-sm font-extrabold tracking-wider text-rose-600 uppercase select-none dark:text-rose-500"
            >
                <Scale class="size-4.5" />
                <span>Quyết định Xử lý kỷ luật & Áp tiền phạt</span>
            </div>

            <!-- Empathy message about report details -->
            <div
                class="mb-4 rounded-2xl border border-rose-100/50 bg-rose-50/50 p-3.5 text-xs select-none dark:bg-rose-950/20"
            >
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Đối tượng: {{ selectedReport.employee_name }} ({{
                        selectedReport.employee_code
                    }})
                </div>
                <div class="mt-1 text-slate-500 dark:text-slate-400">
                    Hành vi tố cáo:
                    <span
                        class="font-bold text-slate-700 dark:text-slate-300"
                        >{{ selectedReport.violation_type }}</span
                    >
                </div>
                <p class="text-slate-450 mt-1 italic dark:text-slate-500">
                    "{{ selectedReport.description }}"
                </p>
            </div>

            <form @submit.prevent="submitResolve" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Severity select -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            for="severity"
                            class="text-xs font-bold text-slate-600 dark:text-slate-400"
                        >
                            Xác định mức độ nghiêm trọng:
                        </Label>
                        <select
                            id="severity"
                            v-model="resolveForm.severity"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            <option value="low">Thấp</option>
                            <option value="medium">Trung bình</option>
                            <option value="high">Cao</option>
                            <option value="critical">Nghiêm trọng</option>
                        </select>
                    </div>

                    <!-- Disciplinary Decision status -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            for="status"
                            class="text-xs font-bold text-slate-600 dark:text-slate-400"
                        >
                            Kết luận giải quyết:
                        </Label>
                        <select
                            id="status"
                            v-model="resolveForm.status"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        >
                            <option value="resolved">
                                Xác nhận sai phạm (Áp phạt)
                            </option>
                            <option value="dismissed">
                                Bác bỏ tố cáo (Không có căn cứ)
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Penalty amount input -->
                <div
                    v-if="resolveForm.status === 'resolved'"
                    class="animate-fadeIn flex flex-col gap-1.5"
                >
                    <Label
                        for="amount"
                        class="text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Số tiền phạt khấu trừ trực tiếp (VND):
                    </Label>
                    <div class="relative">
                        <Input
                            id="amount"
                            type="number"
                            v-model="resolveForm.penalty_amount"
                            min="0"
                            placeholder="Nhập số tiền phạt (Ví dụ: 500000)"
                            class="pr-12"
                        />
                        <span
                            class="pointer-events-none absolute top-2.5 right-3 text-xs font-bold text-slate-400 select-none"
                        >
                            VND
                        </span>
                    </div>

                    <!-- Payroll Integration warning banner -->
                    <div
                        class="flex items-start gap-2 rounded-xl border border-orange-100 bg-orange-50 p-3 text-[10px] leading-normal font-bold text-orange-700 select-none dark:bg-orange-950/20 dark:text-orange-400"
                    >
                        <Info
                            class="mt-0.5 size-3.5 shrink-0 text-orange-500"
                        />
                        <span>
                            CHÚ Ý: Khi xác nhận, số tiền phạt này sẽ tự động móc
                            nối tạo Salary Adjustment dạng violation và cấn trừ
                            trực tiếp thời gian thực vào bảng lương tháng hiện
                            tại của nhân sự vi phạm.
                        </span>
                    </div>
                </div>

                <!-- Disciplinary resolution notes -->
                <div class="flex flex-col gap-1.5">
                    <Label
                        for="notes"
                        class="text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Biện pháp xử lý / Quyết định kỷ luật:
                        <span class="text-rose-500">*</span>
                    </Label>
                    <textarea
                        id="notes"
                        v-model="resolveForm.resolution_notes"
                        placeholder="Nhập nội dung quyết định kỷ luật (Ví dụ: Cảnh cáo trước toàn quán, phạt trừ lương 500k và yêu cầu nhân viên viết bản kiểm điểm hứa cải thiện...)"
                        rows="3"
                        required
                        class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs transition-all focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 focus:outline-none dark:border-slate-800 dark:bg-slate-900"
                    ></textarea>
                </div>

                <!-- Action button resolvers -->
                <div class="mt-2 flex justify-end gap-2.5 border-t pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showResolveModal = false"
                        class="h-9 rounded-xl text-xs"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        :disabled="resolveForm.processing"
                        class="h-9 rounded-xl border-0 bg-gradient-to-r from-rose-600 to-indigo-600 text-xs font-bold text-white shadow-md hover:from-rose-700 hover:to-indigo-700"
                    >
                        Phê duyệt & Khấu trừ
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: scale(0.98);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}
</style>
