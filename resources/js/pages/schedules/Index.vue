<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Clock,
    CheckCircle2,
    AlertCircle,
    Sparkles,
    UserCheck,
    ShieldCheck,
    Calendar,
    Users,
    LogIn,
    LogOut,
    Check,
    Ban,
    Search,
    ArrowLeft,
    Printer,
    RefreshCw,
    HelpCircle,
    MessageSquare,
    ShieldAlert,
    Send,
    FileText,
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
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
    duration: string | null;
    notes: string | null;
};

type RosterAssignment = {
    day: string;
    employee_name: string;
    shift_name: string;
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
    weeklyAssignments?: RosterAssignment[];
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
    employees?: Array<{
        id: number;
        full_name: string;
        job_title: string;
        employee_code: string;
    }>;
    // Staff specific props
    myWeeklySchedules?: Array<{
        id: number;
        date: string;
        day: string;
        day_vn: string;
        shift_name: string;
        shift_time: string;
        check_in_at: string | null;
        check_out_at: string | null;
        status: string;
    }>;
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
    leaveRequests?: Array<{
        id: number;
        leave_type: string;
        start_date: string;
        end_date: string;
        reason: string;
        status: string;
        created_at: string;
    }>;
    myEmployeeId?: number;
};

const props = defineProps<PropType>();

// --- REAL-TIME LIVE CLOCK ---
const currentTime = ref('');
const currentDate = ref('');
let clockInterval: any = null;

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

// --- STAFF PORTAL: LIVE DURATION TIMER ---
const liveDuration = ref('00:00:00');
let durationInterval: any = null;

const parseDateTimeStr = (str: string) => {
    // format expected: "H:i:s d/m/Y"
    const [time, date] = str.split(' ');
    const [h, i, s] = time.split(':').map(Number);
    const [d, m, y] = date.split('/').map(Number);

    return new Date(y, m - 1, d, h, i, s);
};

