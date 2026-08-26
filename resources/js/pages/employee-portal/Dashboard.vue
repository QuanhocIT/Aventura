<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    LayoutGrid,
    Calendar,
    FileText,
    RefreshCw,
    Bell,
    User,
    AlertCircle,
    Coins,
    Clock,
    Plus,
    Check,
    ArrowRightLeft,
    LogOut,
    Eye,
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import { toast } from 'vue-sonner';
import { apiErrorMessage } from '@/composables/useApiCall';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const page = usePage();
const employeeInfo = computed(() => page.props.employee as any);

// Tabs: 'home' | 'schedule' | 'leave' | 'swap' | 'overtime' | 'salary'
const activeTab = ref('home');

// Core data states
const loading = ref(true);
const dashboardError = ref('');
const summary = ref({
    shifts_completed: 0,
    hours_worked: 0.0,
    estimated_earnings: 0.0,
    base_salary: 0.0,
    kpi_bonus: 0.0,
    kpi_commission: 0.0,
});
const kpiData = ref<any>(null);
const schedules = ref<any[]>([]);
const notifications = ref<any[]>([]);
const salaries = ref<any[]>([]);
const leaves = ref<any[]>([]);
const swaps = ref<any>(null);
const overtimeRequests = ref<any[]>([]);
const overtimeLoading = ref(false);
const isSubmittingOvertime = ref(false);
const overtimeForm = ref({
    scheduled_date: new Date().toISOString().slice(0, 10),
    hours_requested: 2,
    start_time: '18:00',
    end_time: '20:00',
    overtime_type: 'normal',
    reason: '',
});

// Forms states
const leaveForm = ref({
    leave_type: 'annual',
    start_date: '',
    end_date: '',
    reason: '',
});
const leaveErrors = ref<any>({});
const leaveSuccessMessage = ref('');

const swapForm = ref({
    requester_assignment_id: '',
    receiver_assignment_id: '',
    notes: '',
});
const swapErrors = ref<any>({});
const swapSuccessMessage = ref('');

// Payslip Modal states
const selectedSalary = ref<any>(null);
const showSalaryModal = ref(false);

// Alert notification state
const unreadNotificationCount = computed(() => {
    return notifications.value.filter((n) => !n.read_at).length;
});

// Load all dashboard data
const fetchData = async () => {
    loading.value = true;
    dashboardError.value = '';

    try {
        const response = await axios.get('/employee-portal/data', {
            timeout: 15000,
        });

        if (response.data.success) {
            summary.value = response.data.summary;
            kpiData.value = response.data.kpis;
            schedules.value = response.data.schedules;
            notifications.value = response.data.notifications;
        } else {
            dashboardError.value =
                response.data.error || 'Không thể tải dữ liệu nhân sự.';
        }
    } catch (error) {
        dashboardError.value = apiErrorMessage(
            error,
            'Dữ liệu đang phản hồi chậm. Vui lòng thử tải lại sau giây lát.',
        );
    } finally {
        loading.value = false;
    }
};

const fetchSalaries = async () => {
    try {
        const response = await axios.get('/employee-portal/salaries');

        if (response.data.success) {
            salaries.value = response.data.salaries;
        }
    } catch (error) {
        toast.error(
            apiErrorMessage(error, 'Không tải được bảng lương của bạn.'),
        );
    }
};

const fetchLeaves = async () => {
    try {
        const response = await axios.get('/employee-portal/leaves');

        if (response.data.success) {
            leaves.value = response.data.leaves;
        }
    } catch (error) {
        toast.error(
            apiErrorMessage(error, 'Không tải được danh sách đơn nghỉ phép.'),
        );
    }
};

const fetchSwaps = async () => {
    try {
        const response = await axios.get('/employee-portal/swaps');

        if (response.data.success) {
            swaps.value = response.data;
        }
    } catch (error) {
        toast.error(
            apiErrorMessage(error, 'Không tải được danh sách yêu cầu đổi ca.'),
        );
    }
};

const fetchOvertime = async () => {
    overtimeLoading.value = true;

    try {
        const response = await axios.get('/employee-portal/overtime');

        if (response.data.success) {
            overtimeRequests.value = response.data.requests;
        }
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Không tải được danh sách tăng ca.'));
    } finally {
        overtimeLoading.value = false;
    }
};

const submitOvertime = async () => {
    if (isSubmittingOvertime.value) {
return;
}

    isSubmittingOvertime.value = true;

    try {
        const response = await axios.post('/employee-portal/overtime', overtimeForm.value);

        if (response.data.success) {
            toast.success(response.data.message);
            overtimeForm.value.reason = '';
            fetchOvertime();
        }
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Không gửi được đơn xin tăng ca.'));
    } finally {
        isSubmittingOvertime.value = false;
    }
};

const respondOvertime = async (overtimeId: number, action: 'accept' | 'decline') => {
    try {
        const response = await axios.post(`/employee-portal/overtime/${overtimeId}/respond`, { action });

        if (response.data.success) {
            toast.success(response.data.message);
            fetchOvertime();
            fetchData();
        }
    } catch (error) {
        toast.error(apiErrorMessage(error, 'Không thể phản hồi yêu cầu tăng ca.'));
    }
};

// Handle Leave Submit
const submitLeave = async () => {
    leaveErrors.value = {};
    leaveSuccessMessage.value = '';

    try {
        const response = await axios.post(
            '/employee-portal/leaves',
            leaveForm.value,
        );

        if (response.data.success) {
            leaveSuccessMessage.value = response.data.message;
            leaveForm.value = {
                leave_type: 'annual',
                start_date: '',
                end_date: '',
                reason: '',
            };
            fetchLeaves();
        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            leaveErrors.value = {
                general:
                    error.response.data.error || error.response.data.message,
            };
        } else {
            leaveErrors.value = {
                general: 'Có lỗi xảy ra khi nộp đơn xin nghỉ phép.',
            };
        }
    }
};

const isSendingSwapRequest = ref(false);

