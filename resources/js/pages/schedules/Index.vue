<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays, Clock, CheckCircle2, AlertCircle, Sparkles, UserCheck, 
    ShieldCheck, Calendar, Users, LogIn, LogOut, Check, Ban, Search, 
    ArrowLeft, Printer, RefreshCw, HelpCircle, MessageSquare, X, Crown, Settings
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
    is_shift_leader: boolean;
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
    allPendingSwaps?: ShiftSwapType[];
    pendingSwapRequests?: ShiftSwapType[];
    monthlyAssignments?: Array<{
        id: number;
        employee_id: number;
        employee_name: string;
        employee_code: string;
        job_title: string;
        compensation_type: string;
        pay_rate: number;
        base_salary: number;
        scheduled_date: string;
        status: string;
        duration_hours: number;
        late_minutes: number | null;
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

type ShiftSwapType = {
    id: number;
    status: 'pending' | 'accepted' | 'approved' | 'rejected' | 'cancelled';
    notes: string | null;
    is_requester?: boolean;
    requester_name: string;
    requester_shift: string;
    requester_date: string;
    receiver_name: string;
    receiver_shift: string;
    receiver_date: string;
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

const selectedRegs = ref<Array<{ shift_id: number; date: string }>>([]);
const isSavingRegs = ref(false);
const activeStaffTab = ref<'roster' | 'register'>('roster');
const activeAdminTab = ref<'attendance' | 'roster' | 'register' | 'settings' | 'swaps' | 'analytics'>('attendance');

// --- SETTINGS PANEL STATE ---
const gracePeriod = ref(props.restaurantSettings?.grace_period_minutes ?? 10);
const otMultiplier = ref(props.restaurantSettings?.ot_multiplier ?? 1.50);
const latitude = ref(props.gpsSettings?.latitude ?? '');
const longitude = ref(props.gpsSettings?.longitude ?? '');
const checkinRadius = ref(props.gpsSettings?.radius ?? 100);
const isSavingSettings = ref(false);
const isGeneratingQR = ref(false);

const saveSettings = () => {
    isSavingSettings.value = true;
    router.post('/schedules/settings', {
        grace_period_minutes: gracePeriod.value,
        ot_multiplier: otMultiplier.value,
        latitude: latitude.value === '' ? null : Number(latitude.value),
        longitude: longitude.value === '' ? null : Number(longitude.value),
        checkin_radius_meters: Number(checkinRadius.value),
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã lưu cấu hình chấm công thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi lưu cấu hình.'));
        },
        onFinish: () => {
            isSavingSettings.value = false;
        }
    });
};

const generateDailyQR = () => {
    isGeneratingQR.value = true;
    router.post('/schedules/settings/generate-qr', {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã tạo mã QR chấm công hôm nay thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi tạo mã QR.'));
        },
        onFinish: () => {
            isGeneratingQR.value = false;
        }
    });
};

// --- SHIFT LEADER TOGGLE ---
const toggleShiftLeader = (assignmentId: number) => {
    router.post('/schedules/toggle-leader', {
        assignment_id: assignmentId
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Cập nhật vai trò Trưởng ca thành công!'));
        }
    });
};

// --- APPROVE REGISTRATION ---
const isApprovingReg = ref<number | null>(null);
const approveRegistration = (regId: number) => {
    isApprovingReg.value = regId;
    router.post('/schedules/approve-registration', {
        registration_id: regId
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã xếp lịch trực từ ca rảnh thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi xếp ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        },
        onFinish: () => {
            isApprovingReg.value = null;
        }
    });
};

// Pagination for personal weekly schedules
const rosterPage = ref(1);
const rosterPerPage = 10;
const rosterTotalPages = computed(() => Math.ceil((props.myWeeklySchedules?.length || 0) / rosterPerPage));
const paginatedRoster = computed(() => {
    if (!props.myWeeklySchedules) return [];
    const start = (rosterPage.value - 1) * rosterPerPage;
    const end = start + rosterPerPage;
    return props.myWeeklySchedules.slice(start, end);
});

const isShiftSelected = (shiftId: number, dateStr: string) => {
    return selectedRegs.value.some(r => r.shift_id === shiftId && r.date === dateStr);
};

const toggleShiftSelection = (shiftId: number, dateStr: string) => {
    const index = selectedRegs.value.findIndex(r => r.shift_id === shiftId && r.date === dateStr);
    if (index > -1) {
        selectedRegs.value.splice(index, 1);
    } else {
        selectedRegs.value.push({ shift_id: shiftId, date: dateStr });
    }
};

