<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Users,
    Plus,
    Calendar,
    CheckCircle2,
    AlertCircle,
    Sparkles,
    UserCheck,
    Mail,
    Pencil,
    ToggleLeft,
    ToggleRight,
    X,
    Trash2,
    Settings,
    RefreshCw,
    ArrowUpDown,
    Search,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    status: string;
    role: string;
    compensation_type?: 'fixed' | 'hourly' | 'shift';
    pay_rate?: number;
    base_salary?: number;
    rating_star?: number | null;
    rating_count?: number;
    date_of_birth?: string | null;
    citizen_id_number?: string | null;
    address?: string | null;
    citizen_id_front_url?: string | null;
    citizen_id_back_url?: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
};
type Shift = { id: number; name: string; start: string; end: string };
type Assignment = {
    id?: number;
    day: string;
    employee_name: string;
    shift_name: string;
    shift_id?: number;
    start_time?: string;
    end_time?: string;
};
type Swap = {
    id: number;
    notes: string | null;
    status: string;
    created_at: string;
    requester_name: string;
    requester_shift: string;
    requester_date: string;
    receiver_name: string;
    receiver_shift: string;
    receiver_date: string;
};

const props = defineProps<{
    employees: Employee[];
    shifts: Shift[];
    schedules: Assignment[];
    registrations?: Array<{
        employee_name: string;
        shift_name: string;
        day: string;
    }>;
    leaveRequests?: any[];
    pendingSwaps?: Swap[];
    autoSchedule?: boolean;
    dailyForecasts?: Record<
        string,
        {
            date: string;
            condition: string;
            temperature: number;
            demand_level: string;
            demand_label: string;
            shifts: Array<{
                shift_id: number;
                shift_name: string;
                optimal_staff: number;
                current_staff: number;
                status: string;
            }>;
        }
    >;
    branches: Array<{ id: number; name: string }>;
    activeBranchId: number | null;
    branchScope: string;
    isBranchManager: boolean;
}>();

const showAddEmployee = ref(false);
const editingEmployee = ref<Employee | null>(null);
const empSearchQuery = ref('');
const empCurrentPage = ref(1);
const empPerPage = 8;

const filteredEmployees = computed(() => {
    const q = empSearchQuery.value.trim().toLowerCase();

    if (!q) {
        return props.employees;
    }

    return props.employees.filter(
        (e) =>
            e.full_name.toLowerCase().includes(q) ||
            e.employee_code.toLowerCase().includes(q) ||
            (e.job_title ?? '').toLowerCase().includes(q) ||
            (e.phone ?? '').toLowerCase().includes(q),
    );
});

const totalEmpPages = computed(() =>
    Math.ceil(filteredEmployees.value.length / empPerPage) || 1,
);

const paginatedEmployees = computed(() => {
    const start = (empCurrentPage.value - 1) * empPerPage;

    return filteredEmployees.value.slice(start, start + empPerPage);
});

const visibleEmpPages = computed(() => {
    const total = totalEmpPages.value;
    const current = empCurrentPage.value;
    const pages: number[] = [];

    let start = Math.max(1, current - 2);
    const end = Math.min(total, start + 4);

    if (end - start < 4) {
        start = Math.max(1, end - 4);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

watch(empSearchQuery, () => {
    empCurrentPage.value = 1;
});

watch(totalEmpPages, (newTotal) => {
    if (empCurrentPage.value > newTotal) {
        empCurrentPage.value = Math.max(1, newTotal);
    }
});

const employeeForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    job_title: 'Thu Ngân',
    compensation_type: 'fixed' as 'fixed' | 'hourly' | 'shift',
    pay_rate: 25000,
    base_salary: 8000000,
    date_of_birth: '',
    address: '',
    citizen_id_number: '',
    citizen_id_front: null as File | null,
    citizen_id_back: null as File | null,
    hire_date: new Date().toISOString().split('T')[0],
    branch_id: props.branches[0]?.id ?? '',
});

const editForm = useForm({
    full_name: '',
    phone: '',
    job_title: '',
    status: 'active' as 'active' | 'inactive',
    role: 'cashier',
    compensation_type: 'fixed' as 'fixed' | 'hourly' | 'shift',
    pay_rate: 25000,
    base_salary: 8000000,
    date_of_birth: '',
    address: '',
    citizen_id_number: '',
    citizen_id_front: null as File | null,
    citizen_id_back: null as File | null,
    branch_id: '',
});

const openEditEmployee = (emp: any) => {
    editingEmployee.value = emp;
    editForm.full_name = emp.full_name;
    editForm.phone = emp.phone ?? '';
    editForm.job_title = emp.job_title ?? '';
    editForm.status = emp.status as 'active' | 'inactive';
    editForm.role = emp.role;
    editForm.compensation_type = emp.compensation_type ?? 'fixed';
    editForm.pay_rate = emp.pay_rate ?? 25000;
    editForm.base_salary = emp.base_salary ?? 8000000;
    editForm.date_of_birth = emp.date_of_birth ?? '';
    editForm.address = emp.address ?? '';
    editForm.citizen_id_number = emp.citizen_id_number ?? '';
    editForm.citizen_id_front = null;
    editForm.citizen_id_back = null;
    editForm.branch_id = emp.branch_id ?? '';
};

const submitEditEmployee = () => {
    if (!editingEmployee.value) {
        return;
    }

    editForm
        .transform((data: any) => ({
            ...data,
            _method: 'PATCH',
        }))
        .post(`/employees/${editingEmployee.value.id}`, {
            onSuccess: () => {
                editingEmployee.value = null;
                editForm.reset();
            },
        });
};

const toggleEmployeeStatus = (emp: Employee) => {
    router.patch(`/employees/${emp.id}/toggle-status`);
};

const handleRoleChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;

    if (val === 'cashier') {
        employeeForm.job_title = 'Thu Ngân';
    } else if (val === 'kitchen') {
        employeeForm.job_title = 'Nhân Viên Bếp';
    } else if (val === 'manager') {
        employeeForm.job_title = 'Quản Lý Cửa Hàng';
    } else if (val === 'waiter') {
        employeeForm.job_title = 'Nhân Viên Order';
    }
};

const submitEmployee = () => {
    employeeForm.post('/employees', {
        onSuccess: () => {
            employeeForm.reset();
            showAddEmployee.value = false;
        },
    });
};

const roleLabels: Record<string, string> = {
    owner: 'Chủ quán',
    manager: 'Quản lý',
    cashier: 'Thu ngân (Cashier)',
    kitchen: 'Đầu bếp/Bếp (Kitchen)',
    waiter: 'Nhân viên order',
    staff: 'Nhân viên phục vụ',
};

type RoleOption = { value: string; label: string; disabled?: boolean };

const managerAllowedRoles = ['cashier', 'waiter', 'kitchen'];
const allRoleOptions: RoleOption[] = [
    { value: 'cashier', label: 'Thu ngân (Bán hàng)' },
    { value: 'kitchen', label: 'Nhà bếp (Chuẩn bị món)' },
    { value: 'manager', label: 'Quản lý cửa hàng (Chi nhánh)' },
    { value: 'waiter', label: 'Nhân viên order (Phục vụ)' },
    { value: 'inventory_staff', label: 'Nhân viên Kho Chi Nhánh' },
    { value: 'warehouse_staff', label: 'Nhân viên Kho Tổng' },
    { value: 'warehouse_manager', label: 'Trưởng Kho Tổng' },
    { value: 'operations_inspector', label: 'Giám sát viên Vận hành / Thanh tra' },
];

const createRoleOptions = computed(() =>
    props.isBranchManager
        ? allRoleOptions.filter((option) => managerAllowedRoles.includes(option.value))
        : allRoleOptions,
);

const editRoleOptions = computed(() => {
    if (!props.isBranchManager || !editForm.role || managerAllowedRoles.includes(editForm.role)) {
        return createRoleOptions.value;
    }

    return [
        {
            value: editForm.role,
            label: `${roleLabels[editForm.role] ?? editForm.role} (Đang giữ nguyên)`,
            disabled: true,
        },
        ...createRoleOptions.value,
    ];
});

const roleColors: Record<string, string> = {
    owner: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    manager: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    cashier:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    kitchen:
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    waiter: 'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-400',
    staff: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
};

const avatarColors = [
    'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-400',
    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-400',
    'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-400',
];

function avatarColor(name: string): string {
    let hash = 0;

    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }

    return avatarColors[Math.abs(hash) % avatarColors.length];
}

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

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
            dateStr: `${yyyy}-${mm}-${dd}`,
        };
    });
});

// Helper function to clean shift names (remove trailing time string like " (06:00 - 14:00)")
function cleanShiftName(name: string): string {
    if (!name) {
        return '';
    }

    return name.split(' (')[0].trim();
}

function isShiftEnded(dateStr?: string, endTimeStr?: string): boolean {
    if (!dateStr || !endTimeStr) {
        return false;
    }

    const parts = endTimeStr.split(':').map(Number);
    const hours = parts[0];
    const minutes = parts[1];

    if (isNaN(hours) || isNaN(minutes)) {
        return false;
    }

    const endDateTime = new Date(dateStr);
    endDateTime.setHours(hours, minutes, 0, 0);

    return new Date() > endDateTime;
}

// ── LOCAL DYNAMIC SHIFT & SCHEDULE STATE (DATABASE DRIVEN) ──
const shiftsState = ref<Shift[]>(
    props.shifts
        ? props.shifts.map((s) => ({ ...s, name: cleanShiftName(s.name) }))
        : [],
);
const schedulesState = ref<Assignment[]>(
    props.schedules ? [...props.schedules] : [],
);

// Keep state perfectly in sync when Inertia reloads database props
watch(
    () => props.shifts,
    (newShifts) => {
        shiftsState.value = newShifts
            ? newShifts.map((s) => ({ ...s, name: cleanShiftName(s.name) }))
            : [];
    },
    { deep: true },
);

watch(
    () => props.schedules,
    (newSchedules) => {
        schedulesState.value = newSchedules ? [...newSchedules] : [];
    },
    { deep: true },
);

const isAutoSchedule = ref(!!props.autoSchedule);
watch(
    () => props.autoSchedule,
    (newVal) => {
        isAutoSchedule.value = !!newVal;
    },
);

const handleToggleAutoSchedule = async () => {
    if (!isAutoSchedule.value) {
        if (
            !(await confirmDialog({
                title: 'Xác nhận thao tác',
                description:
                    'Kích hoạt Chế độ xếp lịch tự động? AI sẽ ưu tiên tối đa nhân viên chủ động đăng ký ca rảnh trước, sau đó ưu tiên theo điểm đánh giá KPI & tín nhiệm cao, đồng thời cân bằng khối lượng công việc trong tuần.',
                variant: 'default',
            }))
        ) {
            return;
        }
    }

const targetState = !isAutoSchedule.value;
    router.post(
        '/employees/schedules/toggle-auto',
        {
            enabled: targetState,
        },
        {
            onSuccess: () => {
                isAutoSchedule.value = targetState;
            },
        },
    );
};