// Handle Swap Submit
const submitSwapRequest = async () => {
    if (isSendingSwapRequest.value) {
        return;
    }

    isSendingSwapRequest.value = true;
    swapErrors.value = {};
    swapSuccessMessage.value = '';

    try {
        const response = await axios.post(
            '/employee-portal/swaps/request',
            swapForm.value,
        );

        if (response.data.success) {
            swapSuccessMessage.value = response.data.message;
            swapForm.value = {
                requester_assignment_id: '',
                receiver_assignment_id: '',
                notes: '',
            };
            fetchSwaps();
        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            swapErrors.value = {
                general:
                    error.response.data.error || error.response.data.message,
            };
        } else {
            swapErrors.value = { general: 'Có lỗi xảy ra khi yêu cầu đổi ca.' };
        }
    } finally {
        isSendingSwapRequest.value = false;
    }
};

const isRespondingSwap = ref<Record<number, boolean>>({});

// Handle Swap Response (Accept/Cancel/Reject)
const handleSwapResponse = async (
    swapId: number,
    action: 'accept' | 'cancel' | 'reject',
) => {
    if (isRespondingSwap.value[swapId]) {
        return;
    }

    isRespondingSwap.value[swapId] = true;

    try {
        const response = await axios.post(
            `/employee-portal/swaps/${swapId}/respond`,
            { action },
        );

        if (response.data.success) {
            fetchSwaps();
            fetchData();
        }
    } catch (error) {
        toast.error(
            apiErrorMessage(
                error,
                'Không gửi được phản hồi đổi ca. Vui lòng thử lại.',
            ),
        );
    } finally {
        isRespondingSwap.value[swapId] = false;
    }
};

// Mark all read
const markAllRead = async () => {
    try {
        await axios.post('/employee-portal/notifications/read-all');
        fetchData();
    } catch (error) {
        toast.error(
            apiErrorMessage(error, 'Không đánh dấu đã đọc được thông báo.'),
        );
    }
};

// Lifecycle
onMounted(() => {
    fetchData();
});

// Watch tab shifts to load specific items
const changeTab = (tab: string) => {
    activeTab.value = tab;

    if (tab === 'salary') {
        fetchSalaries();
    }

    if (tab === 'leave') {
        fetchLeaves();
    }

    if (tab === 'swap') {
        fetchSwaps();
    }

    if (tab === 'overtime') {
        fetchOvertime();
    }
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

const formatDate = (dateStr: string) => {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return dateStr;
    }

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
        case 'approved':
        case 'paid':
        case 'accepted':
            return 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
        case 'pending':
        case 'scheduled':
            return 'bg-amber-500/10 text-amber-400 border border-amber-500/20';
        case 'rejected':
        case 'cancelled':
            return 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
        default:
            return 'bg-gray-500/10 text-gray-400 border border-gray-500/20';
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'completed':
            return 'Đã hoàn thành';
        case 'approved':
            return 'Đã duyệt';
        case 'paid':
            return 'Đã thanh toán';
        case 'accepted':
            return 'Đã chấp nhận';
        case 'pending':
            return 'Chờ duyệt';
        case 'scheduled':
            return 'Được xếp lịch';
        case 'rejected':
            return 'Bị từ chối';
        case 'cancelled':
            return 'Bị hủy';
        default:
            return status;
    }
};

const getLeaveTypeText = (type: string) => {
    switch (type) {
        case 'annual':
            return 'Nghỉ phép năm';
        case 'sick':
            return 'Nghỉ ốm';
        case 'unpaid':
            return 'Nghỉ không lương';
        case 'emergency':
            return 'Nghỉ khẩn cấp';
        default:
            return type;
    }
};
</script>