const saveRegistrations = () => {
    isSavingRegs.value = true;
    router.post('/schedules/register', {
        registrations: selectedRegs.value
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã lưu đăng ký ca làm rảnh thành công!'));
        },
        onError: () => {
            import('vue-sonner').then(m => m.toast.error('Có lỗi xảy ra khi lưu đăng ký.'));
        },
        onFinish: () => {
            isSavingRegs.value = false;
        }
    });
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 1000);

    if (!props.isAdmin && props.todayActiveAssignment && props.todayActiveAssignment.status === 'checked_in' && props.todayActiveAssignment.check_in_at) {
        startLiveDurationTimer(props.todayActiveAssignment.check_in_at);
    }

    if (!props.isAdmin && props.myRegistrations) {
        selectedRegs.value = props.myRegistrations.map(r => ({
            shift_id: r.shift_id,
            date: r.date
        }));
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

const applyViolation = ref(false);
const penaltyAmount = ref(0);
const violationNotes = ref('');

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
        const yyyy = nextDay.getFullYear();
        const mm = String(nextDay.getMonth() + 1).padStart(2, '0');
        const dd = String(nextDay.getDate()).padStart(2, '0');
        return {
            ...wd,
            dateLabel: `${dd}/${mm}`,
            fullLabel: `${wd.label} (${dd}/${mm})`,
            dateStr: `${yyyy}-${mm}-${dd}`
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
    applyViolation.value = false;
    penaltyAmount.value = 0;
    violationNotes.value = '';
};

// --- ACTIONS ---
// --- SECURE CHECK-IN STATE ---
const checkInModalOpen = ref(false);
const gpsCoords = ref<{ latitude: number | null; longitude: number | null }>({ latitude: null, longitude: null });
const gpsStatus = ref<'idle' | 'fetching' | 'success' | 'error'>('idle');
const gpsErrorMsg = ref('');
const inputQrCode = ref('');
const isCheckingIn = ref(false);

const openCheckInFlow = () => {
    gpsCoords.value = { latitude: null, longitude: null };
    gpsStatus.value = 'idle';
    gpsErrorMsg.value = '';
    inputQrCode.value = '';
    isCheckingIn.value = false;
    checkInModalOpen.value = true;

    if (props.gpsSettings?.latitude && props.gpsSettings?.longitude) {
        gpsStatus.value = 'fetching';
        if (!navigator.geolocation) {
            gpsStatus.value = 'error';
            gpsErrorMsg.value = 'Trình duyệt không hỗ trợ định vị GPS.';
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (position) => {
                gpsCoords.value.latitude = position.coords.latitude;
                gpsCoords.value.longitude = position.coords.longitude;
                gpsStatus.value = 'success';
            },
            (error) => {
                gpsStatus.value = 'error';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        gpsErrorMsg.value = 'Quyền truy cập định vị bị từ chối. Vui lòng bật định vị.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        gpsErrorMsg.value = 'Không thể xác định vị trí GPS hiện tại.';
                        break;
                    case error.TIMEOUT:
                        gpsErrorMsg.value = 'Hết thời gian yêu cầu lấy vị trí GPS.';
                        break;
                    default:
                        gpsErrorMsg.value = error.message || 'Lỗi định vị vị trí.';
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        gpsStatus.value = 'success';
    }
};

const submitCheckIn = () => {
    isCheckingIn.value = true;
    router.post('/schedules/check-in', {
        latitude: gpsCoords.value.latitude,
        longitude: gpsCoords.value.longitude,
        qr_code: inputQrCode.value
    }, {
        onSuccess: (page: any) => {
            checkInModalOpen.value = false;
            import('vue-sonner').then(m => m.toast.success('Bấm giờ vào ca thành công!'));
            const freshAssign = page.props.todayActiveAssignment as any;
            if (freshAssign && freshAssign.status === 'checked_in' && freshAssign.check_in_at) {
                startLiveDurationTimer(freshAssign.check_in_at);
            }
        },
        onError: (errors: any) => {
            const errorMsg = Object.values(errors).join(', ') || 'Có lỗi xảy ra khi check-in.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        },
        onFinish: () => {
            isCheckingIn.value = false;
        }
    });
};

// --- STAFF SHIFT SWAPPING STATE ---
const swapModalOpen = ref(false);
const selectedMyShift = ref<any>(null);
const selectedTargetShiftId = ref<number | null>(null);
const swapNotes = ref('');
const isSubmittingSwap = ref(false);

const swappableShifts = computed(() => {
    if (!props.weeklyAssignments || !props.employee) return [];
    return props.weeklyAssignments.filter(wa => wa.employee_id !== props.employee?.id);
});

const openSwapModal = (myShift: any) => {
    selectedMyShift.value = myShift;
    selectedTargetShiftId.value = null;
    swapNotes.value = '';
    isSubmittingSwap.value = false;
    swapModalOpen.value = true;
};

const submitSwapRequest = () => {
    if (!selectedMyShift.value || !selectedTargetShiftId.value) {
        import('vue-sonner').then(m => m.toast.error('Vui lòng chọn ca muốn đề xuất đổi.'));
        return;
    }
    isSubmittingSwap.value = true;
    router.post('/schedules/swap/request', {
        requester_assignment_id: selectedMyShift.value.id,
        receiver_assignment_id: selectedTargetShiftId.value,
        notes: swapNotes.value
    }, {
        onSuccess: () => {
            swapModalOpen.value = false;
            import('vue-sonner').then(m => m.toast.success('Đã gửi đề xuất đổi ca tới đồng nghiệp thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi gửi yêu cầu đổi ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        },
        onFinish: () => {
            isSubmittingSwap.value = false;
        }
    });
};

const acceptSwapRequest = (swapId: number) => {
    router.post(`/schedules/swap/${swapId}/accept`, {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đồng ý đổi ca thành công! Đang chờ Quản lý duyệt.'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi đồng ý đổi ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

const cancelSwapRequest = (swapId: number) => {
    router.post(`/schedules/swap/${swapId}/cancel`, {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã hủy/từ chối yêu cầu đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi xử lý hủy ca.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

// --- ADMIN SHIFT SWAP APPROVALS ---
const isRejectingSwap = ref(false);
const activeRejectSwapId = ref<number | null>(null);
const rejectNotes = ref('');

const approveSwap = (swapId: number) => {
    router.patch(`/schedules/swap/${swapId}/approve`, {}, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã phê duyệt đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi phê duyệt.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

const openRejectSwapModal = (swapId: number) => {
    activeRejectSwapId.value = swapId;
    rejectNotes.value = '';
    isRejectingSwap.value = true;
};

const submitRejectSwap = () => {
    if (!activeRejectSwapId.value) return;
    router.patch(`/schedules/swap/${activeRejectSwapId.value}/reject`, {
        notes: rejectNotes.value
    }, {
        onSuccess: () => {
            isRejectingSwap.value = false;
            import('vue-sonner').then(m => m.toast.success('Đã từ chối đổi ca thành công!'));
        },
        onError: (errors: any) => {
            const errorMsg = errors.error || 'Có lỗi xảy ra khi từ chối.';
            import('vue-sonner').then(m => m.toast.error(errorMsg));
        }
    });
};

// --- REAL-TIME ATTENDANCE ANALYTICS ---
const analyticsData = computed(() => {
    if (!props.monthlyAssignments || props.monthlyAssignments.length === 0) {
        return {
            totalAssignments: 0,
            onTimeCount: 0,
            lateCount: 0,
            absentCount: 0,
            punctualityRate: 0,
            employeeStats: []
        };
    }

    const assignments = props.monthlyAssignments;
    const total = assignments.length;
    
    const punctualityChecked = assignments.filter(a => a.status === 'completed' || a.status === 'checked_in');
    const late = punctualityChecked.filter(a => a.late_minutes && a.late_minutes > 0);
    const absent = assignments.filter(a => a.status === 'absent');
    const onTime = punctualityChecked.length - late.length;
    
    const punctualityRate = punctualityChecked.length > 0 
        ? Math.round((onTime / punctualityChecked.length) * 100) 
        : 0;

    const employeeMap: Record<number, {
        name: string;
        code: string;
        jobTitle: string;
        compensationType: string;
        payRate: number;
        baseSalary: number;
        totalHours: number;
        completedCount: number;
        lateCount: number;
    }> = {};

    assignments.forEach(a => {
        if (!employeeMap[a.employee_id]) {
            employeeMap[a.employee_id] = {
                name: a.employee_name,
                code: a.employee_code,
                jobTitle: a.job_title,
                compensationType: a.compensation_type,
                payRate: a.pay_rate,
                baseSalary: a.base_salary,
                totalHours: 0,
                completedCount: 0,
                lateCount: 0
            };
        }
        
        const emp = employeeMap[a.employee_id];
        if (a.status === 'completed') {
            emp.completedCount++;
            emp.totalHours += a.duration_hours;
        } else if (a.status === 'checked_in') {
            emp.totalHours += a.duration_hours;
        }
        
        if (a.late_minutes && a.late_minutes > 0) {
            emp.lateCount++;
        }
    });

    const employeeStats = Object.values(employeeMap).map(emp => {
        let estimatedWage = 0;
        if (emp.compensationType === 'hourly') {
            estimatedWage = emp.totalHours * emp.payRate;
        } else if (emp.compensationType === 'shift') {
            estimatedWage = emp.completedCount * emp.payRate;
        } else {
            estimatedWage = (emp.baseSalary / 208) * emp.totalHours;
        }

        return {
            ...emp,
            totalHours: Math.round(emp.totalHours * 100) / 100,
            estimatedWage: Math.round(estimatedWage)
        };
    });

    return {
        totalAssignments: total,
        onTimeCount: onTime,
        lateCount: late.length,
        absentCount: absent.length,
        punctualityRate,
        employeeStats
    };
});

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
        notes: overrideNotes.value,
        apply_violation: applyViolation.value,
        penalty_amount: penaltyAmount.value,
        violation_notes: violationNotes.value
    }, {
        onSuccess: () => {
            closeOverrideModal();
            import('vue-sonner').then(m => m.toast.success('Ghi nhận điều chỉnh chấm công thành công!'));
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

            <!-- Admin Tabs Switcher -->
            <div class="flex p-1 bg-slate-100 dark:bg-slate-900 border rounded-xl w-fit print:hidden">
                <button
                    type="button"
                    @click="activeAdminTab = 'attendance'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'attendance'
                            ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    <Users class="size-3.5" />
                    Nhật ký chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'roster'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'roster'
                            ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    <CalendarDays class="size-3.5" />
                    Roster toàn hệ thống
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'register'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'register'
                            ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    <Clock class="size-3.5" />
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
                    <Settings class="size-3.5" />
                    Cài đặt chấm công
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'swaps'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'swaps'
                            ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    <RefreshCw class="size-3.5" />
                    Duyệt đổi ca
                </button>
                <button
                    type="button"
                    @click="activeAdminTab = 'analytics'"
                    :class="[
                        'px-4 py-1.5 text-xs font-bold rounded-lg transition-all duration-150 cursor-pointer flex items-center gap-1.5',
                        activeAdminTab === 'analytics'
                            ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-350'
                    ]"
                >
                    <Sparkles class="size-3.5" />
                    Báo cáo & Phân tích
                </button>
            </div>

            <!-- Card 1: Attendance Logs -->
            <div v-if="activeAdminTab === 'attendance'">
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
                            In báo cáo
                        </Button>

                        <!-- Export CSV -->
                        <a 
                            :href="'/schedules/export?date=' + adminDate"
                            class="h-8 text-xs shrink-0 inline-flex items-center justify-center rounded-md font-semibold border border-slate-200 bg-white shadow-xs hover:bg-slate-50 px-3 gap-1 text-indigo-650 dark:text-indigo-400"
                            target="_blank"
                        >
                            <LogOut class="size-3.5 rotate-90 text-indigo-650 dark:text-indigo-400" />
                            Xuất Excel (CSV)
                        </a>
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
                                        <div class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200">
                                            <span>{{ a.employee_name }}</span>
                                            <Crown v-if="a.is_shift_leader" class="size-3.5 text-amber-500 fill-amber-500 shrink-0 animate-bounce" title="Trưởng ca trực" />
                                        </div>
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
                                        <!-- Toggle Shift Leader Button -->
                                        <button 
                                            @click="toggleShiftLeader(a.id)"
                                            :class="[
                                                'inline-flex cursor-pointer items-center justify-center rounded p-1 text-[10px] font-bold border transition active:scale-95 shadow-xs',
                                                a.is_shift_leader 
                                                    ? 'bg-amber-500 hover:bg-amber-600 text-white border-amber-600' 
                                                    : 'bg-white hover:bg-slate-50 text-slate-650 border-slate-200 dark:bg-slate-900 dark:border-slate-800'
                                            ]"
                                            :title="a.is_shift_leader ? 'Hủy vai trò Trưởng ca' : 'Gán làm Trưởng ca'"
                                        >
                                            <Crown class="size-3.5" />
                                        </button>

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
            </div>

            <!-- Card 2: Weekly Roster -->
            <div v-else-if="activeAdminTab === 'roster'">
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

            <!-- Card 3: Shift Registrations -->
            <div v-else-if="activeAdminTab === 'register'">
                <!-- Global Weekly Shift Registrations (Admin View) -->
                <Card class="shadow-sm mt-6 print:hidden">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-base flex items-center gap-1.5 text-emerald-600 dark:text-emerald-450">
                        <Clock class="size-5" />
                        Tổng Hợp Đăng Ký Ca Rảnh Của Nhân Sự Tuần Này
                    </CardTitle>
                    <CardDescription>Xem nhanh danh sách nhân viên rảnh và có thể đi làm tại từng ca trong tuần.</CardDescription>
                </CardHeader>
                <CardContent class="p-4">
                    <div class="border rounded-2xl overflow-hidden bg-white dark:bg-slate-950">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-900 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                    <th class="p-3.5 border-r w-[120px]">Thứ trong tuần</th>
                                    <th class="p-3.5">Danh sách nhân viên đăng ký rảnh theo ca</th>
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
                                    <td class="p-3.5">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div 
                                                v-for="shift in shifts" 
                                                :key="shift.id"
                                                class="p-2 border border-slate-100 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-900/10 rounded-xl"
                                            >
                                                <div class="font-bold text-[10px] text-indigo-650 dark:text-indigo-400 flex items-center gap-1">
                                                    <span class="size-1 rounded-full bg-indigo-500" />
                                                    {{ shift.name.split(' (')[0] }}
                                                </div>
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    <button
                                                        v-for="r in registrations?.filter(reg => reg.day === day.key && reg.shift_id === shift.id)"
                                                        :key="r.id"
                                                        @click="approveRegistration(r.id)"
                                                        :disabled="isApprovingReg === r.id"
                                                        class="px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30 active:scale-95 transition-all inline-flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                                        title="Click để duyệt nhanh ca rảnh và xếp lịch trực cho nhân sự này"
                                                    >
                                                        <span>{{ r.employee_name }}</span>
                                                        <span class="text-[8px] text-emerald-500 opacity-80 font-bold shrink-0">(+ Xếp)</span>
                                                    </button>
                                                    <span 
                                                        v-if="!registrations?.some(reg => reg.day === day.key && reg.shift_id === shift.id)"
                                                        class="text-[10px] text-slate-400 italic font-medium"
                                                    >
                                                        (Trống)
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
            </div>

            <!-- Card 4: Timekeeping Settings -->
            <div v-else-if="activeAdminTab === 'settings'">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                            <Settings class="size-5 text-indigo-650" />
                            Cấu Hình Tham Số Chấm Công
                        </CardTitle>
                        <CardDescription>Thiết lập thời gian đi trễ cho phép và hệ số tính lương làm thêm giờ cho nhà hàng.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6">
                        <form @submit.prevent="saveSettings" class="max-w-md space-y-6">
                            <!-- Grace period -->
                            <div class="grid gap-2">
                                <Label for="grace-period-input" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                    Thời gian đi trễ cho phép (Phút)
                                </Label>
                                <span class="text-[11px] text-muted-foreground">
                                    Nhân viên check-in trễ trong khoảng thời gian này so với giờ bắt đầu ca vẫn được tính là đi đúng giờ.
                                </span>
                                <Input 
                                    id="grace-period-input"
                                    type="number"
                                    v-model.number="gracePeriod"
                                    min="0"
                                    max="120"
                                    required
                                    class="h-10 text-sm w-full md:w-2/3"
                                />
                            </div>

                            <!-- OT Multiplier -->
                            <div class="grid gap-2">
                                <Label for="ot-multiplier-input" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                    Hệ số tính lương tăng ca (Overtime Multiplier)
                                </Label>
                                <span class="text-[11px] text-muted-foreground">
                                    Hệ số nhân với mức lương cơ bản theo giờ cho phần thời gian nhân viên làm ngoài giờ ca trực được duyệt.
                                </span>
                                <Input 
                                    id="ot-multiplier-input"
                                    type="number"
                                    v-model.number="otMultiplier"
                                    min="1.0"
                                    max="5.0"
                                    step="0.05"
                                    required
                                    class="h-10 text-sm w-full md:w-2/3"
                                />
                            </div>

                            <!-- GPS Latitude & Longitude -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="grid gap-2">
                                    <Label for="gps-lat" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                        Vĩ độ GPS (Latitude)
                                    </Label>
                                    <Input 
                                        id="gps-lat"
                                        type="number"
                                        step="0.00000001"
                                        v-model="latitude"
                                        placeholder="Ví dụ: 10.7769"
                                        class="h-10 text-sm w-full"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="gps-lng" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                        Kinh độ GPS (Longitude)
                                    </Label>
                                    <Input 
                                        id="gps-lng"
                                        type="number"
                                        step="0.00000001"
                                        v-model="longitude"
                                        placeholder="Ví dụ: 106.7009"
                                        class="h-10 text-sm w-full"
                                    />
                                </div>
                            </div>

                            <!-- GPS Radius -->
                            <div class="grid gap-2">
                                <Label for="gps-radius" class="text-xs font-bold text-slate-700 dark:text-slate-350">
                                    Bán kính giới hạn check-in (Mét)
                                </Label>
                                <span class="text-[11px] text-muted-foreground">
                                    Khoảng cách tối đa cho phép nhân viên check-in so với vị trí của nhà hàng.
                                </span>
                                <Input 
                                    id="gps-radius"
                                    type="number"
                                    v-model.number="checkinRadius"
                                    min="10"
                                    max="10000"
                                    required
                                    class="h-10 text-sm w-full md:w-2/3"
                                />
                            </div>

                            <!-- Daily QR Code Generation Section -->
                            <div class="border-t pt-6 space-y-4">
                                <h3 class="text-xs font-bold text-slate-700 dark:text-slate-350 uppercase tracking-wider">
                                    Mã QR Code chấm công hôm nay
                                </h3>
                                <p class="text-[11px] text-muted-foreground">
                                    Tạo mã QR an toàn dùng để dán tại nhà hàng. Nhân viên nhập mã này khi check-in để xác minh.
                                </p>
                                
                                <div class="flex items-center gap-3">
                                    <div class="p-3 bg-slate-50 dark:bg-slate-900 border rounded-xl flex-1">
                                        <div class="text-[10px] text-slate-400 font-bold uppercase">Mã hiện tại:</div>
                                        <div class="text-lg font-mono font-black text-indigo-650 dark:text-indigo-400 mt-1">
                                            {{ props.qrSettings?.code || 'Chưa tạo mã' }}
                                        </div>
                                        <div v-if="props.qrSettings?.expires_at" class="text-[10px] text-slate-400 mt-1">
                                            Hiệu lực đến: {{ props.qrSettings.expires_at }} 
                                            <span v-if="props.qrSettings.is_expired" class="text-rose-600 font-bold ml-1">(Hết hạn)</span>
                                            <span v-else class="text-emerald-600 font-bold ml-1">(Còn hiệu lực)</span>
                                        </div>
                                    </div>
                                    <Button 
                                        type="button" 
                                        @click="generateDailyQR"
                                        :disabled="isGeneratingQR"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-12 px-4 shadow cursor-pointer"
                                    >
                                        {{ isGeneratingQR ? 'Đang tạo...' : 'Tạo mã QR' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Audit info notice -->
                            <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl flex items-start gap-2 text-[10px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                                <AlertCircle class="size-4 shrink-0 text-indigo-650 dark:text-indigo-400 mt-0.5" />
                                <p><strong>Lưu ý:</strong> Cài đặt mới sẽ lập tức áp dụng cho mọi lượt chấm công hoàn thành tiếp theo và được dùng khi tính toán lại bảng lương nháp tháng này.</p>
                            </div>

                            <!-- Submit button -->
                            <div class="flex justify-start">
                                <Button 
                                    type="submit" 
                                    :disabled="isSavingSettings"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-9 px-4 shadow-sm"
                                >
                                    {{ isSavingSettings ? 'Đang lưu...' : 'Lưu cấu hình' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Card 5: Admin Shift Swap Approvals -->
            <div v-else-if="activeAdminTab === 'swaps'">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                            <RefreshCw class="size-5 text-indigo-650" />
                            Phê Duyệt Đổi Ca Trực
                        </CardTitle>
                        <CardDescription>Xem xét và phê duyệt các yêu cầu trao đổi ca trực đã được cả hai nhân sự thống nhất.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="props.allPendingSwaps && props.allPendingSwaps.length > 0" class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3.5">Nhân viên yêu cầu</th>
                                        <th class="p-3.5">Ca của người yêu cầu</th>
                                        <th class="p-3.5">Nhân viên nhận ca</th>
                                        <th class="p-3.5">Ca của người nhận</th>
                                        <th class="p-3.5">Ghi chú đổi</th>
                                        <th class="p-3.5 text-right">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="sw in props.allPendingSwaps" :key="sw.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                            {{ sw.requester_name }}
                                        </td>
                                        <td class="p-3.5">
                                            <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ sw.requester_shift }}</span>
                                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ sw.requester_date }}</div>
                                        </td>
                                        <td class="p-3.5 font-bold text-slate-800 dark:text-slate-200">
                                            {{ sw.receiver_name }}
                                        </td>
                                        <td class="p-3.5">
                                            <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ sw.receiver_shift }}</span>
                                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ sw.receiver_date }}</div>
                                        </td>
                                        <td class="p-3.5 text-slate-500 font-medium">
                                            {{ sw.notes || '—' }}
                                        </td>
                                        <td class="p-3.5 text-right flex items-center justify-end gap-1.5">
                                            <button 
                                                @click="approveSwap(sw.id)"
                                                class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-xs"
                                                title="Duyệt yêu cầu đổi ca"
                                            >
                                                Phê duyệt
                                            </button>
                                            <button 
                                                @click="openRejectSwapModal(sw.id)"
                                                class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 transition active:scale-95"
                                                title="Từ chối yêu cầu đổi ca"
                                            >
                                                Từ chối
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-16 text-center text-slate-400">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                                <RefreshCw class="size-6 text-slate-350" />
                            </div>
                            <p class="text-xs font-semibold">Không có yêu cầu đổi ca nào đang chờ duyệt</p>
                            <p class="mt-1 text-[11px] text-slate-400">Yêu cầu đổi ca chỉ hiển thị ở đây sau khi cả hai nhân viên đã xác nhận đồng ý.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Card 6: Attendance & Payroll Analytics -->
            <div v-else-if="activeAdminTab === 'analytics'">
                <!-- Analytics Summary KPIs -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                    <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Ca làm tháng này</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between pb-3">
                            <span class="text-3xl font-black text-slate-800 dark:text-slate-100 font-mono">{{ analyticsData.totalAssignments }}</span>
                            <div class="h-8 w-8 rounded-lg bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500">
                                <Calendar class="size-4" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-xs border-indigo-150 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Tỷ lệ đúng giờ</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between pb-3">
                            <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono">{{ analyticsData.punctualityRate }}%</span>
                            <div class="h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 flex items-center justify-center text-indigo-650 dark:text-indigo-400">
                                <Sparkles class="size-4 animate-pulse" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500 font-sans">Đúng giờ</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between pb-3">
                            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400 font-mono">{{ analyticsData.onTimeCount }}</span>
                            <div class="h-8 w-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center text-emerald-600">
                                <CheckCircle2 class="size-4" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-xs border-amber-100 dark:border-amber-950/20 hover:translate-y-[-2px] transition-transform">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold uppercase tracking-wider text-amber-500">Đi trễ</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between pb-3">
                            <span class="text-3xl font-black text-amber-600 dark:text-amber-400 font-mono">{{ analyticsData.lateCount }}</span>
                            <div class="h-8 w-8 rounded-lg bg-amber-50 dark:bg-amber-950/40 flex items-center justify-center text-amber-600">
                                <AlertCircle class="size-4" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="shadow-xs border-rose-100 dark:border-rose-950/20 hover:translate-y-[-2px] transition-transform">
                        <CardHeader class="pb-2">
                            <CardDescription class="text-xs font-bold uppercase tracking-wider text-rose-500">Vắng mặt</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between pb-3">
                            <span class="text-3xl font-black text-rose-600 dark:text-rose-400 font-mono">{{ analyticsData.absentCount }}</span>
                            <div class="h-8 w-8 rounded-lg bg-rose-50 dark:bg-rose-950/40 flex items-center justify-center text-rose-600">
                                <Ban class="size-4" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Detailed Table -->
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650">
                            <Users class="size-5 text-indigo-650" />
                            Báo Cáo Tổng Hợp Công & Dự Tính Lương Trong Tháng
                        </CardTitle>
                        <CardDescription>Bảng thống kê số giờ làm việc thực tế, số lần đi trễ và mức lương tích lũy ước tính của nhân viên.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="analyticsData.employeeStats.length > 0" class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3.5">Nhân viên</th>
                                        <th class="p-3.5">Hình thức lương</th>
                                        <th class="p-3.5">Số ca hoàn thành</th>
                                        <th class="p-3.5">Tổng số giờ làm</th>
                                        <th class="p-3.5">Số lần đi trễ</th>
                                        <th class="p-3.5 text-right">Lương tích lũy dự tính</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="emp in analyticsData.employeeStats" :key="emp.code" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                        <td class="p-3.5">
                                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ emp.name }}</div>
                                            <div class="text-[10px] text-slate-400 mt-0.5 font-mono">{{ emp.code }} · {{ emp.jobTitle }}</div>
                                        </td>
                                        <td class="p-3.5">
                                            <span 
                                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                :class="[
                                                    emp.compensationType === 'hourly' ? 'bg-blue-50 text-blue-600 border border-blue-100 dark:bg-blue-950/20 dark:text-blue-400' : '',
                                                    emp.compensationType === 'shift' ? 'bg-amber-50 text-amber-600 border border-amber-100 dark:bg-amber-950/20 dark:text-amber-400' : '',
                                                    emp.compensationType === 'fixed' ? 'bg-purple-50 text-purple-600 border border-purple-100 dark:bg-purple-950/20 dark:text-purple-400' : ''
                                                ]"
                                            >
                                                {{ 
                                                    emp.compensationType === 'hourly' ? 'Theo giờ' : 
                                                    (emp.compensationType === 'shift' ? 'Theo ca' : 'Lương cứng') 
                                                }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 font-mono text-slate-650 dark:text-slate-350">
                                            {{ emp.completedCount }} ca
                                        </td>
                                        <td class="p-3.5 font-mono font-bold text-slate-700 dark:text-slate-200">
                                            {{ emp.totalHours }} giờ
                                        </td>
                                        <td class="p-3.5 font-mono">
                                            <span 
                                                v-if="emp.lateCount > 0" 
                                                class="text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800 font-bold"
                                            >
                                                {{ emp.lateCount }} lần
                                            </span>
                                            <span v-else class="text-emerald-650 font-bold">0</span>
                                        </td>
                                        <td class="p-3.5 text-right font-mono font-black text-indigo-650 dark:text-indigo-400 text-sm">
                                            {{ emp.estimatedWage.toLocaleString('vi-VN') }} ₫
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="py-16 text-center text-slate-450">
                            <p class="text-xs font-semibold">Chưa có dữ liệu thống kê nào trong tháng</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
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
                            @click="openCheckInFlow" 
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
                <!-- Staff Tabs Switcher -->
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
                        <CalendarDays class="size-3.5" />
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
                        <Clock class="size-3.5" />
                        Đăng ký ca làm việc rảnh
                    </button>
                </div>

                <!-- Card 2.1: Personal Weekly Roster -->
                <div v-if="activeStaffTab === 'roster'">
                    <Card class="shadow-sm">
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
                                v-for="ws in paginatedRoster" 
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
                                <div class="flex items-center gap-3 self-end sm:self-center font-bold">
                                    <!-- Times of check-in/out -->
                                    <div v-if="ws.check_in_at" class="text-right text-[10px] font-mono text-slate-400 leading-tight">
                                        <div>Vào: {{ ws.check_in_at }}</div>
                                        <div v-if="ws.check_out_at">Ra: {{ ws.check_out_at }}</div>
                                    </div>

                                    <button 
                                        v-if="ws.status === 'scheduled'"
                                        type="button"
                                        @click="openSwapModal(ws)"
                                        class="h-7 px-2 border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-slate-900 hover:bg-indigo-50 dark:hover:bg-slate-800 text-indigo-650 dark:text-indigo-400 text-[10px] rounded-lg font-bold flex items-center gap-1 cursor-pointer select-none active:scale-95 transition-all"
                                    >
                                        <RefreshCw class="size-3" />
                                        Đổi ca
                                    </button>

                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0 font-sans" :class="statusColors[ws.status]">
                                        {{ statusLabels[ws.status] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div v-if="rosterTotalPages > 1" class="flex items-center justify-between p-4 border-t bg-slate-50/30 dark:bg-slate-900/10">
                            <span class="text-xs text-slate-500 dark:text-slate-400">
                                Hiển thị trang <strong>{{ rosterPage }}</strong> / <strong>{{ rosterTotalPages }}</strong> (Tổng số <strong>{{ myWeeklySchedules?.length }}</strong> ca)
                            </span>
                            <div class="flex items-center gap-1.5">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="rosterPage === 1"
                                    @click="rosterPage--"
                                    class="h-7 text-xs font-semibold cursor-pointer select-none active:scale-95 disabled:opacity-50"
                                >
                                    Trước
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="rosterPage === rosterTotalPages"
                                    @click="rosterPage++"
                                    class="h-7 text-xs font-semibold cursor-pointer select-none active:scale-95 disabled:opacity-50"
                                >
                                    Sau
                                </Button>
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

                <!-- Shift Swap Requests Panel -->
                <Card class="shadow-sm mt-6">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-650 dark:text-indigo-400">
                            <RefreshCw class="size-5 animate-spin-slow" />
                            Danh Sách Yêu Cầu Đổi Ca Trực
                        </CardTitle>
                        <CardDescription>Theo dõi tình trạng các đề xuất đổi ca làm việc của bạn hoặc nhận được từ đồng nghiệp.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="props.pendingSwapRequests && props.pendingSwapRequests.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div 
                                v-for="swap in props.pendingSwapRequests" 
                                :key="swap.id" 
                                class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3 text-xs"
                            >
                                <div class="space-y-1">
                                    <!-- Heading description -->
                                    <div class="font-bold flex flex-wrap items-center gap-1.5 text-slate-800 dark:text-slate-205">
                                        <span v-if="swap.is_requester" class="text-indigo-600 dark:text-indigo-400 font-extrabold">[ĐÃ GỬI]</span>
                                        <span v-else class="text-emerald-600 dark:text-emerald-400 font-extrabold">[NHẬN ĐƯỢC]</span>
                                        
                                        <span v-if="swap.is_requester">
                                            Bạn muốn đổi ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.requester_shift }}</strong> ({{ swap.requester_date }}) 
                                            lấy ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.receiver_shift }}</strong> của <strong>{{ swap.receiver_name }}</strong>
                                        </span>
                                        <span v-else>
                                            <strong>{{ swap.requester_name }}</strong> đề xuất đổi ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.requester_shift }}</strong> ({{ swap.requester_date }})
                                            lấy ca <strong class="font-mono text-indigo-600 dark:text-indigo-400">{{ swap.receiver_shift }}</strong> của bạn
                                        </span>
                                    </div>
                                    <div class="text-slate-400 font-medium italic mt-0.5">Ghi chú: {{ swap.notes || 'Không có ghi chú' }}</div>
                                </div>

                                <!-- Status & Action Buttons -->
                                <div class="flex items-center gap-3 self-end md:self-center font-bold">
                                    <span 
                                        class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase shrink-0" 
                                        :class="[
                                            swap.status === 'pending' ? 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400' : '',
                                            swap.status === 'accepted' ? 'bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-950/20 dark:text-blue-400' : '',
                                            swap.status === 'approved' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400' : '',
                                            swap.status === 'rejected' ? 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/20 dark:text-rose-400' : '',
                                            swap.status === 'cancelled' ? 'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-900/20 dark:text-slate-400' : ''
                                        ]"
                                    >
                                        {{ 
                                            swap.status === 'pending' ? 'Chờ đồng ý' : 
                                            (swap.status === 'accepted' ? 'Chờ duyệt' : 
                                            (swap.status === 'approved' ? 'Đã duyệt' : 
                                            (swap.status === 'rejected' ? 'Đã từ chối' : 'Đã hủy'))) 
                                        }}
                                    </span>

                                    <!-- Actions based on pending / accepted status -->
                                    <template v-if="swap.status === 'pending'">
                                        <button 
                                            v-if="!swap.is_requester"
                                            type="button"
                                            @click="acceptSwapRequest(swap.id)"
                                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                                        >
                                            Đồng ý
                                        </button>
                                        <button 
                                            type="button"
                                            @click="cancelSwapRequest(swap.id)"
                                            class="bg-rose-600 hover:bg-rose-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                                        >
                                            {{ swap.is_requester ? 'Hủy' : 'Từ chối' }}
                                        </button>
                                    </template>
                                    <template v-else-if="swap.status === 'accepted'">
                                        <button 
                                            type="button"
                                            @click="cancelSwapRequest(swap.id)"
                                            class="bg-rose-600 hover:bg-rose-700 text-white font-bold h-7 px-3 text-[10px] rounded-lg cursor-pointer select-none active:scale-95 transition-all"
                                        >
                                            Hủy
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center text-slate-400">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-2">
                                <RefreshCw class="size-5 text-slate-350" />
                            </div>
                            <p class="text-xs font-semibold">Không có yêu cầu đổi ca nào đang chờ xử lý</p>
                        </div>
                    </CardContent>
                </Card>
                </div>

                <!-- 2.2 EMPLOYEE SHIFT REGISTRATION CARD -->
                <div v-else-if="activeStaffTab === 'register'">
                    <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <CardTitle class="text-base flex items-center gap-1.5 text-emerald-650 dark:text-emerald-400">
                                <Clock class="size-5 text-emerald-650 dark:text-emerald-450" />
                                Đăng Ký Ca Làm Việc Rảnh Tuần Này
                            </CardTitle>
                            <CardDescription>Chọn các ca trực rảnh trong tuần của bạn. Chủ quán sẽ dựa trên thông tin này để xếp lịch phù hợp.</CardDescription>
                        </div>
                        <Button
                            @click="saveRegistrations"
                            :disabled="isSavingRegs"
                            class="bg-emerald-650 hover:bg-emerald-700 text-white font-semibold text-xs h-8 flex items-center gap-1.5 shadow"
                        >
                            <CheckCircle2 class="size-4" />
                            {{ isSavingRegs ? 'Đang lưu...' : 'Lưu đăng ký ca rảnh' }}
                        </Button>
                    </CardHeader>

                    <CardContent class="p-4">
                        <div class="border rounded-2xl overflow-hidden bg-white dark:bg-slate-950">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-55 dark:bg-slate-900 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3.5 border-r w-[120px]">Thứ trong tuần</th>
                                        <th class="p-3.5">Các ca có thể nhận việc (Tích chọn để đăng ký)</th>
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
                                            <button
                                                v-for="shift in shifts"
                                                :key="shift.id"
                                                type="button"
                                                @click="toggleShiftSelection(shift.id, day.dateStr)"
                                                :class="[
                                                    'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-all duration-150 cursor-pointer select-none active:scale-95',
                                                    isShiftSelected(shift.id, day.dateStr)
                                                        ? 'bg-emerald-50 border-emerald-200 text-emerald-600 dark:bg-emerald-950/20 dark:border-emerald-900/30 dark:text-emerald-400 font-extrabold'
                                                        : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300 dark:bg-slate-900 dark:border-slate-800 dark:text-slate-400 font-medium'
                                                ]"
                                            >
                                                <span class="size-1.5 rounded-full" :class="isShiftSelected(shift.id, day.dateStr) ? 'bg-emerald-600 dark:bg-emerald-400 animate-pulse' : 'bg-slate-300 dark:bg-slate-700'" />
                                                <span>{{ shift.name.split(' (')[0] }}</span>
                                                <span class="text-[9px] font-mono opacity-70">({{ shift.start }} - {{ shift.end }})</span>
                                                <Check v-if="isShiftSelected(shift.id, day.dateStr)" class="size-3 text-emerald-600 dark:text-emerald-400 shrink-0 ml-0.5" />
                                            </button>
                                            <div v-if="!shifts || !shifts.length" class="text-[10px] text-slate-400 italic">
                                                Không có ca làm việc khả dụng
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
                </div>
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

                    <!-- Violation Integration Checkbox -->
                    <div class="flex items-center space-x-2 p-1 pt-2">
                        <input 
                            id="apply-violation-check" 
                            type="checkbox" 
                            v-model="applyViolation" 
                            class="rounded border-slate-300 text-indigo-650 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                        />
                        <Label for="apply-violation-check" class="text-xs font-bold text-rose-600 cursor-pointer select-none">
                            Lập biên bản vi phạm kỷ luật & Khấu trừ lương
                        </Label>
                    </div>

                    <!-- Expandable Violation Fields -->
                    <div v-if="applyViolation" class="space-y-3 p-3 bg-rose-50/50 dark:bg-rose-950/20 border border-rose-100 dark:border-rose-900/40 rounded-xl animate-in fade-in slide-in-from-top-1 duration-150">
                        <div class="grid gap-1.5">
                            <Label for="violation-notes" class="text-[10px] font-bold text-rose-500 uppercase tracking-wide">Mô tả vi phạm kỷ luật</Label>
                            <Input 
                                id="violation-notes" 
                                type="text" 
                                v-model="violationNotes" 
                                placeholder="Ví dụ: Đi trễ quá 30 phút / Vắng mặt không lý do..."
                                class="h-8 text-xs border-rose-200 focus-visible:ring-rose-500" 
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="penalty-amount" class="text-[10px] font-bold text-rose-500 uppercase tracking-wide">Số tiền khấu trừ phạt (VND)</Label>
                            <Input 
                                id="penalty-amount" 
                                type="number" 
                                v-model.number="penaltyAmount" 
                                min="0" 
                                step="1000"
                                placeholder="Nhập số tiền phạt ví dụ: 50000"
                                class="h-8 text-xs border-rose-200 focus-visible:ring-rose-500 font-mono font-bold" 
                            />
                        </div>
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

        <!-- ========================================== -->
        <!-- 4. SECURE CHECK-IN MODAL (GPS & QR) -->
        <!-- ========================================== -->
        <div v-if="checkInModalOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Clock class="size-5" />
                            Xác Thực Vào Ca (Check-In)
                        </CardTitle>
                        <CardDescription>Hệ thống tự động xác minh vị trí địa lý (GPS) và mã QR an toàn để ghi nhận ca trực.</CardDescription>
                    </div>
                    <button @click="checkInModalOpen = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <!-- GPS Verification Status -->
                    <div v-if="props.gpsSettings?.latitude && props.gpsSettings?.longitude" class="space-y-2">
                        <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Xác minh định vị GPS</Label>
                        <div 
                            class="p-3 border rounded-xl flex items-center gap-3 text-xs" 
                            :class="[
                                gpsStatus === 'fetching' ? 'bg-amber-50/50 border-amber-200 text-amber-600' : '',
                                gpsStatus === 'success' ? 'bg-emerald-50/55 border-emerald-200 text-emerald-600' : '',
                                gpsStatus === 'error' ? 'bg-rose-50/50 border-rose-200 text-rose-600' : ''
                            ]"
                        >
                            <span v-if="gpsStatus === 'fetching'" class="size-2 rounded-full bg-amber-500 animate-ping"></span>
                            <span v-if="gpsStatus === 'success'" class="size-2 rounded-full bg-emerald-600"></span>
                            <span v-if="gpsStatus === 'error'" class="size-2 rounded-full bg-rose-600"></span>

                            <div class="flex-1">
                                <p v-if="gpsStatus === 'fetching'" class="font-bold">Đang lấy tọa độ GPS của thiết bị...</p>
                                <p v-if="gpsStatus === 'success'" class="font-bold">Định vị GPS hợp lệ! (Kinh độ: {{ gpsCoords.longitude }}, Vĩ độ: {{ gpsCoords.latitude }})</p>
                                <p v-if="gpsStatus === 'error'" class="font-bold">Không thể xác minh định vị GPS</p>
                                <p v-if="gpsStatus === 'error'" class="text-[10px] text-rose-500 mt-0.5 font-medium">{{ gpsErrorMsg }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Input (if configured) -->
                    <div v-if="props.qrSettings?.code && !props.qrSettings?.is_expired" class="space-y-2">
                        <Label for="qr-input" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Nhập mã QR của ca trực hôm nay</Label>
                        <Input 
                            id="qr-input" 
                            type="text" 
                            v-model="inputQrCode" 
                            placeholder="Mã QR hiển thị trên bảng thông tin..."
                            required 
                            class="h-10 text-sm font-mono tracking-widest text-center" 
                        />
                    </div>

                    <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-xl flex items-start gap-2 text-[10px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                        <AlertCircle class="size-4 shrink-0 text-indigo-650 mt-0.5" />
                        <p><strong>Lưu ý:</strong> Vị trí GPS của bạn phải nằm trong bán kính {{ props.gpsSettings?.radius ?? 100 }}m của cửa hàng để có thể check-in thành công.</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="checkInModalOpen = false">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitCheckIn" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold"
                            :disabled="isCheckingIn || gpsStatus !== 'success' || (props.qrSettings?.code && !props.qrSettings?.is_expired && !inputQrCode)"
                        >
                            {{ isCheckingIn ? 'Đang xác thực...' : 'Xác nhận Vào Ca' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ========================================== -->
        <!-- 5. EMPLOYEE SHIFT SWAP PROPOSAL MODAL -->
        <!-- ========================================== -->
        <div v-if="swapModalOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <RefreshCw class="size-5" />
                            Đề Xuất Đổi Ca Trực
                        </CardTitle>
                        <CardDescription>Gửi yêu cầu trao đổi ca làm việc của bạn cho một đồng nghiệp trong tuần này.</CardDescription>
                    </div>
                    <button @click="swapModalOpen = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <!-- Source Shift Info -->
                    <div>
                        <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ca trực của bạn muốn đổi</Label>
                        <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 rounded-xl mt-1 text-xs">
                            <span class="font-bold text-indigo-700 dark:text-indigo-400">Ca {{ selectedMyShift?.shift_name }}</span> · {{ selectedMyShift?.day_vn }} ({{ selectedMyShift?.date }})
                            <div class="text-[10px] text-slate-400 mt-0.5">Khung giờ: {{ selectedMyShift?.shift_time }}</div>
                        </div>
                    </div>

                    <!-- Target Shift Selection -->
                    <div class="grid gap-2">
                        <Label for="target-shift-select" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Chọn ca của đồng nghiệp muốn đổi cùng</Label>
                        <select 
                            id="target-shift-select"
                            v-model="selectedTargetShiftId"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-xs ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option :value="null" disabled>-- Chọn ca trực của đồng nghiệp --</option>
                            <option 
                                v-for="ts in swappableShifts" 
                                :key="ts.id" 
                                :value="ts.id"
                            >
                                {{ ts.employee_name }} · Ca {{ ts.shift_name }} ({{ ts.shift_time }}) - {{ ts.day }} ({{ ts.date }})
                            </option>
                        </select>
                        <p v-if="swappableShifts.length === 0" class="text-[10px] text-rose-500 font-semibold italic">Không có ca trực nào khác của đồng nghiệp trong tuần này để đổi.</p>
                    </div>

                    <!-- Swap Notes -->
                    <div class="grid gap-1.5">
                        <Label for="swap-reason" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do xin đổi ca (Ghi chú)</Label>
                        <Input 
                            id="swap-reason" 
                            type="text" 
                            v-model="swapNotes" 
                            placeholder="Ví dụ: Bận việc gia đình đột xuất, nhờ đổi hộ..."
                            required 
                            class="h-10 text-xs" 
                        />
                    </div>

                    <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[10px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                        <AlertCircle class="size-4 shrink-0 text-amber-600 mt-0.5" />
                        <p><strong>Quy trình phê duyệt:</strong> Sau khi gửi, đồng nghiệp nhận được yêu cầu phải nhấn "Đồng ý", sau đó Quản lý/Owner duyệt thì ca đổi mới chính thức có hiệu lực.</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="swapModalOpen = false">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitSwapRequest" 
                            class="bg-indigo-650 hover:bg-indigo-755 text-white font-bold"
                            :disabled="isSubmittingSwap || !selectedTargetShiftId"
                        >
                            {{ isSubmittingSwap ? 'Đang gửi...' : 'Gửi Yêu Cầu' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ========================================== -->
        <!-- 6. ADMIN PORTAL MODAL: REJECT SHIFT SWAP -->
        <!-- ========================================== -->
        <div v-if="isRejectingSwap" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 print:hidden">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-rose-600">
                            <Ban class="size-5" />
                            Từ Chối Yêu Cầu Đổi Ca
                        </CardTitle>
                        <CardDescription>Vui lòng nhập lý do từ chối yêu cầu đổi ca để thông báo cho nhân viên.</CardDescription>
                    </div>
                    <button @click="isRejectingSwap = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground cursor-pointer">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                
                <CardContent class="pt-4 space-y-4">
                    <div class="grid gap-1.5">
                        <Label for="reject-reason" class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do từ chối</Label>
                        <Input 
                            id="reject-reason" 
                            type="text" 
                            v-model="rejectNotes" 
                            placeholder="Ví dụ: Không cân bằng được vai trò nhân sự trong ca..."
                            required 
                            class="h-10 text-xs border-rose-200 focus-visible:ring-rose-500" 
                        />
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 pt-2 border-t">
                        <Button type="button" variant="outline" size="sm" @click="isRejectingSwap = false">Hủy</Button>
                        <Button 
                            type="button" 
                            size="sm" 
                            @click="submitRejectSwap" 
                            class="bg-rose-650 hover:bg-rose-755 text-white font-bold"
                            :disabled="!rejectNotes"
                        >
                            Xác nhận từ chối
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