// Modals State
const showShiftConfigModal = ref(false);
const showQuickScheduleModal = ref(false);
const showAssignModal = ref(false);

const showLeaveModal = ref(false);
const showRejectModal = ref<number | null>(null);
const rejectReason = ref('');

const showApproveReplacementModal = ref(false);
const replacementLeaveId = ref<number | null>(null);
const replacementLeaveData = ref<any>(null);
const selectedReplacements = ref<Record<number, string>>({});
const isLoadingReplacements = ref(false);

const leaveForm = useForm({
    employee_id: '',
    leave_type: 'annual',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date().toISOString().split('T')[0],
    reason: '',
});

const leaveTypeLabels: Record<string, string> = {
    annual: 'Nghỉ phép năm',
    sick: 'Nghỉ ốm',
    unpaid: 'Nghỉ không lương',
    emergency: 'Nghỉ đột xuất (Hủy ca tự động)',
    resignation: 'Xin thôi việc (Khóa & Xóa mềm)',
};

const leaveTypeColors: Record<string, string> = {
    annual: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/50',
    sick: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200/50',
    unpaid: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300 border border-slate-200/50',
    emergency:
        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/50',
    resignation:
        'bg-red-200 text-red-800 dark:bg-red-950/60 dark:text-red-200 border border-red-300/50',
};

const leaveStatusLabels: Record<string, string> = {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    rejected: 'Đã từ chối',
};

const leaveStatusColors: Record<string, string> = {
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    approved:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    rejected:
        'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

function openLeaveModal() {
    if (props.employees.length > 0) {
        leaveForm.employee_id = String(props.employees[0].id);
    }

    showLeaveModal.value = true;
}

function submitLeaveRequest() {
    leaveForm.post('/employees/leaves', {
        onSuccess: () => {
            import('vue-sonner').then((m) =>
                m.toast.success('Đã nộp đơn xin nghỉ thành công!'),
            );
            showLeaveModal.value = false;
            leaveForm.reset('reason');
        },
        onError: () =>
            import('vue-sonner').then((m) =>
                m.toast.error('Không thể nộp đơn.'),
            ),
    });
}

async function startApproveLeave(leave: any) {
    if (leave.leave_type === 'resignation') {
        if (
            !(await confirmDialog({
                title: 'Xác nhận thao tác',
                description:
                    'Bạn có chắc chắn muốn phê duyệt đơn thôi việc này? Tài khoản nhân viên sẽ bị khóa và xóa mềm.',
            }))
        ) {
            return;
        }

        router.patch(
            `/employees/leaves/${leave.id}/approve`,
            {},
            {
                onSuccess: () =>
                    import('vue-sonner').then((m) =>
                        m.toast.success(
                            'Đã phê duyệt đơn thôi việc thành công!',
                        ),
                    ),
                onError: () =>
                    import('vue-sonner').then((m) =>
                        m.toast.error('Lỗi khi phê duyệt.'),
                    ),
            },
        );

        return;
    }

    isLoadingReplacements.value = true;
    axios
        .get(`/employees/leaves/${leave.id}/replacements`)
        .then(async (res) => {
            if (
                res.data.success &&
                res.data.assignments &&
                res.data.assignments.length > 0
            ) {
                replacementLeaveId.value = leave.id;
                replacementLeaveData.value = res.data;
                selectedReplacements.value = {};
                res.data.assignments.forEach((assignment: any) => {
                    if (
                        assignment.suggestions &&
                        assignment.suggestions.length > 0
                    ) {
                        selectedReplacements.value[assignment.assignment_id] =
                            String(assignment.suggestions[0].id);
                    } else {
                        selectedReplacements.value[assignment.assignment_id] =
                            '';
                    }
                });
                showApproveReplacementModal.value = true;
            } else {
                if (
                    !(await confirmDialog({
                        title: 'Xác nhận thao tác',
                        description:
                            'Bạn có chắc chắn muốn phê duyệt đơn nghỉ này?',
                        variant: 'default',
                    }))
                ) {
                    return;
                }

                router.patch(
                    `/employees/leaves/${leave.id}/approve`,
                    {},
                    {
                        onSuccess: () =>
                            import('vue-sonner').then((m) =>
                                m.toast.success('Đã phê duyệt đơn thành công!'),
                            ),
                        onError: () =>
                            import('vue-sonner').then((m) =>
                                m.toast.error('Lỗi khi phê duyệt.'),
                            ),
                    },
                );
            }
        })
        .catch(async (err) => {
            console.error(err);

            if (
                !(await confirmDialog({
                    title: 'Xác nhận thao tác',
                    description:
                        'Bạn có chắc chắn muốn phê duyệt đơn nghỉ này?',
                    variant: 'default',
                }))
            ) {
                return;
            }

            router.patch(
                `/employees/leaves/${leave.id}/approve`,
                {},
                {
                    onSuccess: () =>
                        import('vue-sonner').then((m) =>
                            m.toast.success('Đã phê duyệt đơn thành công!'),
                        ),
                    onError: () =>
                        import('vue-sonner').then((m) =>
                            m.toast.error('Lỗi khi phê duyệt.'),
                        ),
                },
            );
        })
        .finally(() => {
            isLoadingReplacements.value = false;
        });
}

function submitApproveWithReplacements() {
    if (!replacementLeaveId.value) {
        return;
    }

    router.patch(
        `/employees/leaves/${replacementLeaveId.value}/approve`,
        {
            replacements: selectedReplacements.value,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success(
                        'Đã phê duyệt đơn nghỉ và thế chỗ thành công!',
                    ),
                );
                showApproveReplacementModal.value = false;
                replacementLeaveId.value = null;
                replacementLeaveData.value = null;
                selectedReplacements.value = {};
            },
            onError: () =>
                import('vue-sonner').then((m) =>
                    m.toast.error('Lỗi khi phê duyệt và thế chỗ.'),
                ),
        },
    );
}

function submitRejectLeave() {
    if (!rejectReason.value) {
        import('vue-sonner').then((m) =>
            m.toast.error('Vui lòng nhập lý do từ chối.'),
        );

        return;
    }

    router.patch(
        `/employees/leaves/${showRejectModal.value}/reject`,
        {
            rejection_reason: rejectReason.value,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã từ chối đơn xin nghỉ.'),
                );
                showRejectModal.value = null;
                rejectReason.value = '';
            },
            onError: () =>
                import('vue-sonner').then((m) =>
                    m.toast.error('Lỗi khi từ chối.'),
                ),
        },
    );
}

const currentAssignDay = ref('');
const currentAssignDayLabel = computed(() => {
    const day = weekDaysWithDates.value.find(
        (d) => d.key === currentAssignDay.value,
    );

    return day ? day.fullLabel : '';
});

// Danh sách nhân sự thật của nhà hàng để phân ca — rỗng nếu chưa có ai, không dùng tên giả.
const availableEmployeesList = computed(() => {
    return (props.employees ?? []).map((e) => e.full_name);
});

// Forms
const assignForm = ref({
    employee_name: '',
    shift_name: '',
    shift_id: null as number | null,
});

// Shift Config Operations
const addShift = () => {
    const tempId = Date.now();
    const count = shiftsState.value.length + 1;
    shiftsState.value.push({
        id: tempId,
        name: `Ca Mới ${count}`,
        start: '09:00',
        end: '17:00',
    });
};

const deleteShift = (id: number) => {
    shiftsState.value = shiftsState.value.filter((s) => s.id !== id);
};

const saveShiftsConfig = () => {
    router.post(
        '/employees/shifts/sync',
        { shifts: shiftsState.value },
        {
            onSuccess: () => {
                showShiftConfigModal.value = false;
            },
        },
    );
};

const openQuickScheduleModal = () => {
    showQuickScheduleModal.value = true;
};

const submitQuickSchedule = async () => {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thiết lập ca nhanh',
            description:
                'Hệ thống sẽ lấy đúng thời điểm bạn bấm Xếp ca làm mốc. Hôm nay chỉ xếp các ca có giờ bắt đầu từ thời điểm đó trở đi; từ ngày mai đến Chủ nhật sẽ xếp các ca trống. Các ca đã có và đã khóa được giữ nguyên.',
            variant: 'default',
        }))
    ) {
        return;
    }

    router.post(
        '/employees/schedules/quick-auto',
        {},
        {
            onSuccess: () => {
                showQuickScheduleModal.value = false;
            },
            onError: (errors: any) =>
                import('vue-sonner').then((m) =>
                    m.toast.error(
                        errors.quick_schedule || 'Không thể xếp ca nhanh.',
                    ),
                ),
        },
    );
};

// Assignment Operations
const openAssignModal = (dayKey: string) => {
    if (availableEmployeesList.value.length === 0) {
        import('vue-sonner').then((m) =>
            m.toast.error(
                'Chưa có nhân viên nào — vui lòng thêm nhân sự trước khi phân ca.',
            ),
        );

        return;
    }

    currentAssignDay.value = dayKey;
    assignForm.value.employee_name = availableEmployeesList.value[0] ?? '';
    const firstShift = shiftsState.value[0];
    assignForm.value.shift_id = firstShift?.id ?? null;
    assignForm.value.shift_name = firstShift?.name
        ? firstShift.name.split(' (')[0]
        : 'Ca Mới';
    showAssignModal.value = true;
};

const submitAssignment = async () => {
    // 1. Check duplicate day assignment
    const alreadyScheduled = props.schedules?.some(
        (s) =>
            s.employee_name === assignForm.value.employee_name &&
            s.day === currentAssignDay.value,
    );

    // 2. Check approved leave
    const targetDayDate =
        weekDaysWithDates.value.find((d) => d.key === currentAssignDay.value)
            ?.dateStr || '';
    const employeeObj = props.employees.find(
        (e) => e.full_name === assignForm.value.employee_name,
    );
    const onLeave =
        employeeObj &&
        props.leaveRequests &&
        props.leaveRequests.some(
            (lr) =>
                lr.employee_id === employeeObj.id &&
                lr.status === 'approved' &&
                targetDayDate >= lr.start_date &&
                targetDayDate <= lr.end_date,
        );

    // 3. Check weekly limit
    const weeklyCount =
        props.schedules?.filter(
            (s) => s.employee_name === assignForm.value.employee_name,
        ).length ?? 0;

    let warningMsg = '';

    if (alreadyScheduled) {
        warningMsg += `⚠️ Nhân sự này đã được xếp ca trực vào ${currentAssignDayLabel.value}.\n`;
    }

    if (onLeave) {
        warningMsg += `⚠️ Nhân sự đang trong thời gian NGHỈ PHÉP đã được phê duyệt ngày ${targetDayDate}.\n`;
    }

    if (weeklyCount >= 6) {
        warningMsg += `⚠️ Nhân sự đã làm đủ ${weeklyCount} ca trong tuần này (vượt giới hạn tối đa 6 ca).\n`;
    }

    if (warningMsg) {
        if (
            !(await confirmDialog({
                title: 'Xác nhận thao tác',
                description:
                    warningMsg +
                    '\nBạn vẫn muốn tiếp tục xếp lịch ca trực này chứ?',
                variant: 'default',
            }))
        ) {
            return;
        }
    }

    router.post(
        '/employees/schedules',
        {
            day: currentAssignDay.value,
            employee_name: assignForm.value.employee_name,
            shift_name: assignForm.value.shift_name,
            shift_id: assignForm.value.shift_id,
        },
        {
            onSuccess: () => {
                showAssignModal.value = false;
            },
        },
    );
};

