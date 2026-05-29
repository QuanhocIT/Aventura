<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import {
    Users, Plus, Calendar, Clock, CheckCircle2,
    AlertCircle, Sparkles, UserCheck, ShieldCheck, Mail, Phone,
    Pencil, ToggleLeft, ToggleRight, X, Trash2, Settings
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Employee = { id: number; employee_code: string; full_name: string; email: string | null; phone: string | null; job_title: string | null; status: string; role: string };
type Shift = { id: number; name: string; start: string; end: string };
type Assignment = { day: string; employee_name: string; shift_name: string };

const props = defineProps<{
    employees: Employee[];
    shifts: Shift[];
    schedules: Assignment[];
    leaveRequests?: any[];
}>();

const showAddEmployee = ref(false);
const editingEmployee = ref<Employee | null>(null);

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

    editForm.transform((data) => ({
        ...data,
        _method: 'PATCH',
    })).post(`/employees/${editingEmployee.value.id}`, {
        onSuccess: () => {
            editingEmployee.value = null; 
            editForm.reset(); 
        }
    });
};

const toggleEmployeeStatus = (emp: Employee) => {
    router.patch(`/employees/${emp.id}`, {
        status: emp.status === 'active' ? 'inactive' : 'active'
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
        }
    });
};

const roleLabels: Record<string, string> = {
    owner: 'Chủ quán',
    manager: 'Quản lý',
    cashier: 'Thu ngân (Cashier)',
    kitchen: 'Đầu bếp/Bếp (Kitchen)',
    staff: 'Nhân viên phục vụ'
};

const roleColors: Record<string, string> = {
    owner: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    manager: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    cashier: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    kitchen: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    staff: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
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
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

const weekDays = [
    { key: 'Monday', label: 'Thứ Hai' },
    { key: 'Tuesday', label: 'Thứ Ba' },
    { key: 'Wednesday', label: 'Thứ Tư' },
    { key: 'Thursday', label: 'Thứ Năm' },
    { key: 'Friday', label: 'Thứ Sáu' },
    { key: 'Saturday', label: 'Thứ Bảy' },
    { key: 'Sunday', label: 'Chủ Nhật' }
];

// ── LOCAL DYNAMIC SHIFT & SCHEDULE STATE (DATABASE DRIVEN) ──
const shiftsState = ref<Shift[]>(props.shifts ? [...props.shifts] : []);
const schedulesState = ref<Assignment[]>(props.schedules ? [...props.schedules] : []);

// Keep state perfectly in sync when Inertia reloads database props
watch(() => props.shifts, (newShifts) => {
    shiftsState.value = newShifts ? [...newShifts] : [];
}, { deep: true });

watch(() => props.schedules, (newSchedules) => {
    schedulesState.value = newSchedules ? [...newSchedules] : [];
}, { deep: true });

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
    emergency: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/50',
    resignation: 'bg-red-200 text-red-800 dark:bg-red-950/60 dark:text-red-200 border border-red-300/50',
};

const leaveStatusLabels: Record<string, string> = {
    pending: 'Chờ duyệt',
    approved: 'Đã duyệt',
    rejected: 'Đã từ chối',
};

const leaveStatusColors: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
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
            import('vue-sonner').then(m => m.toast.success('Đã nộp đơn xin nghỉ thành công!'));
            showLeaveModal.value = false;
            leaveForm.reset('reason');
        },
        onError: () => import('vue-sonner').then(m => m.toast.error('Không thể nộp đơn.')),
    });
}

function approveLeave(id: number) {
    if (!confirm('Bạn có chắc chắn muốn phê duyệt đơn nghỉ này? Mọi tác vụ tự động (hủy ca hoặc chấm dứt hợp đồng) sẽ được thực thi.')) return;
    router.patch(`/employees/leaves/${id}/approve`, {}, {
        onSuccess: () => import('vue-sonner').then(m => m.toast.success('Đã phê duyệt đơn thành công!')),
        onError: () => import('vue-sonner').then(m => m.toast.error('Lỗi khi phê duyệt.')),
    });
}

function submitRejectLeave() {
    if (!rejectReason.value) {
        import('vue-sonner').then(m => m.toast.error('Vui lòng nhập lý do từ chối.'));
        return;
    }
    router.patch(`/employees/leaves/${showRejectModal.value}/reject`, {
        rejection_reason: rejectReason.value
    }, {
        onSuccess: () => {
            import('vue-sonner').then(m => m.toast.success('Đã từ chối đơn xin nghỉ.'));
            showRejectModal.value = null;
            rejectReason.value = '';
        },
        onError: () => import('vue-sonner').then(m => m.toast.error('Lỗi khi từ chối.')),
    });
}

