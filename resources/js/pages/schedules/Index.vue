<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

// Existing Shared Modals & Navigation Drawers
import NotificationDrawer from './components/NotificationDrawer.vue';
import WebcamCheckInModal from './components/WebcamCheckInModal.vue';
import ShiftSwapProposalModal from './components/ShiftSwapProposalModal.vue';

// Admin Components
import AdminOverrideModal from './components/AdminOverrideModal.vue';
import AdminAttendanceLogs from './components/AdminAttendanceLogs.vue';
import AdminWeeklyRoster from './components/AdminWeeklyRoster.vue';
import AdminShiftRegistrations from './components/AdminShiftRegistrations.vue';
import AdminTimekeepingSettings from './components/AdminTimekeepingSettings.vue';
import AdminShiftSwapApprovals from './components/AdminShiftSwapApprovals.vue';
import AdminAttendanceAnalytics from './components/AdminAttendanceAnalytics.vue';

// Staff Components
import StaffTimeClock from './components/StaffTimeClock.vue';
import StaffWeeklyRoster from './components/StaffWeeklyRoster.vue';
import StaffShiftSwapRequests from './components/StaffShiftSwapRequests.vue';
import StaffShiftRegistration from './components/StaffShiftRegistration.vue';

defineOptions({ layout: AppLayout });

type Assignment = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string;
    job_title: string;
    shift_id: number;
    shift_name: string;
    shift_time: string;
    scheduled_date: string;
    check_in_at: string | null;
    check_out_at: string | null;
    status: 'scheduled' | 'checked_in' | 'completed' | 'absent' | 'leave_approved';
    is_shift_leader: boolean;
    duration: string | null;
    notes: string | null;
    check_in_photo_path: string | null;
};

type StaffingTip = {
    shift: string;
    pct: number;
    message: string;
    level: 'warning' | 'info';
};

type PropType = {
    isAdmin: boolean;
    staffingTips?: StaffingTip[];
    // Admin specific props
    selectedDate?: string;
    assignments?: Assignment[];
    stats?: {
        scheduled: number;
        working: number;
        completed: number;
        absent: number;
        leave: number;
        total: number;
    };
    weeklyAssignments?: any[];
    employee?: { id: number; full_name: string };
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
    employees?: Array<{ id: number; full_name: string; job_title: string; employee_code: string }>;
    registrations?: Array<{
        id: number;
        employee_id: number;
        employee_name: string;
        employee_code: string;
        job_title: string;
        shift_id: number;
        shift_name: string;
        scheduled_date: string;
        day: string;
    }>;
    restaurantSettings?: {
        grace_period_minutes: number;
        ot_multiplier: number;
    };
    gpsSettings?: {
        latitude: number | null;
        longitude: number | null;
        radius: number;
    };
    qrSettings?: {
        code: string | null;
        expires_at: string | null;
        is_expired: boolean;
    };
    allPendingSwaps?: any[];
    pendingSwapRequests?: any[];
    monthlyAssignments?: any[];
    // Staff specific props
    myWeeklySchedules?: any[];
    myRegistrations?: Array<{ shift_id: number; date: string }>;
    todayActiveAssignment?: {
        id: number;
        shift_name: string;
        shift_time: string;
        check_in_at: string | null;
        status: string;
        duration: string | null;
        can_check_in: boolean;
        can_check_out: boolean;
    } | null;
};

const props = defineProps<PropType>();

// --- REAL-TIME LIVE CLOCK ---
const currentTime = ref('');
const currentDate = ref('');
let clockInterval: any = null;

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    currentDate.value = now.toLocaleDateString('vi-VN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
});

const activeStaffTab = ref<'roster' | 'register'>('roster');
const activeAdminTab = ref<'attendance' | 'roster' | 'register' | 'settings' | 'swaps' | 'analytics'>('attendance');

// --- OVERRIDE MODAL CONTROL ---
const overrideModal = ref(false);
const activeOverrideAssignment = ref<Assignment | null>(null);
const overrideAction = ref<'check_in' | 'check_out' | 'absent'>('check_in');

