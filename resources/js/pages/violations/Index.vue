<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ShieldCheck, ShieldAlert, FileText, Send, CheckCircle2,
    Calendar, Filter, Search, Shield, Info, AlertTriangle,
    Users, AlertCircle, Clock, Trash2, Award, PiggyBank,
    EyeOff, Eye, Scale, HelpCircle, BadgeAlert
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    return authUser?.permissions?.includes('manage_violations') || props.currentUserRole === 'owner';
});

const filteredReports = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.reports.filter(r => {
        const matchFilter =
            activeFilter.value === 'all' ||
            (activeFilter.value === 'open'      && r.status === 'open') ||
            (activeFilter.value === 'resolved'  && r.status === 'resolved') ||
            (activeFilter.value === 'dismissed' && r.status === 'dismissed');
        if (!matchFilter) return false;
        if (!q) return true;
        return (
            r.employee_name.toLowerCase().includes(q) ||
            r.violation_type.toLowerCase().includes(q) ||
            r.description.toLowerCase().includes(q) ||
            r.employee_code.toLowerCase().includes(q)
        );
    });
});

const criticalOpen = computed(() =>
    props.reports.filter(r => r.status === 'open' && (r.severity === 'critical' || r.severity === 'high'))
);

const reportStats = computed(() => ({
    open:      props.reports.filter(r => r.status === 'open').length,
    critical:  props.reports.filter(r => r.severity === 'critical' && r.status === 'open').length,
    resolved:  props.reports.filter(r => r.status === 'resolved').length,
    dismissed: props.reports.filter(r => r.status === 'dismissed').length,
}));