const startLiveDurationTimer = (checkInStr: string) => {
    const checkIn = parseDateTimeStr(checkInStr);

    if (durationInterval) {
        clearInterval(durationInterval);
    }

    const updateTimer = () => {
        const diffMs = new Date().getTime() - checkIn.getTime();

        if (diffMs < 0) {
            liveDuration.value = '00:00:00';

            return;
        }

        const totalSecs = Math.floor(diffMs / 1000);
        const hrs = Math.floor(totalSecs / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        const secs = totalSecs % 60;
        liveDuration.value = `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };

    updateTimer();
    durationInterval = setInterval(updateTimer, 1000);
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    if (
        !props.isAdmin &&
        props.todayActiveAssignment &&
        props.todayActiveAssignment.status === 'checked_in' &&
        props.todayActiveAssignment.check_in_at
    ) {
        startLiveDurationTimer(props.todayActiveAssignment.check_in_at);
    }
});

onUnmounted(() => {
    if (clockInterval) {
        clearInterval(clockInterval);
    }

    if (durationInterval) {
        clearInterval(durationInterval);
    }
});

// --- ADMIN PORTAL STATE ---
const adminDate = ref(
    props.selectedDate || new Date().toISOString().split('T')[0],
);
const searchQuery = ref('');
const overrideModal = ref(false);
const activeOverrideAssignment = ref<Assignment | null>(null);
const overrideAction = ref<'check_in' | 'check_out' | 'absent'>('check_in');
const overrideNotes = ref('');
const processingOverride = ref(false);

const weekDays = [
    { key: 'Monday', label: 'Thứ Hai' },
    { key: 'Tuesday', label: 'Thứ Ba' },
    { key: 'Wednesday', label: 'Thứ Tư' },
    { key: 'Thursday', label: 'Thứ Năm' },
    { key: 'Friday', label: 'Thứ Sáu' },
    { key: 'Saturday', label: 'Thứ Bảy' },
    { key: 'Sunday', label: 'Chủ Nhật' },
];

const weekDaysWithDates = computed(() => {
    const current = new Date();
    const day = current.getDay();
    const diff = current.getDate() - day + (day === 0 ? -6 : 1);
    const monday = new Date(current.setDate(diff));

    return weekDays.map((wd, index) => {
        const nextDay = new Date(monday);
        nextDay.setDate(monday.getDate() + index);
        const dd = String(nextDay.getDate()).padStart(2, '0');
        const mm = String(nextDay.getMonth() + 1).padStart(2, '0');

        return {
            ...wd,
            dateLabel: `${dd}/${mm}`,
            fullLabel: `${wd.label} (${dd}/${mm})`,
        };
    });
});

const openOverrideModal = (
    assignment: Assignment,
    action: 'check_in' | 'check_out' | 'absent',
) => {
    activeOverrideAssignment.value = assignment;
    overrideAction.value = action;
    overrideNotes.value =
        action === 'check_in'
            ? 'Check-in hộ do nhân viên quên'
            : action === 'check_out'
              ? 'Check-out hộ do nhân viên quên'
              : 'Vắng mặt không lý do';
    overrideModal.value = true;
};

const closeOverrideModal = () => {
    overrideModal.value = false;
    activeOverrideAssignment.value = null;
    overrideNotes.value = '';
};

// --- ACTIONS ---
const handleCheckIn = () => {
    router.post(
        '/schedules/check-in',
        {},
        {
            onSuccess: (page: any) => {
                // Start duration timer immediately if active assignment checked-in successfully
                const freshAssign = page.props.todayActiveAssignment as any;

                if (
                    freshAssign &&
                    freshAssign.status === 'checked_in' &&
                    freshAssign.check_in_at
                ) {
                    startLiveDurationTimer(freshAssign.check_in_at);
                }
            },
        },
    );
};

const handleCheckOut = () => {
    if (
        confirm(
            'Bạn chắc chắn muốn check-out ra ca trực hiện tại? Hệ thống sẽ ghi nhận giờ chấm công của bạn.',
        )
    ) {
        router.post(
            '/schedules/check-out',
            {},
            {
                onFinish: () => {
                    if (durationInterval) {
                        clearInterval(durationInterval);
                    }
                },
            },
        );
    }
};

const submitAdminOverride = () => {
    if (!activeOverrideAssignment.value) {
        return;
    }

    processingOverride.value = true;

    let url = '/schedules/check-in-employee';

    if (overrideAction.value === 'check_out') {
        url = '/schedules/check-out-employee';
    }

    if (overrideAction.value === 'absent') {
        url = '/schedules/absent-employee';
    }

    router.post(
        url,
        {
            assignment_id: activeOverrideAssignment.value.id,
            notes: overrideNotes.value,
        },
        {
            onSuccess: () => {
                closeOverrideModal();
            },
            onFinish: () => {
                processingOverride.value = false;
            },
        },
    );
};

const refreshAdminData = () => {
    router.get(
        '/schedules',
        { date: adminDate.value },
        { preserveState: true },
    );
};

const handleDateChange = () => {
    router.get('/schedules', { date: adminDate.value });
};

// --- EMPLOYEE SELF SERVICE PORTAL STATE & ACTIONS ---
const selfServiceTab = ref<'register' | 'leave' | 'complaint'>('register');

// Shift Registration Form state
const regDate = ref(new Date().toISOString().split('T')[0]);
const regShiftId = ref('');
const regProcessing = ref(false);
const regErrors = ref<Record<string, string>>({});

const submitShiftRegistration = () => {
    if (!regDate.value || !regShiftId.value) {
        return;
    }

    regProcessing.value = true;
    regErrors.value = {};
    router.post(
        '/schedules/register',
        {
            scheduled_date: regDate.value,
            shift_id: regShiftId.value,
        },
        {
            onSuccess: () => {
                regErrors.value = {};
            },
            onError: (err: any) => {
                regErrors.value = err;
            },
            onFinish: () => {
                regProcessing.value = false;
            },
        },
    );
};

// Leave Request Form state
const leaveType = ref('emergency');
const leaveStartDate = ref(new Date().toISOString().split('T')[0]);
const leaveEndDate = ref(new Date().toISOString().split('T')[0]);
const leaveReason = ref('');
const leaveProcessing = ref(false);
const leaveErrors = ref<Record<string, string>>({});

const submitLeaveRequest = () => {
    if (!leaveStartDate.value || !leaveEndDate.value) {
        return;
    }

    leaveProcessing.value = true;
    leaveErrors.value = {};
    router.post(
        '/employees/leaves',
        {
            employee_id: props.myEmployeeId,
            leave_type: leaveType.value,
            start_date: leaveStartDate.value,
            end_date: leaveEndDate.value,
            reason: leaveReason.value,
        },
        {
            onSuccess: () => {
                leaveReason.value = '';
                leaveErrors.value = {};
            },
            onError: (err: any) => {
                leaveErrors.value = err;
            },
            onFinish: () => {
                leaveProcessing.value = false;
            },
        },
    );
};

// Complaint Form state
const complaintEmployeeId = ref('');
const complaintViolationType = ref('');
const complaintOccurredAt = ref(new Date().toISOString().split('T')[0]);
const complaintDescription = ref('');
const complaintProcessing = ref(false);
const complaintErrors = ref<Record<string, string>>({});

const submitComplaint = () => {
    if (
        !complaintEmployeeId.value ||
        !complaintViolationType.value ||
        !complaintOccurredAt.value ||
        !complaintDescription.value
    ) {
        return;
    }

    complaintProcessing.value = true;
    complaintErrors.value = {};
    router.post(
        '/violations',
        {
            employee_id: complaintEmployeeId.value,
            violation_type: complaintViolationType.value,
            occurred_at: complaintOccurredAt.value,
            description: complaintDescription.value,
            is_anonymous: true,
        },
        {
            onSuccess: () => {
                complaintEmployeeId.value = '';
                complaintViolationType.value = '';
                complaintDescription.value = '';
                complaintErrors.value = {};
            },
            onError: (err: any) => {
                complaintErrors.value = err;
            },
            onFinish: () => {
                complaintProcessing.value = false;
            },
        },
    );
};

// --- LATE INDICATOR ---
function lateMinutes(a: Assignment): number | null {
    if (!a.check_in_at || !props.shifts) {
        return null;
    }

    const shift = props.shifts.find((s) => s.id === a.shift_id);

    if (!shift) {
        return null;
    }

    // shift.start = "06:00", a.scheduled_date = "2026-05-29"
    const shiftStart = new Date(`${a.scheduled_date}T${shift.start}`);
    const graceEnd = new Date(shiftStart.getTime() + 5 * 60_000); // 5 min grace
    // check_in_at from backend is "H:i:s d/m/Y"
    const checkIn = parseDateTimeStr(a.check_in_at);
    const diffMin = Math.round(
        (checkIn.getTime() - graceEnd.getTime()) / 60_000,
    );

    return diffMin > 0 ? diffMin : null;
}

// --- COMPUTED PROPERTIES ---
const filteredAssignments = computed(() => {
    if (!props.assignments) {
        return [];
    }

    if (!searchQuery.value.trim()) {
        return props.assignments;
    }

    const query = searchQuery.value.toLowerCase().trim();

    return props.assignments.filter(
        (a) =>
            a.employee_name.toLowerCase().includes(query) ||
            a.employee_code.toLowerCase().includes(query) ||
            a.shift_name.toLowerCase().includes(query),
    );
});

// Color Maps
const statusLabels: Record<string, string> = {
    scheduled: 'Chưa vào ca',
    checked_in: 'Đang làm việc',
    completed: 'Đã hoàn thành ca',
    absent: 'Vắng mặt',
    leave_approved: 'Nghỉ phép',
};

const statusColors: Record<string, string> = {
    scheduled:
        'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30',
    checked_in:
        'bg-emerald-50 text-emerald-600 border border-emerald-200 animate-pulse dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30',
    completed:
        'bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30',
    absent: 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30',
    leave_approved:
        'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-950/20 dark:text-slate-400 dark:border-slate-800',
};

const printRoster = () => {
    window.print();
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
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
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
            <div
                class="flex flex-col justify-center rounded-2xl border bg-slate-50 px-5 py-3 text-right shadow-xs dark:bg-slate-900/50"
            >
                <span
                    class="font-mono text-sm text-[9px] font-bold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                    >{{ currentDate }}</span
                >
                <span
                    class="font-mono text-2xl font-black tracking-tight text-indigo-600 dark:text-indigo-400"
                    >{{ currentTime }}</span
                >
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
                <Card
                    class="shadow-xs transition-transform hover:translate-y-[-2px]"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >Tổng lịch trực</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-3xl font-black text-slate-800 dark:text-slate-100"
                            >{{ stats?.total }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"
                        >
                            <Calendar class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Working -->
                <Card
                    class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                            >Đang làm việc</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-3xl font-black text-emerald-600 dark:text-emerald-400"
                            >{{ stats?.working }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                        >
                            <Clock class="size-4 animate-pulse" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Completed -->
                <Card
                    class="border-indigo-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                            >Hoàn thành ca</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-3xl font-black text-indigo-600 dark:text-indigo-400"
                            >{{ stats?.completed }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                        >
                            <CheckCircle2 class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Leave -->
                <Card
                    class="border-slate-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-slate-800"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                            >Nghỉ phép duyệt</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-3xl font-black text-slate-700 dark:text-slate-300"
                            >{{ stats?.leave }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800"
                        >
                            <UserCheck class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Absent -->
                <Card
                    class="border-rose-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-rose-950/20"
                >
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                            >Vắng mặt</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span
                            class="text-3xl font-black text-rose-600 dark:text-rose-400"
                            >{{ stats?.absent }}</span
                        >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                        >
                            <Ban class="size-4" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Attendance Controls & Table -->
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-col gap-4 border-b pb-3 md:flex-row md:items-center md:justify-between print:hidden"
                >
                    <div class="flex-1">
                        <CardTitle class="flex items-center gap-1.5 text-base">
                            <Users class="size-5 text-indigo-600" />
                            Nhật Ký Chấm Công Chi Tiết Trong Ngày
                        </CardTitle>
                        <CardDescription
                            >Bảng giám sát trực quan các lượt bấm giờ vào ca và
                            ra ca thực tế của nhân sự.</CardDescription
                        >
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Date picker selector -->
                        <div class="flex items-center gap-1.5">
                            <Label
                                for="admin-date"
                                class="shrink-0 text-xs font-semibold text-slate-600"
                                >Chọn ngày:</Label
                            >
                            <Input
                                id="admin-date"
                                type="date"
                                v-model="adminDate"
                                @change="handleDateChange"
                                class="h-8 w-36 bg-white py-1 text-xs font-semibold"
                            />
                        </div>

                        <!-- Print list -->
                        <Button
                            @click="printRoster"
                            variant="outline"
                            size="sm"
                            class="flex h-8 shrink-0 items-center gap-1 border-slate-200 text-xs text-slate-600"
                        >
                            <Printer class="size-3.5" />
                            In/Xuất báo cáo
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <!-- Search input -->
                    <div
                        class="flex items-center gap-2 border-b bg-slate-50/50 p-4 dark:bg-slate-900/30 print:hidden"
                    >
                        <div class="relative w-full max-w-sm">
                            <Search
                                class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                            />
                            <Input
                                type="text"
                                placeholder="Tìm theo tên nhân viên, mã số, ca trực..."
                                v-model="searchQuery"
                                class="h-9 bg-white pl-8 text-xs"
                            />
                        </div>
                        <Button
                            @click="refreshAdminData"
                            variant="ghost"
                            size="icon"
                            class="h-9 w-9 shrink-0 text-slate-500 hover:text-indigo-600"
                            title="Tải lại dữ liệu"
                        >
                            <RefreshCw class="size-4" />
                        </Button>
                    </div>

                    <!-- Attendance Registry Table -->
                    <div
                        v-if="filteredAssignments.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                                >
                                    <th class="p-3.5">Nhân viên</th>
                                    <th class="p-3.5">Ca trực xếp lịch</th>
                                    <th class="p-3.5">Giờ hành chính</th>
                                    <th class="p-3.5">Thực tế Vào Ca</th>
                                    <th class="p-3.5">Thực tế Ra Ca</th>
                                    <th class="p-3.5">Số giờ làm</th>
                                    <th class="p-3.5">Trạng thái</th>
                                    <th class="p-3.5 text-right print:hidden">
                                        Thao tác phê duyệt
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="a in filteredAssignments"
                                    :key="a.id"
                                    class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                >
                                    <td class="p-3.5">
                                        <div
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ a.employee_name }}
                                        </div>
                                        <div
                                            class="mt-0.5 text-[10px] text-slate-400"
                                        >
                                            {{ a.employee_code }} ·
                                            {{ a.job_title }}
                                        </div>
                                    </td>
                                    <td class="p-3.5">
                                        <span
                                            class="rounded bg-indigo-50 px-2 py-0.5 font-mono font-semibold text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
                                            >{{ a.shift_name }}</span
                                        >
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-500">
                                        {{ a.shift_time }}
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-300"
                                    >
                                        <div
                                            v-if="a.check_in_at"
                                            class="flex flex-col gap-0.5"
                                        >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <span
                                                    class="size-1.5 rounded-full bg-emerald-600"
                                                />
                                                <span class="font-mono">{{
                                                    a.check_in_at.split(' ')[0]
                                                }}</span>
                                            </div>
                                            <span
                                                v-if="lateMinutes(a)"
                                                class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-600 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                                                >⚠ Trễ
                                                {{ lateMinutes(a) }} phút</span
                                            >
                                        </div>
                                        <div
                                            v-else
                                            class="text-slate-300 dark:text-slate-700"
                                        >
                                            —
                                        </div>
                                    </td>
                                    <td
                                        class="p-3.5 font-mono text-slate-600 dark:text-slate-300"
                                    >
                                        <div
                                            v-if="a.check_out_at"
                                            class="flex items-center gap-1"
                                        >
                                            <span
                                                class="size-1.5 rounded-full bg-indigo-600"
                                            />
                                            {{ a.check_out_at.split(' ')[0] }}
                                        </div>
                                        <div
                                            v-else-if="
                                                a.status === 'checked_in'
                                            "
                                            class="animate-pulse font-bold text-emerald-500 italic"
                                        >
                                            Đang làm...
                                        </div>
                                        <div
                                            v-else
                                            class="text-slate-300 dark:text-slate-700"
                                        >
                                            —
                                        </div>
                                    </td>
                                    <td
                                        class="p-3.5 font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{ a.duration || '—' }}
                                        <span
                                            v-if="a.notes"
                                            class="mt-0.5 block max-w-[150px] truncate font-sans text-[9px] font-normal text-amber-600 italic dark:text-amber-500"
                                            :title="a.notes"
                                        >
                                            * {{ a.notes }}
                                        </span>
                                    </td>
                                    <td class="p-3.5">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="statusColors[a.status]"
                                        >
                                            {{ statusLabels[a.status] }}
                                        </span>
                                    </td>
                                    <td
                                        class="flex items-center justify-end gap-1.5 p-3.5 text-right print:hidden"
                                    >
                                        <!-- Actions based on status -->
                                        <template
                                            v-if="a.status === 'scheduled'"
                                        >
                                            <button
                                                @click="
                                                    openOverrideModal(
                                                        a,
                                                        'check_in',
                                                    )
                                                "
                                                class="inline-flex cursor-pointer items-center justify-center rounded bg-emerald-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition hover:bg-emerald-700 active:scale-95"
                                                title="Check-in hộ nhân sự"
                                            >
                                                Check-in hộ
                                            </button>
                                            <button
                                                @click="
                                                    openOverrideModal(
                                                        a,
                                                        'absent',
                                                    )
                                                "
                                                class="inline-flex cursor-pointer items-center justify-center rounded border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-600 transition hover:bg-rose-100 active:scale-95"
                                                title="Báo vắng trực"
                                            >
                                                Báo Vắng
                                            </button>
                                        </template>
                                        <template
                                            v-else-if="
                                                a.status === 'checked_in'
                                            "
                                        >
                                            <button
                                                @click="
                                                    openOverrideModal(
                                                        a,
                                                        'check_out',
                                                    )
                                                "
                                                class="inline-flex cursor-pointer items-center justify-center rounded bg-indigo-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition hover:bg-indigo-700 active:scale-95"
                                                title="Check-out hộ nhân sự"
                                            >
                                                Check-out hộ
                                            </button>
                                        </template>
                                        <span
                                            v-else
                                            class="text-[10px] font-semibold text-slate-400 italic"
                                            >Đã chốt ca</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-16 text-center">
                        <div
                            class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-900"
                        >
                            <Calendar class="size-6" />
                        </div>
                        <p class="text-sm font-semibold">
                            Không tìm thấy ca xếp trực nào trong ngày
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Nhà hàng của bạn không có lịch xếp ca làm việc hoặc
                            không trùng điều kiện tìm kiếm.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Global Weekly Roster Overview (For reference) -->
            <Card class="shadow-sm print:hidden">
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <CalendarDays class="size-5" />
                            Roster Toàn Hệ Thống Tuần Này
                        </CardTitle>
                        <CardDescription
                            >Tổng quan nhanh phân công ca trực từ Thứ 2 đến Chủ
                            nhật của mọi nhân viên.</CardDescription
                        >
                    </div>
                    <a
                        href="/employees"
                        class="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 hover:underline"
                    >
                        Đi tới xếp lịch <ArrowLeft class="size-3 rotate-180" />
                    </a>
                </CardHeader>
                <CardContent class="p-4">
                    <div
                        class="overflow-hidden rounded-2xl border bg-white dark:bg-slate-950"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                                >
                                    <th class="w-[120px] border-r p-3.5">
                                        Thứ trong tuần
                                    </th>
                                    <th class="p-3.5">
                                        Danh sách phân ca nhân viên
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="day in weekDaysWithDates"
                                    :key="day.key"
                                    class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                                >
                                    <td
                                        class="border-r bg-slate-50/30 p-3.5 font-bold text-slate-700 dark:text-slate-300"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span>{{ day.label }}</span>
                                            <span
                                                class="font-mono text-[10px] font-medium text-slate-400 dark:text-slate-500"
                                                >({{ day.dateLabel }})</span
                                            >
                                        </div>
                                    </td>
                                    <td
                                        class="flex flex-wrap items-center gap-2 p-3.5"
                                    >
                                        <div
                                            v-for="(
                                                s, idx
                                            ) in weeklyAssignments?.filter(
                                                (sc) => sc.day === day.key,
                                            )"
                                            :key="'s-' + idx"
                                            class="group/assign relative flex items-center gap-1.5 rounded-lg border border-indigo-100 bg-indigo-50/30 px-2.5 py-1.5 dark:border-indigo-900/40 dark:bg-indigo-950/20"
                                        >
                                            <span
                                                class="size-1.5 rounded-full bg-indigo-600 dark:bg-indigo-400"
                                            />
                                            <span
                                                class="text-[10px] font-bold text-slate-800 dark:text-slate-200"
                                                >{{ s.employee_name }}</span
                                            >
                                            <span
                                                class="font-mono text-[9px] text-slate-400"
                                                >({{ s.shift_name }})</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                !weeklyAssignments?.some(
                                                    (sc) => sc.day === day.key,
                                                )
                                            "
                                            class="text-[10px] text-slate-400 italic"
                                        >
                                            Không có ca xếp
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ========================================== -->
        <!-- 2. STAFF CHRONO TIME CLOCK VIEW (EMPLOYEE) -->
        <!-- ========================================== -->
        <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Interactive Live Time Clock Card (Left / Span 1) -->
            <div class="lg:col-span-1">
                <Card
                    class="flex h-full flex-col justify-between border-indigo-100 bg-gradient-to-b from-indigo-50/20 to-white shadow-md dark:from-slate-900/50 dark:to-slate-900"
                >
                    <CardHeader class="border-b pb-3 text-center">
                        <CardTitle
                            class="flex items-center justify-center gap-1.5 text-base text-indigo-600"
                        >
                            <Clock class="size-5" />
                            Giao Diện Bấm Giờ Chấm Công
                        </CardTitle>
                        <CardDescription
                            >Bấm giờ để chấm công thời gian làm việc thực tế cho
                            ca trực của bạn hôm nay.</CardDescription
                        >
                    </CardHeader>

                    <CardContent
                        class="flex flex-1 flex-col items-center justify-center space-y-6 p-6"
                    >
                        <!-- Clock Display Face -->
                        <div
                            class="relative flex h-44 w-44 flex-col items-center justify-center rounded-full border-4 border-indigo-200 bg-white shadow-inner dark:bg-slate-950"
                        >
                            <div
                                class="absolute inset-0 rounded-full bg-indigo-500/5 blur-xs"
                            ></div>
                            <span
                                class="font-mono text-xs text-[9px] font-bold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                                >CA HÔM NAY</span
                            >
                            <span
                                class="mt-1 font-mono text-[26px] leading-none font-black tracking-tight text-indigo-600 dark:text-indigo-400"
                                >{{ currentTime }}</span
                            >
                            <!-- Live Status Indicator -->
                            <div class="mt-2 shrink-0">
                                <span
                                    v-if="
                                        todayActiveAssignment?.status ===
                                        'checked_in'
                                    "
                                    class="animate-pulse rounded-full bg-emerald-500 px-2 py-0.5 text-[9px] font-extrabold text-white uppercase"
                                >
                                    Đang làm việc
                                </span>
                                <span
                                    v-else-if="
                                        todayActiveAssignment?.status ===
                                        'completed'
                                    "
                                    class="rounded-full bg-indigo-500 px-2 py-0.5 text-[9px] font-extrabold text-white uppercase"
                                >
                                    Đã hoàn thành ca
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-slate-300 px-2 py-0.5 text-[9px] font-extrabold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-400"
                                >
                                    Chờ vào ca
                                </span>
                            </div>
                        </div>

                        <!-- Active Shift Info Roster Block -->
                        <div
                            v-if="todayActiveAssignment"
                            class="w-full rounded-2xl border bg-slate-50 p-4 text-center dark:bg-slate-900/60"
                        >
                            <h4
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Ca trực gán hôm nay
                            </h4>
                            <p
                                class="mt-1 text-sm font-black text-slate-800 dark:text-slate-200"
                            >
                                {{ todayActiveAssignment.shift_name }}
                            </p>
                            <p
                                class="mt-0.5 font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                            >
                                ({{ todayActiveAssignment.shift_time }})
                            </p>

                            <!-- Check-in details if working -->
                            <div
                                v-if="
                                    todayActiveAssignment.status ===
                                    'checked_in'
                                "
                                class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="font-medium text-slate-400"
                                        >Bắt đầu lúc:</span
                                    >
                                    <span
                                        class="font-mono font-bold text-slate-700 dark:text-slate-300"
                                        >{{
                                            todayActiveAssignment.check_in_at?.split(
                                                ' ',
                                            )[0]
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="font-medium text-slate-400"
                                        >Số giờ làm:</span
                                    >
                                    <span
                                        class="font-mono text-sm font-black tracking-tight text-emerald-600 dark:text-emerald-400"
                                        >{{ liveDuration }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Illustration if no shift scheduled today -->
                        <div v-else class="py-6 text-center">
                            <div
                                class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-900"
                            >
                                <Sparkles class="size-6" />
                            </div>
                            <p
                                class="text-xs font-bold text-slate-700 dark:text-slate-300"
                            >
                                Nghỉ ngơi thật tốt nhé!
                            </p>
                            <p
                                class="mx-auto mt-1 max-w-[200px] text-[11px] text-muted-foreground"
                            >
                                Hôm nay bạn không được gán ca làm việc nào. Tận
                                hưởng ngày nghỉ vui vẻ!
                            </p>
                        </div>
                    </CardContent>

                    <!-- Interactive Buttons Block -->
                    <CardContent
                        class="rounded-b-2xl border-t border-indigo-50/60 bg-slate-50/50 p-6 pt-0 pb-6 dark:bg-slate-950/20"
                    >
                        <!-- Check In Action Button -->
                        <Button
                            v-if="todayActiveAssignment?.can_check_in"
                            @click="handleCheckIn"
                            class="flex h-12 w-full animate-pulse items-center justify-center gap-1.5 bg-indigo-600 text-sm font-black text-white shadow-md hover:bg-indigo-700 active:scale-98"
                        >
                            <LogIn class="size-4" />
                            BẤM GIỜ VÀO CA (CHECK IN)
                        </Button>

                        <!-- Check Out Action Button -->
                        <Button
                            v-else-if="todayActiveAssignment?.can_check_out"
                            @click="handleCheckOut"
                            variant="destructive"
                            class="flex h-12 w-full items-center justify-center gap-1.5 bg-rose-600 text-sm font-black text-white shadow-md hover:bg-rose-700 active:scale-98"
                        >
                            <LogOut class="size-4" />
                            BẤM GIỜ RA CA (CHECK OUT)
                        </Button>

                        <!-- Completed state description -->
                        <div
                            v-else-if="
                                todayActiveAssignment?.status === 'completed'
                            "
                            class="flex items-start gap-2 rounded-xl border border-indigo-100/50 bg-indigo-50 p-3 text-[11px] text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-indigo-600 dark:text-indigo-400"
                            />
                            <p>
                                <strong>Đã ghi nhận công:</strong> Bạn đã hoàn
                                thành ca trực hôm nay thành công. Dữ liệu thời
                                gian đã được lưu trữ bảo mật để tự động tính
                                lương cuối tháng.
                            </p>
                        </div>

                        <!-- Awaiting scheduled check-in time block -->
                        <div
                            v-else-if="
                                todayActiveAssignment &&
                                todayActiveAssignment.status === 'scheduled' &&
                                !todayActiveAssignment.can_check_in
                            "
                            class="flex items-start gap-2 rounded-xl border border-amber-100/50 bg-amber-50/50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            <AlertCircle
                                class="mt-0.5 size-4 shrink-0 text-amber-500"
                            />
                            <p>
                                <strong>Chờ giờ check-in:</strong> Lịch xếp ca
                                trực của bạn chưa đến khung thời gian mở khóa.
                                Vui lòng quay lại check-in trước giờ vào ca tối
                                đa 30 phút.
                            </p>
                        </div>

                        <!-- System rules brief -->
                        <div
                            v-else
                            class="flex items-start gap-1.5 text-[10px] text-slate-400 dark:text-slate-500"
                        >
                            <HelpCircle class="mt-0.5 size-3.5 shrink-0" />
                            <p>
                                Hệ thống tự động đồng bộ hóa chấm công với
                                Spatie ACL. Bấm giờ ra ca sẽ chặn truy cập hệ
                                thống để bảo an an ninh vận hành.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Employee Weekly Shift Roster (Right / Span 2) -->
            <div class="lg:col-span-2">
                <Card class="h-full shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <CalendarDays class="size-5" />
                                Lịch Xếp Ca Trực Cá Nhân Trong Tuần
                            </CardTitle>
                            <CardDescription
                                >Lịch trình phân phối ca trực của bạn được chốt
                                bởi Quản lý hàng tuần.</CardDescription
                            >
                        </div>
                    </CardHeader>

                    <CardContent class="p-0">
                        <div
                            v-if="myWeeklySchedules?.length"
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <div
                                v-for="ws in myWeeklySchedules"
                                :key="ws.id"
                                class="flex flex-col gap-3 p-4 transition-colors hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/30"
                            >
                                <div class="flex items-center gap-3">
                                    <!-- Colored weekday initials circle -->
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                    >
                                        {{ ws.day_vn.split(' ').pop() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-bold">
                                                {{ ws.day_vn }}
                                            </p>
                                            <span
                                                class="font-mono text-[10px] text-slate-400"
                                                >{{ ws.date }}</span
                                            >
                                        </div>
                                        <div
                                            class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-slate-500"
                                        >
                                            Ca:
                                            <span
                                                class="font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                                >{{ ws.shift_name }}</span
                                            >
                                            · Khung giờ:
                                            <span
                                                class="font-mono text-slate-600 dark:text-slate-400"
                                                >{{ ws.shift_time }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Status Tag -->
                                <div
                                    class="flex items-center gap-3 self-end sm:self-center"
                                >
                                    <!-- Times of check-in/out -->
                                    <div
                                        v-if="ws.check_in_at"
                                        class="text-right font-mono text-[10px] leading-tight text-slate-400"
                                    >
                                        <div>Vào: {{ ws.check_in_at }}</div>
                                        <div v-if="ws.check_out_at">
                                            Ra: {{ ws.check_out_at }}
                                        </div>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase"
                                        :class="statusColors[ws.status]"
                                    >
                                        {{ statusLabels[ws.status] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-24 text-center">
                            <div
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                            >
                                <Calendar
                                    class="size-7 text-muted-foreground/40"
                                />
                            </div>
                            <p class="text-sm font-semibold">
                                Chưa có lịch trực tuần này
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Vui lòng liên hệ với Quản lý cửa hàng để kiểm
                                tra việc xếp ca làm việc của bạn.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 2.1 EMPLOYEE SELF SERVICE PORTAL (NEW)     -->
        <!-- ========================================== -->
        <Card
            v-if="!isAdmin"
            class="mt-6 border-indigo-100 shadow-md dark:border-slate-800"
        >
            <CardHeader
                class="border-b bg-slate-50/50 pb-3 dark:bg-slate-900/20"
            >
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-base text-indigo-600 dark:text-indigo-400"
                        >
                            <Sparkles class="size-5" />
                            Cổng Tự Phục Vụ Nhân Viên (Self-Service)
                        </CardTitle>
                        <CardDescription>
                            Đăng ký ca trực cá nhân, gửi đơn xin nghỉ trực tuyến
                            và hòm thư khiếu nại nội bộ an toàn.
                        </CardDescription>
                    </div>

                    <!-- Tabs switcher -->
                    <div
                        class="dark:border-slate-850 flex items-center gap-1.5 rounded-lg border border-slate-200/50 bg-slate-100 p-0.5 dark:bg-slate-950"
                    >
                        <button
                            type="button"
                            @click="selfServiceTab = 'register'"
                            :class="[
                                'rounded-md px-3 py-1.5 text-xs font-bold transition-all',
                                selfServiceTab === 'register'
                                    ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            <Calendar class="mr-1 inline size-3.5" />
                            Đăng ký ca làm
                        </button>
                        <button
                            type="button"
                            @click="selfServiceTab = 'leave'"
                            :class="[
                                'rounded-md px-3 py-1.5 text-xs font-bold transition-all',
                                selfServiceTab === 'leave'
                                    ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            <FileText class="mr-1 inline size-3.5" />
                            Làm đơn trực tuyến
                        </button>
                        <button
                            type="button"
                            @click="selfServiceTab = 'complaint'"
                            :class="[
                                'rounded-md px-3 py-1.5 text-xs font-bold transition-all',
                                selfServiceTab === 'complaint'
                                    ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-400'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            <ShieldAlert class="mr-1 inline size-3.5" />
                            Khiếu nại nội bộ (Ẩn danh)
                        </button>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <!-- TAB 1: SHIFT REGISTRATION -->
                <div v-if="selfServiceTab === 'register'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <!-- Registration Form -->
                        <div
                            class="border-r border-slate-100 pr-0 md:col-span-1 md:pr-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-slate-850 mb-4 text-sm font-bold dark:text-slate-200"
                            >
                                Đăng ký lịch làm việc mới
                            </h3>
                            <form
                                @submit.prevent="submitShiftRegistration"
                                class="space-y-4"
                            >
                                <div class="space-y-1.5">
                                    <Label
                                        for="reg-date"
                                        class="text-xs font-bold text-slate-500"
                                        >Chọn ngày trực:</Label
                                    >
                                    <Input
                                        id="reg-date"
                                        type="date"
                                        v-model="regDate"
                                        required
                                        class="h-9 text-xs"
                                    />
                                    <p
                                        v-if="regErrors.scheduled_date"
                                        class="mt-1 text-[11px] font-bold text-rose-500"
                                    >
                                        {{ regErrors.scheduled_date }}
                                    </p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label
                                        for="reg-shift"
                                        class="text-xs font-bold text-slate-500"
                                        >Chọn ca trực:</Label
                                    >
                                    <select
                                        id="reg-shift"
                                        v-model="regShiftId"
                                        required
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="" disabled>
                                            -- Vui lòng chọn ca --
                                        </option>
                                        <option
                                            v-for="s in shifts"
                                            :key="s.id"
                                            :value="s.id"
                                        >
                                            {{ s.name }} ({{ s.start }} -
                                            {{ s.end }})
                                        </option>
                                    </select>
                                    <p
                                        v-if="regErrors.shift_id"
                                        class="mt-1 text-[11px] font-bold text-rose-500"
                                    >
                                        {{ regErrors.shift_id }}
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="regProcessing"
                                    class="flex h-9 w-full items-center justify-center gap-1.5 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                >
                                    <Send class="size-3.5" />
                                    Gửi yêu cầu đăng ký ca
                                </Button>
                            </form>
                        </div>

                        <!-- Available shifts guide list -->
                        <div class="md:col-span-2">
                            <h3
                                class="text-slate-850 mb-4 text-sm font-bold dark:text-slate-200"
                            >
                                Danh sách các ca trực của nhà hàng
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div
                                    v-for="s in shifts"
                                    :key="s.id"
                                    class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/40 dark:hover:bg-slate-900/60"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="size-2 rounded-full bg-indigo-600"
                                        ></span>
                                        <h4
                                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ s.name }}
                                        </h4>
                                    </div>
                                    <p
                                        class="mt-2 font-mono text-xs text-slate-500"
                                    >
                                        Giờ trực: {{ s.start }} - {{ s.end }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: LEAVE & RESIGNATION REQUESTS -->
                <div v-if="selfServiceTab === 'leave'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <!-- Leave Form -->
                        <div
                            class="border-r border-slate-100 pr-0 md:col-span-1 md:pr-6 dark:border-slate-800"
                        >
                            <h3
                                class="text-slate-850 mb-4 text-sm font-bold dark:text-slate-200"
                            >
                                Làm đơn xin nghỉ trực tuyến
                            </h3>
                            <form
                                @submit.prevent="submitLeaveRequest"
                                class="space-y-4"
                            >
                                <div class="space-y-1.5">
                                    <Label
                                        for="leave-type"
                                        class="text-xs font-bold text-slate-500"
                                        >Loại đơn yêu cầu:</Label
                                    >
                                    <select
                                        id="leave-type"
                                        v-model="leaveType"
                                        required
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="emergency">
                                            Xin nghỉ đột xuất (Khẩn cấp)
                                        </option>
                                        <option value="resignation">
                                            Đơn xin thôi việc (Nghỉ việc)
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Label
                                            for="leave-start"
                                            class="text-xs font-bold text-slate-500"
                                            >Từ ngày:</Label
                                        >
                                        <Input
                                            id="leave-start"
                                            type="date"
                                            v-model="leaveStartDate"
                                            required
                                            class="h-9 text-xs"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label
                                            for="leave-end"
                                            class="text-xs font-bold text-slate-500"
                                            >Đến ngày:</Label
                                        >
                                        <Input
                                            id="leave-end"
                                            type="date"
                                            v-model="leaveEndDate"
                                            required
                                            class="h-9 text-xs"
                                        />
                                    </div>
                                </div>
                                <p
                                    v-if="leaveErrors.end_date"
                                    class="mt-1 text-[11px] font-bold text-rose-500"
                                >
                                    {{ leaveErrors.end_date }}
                                </p>

                                <div class="space-y-1.5">
                                    <Label
                                        for="leave-reason"
                                        class="text-xs font-bold text-slate-500"
                                        >Lý do xin nghỉ:</Label
                                    >
                                    <textarea
                                        id="leave-reason"
                                        v-model="leaveReason"
                                        placeholder="Ghi rõ lý do tại đây..."
                                        rows="3"
                                        required
                                        class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-2 text-xs focus:ring-2 focus:ring-ring focus:outline-none dark:border-slate-800 dark:bg-slate-950"
                                    ></textarea>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="leaveProcessing"
                                    class="flex h-9 w-full items-center justify-center gap-1.5 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                >
                                    <Send class="size-3.5" />
                                    Gửi đơn yêu cầu
                                </Button>
                            </form>
                        </div>

                        <!-- History of requests -->
                        <div class="md:col-span-2">
                            <h3
                                class="text-slate-850 mb-4 text-sm font-bold dark:text-slate-200"
                            >
                                Lịch sử gửi đơn trực tuyến
                            </h3>
                            <div
                                v-if="leaveRequests?.length"
                                class="overflow-x-auto rounded-xl border bg-white dark:border-slate-800 dark:bg-slate-950"
                            >
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
                                        >
                                            <th class="p-3">Loại đơn</th>
                                            <th class="p-3">
                                                Thời gian xin nghỉ
                                            </th>
                                            <th class="p-3">Lý do</th>
                                            <th class="p-3">Trạng thái</th>
                                            <th class="p-3">Ngày gửi</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-slate-100 dark:divide-slate-800"
                                    >
                                        <tr
                                            v-for="lr in leaveRequests"
                                            :key="lr.id"
                                            class="hover:bg-slate-50/50 dark:hover:bg-slate-900/20"
                                        >
                                            <td
                                                class="p-3 font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{
                                                    lr.leave_type ===
                                                    'emergency'
                                                        ? 'Nghỉ đột xuất'
                                                        : lr.leave_type ===
                                                            'resignation'
                                                          ? 'Thôi việc'
                                                          : lr.leave_type
                                                }}
                                            </td>
                                            <td class="p-3 font-mono">
                                                {{ lr.start_date }} -
                                                {{ lr.end_date }}
                                            </td>
                                            <td
                                                class="max-w-[200px] truncate p-3"
                                                :title="lr.reason"
                                            >
                                                {{ lr.reason }}
                                            </td>
                                            <td class="p-3">
                                                <span
                                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                    :class="[
                                                        lr.status === 'pending'
                                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/20 dark:text-amber-400'
                                                            : lr.status ===
                                                                'approved'
                                                              ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                              : 'bg-rose-100 text-rose-800 dark:bg-rose-950/20 dark:text-rose-400',
                                                    ]"
                                                >
                                                    {{
                                                        lr.status === 'pending'
                                                            ? 'Chờ duyệt'
                                                            : lr.status ===
                                                                'approved'
                                                              ? 'Đã duyệt'
                                                              : 'Đã từ chối'
                                                    }}
                                                </span>
                                            </td>
                                            <td
                                                class="p-3 font-mono text-slate-400"
                                            >
                                                {{ lr.created_at }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-else
                                class="rounded-xl border border-dashed py-12 text-center text-slate-400 dark:border-slate-800"
                            >
                                <FileText
                                    class="mx-auto mb-2 size-8 opacity-50"
                                />
                                Chưa có đơn xin nghỉ nào được nộp.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: INTERNAL COMPLAINTS -->
                <div v-if="selfServiceTab === 'complaint'" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <!-- Complaint Form -->
                        <div
                            class="border-r border-slate-100 pr-0 md:col-span-1 md:pr-6 dark:border-slate-800"
                        >
                            <div
                                class="mb-4 flex items-center gap-1.5 text-xs font-bold text-rose-600 uppercase select-none dark:text-rose-500"
                            >
                                <ShieldAlert class="size-4 shrink-0" />
                                <span>Hòm thư tố cáo ẩn danh bảo mật</span>
                            </div>
                            <form
                                @submit.prevent="submitComplaint"
                                class="space-y-4"
                            >
                                <div class="space-y-1.5">
                                    <Label
                                        for="comp-employee"
                                        class="text-xs font-bold text-slate-500"
                                        >Đối tượng bị khiếu nại:</Label
                                    >
                                    <select
                                        id="comp-employee"
                                        v-model="complaintEmployeeId"
                                        required
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="" disabled>
                                            -- Chọn nhân viên vi phạm --
                                        </option>
                                        <option
                                            v-for="emp in employees"
                                            :key="emp.id"
                                            :value="emp.id"
                                        >
                                            {{ emp.full_name }} [{{
                                                emp.employee_code
                                            }}
                                            - {{ emp.job_title }}]
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1.5">
                                    <Label
                                        for="comp-type"
                                        class="text-xs font-bold text-slate-500"
                                        >Loại sai phạm:</Label
                                    >
                                    <select
                                        id="comp-type"
                                        v-model="complaintViolationType"
                                        required
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 py-1.5 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                                    >
                                        <option value="" disabled>
                                            -- Vui lòng chọn --
                                        </option>
                                        <option
                                            value="Bòn rút tiền mặt / Gian lận ngân quỹ"
                                        >
                                            Bòn rút tiền mặt / Gian lận ngân quỹ
                                        </option>
                                        <option
                                            value="Bớt xén nguyên vật liệu kho / Ăn cắp tài sản"
                                        >
                                            Bớt xén nguyên vật liệu kho / Ăn cắp
                                            tài sản
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
                                            Cấu kết người ngoài / Tiết lộ thông
                                            tin kinh doanh
                                        </option>
                                        <option
                                            value="Hành vi không trung thực khác"
                                        >
                                            Hành vi không trung thực khác
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1.5">
                                    <Label
                                        for="comp-date"
                                        class="text-xs font-bold text-slate-500"
                                        >Ngày xảy ra sự việc:</Label
                                    >
                                    <Input
                                        id="comp-date"
                                        type="date"
                                        v-model="complaintOccurredAt"
                                        required
                                        class="h-9 text-xs"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <Label
                                        for="comp-desc"
                                        class="text-xs font-bold text-slate-500"
                                        >Mô tả hành vi chi tiết:</Label
                                    >
                                    <textarea
                                        id="comp-desc"
                                        v-model="complaintDescription"
                                        placeholder="Cung cấp rõ thời gian, hành vi cụ thể và bằng chứng..."
                                        rows="3"
                                        required
                                        class="w-full resize-none rounded-md border border-slate-200 bg-white px-3 py-2 text-xs focus:ring-2 focus:ring-ring focus:outline-none dark:border-slate-800 dark:bg-slate-950"
                                    ></textarea>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="complaintProcessing"
                                    class="flex h-9 w-full items-center justify-center gap-1.5 bg-rose-600 text-xs font-bold text-white hover:bg-rose-700"
                                >
                                    <Send class="size-3.5" />
                                    Gửi khiếu nại bảo mật (Ẩn danh)
                                </Button>
                            </form>
                        </div>

                        <!-- Anonymity assurance card -->
                        <div class="flex flex-col justify-center md:col-span-2">
                            <div
                                class="mx-auto max-w-xl space-y-4 rounded-2xl border border-rose-100 bg-rose-50/50 p-6 dark:border-rose-900/30 dark:bg-rose-950/15"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-900/50 dark:text-rose-400"
                                    >
                                        <ShieldCheck class="size-6" />
                                    </div>
                                    <div>
                                        <h4
                                            class="dark:text-rose-350 text-sm font-bold text-rose-800"
                                        >
                                            Cam kết Bảo mật Thông tin 100%
                                        </h4>
                                        <p
                                            class="text-xs text-rose-600/80 dark:text-rose-400/80"
                                        >
                                            Hòm thư tố cáo ẩn danh an toàn tuyệt
                                            đối
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="space-y-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                                >
                                    <p>
                                        Hệ thống nhà hàng Aventura thiết lập cơ
                                        chế
                                        <strong>Giám sát chéo ẩn danh</strong>
                                        để bảo vệ nhân viên khỏi sự trù dập hoặc
                                        thiên vị.
                                    </p>
                                    <p>
                                        Khi bạn gửi đơn tố cáo sai phạm nội bộ
                                        này:
                                    </p>
                                    <ul class="list-disc space-y-1 pl-5">
                                        <li>
                                            Tên và thông tin cá nhân của bạn sẽ
                                            được ẩn hoàn toàn trước mọi người
                                            xem (kể cả Manager hay Owner).
                                        </li>
                                        <li>
                                            Dữ liệu được mã hóa để chỉ có thuật
                                            toán AI phân loại trước khi báo cáo.
                                        </li>
                                        <li>
                                            Hành động của bạn góp phần xây dựng
                                            môi trường làm việc trong sạch, lành
                                            mạnh và công bằng.
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- ========================================== -->
        <!-- 3. ADMIN PORTAL MODAL: MANUAL CHECK-IN/OUT -->
        <!-- ========================================== -->
        <div
            v-if="overrideModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <ShieldCheck class="size-5" />
                            Điều Chỉnh Chấm Công Thủ Công
                        </CardTitle>
                        <CardDescription
                            >Ghi nhận thông tin chấm công hộ hoặc báo vắng trực
                            tiếp dưới danh nghĩa quản trị viên.</CardDescription
                        >
                    </div>
                    <button
                        @click="closeOverrideModal"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <!-- Target Employee Info -->
                    <div
                        class="flex gap-3 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3 dark:border-indigo-900/40 dark:bg-indigo-950/20"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700"
                        >
                            {{
                                activeOverrideAssignment?.employee_name.charAt(
                                    0,
                                )
                            }}
                        </div>
                        <div>
                            <h4
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{ activeOverrideAssignment?.employee_name }}
                            </h4>
                            <p class="mt-0.5 text-[10px] text-slate-500">
                                Ca:
                                <span
                                    class="font-mono font-bold text-indigo-600"
                                    >{{
                                        activeOverrideAssignment?.shift_name
                                    }}</span
                                >
                                ({{ activeOverrideAssignment?.shift_time }})
                            </p>
                        </div>
                    </div>

                    <!-- Operation Type Display -->
                    <div class="grid gap-1.5">
                        <Label
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Thao tác thực hiện</Label
                        >
                        <div
                            class="flex items-center gap-2 rounded-xl border bg-slate-50 p-3 text-xs font-bold"
                        >
                            <span
                                v-if="overrideAction === 'check_in'"
                                class="size-2 animate-ping rounded-full bg-emerald-600"
                            ></span>
                            <span
                                v-if="overrideAction === 'check_out'"
                                class="size-2 rounded-full bg-indigo-600"
                            ></span>
                            <span
                                v-if="overrideAction === 'absent'"
                                class="size-2 rounded-full bg-rose-600"
                            ></span>

                            {{
                                overrideAction === 'check_in'
                                    ? 'Check-in hộ (Báo vào ca)'
                                    : overrideAction === 'check_out'
                                      ? 'Check-out hộ (Báo ra ca)'
                                      : 'Báo Vắng (Không phép/Có phép)'
                            }}
                        </div>
                    </div>

                    <!-- Notes / Reason -->
                    <div class="grid gap-1.5">
                        <Label
                            for="override-notes"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Lý do điều chỉnh (Ghi chú)</Label
                        >
                        <Input
                            id="override-notes"
                            type="text"
                            v-model="overrideNotes"
                            placeholder="Ví dụ: Nhân viên quên bấm máy check-in..."
                            required
                            class="h-9 text-xs"
                        />
                    </div>

                    <div
                        class="flex items-start gap-2 rounded-xl border border-amber-100/50 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                    >
                        <AlertCircle
                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                        />
                        <p>
                            <strong>Cảnh báo kiểm toán:</strong> Mọi thao tác
                            ghi nhận hộ sẽ được lưu vết trực tiếp trong Audit
                            Log của nhà hàng và hiển thị trên bảng lương nhân
                            viên.
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 border-t pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="closeOverrideModal"
                            >Hủy</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            @click="submitAdminOverride"
                            class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                            :disabled="processingOverride"
                        >
                            {{
                                processingOverride
                                    ? 'Đang cập nhật...'
                                    : 'Xác nhận ghi nhận'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
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