const removeAssignment = (
    dayKey: string,
    empName: string,
    shiftName: string,
    shiftId?: number,
) => {
    router.post(
        '/employees/schedules/delete',
        {
            day: dayKey,
            employee_name: empName,
            shift_name: shiftName,
            shift_id: shiftId ?? null,
        },
        {
            onError: (errors: any) => {
                const msg =
                    errors.shift_name ||
                    errors.employee_name ||
                    'Không thể xóa ca làm việc.';
                import('vue-sonner').then((m) => m.toast.error(msg));
            },
        },
    );
};

const expandedEmployeeId = ref<number | null>(null);
const toggleExpandEmployee = (id: number) => {
    expandedEmployeeId.value = expandedEmployeeId.value === id ? null : id;
};

const getRegistrationsForDay = (dayKey: string) => {
    if (!props.registrations) {
        return [];
    }

    return props.registrations.filter((r) => r.day === dayKey);
};

const weatherIcons: Record<string, string> = {
    sunny: '☀️',
    rainy: '🌧️',
    cloudy: '☁️',
    windy: '💨',
};

const weatherColors: Record<string, string> = {
    sunny: 'text-amber-500',
    rainy: 'text-blue-500',
    cloudy: 'text-slate-400',
    windy: 'text-teal-500',
};

const getForecastForDay = (dateStr: string) => {
    return props.dailyForecasts?.[dateStr] || null;
};

const getDayStaffingStatus = (dateStr: string) => {
    const forecast = getForecastForDay(dateStr);

    if (!forecast) {
        return {
            status: 'optimal',
            text: 'Đủ nhân sự',
            className: 'bg-emerald-100 text-emerald-800',
        };
    }

    const understaffedCount = forecast.shifts.filter(
        (s: any) => s.status === 'understaffed',
    ).length;
    const overstaffedCount = forecast.shifts.filter(
        (s: any) => s.status === 'overstaffed',
    ).length;

    if (understaffedCount > 0) {
        return {
            status: 'understaffed',
            text: 'Thiếu nhân sự',
            className:
                'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/50',
        };
    }

    if (overstaffedCount > 0) {
        return {
            status: 'overstaffed',
            text: 'Thừa nhân sự',
            className:
                'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200/50',
        };
    }

    return {
        status: 'optimal',
        text: 'Đủ nhân sự',
        className:
            'bg-emerald-100 text-emerald-850 dark:bg-emerald-950/40 dark:text-emerald-350 border border-emerald-250/50',
    };
};

const getSelectedReplacementWarning = (assignmentId: number) => {
    const selectedId = selectedReplacements.value[assignmentId];

    if (!selectedId || !replacementLeaveData.value) {
        return '';
    }

    const assignment = replacementLeaveData.value.assignments.find(
        (a: any) => a.assignment_id === assignmentId,
    );

    if (!assignment) {
        return '';
    }

    const cand = assignment.suggestions.find(
        (c: any) => String(c.id) === selectedId,
    );

    if (!cand) {
        return '';
    }

    if (cand.has_overtime_violation && cand.has_rest_violation) {
        return '⚠️ Quá ca & Thiếu nghỉ: Nhân sự này đã làm đủ 6 ca/tuần và không đủ 11 tiếng nghỉ ngơi giữa các ca.';
    }

    if (cand.has_overtime_violation) {
        return '⚠️ Quá 6 ca/tuần: Phân ca này sẽ gây tăng ca (Overtime) vượt hạn mức.';
    }

    if (cand.has_rest_violation) {
        return '⚠️ Thiếu nghỉ 11h: Nhân sự không có đủ 11 tiếng nghỉ ngơi giữa các ca làm việc.';
    }

    return '';
};

const dragOverDay = ref<string | null>(null);

const handleDropShift = (e: DragEvent, dayKey: string) => {
    dragOverDay.value = null;
    const shiftName = e.dataTransfer?.getData('text/plain');

    if (!shiftName) {
        return;
    }

    if (availableEmployeesList.value.length === 0) {
        import('vue-sonner').then((m) =>
            m.toast.error(
                'Chưa có nhân viên nào — vui lòng thêm nhân sự trước khi phân ca.',
            ),
        );

        return;
    }

    currentAssignDay.value = dayKey;
    assignForm.value.employee_name = availableEmployeesList.value[0] ?? '';
    assignForm.value.shift_name = shiftName;
    showAssignModal.value = true;
};

const copyLastWeekSchedules = async () => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Sao chép toàn bộ lịch xếp ca của tuần trước sang tuần này? Các lịch đã xếp trong tuần này sẽ bị thế chỗ.',
            variant: 'default',
        })
    ) {
        router.post(
            '/employees/schedules/copy-last-week',
            {},
            {
                onSuccess: () =>
                    import('vue-sonner').then((m) =>
                        m.toast.success('Đã sao chép lịch trực thành công!'),
                    ),
                onError: (errs: any) =>
                    import('vue-sonner').then((m) =>
                        m.toast.error(errs.error || 'Lỗi khi sao chép.'),
                    ),
            },
        );
    }
};

const showSwapRejectModal = ref<number | null>(null);
const swapRejectReason = ref('');

const approveSwap = async (swapId: number) => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Phê duyệt yêu cầu đổi ca trực này?',
            variant: 'default',
        })
    ) {
        router.patch(
            `/schedules/swap/${swapId}/approve`,
            {},
            {
                onSuccess: () =>
                    import('vue-sonner').then((m) =>
                        m.toast.success('Đã phê duyệt đổi ca thành công!'),
                    ),
            },
        );
    }
};

const submitSwapReject = () => {
    if (!swapRejectReason.value) {
        import('vue-sonner').then((m) =>
            m.toast.error('Vui lòng nhập lý do từ chối.'),
        );

        return;
    }

    router.patch(
        `/schedules/swap/${showSwapRejectModal.value}/reject`,
        {
            notes: swapRejectReason.value,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã từ chối yêu cầu đổi ca.'),
                );
                showSwapRejectModal.value = null;
                swapRejectReason.value = '';
            },
        },
    );
};
</script>