// --- ACTIONS ---
const submitReport = () => {
    reportForm.post('/violations', {
        onSuccess: () => {
            activeTab.value = 'reports';
            reportForm.reset();
        }
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
    if (!selectedReport.value) return;

    resolveForm.post(`/violations/${selectedReport.value.id}/resolve`, {
        onSuccess: () => {
            showResolveModal.value = false;
            selectedReport.value = null;
            resolveForm.reset();
        }
    });
};

function todayDateString() {
    return new Date().toISOString().split('T')[0];
}

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const severityConfig: Record<string, { label: string; text: string; bg: string; border: string }> = {
    low: { label: 'Thấp', text: 'text-slate-600 dark:text-slate-400', bg: 'bg-slate-100 dark:bg-slate-800/60', border: 'border-slate-200/50' },
    medium: { label: 'Trung bình', text: 'text-amber-700 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/20', border: 'border-amber-200/40' },
    high: { label: 'Cao', text: 'text-orange-700 dark:text-orange-400', bg: 'bg-orange-50 dark:bg-orange-950/20', border: 'border-orange-200/40' },
    critical: { label: 'Nghiêm trọng', text: 'text-rose-700 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/20', border: 'border-rose-200/40 animate-pulse' },
};

const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    open: { label: 'Chờ giải quyết', color: 'text-blue-700 dark:text-blue-400', bg: 'bg-blue-100 dark:bg-blue-950/30' },
    reviewed: { label: 'Đang xem xét', color: 'text-amber-700 dark:text-amber-400', bg: 'bg-amber-100 dark:bg-amber-950/30' },
    resolved: { label: 'Đã giải quyết', color: 'text-emerald-700 dark:text-emerald-400', bg: 'bg-emerald-100 dark:bg-emerald-950/30' },
    dismissed: { label: 'Đã bác bỏ', color: 'text-slate-500 dark:text-slate-400', bg: 'bg-slate-100 dark:bg-slate-800' },
};
</script>

<template>
    <Head title="Hòm thư Tố cáo Nội bộ & AI Integrity Control" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 shadow-sm">
                    <Scale class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Giám Sát Nội Bộ & Sai Phạm</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Cơ chế giám sát chéo minh bạch, hòm thư tố cáo ẩn danh bảo vệ nhân sự và quy trình cấn trừ phạt trực tiếp vào bảng lương.
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
                        'text-xs font-bold rounded-xl transition-all',
                        activeTab === 'reports' ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-950/20 text-indigo-600' : ''
                    ]"
                >
                    <FileText class="size-4 mr-1.5" />
                    Danh sách tố cáo
                </Button>
                <Button 
                    variant="outline" 
                    size="sm"
                    @click="activeTab = 'submit'"
                    :class="[
                        'text-xs font-bold rounded-xl transition-all',
                        activeTab === 'submit' ? 'bg-indigo-50 border-indigo-200 dark:bg-indigo-950/20 text-indigo-600' : ''
                    ]"
                >
                    <Send class="size-4 mr-1.5" />
                    Gửi tố cáo ẩn danh
                </Button>
            </div>
        </div>

        <!-- TAB 1: LIST OF REPORTS -->
        <div v-if="activeTab === 'reports'" class="flex flex-col gap-4 animate-fadeIn">

            <!-- Critical alert banner -->
            <div
                v-if="criticalOpen.length > 0"
                class="flex items-start gap-3 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800"
            >
                <BadgeAlert class="size-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" />
                <div class="flex-1">
                    <p class="text-sm font-bold text-rose-800 dark:text-rose-300">
                        {{ criticalOpen.length }} tố cáo mức độ CAO / NGHIÊM TRỌNG chưa xử lý!
                    </p>
                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-0.5">
                        Các tố cáo này cần được phân xử kịp thời để bảo vệ tài sản và uy tín nhà hàng.
                    </p>
                </div>
                <button @click="activeFilter = 'open'" class="text-xs font-bold text-rose-700 dark:text-rose-400 underline shrink-0">
                    Xem ngay
                </button>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-100 dark:border-blue-900/30 rounded-xl p-3">
                    <p class="text-2xl font-black text-blue-700 dark:text-blue-400">{{ reportStats.open }}</p>
                    <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mt-0.5">Đang xử lý</p>
                </div>
                <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/30 rounded-xl p-3">
                    <p class="text-2xl font-black text-rose-700 dark:text-rose-400">{{ reportStats.critical }}</p>
                    <p class="text-[10px] font-bold text-rose-500 uppercase tracking-wider mt-0.5">Nghiêm trọng</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 rounded-xl p-3">
                    <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ reportStats.resolved }}</p>
                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider mt-0.5">Đã giải quyết</p>
                </div>
                <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800 rounded-xl p-3">
                    <p class="text-2xl font-black text-slate-600 dark:text-slate-400">{{ reportStats.dismissed }}</p>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Đã bác bỏ</p>
                </div>
            </div>

            <!-- Filter Workspace -->
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800 overflow-hidden">
                <div class="p-4 border-b bg-slate-50/50 dark:bg-slate-900/20 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-900 rounded-xl p-0.5 border border-slate-200/50 dark:border-slate-800">
                        <button
                            v-for="filter in [
                                { key: 'all', label: 'Tất cả', count: props.reports.length },
                                { key: 'open', label: 'Đang xử lý', count: reportStats.open },
                                { key: 'resolved', label: 'Đã giải quyết', count: reportStats.resolved },
                                { key: 'dismissed', label: 'Đã bác bỏ', count: reportStats.dismissed },
                            ]"
                            :key="filter.key"
                            type="button"
                            @click="activeFilter = filter.key as any"
                            :class="[
                                'px-3.5 py-1.5 text-[11px] font-bold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1',
                                activeFilter === filter.key
                                    ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm border border-slate-200/20'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                            ]"
                        >
                            {{ filter.label }}
                            <span class="text-[9px] bg-slate-200 dark:bg-slate-700 px-1 rounded-full">{{ filter.count }}</span>
                        </button>
                    </div>

                    <!-- Search input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="absolute left-2.5 top-2 size-3.5 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm theo tên, mã NV, loại vi phạm..."
                            class="w-full pl-7 pr-3 py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-rose-500/30 focus:border-rose-400"
                        />
                    </div>
                </div>

                <!-- Violation list items -->
                <div class="divide-y dark:divide-slate-850">
                    <div 
                        v-for="report in filteredReports" 
                        :key="report.id"
                        class="p-5 flex flex-col md:flex-row justify-between gap-5 transition-all duration-300 hover:bg-slate-50/30 dark:hover:bg-slate-900/10"
                    >
                        <!-- Left Info details -->
                        <div class="flex-grow flex gap-4 items-start">
                            <div class="flex-grow flex flex-col gap-1.5">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="text-xs font-black text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 px-2 py-0.5 rounded-lg border border-rose-100/40">
                                        #{{ report.id }}
                                    </span>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                        Đối tượng bị tố cáo: {{ report.employee_name }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-mono bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                        Mã: {{ report.employee_code }} ({{ report.job_title }})
                                    </span>
                                    <span class="text-[9px] text-slate-400 font-medium">
                                        Gửi lúc: {{ report.created_at }}
                                    </span>
                                </div>

                                <!-- Whistleblower anonymity protection indicator -->
                                <div class="flex items-center gap-1.5 text-[10px] font-bold">
                                    <span class="text-slate-400">Người tố giác:</span>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-[9px] font-extrabold shadow-sm border',
                                            report.is_anonymous 
                                                ? 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200/50'
                                                : 'bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 border-indigo-150/40'
                                        ]"
                                    >
                                        {{ report.reported_by_name }}
                                    </span>
                                </div>

                                <div class="bg-slate-50/50 dark:bg-slate-900/40 rounded-xl border p-3 mt-1.5">
                                    <div class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                        Loại sai phạm: <span class="text-slate-800 dark:text-slate-200">{{ report.violation_type }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed italic">
                                        "{{ report.description }}"
                                    </p>
                                    <div class="text-[9px] text-slate-400 font-bold mt-2 flex items-center gap-1">
                                        <Clock class="size-3" />
                                        <span>Ngày xảy ra: {{ report.occurred_at_display }}</span>
                                    </div>
                                </div>

                                <!-- Disciplinary resolution details if resolved -->
                                <div 
                                    v-if="report.status === 'resolved' && report.penalty_amount > 0"
                                    class="mt-3.5 bg-rose-50/50 dark:bg-rose-950/10 rounded-xl border border-rose-100/60 dark:border-rose-900/20 p-3.5 flex flex-col gap-1.5 animate-fadeIn"
                                >
                                    <div class="flex items-center gap-1.5 text-rose-700 dark:text-rose-400 text-[10px] font-extrabold uppercase tracking-wider">
                                        <ShieldCheck class="size-4 shrink-0" />
                                        <span>Đã Xử Lý Kỷ Luật & Cấn Trừ Phạt Lương</span>
                                    </div>
                                    <div class="text-xs text-slate-700 dark:text-slate-350 font-bold flex items-center gap-1">
                                        <span>Khấu trừ lương:</span>
                                        <span class="text-rose-600 dark:text-rose-400 font-black">
                                            {{ formatCurrency(report.penalty_amount) }}
                                        </span>
                                        <span class="text-[10px] text-slate-400 font-normal">(Đã tự động cấn trừ bảng lương tháng hiện tại)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions and badging details -->
                        <div class="w-full md:w-56 shrink-0 flex flex-col gap-3 justify-between items-start md:items-end border-t md:border-t-0 pt-4 md:pt-0 border-slate-100 dark:border-slate-850">
                            <!-- Badges -->
                            <div class="flex flex-col gap-1.5 items-start md:items-end select-none">
                                <div class="flex items-center gap-1">
                                    <span class="text-[9px] text-slate-400 font-bold">Mức độ:</span>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-[9px] font-extrabold border',
                                            severityConfig[report.severity]?.text,
                                            severityConfig[report.severity]?.bg,
                                            severityConfig[report.severity]?.border
                                        ]"
                                    >
                                        {{ severityConfig[report.severity]?.label }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-[9px] text-slate-400 font-bold">Trạng thái:</span>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-[9px] font-extrabold',
                                            statusConfig[report.status]?.color,
                                            statusConfig[report.status]?.bg
                                        ]"
                                    >
                                        {{ statusConfig[report.status]?.label }}
                                    </span>
                                </div>
                            </div>

                            <!-- Disciplinary action trigger buttons -->
                            <div v-if="report.status === 'open'" class="w-full flex justify-end">
                                <!-- Owner can resolve -->
                                <Button 
                                    v-if="isOwner"
                                    size="sm" 
                                    @click="openResolveModal(report)"
                                    class="w-full md:w-auto h-8 text-[11px] font-bold bg-gradient-to-r from-rose-600 to-indigo-600 hover:from-rose-700 hover:to-indigo-700 text-white rounded-lg border-0 shadow-sm flex items-center justify-center gap-1.5 select-none"
                                >
                                    <Scale class="size-3.5" />
                                    Phê duyệt kỷ luật
                                </Button>
                                <!-- Managers can see but cannot resolve (Authorization restrictions) -->
                                <div 
                                    v-else
                                    class="text-[9px] font-semibold text-slate-400 bg-slate-50 dark:bg-slate-900 border p-2 rounded-lg text-center w-full"
                                >
                                    Chỉ Owner có quyền quyết định áp phạt cấn trừ lương.
                                </div>
                            </div>
                            <div v-else class="w-full flex justify-end select-none">
                                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-900 px-3 py-1 rounded-full border border-slate-200 dark:border-slate-800">
                                    Giải quyết hoàn tất
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="filteredReports.length === 0" class="flex flex-col items-center justify-center py-20 text-center gap-2 select-none">
                    <CheckCircle2 class="size-10 text-slate-350 dark:text-slate-700" />
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Không có tố cáo nội bộ</h3>
                    <p class="text-xs text-slate-400 dark:text-slate-500 max-w-[250px]">Toàn bộ nhân sự hoạt động chuẩn chỉ, không phát hiện dấu hiệu sai phạm!</p>
                </div>
            </Card>
        </div>

        <!-- TAB 2: SUBMIT DISCIPLINARY REPORT (ANONYMOUS SAFEBOX) -->
        <div v-if="activeTab === 'submit'" class="max-w-2xl mx-auto w-full animate-fadeIn">
            <Card class="rounded-2xl border-slate-200 dark:border-slate-800">
                <CardHeader class="p-6 border-b bg-slate-50/50 dark:bg-slate-900/10">
                    <div class="flex items-center gap-2 text-rose-600 dark:text-rose-500 font-extrabold text-sm uppercase tracking-wider mb-1 select-none">
                        <ShieldAlert class="size-4 shrink-0" />
                        <span>Hòm Thư Tố Cáo Ẩn Danh (Whistleblower Safebox)</span>
                    </div>
                    <CardTitle class="text-lg font-bold">Báo Cáo Sai Phạm & Gian Lận Nội Bộ</CardTitle>
                    <CardDescription class="text-xs mt-1 leading-relaxed">
                        Hãy cung cấp thông tin chi tiết và chính xác. Chúng tôi cam kết bảo mật tuyệt đối 100% danh tính của bạn để ngăn chặn sự lạm dụng trù dập của cấp trên.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-6">
                    <form @submit.prevent="submitReport" class="flex flex-col gap-4">
                        <!-- Employee being reported -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="employee" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                Chọn Nhân Sự Vi Phạm: <span class="text-rose-500">*</span>
                            </Label>
                            <select 
                                id="employee"
                                v-model="reportForm.employee_id"
                                required
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="" disabled>-- Vui lòng chọn nhân sự nghi ngờ vi phạm --</option>
                                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                    {{ emp.full_name }} [Mã: {{ emp.employee_code }} - {{ emp.job_title }}]
                                </option>
                            </select>
                        </div>

                        <!-- Type of violation -->
                        <div class="flex flex-col gap-1.5">
                            <Label for="type" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                Loại Sai Phạm: <span class="text-rose-500">*</span>
                            </Label>
                            <select 
                                id="type"
                                v-model="reportForm.violation_type"
                                required
                                class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="" disabled>-- Vui lòng chọn loại sai phạm --</option>
                                <option value="Bòn rút tiền mặt / Gian lận ngân quỹ">Bòn rút tiền mặt / Gian lận ngân quỹ</option>
                                <option value="Bớt xén nguyên vật liệu kho / Ăn cắp tài sản">Bớt xén nguyên vật liệu kho / Ăn cắp tài sản</option>
                                <option value="Thái độ phục vụ bạo lực / Gây gổ">Thái độ phục vụ bạo lực / Gây gổ</option>
                                <option value="Đi muộn về sớm / Trốn ca làm việc">Đi muộn về sớm / Trốn ca làm việc</option>
                                <option value="Cấu kết người ngoài / Tiết lộ thông tin kinh doanh">Cấu kết người ngoài / Tiết lộ thông tin kinh doanh</option>
                                <option value="Hành vi không trung thực khác">Hành vi không trung thực khác</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Occurred Date -->
                            <div class="flex flex-col gap-1.5">
                                <Label for="occurred" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                    Thời Gian Xảy Ra Sự Việc: <span class="text-rose-500">*</span>
                                </Label>
                                <input 
                                    id="occurred"
                                    type="date"
                                    v-model="reportForm.occurred_at"
                                    required
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>

                            <!-- Anonymity Toggle -->
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                    Cấu Hình Ẩn Danh (Bảo Vệ Người Gửi):
                                </Label>
                                <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border rounded-lg h-9 px-3 w-full justify-between select-none">
                                    <span class="text-[11px] font-bold text-slate-500">Tự động ẩn danh tính:</span>
                                    <div class="flex items-center gap-1">
                                        <button 
                                            type="button"
                                            @click="reportForm.is_anonymous = true"
                                            :class="[
                                                'px-2 py-0.5 rounded text-[9px] font-bold transition-all border',
                                                reportForm.is_anonymous ? 'bg-rose-500 text-white border-rose-500 shadow-sm font-black' : 'text-slate-400'
                                            ]"
                                        >
                                            Bật (Ẩn Danh)
                                        </button>
                                        <button 
                                            type="button"
                                            @click="reportForm.is_anonymous = false"
                                            :class="[
                                                'px-2 py-0.5 rounded text-[9px] font-bold transition-all border',
                                                !reportForm.is_anonymous ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm font-black' : 'text-slate-400'
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
                            <Label for="desc" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                                Mô Tả Chi Tiết Hành Vi Vi Phạm: <span class="text-rose-500">*</span>
                            </Label>
                            <textarea 
                                id="desc"
                                v-model="reportForm.description"
                                placeholder="Hãy cung cấp các dữ kiện cụ thể: thời gian xảy ra, nguyên vật liệu hoặc số tiền nghi ngờ bị bòn rút, hành động cụ thể và bằng chứng đi kèm..."
                                rows="4"
                                required
                                class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-905 px-3.5 py-2.5 text-xs placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all resize-none"
                            ></textarea>
                        </div>

                        <!-- Submit Safely -->
                        <div class="flex justify-end pt-2 border-t mt-2">
                            <Button 
                                type="submit" 
                                :disabled="reportForm.processing"
                                class="h-10 font-bold text-xs bg-gradient-to-r from-rose-600 to-orange-650 hover:from-rose-700 hover:to-orange-700 text-white rounded-xl shadow-lg shadow-rose-100 dark:shadow-none hover:shadow-xl border-0 flex items-center justify-center gap-1.5 select-none w-full sm:w-auto px-5"
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
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm animate-fadeIn"
    >
        <div class="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/60 dark:border-slate-800 shadow-2xl p-6 relative overflow-hidden">
            <div class="flex items-center gap-2 text-rose-600 dark:text-rose-500 font-extrabold text-sm uppercase tracking-wider mb-4 border-b pb-3 select-none">
                <Scale class="size-4.5" />
                <span>Quyết định Xử lý kỷ luật & Áp tiền phạt</span>
            </div>

            <!-- Empathy message about report details -->
            <div class="rounded-2xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100/50 p-3.5 mb-4 text-xs select-none">
                <div class="font-bold text-slate-800 dark:text-slate-200">
                    Đối tượng: {{ selectedReport.employee_name }} ({{ selectedReport.employee_code }})
                </div>
                <div class="text-slate-500 dark:text-slate-400 mt-1">
                    Hành vi tố cáo: <span class="font-bold text-slate-700 dark:text-slate-300">{{ selectedReport.violation_type }}</span>
                </div>
                <p class="text-slate-450 dark:text-slate-500 mt-1 italic">
                    "{{ selectedReport.description }}"
                </p>
            </div>

            <form @submit.prevent="submitResolve" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Severity select -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="severity" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                            Xác định mức độ nghiêm trọng:
                        </Label>
                        <select 
                            id="severity"
                            v-model="resolveForm.severity"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="low">Thấp</option>
                            <option value="medium">Trung bình</option>
                            <option value="high">Cao</option>
                            <option value="critical">Nghiêm trọng</option>
                        </select>
                    </div>

                    <!-- Disciplinary Decision status -->
                    <div class="flex flex-col gap-1.5">
                        <Label for="status" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                            Kết luận giải quyết:
                        </Label>
                        <select 
                            id="status"
                            v-model="resolveForm.status"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        >
                            <option value="resolved">Xác nhận sai phạm (Áp phạt)</option>
                            <option value="dismissed">Bác bỏ tố cáo (Không có căn cứ)</option>
                        </select>
                    </div>
                </div>

                <!-- Penalty amount input -->
                <div v-if="resolveForm.status === 'resolved'" class="flex flex-col gap-1.5 animate-fadeIn">
                    <Label for="amount" class="text-xs font-bold text-slate-600 dark:text-slate-400">
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
                        <span class="absolute right-3 top-2.5 text-xs font-bold text-slate-400 pointer-events-none select-none">
                            VND
                        </span>
                    </div>

                    <!-- Payroll Integration warning banner -->
                    <div class="rounded-xl bg-orange-50 dark:bg-orange-950/20 border border-orange-100 p-3 flex items-start gap-2 text-[10px] text-orange-700 dark:text-orange-400 font-bold select-none leading-normal">
                        <Info class="size-3.5 shrink-0 mt-0.5 text-orange-500" />
                        <span>
                            CHÚ Ý: Khi xác nhận, số tiền phạt này sẽ tự động móc nối tạo Salary Adjustment dạng violation và cấn trừ trực tiếp thời gian thực vào bảng lương tháng hiện tại của nhân sự vi phạm.
                        </span>
                    </div>
                </div>

                <!-- Disciplinary resolution notes -->
                <div class="flex flex-col gap-1.5">
                    <Label for="notes" class="text-xs font-bold text-slate-600 dark:text-slate-400">
                        Biện pháp xử lý / Quyết định kỷ luật: <span class="text-rose-500">*</span>
                    </Label>
                    <textarea 
                        id="notes"
                        v-model="resolveForm.resolution_notes"
                        placeholder="Nhập nội dung quyết định kỷ luật (Ví dụ: Cảnh cáo trước toàn quán, phạt trừ lương 500k và yêu cầu nhân viên viết bản kiểm điểm hứa cải thiện...)"
                        rows="3"
                        required
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all resize-none"
                    ></textarea>
                </div>

                <!-- Action button resolvers -->
                <div class="flex justify-end gap-2.5 border-t pt-4 mt-2">
                    <Button 
                        type="button" 
                        variant="outline" 
                        @click="showResolveModal = false"
                        class="h-9 text-xs rounded-xl"
                    >
                        Hủy
                    </Button>
                    <Button 
                        type="submit" 
                        :disabled="resolveForm.processing"
                        class="h-9 text-xs font-bold bg-gradient-to-r from-rose-600 to-indigo-600 hover:from-rose-700 hover:to-indigo-700 text-white rounded-xl shadow-md border-0"
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
    from { opacity: 0; transform: scale(0.98); }
    to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease-out forwards;
}
</style>
