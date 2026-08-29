<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays } from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';

// Existing Shared Modals & Navigation Drawers
import AdminAttendanceAnalytics from './components/AdminAttendanceAnalytics.vue';
import AdminAttendanceLogs from './components/AdminAttendanceLogs.vue';
import AdminMonthlyShiftsHistory from './components/AdminMonthlyShiftsHistory.vue';
import AdminOverrideModal from './components/AdminOverrideModal.vue';

// Admin Components
import AdminShiftRegistrations from './components/AdminShiftRegistrations.vue';
import AdminShiftSwapApprovals from './components/AdminShiftSwapApprovals.vue';
import AdminTimekeepingSettings from './components/AdminTimekeepingSettings.vue';
import AdminWeeklyRoster from './components/AdminWeeklyRoster.vue';
import ShiftSwapProposalModal from './components/ShiftSwapProposalModal.vue';

// Staff Components
import StaffShiftRegistration from './components/StaffShiftRegistration.vue';
import StaffShiftSwapRequests from './components/StaffShiftSwapRequests.vue';
import StaffTimeClock from './components/StaffTimeClock.vue';
import StaffWeeklyRoster from './components/StaffWeeklyRoster.vue';
import WebcamCheckInModal from './components/WebcamCheckInModal.vue';

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
    status:
        | 'scheduled'
        | 'checked_in'
        | 'completed'
        | 'absent'
        | 'leave_approved';
    is_shift_leader: boolean;
    duration: string | null;
    notes: string | null;
    check_in_photo_path: string | null;
};