<template>
    <Head title="Nhân sự & Lịch biểu" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Hệ Thống Quản Lý Nhân Sự & Xếp Ca
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Thêm nhân viên mới, phân quyền truy cập hệ thống và quản
                        lý lịch làm việc hàng tuần của cửa hàng.
                    </p>
                </div>
            </div>

            <!-- Day 3 Tour Target: btn-add-employee -->
            <Button
                id="btn-add-employee"
                @click="showAddEmployee = true"
                class="h-10 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
            >
                <Plus class="mr-2 size-4" />
                Thêm nhân sự mới
            </Button>
        </div>

        <!-- Add Employee Form Modal Overlay -->
        <Teleport to="body">
            <div
                v-if="showAddEmployee"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card
                class="w-full max-w-2xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <CardTitle
                        class="flex items-center gap-1.5 text-base text-indigo-600"
                    >
                        <UserCheck class="size-5" />
                        Tạo tài khoản nhân viên mới (Hồ sơ bảo mật)
                    </CardTitle>
                    <CardDescription
                        >Khai báo chi tiết lý lịch trích ngang của nhân sự để
                        đảm bảo tính pháp lý và phòng chống rủi ro gian
                        lận.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEmployee" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-name"
                                    >Họ và tên nhân sự
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-name"
                                    v-model="employeeForm.name"
                                    placeholder="Ví dụ: Nguyễn Văn A"
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-email"
                                    >Email đăng nhập
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-email"
                                    type="email"
                                    v-model="employeeForm.email"
                                    placeholder="Ví dụ: nva@aventura.vn"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-phone"
                                    >Số điện thoại liên lạc
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-phone"
                                    v-model="employeeForm.phone"
                                    placeholder="0900 000 000"
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-dob"
                                    >Ngày tháng năm sinh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-dob"
                                    type="date"
                                    v-model="employeeForm.date_of_birth"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-citizen-number"
                                    >Số định danh / Số CCCD
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-citizen-number"
                                    v-model="employeeForm.citizen_id_number"
                                    placeholder="12 chữ số..."
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-address"
                                    >Địa chỉ tạm trú hiện tại
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    id="emp-address"
                                    v-model="employeeForm.address"
                                    placeholder="Ví dụ: 123 Lê Lợi, Quận 1, TP. HCM"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-role"
                                    >Phân quyền hệ thống</Label
                                >
                                <p
                                    v-if="props.isBranchManager"
                                    class="text-xs text-amber-600 dark:text-amber-400"
                                >
                                    Quản lý chỉ được tạo Thu ngân, Order hoặc Bếp.
                                </p>
                                <select
                                    id="emp-role"
                                    v-model="employeeForm.role"
                                    @change="handleRoleChange"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="roleOption in createRoleOptions"
                                        :key="roleOption.value"
                                        :value="roleOption.value"
                                    >
                                        {{ roleOption.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-title"
                                    >Chức danh công việc</Label
                                >
                                <Input
                                    id="emp-title"
                                    v-model="employeeForm.job_title"
                                    required
                                />
                            </div>
                        </div>

                        <!-- Cấu hình Hình thức trả lương & Mức lương -->
                        <div
                            class="grid grid-cols-2 gap-4 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 dark:border-indigo-950/40 dark:bg-indigo-950/20"
                        >
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Hình thức trả lương</Label
                                >
                                <select
                                    v-model="employeeForm.compensation_type"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option value="fixed">
                                        💼 Lương tháng cố định
                                    </option>
                                    <option value="hourly">
                                        ⏱ Lương theo giờ
                                    </option>
                                    <option value="shift">
                                        📋 Lương theo ca
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="
                                    employeeForm.compensation_type === 'fixed'
                                "
                                class="grid gap-1.5"
                            >
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Mức lương tháng cố định (VNĐ)</Label
                                >
                                <Input
                                    type="number"
                                    step="50000"
                                    v-model="employeeForm.base_salary"
                                    placeholder="Ví dụ: 8000000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-indigo-600"
                                />
                            </div>
                            <div
                                v-else-if="
                                    employeeForm.compensation_type === 'hourly'
                                "
                                class="grid gap-1.5"
                            >
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Đơn giá lương giờ (VNĐ/h)</Label
                                >
                                <Input
                                    type="number"
                                    step="1000"
                                    v-model="employeeForm.pay_rate"
                                    placeholder="Ví dụ: 25000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-purple-600"
                                />
                            </div>
                            <div v-else class="grid gap-1.5">
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Đơn giá lương ca (VNĐ/ca)</Label
                                >
                                <Input
                                    type="number"
                                    step="5000"
                                    v-model="employeeForm.pay_rate"
                                    placeholder="Ví dụ: 200000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-amber-600"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                >Chi nhánh làm việc
                                <span class="text-rose-500">*</span></Label
                            >
                            <select
                                v-model="employeeForm.branch_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- CCCD Front / Back File Upload Section -->
                        <div class="grid grid-cols-2 gap-4 border-t pt-3">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold text-slate-700"
                                    >Ảnh mặt trước CCCD
                                    <span class="text-rose-500">*</span></Label
                                >
                                <div
                                    class="flex w-full items-center justify-center"
                                >
                                    <label
                                        class="flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 transition-colors hover:bg-slate-100"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center px-3 pt-2 pb-2 text-center"
                                        >
                                            <Plus
                                                class="mb-0.5 size-4 text-indigo-600"
                                            />
                                            <p
                                                class="max-w-[200px] truncate text-[10px] font-medium text-slate-500"
                                            >
                                                {{
                                                    employeeForm.citizen_id_front
                                                        ? employeeForm
                                                              .citizen_id_front
                                                              .name
                                                        : 'Tải lên mặt trước CCCD'
                                                }}
                                            </p>
                                        </div>
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="
                                                (e) =>
                                                    (employeeForm.citizen_id_front =
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).files?.[0] || null)
                                            "
                                            required
                                        />
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold text-slate-700"
                                    >Ảnh mặt sau CCCD
                                    <span class="text-rose-500">*</span></Label
                                >
                                <div
                                    class="flex w-full items-center justify-center"
                                >
                                    <label
                                        class="flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 transition-colors hover:bg-slate-100"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center px-3 pt-2 pb-2 text-center"
                                        >
                                            <Plus
                                                class="mb-0.5 size-4 text-indigo-600"
                                            />
                                            <p
                                                class="max-w-[200px] truncate text-[10px] font-medium text-slate-500"
                                            >
                                                {{
                                                    employeeForm.citizen_id_back
                                                        ? employeeForm
                                                              .citizen_id_back
                                                              .name
                                                        : 'Tải lên mặt sau CCCD'
                                                }}
                                            </p>
                                        </div>
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="
                                                (e) =>
                                                    (employeeForm.citizen_id_back =
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).files?.[0] || null)
                                            "
                                            required
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                        >
                            <AlertCircle class="mt-0.5 size-4 shrink-0" />
                            <p>
                                <strong>Bảo mật mật khẩu:</strong> Nhân viên sẽ nhận
                                link kích hoạt qua email và tự đặt mật khẩu riêng.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddEmployee = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
                                :disabled="employeeForm.processing"
                            >
                                {{
                                    employeeForm.processing
                                        ? 'Đang tạo...'
                                        : 'Tạo nhân sự'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
        </Teleport>

        <!-- Edit Employee Modal -->
        <Teleport to="body">
            <div
                v-if="editingEmployee"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card
                class="w-full max-w-2xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <Pencil class="size-4" />
                            Sửa hồ sơ & thông tin nhân viên
                        </CardTitle>
                        <button
                            @click="editingEmployee = null"
                            class="text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form
                        @submit.prevent="submitEditEmployee"
                        class="space-y-4"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Họ và tên
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input v-model="editForm.full_name" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    >Số điện thoại
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input v-model="editForm.phone" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Ngày sinh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    type="date"
                                    v-model="editForm.date_of_birth"
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    >Số định danh / CCCD
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    v-model="editForm.citizen_id_number"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                >Địa chỉ tạm trú
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input v-model="editForm.address" required />
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-1.5">
                                <Label
                                    >Chức danh
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input v-model="editForm.job_title" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Phân quyền hệ thống</Label>
                                <select
                                    v-model="editForm.role"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="roleOption in editRoleOptions"
                                        :key="roleOption.value"
                                        :value="roleOption.value"
                                        :disabled="roleOption.disabled"
                                    >
                                        {{ roleOption.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Trạng thái tài khoản</Label>
                                <select
                                    v-model="editForm.status"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option value="active">
                                        Đang hoạt động
                                    </option>
                                    <option value="inactive">
                                        Khóa / Tạm ngưng
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Cấu hình Hình thức trả lương & Mức lương -->
                        <div
                            class="grid grid-cols-2 gap-4 rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 dark:border-indigo-950/40 dark:bg-indigo-950/20"
                        >
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Hình thức trả lương</Label
                                >
                                <select
                                    v-model="editForm.compensation_type"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option value="fixed">
                                        💼 Lương tháng cố định
                                    </option>
                                    <option value="hourly">
                                        ⏱ Lương theo giờ
                                    </option>
                                    <option value="shift">
                                        📋 Lương theo ca
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="editForm.compensation_type === 'fixed'"
                                class="grid gap-1.5"
                            >
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Mức lương tháng hợp đồng (VNĐ)</Label
                                >
                                <Input
                                    type="number"
                                    step="50000"
                                    v-model="editForm.base_salary"
                                    placeholder="Ví dụ: 8000000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-indigo-600"
                                />
                            </div>
                            <div
                                v-else-if="
                                    editForm.compensation_type === 'hourly'
                                "
                                class="grid gap-1.5"
                            >
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Đơn giá lương giờ (VNĐ/h)</Label
                                >
                                <Input
                                    type="number"
                                    step="1000"
                                    v-model="editForm.pay_rate"
                                    placeholder="Ví dụ: 25000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-purple-600"
                                />
                            </div>
                            <div v-else class="grid gap-1.5">
                                <Label
                                    class="text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                    >Đơn giá lương ca (VNĐ/ca)</Label
                                >
                                <Input
                                    type="number"
                                    step="5000"
                                    v-model="editForm.pay_rate"
                                    placeholder="Ví dụ: 200000"
                                    required
                                    class="h-9 font-mono text-xs font-bold text-amber-600"
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label
                                >Chi nhánh làm việc
                                <span class="text-rose-500">*</span></Label
                            >
                            <select
                                v-model="editForm.branch_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option
                                    v-for="branch in branches"
                                    :key="branch.id"
                                    :value="branch.id"
                                >
                                    {{ branch.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Optional CCCD Front / Back File Upload Section -->
                        <div class="grid grid-cols-2 gap-4 border-t pt-3">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold text-slate-700"
                                    >Cập nhật mặt trước CCCD
                                    <span class="text-[9px] text-slate-400"
                                        >(Tùy chọn)</span
                                    ></Label
                                >
                                <div
                                    class="flex w-full items-center justify-center"
                                >
                                    <label
                                        class="flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 transition-colors hover:bg-slate-100"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center px-3 pt-2 pb-2 text-center"
                                        >
                                            <Plus
                                                class="mb-0.5 size-4 text-indigo-600"
                                            />
                                            <p
                                                class="max-w-[200px] truncate text-[10px] font-medium text-slate-500"
                                            >
                                                {{
                                                    editForm.citizen_id_front
                                                        ? editForm
                                                              .citizen_id_front
                                                              .name
                                                        : 'Chọn ảnh mới'
                                                }}
                                            </p>
                                        </div>
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="
                                                (e) =>
                                                    (editForm.citizen_id_front =
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).files?.[0] || null)
                                            "
                                        />
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-xs font-semibold text-slate-700"
                                    >Cập nhật mặt sau CCCD
                                    <span class="text-[9px] text-slate-400"
                                        >(Tùy chọn)</span
                                    ></Label
                                >
                                <div
                                    class="flex w-full items-center justify-center"
                                >
                                    <label
                                        class="flex h-20 w-full cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 bg-slate-50 transition-colors hover:bg-slate-100"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center px-3 pt-2 pb-2 text-center"
                                        >
                                            <Plus
                                                class="mb-0.5 size-4 text-indigo-600"
                                            />
                                            <p
                                                class="max-w-[200px] truncate text-[10px] font-medium text-slate-500"
                                            >
                                                {{
                                                    editForm.citizen_id_back
                                                        ? editForm
                                                              .citizen_id_back
                                                              .name
                                                        : 'Chọn ảnh mới'
                                                }}
                                            </p>
                                        </div>
                                        <input
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="
                                                (e) =>
                                                    (editForm.citizen_id_back =
                                                        (
                                                            e.target as HTMLInputElement
                                                        ).files?.[0] || null)
                                            "
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="editingEmployee = null"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
                                :disabled="editForm.processing"
                            >
                                {{
                                    editForm.processing
                                        ? 'Đang lưu...'
                                        : 'Lưu hồ sơ'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
        </Teleport>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Left content: Employee roster -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <Card class="shadow-sm">
                    <CardHeader class="border-b pb-3">
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <Users class="size-4 text-indigo-600" />
                            Danh Sách Nhân Sự ({{ filteredEmployees.length }}/{{
                                employees.length
                            }})
                        </CardTitle>
                        <CardDescription class="text-[11px]"
                            >Tài khoản nhân viên được phân quyền đăng
                            nhập</CardDescription
                        >
                        <!-- Search -->
                        <div class="relative mt-2">
                            <Search
                                class="absolute top-2 left-2.5 size-3.5 text-slate-400"
                            />
                            <input
                                v-model="empSearchQuery"
                                type="text"
                                placeholder="Tìm theo tên, mã NV, SĐT..."
                                class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-7 text-xs focus:ring-1 focus:ring-indigo-400 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                            />
                        </div>
                    </CardHeader>
                    <CardContent
                        class="divide-y divide-slate-100 p-0 dark:divide-slate-800"
                    >
                        <div v-if="filteredEmployees.length">
                            <div
                                v-for="emp in paginatedEmployees"
                                :key="emp.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                            >
                                <div
                                    @click="toggleExpandEmployee(emp.id)"
                                    class="group flex cursor-pointer items-center gap-3 p-4 transition-colors hover:bg-muted/30"
                                    :class="
                                        emp.status === 'inactive'
                                            ? 'opacity-60'
                                            : ''
                                    "
                                >
                                    <!-- Colored avatar -->
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="avatarColor(emp.full_name)"
                                    >
                                        {{ initials(emp.full_name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="order-last ml-auto shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase"
                                                :class="
                                                    roleColors[emp.role] ||
                                                    'bg-slate-100'
                                                "
                                            >
                                                {{
                                                    roleLabels[emp.role] ??
                                                    emp.role
                                                }}
                                            </span>
                                            <p
                                                class="min-w-0 flex-1 truncate text-sm font-semibold"
                                            >
                                                {{ emp.full_name }}
                                            </p>
                                            <span
                                                v-if="emp.status === 'inactive'"
                                                class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                                >Bị khóa</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 truncate text-xs text-muted-foreground"
                                        >
                                            {{ emp.employee_code }} ·
                                            {{ emp.job_title }}
                                        </p>
                                        <div
                                            class="mt-1 flex items-center gap-1.5 text-xs"
                                        >
                                            <span
                                                v-if="(emp.rating_count || 0) > 0"
                                                class="inline-flex items-center gap-0.5 rounded-full border border-amber-200/50 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                            >
                                                ⭐
                                                {{
                                                    Number(emp.rating_star).toFixed(1)
                                                }}
                                                / 5.0
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] font-medium text-slate-400"
                                            >
                                                Chưa có đánh giá
                                            </span>
                                            <span
                                                v-if="(emp.rating_count || 0) > 0"
                                                class="text-[10px] font-medium text-slate-400"
                                            >
                                                ({{ emp.rating_count || 0 }}
                                                lượt đánh giá)
                                            </span>
                                        </div>
                                        <div
                                            class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-slate-300 dark:text-slate-300"
                                        >
                                            <Mail
                                                class="size-3 shrink-0 text-slate-400"
                                            />
                                            <span
                                                class="shrink-0 font-semibold text-slate-400"
                                                >Email:</span
                                            >
                                            <span
                                                class="truncate"
                                                :title="
                                                    emp.email ??
                                                    'Chưa cập nhật email'
                                                "
                                            >
                                                {{
                                                    emp.email ||
                                                    'Chưa cập nhật email'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        <button
                                            @click.stop="openEditEmployee(emp)"
                                            class="rounded-lg p-1.5 text-indigo-600 transition-colors hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                                            title="Sửa thông tin"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            @click.stop="
                                                toggleEmployeeStatus(emp)
                                            "
                                            class="rounded-lg p-1.5 transition-colors"
                                            :class="
                                                emp.status === 'active'
                                                    ? 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40'
                                                    : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40'
                                            "
                                            :title="
                                                emp.status === 'active'
                                                    ? 'Khóa tài khoản'
                                                    : 'Mở khóa tài khoản'
                                            "
                                        >
                                            <ToggleRight
                                                v-if="emp.status === 'active'"
                                                class="size-4"
                                            />
                                            <ToggleLeft v-else class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Expanded legal dossier -->
                                <div
                                    v-if="expandedEmployeeId === emp.id"
                                    class="animate-in border-t border-slate-100 bg-slate-50/50 px-4 pt-2 pb-4 duration-200 fade-in slide-in-from-top-2 dark:border-slate-800 dark:bg-slate-900/50"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-3 text-[11px]"
                                    >
                                        <div
                                            class="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-400"
                                        >
                                            <div>
                                                <span
                                                    class="font-bold tracking-wide text-slate-500 uppercase"
                                                    >Ngày sinh:</span
                                                >
                                                <p
                                                    class="text-xs font-semibold text-slate-800 dark:text-slate-200"
                                                >
                                                    {{
                                                        emp.date_of_birth ||
                                                        'Chưa khai báo'
                                                    }}
                                                </p>
                                            </div>
                                            <div>
                                                <span
                                                    class="font-bold tracking-wide text-slate-500 uppercase"
                                                    >Số CCCD:</span
                                                >
                                                <p
                                                    class="text-xs font-semibold text-indigo-600 text-slate-800 dark:text-indigo-400 dark:text-slate-200"
                                                >
                                                    {{
                                                        emp.citizen_id_number ||
                                                        'Chưa khai báo'
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <span
                                                class="font-bold tracking-wide text-slate-500 uppercase"
                                                >Địa chỉ tạm trú:</span
                                            >
                                            <p
                                                class="text-xs font-semibold text-slate-800 dark:text-slate-200"
                                            >
                                                {{
                                                    emp.address ||
                                                    'Chưa khai báo'
                                                }}
                                            </p>
                                        </div>

                                        <!-- CCCD images preview -->
                                        <div
                                            class="mt-1 grid grid-cols-2 gap-2"
                                        >
                                            <div
                                                class="rounded-lg border bg-white p-1 text-center dark:bg-slate-950"
                                            >
                                                <span
                                                    class="mb-1 block text-[9px] font-bold text-slate-400"
                                                    >Mặt trước CCCD</span
                                                >
                                                <img
                                                    v-if="
                                                        emp.citizen_id_front_url
                                                    "
                                                    :src="
                                                        emp.citizen_id_front_url
                                                    "
                                                    class="mx-auto max-h-[100px] w-auto rounded border object-contain"
                                                    alt="Mặt trước"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-16 items-center justify-center rounded border border-dashed bg-slate-50 text-[9px] text-slate-400 italic"
                                                >
                                                    Chưa có ảnh
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-lg border bg-white p-1 text-center dark:bg-slate-950"
                                            >
                                                <span
                                                    class="mb-1 block text-[9px] font-bold text-slate-400"
                                                    >Mặt sau CCCD</span
                                                >
                                                <img
                                                    v-if="
                                                        emp.citizen_id_back_url
                                                    "
                                                    :src="
                                                        emp.citizen_id_back_url
                                                    "
                                                    class="mx-auto max-h-[100px] w-auto rounded border object-contain"
                                                    alt="Mặt sau"
                                                />
                                                <div
                                                    v-else
                                                    class="flex h-16 items-center justify-center rounded border border-dashed bg-slate-50 text-[9px] text-slate-400 italic"
                                                >
                                                    Chưa có ảnh
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Print/Export Button -->
                                        <div
                                            class="mt-2 flex justify-end gap-1.5 border-t pt-2"
                                        >
                                            <a
                                                :href="`/employees/${emp.id}/export-profile`"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1.5 text-[10px] font-bold text-indigo-600 transition-colors hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-400 dark:hover:bg-indigo-950"
                                            >
                                                <Sparkles class="size-3" />
                                                Xuất hồ sơ pháp lý (PDF/In)
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination controls (8 employees per page) -->
                            <div
                                v-if="totalEmpPages > 1"
                                class="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-slate-800"
                            >
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    Trang {{ empCurrentPage }} / {{ totalEmpPages }}
                                </span>
                                <div class="flex items-center gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="empCurrentPage === 1"
                                        @click="empCurrentPage--"
                                        class="h-7 px-2 text-xs"
                                    >
                                        <ChevronLeft class="mr-0.5 size-3.5" />
                                        Trước
                                    </Button>
                                    <Button
                                        v-for="p in visibleEmpPages"
                                        :key="p"
                                        variant="outline"
                                        size="sm"
                                        @click="empCurrentPage = p"
                                        :class="[
                                            'h-7 w-7 p-0 text-xs font-semibold',
                                            empCurrentPage === p
                                                ? 'bg-indigo-600 text-white hover:bg-indigo-700 hover:text-white dark:bg-indigo-500'
                                                : ''
                                        ]"
                                    >
                                        {{ p }}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="empCurrentPage === totalEmpPages"
                                        @click="empCurrentPage++"
                                        class="h-7 px-2 text-xs"
                                    >
                                        Sau
                                        <ChevronRight class="ml-0.5 size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-12 text-center">
                            <div
                                class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                            >
                                <Users
                                    class="size-7 text-muted-foreground/40"
                                />
                            </div>
                            <p class="text-sm font-semibold">
                                Chưa có nhân viên
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Thêm nhân viên để phân quyền truy cập hệ thống.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: Weekly scheduling board -->
            <!-- Day 3 Tour Target: scheduler-card -->
            <div class="lg:col-span-2">
                <Card
                    id="scheduler-card"
                    class="border-indigo-100 bg-gradient-to-br from-indigo-50/20 to-white shadow-sm dark:from-slate-900/50 dark:to-slate-900"
                >
                    <CardHeader
                        class="flex flex-col gap-4 border-b pb-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base"
                            >
                                <Calendar class="size-5 text-indigo-600" />
                                Bảng Xếp Lịch Làm Việc Hàng Tuần (Weekly
                                Scheduler)
                            </CardTitle>
                            <CardDescription
                                >Xây dựng các ca làm việc và xếp lịch để nhân
                                viên bấm giờ chấm công hàng
                                ngày.</CardDescription
                            >
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-4 self-end sm:self-center"
                        >
                            <!-- Toggle switch for Auto Schedule -->
                            <div
                                @click="handleToggleAutoSchedule"
                                class="flex cursor-pointer items-center gap-2 rounded-xl border bg-slate-50 px-3 py-1.5 shadow-xs transition-colors select-none hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800"
                            >
                                <span
                                    class="shrink-0 text-xs font-bold"
                                    :class="
                                        isAutoSchedule
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-slate-500'
                                    "
                                >
                                    {{
                                        isAutoSchedule
                                            ? '⚡ Tự động (AI)'
                                            : '🔧 Thủ công'
                                    }}
                                </span>
                                <div
                                    class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                                    :class="
                                        isAutoSchedule
                                            ? 'bg-emerald-500'
                                            : 'bg-slate-200 dark:bg-slate-800'
                                    "
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                        :class="
                                            isAutoSchedule
                                                ? 'translate-x-4'
                                                : 'translate-x-0'
                                        "
                                    />
                                </div>
                            </div>

                            <Button
                                @click="copyLastWeekSchedules"
                                :disabled="isAutoSchedule"
                                variant="outline"
                                size="sm"
                                class="text-emerald-650 flex h-8 shrink-0 items-center gap-1.5 border-emerald-200 text-xs hover:border-emerald-300 hover:text-emerald-700 disabled:opacity-50"
                            >
                                <RefreshCw class="size-3.5" />
                                Sao chép tuần trước
                            </Button>

                            <Button
                                @click="showShiftConfigModal = true"
                                :disabled="isAutoSchedule"
                                variant="outline"
                                size="sm"
                                class="flex h-8 shrink-0 items-center gap-1.5 border-indigo-200 text-xs text-indigo-600 hover:border-indigo-300 hover:text-indigo-700 disabled:opacity-50"
                            >
                                <Settings class="size-3.5" />
                                Thiết lập Ca
                            </Button>

                            <Button
                                @click="openQuickScheduleModal"
                                :disabled="isAutoSchedule"
                                variant="outline"
                                size="sm"
                                class="flex h-8 shrink-0 items-center gap-1.5 border-amber-200 text-xs text-amber-600 hover:border-amber-300 hover:text-amber-700 disabled:opacity-50"
                            >
                                <Sparkles class="size-3.5" />
                                Thiết lập ca nhanh
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4">
                        <!-- Time Grid Table -->
                        <div
                            class="overflow-hidden rounded-2xl border bg-white dark:bg-slate-950"
                        >
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="bg-slate-55 border-b text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                                    >
                                        <th class="w-[120px] border-r p-3.5">
                                            Thứ trong tuần
                                        </th>
                                        <th class="p-3.5">
                                            Lịch xếp ca nhân sự trong tuần
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

                                                <!-- Weather & Demand Forecast -->
                                                <div
                                                    v-if="
                                                        getForecastForDay(
                                                            day.dateStr,
                                                        )
                                                    "
                                                    class="mt-1 flex flex-col gap-1 border-t border-slate-100 pt-1.5 dark:border-slate-800"
                                                >
                                                    <span
                                                        class="flex items-center gap-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                                                    >
                                                        <span
                                                            :class="
                                                                weatherColors[
                                                                    getForecastForDay(
                                                                        day.dateStr,
                                                                    )
                                                                        ?.condition ||
                                                                        ''
                                                                ]
                                                            "
                                                            >{{
                                                                weatherIcons[
                                                                    getForecastForDay(
                                                                        day.dateStr,
                                                                    )
                                                                        ?.condition ||
                                                                        ''
                                                                ]
                                                            }}</span
                                                        >
                                                        {{
                                                            getForecastForDay(
                                                                day.dateStr,
                                                            )?.temperature
                                                        }}°C
                                                    </span>
                                                    <span
                                                        class="text-[8px] font-extrabold tracking-wider text-slate-400 uppercase"
                                                    >
                                                        Khách:
                                                        <span
                                                            :class="
                                                                getForecastForDay(
                                                                    day.dateStr,
                                                                )
                                                                    ?.demand_level ===
                                                                'high'
                                                                    ? 'font-black text-amber-600 dark:text-amber-400'
                                                                    : getForecastForDay(
                                                                            day.dateStr,
                                                                        )
                                                                            ?.demand_level ===
                                                                        'low'
                                                                      ? 'text-slate-500'
                                                                      : 'dark:text-emerald-450 font-black text-emerald-600'
                                                            "
                                                            >{{
                                                                getForecastForDay(
                                                                    day.dateStr,
                                                                )?.demand_label
                                                            }}</span
                                                        >
                                                    </span>
                                                </div>

                                                <!-- Staffing status badge -->
                                                <div class="mt-1.5">
                                                    <span
                                                        class="rounded-md px-1.5 py-0.5 text-[8px] font-black tracking-wider uppercase"
                                                        :class="
                                                            getDayStaffingStatus(
                                                                day.dateStr,
                                                            ).className
                                                        "
                                                        :title="
                                                            getForecastForDay(
                                                                day.dateStr,
                                                            )
                                                                ?.shifts.map(
                                                                    (s) =>
                                                                        `${s.shift_name}: Cần ${s.optimal_staff} NV (Có ${s.current_staff})`,
                                                                )
                                                                .join('\n')
                                                        "
                                                    >
                                                        {{
                                                            getDayStaffingStatus(
                                                                day.dateStr,
                                                            ).text
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            class="flex min-h-[60px] flex-wrap items-center gap-2 p-3.5 transition-colors"
                                            :class="
                                                dragOverDay === day.key
                                                    ? 'rounded-lg border border-dashed border-indigo-400 bg-indigo-50/45 dark:bg-indigo-950/20'
                                                    : ''
                                            "
                                            @dragover.prevent="
                                                dragOverDay = day.key
                                            "
                                            @dragleave="dragOverDay = null"
                                            @drop="
                                                handleDropShift($event, day.key)
                                            "
                                        >
                                            <!-- Load assigned schedules -->
                                            <div
                                                v-for="s in schedulesState.filter(
                                                    (sc) =>
                                                        sc.day === day.key,
                                                )"
                                                :key="
                                                    s.employee_name +
                                                    '-' +
                                                    s.shift_name +
                                                    '-' +
                                                    (s.shift_id ?? '')
                                                "
                                                class="group/assign relative flex items-center gap-1 rounded-md border border-indigo-100 bg-indigo-50/70 px-2 py-1 transition-all hover:bg-indigo-100 hover:shadow-xs dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:hover:bg-indigo-900/60"
                                                :title="`${s.employee_name} — ${s.shift_name}${s.start_time ? ' (' + s.start_time + ' - ' + s.end_time + ')' : ''}`"
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
                                                    >({{ s.shift_name }}<template v-if="s.start_time">: {{ s.start_time }}-{{ s.end_time }}</template>)</span
                                                >
                                                <!-- Delete button (Only available before shift end time) -->
                                                <button
                                                    v-if="!isAutoSchedule && !isShiftEnded(day.dateStr, s.end_time)"
                                                    @click="
                                                        removeAssignment(
                                                            day.key,
                                                            s.employee_name,
                                                            s.shift_name,
                                                            s.shift_id,
                                                        )
                                                    "
                                                    class="ml-1 rounded p-0.5 text-rose-500 opacity-0 transition-opacity group-hover/assign:opacity-100 hover:bg-rose-100 dark:hover:bg-rose-950/40"
                                                    title="Hủy xếp ca"
                                                >
                                                    <X class="size-3" />
                                                </button>
                                                <span
                                                    v-else-if="isShiftEnded(day.dateStr, s.end_time)"
                                                    class="ml-1 text-[9px] text-slate-400 opacity-60"
                                                    title="Ca làm việc đã kết thúc (không thể xóa)"
                                                >
                                                    🔒
                                                </span>
                                            </div>

                                            <!-- Add dynamic assign button -->
                                            <button
                                                v-if="!isAutoSchedule"
                                                @click="
                                                    openAssignModal(day.key)
                                                "
                                                class="flex h-7 w-7 items-center justify-center rounded-lg border border-dashed border-indigo-200 text-indigo-500 transition-colors hover:border-indigo-400 hover:bg-indigo-50 dark:border-indigo-900 dark:hover:bg-indigo-950/30"
                                                title="Xếp ca cho nhân sự"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>

                                            <div
                                                v-if="
                                                    !schedulesState.some(
                                                        (sc) =>
                                                            sc.day === day.key,
                                                    )
                                                "
                                                class="text-[10px] text-slate-400 italic"
                                            >
                                                Không có ca xếp
                                            </div>

                                            <!-- Shift registrations availability list -->
                                            <div
                                                v-if="
                                                    getRegistrationsForDay(
                                                        day.key,
                                                    ).length
                                                "
                                                class="mt-2 flex w-full flex-wrap items-center gap-1.5 border-t border-slate-100 pt-2 dark:border-slate-800"
                                            >
                                                <span
                                                    class="dark:text-emerald-450 rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-extrabold tracking-wider text-emerald-600 uppercase dark:bg-emerald-950/20"
                                                    >Rảnh ca:</span
                                                >
                                                <div
                                                    class="flex flex-wrap gap-1"
                                                >
                                                    <span
                                                        v-for="r in getRegistrationsForDay(
                                                            day.key,
                                                        )"
                                                        :key="
                                                            r.employee_name +
                                                            '-' +
                                                            r.shift_name
                                                        "
                                                        class="text-slate-650 rounded-md border bg-slate-50 px-1.5 py-0.5 text-[9px] font-bold dark:bg-slate-900 dark:text-slate-400"
                                                        :title="`Đăng ký rảnh: ${r.employee_name} (${r.shift_name})`"
                                                    >
                                                        {{ r.employee_name }}
                                                        ({{
                                                            r.shift_name.split(
                                                                ' (',
                                                            )[0]
                                                        }})
                                                    </span>
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
        </div>

        <!-- Section: Leave & Resignation Management (Full-Width Card) -->
        <Card class="border-slate-100 bg-white shadow-sm dark:bg-slate-900">
            <CardHeader
                class="flex flex-col gap-4 border-b pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-1.5 text-base text-indigo-600"
                    >
                        <Sparkles class="size-5" />
                        Quản lý Đơn nghỉ phép & Xin nghỉ việc (Leave Requests)
                    </CardTitle>
                    <CardDescription
                        >Duyệt các đơn xin nghỉ phép đột xuất (hệ thống tự động
                        hủy ca trực) hoặc xin thôi việc (tự động khóa tài khoản
                        & xóa mềm nhân sự).</CardDescription
                    >
                </div>
                <Button
                    @click="openLeaveModal"
                    class="flex h-8 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                >
                    <Plus class="size-4" />
                    Tạo đơn nghỉ phép / Thôi việc
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="leaveRequests && leaveRequests.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <th class="p-3.5">Thời gian tạo</th>
                                <th class="p-3.5">Nhân sự</th>
                                <th class="p-3.5">Loại đơn</th>
                                <th class="p-3.5">Thời gian xin nghỉ</th>
                                <th class="p-3.5">Lý do</th>
                                <th class="p-3.5">Trạng thái</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="leave in leaveRequests"
                                :key="leave.id"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                            >
                                <td
                                    class="p-3.5 font-mono font-medium text-slate-400"
                                >
                                    {{ leave.created_at }}
                                </td>
                                <td class="p-3.5">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ leave.employee_name }}
                                    </div>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            leaveTypeColors[leave.leave_type] ||
                                            'bg-slate-100'
                                        "
                                    >
                                        {{
                                            leaveTypeLabels[leave.leave_type] ??
                                            leave.leave_type
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="p-3.5 font-mono text-[11px] text-slate-600 dark:text-slate-400"
                                >
                                    {{ leave.start_date }}
                                    <span class="text-slate-300">➜</span>
                                    {{ leave.end_date }}
                                </td>
                                <td
                                    class="max-w-[200px] truncate p-3.5 text-slate-600 dark:text-slate-400"
                                    :title="leave.reason"
                                >
                                    {{ leave.reason }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            leaveStatusColors[leave.status] ||
                                            'bg-slate-100'
                                        "
                                    >
                                        {{
                                            leaveStatusLabels[leave.status] ??
                                            leave.status
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="flex items-center justify-end gap-1.5 p-3.5 text-right"
                                >
                                    <template v-if="leave.status === 'pending'">
                                        <button
                                            @click="startApproveLeave(leave)"
                                            class="inline-flex animate-in cursor-pointer items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[10px] font-bold text-white shadow transition fade-in hover:bg-emerald-700 active:scale-95"
                                        >
                                            Phê duyệt
                                        </button>
                                        <button
                                            @click="showRejectModal = leave.id"
                                            class="inline-flex animate-in cursor-pointer items-center justify-center rounded-lg bg-rose-600 px-2.5 py-1.5 text-[10px] font-bold text-white shadow transition fade-in hover:bg-rose-700 active:scale-95"
                                        >
                                            Từ chối
                                        </button>
                                    </template>
                                    <span
                                        v-else
                                        class="text-[10px] font-semibold text-slate-400 italic"
                                        >Đã xử lý</span
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
                        <Sparkles class="size-6" />
                    </div>
                    <p class="text-sm font-semibold">
                        Chưa có đơn nghỉ phép hay thôi việc nào
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Mọi đơn nộp bởi nhân viên hoặc do quản lý tạo thử sẽ
                        hiển thị tại đây.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Section: Shift Swaps Approvals (Full-Width Card) -->
        <Card
            class="mt-6 border-slate-100 bg-white shadow-sm dark:bg-slate-900"
        >
            <CardHeader class="border-b pb-3">
                <div>
                    <CardTitle
                        class="text-indigo-650 flex items-center gap-1.5 text-base dark:text-indigo-400"
                    >
                        <ArrowUpDown class="size-5 text-indigo-600" />
                        Quản lý Yêu cầu Đổi ca trực (Shift Swaps)
                    </CardTitle>
                    <CardDescription
                        >Phê duyệt hoặc từ chối các yêu cầu đổi ca trực đã được
                        đồng ý bởi cả hai nhân sự.</CardDescription
                    >
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="pendingSwaps && pendingSwaps.length"
                    class="overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <th class="p-3.5">Thời gian gửi</th>
                                <th class="p-3.5">Nhân sự đề xuất (Ca trực)</th>
                                <th class="p-3.5 text-center">Đổi với</th>
                                <th class="p-3.5">Nhân sự nhận (Ca trực)</th>
                                <th class="p-3.5">Ghi chú / Lý do</th>
                                <th class="p-3.5">Trạng thái</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="swap in pendingSwaps"
                                :key="swap.id"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                            >
                                <td
                                    class="p-3.5 font-mono font-medium text-slate-400"
                                >
                                    {{ swap.created_at }}
                                </td>
                                <td class="p-3.5">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ swap.requester_name }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] font-semibold text-indigo-600"
                                    >
                                        {{ swap.requester_shift }} ({{
                                            swap.requester_date
                                        }})
                                    </div>
                                </td>
                                <td
                                    class="p-3.5 text-center font-bold text-slate-400"
                                >
                                    <ArrowUpDown
                                        class="mx-auto size-4 animate-pulse text-slate-300"
                                    />
                                </td>
                                <td class="p-3.5">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ swap.receiver_name }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] font-semibold text-emerald-600"
                                    >
                                        {{ swap.receiver_shift }} ({{
                                            swap.receiver_date
                                        }})
                                    </div>
                                </td>
                                <td
                                    class="max-w-[200px] truncate p-3.5 text-slate-600 dark:text-slate-400"
                                    :title="swap.notes || ''"
                                >
                                    {{ swap.notes }}
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        Chờ duyệt
                                    </span>
                                </td>
                                <td
                                    class="flex items-center justify-end gap-1.5 p-3.5 text-right"
                                >
                                    <button
                                        @click="approveSwap(swap.id)"
                                        class="inline-flex animate-in cursor-pointer items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[10px] font-bold text-white shadow transition fade-in hover:bg-emerald-700 active:scale-95"
                                    >
                                        Phê duyệt
                                    </button>
                                    <button
                                        @click="showSwapRejectModal = swap.id"
                                        class="inline-flex animate-in cursor-pointer items-center justify-center rounded-lg bg-rose-600 px-2.5 py-1.5 text-[10px] font-bold text-white shadow transition fade-in hover:bg-rose-700 active:scale-95"
                                    >
                                        Từ chối
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-16 text-center">
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-900"
                    >
                        <ArrowUpDown class="size-6" />
                    </div>
                    <p class="text-sm font-semibold">
                        Chưa có yêu cầu đổi ca trực nào
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Các yêu cầu đổi ca đã được hai nhân sự đồng ý và chờ
                        quản lý duyệt sẽ hiển thị tại đây.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Modal: Thiết lập ca nhanh -->
        <Teleport to="body">
            <div
                v-if="showQuickScheduleModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
                <Card
                    class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-amber-600"
                            >
                                <Sparkles class="size-5" />
                                Thiết lập ca nhanh
                            </CardTitle>
                            <CardDescription>
                                Hệ thống tự lấy ngày và thời điểm bạn bấm
                                “Xếp ca” làm mốc, không cần chọn ngày.
                            </CardDescription>
                        </div>
                        <button
                            @click="showQuickScheduleModal = false"
                            class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <div
                            class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            <p class="font-semibold">
                                Quy tắc thiết lập ca nhanh
                            </p>
                            <p class="mt-1">
                                Hôm nay chỉ xếp ca có giờ bắt đầu không trước
                                thời điểm bạn bấm “Xếp ca”. Ví dụ bấm lúc 17:00
                                thì ca bắt đầu trước 17:00 sẽ không được xếp.
                                Từ ngày mai đến Chủ nhật, hệ thống xếp các ca
                                còn trống. Ca đã có, đã hoàn thành hoặc đã khóa
                                không bị thay đổi.
                            </p>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showQuickScheduleModal = false"
                                >Hủy</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                @click="submitQuickSchedule"
                                class="bg-amber-500 font-semibold text-white shadow hover:bg-amber-600"
                            >
                                <Sparkles class="mr-1.5 size-3.5" />
                                Xếp ca
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- Modal: Thiết lập Ca làm việc (showShiftConfigModal) -->
        <Teleport to="body">
            <div
                v-if="showShiftConfigModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
                <Card
                    class="w-full max-w-lg animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Settings class="size-5" />
                                Thiết lập Ca làm việc trong ngày
                            </CardTitle>
                            <CardDescription
                                >Cấu hình thời gian và số ca hoạt động của nhà hàng
                                hàng ngày.</CardDescription
                            >
                        </div>
                        <button
                            @click="showShiftConfigModal = false"
                            class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <div
                            v-if="shiftsState.length > 0"
                            class="max-h-[300px] space-y-3 overflow-y-auto pr-1"
                        >
                            <div
                                v-for="s in shiftsState"
                                :key="s.id"
                                class="flex items-center gap-3 rounded-xl border border-border bg-muted/20 p-3"
                            >
                                <!-- Name input -->
                                <div class="min-w-0 flex-1">
                                    <Label
                                        class="text-[10px] font-semibold text-muted-foreground uppercase"
                                        >Tên ca</Label
                                    >
                                    <Input
                                        v-model="s.name"
                                        class="h-8 text-xs font-semibold"
                                        placeholder="Ví dụ: Ca Sáng"
                                    />
                                </div>

                                <!-- Start time input -->
                                <div class="w-24 shrink-0">
                                    <Label
                                        class="text-[10px] font-semibold text-muted-foreground uppercase"
                                        >Bắt đầu</Label
                                    >
                                    <Input
                                        type="time"
                                        v-model="s.start"
                                        class="h-8 font-mono text-xs"
                                    />
                                </div>

                                <!-- End time input -->
                                <div class="w-24 shrink-0">
                                    <Label
                                        class="text-[10px] font-semibold text-muted-foreground uppercase"
                                        >Kết thúc</Label
                                    >
                                    <Input
                                        type="time"
                                        v-model="s.end"
                                        class="h-8 font-mono text-xs"
                                    />
                                </div>

                                <!-- Delete shift button -->
                                <button
                                    @click="deleteShift(s.id)"
                                    class="mb-0.5 shrink-0 self-end rounded-lg p-1.5 text-rose-500 transition-colors hover:bg-rose-100 dark:hover:bg-rose-950/40"
                                    title="Xóa ca này"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <div
                            v-else
                            class="py-8 text-center text-xs text-muted-foreground italic"
                        >
                            Chưa có ca làm việc nào. Vui lòng bấm thêm ca bên dưới.
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addShift"
                                class="border-indigo-200 font-semibold text-indigo-600 hover:bg-indigo-50"
                            >
                                <Plus class="mr-1.5 size-4" /> Thêm ca mới
                            </Button>

                            <div class="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showShiftConfigModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="button"
                                    size="sm"
                                    @click="saveShiftsConfig"
                                    class="bg-indigo-600 font-semibold text-white shadow"
                                >
                                    Lưu cấu hình
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- Modal: Phân Ca Lịch làm việc (showAssignModal) -->
        <Teleport to="body">
            <div
                v-if="showAssignModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-28"
            >
                <Card
                    class="w-full max-w-sm animate-in shadow-2xl duration-150 zoom-in-95 fade-in"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-base text-indigo-600"
                            >
                                <Calendar class="size-5" />
                                Xếp ca - {{ currentAssignDayLabel }}
                            </CardTitle>
                            <CardDescription
                                >Chọn nhân sự và gán ca tương ứng vào ngày
                                này.</CardDescription
                            >
                        </div>
                        <button
                            @click="showAssignModal = false"
                            class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                        >
                            <X class="size-4" />
                        </button>
                    </CardHeader>
                    <CardContent class="space-y-4 pt-4">
                        <form
                            @submit.prevent="submitAssignment"
                            class="space-y-4"
                        >
                            <div class="grid gap-1.5">
                                <Label for="assign-emp"
                                    >Chọn nhân sự
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    id="assign-emp"
                                    v-model="assignForm.employee_name"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="name in availableEmployeesList"
                                        :key="name"
                                        :value="name"
                                    >
                                        {{ name }}
                                    </option>
                                </select>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="assign-shift"
                                    >Chọn ca làm việc
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    id="assign-shift"
                                    v-model="assignForm.shift_id"
                                    @change="
                                        (e) => {
                                            const targetId = Number(
                                                (
                                                    e.target as HTMLSelectElement
                                                ).value,
                                            );
                                            const s = shiftsState.find(
                                                (x) => x.id === targetId,
                                            );
                                            if (s)
                                                assignForm.shift_name =
                                                    s.name.split(' (')[0];
                                        }
                                    "
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option
                                        v-for="s in shiftsState"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }} ({{ s.start }} -
                                        {{ s.end }})
                                    </option>
                                </select>
                            </div>

                            <!-- Suggestions helper -->
                            <div
                                v-if="
                                    getRegistrationsForDay(currentAssignDay)
                                        .length
                                "
                                class="dark:text-emerald-350 rounded-xl border border-emerald-100 bg-emerald-50 p-3 text-[11px] text-emerald-800 dark:border-emerald-900/30 dark:bg-emerald-950/20"
                            >
                                <span class="flex items-center gap-1 font-bold"
                                    ><Sparkles class="size-3.5" /> Gợi ý: Nhân
                                    viên đăng ký rảnh hôm nay</span
                                >
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    <button
                                        v-for="r in getRegistrationsForDay(
                                            currentAssignDay,
                                        )"
                                        :key="
                                            r.employee_name + '-' + r.shift_name
                                        "
                                        type="button"
                                        @click="
                                            assignForm.employee_name =
                                                r.employee_name;
                                            assignForm.shift_name =
                                                r.shift_name.split(' (')[0];
                                        "
                                        class="cursor-pointer rounded border bg-white px-2 py-1 text-[10px] font-semibold text-slate-700 transition-colors hover:bg-slate-100 active:scale-95 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                        title="Chọn nhanh nhân viên này"
                                    >
                                        {{ r.employee_name }} ({{
                                            r.shift_name.split(' (')[0]
                                        }})
                                    </button>
                                </div>
                            </div>

                            <div
                                class="flex justify-end gap-2 border-t border-border/60 pt-2"
                            >
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="showAssignModal = false"
                                    >Hủy</Button
                                >
                                <Button
                                    type="submit"
                                    size="sm"
                                    class="bg-indigo-600 font-semibold text-white"
                                    >Xác nhận xếp ca</Button
                                >
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </Teleport>

        <!-- Modal: Tạo đơn xin nghỉ phép / thôi việc (showLeaveModal) -->
        <Teleport to="body">
            <div
                v-if="showLeaveModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card class="w-full max-w-md shadow-2xl">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <Sparkles class="size-5" />
                            Tạo Đơn Nghỉ Phép / Thôi Việc
                        </CardTitle>
                        <CardDescription
                            >Khai báo đơn xin nghỉ cho nhân sự trực thuộc chi
                            nhánh.</CardDescription
                        >
                    </div>
                    <button
                        @click="showLeaveModal = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4">
                    <form
                        @submit.prevent="submitLeaveRequest"
                        class="space-y-4"
                    >
                        <div class="grid gap-1.5">
                            <Label for="leave-emp"
                                >Chọn nhân sự
                                <span class="text-rose-500">*</span></Label
                            >
                            <select
                                id="leave-emp"
                                v-model="leaveForm.employee_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option
                                    v-for="emp in employees"
                                    :key="emp.id"
                                    :value="String(emp.id)"
                                >
                                    {{ emp.full_name }} ({{
                                        emp.employee_code
                                    }})
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="leave-type"
                                >Loại đơn
                                <span class="text-rose-500">*</span></Label
                            >
                            <select
                                id="leave-type"
                                v-model="leaveForm.leave_type"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option value="annual">
                                    Nghỉ phép năm (Dự phòng phép)
                                </option>
                                <option value="sick">
                                    Nghỉ ốm (Đau ốm / Khám bệnh)
                                </option>
                                <option value="unpaid">
                                    Nghỉ không lương (Việc cá nhân)
                                </option>
                                <option value="emergency">
                                    Nghỉ đột xuất (Tự động xóa/hủy ca làm việc)
                                </option>
                                <option value="resignation">
                                    Đơn xin nghỉ việc hẳn (Khóa tài khoản,
                                    SoftDelete)
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="leave-start"
                                    >Từ ngày
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    type="date"
                                    id="leave-start"
                                    v-model="leaveForm.start_date"
                                    required
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="leave-end"
                                    >Đến hết ngày
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Input
                                    type="date"
                                    id="leave-end"
                                    v-model="leaveForm.end_date"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="leave-reason">Lý do xin nghỉ</Label>
                            <textarea
                                id="leave-reason"
                                v-model="leaveForm.reason"
                                rows="3"
                                placeholder="Nhập lý do chi tiết..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            ></textarea>
                        </div>

                        <div
                            class="flex justify-end gap-2 border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showLeaveModal = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                class="bg-indigo-600 font-semibold text-white"
                                >Gửi yêu cầu</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
        </Teleport>

        <!-- Modal: Duyệt đơn & Gợi ý thế chỗ nhân sự (showApproveReplacementModal) -->
        <Teleport to="body">
            <div
                v-if="showApproveReplacementModal && replacementLeaveData"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card
                class="w-full max-w-md animate-in shadow-2xl duration-150 zoom-in-95"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-emerald-600"
                        >
                            <UserCheck class="size-5" />
                            Duyệt Nghỉ Phép & Thế Chỗ Nhân Sự
                        </CardTitle>
                        <CardDescription>
                            Nhân viên
                            <strong
                                class="text-slate-800 dark:text-slate-200"
                                >{{
                                    replacementLeaveData.employee_name
                                }}</strong
                            >
                            xin nghỉ từ
                            {{ replacementLeaveData.start_date }} đến
                            {{ replacementLeaveData.end_date }}.
                        </CardDescription>
                    </div>
                    <button
                        @click="showApproveReplacementModal = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="space-y-4 pt-4">
                    <p class="text-xs text-muted-foreground">
                        Hệ thống tự động phát hiện nhân sự có lịch làm trùng
                        lặp. Vui lòng chọn nhân sự thay thế cho từng ca trực
                        dưới đây:
                    </p>

                    <div class="max-h-[300px] space-y-3 overflow-y-auto pr-1">
                        <div
                            v-for="assignment in replacementLeaveData.assignments"
                            :key="assignment.assignment_id"
                            class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 dark:border-slate-800 dark:bg-slate-900/30"
                        >
                            <div class="flex items-center justify-between">
                                <div class="text-xs">
                                    <span
                                        class="font-black text-slate-800 dark:text-slate-200"
                                        >Thứ
                                        {{ assignment.formatted_date }}</span
                                    >
                                    <p
                                        class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                    >
                                        {{ assignment.shift_name }} ({{
                                            assignment.shift_time
                                        }})
                                    </p>
                                </div>
                                <span
                                    class="rounded bg-indigo-100 px-2 py-0.5 text-[9px] font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400"
                                >
                                    Ca cần thế chỗ
                                </span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-bold text-muted-foreground uppercase"
                                    >Nhân sự thay thế</Label
                                >
                                <select
                                    v-model="
                                        selectedReplacements[
                                            assignment.assignment_id
                                        ]
                                    "
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-xs focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option value="">
                                        -- Để trống ca trực --
                                    </option>
                                    <option
                                        v-for="cand in assignment.suggestions"
                                        :key="cand.id"
                                        :value="String(cand.id)"
                                    >
                                        {{ cand.full_name }} ({{
                                            cand.employee_code
                                        }})
                                        {{
                                            cand.registered_available
                                                ? '⚡ AI Đề xuất (Rảnh)'
                                                : ''
                                        }}
                                        {{
                                            cand.has_warning
                                                ? ' [⚠️ ' +
                                                  cand.warning_message +
                                                  ']'
                                                : ''
                                        }}
                                    </option>
                                </select>

                                <!-- Warning description alert block -->
                                <div
                                    v-if="
                                        getSelectedReplacementWarning(
                                            assignment.assignment_id,
                                        )
                                    "
                                    class="border-amber-150 flex animate-in items-start gap-1 rounded-lg border bg-amber-50 p-2 text-[10px] text-amber-600 duration-150 fade-in slide-in-from-top-1 dark:bg-amber-950/20 dark:text-amber-400"
                                >
                                    <AlertCircle
                                        class="mt-0.5 size-3.5 shrink-0"
                                    />
                                    <span>{{
                                        getSelectedReplacementWarning(
                                            assignment.assignment_id,
                                        )
                                    }}</span>
                                </div>
                            </div>

                            <div
                                v-if="assignment.suggestions.length === 0"
                                class="flex items-center gap-1 text-[10px] text-rose-500 italic"
                            >
                                <AlertCircle class="size-3" /> Không có nhân
                                viên cùng vị trí rảnh ca này.
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t border-border/60 pt-2"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showApproveReplacementModal = false"
                            >Hủy</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            @click="submitApproveWithReplacements"
                            class="flex items-center gap-1 bg-indigo-600 font-semibold text-white shadow hover:bg-indigo-700"
                        >
                            <CheckCircle2 class="size-4" />
                            Phê duyệt & Thế chỗ (1-Click)
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
        </Teleport>

        <!-- Modal: Nhập lý do từ chối (showRejectModal) -->
        <Teleport to="body">
            <div
                v-if="showRejectModal !== null"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card class="w-full max-w-sm shadow-2xl">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-rose-600"
                        >
                            <AlertCircle class="size-5" />
                            Từ chối đơn xin nghỉ
                        </CardTitle>
                        <CardDescription
                            >Vui lòng khai báo lý do từ chối đơn xin nghỉ
                            này.</CardDescription
                        >
                    </div>
                    <button
                        @click="showRejectModal = null"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4">
                    <form @submit.prevent="submitRejectLeave" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="reject-reason"
                                >Lý do từ chối
                                <span class="text-rose-500">*</span></Label
                            >
                            <textarea
                                id="reject-reason"
                                v-model="rejectReason"
                                required
                                rows="3"
                                placeholder="Ví dụ: Ca trực hôm nay đang thiếu nhân sự trầm trọng..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            ></textarea>
                        </div>

                        <div
                            class="flex justify-end gap-2 border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showRejectModal = null"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                class="bg-rose-600 font-semibold text-white"
                                >Xác nhận từ chối</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
        </Teleport>

        <!-- Modal: Nhập lý do từ chối đổi ca trực (showSwapRejectModal) -->
        <Teleport to="body">
            <div
                v-if="showSwapRejectModal !== null"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-16 backdrop-blur-xs md:pt-24"
            >
            <Card class="w-full max-w-sm shadow-2xl">
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-rose-600"
                        >
                            <AlertCircle class="size-5" />
                            Từ chối yêu cầu đổi ca
                        </CardTitle>
                        <CardDescription
                            >Vui lòng nhập lý do từ chối yêu cầu đổi ca trực
                            này.</CardDescription
                        >
                    </div>
                    <button
                        @click="showSwapRejectModal = null"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4">
                    <form @submit.prevent="submitSwapReject" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="swap-reject-reason"
                                >Lý do từ chối
                                <span class="text-rose-500">*</span></Label
                            >
                            <textarea
                                id="swap-reject-reason"
                                v-model="swapRejectReason"
                                required
                                rows="3"
                                placeholder="Ví dụ: Ca trực hôm nay không thể thay đổi do yêu cầu đặc biệt..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            ></textarea>
                        </div>

                        <div
                            class="flex justify-end gap-2 border-t border-border/60 pt-2"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="showSwapRejectModal = null"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                size="sm"
                                class="bg-rose-600 font-semibold text-white"
                                >Xác nhận từ chối</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
        </Teleport>
    </div>
</template>
