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
}>();

const showAddEmployee = ref(false);
const editingEmployee = ref<Employee | null>(null);

const employeeForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    job_title: 'Thu Ngân'
});

const editForm = useForm({
    full_name: '',
    phone: '',
    job_title: '',
    status: 'active' as 'active' | 'inactive',
    role: 'cashier',
});

const openEditEmployee = (emp: Employee) => {
    editingEmployee.value = emp;
    editForm.full_name = emp.full_name;
    editForm.phone = emp.phone ?? '';
    editForm.job_title = emp.job_title ?? '';
    editForm.status = emp.status as 'active' | 'inactive';
    editForm.role = emp.role;
};

const submitEditEmployee = () => {
    if (!editingEmployee.value) {
return;
}

    editForm.patch(`/employees/${editingEmployee.value.id}`, {
        onSuccess: () => {
 editingEmployee.value = null; editForm.reset(); 
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
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-1.5">
                        <UserCheck class="size-5 text-indigo-600" />
                        Tạo tài khoản nhân viên mới
                    </CardTitle>
                    <CardDescription>Tạo tài khoản và phân vai trò vận hành cho nhân sự mới.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEmployee" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="emp-name">Họ và tên <span class="text-rose-500">*</span></Label>
                            <Input id="emp-name" v-model="employeeForm.name" placeholder="Ví dụ: Nguyễn Văn Thu Ngân" required />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-email">Email đăng nhập <span class="text-rose-500">*</span></Label>
                                <Input id="emp-email" type="email" v-model="employeeForm.email" placeholder="Ví dụ: thungan1@aventura.vn" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-phone">Số điện thoại</Label>
                                <Input id="emp-phone" v-model="employeeForm.phone" placeholder="Số điện thoại..." />
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

                        <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 rounded-xl flex items-start gap-2 text-[11px] text-indigo-700 dark:text-indigo-400">
                            <ShieldCheck class="size-4 shrink-0 mt-0.5" />
                            <p><strong>Phân quyền hoạt động:</strong> Thu ngân chỉ có quyền tạo và thanh toán đơn. Bếp chỉ có quyền nhận đơn nấu ăn. Quản lý có thêm quyền xem báo cáo.</p>
                        </div>

                        <div class="p-3 bg-amber-50/50 dark:bg-amber-950/20 border border-amber-100 rounded-xl flex items-start gap-2 text-[11px] text-amber-700 dark:text-amber-400">
                            <AlertCircle class="size-4 shrink-0 mt-0.5" />
                            <p><strong>Lưu ý:</strong> Tài khoản sẽ luôn có mật khẩu mặc định là <code class="bg-amber-100 dark:bg-amber-900 px-1 py-0.5 rounded font-bold font-mono">12345678</code>. Nhân viên sử dụng mật khẩu này để đăng nhập lần đầu, sau đó có thể vào phần cài đặt tài khoản cá nhân để thay đổi mật khẩu riêng tư.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddEmployee = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="employeeForm.processing">
                                {{ employeeForm.processing ? 'Đang tạo...' : 'Tạo nhân viên' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Edit Employee Modal -->
        <div v-if="editingEmployee" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-base flex items-center gap-1.5">
                            <Pencil class="size-4 text-indigo-600" />
                            Sửa thông tin nhân viên
                        </CardTitle>
                        <button @click="editingEmployee = null" class="text-muted-foreground hover:text-foreground">
                            <X class="size-4" />
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEditEmployee" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label>Họ và tên <span class="text-rose-500">*</span></Label>
                            <Input v-model="editForm.full_name" required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label>Số điện thoại</Label>
                                <Input v-model="editForm.phone" placeholder="0900 000 000" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Chức danh <span class="text-rose-500">*</span></Label>
                                <Input v-model="editForm.job_title" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
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
                                <Label>Trạng thái</Label>
                                <select v-model="editForm.status"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    <option value="active">Đang hoạt động</option>
                                    <option value="inactive">Tạm ngưng</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="editingEmployee = null">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="editForm.processing">
                                {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
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
                            <div v-for="emp in employees" :key="emp.id" class="flex items-center gap-3 p-4 hover:bg-muted/30 transition-colors group"
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
                                        <span v-if="emp.status === 'inactive'" class="text-[9px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 shrink-0">Tạm ngưng</span>
                                    </div>
                                    <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ emp.employee_code }} · {{ emp.job_title }}</p>
                                    <span v-if="emp.email" class="text-[10px] text-muted-foreground flex items-center gap-1 mt-0.5">
                                        <Mail class="size-2.5" /> {{ emp.email }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0">
                                    <button
                                        @click="openEditEmployee(emp)"
                                        class="p-1.5 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-950/40 text-indigo-600 transition-colors"
                                        title="Sửa thông tin"
                                    >
                                        <Pencil class="size-3.5" />
                                    </button>
                                    <button
                                        @click="toggleEmployeeStatus(emp)"
                                        class="p-1.5 rounded-lg transition-colors"
                                        :class="emp.status === 'active'
                                            ? 'hover:bg-amber-50 dark:hover:bg-amber-950/40 text-amber-600'
                                            : 'hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-emerald-600'"
                                        :title="emp.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt lại'"
                                    >
                                        <ToggleRight v-if="emp.status === 'active'" class="size-4" />
                                        <ToggleLeft v-else class="size-4" />
                                    </button>
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
    </div>
</template>
