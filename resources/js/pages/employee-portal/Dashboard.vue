<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    LayoutGrid,
    Calendar,
    FileText,
    RefreshCw,
    Bell,
    User,
    CheckCircle,
    XCircle,
    AlertCircle,
    ChevronRight,
    UserCheck,
    Coins,
    Clock,
    Plus,
    Check,
    ArrowRightLeft,
    LogOut,
    Eye
} from 'lucide-vue-next';

// Layout setting for Inertia
defineOptions({
    layout: null // Uses BareLayout since it's null in inertia resolve, giving us full screen control
});

const page = usePage();
const employeeInfo = computed(() => page.props.employee as any);

// Tabs: 'home' | 'schedule' | 'leave' | 'swap' | 'salary'
const activeTab = ref('home');

// Core data states
const loading = ref(true);
const summary = ref({
    shifts_completed: 0,
    hours_worked: 0.0,
    estimated_earnings: 0.0,
    base_salary: 0.0,
    kpi_bonus: 0.0,
    kpi_commission: 0.0
});
const kpiData = ref<any>(null);
const schedules = ref<any[]>([]);
const notifications = ref<any[]>([]);
const salaries = ref<any[]>([]);
const leaves = ref<any[]>([]);
const swaps = ref<any>(null);

// Forms states
const leaveForm = ref({
    leave_type: 'annual',
    start_date: '',
    end_date: '',
    reason: ''
});
const leaveErrors = ref<any>({});
const leaveSuccessMessage = ref('');

const swapForm = ref({
    requester_assignment_id: '',
    receiver_assignment_id: '',
    notes: ''
});
const swapErrors = ref<any>({});
const swapSuccessMessage = ref('');

// Payslip Modal states
const selectedSalary = ref<any>(null);
const showSalaryModal = ref(false);

// Alert notification state
const unreadNotificationCount = computed(() => {
    return notifications.value.filter(n => !n.read_at).length;
});

// Load all dashboard data
const fetchData = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/employee-portal/data');
        if (response.data.success) {
            summary.value = response.data.summary;
            kpiData.value = response.data.kpis;
            schedules.value = response.data.schedules;
            notifications.value = response.data.notifications;
        }
    } catch (error) {
        console.error('Error fetching dashboard data:', error);
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
        console.error('Error fetching salaries:', error);
    }
};

const fetchLeaves = async () => {
    try {
        const response = await axios.get('/employee-portal/leaves');
        if (response.data.success) {
            leaves.value = response.data.leaves;
        }
    } catch (error) {
        console.error('Error fetching leaves:', error);
    }
};

const fetchSwaps = async () => {
    try {
        const response = await axios.get('/employee-portal/swaps');
        if (response.data.success) {
            swaps.value = response.data;
        }
    } catch (error) {
        console.error('Error fetching swaps:', error);
    }
};

// Handle Leave Submit
const submitLeave = async () => {
    leaveErrors.value = {};
    leaveSuccessMessage.value = '';
    try {
        const response = await axios.post('/employee-portal/leaves', leaveForm.value);
        if (response.data.success) {
            leaveSuccessMessage.value = response.data.message;
            leaveForm.value = { leave_type: 'annual', start_date: '', end_date: '', reason: '' };
            fetchLeaves();
        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            leaveErrors.value = { general: error.response.data.error || error.response.data.message };
        } else {
            leaveErrors.value = { general: 'Có lỗi xảy ra khi nộp đơn xin nghỉ phép.' };
        }
    }
};

// Handle Swap Submit
const submitSwapRequest = async () => {
    swapErrors.value = {};
    swapSuccessMessage.value = '';
    try {
        const response = await axios.post('/employee-portal/swaps/request', swapForm.value);
        if (response.data.success) {
            swapSuccessMessage.value = response.data.message;
            swapForm.value = { requester_assignment_id: '', receiver_assignment_id: '', notes: '' };
            fetchSwaps();
        }
    } catch (error: any) {
        if (error.response && error.response.status === 422) {
            swapErrors.value = { general: error.response.data.error || error.response.data.message };
        } else {
            swapErrors.value = { general: 'Có lỗi xảy ra khi yêu cầu đổi ca.' };
        }
    }
};

