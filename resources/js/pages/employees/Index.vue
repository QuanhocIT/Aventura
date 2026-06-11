<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Users,
    Plus,
    Calendar,
    Clock,
    CheckCircle2,
    AlertCircle,
    Sparkles,
    UserCheck,
    ShieldCheck,
    Mail,
    Phone,
    Pencil,
    ToggleLeft,
    ToggleRight,
    X,
    Trash2,
    Settings,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
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

type Employee = {
    id: number;
    employee_code: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    job_title: string | null;
    status: string;
    role: string;
};
type Shift = { id: number; name: string; start: string; end: string };
type Assignment = { day: string; employee_name: string; shift_name: string };

const props = defineProps<{
    employees: Employee[];
    shifts: Shift[];
    schedules: Assignment[];
    leaveRequests?: any[];
    autoSchedule?: boolean;
}>();

const showAddEmployee = ref(false);
const editingEmployee = ref<Employee | null>(null);
const empSearchQuery = ref('');

const filteredEmployees = computed(() => {
    const q = empSearchQuery.value.trim().toLowerCase();
    if (!q) return props.employees;
    return props.employees.filter(
        (e) =>
            e.full_name.toLowerCase().includes(q) ||
            e.employee_code.toLowerCase().includes(q) ||
            e.job_title.toLowerCase().includes(q) ||
            (e.phone ?? '').toLowerCase().includes(q),
    );
});

const employeeForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    job_title: 'Thu Ngân',
    date_of_birth: '',
    address: '',
    citizen_id_number: '',
    citizen_id_front: null as File | null,
    citizen_id_back: null as File | null,
    hire_date: new Date().toISOString().split('T')[0],
    base_salary: 6000000,
});

const editForm = useForm({
    full_name: '',
    phone: '',
    job_title: '',
    status: 'active' as 'active' | 'inactive',
    role: 'cashier',
    date_of_birth: '',
    address: '',
    citizen_id_number: '',
    citizen_id_front: null as File | null,
    citizen_id_back: null as File | null,
});

const openEditEmployee = (emp: any) => {
    editingEmployee.value = emp;
    editForm.full_name = emp.full_name;
    editForm.phone = emp.phone ?? '';
    editForm.job_title = emp.job_title ?? '';
    editForm.status = emp.status as 'active' | 'inactive';
    editForm.role = emp.role;
    editForm.date_of_birth = emp.date_of_birth ?? '';
    editForm.address = emp.address ?? '';
    editForm.citizen_id_number = emp.citizen_id_number ?? '';
    editForm.citizen_id_front = null;
    editForm.citizen_id_back = null;
};

const submitEditEmployee = () => {
    if (!editingEmployee.value) {
        return;
    }

    editForm
        .transform((data) => ({
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
    router.patch(`/employees/${emp.id}`, {
        status: emp.status === 'active' ? 'inactive' : 'active',
    });
};

const handleRoleChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;

    if (val === 'cashier') {
        employeeForm.job_title = 'Thu Ngân';
    } else if (val === 'kitchen') {
        employeeForm.job_title = 'Nhân Viên Bếp';
    } else if (val === 'manager') {
        employeeForm.job_title = 'Quản Lý Cửa Hàng';
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
    staff: 'Nhân viên phục vụ',
};

const roleColors: Record<string, string> = {
    owner: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    manager: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    cashier:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    kitchen:
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
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
        const dd = String(nextDay.getDate()).padStart(2, '0');
        const mm = String(nextDay.getMonth() + 1).padStart(2, '0');
        return {
            ...wd,
            dateLabel: `${dd}/${mm}`,
            fullLabel: `${wd.label} (${dd}/${mm})`,
        };
    });
});

// ── LOCAL DYNAMIC SHIFT & SCHEDULE STATE (DATABASE DRIVEN) ──
const shiftsState = ref<Shift[]>(props.shifts ? [...props.shifts] : []);
const schedulesState = ref<Assignment[]>(
    props.schedules ? [...props.schedules] : [],
);