type StaffingTip = {
    shift: string;
    branch_name?: string;
    day_of_week?: string;
    date?: string;
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
    employees?: Array<{
        id: number;
        full_name: string;
        job_title: string;
        employee_code: string;
    }>;
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
    monthlyShiftClosings?: any[];
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
let attendanceRefreshInterval: any = null;

const updateClock = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
    currentDate.value = now.toLocaleDateString('vi-VN', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    // Đồng bộ trạng thái để ca mới tự hiện nút check-in khi trang vẫn đang mở.
    if (!props.isAdmin) {
        attendanceRefreshInterval = setInterval(() => {
            router.reload({
                only: ['todayActiveAssignment'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 30000);
    }
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }

    if (attendanceRefreshInterval) {
        clearInterval(attendanceRefreshInterval);
    }
});

const activeStaffTab = ref<'roster' | 'register'>('roster');
const activeAdminTab = ref<
    | 'attendance'
    | 'roster'
    | 'register'
    | 'settings'
    | 'swaps'
    | 'analytics'
    | 'monthly_shifts'
>('attendance');

// --- OVERRIDE MODAL CONTROL ---
const overrideModal = ref(false);
const activeOverrideAssignment = ref<Assignment | null>(null);
const overrideAction = ref<'check_in' | 'check_out' | 'absent'>('check_in');

const openOverrideModal = (
    assignment: Assignment,
    action: 'check_in' | 'check_out' | 'absent',
) => {
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
    if (!props.weeklyAssignments || !props.employee) {
        return [];
    }

    return props.weeklyAssignments.filter(
        (wa) => wa.employee_id !== props.employee?.id,
    );
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
    router.post(
        '/schedules/toggle-leader',
        {
            assignment_id: assignmentId,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Cập nhật vai trò Trưởng ca thành công!'),
                );
            },
        },
    );
};

// --- DATA FILTER ACTIONS ---
const adminDate = ref(
    props.selectedDate || new Date().toISOString().split('T')[0],
);

const handleDateChange = (newDate: string) => {
    adminDate.value = newDate;
    router.get(
        '/schedules',
        { date: adminDate.value },
        { preserveState: true },
    );
};

const refreshAdminData = () => {
    router.get(
        '/schedules',
        { date: adminDate.value },
        { preserveState: true },
    );
};
</script>

<template>
    <Head title="Chấm Công & Lịch Trực" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6 print:p-0">
        <!-- HEADER -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between print:hidden"
        >
            <div class="flex items-center gap-3">
                <div
                    class="dark:bg-indigo-955 text-indigo-650 flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 bg-indigo-50/50 dark:text-indigo-400"
                >
                    <CalendarDays class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Quản Lý Chấm Công & Ca Trực
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            isAdmin
                                ? 'Theo dõi chấm công thời gian thực, quản lý ca trực nhân viên và duyệt báo cáo chuyên nghiệp.'
                                : 'Theo dõi lịch làm việc cá nhân hàng tuần và thực hiện chấm công vào ca/ra ca.'
                        }}
                    </p>
                </div>
            </div>

            <!-- TIME CLOCK DIGITAL DISPLAY -->
            <div class="flex items-center gap-4">
                <div
                    class="flex flex-col justify-center rounded-2xl border bg-slate-50 px-5 py-3 text-right shadow-xs dark:bg-slate-900/50"
                >
                    <span
                        class="dark:text-slate-555 font-mono text-sm text-[9px] font-bold tracking-widest text-slate-400 uppercase"
                        >{{ currentDate }}</span
                    >
                    <span
                        class="font-mono text-2xl font-black tracking-tight text-indigo-600 dark:text-indigo-400"
                        >{{ currentTime }}</span
                    >
                </div>
            </div>
        </div>

        <!-- PRINT ONLY HEADER -->
        <div class="mb-6 hidden border-b pb-4 print:block">
            <h1 class="text-center text-xl font-bold uppercase">
                BẢNG DANH SÁCH CHẤM CÔNG NHÂN VIÊN
            </h1>
            <p class="mt-1 text-center text-xs text-slate-500">
                Ngày chấm công: {{ adminDate }} | Đơn vị: Hệ thống nhà hàng
                Aventura
            </p>
        </div>

        <!-- ========================================== -->
        <!-- 1. ADMIN MONITORING VIEW (OWNER / MANAGER) -->
        <!-- ========================================== -->
        <div v-if="isAdmin" class="space-y-6">
            <!-- AI Staffing Suggestions Banner -->
            <div
                v-if="staffingTips && staffingTips.length > 0"
                class="space-y-2"
            >
                <p
                    class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                >
                    <span class="text-sm">⚡</span> Gợi ý AI — Tối ưu nhân sự
                    theo ca
                </p>
                <div
                    v-for="(tip, i) in staffingTips"
                    :key="i"
                    :class="[
                        'flex items-start gap-3 rounded-xl border p-3 text-xs',
                        tip.level === 'warning'
                            ? 'border-amber-200 bg-amber-50 dark:border-amber-800/40 dark:bg-amber-950/20'
                            : 'border-blue-200 bg-blue-50 dark:border-blue-800/40 dark:bg-blue-950/20',
                    ]"
                >
                    <span class="mt-0.5 shrink-0 text-base">{{
                        tip.level === 'warning' ? '⚠️' : '💡'
                    }}</span>
                    <p
                        :class="
                            tip.level === 'warning'
                                ? 'text-amber-800 dark:text-amber-300'
                                : 'text-blue-800 dark:text-blue-300'
                        "
                        v-html="tip.message"
                    />
                    <span
                        class="ml-auto shrink-0 text-[10px] font-bold"
                        :class="
                            tip.level === 'warning'
                                ? 'text-amber-600'
                                : 'text-blue-500'
                        "
                    >
                        {{ tip.pct }}% DT
                    </span>
                </div>
            </div>

            <!-- Attendance Summary Stats Panel -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-5 print:hidden">
                <!-- Total Scheduled -->
                <div
                    class="flex flex-col justify-between rounded-2xl border bg-white p-4 shadow-xs transition-transform hover:translate-y-[-2px] dark:bg-slate-900"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Tổng lịch trực</span
                    >
                    <div class="mt-2 flex items-center justify-between">
                        <span
                            class="text-3xl font-black text-slate-800 dark:text-slate-100"
                            >{{ stats?.total }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-sm font-bold text-slate-500 dark:bg-slate-800"
                        >
                            📅
                        </div>
                    </div>
                </div>

                <!-- Working -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-emerald-100 bg-white p-4 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20 dark:bg-slate-900"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Đang làm việc</span
                    >
                    <div class="mt-2 flex items-center justify-between">
                        <span
                            class="text-3xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ stats?.working }}</span
                        >
                        <div
                            class="flex h-8 w-8 animate-pulse items-center justify-center rounded-lg bg-emerald-50 text-sm font-bold text-emerald-600 dark:bg-emerald-950/40"
                        >
                            ⏰
                        </div>
                    </div>
                </div>

                <!-- Completed -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-indigo-100 bg-white p-4 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20 dark:bg-slate-900"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Hoàn thành ca</span
                    >
                    <div class="mt-2 flex items-center justify-between">
                        <span
                            class="text-3xl font-black text-indigo-600 dark:text-indigo-400"
                            >{{ stats?.completed }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-sm font-bold text-indigo-600 dark:bg-indigo-950/40"
                        >
                            ✅
                        </div>
                    </div>
                </div>

                <!-- Leave -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-4 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-slate-800 dark:bg-slate-900"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                        >Nghỉ phép duyệt</span
                    >
                    <div class="mt-2 flex items-center justify-between">
                        <span
                            class="text-3xl font-black text-slate-700 dark:text-slate-300"
                            >{{ stats?.leave }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-sm font-bold text-slate-600 dark:bg-slate-800"
                        >
                            👤
                        </div>
                    </div>
                </div>

                <!-- Absent -->
                <div
                    class="flex flex-col justify-between rounded-2xl border border-rose-100 bg-white p-4 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-rose-950/20 dark:bg-slate-900"
                >
                    <span
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >Vắng mặt</span
                    >
                    <div class="mt-2 flex items-center justify-between">
                        <span
                            class="text-3xl font-black text-rose-600 dark:text-rose-400"
                            >{{ stats?.absent }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-sm font-bold text-rose-600 dark:bg-rose-950/40"
                        >
                            🚫
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Tabs Switcher -->
            <div
                class="flex w-fit rounded-xl border bg-slate-100 p-1 dark:bg-slate-900 print:hidden"
            >
                <button
                    type="button"
                    @click="activeAdminTab = 'attendance'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'attendance'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Nhật ký chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'roster'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'roster'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Roster toàn hệ thống
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'register'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'register'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Đăng ký ca rảnh
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'settings'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'settings'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Cài đặt chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'swaps'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'swaps'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Duyệt đổi ca
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'monthly_shifts'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'monthly_shifts'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                    ]"
                >
                    Ca đã diễn ra trong tháng
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'analytics'"
                    :class="[
                        'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                        activeAdminTab === 'analytics'
                            ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                            : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
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
                <AdminWeeklyRoster :weekly-assignments="weeklyAssignments" />
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
                <AdminShiftSwapApprovals :all-pending-swaps="allPendingSwaps" />
            </div>

            <!-- Tab 6: Attendance & Payroll Analytics -->
            <div v-else-if="activeAdminTab === 'analytics'">
                <AdminAttendanceAnalytics
                    :monthly-assignments="monthlyAssignments"
                    :shifts="shifts"
                />
            </div>

            <!-- Tab 7: Executed Monthly Shifts History Audit -->
            <div v-else-if="activeAdminTab === 'monthly_shifts'">
                <AdminMonthlyShiftsHistory
                    :monthly-assignments="monthlyAssignments || []"
                    :monthly-shift-closings="monthlyShiftClosings || []"
                    :shifts="shifts"
                />
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2. STAFF CHRONO TIME CLOCK VIEW (EMPLOYEE) -->
        <!-- ========================================== -->
        <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
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
                <div
                    class="mb-4 flex w-fit rounded-xl border bg-slate-100 p-1 dark:bg-slate-900"
                >
                    <button
                        type="button"
                        @click="activeStaffTab = 'roster'"
                        :class="[
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                            activeStaffTab === 'roster'
                                ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
                        ]"
                    >
                        Lịch làm việc cá nhân
                    </button>
                    <button
                        type="button"
                        @click="activeStaffTab = 'register'"
                        :class="[
                            'flex cursor-pointer items-center gap-1.5 rounded-lg px-4 py-1.5 text-xs font-bold transition-all duration-150',
                            activeStaffTab === 'register'
                                ? 'text-indigo-650 bg-white shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                : 'dark:hover:text-slate-350 text-slate-500 hover:text-slate-700',
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