// Handle Swap Response (Accept/Cancel/Reject)
const handleSwapResponse = async (swapId: number, action: 'accept' | 'cancel' | 'reject') => {
    try {
        const response = await axios.post(`/employee-portal/swaps/${swapId}/respond`, { action });
        if (response.data.success) {
            fetchSwaps();
            fetchData();
        }
    } catch (error) {
        console.error('Error responding to swap:', error);
    }
};

// Mark all read
const markAllRead = async () => {
    try {
        await axios.post('/employee-portal/notifications/read-all');
        fetchData();
    } catch (error) {
        console.error(error);
    }
};

// Lifecycle
onMounted(() => {
    fetchData();
});

// Watch tab shifts to load specific items
const changeTab = (tab: string) => {
    activeTab.value = tab;
    if (tab === 'salary') fetchSalaries();
    if (tab === 'leave') fetchLeaves();
    if (tab === 'swap') fetchSwaps();
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    if (isNaN(date.getTime())) return dateStr;
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
        case 'completed': return 'Đã hoàn thành';
        case 'approved': return 'Đã duyệt';
        case 'paid': return 'Đã thanh toán';
        case 'accepted': return 'Đã chấp nhận';
        case 'pending': return 'Chờ duyệt';
        case 'scheduled': return 'Được xếp lịch';
        case 'rejected': return 'Bị từ chối';
        case 'cancelled': return 'Bị hủy';
        default: return status;
    }
};