<template>
    <div
        class="employee-portal-page min-h-full bg-background pb-10 font-sans text-foreground"
    >
        <Head title="Cổng nhân sự" />

        <section
            class="mx-auto w-full max-w-7xl space-y-6 px-4 py-5 lg:px-6 lg:py-6"
        >
            <div
                class="flex flex-col justify-between gap-5 border-b border-border pb-6 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <User class="size-6" />
                    </div>
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.16em] text-primary uppercase"
                        >
                            Cổng nhân sự
                        </p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight">
                            Chào {{ employeeInfo?.full_name ?? 'Nhân viên' }} 👋
                        </h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ employeeInfo?.job_title ?? 'Nhân viên' }} ·
                            {{
                                employeeInfo?.employee_code ||
                                'Chưa cập nhật mã'
                            }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    @click="changeTab('overtime')"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                >
                    <Clock class="size-4" />
                    Tăng ca
                </button>

                <a
                    href="/dashboard"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-border bg-background px-4 py-2.5 text-xs font-semibold text-muted-foreground transition hover:border-primary/40 hover:text-foreground"
                >
                    <LogOut class="size-4" />
                    Quay lại Quản lý
                </a>
            </div>

            <nav
                class="flex gap-1 overflow-x-auto rounded-xl border border-border bg-card p-1.5"
            >
                <button
                    v-for="t in [
                        { key: 'home', label: 'Tổng quan', icon: LayoutGrid },
                        { key: 'schedule', label: 'Lịch ca', icon: Calendar },
                        { key: 'leave', label: 'Nghỉ phép', icon: FileText },
                        { key: 'swap', label: 'Đổi ca', icon: RefreshCw },
                        { key: 'overtime', label: 'Tăng ca', icon: Clock },
                        { key: 'salary', label: 'Lương', icon: Coins },
                    ]"
                    :key="t.key"
                    type="button"
                    @click="changeTab(t.key)"
                    class="flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-semibold transition"
                    :class="
                        activeTab === t.key
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    <component :is="t.icon" class="size-4" />
                    {{ t.label }}
                    <span
                        v-if="t.key === 'home' && unreadNotificationCount > 0"
                        class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] text-white"
                    >
                        {{ unreadNotificationCount }}
                    </span>
                </button>
            </nav>

            <!-- Mobile/Desktop Premium Header -->
            <header
                v-if="false"
                class="sticky top-0 z-40 flex items-center justify-between border-b border-slate-800 bg-slate-900/80 px-4 py-3 backdrop-blur-md"
            >
                <div class="flex items-center space-x-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 font-bold text-white shadow-lg shadow-indigo-500/30"
                    >
                        {{
                            employeeInfo?.full_name
                                ? employeeInfo.full_name.charAt(0)
                                : 'E'
                        }}
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-200">
                            Chào {{ employeeInfo?.full_name ?? 'Nhân viên' }} 👋
                        </h2>
                        <p class="text-xs font-medium text-indigo-400">
                            {{ employeeInfo?.job_title ?? 'Nhân viên' }} ({{
                                employeeInfo?.employee_code
                            }})
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Desktop Navigation Tab bar -->
                    <div
                        class="mr-4 hidden space-x-1 rounded-lg border border-slate-800 bg-slate-950/80 p-1 md:flex"
                    >
                        <button
                            v-for="t in [
                                {
                                    key: 'home',
                                    label: 'Tổng quan',
                                    icon: LayoutGrid,
                                },
                                {
                                    key: 'schedule',
                                    label: 'Lịch ca',
                                    icon: Calendar,
                                },
                                {
                                    key: 'leave',
                                    label: 'Nghỉ phép',
                                    icon: FileText,
                                },
                                {
                                    key: 'swap',
                                    label: 'Đổi ca',
                                    icon: RefreshCw,
                                },
                                { key: 'salary', label: 'Lương', icon: Coins },
                            ]"
                            :key="t.key"
                            @click="changeTab(t.key)"
                            class="flex items-center space-x-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition"
                            :class="
                                activeTab === t.key
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-slate-400 hover:text-slate-200'
                            "
                        >
                            <component :is="t.icon" class="h-3.5 w-3.5" />
                            <span>{{ t.label }}</span>
                        </button>
                    </div>

                    <a
                        href="/dashboard"
                        class="flex items-center space-x-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:bg-slate-700"
                    >
                        <LogOut class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Quay lại Quản lý</span>
                    </a>
                </div>
            </header>

            <!-- Main Body Wrapper -->
            <main
                class="mx-auto w-full max-w-7xl flex-1 space-y-6 px-4 pb-6 lg:px-6"
            >
                <div v-if="loading" class="space-y-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div
                            v-for="n in 3"
                            :key="n"
                            class="h-32 animate-pulse rounded-xl border border-border bg-card"
                        ></div>
                    </div>
                    <div
                        class="h-64 animate-pulse rounded-xl border border-border bg-card"
                    ></div>
                    <div
                        class="flex items-center justify-center gap-2 py-3 text-sm text-muted-foreground"
                    >
                        <RefreshCw
                            class="size-4 animate-spin text-indigo-500"
                        />
                        Đang tải dữ liệu nhân sự...
                    </div>
                </div>

                <div
                    v-else-if="dashboardError"
                    class="flex flex-col items-center justify-center rounded-xl border border-amber-500/30 bg-amber-500/10 px-6 py-14 text-center"
                >
                    <AlertCircle class="mb-3 size-8 text-amber-500" />
                    <h2 class="text-base font-bold text-foreground">
                        Chưa thể tải dữ liệu
                    </h2>
                    <p class="mt-1 max-w-md text-sm text-muted-foreground">
                        {{ dashboardError }}
                    </p>
                    <button
                        type="button"
                        @click="fetchData"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                    >
                        <RefreshCw class="size-4" />
                        Thử tải lại
                    </button>
                </div>

                <div v-else class="space-y-6">
                    <!-- TAB 1: HOME (DASHBOARD) -->
                    <div
                        v-if="activeTab === 'home'"
                        class="animate-fadeIn space-y-6"
                    >
                        <!-- Earnings & Hours Grid -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div
                                class="relative overflow-hidden rounded-xl border border-primary/30 bg-primary/5 p-5"
                            >
                                <div
                                    class="absolute -right-4 -bottom-4 opacity-10"
                                >
                                    <Coins class="h-28 w-28" />
                                </div>
                                <h3
                                    class="text-xs font-semibold tracking-wider text-primary uppercase"
                                >
                                    Ước Tính Thu Nhập Tháng
                                </h3>
                                <p
                                    class="mt-2 text-2xl font-bold text-foreground"
                                >
                                    {{
                                        formatCurrency(
                                            summary.estimated_earnings,
                                        )
                                    }}
                                </p>
                                <div
                                    class="mt-3 flex items-center space-x-2 text-[10px] text-muted-foreground"
                                >
                                    <span class="rounded bg-muted px-1.5 py-0.5"
                                        >Cứng:
                                        {{
                                            formatCurrency(summary.base_salary)
                                        }}</span
                                    >
                                    <span
                                        v-if="summary.kpi_bonus > 0"
                                        class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-emerald-500"
                                        >Thưởng KPI: +{{
                                            formatCurrency(summary.kpi_bonus)
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="rounded-xl border border-border bg-card p-5"
                            >
                                <h3
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Tổng Giờ Làm (Tháng Này)
                                </h3>
                                <p
                                    class="mt-2 text-2xl font-bold text-foreground"
                                >
                                    {{ summary.hours_worked }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >giờ</span
                                    >
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Tổng số giờ chấm công hoàn thành.
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-border bg-card p-5"
                            >
                                <h3
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Số Ca Đã Hoàn Thành
                                </h3>
                                <p
                                    class="mt-2 text-2xl font-bold text-foreground"
                                >
                                    {{ summary.shifts_completed }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >ca</span
                                    >
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Các ca làm việc đã xác nhận hoàn thành.
                                </p>
                            </div>
                        </div>

                        <!-- Upcoming shifts quick view -->
                        <div
                            class="space-y-4 rounded-xl border border-border bg-card p-5"
                        >
                            <h3
                                class="flex items-center space-x-2 text-sm font-semibold text-foreground"
                            >
                                <Calendar class="h-4 w-4 text-indigo-400" />
                                <span>Lịch làm việc sắp tới (Tuần này)</span>
                            </h3>

                            <div
                                v-if="schedules.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                Không có ca trực nào được xếp lịch.
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="sched in schedules.slice(0, 3)"
                                    :key="sched.id"
                                    class="flex items-center justify-between rounded-lg border border-border bg-muted/30 p-3.5 transition hover:border-primary/40"
                                >
                                    <div class="flex items-center space-x-3">
                                        <div
                                            class="flex h-9 w-9 flex-col items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-[10px] font-bold text-primary"
                                        >
                                            <span>{{ sched.day_name }}</span>
                                        </div>
                                        <div>
                                            <p
                                                class="text-xs font-bold text-foreground"
                                            >
                                                {{ sched.shift_name }}
                                            </p>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                {{ sched.start_time }} -
                                                {{ sched.end_time }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                            :class="
                                                getStatusBadge(sched.status)
                                            "
                                        >
                                            {{ getStatusText(sched.status) }}
                                        </span>
                                        <span
                                            v-if="sched.check_in_at"
                                            class="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                                        >
                                            Vào: {{ sched.check_in_at }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal KPI Progress -->
                        <div
                            v-if="kpiData"
                            class="space-y-4 rounded-xl border border-border bg-card p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center space-x-2 text-sm font-semibold text-foreground"
                                >
                                    <Coins class="h-4 w-4 text-emerald-400" />
                                    <span>Hiệu Suất KPI & Ước Tính Thưởng</span>
                                </h3>
                                <span
                                    class="rounded bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary"
                                >
                                    Điểm KPI: {{ kpiData.total_score }} / 100
                                </span>
                            </div>

                            <div
                                class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2"
                            >
                                <div
                                    v-for="metric in kpiData.metrics"
                                    :key="metric.metric_name"
                                    class="flex flex-col justify-between space-y-3 rounded-lg border border-border bg-muted/30 p-3.5"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <h4
                                            class="text-xs font-bold text-foreground"
                                        >
                                            {{ metric.metric_name }}
                                        </h4>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                            :class="
                                                metric.is_achieved
                                                    ? 'bg-emerald-500/10 text-emerald-400'
                                                    : 'bg-rose-500/10 text-rose-400'
                                            "
                                        >
                                            {{
                                                metric.is_achieved
                                                    ? 'Đạt'
                                                    : 'Chưa Đạt'
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="flex items-end justify-between text-xs"
                                    >
                                        <div class="space-y-1">
                                            <p
                                                class="text-[10px] text-muted-foreground"
                                            >
                                                Tiến độ thực tế / Chỉ tiêu
                                            </p>
                                            <p
                                                class="text-sm font-bold text-foreground"
                                            >
                                                {{ metric.actual_value }}
                                                <span
                                                    class="font-medium text-muted-foreground"
                                                    >/
                                                    {{
                                                        metric.target_value
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                        <div
                                            class="space-y-0.5 text-right text-[11px]"
                                        >
                                            <p
                                                v-if="metric.bonus_earned > 0"
                                                class="font-bold text-emerald-500"
                                            >
                                                +{{
                                                    formatCurrency(
                                                        metric.bonus_earned,
                                                    )
                                                }}
                                                Thưởng
                                            </p>
                                            <p
                                                v-if="
                                                    metric.commission_earned > 0
                                                "
                                                class="font-bold text-primary"
                                            >
                                                +{{
                                                    formatCurrency(
                                                        metric.commission_earned,
                                                    )
                                                }}
                                                Hoa hồng
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification Center -->
                        <div
                            class="space-y-4 rounded-xl border border-border bg-card p-5"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="flex items-center space-x-2 text-sm font-semibold text-foreground"
                                >
                                    <Bell class="h-4 w-4 text-indigo-400" />
                                    <span>Thông báo mới</span>
                                </h3>
                                <button
                                    v-if="unreadNotificationCount > 0"
                                    @click="markAllRead"
                                    class="text-xs font-medium text-primary hover:text-primary/80"
                                >
                                    Đọc tất cả
                                </button>
                            </div>

                            <div
                                v-if="notifications.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                Không có thông báo mới nào.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="notif in notifications"
                                    :key="notif.id"
                                    class="flex items-start space-x-3 rounded-xl border p-3 text-xs transition"
                                    :class="
                                        notif.read_at
                                            ? 'border-border bg-muted/20 text-muted-foreground'
                                            : 'border-primary/20 bg-primary/5 text-foreground'
                                    "
                                >
                                    <span
                                        class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full"
                                        :class="
                                            notif.read_at
                                                ? 'bg-muted-foreground'
                                                : 'bg-indigo-500'
                                        "
                                    ></span>
                                    <div class="flex-1 space-y-1">
                                        <p>{{ notif.message }}</p>
                                        <span
                                            class="block text-[10px] font-medium text-muted-foreground"
                                            >{{ notif.created_at }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: SCHEDULE (LỊCH TRỰC CHI TIẾT) -->
                    <div
                        v-if="activeTab === 'schedule'"
                        class="animate-fadeIn space-y-4"
                    >
                        <h3 class="text-base font-semibold text-slate-200">
                            Lịch làm việc chi tiết
                        </h3>
                        <p class="text-xs text-slate-400">
                            Lịch làm việc hiển thị ca trực 3 tuần gần nhất (tuần
                            trước, tuần này, tuần sau).
                        </p>

                        <div
                            v-if="schedules.length === 0"
                            class="rounded-2xl border border-slate-800 bg-slate-900 py-12 text-center text-xs text-slate-500"
                        >
                            Không tìm thấy lịch trực trong hệ thống.
                        </div>
                        <div v-else class="grid grid-cols-1 gap-3">
                            <div
                                v-for="sched in schedules"
                                :key="sched.id"
                                class="flex flex-col space-y-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 transition hover:border-slate-700 sm:flex-row sm:items-center sm:justify-between sm:space-y-0"
                            >
                                <div class="flex items-start space-x-4">
                                    <div
                                        class="flex h-12 w-12 flex-shrink-0 flex-col items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-600/10 text-xs font-bold text-indigo-400"
                                    >
                                        <span>{{ sched.day_name }}</span>
                                        <span
                                            class="text-[9px] font-medium text-slate-400"
                                            >{{
                                                sched.formatted_date.slice(0, 5)
                                            }}</span
                                        >
                                    </div>
                                    <div class="space-y-1">
                                        <h4
                                            class="text-sm font-bold text-slate-200"
                                        >
                                            {{ sched.shift_name }}
                                        </h4>
                                        <p
                                            class="flex items-center space-x-2 text-xs text-slate-400"
                                        >
                                            <Clock
                                                class="h-3 w-3 text-slate-500"
                                            />
                                            <span
                                                >{{ sched.start_time }} -
                                                {{ sched.end_time }}</span
                                            >
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between sm:justify-end sm:space-x-4"
                                >
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadge(sched.status)"
                                    >
                                        {{ getStatusText(sched.status) }}
                                    </span>

                                    <div class="text-right text-xs">
                                        <p
                                            v-if="sched.check_in_at"
                                            class="font-medium text-emerald-400"
                                        >
                                            Check-In: {{ sched.check_in_at }}
                                        </p>
                                        <p
                                            v-if="sched.check_out_at"
                                            class="font-medium text-amber-400"
                                        >
                                            Check-Out: {{ sched.check_out_at }}
                                        </p>
                                        <p
                                            v-if="
                                                !sched.check_in_at &&
                                                sched.status === 'scheduled'
                                            "
                                            class="text-slate-500"
                                        >
                                            Chưa bắt đầu ca
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: LEAVE (NGHỈ PHÉP) -->
                    <div
                        v-if="activeTab === 'leave'"
                        class="animate-fadeIn space-y-6"
                    >
                        <!-- Leave Form -->
                        <div
                            class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
                        >
                            <h3
                                class="flex items-center space-x-2 text-sm font-semibold text-slate-200"
                            >
                                <Plus class="h-4 w-4 text-indigo-400" />
                                <span>Đăng ký nghỉ phép mới</span>
                            </h3>

                            <form
                                @submit.prevent="submitLeave"
                                class="space-y-4"
                            >
                                <div
                                    v-if="leaveErrors.general"
                                    class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs text-rose-400"
                                >
                                    {{ leaveErrors.general }}
                                </div>
                                <div
                                    v-if="leaveSuccessMessage"
                                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-400"
                                >
                                    {{ leaveSuccessMessage }}
                                </div>

                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                                >
                                    <div class="space-y-1">
                                        <label
                                            class="text-xs font-medium text-slate-400"
                                            >Loại nghỉ phép</label
                                        >
                                        <select
                                            v-model="leaveForm.leave_type"
                                            class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="annual">
                                                Nghỉ phép năm (có lương)
                                            </option>
                                            <option value="sick">
                                                Nghỉ ốm
                                            </option>
                                            <option value="unpaid">
                                                Nghỉ không lương
                                            </option>
                                            <option value="emergency">
                                                Nghỉ khẩn cấp
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1">
                                        <label
                                            class="text-xs font-medium text-slate-400"
                                            >Từ ngày</label
                                        >
                                        <input
                                            v-model="leaveForm.start_date"
                                            type="date"
                                            required
                                            class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>

                                    <div class="space-y-1">
                                        <label
                                            class="text-xs font-medium text-slate-400"
                                            >Đến ngày</label
                                        >
                                        <input
                                            v-model="leaveForm.end_date"
                                            type="date"
                                            required
                                            class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 focus:border-indigo-500 focus:outline-none"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="text-xs font-medium text-slate-400"
                                        >Lý do nghỉ phép</label
                                    >
                                    <textarea
                                        v-model="leaveForm.reason"
                                        rows="2"
                                        placeholder="Điền lý do xin nghỉ chi tiết..."
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    class="w-full rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-500 sm:w-auto"
                                >
                                    Gửi Đơn Xin Nghỉ
                                </button>
                            </form>
                        </div>

                        <!-- Leave History -->
                        <div
                            class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
                        >
                            <h3 class="text-sm font-semibold text-slate-200">
                                Lịch sử xin nghỉ phép
                            </h3>

                            <div
                                v-if="leaves.length === 0"
                                class="py-8 text-center text-xs text-slate-500"
                            >
                                Bạn chưa gửi đơn xin nghỉ phép nào.
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="leave in leaves"
                                    :key="leave.id"
                                    class="flex items-center justify-between rounded-xl border border-slate-800/80 bg-slate-950/40 p-3.5"
                                >
                                    <div class="space-y-1.5">
                                        <h4
                                            class="text-xs font-bold text-slate-200"
                                        >
                                            {{
                                                getLeaveTypeText(
                                                    leave.leave_type,
                                                )
                                            }}
                                        </h4>
                                        <p class="text-[11px] text-slate-400">
                                            {{ formatDate(leave.start_date) }} -
                                            {{ formatDate(leave.end_date) }}
                                        </p>
                                        <p
                                            v-if="leave.reason"
                                            class="text-[10px] text-slate-500 italic"
                                        >
                                            Lý do: {{ leave.reason }}
                                        </p>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-medium"
                                            :class="
                                                getStatusBadge(leave.status)
                                            "
                                        >
                                            {{ getStatusText(leave.status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: SWAP (ĐỔI CA) -->
                    <div
                        v-if="activeTab === 'swap'"
                        class="animate-fadeIn space-y-6"
                    >
                        <!-- Swap Request Form -->
                        <div
                            class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
                        >
                            <h3
                                class="flex items-center space-x-2 text-sm font-semibold text-slate-200"
                            >
                                <ArrowRightLeft
                                    class="h-4 w-4 text-indigo-400"
                                />
                                <span>Đề xuất đổi ca trực</span>
                            </h3>

                            <form
                                @submit.prevent="submitSwapRequest"
                                class="space-y-4"
                            >
                                <div
                                    v-if="swapErrors.general"
                                    class="rounded-xl border border-rose-500/20 bg-rose-500/10 p-3 text-xs text-rose-400"
                                >
                                    {{ swapErrors.general }}
                                </div>
                                <div
                                    v-if="swapSuccessMessage"
                                    class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs text-emerald-400"
                                >
                                    {{ swapSuccessMessage }}
                                </div>

                                <div
                                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                                >
                                    <div class="space-y-1">
                                        <label
                                            class="text-xs font-medium text-slate-400"
                                            >Ca trực của bạn cần đổi</label
                                        >
                                        <select
                                            v-model="
                                                swapForm.requester_assignment_id
                                            "
                                            required
                                            class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="" disabled>
                                                -- Chọn ca trực của bạn --
                                            </option>
                                            <option
                                                v-for="a in swaps?.my_assignments"
                                                :key="a.id"
                                                :value="a.id"
                                            >
                                                Ngày {{ a.date_formatted }} -
                                                {{ a.shift_name }} ({{
                                                    a.shift_time
                                                }})
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-1">
                                        <label
                                            class="text-xs font-medium text-slate-400"
                                            >Ca trực muốn đổi của đồng
                                            nghiệp</label
                                        >
                                        <select
                                            v-model="
                                                swapForm.receiver_assignment_id
                                            "
                                            required
                                            class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 focus:border-indigo-500 focus:outline-none"
                                        >
                                            <option value="" disabled>
                                                -- Chọn ca trực đồng nghiệp --
                                            </option>
                                            <option
                                                v-for="a in swaps?.colleague_assignments"
                                                :key="a.id"
                                                :value="a.id"
                                            >
                                                {{ a.employee_name }} -
                                                {{ a.day_name }}
                                                {{ a.date_formatted }} -
                                                {{ a.shift_name }} ({{
                                                    a.shift_time
                                                }})
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="text-xs font-medium text-slate-400"
                                        >Ghi chú gửi đồng nghiệp</label
                                    >
                                    <input
                                        v-model="swapForm.notes"
                                        type="text"
                                        placeholder="Điền ghi chú (ví dụ: bận việc gia đình, đổi ca trực sáng/chiều...)"
                                        class="w-full rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-600 focus:border-indigo-500 focus:outline-none"
                                    />
                                </div>

                                <button
                                    type="submit"
                                    class="w-full rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-500 sm:w-auto"
                                >
                                    Gửi Yêu Cầu Đổi Ca
                                </button>
                            </form>
                        </div>

                        <!-- Active Swaps List -->
                        <div
                            class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
                        >
                            <h3 class="text-sm font-semibold text-slate-200">
                                Danh sách các yêu cầu đổi ca trực
                            </h3>

                            <div
                                v-if="!swaps?.swaps || swaps.swaps.length === 0"
                                class="py-8 text-center text-xs text-slate-500"
                            >
                                Không có yêu cầu đổi ca trực nào.
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="swap in swaps.swaps"
                                    :key="swap.id"
                                    class="flex flex-col justify-between space-y-3 rounded-xl border border-slate-800/80 bg-slate-950/40 p-4"
                                >
                                    <div
                                        class="flex items-start justify-between"
                                    >
                                        <div class="space-y-1">
                                            <p class="text-xs text-slate-400">
                                                <span
                                                    class="font-bold text-slate-200"
                                                    >{{
                                                        swap.requester_name
                                                    }}</span
                                                >
                                                muốn đổi
                                            </p>
                                            <p
                                                class="text-xs font-bold text-indigo-400"
                                            >
                                                Ca
                                                {{ swap.requester_shift }} (Ngày
                                                {{ swap.requester_date }})
                                            </p>

                                            <div
                                                class="flex items-center space-x-2 py-1 text-[10px] text-slate-500"
                                            >
                                                <ArrowRightLeft
                                                    class="h-3 w-3 text-slate-500"
                                                />
                                                <span
                                                    >Đổi với ca trực của
                                                    {{
                                                        swap.receiver_name
                                                    }}:</span
                                                >
                                            </div>

                                            <p
                                                class="text-xs font-bold text-amber-400"
                                            >
                                                Ca
                                                {{ swap.receiver_shift }} (Ngày
                                                {{ swap.receiver_date }})
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-medium"
                                            :class="getStatusBadge(swap.status)"
                                        >
                                            {{ getStatusText(swap.status) }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="swap.notes"
                                        class="rounded bg-slate-950 px-2 py-1 text-[10px] text-slate-500 italic"
                                    >
                                        Ghi chú: {{ swap.notes }}
                                    </p>

                                    <!-- Respond controls -->
                                    <div
                                        v-if="swap.status === 'pending'"
                                        class="flex items-center space-x-2 pt-1"
                                    >
                                        <!-- I am the receiver and can accept or reject -->
                                        <template v-if="!swap.is_requester">
                                            <button
                                                @click="
                                                    handleSwapResponse(
                                                        swap.id,
                                                        'accept',
                                                    )
                                                "
                                                class="flex items-center space-x-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-[10px] font-bold text-white transition hover:bg-emerald-500"
                                            >
                                                <Check class="h-3.5 w-3.5" />
                                                <span>Đồng ý đổi</span>
                                            </button>
                                            <button
                                                @click="
                                                    handleSwapResponse(
                                                        swap.id,
                                                        'reject',
                                                    )
                                                "
                                                class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[10px] font-bold text-rose-400 transition hover:bg-slate-700"
                                            >
                                                Từ chối
                                            </button>
                                        </template>
                                        <!-- I am the requester and can cancel -->
                                        <template v-else>
                                            <button
                                                @click="
                                                    handleSwapResponse(
                                                        swap.id,
                                                        'cancel',
                                                    )
                                                "
                                                class="rounded-lg border border-slate-700 bg-slate-800 px-3 py-1.5 text-[10px] font-bold text-slate-400 transition hover:bg-slate-700"
                                            >
                                                Hủy yêu cầu
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 5: OVERTIME -->
                    <div
                        v-if="activeTab === 'overtime'"
                        class="animate-fadeIn space-y-4"
                    >
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                            <div>
                                <h3 class="text-base font-semibold text-foreground">Đăng ký tăng ca</h3>
                                <p class="mt-1 text-xs text-muted-foreground">Hệ thống tính theo đơn giá và hệ số OT; chỉ giờ được duyệt và chấm công mới vào lương.</p>
                            </div>
                            <span class="rounded-full bg-indigo-500/10 px-3 py-1 text-[11px] font-semibold text-indigo-600">Tăng ca được duyệt sẽ cộng vào kỳ lương</span>
                        </div>

                        <form class="grid gap-3 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-4 md:grid-cols-4" @submit.prevent="submitOvertime">
                            <label class="space-y-1 text-xs font-semibold">Ngày tăng ca<input v-model="overtimeForm.scheduled_date" type="date" required class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm font-normal" /></label>
                            <label class="space-y-1 text-xs font-semibold">Số giờ<input v-model.number="overtimeForm.hours_requested" type="number" min="0.25" max="12" step="0.25" required class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm font-normal" /></label>
                            <label class="space-y-1 text-xs font-semibold">Bắt đầu<input v-model="overtimeForm.start_time" type="time" required class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm font-normal" /></label>
                            <label class="space-y-1 text-xs font-semibold">Kết thúc<input v-model="overtimeForm.end_time" type="time" required class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm font-normal" /></label>
                            <label class="space-y-1 text-xs font-semibold md:col-span-2">Loại tăng ca<select v-model="overtimeForm.overtime_type" class="h-10 w-full rounded-xl border border-border bg-background px-3 text-sm font-normal"><option value="normal">Ngày thường</option><option value="night">Ban đêm</option><option value="rest_day">Ngày nghỉ</option><option value="holiday">Ngày lễ</option></select></label>
                            <label class="space-y-1 text-xs font-semibold md:col-span-2">Lý do<textarea v-model="overtimeForm.reason" rows="1" maxlength="500" class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm font-normal" placeholder="Mô tả công việc cần hoàn thành..." /></label>
                            <button type="submit" :disabled="isSubmittingOvertime" class="md:col-span-4 inline-flex h-10 items-center justify-center rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white transition hover:bg-indigo-500 disabled:opacity-50">{{ isSubmittingOvertime ? 'Đang gửi...' : 'Gửi đơn xin tăng ca' }}</button>
                        </form>

                        <div v-if="overtimeLoading" class="rounded-2xl border border-border py-10 text-center text-sm text-muted-foreground">Đang tải đơn tăng ca...</div>
                        <div v-else-if="overtimeRequests.length === 0" class="rounded-2xl border border-dashed border-border py-10 text-center text-sm text-muted-foreground">Chưa có yêu cầu tăng ca.</div>
                        <div v-else class="space-y-3">
                            <div v-for="item in overtimeRequests" :key="item.id" class="flex flex-col justify-between gap-3 rounded-2xl border border-border bg-card p-4 sm:flex-row sm:items-center">
                                <div class="space-y-1"><div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-foreground">{{ formatDate(item.scheduled_date) }}</span><span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="getStatusBadge(item.status)">{{ getStatusText(item.status) }}</span><span class="text-[11px] text-muted-foreground">{{ item.overtime_type_label }} · {{ item.hours_requested }} giờ · {{ item.overtime_multiplier }}x</span></div><p class="text-xs text-muted-foreground">{{ item.scheduled_start_at || '--:--' }} - {{ item.scheduled_end_at || '--:--' }} · Dự kiến: <b class="text-indigo-600">{{ formatCurrency(item.estimated_amount) }}</b></p><p v-if="item.reason" class="text-xs text-muted-foreground">{{ item.reason }}</p><p v-if="item.rejection_reason" class="text-xs text-rose-500">{{ item.rejection_reason }}</p></div>
                                <div v-if="item.request_source === 'management' && item.employee_response === 'pending' && item.status === 'pending'" class="flex shrink-0 gap-2"><button type="button" class="rounded-xl bg-emerald-600 px-3 py-2 text-[11px] font-bold text-white hover:bg-emerald-500" @click="respondOvertime(item.id, 'accept')">Đồng ý</button><button type="button" class="rounded-xl border border-rose-300 px-3 py-2 text-[11px] font-bold text-rose-600 hover:bg-rose-50" @click="respondOvertime(item.id, 'decline')">Từ chối</button></div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 6: SALARY (LƯƠNG & PHIẾU LƯƠNG) -->
                    <div
                        v-if="activeTab === 'salary'"
                        class="animate-fadeIn space-y-4"
                    >
                        <h3 class="text-base font-semibold text-slate-200">
                            Lịch sử phiếu lương
                        </h3>

                        <div
                            v-if="salaries.length === 0"
                            class="rounded-2xl border border-slate-800 bg-slate-900 py-12 text-center text-xs text-slate-500"
                        >
                            Chưa có lịch sử bảng lương nào.
                        </div>
                        <div v-else class="grid grid-cols-1 gap-3">
                            <div
                                v-for="sal in salaries"
                                :key="sal.id"
                                class="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-900 p-4 transition hover:border-slate-700"
                            >
                                <div class="space-y-1.5">
                                    <h4
                                        class="text-sm font-bold text-slate-200"
                                    >
                                        Phiếu Lương Kỳ {{ sal.pay_period }}
                                    </h4>
                                    <div
                                        class="flex items-center space-x-2 text-xs"
                                    >
                                        <span class="text-slate-400"
                                            >Thực lĩnh:</span
                                        >
                                        <span
                                            class="font-bold text-indigo-400"
                                            >{{
                                                formatCurrency(sal.net_salary)
                                            }}</span
                                        >
                                    </div>
                                    <p
                                        v-if="sal.overtime_amount > 0"
                                        class="text-[11px] text-emerald-400"
                                    >
                                        OT: {{ formatCurrency(sal.overtime_amount) }}
                                        <span v-if="sal.compensation_type === 'hourly'">(đã tính trong lương theo giờ)</span>
                                        <span v-else>(cộng thêm theo hệ số)</span>
                                    </p>
                                    <p
                                        v-if="sal.paid_at"
                                        class="text-[10px] text-slate-500"
                                    >
                                        Thanh toán ngày: {{ sal.paid_at }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-3">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-medium"
                                        :class="getStatusBadge(sal.status)"
                                    >
                                        {{ getStatusText(sal.status) }}
                                    </span>

                                    <button
                                        @click="
                                            selectedSalary = sal;
                                            showSalaryModal = true;
                                        "
                                        class="rounded-xl border border-slate-700 bg-slate-800 p-2 text-slate-300 transition hover:bg-slate-700"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Mobile Premium Bottom Navigation Bar -->
            <nav
                v-if="false"
                class="fixed right-0 bottom-0 left-0 z-40 flex items-center justify-around border-t border-slate-800 bg-slate-950/95 px-4 py-2 backdrop-blur-lg md:hidden"
            >
                <button
                    v-for="tab in [
                        { key: 'home', label: 'Home', icon: LayoutGrid },
                        { key: 'schedule', label: 'Lịch', icon: Calendar },
                        { key: 'leave', label: 'Phép', icon: FileText },
                        { key: 'swap', label: 'Đổi ca', icon: RefreshCw },
                        { key: 'salary', label: 'Lương', icon: Coins },
                    ]"
                    :key="tab.key"
                    @click="changeTab(tab.key)"
                    class="flex flex-col items-center justify-center space-y-1.5 py-1 text-[10px] transition"
                    :class="
                        activeTab === tab.key
                            ? 'font-bold text-indigo-500'
                            : 'text-slate-500'
                    "
                >
                    <div class="relative">
                        <component :is="tab.icon" class="h-5 w-5" />
                        <span
                            v-if="
                                tab.key === 'home' &&
                                unreadNotificationCount > 0
                            "
                            class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-indigo-500"
                        ></span>
                    </div>
                    <span>{{ tab.label }}</span>
                </button>
            </nav>

            <!-- Payslip Modal Details -->
            <Teleport to="body">
                <div
                    v-if="showSalaryModal && selectedSalary"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
                >
                    <div
                        class="relative w-full max-w-md space-y-6 rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl"
                    >
                        <button
                            @click="showSalaryModal = false"
                            class="absolute top-4 right-4 flex h-7 w-7 items-center justify-center rounded-full bg-slate-800 text-sm font-bold text-slate-400 hover:text-slate-200"
                        >
                            ✕
                        </button>

                        <div class="space-y-1 text-center">
                            <h3 class="text-base font-bold text-slate-200">
                                Chi Tiết Phiếu Lương
                            </h3>
                            <p class="text-xs font-medium text-indigo-400">
                                Kỳ thanh toán: {{ selectedSalary.pay_period }}
                            </p>
                        </div>

                        <div
                            class="space-y-3 divide-y divide-slate-800 text-xs"
                        >
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-400"
                                    >Lương cứng cơ sở:</span
                                >
                                <span class="font-bold text-slate-200">{{
                                    formatCurrency(selectedSalary.base_salary)
                                }}</span>
                            </div>

                            <div
                                v-if="selectedSalary.overtime_amount > 0"
                                class="flex justify-between py-2.5"
                            >
                                <span class="text-slate-400">Tiền tăng ca:</span>
                                <span class="font-bold text-emerald-400">
                                    {{ formatCurrency(selectedSalary.overtime_amount) }}
                                </span>
                            </div>

                            <!-- Adjustments details -->
                            <div
                                v-if="
                                    selectedSalary.adjustments &&
                                    selectedSalary.adjustments.length > 0
                                "
                                class="space-y-2 py-2.5"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    Các khoản cộng/khấu trừ
                                </p>
                                <div
                                    v-for="adj in selectedSalary.adjustments"
                                    :key="adj.reason"
                                    class="flex items-start justify-between text-[11px]"
                                >
                                    <span class="max-w-[70%] text-slate-400">
                                        {{ adj.reason }}
                                    </span>
                                    <span
                                        :class="
                                            adj.type === 'bonus'
                                                ? 'text-emerald-400'
                                                : 'text-rose-400'
                                        "
                                        class="font-bold"
                                    >
                                        {{ adj.type === 'bonus' ? '+' : '-'
                                        }}{{ formatCurrency(adj.amount) }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex justify-between pt-3 text-sm">
                                <span class="font-bold text-slate-400"
                                    >Thực lĩnh thực tế:</span
                                >
                                <span
                                    class="text-base font-extrabold text-indigo-400"
                                    >{{
                                        formatCurrency(
                                            selectedSalary.net_salary,
                                        )
                                    }}</span
                                >
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                @click="showSalaryModal = false"
                                class="rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white transition hover:bg-indigo-500"
                            >
                                Đóng Phiếu Lương
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </section>
    </div>
</template>

<style>
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fadeIn {
    animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.employee-portal-page {
    background-color: var(--background) !important;
    color: var(--foreground);
}

/* The older tab panels still use slate utility classes. Map them to the same
   design tokens used by the rest of the management workspace. */
.employee-portal-page main [class*='bg-slate-950'] {
    background-color: var(--background) !important;
}

.employee-portal-page main [class*='bg-slate-900'] {
    background-color: var(--card) !important;
}

.employee-portal-page main [class*='bg-slate-800'],
.employee-portal-page main [class*='bg-slate-700'] {
    background-color: var(--muted) !important;
}

.employee-portal-page main [class*='border-slate-900'],
.employee-portal-page main [class*='border-slate-800'],
.employee-portal-page main [class*='border-slate-700'],
.employee-portal-page main [class*='divide-slate-800'] {
    border-color: var(--border) !important;
}

.employee-portal-page main [class*='text-slate-100'],
.employee-portal-page main [class*='text-slate-200'],
.employee-portal-page main [class*='text-slate-300'] {
    color: var(--foreground) !important;
}

.employee-portal-page main [class*='text-slate-400'],
.employee-portal-page main [class*='text-slate-500'] {
    color: var(--muted-foreground) !important;
}

.employee-portal-page main [class*='text-indigo-300'],
.employee-portal-page main [class*='text-indigo-400'] {
    color: var(--primary) !important;
}

.employee-portal-page main [class*='placeholder:text-slate-600']::placeholder,
.employee-portal-page main input::placeholder,
.employee-portal-page main textarea::placeholder {
    color: var(--muted-foreground) !important;
}

.employee-portal-page main input,
.employee-portal-page main select,
.employee-portal-page main textarea {
    color: var(--foreground);
}
</style>