const openOverrideModal = (assignment: Assignment, action: 'check_in' | 'check_out' | 'absent') => {
    activeOverrideAssignment.value = assignment;
    overrideAction.value = action;
    overrideModal.value = true;
};

const closeOverrideModal = () => {
    overrideModal.value = false;
    activeOverrideAssignment.value = null;
};

const handleOverrideSuccess = () => {
    closeOverrideModal();
    refreshAdminData();
};

// --- SECURE WEBCAM CHECK-IN MODAL CONTROL ---
const checkInModalOpen = ref(false);

const openCheckInFlow = () => {
    checkInModalOpen.value = true;
};

const handleCheckInSuccess = () => {
    checkInModalOpen.value = false;
};

// --- PEER-TO-PEER SHIFT SWAP MODAL CONTROL ---
const swapModalOpen = ref(false);
const selectedMyShift = ref<any>(null);

const swappableShifts = computed(() => {
    if (!props.weeklyAssignments || !props.employee) return [];
    return props.weeklyAssignments.filter(wa => wa.employee_id !== props.employee?.id);
});

const openSwapModal = (myShift: any) => {
    selectedMyShift.value = myShift;
    swapModalOpen.value = true;
};

const handleSwapSuccess = () => {
    swapModalOpen.value = false;
};

// --- SHIFT LEADER ACTIONS ---
const toggleShiftLeader = (assignmentId: number) => {
    router.post('/schedules/toggle-leader', {
        assignment_id: assignmentId
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Cập nhật vai trò Trưởng ca thành công!'));
        }
    });
};

// --- DATA FILTER ACTIONS ---
const adminDate = ref(props.selectedDate || new Date().toISOString().split('T')[0]);

const handleDateChange = (newDate: string) => {
    adminDate.value = newDate;
    router.get('/schedules', { date: adminDate.value }, { preserveState: true });
};