const getLeaveTypeText = (type: string) => {
    switch (type) {
        case 'annual': return 'Nghỉ phép năm';
        case 'sick': return 'Nghỉ ốm';
        case 'unpaid': return 'Nghỉ không lương';
        case 'emergency': return 'Nghỉ khẩn cấp';
        default: return type;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col font-sans select-none pb-24 md:pb-0 md:pl-0">
        <Head title="Employee Portal" />

        <!-- Mobile/Desktop Premium Header -->
        <header class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-white shadow-lg shadow-indigo-500/30">
                    {{ employeeInfo?.full_name ? employeeInfo.full_name.charAt(0) : 'E' }}
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-slate-200">Chào {{ employeeInfo?.full_name ?? 'Nhân viên' }} 👋</h2>
                    <p class="text-xs text-indigo-400 font-medium">{{ employeeInfo?.job_title ?? 'Nhân viên' }} ({{ employeeInfo?.employee_code }})</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <!-- Desktop Navigation Tab bar -->
                <div class="hidden md:flex space-x-1 bg-slate-950/80 p-1 rounded-lg border border-slate-800 mr-4">
                    <button
                        v-for="t in [
                            { key: 'home', label: 'Tổng quan', icon: LayoutGrid },
                            { key: 'schedule', label: 'Lịch ca', icon: Calendar },
                            { key: 'leave', label: 'Nghỉ phép', icon: FileText },
                            { key: 'swap', label: 'Đổi ca', icon: RefreshCw },
                            { key: 'salary', label: 'Lương', icon: Coins }
                        ]"
                        :key="t.key"
                        @click="changeTab(t.key)"
                        class="flex items-center space-x-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition"
                        :class="activeTab === t.key ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-slate-200'"
                    >
                        <component :is="t.icon" class="h-3.5 w-3.5" />
                        <span>{{ t.label }}</span>
                    </button>
                </div>

                <a href="/dashboard" class="flex items-center space-x-1 px-3 py-1.5 rounded-lg text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium border border-slate-700 transition">
                    <LogOut class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Quay lại Quản lý</span>
                </a>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="flex-1 max-w-4xl w-full mx-auto p-4 space-y-6">
            <div v-if="loading" class="flex flex-col items-center justify-center py-24 space-y-4">
                <div class="h-10 w-10 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-sm text-slate-400">Đang tải dữ liệu, vui lòng đợi...</p>
            </div>

            <div v-else class="space-y-6">

                <!-- TAB 1: HOME (DASHBOARD) -->
                <div v-if="activeTab === 'home'" class="space-y-6 animate-fadeIn">
                    
                    <!-- Earnings & Hours Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-indigo-900/40 to-slate-900 border border-indigo-500/20 rounded-2xl p-5 shadow-xl relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 opacity-10">
                                <Coins class="h-28 w-28" />
                            </div>
                            <h3 class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Ước Tính Thu Nhập Tháng</h3>
                            <p class="text-2xl font-bold mt-2 text-white">{{ formatCurrency(summary.estimated_earnings) }}</p>
                            <div class="mt-3 flex items-center space-x-2 text-[10px] text-slate-400">
                                <span class="bg-slate-800 px-1.5 py-0.5 rounded">Cứng: {{ formatCurrency(summary.base_salary) }}</span>
                                <span v-if="summary.kpi_bonus > 0" class="bg-emerald-500/10 text-emerald-400 px-1.5 py-0.5 rounded">Thưởng KPI: +{{ formatCurrency(summary.kpi_bonus) }}</span>
                            </div>
                        </div>

                        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-xl">
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tổng Giờ Làm (Tháng Này)</h3>
                            <p class="text-2xl font-bold mt-2 text-white">{{ summary.hours_worked }} <span class="text-xs font-normal text-slate-400">giờ</span></p>
                            <p class="text-xs text-slate-500 mt-2">Tổng số giờ chấm công hoàn thành.</p>
                        </div>

                        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-5 shadow-xl">
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Số Ca Đã Hoàn Thành</h3>
                            <p class="text-2xl font-bold mt-2 text-white">{{ summary.shifts_completed }} <span class="text-xs font-normal text-slate-400">ca</span></p>
                            <p class="text-xs text-slate-500 mt-2">Các ca làm việc đã xác nhận hoàn thành.</p>
                        </div>
                    </div>

                    <!-- Upcoming shifts quick view -->
                    <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <h3 class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                            <Calendar class="h-4 w-4 text-indigo-400" />
                            <span>Lịch làm việc sắp tới (Tuần này)</span>
                        </h3>
                        
                        <div v-if="schedules.length === 0" class="text-center py-6 text-xs text-slate-500">
                            Không có ca trực nào được xếp lịch.
                        </div>
                        <div v-else class="space-y-3">
                            <div 
                                v-for="sched in schedules.slice(0, 3)" 
                                :key="sched.id"
                                class="bg-slate-950/60 border border-slate-800 rounded-xl p-3.5 flex items-center justify-between transition hover:border-slate-700"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="h-9 w-9 rounded-lg bg-indigo-600/10 border border-indigo-500/20 flex flex-col items-center justify-center text-[10px] font-bold text-indigo-400">
                                        <span>{{ sched.day_name }}</span>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-200">{{ sched.shift_name }}</p>
                                        <p class="text-[11px] text-slate-400">{{ sched.start_time }} - {{ sched.end_time }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium" :class="getStatusBadge(sched.status)">
                                        {{ getStatusText(sched.status) }}
                                    </span>
                                    <span v-if="sched.check_in_at" class="text-[10px] text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded">
                                        Vào: {{ sched.check_in_at }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal KPI Progress -->
                    <div v-if="kpiData" class="bg-slate-900/40 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                                <Coins class="h-4 w-4 text-emerald-400" />
                                <span>Hiệu Suất KPI & Ước Tính Thưởng</span>
                            </h3>
                            <span class="text-xs text-indigo-400 font-bold bg-indigo-500/10 px-2 py-0.5 rounded">
                                Điểm KPI: {{ kpiData.total_score }} / 100
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                            <div 
                                v-for="metric in kpiData.metrics" 
                                :key="metric.metric_name"
                                class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-3.5 flex flex-col justify-between space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-200">{{ metric.metric_name }}</h4>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium" :class="metric.is_achieved ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">
                                        {{ metric.is_achieved ? 'Đạt' : 'Chưa Đạt' }}
                                    </span>
                                </div>

                                <div class="flex justify-between items-end text-xs">
                                    <div class="space-y-1">
                                        <p class="text-[10px] text-slate-500">Tiến độ thực tế / Chỉ tiêu</p>
                                        <p class="text-sm font-bold text-slate-300">
                                            {{ metric.actual_value }} <span class="text-slate-500 font-medium">/ {{ metric.target_value }}</span>
                                        </p>
                                    </div>
                                    <div class="text-right space-y-0.5 text-[11px]">
                                        <p v-if="metric.bonus_earned > 0" class="text-emerald-400 font-bold">+{{ formatCurrency(metric.bonus_earned) }} Thưởng</p>
                                        <p v-if="metric.commission_earned > 0" class="text-indigo-400 font-bold">+{{ formatCurrency(metric.commission_earned) }} Hoa hồng</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Center -->
                    <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                                <Bell class="h-4 w-4 text-indigo-400" />
                                <span>Thông báo mới</span>
                            </h3>
                            <button 
                                v-if="unreadNotificationCount > 0" 
                                @click="markAllRead" 
                                class="text-xs text-indigo-400 hover:text-indigo-300 font-medium"
                            >
                                Đọc tất cả
                            </button>
                        </div>

                        <div v-if="notifications.length === 0" class="text-center py-6 text-xs text-slate-500">
                            Không có thông báo mới nào.
                        </div>
                        <div v-else class="space-y-2">
                            <div 
                                v-for="notif in notifications" 
                                :key="notif.id"
                                class="p-3 rounded-xl text-xs flex items-start space-x-3 border transition"
                                :class="notif.read_at ? 'bg-slate-950/20 border-slate-900 text-slate-400' : 'bg-slate-900 border-slate-800 text-slate-200'"
                            >
                                <span class="h-2 w-2 rounded-full mt-1.5 flex-shrink-0" :class="notif.read_at ? 'bg-slate-700' : 'bg-indigo-500'"></span>
                                <div class="flex-1 space-y-1">
                                    <p>{{ notif.message }}</p>
                                    <span class="text-[10px] text-slate-500 font-medium block">{{ notif.created_at }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- TAB 2: SCHEDULE (LỊCH TRỰC CHI TIẾT) -->
                <div v-if="activeTab === 'schedule'" class="space-y-4 animate-fadeIn">
                    <h3 class="text-base font-semibold text-slate-200">Lịch làm việc chi tiết</h3>
                    <p class="text-xs text-slate-400">Lịch làm việc hiển thị ca trực 3 tuần gần nhất (tuần trước, tuần này, tuần sau).</p>

                    <div v-if="schedules.length === 0" class="text-center py-12 bg-slate-900 border border-slate-800 rounded-2xl text-xs text-slate-500">
                        Không tìm thấy lịch trực trong hệ thống.
                    </div>
                    <div v-else class="grid grid-cols-1 gap-3">
                        <div 
                            v-for="sched in schedules" 
                            :key="sched.id"
                            class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0 transition hover:border-slate-700"
                        >
                            <div class="flex items-start space-x-4">
                                <div class="h-12 w-12 rounded-xl bg-indigo-600/10 border border-indigo-500/20 flex flex-col items-center justify-center text-xs font-bold text-indigo-400 flex-shrink-0">
                                    <span>{{ sched.day_name }}</span>
                                    <span class="text-[9px] font-medium text-slate-400">{{ sched.formatted_date.slice(0, 5) }}</span>
                                </div>
                                <div class="space-y-1">
                                    <h4 class="text-sm font-bold text-slate-200">{{ sched.shift_name }}</h4>
                                    <p class="text-xs text-slate-400 flex items-center space-x-2">
                                        <Clock class="h-3 w-3 text-slate-500" />
                                        <span>{{ sched.start_time }} - {{ sched.end_time }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end sm:space-x-4">
                                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium" :class="getStatusBadge(sched.status)">
                                    {{ getStatusText(sched.status) }}
                                </span>

                                <div class="text-right text-xs">
                                    <p v-if="sched.check_in_at" class="text-emerald-400 font-medium">Check-In: {{ sched.check_in_at }}</p>
                                    <p v-if="sched.check_out_at" class="text-amber-400 font-medium">Check-Out: {{ sched.check_out_at }}</p>
                                    <p v-if="!sched.check_in_at && sched.status === 'scheduled'" class="text-slate-500">Chưa bắt đầu ca</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: LEAVE (NGHỈ PHÉP) -->
                <div v-if="activeTab === 'leave'" class="space-y-6 animate-fadeIn">
                    
                    <!-- Leave Form -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <h3 class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                            <Plus class="h-4 w-4 text-indigo-400" />
                            <span>Đăng ký nghỉ phép mới</span>
                        </h3>

                        <form @submit.prevent="submitLeave" class="space-y-4">
                            <div v-if="leaveErrors.general" class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs">
                                {{ leaveErrors.general }}
                            </div>
                            <div v-if="leaveSuccessMessage" class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs">
                                {{ leaveSuccessMessage }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div class="space-y-1">
                                    <label class="text-xs text-slate-400 font-medium">Loại nghỉ phép</label>
                                    <select 
                                        v-model="leaveForm.leave_type" 
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="annual">Nghỉ phép năm (có lương)</option>
                                        <option value="sick">Nghỉ ốm</option>
                                        <option value="unpaid">Nghỉ không lương</option>
                                        <option value="emergency">Nghỉ khẩn cấp</option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs text-slate-400 font-medium">Từ ngày</label>
                                    <input 
                                        v-model="leaveForm.start_date" 
                                        type="date"
                                        required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs text-slate-400 font-medium">Đến ngày</label>
                                    <input 
                                        v-model="leaveForm.end_date" 
                                        type="date"
                                        required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs text-slate-400 font-medium">Lý do nghỉ phép</label>
                                <textarea 
                                    v-model="leaveForm.reason" 
                                    rows="2"
                                    placeholder="Điền lý do xin nghỉ chi tiết..."
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 placeholder:text-slate-600"
                                ></textarea>
                            </div>

                            <button 
                                type="submit"
                                class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-5 py-2.5 rounded-xl transition"
                            >
                                Gửi Đơn Xin Nghỉ
                            </button>
                        </form>
                    </div>

                    <!-- Leave History -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <h3 class="text-sm font-semibold text-slate-200">Lịch sử xin nghỉ phép</h3>

                        <div v-if="leaves.length === 0" class="text-center py-8 text-xs text-slate-500">
                            Bạn chưa gửi đơn xin nghỉ phép nào.
                        </div>
                        <div v-else class="space-y-3">
                            <div 
                                v-for="leave in leaves" 
                                :key="leave.id"
                                class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-3.5 flex items-center justify-between"
                            >
                                <div class="space-y-1.5">
                                    <h4 class="text-xs font-bold text-slate-200">
                                        {{ getLeaveTypeText(leave.leave_type) }}
                                    </h4>
                                    <p class="text-[11px] text-slate-400">
                                        {{ formatDate(leave.start_date) }} - {{ formatDate(leave.end_date) }}
                                    </p>
                                    <p v-if="leave.reason" class="text-[10px] text-slate-500 italic">Lý do: {{ leave.reason }}</p>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span class="text-[10px] px-2.5 py-0.5 rounded-full font-medium" :class="getStatusBadge(leave.status)">
                                        {{ getStatusText(leave.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: SWAP (ĐỔI CA) -->
                <div v-if="activeTab === 'swap'" class="space-y-6 animate-fadeIn">
                    
                    <!-- Swap Request Form -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <h3 class="text-sm font-semibold text-slate-200 flex items-center space-x-2">
                            <ArrowRightLeft class="h-4 w-4 text-indigo-400" />
                            <span>Đề xuất đổi ca trực</span>
                        </h3>

                        <form @submit.prevent="submitSwapRequest" class="space-y-4">
                            <div v-if="swapErrors.general" class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs">
                                {{ swapErrors.general }}
                            </div>
                            <div v-if="swapSuccessMessage" class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs">
                                {{ swapSuccessMessage }}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label class="text-xs text-slate-400 font-medium">Ca trực của bạn cần đổi</label>
                                    <select 
                                        v-model="swapForm.requester_assignment_id" 
                                        required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="" disabled>-- Chọn ca trực của bạn --</option>
                                        <option 
                                            v-for="a in swaps?.my_assignments" 
                                            :key="a.id" 
                                            :value="a.id"
                                        >
                                            Ngày {{ a.date_formatted }} - {{ a.shift_name }} ({{ a.shift_time }})
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label class="text-xs text-slate-400 font-medium">Ca trực muốn đổi của đồng nghiệp</label>
                                    <select 
                                        v-model="swapForm.receiver_assignment_id" 
                                        required
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                                    >
                                        <option value="" disabled>-- Chọn ca trực đồng nghiệp --</option>
                                        <option 
                                            v-for="a in swaps?.colleague_assignments" 
                                            :key="a.id" 
                                            :value="a.id"
                                        >
                                            {{ a.employee_name }} - {{ a.day_name }} {{ a.date_formatted }} - {{ a.shift_name }} ({{ a.shift_time }})
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs text-slate-400 font-medium">Ghi chú gửi đồng nghiệp</label>
                                <input 
                                    v-model="swapForm.notes"
                                    type="text"
                                    placeholder="Điền ghi chú (ví dụ: bận việc gia đình, đổi ca trực sáng/chiều...)"
                                    class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 placeholder:text-slate-600"
                                />
                            </div>

                            <button 
                                type="submit"
                                class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-5 py-2.5 rounded-xl transition"
                            >
                                Gửi Yêu Cầu Đổi Ca
                            </button>
                        </form>
                    </div>

                    <!-- Active Swaps List -->
                    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                        <h3 class="text-sm font-semibold text-slate-200">Danh sách các yêu cầu đổi ca trực</h3>

                        <div v-if="!swaps?.swaps || swaps.swaps.length === 0" class="text-center py-8 text-xs text-slate-500">
                            Không có yêu cầu đổi ca trực nào.
                        </div>
                        <div v-else class="space-y-3">
                            <div 
                                v-for="swap in swaps.swaps" 
                                :key="swap.id"
                                class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-4 space-y-3 flex flex-col justify-between"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="space-y-1">
                                        <p class="text-xs text-slate-400">
                                            <span class="font-bold text-slate-200">{{ swap.requester_name }}</span> muốn đổi
                                        </p>
                                        <p class="text-xs font-bold text-indigo-400">Ca {{ swap.requester_shift }} (Ngày {{ swap.requester_date }})</p>
                                        
                                        <div class="py-1 text-slate-500 flex items-center space-x-2 text-[10px]">
                                            <ArrowRightLeft class="h-3 w-3 text-slate-500" />
                                            <span>Đổi với ca trực của {{ swap.receiver_name }}:</span>
                                        </div>

                                        <p class="text-xs font-bold text-amber-400">Ca {{ swap.receiver_shift }} (Ngày {{ swap.receiver_date }})</p>
                                    </div>
                                    <span class="text-[10px] px-2.5 py-0.5 rounded-full font-medium" :class="getStatusBadge(swap.status)">
                                        {{ getStatusText(swap.status) }}
                                    </span>
                                </div>
                                <p v-if="swap.notes" class="text-[10px] text-slate-500 italic bg-slate-950 px-2 py-1 rounded">Ghi chú: {{ swap.notes }}</p>

                                <!-- Respond controls -->
                                <div v-if="swap.status === 'pending'" class="flex items-center space-x-2 pt-1">
                                    <!-- I am the receiver and can accept or reject -->
                                    <template v-if="!swap.is_requester">
                                        <button 
                                            @click="handleSwapResponse(swap.id, 'accept')"
                                            class="bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold px-3 py-1.5 rounded-lg transition flex items-center space-x-1"
                                        >
                                            <Check class="h-3.5 w-3.5" />
                                            <span>Đồng ý đổi</span>
                                        </button>
                                        <button 
                                            @click="handleSwapResponse(swap.id, 'reject')"
                                            class="bg-slate-800 hover:bg-slate-700 text-rose-400 text-[10px] font-bold px-3 py-1.5 rounded-lg transition border border-slate-700"
                                        >
                                            Từ chối
                                        </button>
                                    </template>
                                    <!-- I am the requester and can cancel -->
                                    <template v-else>
                                        <button 
                                            @click="handleSwapResponse(swap.id, 'cancel')"
                                            class="bg-slate-800 hover:bg-slate-700 text-slate-400 text-[10px] font-bold px-3 py-1.5 rounded-lg transition border border-slate-700"
                                        >
                                            Hủy yêu cầu
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: SALARY (LƯƠNG & PHIẾU LƯƠNG) -->
                <div v-if="activeTab === 'salary'" class="space-y-4 animate-fadeIn">
                    <h3 class="text-base font-semibold text-slate-200">Lịch sử phiếu lương</h3>

                    <div v-if="salaries.length === 0" class="text-center py-12 bg-slate-900 border border-slate-800 rounded-2xl text-xs text-slate-500">
                        Chưa có lịch sử bảng lương nào.
                    </div>
                    <div v-else class="grid grid-cols-1 gap-3">
                        <div 
                            v-for="sal in salaries" 
                            :key="sal.id"
                            class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex items-center justify-between transition hover:border-slate-700"
                        >
                            <div class="space-y-1.5">
                                <h4 class="text-sm font-bold text-slate-200">Phiếu Lương Kỳ {{ sal.pay_period }}</h4>
                                <div class="flex items-center space-x-2 text-xs">
                                    <span class="text-slate-400">Thực lĩnh:</span>
                                    <span class="text-indigo-400 font-bold">{{ formatCurrency(sal.net_salary) }}</span>
                                </div>
                                <p v-if="sal.paid_at" class="text-[10px] text-slate-500">Thanh toán ngày: {{ sal.paid_at }}</p>
                            </div>

                            <div class="flex items-center space-x-3">
                                <span class="text-[10px] px-2.5 py-0.5 rounded-full font-medium" :class="getStatusBadge(sal.status)">
                                    {{ getStatusText(sal.status) }}
                                </span>
                                
                                <button 
                                    @click="selectedSalary = sal; showSalaryModal = true"
                                    class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl transition border border-slate-700"
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
        <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-950/95 backdrop-blur-lg border-t border-slate-800 px-4 py-2 flex justify-around items-center">
            <button
                v-for="tab in [
                    { key: 'home', label: 'Home', icon: LayoutGrid },
                    { key: 'schedule', label: 'Lịch', icon: Calendar },
                    { key: 'leave', label: 'Phép', icon: FileText },
                    { key: 'swap', label: 'Đổi ca', icon: RefreshCw },
                    { key: 'salary', label: 'Lương', icon: Coins }
                ]"
                :key="tab.key"
                @click="changeTab(tab.key)"
                class="flex flex-col items-center justify-center space-y-1.5 py-1 text-[10px] transition"
                :class="activeTab === tab.key ? 'text-indigo-500 font-bold' : 'text-slate-500'"
            >
                <div class="relative">
                    <component :is="tab.icon" class="h-5 w-5" />
                    <span 
                        v-if="tab.key === 'home' && unreadNotificationCount > 0" 
                        class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-indigo-500"
                    ></span>
                </div>
                <span>{{ tab.label }}</span>
            </button>
        </nav>

        <!-- Payslip Modal Details -->
        <div v-if="showSalaryModal && selectedSalary" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl relative">
                <button 
                    @click="showSalaryModal = false"
                    class="absolute top-4 right-4 text-slate-400 hover:text-slate-200 text-sm font-bold bg-slate-800 rounded-full h-7 w-7 flex items-center justify-center"
                >
                    ✕
                </button>

                <div class="text-center space-y-1">
                    <h3 class="text-base font-bold text-slate-200">Chi Tiết Phiếu Lương</h3>
                    <p class="text-xs text-indigo-400 font-medium">Kỳ thanh toán: {{ selectedSalary.pay_period }}</p>
                </div>

                <div class="space-y-3 divide-y divide-slate-800 text-xs">
                    <div class="flex justify-between py-2.5">
                        <span class="text-slate-400">Lương cứng cơ sở:</span>
                        <span class="font-bold text-slate-200">{{ formatCurrency(selectedSalary.base_salary) }}</span>
                    </div>

                    <!-- Adjustments details -->
                    <div v-if="selectedSalary.adjustments && selectedSalary.adjustments.length > 0" class="py-2.5 space-y-2">
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Các khoản cộng/khấu trừ</p>
                        <div 
                            v-for="adj in selectedSalary.adjustments" 
                            :key="adj.reason"
                            class="flex justify-between items-start text-[11px]"
                        >
                            <span class="text-slate-400 max-w-[70%]">
                                {{ adj.reason }}
                            </span>
                            <span :class="adj.type === 'bonus' ? 'text-emerald-400' : 'text-rose-400'" class="font-bold">
                                {{ adj.type === 'bonus' ? '+' : '-' }}{{ formatCurrency(adj.amount) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex justify-between pt-3 text-sm">
                        <span class="font-bold text-slate-400">Thực lĩnh thực tế:</span>
                        <span class="font-extrabold text-indigo-400 text-base">{{ formatCurrency(selectedSalary.net_salary) }}</span>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button 
                        @click="showSalaryModal = false"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs px-5 py-2.5 rounded-xl transition"
                    >
                        Đóng Phiếu Lương
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
    animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