const currentAssignDay = ref('');
const currentAssignDayLabel = computed(() => {
    const day = weekDays.find(d => d.key === currentAssignDay.value);
    return day ? day.label : '';
});

// Dynamic Available Employees list (fallback to defaults if DB roster is empty)
const availableEmployeesList = computed(() => {
    if (props.employees && props.employees.length > 0) {
        return props.employees.map(e => e.full_name);
    }
    return ['Nguyễn Văn Thu Ngân', 'Trần Thị Bếp', 'Lê Văn Phục Vụ', 'Hoàng Văn Quản Lý'];
});

// Forms
const assignForm = ref({
    employee_name: '',
    shift_name: '',
});

// Shift Config Operations
const addShift = () => {
    const nextId = shiftsState.value.length ? Math.max(...shiftsState.value.map(s => s.id)) + 1 : 1;
    shiftsState.value.push({
        id: nextId,
        name: `Ca Mới ${nextId}`,
        start: '09:00',
        end: '17:00',
    });
};

const deleteShift = (id: number) => {
    shiftsState.value = shiftsState.value.filter(s => s.id !== id);
};

const saveShiftsConfig = () => {
    router.post('/employees/shifts/sync', { shifts: shiftsState.value }, {
        onSuccess: () => {
            showShiftConfigModal.value = false;
        }
    });
};

// Assignment Operations
const openAssignModal = (dayKey: string) => {
    currentAssignDay.value = dayKey;
    assignForm.value.employee_name = availableEmployeesList.value[0] ?? '';
    assignForm.value.shift_name = shiftsState.value[0]?.name ? shiftsState.value[0].name.split(' (')[0] : 'Ca Mới';
    showAssignModal.value = true;
};

const submitAssignment = () => {
    router.post('/employees/schedules', {
        day: currentAssignDay.value,
        employee_name: assignForm.value.employee_name,
        shift_name: assignForm.value.shift_name,
    }, {
        onSuccess: () => {
            showAssignModal.value = false;
        }
    });
};

