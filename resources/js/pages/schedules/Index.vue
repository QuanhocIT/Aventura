<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays, Clock, CheckCircle2, AlertCircle, Sparkles, UserCheck, 
    ShieldCheck, Calendar, Users, LogIn, LogOut, Check, Ban, Search, 
    ArrowLeft, Printer, RefreshCw, HelpCircle, MessageSquare
} from 'lucide-vue-next';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    status: 'scheduled' | 'checked_in' | 'completed' | 'absent' | 'leave_approved';
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
    employees?: Array<{ id: number; full_name: string; job_title: string; employee_code: string }>;
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
    
    if (durationInterval) clearInterval(durationInterval);
    
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

    if (!props.isAdmin && props.todayActiveAssignment && props.todayActiveAssignment.status === 'checked_in' && props.todayActiveAssignment.check_in_at) {
        startLiveDurationTimer(props.todayActiveAssignment.check_in_at);
    }
});

onUnmounted(() => {
    if (clockInterval) clearInterval(clockInterval);
    if (durationInterval) clearInterval(durationInterval);
});

// --- ADMIN PORTAL STATE ---
const adminDate = ref(props.selectedDate || new Date().toISOString().split('T')[0]);
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
            fullLabel: `${wd.label} (${dd}/${mm})`
        };
    });
});


const openOverrideModal = (assignment: Assignment, action: 'check_in' | 'check_out' | 'absent') => {
    activeOverrideAssignment.value = assignment;
    overrideAction.value = action;
    overrideNotes.value = action === 'check_in' ? 'Check-in hộ do nhân viên quên' : 
                         (action === 'check_out' ? 'Check-out hộ do nhân viên quên' : 'Vắng mặt không lý do');
    overrideModal.value = true;
};

const closeOverrideModal = () => {
    overrideModal.value = false;
    activeOverrideAssignment.value = null;
    overrideNotes.value = '';
};

// --- ACTIONS ---
const handleCheckIn = () => {
    router.post('/schedules/check-in', {}, {
        onSuccess: (page: any) => {
            // Start duration timer immediately if active assignment checked-in successfully
            const freshAssign = page.props.todayActiveAssignment as any;
            if (freshAssign && freshAssign.status === 'checked_in' && freshAssign.check_in_at) {
                startLiveDurationTimer(freshAssign.check_in_at);
            }
        }
    });
};

const handleCheckOut = () => {
    if (confirm('Bạn chắc chắn muốn check-out ra ca trực hiện tại? Hệ thống sẽ ghi nhận giờ chấm công của bạn.')) {
        router.post('/schedules/check-out', {}, {
            onFinish: () => {
                if (durationInterval) clearInterval(durationInterval);
            }
        });
    }
};

const submitAdminOverride = () => {
    if (!activeOverrideAssignment.value) return;
    processingOverride.value = true;
    
    let url = '/schedules/check-in-employee';
    if (overrideAction.value === 'check_out') url = '/schedules/check-out-employee';
    if (overrideAction.value === 'absent') url = '/schedules/absent-employee';
    
    router.post(url, {
        assignment_id: activeOverrideAssignment.value.id,
        notes: overrideNotes.value
    }, {
        onSuccess: () => {
            closeOverrideModal();
        },
        onFinish: () => {
            processingOverride.value = false;
        }
    });
};

const refreshAdminData = () => {
    router.get('/schedules', { date: adminDate.value }, { preserveState: true });
};

const handleDateChange = () => {
    router.get('/schedules', { date: adminDate.value });
};

// --- LATE INDICATOR ---
function lateMinutes(a: Assignment): number | null {
    if (!a.check_in_at || !props.shifts) return null;
    const shift = props.shifts.find(s => s.id === a.shift_id);
    if (!shift) return null;
    // shift.start = "06:00", a.scheduled_date = "2026-05-29"
    const shiftStart = new Date(`${a.scheduled_date}T${shift.start}`);
    const graceEnd   = new Date(shiftStart.getTime() + 5 * 60_000); // 5 min grace
    // check_in_at from backend is "H:i:s d/m/Y"
    const checkIn = parseDateTimeStr(a.check_in_at);
    const diffMin = Math.round((checkIn.getTime() - graceEnd.getTime()) / 60_000);
    return diffMin > 0 ? diffMin : null;
}