// Keep state perfectly in sync when Inertia reloads database props
watch(
    () => props.shifts,
    (newShifts) => {
        shiftsState.value = newShifts ? [...newShifts] : [];
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

const handleToggleAutoSchedule = () => {
    if (!isAutoSchedule.value) {
        if (
            !confirm(
                'Kích hoạt Chế độ xếp lịch tự động? AI sẽ tự động phân phối các ca trực tối ưu cho tất cả nhân sự đang hoạt động trong tuần này. Các lịch ca trực hiện tại của tuần này sẽ bị thay thế.',
            )
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
const showAssignModal = ref(false);

const showLeaveModal = ref(false);
const showRejectModal = ref<number | null>(null);
const rejectReason = ref('');

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

function approveLeave(id: number) {
    if (
        !confirm(
            'Bạn có chắc chắn muốn phê duyệt đơn nghỉ này? Mọi tác vụ tự động (hủy ca hoặc chấm dứt hợp đồng) sẽ được thực thi.',
        )
    )
        return;
    router.patch(
        `/employees/leaves/${id}/approve`,
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

// Dynamic Available Employees list (fallback to defaults if DB roster is empty)
const availableEmployeesList = computed(() => {
    if (props.employees && props.employees.length > 0) {
        return props.employees.map((e) => e.full_name);
    }
    return [
        'Nguyễn Văn Thu Ngân',
        'Trần Thị Bếp',
        'Lê Văn Phục Vụ',
        'Hoàng Văn Quản Lý',
    ];
});

// Forms
const assignForm = ref({
    employee_name: '',
    shift_name: '',
});

// Shift Config Operations
const addShift = () => {
    const nextId = shiftsState.value.length
        ? Math.max(...shiftsState.value.map((s) => s.id)) + 1
        : 1;
    shiftsState.value.push({
        id: nextId,
        name: `Ca Mới ${nextId}`,
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

// Assignment Operations
const openAssignModal = (dayKey: string) => {
    currentAssignDay.value = dayKey;
    assignForm.value.employee_name = availableEmployeesList.value[0] ?? '';
    assignForm.value.shift_name = shiftsState.value[0]?.name
        ? shiftsState.value[0].name.split(' (')[0]
        : 'Ca Mới';
    showAssignModal.value = true;
};

const submitAssignment = () => {
    router.post(
        '/employees/schedules',
        {
            day: currentAssignDay.value,
            employee_name: assignForm.value.employee_name,
            shift_name: assignForm.value.shift_name,
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
) => {
    router.post('/employees/schedules/delete', {
        day: dayKey,
        employee_name: empName,
        shift_name: shiftName,
    });
};

const expandedEmployeeId = ref<number | null>(null);
const toggleExpandEmployee = (id: number) => {
    expandedEmployeeId.value = expandedEmployeeId.value === id ? null : id;
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
        <div
            v-if="showAddEmployee"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
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
                                <select
                                    id="emp-role"
                                    v-model="employeeForm.role"
                                    @change="handleRoleChange"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option value="cashier">
                                        Thu ngân (Bán hàng)
                                    </option>
                                    <option value="kitchen">
                                        Nhà bếp (Chuẩn bị món)
                                    </option>
                                    <option value="manager">
                                        Quản lý cửa hàng
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
                                                        e.target.files[0])
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
                                                        e.target.files[0])
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
                                <strong>Bảo mật mật khẩu:</strong> Tài khoản mới
                                sẽ có mật khẩu mặc định là
                                <code
                                    class="rounded bg-amber-100 px-1 py-0.5 font-mono font-bold dark:bg-amber-900"
                                    >12345678</code
                                >. Nhân viên dùng mật khẩu này đăng nhập và bắt
                                buộc đổi mật khẩu riêng tư ngay sau đó.
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

        <!-- Edit Employee Modal -->
        <div
            v-if="editingEmployee"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
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
                                    <option value="cashier">
                                        Thu ngân (Bán hàng)
                                    </option>
                                    <option value="kitchen">
                                        Nhà bếp (Chuẩn bị món)
                                    </option>
                                    <option value="manager">
                                        Quản lý cửa hàng
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
                                                        e.target.files[0])
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
                                                        e.target.files[0])
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
                                v-for="emp in filteredEmployees"
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
                                            <p
                                                class="truncate text-sm font-semibold"
                                            >
                                                {{ emp.full_name }}
                                            </p>
                                            <span
                                                class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase"
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
                                        <span
                                            v-if="emp.email"
                                            class="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground"
                                        >
                                            <Mail class="size-2.5" />
                                            {{ emp.email }}
                                        </span>
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
                                @click="showShiftConfigModal = true"
                                :disabled="isAutoSchedule"
                                variant="outline"
                                size="sm"
                                class="flex h-8 shrink-0 items-center gap-1.5 border-indigo-200 text-xs text-indigo-600 hover:border-indigo-300 hover:text-indigo-700 disabled:opacity-50"
                            >
                                <Settings class="size-3.5" />
                                Thiết lập Ca
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-4">
                        <!-- Shifts listing brief -->
                        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div
                                v-for="s in shiftsState"
                                :key="s.id"
                                class="group relative flex flex-col items-center justify-center rounded-xl border bg-white p-3 text-center shadow-sm dark:bg-slate-950"
                            >
                                <Clock class="mb-1 size-4 text-indigo-600" />
                                <span
                                    class="max-w-full truncate text-[10px] font-bold text-slate-800 dark:text-slate-200"
                                    >{{ s.name }}</span
                                >
                                <span
                                    class="mt-0.5 font-mono text-[9px] text-slate-400"
                                    >{{ s.start }} - {{ s.end }}</span
                                >
                            </div>
                        </div>

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
                                            Lịch xếp ca nhân sự hôm nay
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
                                            <!-- Load assigned schedules -->
                                            <div
                                                v-for="(
                                                    s, idx
                                                ) in schedulesState.filter(
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
                                                <!-- Delete button -->
                                                <button
                                                    v-if="!isAutoSchedule"
                                                    @click="
                                                        removeAssignment(
                                                            day.key,
                                                            s.employee_name,
                                                            s.shift_name,
                                                        )
                                                    "
                                                    class="ml-1 rounded p-0.5 text-rose-500 opacity-0 transition-opacity group-hover/assign:opacity-100 hover:bg-rose-100 dark:hover:bg-rose-950/40"
                                                    title="Hủy xếp ca"
                                                >
                                                    <X class="size-3" />
                                                </button>
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
                                            @click="approveLeave(leave.id)"
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

        <!-- Modal: Thiết lập Ca làm việc (showShiftConfigModal) -->
        <div
            v-if="showShiftConfigModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
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

        <!-- Modal: Phân Ca Lịch làm việc (showAssignModal) -->
        <div
            v-if="showAssignModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
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
                    <form @submit.prevent="submitAssignment" class="space-y-4">
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
                                v-model="assignForm.shift_name"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option
                                    v-for="s in shiftsState"
                                    :key="s.id"
                                    :value="s.name.split(' (')[0]"
                                >
                                    {{ s.name }} ({{ s.start }} - {{ s.end }})
                                </option>
                            </select>
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

        <!-- Modal: Tạo đơn xin nghỉ phép / thôi việc (showLeaveModal) -->
        <div
            v-if="showLeaveModal"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-200 fade-in"
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

        <!-- Modal: Nhập lý do từ chối (showRejectModal) -->
        <div
            v-if="showRejectModal !== null"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/50 p-4 backdrop-blur-xs duration-200 fade-in"
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
    </div>
</template>