const removeAssignment = (dayKey: string, empName: string, shiftName: string) => {
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

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Hệ Thống Quản Lý Nhân Sự & Xếp Ca</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Thêm nhân viên mới, phân quyền truy cập hệ thống và quản lý lịch làm việc hàng tuần của cửa hàng.
                    </p>
                </div>
            </div>

            <!-- Day 3 Tour Target: btn-add-employee -->
            <Button
                id="btn-add-employee"
                @click="showAddEmployee = true"
                class="h-10 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
            >
                <Plus class="size-4 mr-2" />
                Thêm nhân sự mới
            </Button>
        </div>

        <!-- Add Employee Form Modal Overlay -->
        <div v-if="showAddEmployee" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-2xl w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                        <UserCheck class="size-5" />
                        Tạo tài khoản nhân viên mới (Hồ sơ bảo mật)
                    </CardTitle>
                    <CardDescription>Khai báo chi tiết lý lịch trích ngang của nhân sự để đảm bảo tính pháp lý và phòng chống rủi ro gian lận.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEmployee" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-name">Họ và tên nhân sự <span class="text-rose-500">*</span></Label>
                                <Input id="emp-name" v-model="employeeForm.name" placeholder="Ví dụ: Nguyễn Văn A" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-email">Email đăng nhập <span class="text-rose-500">*</span></Label>
                                <Input id="emp-email" type="email" v-model="employeeForm.email" placeholder="Ví dụ: nva@aventura.vn" required />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-phone">Số điện thoại liên lạc <span class="text-rose-500">*</span></Label>
                                <Input id="emp-phone" v-model="employeeForm.phone" placeholder="0900 000 000" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-dob">Ngày tháng năm sinh <span class="text-rose-500">*</span></Label>
                                <Input id="emp-dob" type="date" v-model="employeeForm.date_of_birth" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-citizen-number">Số định danh / Số CCCD <span class="text-rose-500">*</span></Label>
                                <Input id="emp-citizen-number" v-model="employeeForm.citizen_id_number" placeholder="12 chữ số..." required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-address">Địa chỉ tạm trú hiện tại <span class="text-rose-500">*</span></Label>
                                <Input id="emp-address" v-model="employeeForm.address" placeholder="Ví dụ: 123 Lê Lợi, Quận 1, TP. HCM" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-role">Phân quyền hệ thống</Label>
                                <select
                                    id="emp-role"
                                    v-model="employeeForm.role"
                                    @change="handleRoleChange"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                >
                                    <option value="cashier">Thu ngân (Bán hàng)</option>
                                    <option value="kitchen">Nhà bếp (Chuẩn bị món)</option>
                                    <option value="manager">Quản lý cửa hàng</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-title">Chức danh công việc</Label>
                                <Input id="emp-title" v-model="employeeForm.job_title" required />
                            </div>
                        </div>

                        <!-- CCCD Front / Back File Upload Section -->
                        <div class="grid grid-cols-2 gap-4 border-t pt-3">
                            <div class="grid gap-1.5">
                                <Label class="text-xs font-semibold text-slate-700">Ảnh mặt trước CCCD <span class="text-rose-500">*</span></Label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-20 border border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-2 px-3 text-center">
                                            <Plus class="size-4 text-indigo-600 mb-0.5" />
                                            <p class="text-[10px] text-slate-500 font-medium truncate max-w-[200px]">{{ employeeForm.citizen_id_front ? employeeForm.citizen_id_front.name : 'Tải lên mặt trước CCCD' }}</p>
                                        </div>
                                        <input type="file" class="hidden" accept="image/*" @change="e => employeeForm.citizen_id_front = e.target.files[0]" required />
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs font-semibold text-slate-700">Ảnh mặt sau CCCD <span class="text-rose-500">*</span></Label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-20 border border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-2 px-3 text-center">
                                            <Plus class="size-4 text-indigo-600 mb-0.5" />
                                            <p class="text-[10px] text-slate-500 font-medium truncate max-w-[200px]">{{ employeeForm.citizen_id_back ? employeeForm.citizen_id_back.name : 'Tải lên mặt sau CCCD' }}</p>
                                        </div>
                                        <input type="file" class="hidden" accept="image/*" @change="e => employeeForm.citizen_id_back = e.target.files[0]" required />
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 rounded-xl flex items-start gap-2 text-[11px] text-amber-700 dark:text-amber-400">
                            <AlertCircle class="size-4 shrink-0 mt-0.5" />
                            <p><strong>Bảo mật mật khẩu:</strong> Tài khoản mới sẽ có mật khẩu mặc định là <code class="bg-amber-100 dark:bg-amber-900 px-1 py-0.5 rounded font-bold font-mono">12345678</code>. Nhân viên dùng mật khẩu này đăng nhập và bắt buộc đổi mật khẩu riêng tư ngay sau đó.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" @click="showAddEmployee = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="employeeForm.processing">
                                {{ employeeForm.processing ? 'Đang tạo...' : 'Tạo nhân sự' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Employee Modal -->
        <div v-if="editingEmployee" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-2xl w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Pencil class="size-4" />
                            Sửa hồ sơ & thông tin nhân viên
                        </CardTitle>
                        <button @click="editingEmployee = null" class="text-muted-foreground hover:text-foreground">
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEditEmployee" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Họ và tên <span class="text-rose-500">*</span></Label>
                                <Input v-model="editForm.full_name" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Số điện thoại <span class="text-rose-500">*</span></Label>
                                <Input v-model="editForm.phone" required />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Ngày sinh <span class="text-rose-500">*</span></Label>
                                <Input type="date" v-model="editForm.date_of_birth" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Số định danh / CCCD <span class="text-rose-500">*</span></Label>
                                <Input v-model="editForm.citizen_id_number" required />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Địa chỉ tạm trú <span class="text-rose-500">*</span></Label>
                            <Input v-model="editForm.address" required />
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Chức danh <span class="text-rose-500">*</span></Label>
                                <Input v-model="editForm.job_title" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Phân quyền hệ thống</Label>
                                <select v-model="editForm.role"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    <option value="cashier">Thu ngân (Bán hàng)</option>
                                    <option value="kitchen">Nhà bếp (Chuẩn bị món)</option>
                                    <option value="manager">Quản lý cửa hàng</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Trạng thái tài khoản</Label>
                                <select v-model="editForm.status"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    <option value="active">Đang hoạt động</option>
                                    <option value="inactive">Khóa / Tạm ngưng</option>
                                </select>
                            </div>
                        </div>

                        <!-- Optional CCCD Front / Back File Upload Section -->
                        <div class="grid grid-cols-2 gap-4 border-t pt-3">
                            <div class="grid gap-1.5">
                                <Label class="text-xs font-semibold text-slate-700">Cập nhật mặt trước CCCD <span class="text-[9px] text-slate-400">(Tùy chọn)</span></Label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-20 border border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-2 px-3 text-center">
                                            <Plus class="size-4 text-indigo-600 mb-0.5" />
                                            <p class="text-[10px] text-slate-500 font-medium truncate max-w-[200px]">{{ editForm.citizen_id_front ? editForm.citizen_id_front.name : 'Chọn ảnh mới' }}</p>
                                        </div>
                                        <input type="file" class="hidden" accept="image/*" @change="e => editForm.citizen_id_front = e.target.files[0]" />
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs font-semibold text-slate-700">Cập nhật mặt sau CCCD <span class="text-[9px] text-slate-400">(Tùy chọn)</span></Label>
                                <div class="flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full h-20 border border-dashed border-slate-300 rounded-lg cursor-pointer bg-slate-50 hover:bg-slate-100 transition-colors">
                                        <div class="flex flex-col items-center justify-center pt-2 pb-2 px-3 text-center">
                                            <Plus class="size-4 text-indigo-600 mb-0.5" />
                                            <p class="text-[10px] text-slate-500 font-medium truncate max-w-[200px]">{{ editForm.citizen_id_back ? editForm.citizen_id_back.name : 'Chọn ảnh mới' }}</p>
                                        </div>
                                        <input type="file" class="hidden" accept="image/*" @change="e => editForm.citizen_id_back = e.target.files[0]" />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t">
                            <Button type="button" variant="outline" @click="editingEmployee = null">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu hồ sơ' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left content: Employee roster -->
            <div class="lg:col-span-1 flex flex-col gap-6">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <Users class="size-4 text-indigo-600" />
                            Danh Sách Nhân Sự ({{ employees.length }})
                        </CardTitle>
                        <CardDescription class="text-[11px]">Tài khoản nhân viên được phân quyền đăng nhập</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-if="employees.length">
                            <div v-for="emp in employees" :key="emp.id" class="border-b last:border-0 border-slate-100 dark:border-slate-800">
                                <div @click="toggleExpandEmployee(emp.id)" class="flex items-center gap-3 p-4 hover:bg-muted/30 cursor-pointer transition-colors group"
                                    :class="emp.status === 'inactive' ? 'opacity-60' : ''">
                                    <!-- Colored avatar -->
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="avatarColor(emp.full_name)"
                                    >
                                        {{ initials(emp.full_name) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold text-sm truncate">{{ emp.full_name }}</p>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider shrink-0" :class="roleColors[emp.role] || 'bg-slate-100'">
                                                {{ roleLabels[emp.role] ?? emp.role }}
                                            </span>
                                            <span v-if="emp.status === 'inactive'" class="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0">Bị khóa</span>
                                        </div>
                                        <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ emp.employee_code }} · {{ emp.job_title }}</p>
                                        <span v-if="emp.email" class="text-[10px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                            <Mail class="size-2.5" /> {{ emp.email }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                        <button
                                            @click.stop="openEditEmployee(emp)"
                                            class="p-1.5 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 transition-colors"
                                            title="Sửa thông tin"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            @click.stop="toggleEmployeeStatus(emp)"
                                            class="p-1.5 rounded-lg transition-colors"
                                            :class="emp.status === 'active'
                                                ? 'hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-600'
                                                : 'hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-emerald-600'"
                                            :title="emp.status === 'active' ? 'Khóa tài khoản' : 'Mở khóa tài khoản'"
                                        >
                                            <ToggleRight v-if="emp.status === 'active'" class="size-4" />
                                            <ToggleLeft v-else class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Expanded legal dossier -->
                                <div v-if="expandedEmployeeId === emp.id" class="px-4 pb-4 pt-2 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800 animate-in fade-in slide-in-from-top-2 duration-200">
                                    <div class="grid grid-cols-1 gap-3 text-[11px]">
                                        <div class="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-400">
                                            <div>
                                                <span class="font-bold text-slate-500 uppercase tracking-wide">Ngày sinh:</span>
                                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ emp.date_of_birth || 'Chưa khai báo' }}</p>
                                            </div>
                                            <div>
                                                <span class="font-bold text-slate-500 uppercase tracking-wide">Số CCCD:</span>
                                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 text-indigo-600 dark:text-indigo-400">{{ emp.citizen_id_number || 'Chưa khai báo' }}</p>
                                            </div>
                                        </div>

                                        <div>
                                            <span class="font-bold text-slate-500 uppercase tracking-wide">Địa chỉ tạm trú:</span>
                                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ emp.address || 'Chưa khai báo' }}</p>
                                        </div>

                                        <!-- CCCD images preview -->
                                        <div class="grid grid-cols-2 gap-2 mt-1">
                                            <div class="border rounded-lg p-1 bg-white dark:bg-slate-950 text-center">
                                                <span class="text-[9px] font-bold text-slate-400 block mb-1">Mặt trước CCCD</span>
                                                <img v-if="emp.citizen_id_front_url" :src="emp.citizen_id_front_url" class="max-h-[100px] w-auto mx-auto rounded object-contain border" alt="Mặt trước" />
                                                <div v-else class="h-16 flex items-center justify-center text-[9px] text-slate-400 italic bg-slate-50 rounded border border-dashed">Chưa có ảnh</div>
                                            </div>
                                            <div class="border rounded-lg p-1 bg-white dark:bg-slate-950 text-center">
                                                <span class="text-[9px] font-bold text-slate-400 block mb-1">Mặt sau CCCD</span>
                                                <img v-if="emp.citizen_id_back_url" :src="emp.citizen_id_back_url" class="max-h-[100px] w-auto mx-auto rounded object-contain border" alt="Mặt sau" />
                                                <div v-else class="h-16 flex items-center justify-center text-[9px] text-slate-400 italic bg-slate-50 rounded border border-dashed">Chưa có ảnh</div>
                                            </div>
                                        </div>

                                        <!-- Print/Export Button -->
                                        <div class="flex justify-end gap-1.5 mt-2 border-t pt-2">
                                            <a 
                                                :href="`/employees/${emp.id}/export-profile`" 
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:hover:bg-indigo-950 text-indigo-600 dark:text-indigo-400 rounded-lg text-[10px] font-bold transition-colors"
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
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted mx-auto mb-3">
                                <Users class="size-7 text-muted-foreground/40" />
                            </div>
                            <p class="text-sm font-semibold">Chưa có nhân viên</p>
                            <p class="mt-1 text-xs text-muted-foreground">Thêm nhân viên để phân quyền truy cập hệ thống.</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: Weekly scheduling board -->
            <!-- Day 3 Tour Target: scheduler-card -->
            <div class="lg:col-span-2">
                <Card id="scheduler-card" class="shadow-sm border-indigo-100 bg-gradient-to-br from-indigo-50/20 to-white dark:from-slate-900/50 dark:to-slate-900">
                    <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                        <div>
                            <CardTitle class="text-base flex items-center gap-1.5">
                                <Calendar class="size-5 text-indigo-600" />
                                Bảng Xếp Lịch Làm Việc Hàng Tuần (Weekly Scheduler)
                            </CardTitle>
                            <CardDescription>Xây dựng các ca làm việc và xếp lịch để nhân viên bấm giờ chấm công hàng ngày.</CardDescription>
                        </div>
                        <Button 
                            @click="showShiftConfigModal = true"
                            variant="outline" 
                            size="sm"
                            class="h-8 text-xs shrink-0 flex items-center gap-1.5 text-indigo-600 hover:text-indigo-700 border-indigo-200 hover:border-indigo-300"
                        >
                            <Settings class="size-3.5" />
                            Thiết lập Ca
                        </Button>
                    </CardHeader>
                    <CardContent class="p-4">
                        <!-- Shifts listing brief -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
                            <div v-for="s in shiftsState" :key="s.id" class="p-3 bg-white dark:bg-slate-950 border rounded-xl shadow-sm text-center flex flex-col justify-center items-center relative group">
                                <Clock class="size-4 text-indigo-600 mb-1" />
                                <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate max-w-full">{{ s.name }}</span>
                                <span class="text-[9px] text-slate-400 font-mono mt-0.5">{{ s.start }} - {{ s.end }}</span>
                            </div>
                        </div>
 
                        <!-- Time Grid Table -->
                        <div class="border rounded-2xl overflow-hidden bg-white dark:bg-slate-950">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-55 dark:bg-slate-900 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3.5 border-r w-[120px]">Thứ trong tuần</th>
                                        <th class="p-3.5">Lịch xếp ca nhân sự hôm nay</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr v-for="day in weekDays" :key="day.key" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                        <td class="p-3.5 font-bold border-r text-slate-700 dark:text-slate-300 bg-slate-50/30">{{ day.label }}</td>
                                        <td class="p-3.5 flex flex-wrap gap-2 items-center">
                                            <!-- Load assigned schedules -->
                                            <div
                                                v-for="(s, idx) in schedulesState.filter(sc => sc.day === day.key)"
                                                :key="'s-' + idx"
                                                class="px-2.5 py-1.5 rounded-lg border bg-indigo-50/30 border-indigo-100 dark:bg-indigo-950/20 dark:border-indigo-900/40 flex items-center gap-1.5 group/assign relative"
                                            >
                                                <span class="size-1.5 rounded-full bg-indigo-600 dark:bg-indigo-400" />
                                                <span class="font-bold text-[10px] text-slate-800 dark:text-slate-200">{{ s.employee_name }}</span>
                                                <span class="text-[9px] text-slate-400 font-mono">({{ s.shift_name }})</span>
                                                <!-- Delete button -->
                                                <button 
                                                    @click="removeAssignment(day.key, s.employee_name, s.shift_name)"
                                                    class="p-0.5 rounded hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-500 opacity-0 group-hover/assign:opacity-100 transition-opacity ml-1"
                                                    title="Hủy xếp ca"
                                                >
                                                    <X class="size-3" />
                                                </button>
                                            </div>
 
                                            <!-- Add dynamic assign button -->
                                            <button 
                                                @click="openAssignModal(day.key)"
                                                class="h-7 w-7 flex items-center justify-center rounded-lg border border-dashed border-indigo-200 hover:border-indigo-400 text-indigo-500 hover:bg-indigo-50 dark:border-indigo-900 dark:hover:bg-indigo-950/30 transition-colors"
                                                title="Xếp ca cho nhân sự"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
 
                                            <div v-if="!schedulesState.some(sc => sc.day === day.key)" class="text-[10px] text-slate-400 italic">
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
        <Card class="shadow-sm border-slate-100 bg-white dark:bg-slate-900">
            <CardHeader class="pb-3 border-b flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                        <Sparkles class="size-5" />
                        Quản lý Đơn nghỉ phép & Xin nghỉ việc (Leave Requests)
                    </CardTitle>
                    <CardDescription>Duyệt các đơn xin nghỉ phép đột xuất (hệ thống tự động hủy ca trực) hoặc xin thôi việc (tự động khóa tài khoản & xóa mềm nhân sự).</CardDescription>
                </div>
                <Button 
                    @click="openLeaveModal"
                    class="bg-indigo-600 text-white hover:bg-indigo-700 shadow flex items-center gap-1.5 text-xs font-semibold h-8"
                >
                    <Plus class="size-4" />
                    Tạo đơn nghỉ phép / Thôi việc
                </Button>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="leaveRequests && leaveRequests.length" class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-950 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                <th class="p-3.5">Thời gian tạo</th>
                                <th class="p-3.5">Nhân sự</th>
                                <th class="p-3.5">Loại đơn</th>
                                <th class="p-3.5">Thời gian xin nghỉ</th>
                                <th class="p-3.5">Lý do</th>
                                <th class="p-3.5">Trạng thái</th>
                                <th class="p-3.5 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="leave in leaveRequests" :key="leave.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                <td class="p-3.5 font-medium text-slate-400 font-mono">{{ leave.created_at }}</td>
                                <td class="p-3.5">
                                    <div class="font-bold text-slate-800 dark:text-slate-200">{{ leave.employee_name }}</div>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold" :class="leaveTypeColors[leave.leave_type] || 'bg-slate-100'">
                                        {{ leaveTypeLabels[leave.leave_type] ?? leave.leave_type }}
                                    </span>
                                </td>
                                <td class="p-3.5 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                                    {{ leave.start_date }} <span class="text-slate-300">➜</span> {{ leave.end_date }}
                                </td>
                                <td class="p-3.5 text-slate-600 dark:text-slate-400 max-w-[200px] truncate" :title="leave.reason">
                                    {{ leave.reason }}
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="leaveStatusColors[leave.status] || 'bg-slate-100'">
                                        {{ leaveStatusLabels[leave.status] ?? leave.status }}
                                    </span>
                                </td>
                                <td class="p-3.5 text-right flex items-center justify-end gap-1.5">
                                    <template v-if="leave.status === 'pending'">
                                        <button 
                                            @click="approveLeave(leave.id)"
                                            class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[10px] font-bold text-white hover:bg-emerald-700 transition shadow active:scale-95 animate-in fade-in"
                                        >
                                            Phê duyệt
                                        </button>
                                        <button 
                                            @click="showRejectModal = leave.id"
                                            class="inline-flex cursor-pointer items-center justify-center rounded-lg bg-rose-600 px-2.5 py-1.5 text-[10px] font-bold text-white hover:bg-rose-700 transition shadow active:scale-95 animate-in fade-in"
                                        >
                                            Từ chối
                                        </button>
                                    </template>
                                    <span v-else class="text-[10px] text-slate-400 font-semibold italic">Đã xử lý</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-16 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                        <Sparkles class="size-6" />
                    </div>
                    <p class="text-sm font-semibold">Chưa có đơn nghỉ phép hay thôi việc nào</p>
                    <p class="mt-1 text-xs text-muted-foreground">Mọi đơn nộp bởi nhân viên hoặc do quản lý tạo thử sẽ hiển thị tại đây.</p>
                </div>
            </CardContent>
        </Card>

        <!-- Modal: Thiết lập Ca làm việc (showShiftConfigModal) -->
        <div v-if="showShiftConfigModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-lg w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Settings class="size-5" />
                            Thiết lập Ca làm việc trong ngày
                        </CardTitle>
                        <CardDescription>Cấu hình thời gian và số ca hoạt động của nhà hàng hàng ngày.</CardDescription>
                    </div>
                    <button @click="showShiftConfigModal = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4 space-y-4">
                    <div v-if="shiftsState.length > 0" class="max-h-[300px] overflow-y-auto space-y-3 pr-1">
                        <div 
                            v-for="s in shiftsState" 
                            :key="s.id"
                            class="flex items-center gap-3 p-3 rounded-xl border border-border bg-muted/20"
                        >
                            <!-- Name input -->
                            <div class="flex-1 min-w-0">
                                <Label class="text-[10px] text-muted-foreground uppercase font-semibold">Tên ca</Label>
                                <Input v-model="s.name" class="h-8 text-xs font-semibold" placeholder="Ví dụ: Ca Sáng" />
                            </div>
 
                            <!-- Start time input -->
                            <div class="w-24 shrink-0">
                                <Label class="text-[10px] text-muted-foreground uppercase font-semibold">Bắt đầu</Label>
                                <Input type="time" v-model="s.start" class="h-8 text-xs font-mono" />
                            </div>
 
                            <!-- End time input -->
                            <div class="w-24 shrink-0">
                                <Label class="text-[10px] text-muted-foreground uppercase font-semibold">Kết thúc</Label>
                                <Input type="time" v-model="s.end" class="h-8 text-xs font-mono" />
                            </div>
 
                            <!-- Delete shift button -->
                            <button 
                                @click="deleteShift(s.id)"
                                class="p-1.5 rounded-lg hover:bg-rose-100 dark:hover:bg-rose-950/40 text-rose-500 self-end mb-0.5 shrink-0 transition-colors"
                                title="Xóa ca này"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                    
                    <div v-else class="py-8 text-center text-muted-foreground text-xs italic">
                        Chưa có ca làm việc nào. Vui lòng bấm thêm ca bên dưới.
                    </div>
 
                    <div class="flex justify-between items-center pt-2 border-t border-border/60">
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            @click="addShift"
                            class="text-indigo-600 border-indigo-200 hover:bg-indigo-50 font-semibold"
                        >
                            <Plus class="size-4 mr-1.5" /> Thêm ca mới
                        </Button>
                        
                        <div class="flex gap-2">
                            <Button type="button" variant="outline" size="sm" @click="showShiftConfigModal = false">Hủy</Button>
                            <Button type="button" size="sm" @click="saveShiftsConfig" class="bg-indigo-600 text-white font-semibold shadow">
                                Lưu cấu hình
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
 
        <!-- Modal: Phân Ca Lịch làm việc (showAssignModal) -->
        <div v-if="showAssignModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-sm w-full animate-in fade-in zoom-in-95 duration-150 shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Calendar class="size-5" />
                            Xếp ca - {{ currentAssignDayLabel }}
                        </CardTitle>
                        <CardDescription>Chọn nhân sự và gán ca tương ứng vào ngày này.</CardDescription>
                    </div>
                    <button @click="showAssignModal = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4 space-y-4">
                    <form @submit.prevent="submitAssignment" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="assign-emp">Chọn nhân sự <span class="text-rose-500">*</span></Label>
                            <select
                                id="assign-emp"
                                v-model="assignForm.employee_name"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
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
                            <Label for="assign-shift">Chọn ca làm việc <span class="text-rose-500">*</span></Label>
                            <select
                                id="assign-shift"
                                v-model="assignForm.shift_name"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
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
 
                        <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                            <Button type="button" variant="outline" size="sm" @click="showAssignModal = false">Hủy</Button>
                            <Button type="submit" size="sm" class="bg-indigo-600 text-white font-semibold">Xác nhận xếp ca</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Modal: Tạo đơn xin nghỉ phép / thôi việc (showLeaveModal) -->
        <div v-if="showLeaveModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-200">
            <Card class="max-w-md w-full shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                            <Sparkles class="size-5" />
                            Tạo Đơn Nghỉ Phép / Thôi Việc
                        </CardTitle>
                        <CardDescription>Khai báo đơn xin nghỉ cho nhân sự trực thuộc chi nhánh.</CardDescription>
                    </div>
                    <button @click="showLeaveModal = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4">
                    <form @submit.prevent="submitLeaveRequest" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="leave-emp">Chọn nhân sự <span class="text-rose-500">*</span></Label>
                            <select
                                id="leave-emp"
                                v-model="leaveForm.employee_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option 
                                    v-for="emp in employees" 
                                    :key="emp.id" 
                                    :value="String(emp.id)"
                                >
                                    {{ emp.full_name }} ({{ emp.employee_code }})
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="leave-type">Loại đơn <span class="text-rose-500">*</span></Label>
                            <select
                                id="leave-type"
                                v-model="leaveForm.leave_type"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option value="annual">Nghỉ phép năm (Dự phòng phép)</option>
                                <option value="sick">Nghỉ ốm (Đau ốm / Khám bệnh)</option>
                                <option value="unpaid">Nghỉ không lương (Việc cá nhân)</option>
                                <option value="emergency">Nghỉ đột xuất (Tự động xóa/hủy ca làm việc)</option>
                                <option value="resignation">Đơn xin nghỉ việc hẳn (Khóa tài khoản, SoftDelete)</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="leave-start">Từ ngày <span class="text-rose-500">*</span></Label>
                                <Input type="date" id="leave-start" v-model="leaveForm.start_date" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="leave-end">Đến hết ngày <span class="text-rose-500">*</span></Label>
                                <Input type="date" id="leave-end" v-model="leaveForm.end_date" required />
                            </div>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="leave-reason">Lý do xin nghỉ</Label>
                            <textarea
                                id="leave-reason"
                                v-model="leaveForm.reason"
                                rows="3"
                                placeholder="Nhập lý do chi tiết..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                            <Button type="button" variant="outline" size="sm" @click="showLeaveModal = false">Hủy</Button>
                            <Button type="submit" size="sm" class="bg-indigo-600 text-white font-semibold">Gửi yêu cầu</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Modal: Nhập lý do từ chối (showRejectModal) -->
        <div v-if="showRejectModal !== null" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-200">
            <Card class="max-w-sm w-full shadow-2xl">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div>
                        <CardTitle class="text-base flex items-center gap-1.5 text-rose-600">
                            <AlertCircle class="size-5" />
                            Từ chối đơn xin nghỉ
                        </CardTitle>
                        <CardDescription>Vui lòng khai báo lý do từ chối đơn xin nghỉ này.</CardDescription>
                    </div>
                    <button @click="showRejectModal = null" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>
                <CardContent class="pt-4">
                    <form @submit.prevent="submitRejectLeave" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="reject-reason">Lý do từ chối <span class="text-rose-500">*</span></Label>
                            <textarea
                                id="reject-reason"
                                v-model="rejectReason"
                                required
                                rows="3"
                                placeholder="Ví dụ: Ca trực hôm nay đang thiếu nhân sự trầm trọng..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                            <Button type="button" variant="outline" size="sm" @click="showRejectModal = null">Hủy</Button>
                            <Button type="submit" size="sm" class="bg-rose-600 text-white font-semibold">Xác nhận từ chối</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