const refreshAdminData = () => {
    router.get('/schedules', { date: adminDate.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Chấm Công & Lịch Trực" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full print:p-0">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 print:hidden">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-955 bg-indigo-50/50 text-indigo-650 dark:text-indigo-400">
                    <CalendarDays class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Quản Lý Chấm Công & Ca Trực</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ isAdmin 
                            ? 'Theo dõi chấm công thời gian thực, quản lý ca trực nhân viên và duyệt báo cáo chuyên nghiệp.'
                            : 'Theo dõi lịch làm việc cá nhân hàng tuần và thực hiện chấm công vào ca/ra ca.' }}
                    </p>
                </div>
            </div>

            <!-- TIME CLOCK DIGITAL DISPLAY & NOTIFICATION BELL -->
            <div class="flex items-center gap-4">
                <!-- Notifications Bell & Drawer -->
                <NotificationDrawer />

                <div class="flex flex-col text-right justify-center bg-slate-50 dark:bg-slate-900/50 border rounded-2xl px-5 py-3 shadow-xs">
                    <span class="text-sm font-bold text-slate-400 dark:text-slate-555 uppercase tracking-widest font-mono text-[9px]">{{ currentDate }}</span>
                    <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono tracking-tight">{{ currentTime }}</span>
                </div>
            </div>
        </div>

        <!-- PRINT ONLY HEADER -->
        <div class="hidden print:block border-b pb-4 mb-6">
            <h1 class="text-xl font-bold text-center uppercase">BẢNG DANH SÁCH CHẤM CÔNG NHÂN VIÊN</h1>
            <p class="text-xs text-center text-slate-500 mt-1">Ngày chấm công: {{ adminDate }} | Đơn vị: Hệ thống nhà hàng Aventura</p>
        </div>

        <!-- ========================================== -->
        <!-- 1. ADMIN MONITORING VIEW (OWNER / MANAGER) -->
        <!-- ========================================== -->
        <div v-if="isAdmin" class="space-y-6">

            <!-- AI Staffing Suggestions Banner -->
            <div v-if="staffingTips && staffingTips.length > 0" class="space-y-2">
                <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                    <span class="text-sm">⚡</span> Gợi ý AI — Tối ưu nhân sự theo ca
                </p>
                <div v-for="(tip, i) in staffingTips" :key="i"
                    :class="[
                        'flex items-start gap-3 p-3 rounded-xl border text-xs',
                        tip.level === 'warning'
                            ? 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800/40'
                            : 'bg-blue-50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800/40'
                    ]"
                >
                    <span class="text-base shrink-0 mt-0.5">{{ tip.level === 'warning' ? '⚠️' : '💡' }}</span>
                    <p :class="tip.level === 'warning' ? 'text-amber-800 dark:text-amber-300' : 'text-blue-800 dark:text-blue-300'"
                       v-html="tip.message" />
                    <span class="ml-auto shrink-0 font-bold text-[10px]" :class="tip.level === 'warning' ? 'text-amber-600' : 'text-blue-500'">
                        {{ tip.pct }}% DT
                    </span>
                </div>
            </div>

            <!-- Attendance Summary Stats Panel -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 print:hidden">
                <!-- Total Scheduled -->
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border rounded-2xl shadow-xs hover:translate-y-[-2px] transition-transform">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng lịch trực</span>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-3xl font-black text-slate-800 dark:text-slate-100">{{ stats?.total }}</span>
                        <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 font-bold text-sm">📅</div>
                    </div>
                </div>

                <!-- Working -->
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border border-emerald-100 dark:border-emerald-950/20 rounded-2xl shadow-xs hover:translate-y-[-2px] transition-transform">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-500">Đang làm việc</span>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ stats?.working }}</span>
                        <div class="h-8 w-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 font-bold text-sm animate-pulse">⏰</div>
                    </div>
                </div>

                <!-- Completed -->
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border border-indigo-100 dark:border-indigo-950/20 rounded-2xl shadow-xs hover:translate-y-[-2px] transition-transform">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-500">Hoàn thành ca</span>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ stats?.completed }}</span>
                        <div class="h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-600 font-bold text-sm">✅</div>
                    </div>
                </div>

                <!-- Leave -->
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl shadow-xs hover:translate-y-[-2px] transition-transform">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Nghỉ phép duyệt</span>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-3xl font-black text-slate-700 dark:text-slate-300">{{ stats?.leave }}</span>
                        <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 font-bold text-sm">👤</div>
                    </div>
                </div>

                <!-- Absent -->
                <div class="flex flex-col justify-between p-4 bg-white dark:bg-slate-900 border border-rose-100 dark:border-rose-950/20 rounded-2xl shadow-xs hover:translate-y-[-2px] transition-transform">
                    <span class="text-xs font-bold uppercase tracking-wider text-rose-500">Vắng mặt</span>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ stats?.absent }}</span>
                        <div class="h-8 w-8 rounded-lg bg-rose-50 dark:bg-rose-950/40 flex items-center justify-center text-rose-600 font-bold text-sm">🚫</div>
                    </div>
                </div>
            </div>

            <!-- Admin Tabs Switcher -->
            <div class="flex p-1 bg-slate-100 dark:bg-slate-900 border rounded-xl w-fit print:hidden">
                <button
                    type="button"
                    @click="activeAdminTab = 'attendance'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'attendance'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Nhật ký chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'roster'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'roster'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Roster toàn hệ thống
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'register'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'register'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Đăng ký ca rảnh
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'settings'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'settings'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Cài đặt chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'swaps'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'swaps'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Duyệt đổi ca
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'analytics'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'analytics'
                            ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    Báo cáo & Phân tích
                </button>
            </div>

            <!-- Tab 1: Attendance Logs -->
            <div v-if="activeAdminTab === 'attendance'">
                <AdminAttendanceLogs
                    :assignments="assignments || []"
                    :shifts="shifts"
                    :selected-date="adminDate"
                    @open-override="openOverrideModal"
                    @toggle-leader="toggleShiftLeader"
                    @change-date="handleDateChange"
                    @refresh="refreshAdminData"
                />
            </div>

            <!-- Tab 2: Weekly Roster -->
            <div v-else-if="activeAdminTab === 'roster'">
                <AdminWeeklyRoster
                    :weekly-assignments="weeklyAssignments"
                />
            </div>

            <!-- Tab 3: Shift Registrations -->
            <div v-else-if="activeAdminTab === 'register'">
                <AdminShiftRegistrations
                    :registrations="registrations"
                    :shifts="shifts"
                />
            </div>

            <!-- Tab 4: Timekeeping Settings -->
            <div v-else-if="activeAdminTab === 'settings'">
                <AdminTimekeepingSettings
                    :restaurant-settings="restaurantSettings"
                    :gps-settings="gpsSettings"
                    :qr-settings="qrSettings"
                />
            </div>

            <!-- Tab 5: Admin Shift Swap Approvals -->
            <div v-else-if="activeAdminTab === 'swaps'">
                <AdminShiftSwapApprovals
                    :all-pending-swaps="allPendingSwaps"
                />
            </div>

            <!-- Tab 6: Attendance & Payroll Analytics -->
            <div v-else-if="activeAdminTab === 'analytics'">
                <AdminAttendanceAnalytics
                    :monthly-assignments="monthlyAssignments"
                    :shifts="shifts"
                />
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. STAFF CHRONO TIME CLOCK VIEW (EMPLOYEE) -->
        <!-- ========================================== -->
        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Interactive Live Time Clock Card -->
            <div class="lg:col-span-1">
                <StaffTimeClock
                    :today-active-assignment="todayActiveAssignment"
                    :current-time="currentTime"
                    @check-in="openCheckInFlow"
                />
            </div>

            <!-- Employee Weekly Shift Roster -->
            <div class="lg:col-span-2">
                <div class="flex p-1 bg-slate-100 dark:bg-slate-900 border rounded-xl w-fit mb-4">
                    <button
                        type="button"
                        @click="activeStaffTab = 'roster'"
                        :class="[
                            'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                            activeStaffTab === 'roster'
                                ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                        ]"
                    >
                        Lịch làm việc cá nhân
                    </button>
                    <button
                        type="button"
                        @click="activeStaffTab = 'register'"
                        :class="[
                            'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                            activeStaffTab === 'register'
                                ? 'bg-white dark:bg-slate-800 text-indigo-650 dark:text-indigo-400 shadow-sm'
                                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                        ]"
                    >
                        Đăng ký ca làm việc rảnh
                    </button>
                </div>

                <!-- Personal Weekly Roster Tab -->
                <div v-if="activeStaffTab === 'roster'" class="space-y-6">
                    <StaffWeeklyRoster
                        :my-weekly-schedules="myWeeklySchedules"
                        @open-swap="openSwapModal"
                    />

                    <!-- Shift Swap Requests Panel -->
                    <StaffShiftSwapRequests
                        :pending-swap-requests="pendingSwapRequests"
                    />
                </div>

                <!-- Personal Shift Registration Tab -->
                <div v-else-if="activeStaffTab === 'register'">
                    <StaffShiftRegistration
                        :my-registrations="myRegistrations"
                        :shifts="shifts"
                    />
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 3. MODALS & POPUPS -->
        <!-- ========================================== -->
        
        <!-- Admin Manual Override Modal -->
        <AdminOverrideModal
            :is-open="overrideModal"
            :assignment="activeOverrideAssignment"
            :action="overrideAction"
            @close="closeOverrideModal"
            @success="handleOverrideSuccess"
        />

        <!-- Staff Webcam selfie capture check-in modal -->
        <WebcamCheckInModal
            v-if="checkInModalOpen"
            :is-open="checkInModalOpen"
            :gps-settings="gpsSettings"
            :qr-settings="qrSettings"
            @close="checkInModalOpen = false"
            @success="handleCheckInSuccess"
        />

        <!-- Staff peer swap proposal modal -->
        <ShiftSwapProposalModal
            v-if="swapModalOpen"
            :is-open="swapModalOpen"
            :selected-my-shift="selectedMyShift"
            :swappable-shifts="swappableShifts"
            @close="swapModalOpen = false"
            @success="handleSwapSuccess"
        />
    </div>
</template>

<style>
@media print {
    body {
        background-color: white !important;
        color: black !important;
    }
    .print\:hidden {
        display: none !important;
    }
}
</style>