// --- COMPUTED PROPERTIES ---
const filteredAssignments = computed(() => {
    if (!props.assignments) return [];
    if (!searchQuery.value.trim()) return props.assignments;
    
    const query = searchQuery.value.toLowerCase().trim();
    return props.assignments.filter(a => 
        a.employee_name.toLowerCase().includes(query) || 
        a.employee_code.toLowerCase().includes(query) || 
        a.shift_name.toLowerCase().includes(query)
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
    scheduled: 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30',
    checked_in: 'bg-emerald-50 text-emerald-600 border border-emerald-200 animate-pulse dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30',
    completed: 'bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30',
    absent: 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30',
    leave_approved: 'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-950/20 dark:text-slate-400 dark:border-slate-800',
};

const printRoster = () => {
    window.print();
};
</script>

<template>
    <Head title="Chấm Công & Lịch Trực" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full print:p-0">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 print:hidden">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
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

            <!-- TIME CLOCK DIGITAL DISPLAY -->
            <div class="flex flex-col text-right justify-center bg-slate-50 dark:bg-slate-900/50 border rounded-2xl px-5 py-3 shadow-xs">
                <span class="text-sm font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest font-mono text-[9px]">{{ currentDate }}</span>
                <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono tracking-tight">{{ currentTime }}</span>
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
                <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng lịch trực</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span class="text-3xl font-black text-slate-800 dark:text-slate-100">{{ stats?.total }}</span>
                        <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                            <Calendar class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Working -->
                <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Đang làm việc</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ stats?.working }}</span>
                        <div class="h-8 w-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <Clock class="size-4 animate-pulse" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Completed -->
                <Card class="shadow-xs border-indigo-100 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Hoàn thành ca</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ stats?.completed }}</span>
                        <div class="h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <CheckCircle2 class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Leave -->
                <Card class="shadow-xs border-slate-100 dark:border-slate-800 hover:translate-y-[-2px] transition-transform">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-500">Nghỉ phép duyệt</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span class="text-3xl font-black text-slate-700 dark:text-slate-300">{{ stats?.leave }}</span>
                        <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600">
                            <UserCheck class="size-4" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Absent -->
                <Card class="shadow-xs border-rose-100 dark:border-rose-950/20 hover:translate-y-[-2px] transition-transform">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-xs font-bold uppercase tracking-wider text-rose-500">Vắng mặt</CardDescription>
                    </CardHeader>
                    <CardContent class="flex items-center justify-between pb-3">
                        <span class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ stats?.absent }}</span>
                        <div class="h-8 w-8 rounded-lg bg-rose-50 dark:bg-rose-950/40 flex items-center justify-center text-rose-600 dark:text-rose-400">
                            <Ban class="size-4" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Attendance Controls & Table -->
            <Card class="shadow-sm">
                <CardHeader class="pb-3 border-b flex flex-col md:flex-row md:items-center md:justify-between gap-4 print:hidden">
                    <div class="flex-1">
                        <CardTitle class="text-base flex items-center gap-1.5">
                            <Users class="size-5 text-indigo-600" />
                            Nhật Ký Chấm Công Chi Tiết Trong Ngày
                        </CardTitle>
                        <CardDescription>Bảng giám sát trực quan các lượt bấm giờ vào ca và ra ca thực tế của nhân sự.</CardDescription>
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Date picker selector -->
                        <div class="flex items-center gap-1.5">
                            <Label for="admin-date" class="text-xs shrink-0 font-semibold text-slate-600">Chọn ngày:</Label>
                            <Input 
                                id="admin-date" 
                                type="date" 
                                v-model="adminDate" 
                                @change="handleDateChange" 
                                class="h-8 w-36 text-xs font-semibold py-1 bg-white" 
                            />
                        </div>

                        <!-- Print list -->
                        <Button 
                            @click="printRoster" 
                            variant="outline" 
                            size="sm"
                            class="h-8 text-xs shrink-0 flex items-center gap-1 text-slate-600 border-slate-200"
                        >
                            <Printer class="size-3.5" />
                            In/Xuất báo cáo
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <!-- Search input -->
                    <div class="p-4 bg-slate-50/50 dark:bg-slate-900/30 border-b flex items-center gap-2 print:hidden">
                        <div class="relative w-full max-w-sm">
                            <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                            <Input 
                                type="text" 
                                placeholder="Tìm theo tên nhân viên, mã số, ca trực..." 
                                v-model="searchQuery" 
                                class="h-9 text-xs pl-8 bg-white" 
                            />
                        </div>
                        <Button @click="refreshAdminData" variant="ghost" size="icon" class="h-9 w-9 shrink-0 text-slate-500 hover:text-indigo-600" title="Tải lại dữ liệu">
                            <RefreshCw class="size-4" />
                        </Button>
                    </div>

                    <!-- Attendance Registry Table -->
                    <div v-if="filteredAssignments.length" class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                    <th class="p-3.5">Nhân viên</th>
                                    <th class="p-3.5">Ca trực xếp lịch</th>
                                    <th class="p-3.5">Giờ hành chính</th>
                                    <th class="p-3.5">Thực tế Vào Ca</th>
                                    <th class="p-3.5">Thực tế Ra Ca</th>
                                    <th class="p-3.5">Số giờ làm</th>
                                    <th class="p-3.5">Trạng thái</th>
                                    <th class="p-3.5 text-right print:hidden">Thao tác phê duyệt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="a in filteredAssignments" :key="a.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="p-3.5">
                                        <div class="font-bold text-slate-800 dark:text-slate-200">{{ a.employee_name }}</div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ a.employee_code }} · {{ a.job_title }}</div>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ a.shift_name }}</span>
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-500">{{ a.shift_time }}</td>
                                    <td class="p-3.5 font-mono text-slate-600 dark:text-slate-300">
                                        <div v-if="a.check_in_at" class="flex flex-col gap-0.5">
                                            <div class="flex items-center gap-1">
                                                <span class="size-1.5 rounded-full bg-emerald-600" />
                                                <span class="font-mono">{{ a.check_in_at.split(' ')[0] }}</span>
                                            </div>
                                            <span
                                                v-if="lateMinutes(a)"
                                                class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800"
                                            >⚠ Trễ {{ lateMinutes(a) }} phút</span>
                                        </div>
                                        <div v-else class="text-slate-300 dark:text-slate-700">—</div>
                                    </td>
                                    <td class="p-3.5 font-mono text-slate-600 dark:text-slate-300">
                                        <div v-if="a.check_out_at" class="flex items-center gap-1">
                                            <span class="size-1.5 rounded-full bg-indigo-600" />
                                            {{ a.check_out_at.split(' ')[0] }}
                                        </div>
                                        <div v-else-if="a.status === 'checked_in'" class="text-emerald-500 font-bold italic animate-pulse">Đang làm...</div>
                                        <div v-else class="text-slate-300 dark:text-slate-700">—</div>
                                    </td>
                                    <td class="p-3.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ a.duration || '—' }}
                                        <span v-if="a.notes" class="block font-sans text-[9px] font-normal text-amber-600 dark:text-amber-500 mt-0.5 italic max-w-[150px] truncate" :title="a.notes">
                                            * {{ a.notes }}
                                        </span>
                                    </td>
                                    <td class="p-3.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusColors[a.status]">
                                            {{ statusLabels[a.status] }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-right flex items-center justify-end gap-1.5 print:hidden">
                                        <!-- Actions based on status -->
                                        <template v-if="a.status === 'scheduled'">
                                            <button 
                                                @click="openOverrideModal(a, 'check_in')"
                                                class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-xs"
                                                title="Check-in hộ nhân sự"
                                            >
                                                Check-in hộ
                                            </button>
                                            <button 
                                                @click="openOverrideModal(a, 'absent')"
                                                class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 transition active:scale-95"
                                                title="Báo vắng trực"
                                            >
                                                Báo Vắng
                                            </button>
                                        </template>
                                        <template v-else-if="a.status === 'checked_in'">
                                            <button 
                                                @click="openOverrideModal(a, 'check_out')"
                                                class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-indigo-600 hover:bg-indigo-700 text-white transition active:scale-95 shadow-xs"
                                                title="Check-out hộ nhân sự"
                                            >
                                                Check-out hộ
                                            </button>
                                        </template>
                                        <span v-else class="text-[10px] text-slate-400 font-semibold italic">Đã chốt ca</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="py-16 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                            <Calendar class="size-6" />
                        </div>
                        <p class="text-sm font-semibold">Không tìm thấy ca xếp trực nào trong ngày</p>
                        <p class="mt-1 text-xs text-muted-foreground">Nhà hàng của bạn không có lịch xếp ca làm việc hoặc không trùng điều kiện tìm kiếm.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Global Weekly Roster Overview (For reference) -->
            <Card class="shadow-sm print:hidden">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <CalendarDays class="size-5" />
                            Roster Toàn Hệ Thống Tuần Này
                        </CardTitle>
                        <CardDescription>Tổng quan nhanh phân công ca trực từ Thứ 2 đến Chủ nhật của mọi nhân viên.</CardDescription>
                    </div>
                    <a href="/employees" class="text-xs font-bold text-indigo-600 hover:text-indigo-700 hover:underline flex items-center gap-1">
                        Đi tới xếp lịch <ArrowLeft class="size-3 rotate-180" />
                    </a>
                </CardHeader>
                <CardContent class="p-4">
                    <div class="border rounded-2xl overflow-hidden bg-white dark:bg-slate-950">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-900 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                    <th class="p-3.5 border-r w-[120px]">Thứ trong tuần</th>
                                    <th class="p-3.5">Danh sách phân ca nhân viên</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="day in weekDaysWithDates" :key="day.key" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="p-3.5 font-bold border-r text-slate-700 dark:text-slate-300 bg-slate-50/30">
                                        <div class="flex flex-col gap-0.5">
                                            <span>{{ day.label }}</span>
                                            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono font-medium">({{ day.dateLabel }})</span>
                                        </div>
                                    </td>
                                    <td class="p-3.5 flex flex-wrap gap-2 items-center">
                                        <div
                                            v-for="(s, idx) in weeklyAssignments?.filter(sc => sc.day === day.key)"
                                            :key="'s-' + idx"
                                            class="px-2.5 py-1.5 rounded-lg border bg-indigo-50/30 border-indigo-100 dark:bg-indigo-950/20 dark:border-indigo-900/40 flex items-center gap-1.5 group/assign relative"
                                        >
                                            <span class="size-1.5 rounded-full bg-indigo-600 dark:bg-indigo-400" />
                                            <span class="font-bold text-[10px] text-slate-800 dark:text-slate-200">{{ s.employee_name }}</span>
                                            <span class="text-[9px] text-slate-400 font-mono">({{ s.shift_name }})</span>
                                        </div>
                                        <div v-if="!weeklyAssignments?.some(sc => sc.day === day.key)" class="text-[10px] text-slate-400 italic">
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
        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Interactive Live Time Clock Card (Left / Span 1) -->
            <div class="lg:col-span-1">
                <Card class="shadow-md border-indigo-100 bg-gradient-to-b from-indigo-50/20 to-white dark:from-slate-900/50 dark:to-slate-900 h-full flex flex-col justify-between">
                    <CardHeader class="pb-3 border-b text-center">
                        <CardTitle class="text-base text-indigo-600 flex items-center justify-center gap-1.5">
                            <Clock class="size-5" />
                            Giao Diện Bấm Giờ Chấm Công
                        </CardTitle>
                        <CardDescription>Bấm giờ để chấm công thời gian làm việc thực tế cho ca trực của bạn hôm nay.</CardDescription>
                    </CardHeader>

                    <CardContent class="p-6 flex flex-col items-center justify-center flex-1 space-y-6">
                        <!-- Clock Display Face -->
                        <div class="relative flex flex-col items-center justify-center h-44 w-44 rounded-full border-4 border-indigo-200 bg-white dark:bg-slate-950 shadow-inner">
                            <div class="absolute inset-0 rounded-full bg-indigo-500/5 blur-xs"></div>
                            <span class="text-xs font-bold text-slate-400 dark:text-slate-500 font-mono uppercase tracking-widest text-[9px]">CA HÔM NAY</span>
                            <span class="text-[26px] font-black text-indigo-600 dark:text-indigo-400 tracking-tight mt-1 font-mono leading-none">{{ currentTime }}</span>
                            <!-- Live Status Indicator -->
                            <div class="mt-2 shrink-0">
                                <span v-if="todayActiveAssignment?.status === 'checked_in'" class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-emerald-500 text-white animate-pulse">
                                    Đang làm việc
                                </span>
                                <span v-else-if="todayActiveAssignment?.status === 'completed'" class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-indigo-500 text-white">
                                    Đã hoàn thành ca
                                </span>
                                <span v-else class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-slate-300 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    Chờ vào ca
                                </span>
                            </div>
                        </div>

                        <!-- Active Shift Info Roster Block -->
                        <div v-if="todayActiveAssignment" class="w-full bg-slate-50 dark:bg-slate-900/60 border rounded-2xl p-4 text-center">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Ca trực gán hôm nay</h4>
                            <p class="text-sm font-black text-slate-800 dark:text-slate-200 mt-1">{{ todayActiveAssignment.shift_name }}</p>
                            <p class="text-xs font-mono font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5">({{ todayActiveAssignment.shift_time }})</p>

                            <!-- Check-in details if working -->
                            <div v-if="todayActiveAssignment.status === 'checked_in'" class="border-t border-slate-200 dark:border-slate-800 mt-3 pt-3 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-400 font-medium">Bắt đầu lúc:</span>
                                    <span class="font-bold text-slate-700 dark:text-slate-300 font-mono">{{ todayActiveAssignment.check_in_at?.split(' ')[0] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-400 font-medium">Số giờ làm:</span>
                                    <span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-sm tracking-tight">{{ liveDuration }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Illustration if no shift scheduled today -->
                        <div v-else class="text-center py-6">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                                <Sparkles class="size-6" />
                            </div>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Nghỉ ngơi thật tốt nhé!</p>
                            <p class="text-[11px] text-muted-foreground mt-1 max-w-[200px] mx-auto">Hôm nay bạn không được gán ca làm việc nào. Tận hưởng ngày nghỉ vui vẻ!</p>
                        </div>
                    </CardContent>

                    <!-- Interactive Buttons Block -->
                    <CardContent class="pb-6 pt-0 border-t border-indigo-50/60 bg-slate-50/50 dark:bg-slate-950/20 rounded-b-2xl p-6">
                        <!-- Check In Action Button -->
                        <Button 
                            v-if="todayActiveAssignment?.can_check_in"
                            @click="handleCheckIn" 
                            class="w-full h-12 text-sm font-black bg-indigo-600 hover:bg-indigo-700 text-white shadow-md active:scale-98 animate-pulse flex items-center justify-center gap-1.5"
                        >
                            <LogIn class="size-4" />
                            BẤM GIỜ VÀO CA (CHECK IN)
                        </Button>
                        
                        <!-- Check Out Action Button -->
                        <Button 
                            v-else-if="todayActiveAssignment?.can_check_out"
                            @click="handleCheckOut" 
                            variant="destructive"
                            class="w-full h-12 text-sm font-black bg-rose-600 hover:bg-rose-700 text-white shadow-md active:scale-98 flex items-center justify-center gap-1.5"
                        >
                            <LogOut class="size-4" />
                            BẤM GIỜ RA CA (CHECK OUT)
                        </Button>

                        <!-- Completed state description -->
                        <div v-else-if="todayActiveAssignment?.status === 'completed'" class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-xl flex items-start gap-2 text-[11px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                            <CheckCircle2 class="size-4 shrink-0 text-indigo-600 dark:text-indigo-400 mt-0.5" />
                            <p><strong>Đã ghi nhận công:</strong> Bạn đã hoàn thành ca trực hôm nay thành công. Dữ liệu thời gian đã được lưu trữ bảo mật để tự động tính lương cuối tháng.</p>
                        </div>

                        <!-- Awaiting scheduled check-in time block -->
                        <div v-else-if="todayActiveAssignment && todayActiveAssignment.status === 'scheduled' && !todayActiveAssignment.can_check_in" class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[11px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                            <AlertCircle class="size-4 shrink-0 text-amber-500 mt-0.5" />
                            <p><strong>Chờ giờ check-in:</strong> Lịch xếp ca trực của bạn chưa đến khung thời gian mở khóa. Vui lòng quay lại check-in trước giờ vào ca tối đa 30 phút.</p>
                        </div>

                        <!-- System rules brief -->
                        <div v-else class="text-[10px] text-slate-400 dark:text-slate-500 flex items-start gap-1.5">
                            <HelpCircle class="size-3.5 shrink-0 mt-0.5" />
                            <p>Hệ thống tự động đồng bộ hóa chấm công với Spatie ACL. Bấm giờ ra ca sẽ chặn truy cập hệ thống để bảo an an ninh vận hành.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Employee Weekly Shift Roster (Right / Span 2) -->
            <div class="lg:col-span-2">
                <Card class="shadow-sm h-full">
                    <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                                <CalendarDays class="size-5" />
                                Lịch Xếp Ca Trực Cá Nhân Trong Tuần
                            </CardTitle>
                            <CardDescription>Lịch trình phân phối ca trực của bạn được chốt bởi Quản lý hàng tuần.</CardDescription>
                        </div>
                    </CardHeader>
                    
                    <CardContent class="p-0">
                        <div v-if="myWeeklySchedules?.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div 
                                v-for="ws in myWeeklySchedules" 
                                :key="ws.id" 
                                class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors gap-3"
                            >
                                <div class="flex items-center gap-3">
                                    <!-- Colored weekday initials circle -->
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-black">
                                        {{ ws.day_vn.split(' ').pop() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-sm">{{ ws.day_vn }}</p>
                                            <span class="text-[10px] text-slate-400 font-mono">{{ ws.date }}</span>
                                        </div>
                                        <div class="text-xs font-semibold text-slate-500 mt-0.5 flex items-center gap-1">
                                            Ca: <span class="text-indigo-600 dark:text-indigo-400 font-bold font-mono">{{ ws.shift_name }}</span> 
                                            · Khung giờ: <span class="font-mono text-slate-600 dark:text-slate-400">{{ ws.shift_time }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Status Tag -->
                                <div class="flex items-center gap-3 self-end sm:self-center">
                                    <!-- Times of check-in/out -->
                                    <div v-if="ws.check_in_at" class="text-right text-[10px] font-mono text-slate-400 leading-tight">
                                        <div>Vào: {{ ws.check_in_at }}</div>
                                        <div v-if="ws.check_out_at">Ra: {{ ws.check_out_at }}</div>
                                    </div>

                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0" :class="statusColors[ws.status]">
                                        {{ statusLabels[ws.status] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-24 text-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted mx-auto mb-3">
                                <Calendar class="size-7 text-muted-foreground/40" />
                            </div>
                            <p class="text-sm font-semibold">Chưa có lịch trực tuần này</p>
                            <p class="mt-1 text-xs text-muted-foreground">Vui lòng liên hệ với Quản lý cửa hàng để kiểm tra việc xếp ca làm việc của bạn.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 3. ADMIN PORTAL MODAL: MANUAL CHECK-IN/OUT -->
        <!-- ========================================== -->
        <div v-if="overrideModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <ShieldCheck class="size-5" />
                            Điều Chỉnh Chấm Công Thủ Công
                        </CardTitle>
                        <CardDescription>Ghi nhận thông tin chấm công hộ hoặc báo vắng trực tiếp dưới danh nghĩa quản trị viên.</CardDescription>
                    </div>
                    <button @click="closeOverrideModal" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <!-- Target Employee Info -->
                    <div class="bg-indigo-50/40 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-900/40 rounded-xl p-3 flex gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700 text-xs font-black">
                            {{ activeOverrideAssignment?.employee_name.charAt(0) }}
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ activeOverrideAssignment?.employee_name }}</h4>
                            <p class="text-[10px] text-slate-500 mt-0.5">Ca: <span class="font-bold text-indigo-600 font-mono">{{ activeOverrideAssignment?.shift_name }}</span> ({{ activeOverrideAssignment?.shift_time }})</p>
                        </div>
                    </div>

                    <!-- Operation Type Display -->
                    <div class="grid gap-1.5">
                        <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Thao tác thực hiện</Label>
                        <div class="p-3 border rounded-xl bg-slate-50 text-xs font-bold flex items-center gap-2">
                            <span v-if="overrideAction === 'check_in'" class="size-2 rounded-full bg-emerald-600 animate-ping"></span>
                            <span v-if="overrideAction === 'check_out'" class="size-2 rounded-full bg-indigo-600"></span>
                            <span v-if="overrideAction === 'absent'" class="size-2 rounded-full bg-rose-600"></span>
                            
                            {{ overrideAction === 'check_in' ? 'Check-in hộ (Báo vào ca)' : 
                               (overrideAction === 'check_out' ? 'Check-out hộ (Báo ra ca)' : 'Báo Vắng (Không phép/Có phép)') }}
                        </div>
                    </div>

                    <!-- Notes / Reason -->
                    <div class="grid gap-1.5">
                        <Label for="override-notes" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do điều chỉnh (Ghi chú)</Label>
                        <Input 
                            id="override-notes" 
                            type="text" 
                            v-model="overrideNotes" 
                            placeholder="Ví dụ: Nhân viên quên bấm máy check-in..."
                            required 
                            class="h-9 text-xs" 
                        />
                    </div>
                    
                    <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                        <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                        <p><strong>Cảnh báo kiểm toán:</strong> Mọi thao tác ghi nhận hộ sẽ được lưu vết trực tiếp trong Audit Log của nhà hàng và hiển thị trên bảng lương nhân viên.</p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="closeOverrideModal">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitAdminOverride" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                            :disabled="processingOverride"
                        >
                            {{ processingOverride ? 'Đang cập nhật...' : 'Xác nhận ghi nhận' }}
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
