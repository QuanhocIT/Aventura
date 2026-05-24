<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Users, Plus, Calendar, Clock, CheckCircle2,
    AlertCircle, Sparkles, UserCheck, ShieldCheck, Mail, Phone
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

const employeeForm = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'cashier',
    job_title: 'Thu Ngân'
});

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

const weekDays = [
    { key: 'Monday', label: 'Thứ Hai' },
    { key: 'Tuesday', label: 'Thứ Ba' },
    { key: 'Wednesday', label: 'Thứ Tư' },
    { key: 'Thursday', label: 'Thứ Năm' },
    { key: 'Friday', label: 'Thứ Sáu' },
    { key: 'Saturday', label: 'Thứ Bảy' },
    { key: 'Sunday', label: 'Chủ Nhật' }
];
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
        <div v-if="showAddEmployee" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-1.5">
                        <UserCheck class="size-5 text-indigo-600" />
                        Tạo tài khoản nhân viên mới
                    </CardTitle>
                    <CardDescription>Nhân viên có thể đăng nhập bằng email này với mật khẩu mặc định: <strong>12345678</strong></CardDescription>
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
                            <div v-for="emp in employees" :key="emp.id" class="p-4 flex flex-col gap-2.5">
                                <div class="flex justify-between items-start gap-2">
                                    <div>
                                        <p class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ emp.full_name }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ emp.employee_code }} · {{ emp.job_title }}</p>
                                    </div>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider" :class="roleColors[emp.role] || 'bg-slate-100'">
                                        {{ roleLabels[emp.role] ?? emp.role }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-[10px] text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <Mail class="size-3 text-slate-400" /> {{ emp.email }}
                                    </span>
                                    <span v-if="emp.phone" class="flex items-center gap-1">
                                        <Phone class="size-3 text-slate-400" /> {{ emp.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-slate-400 text-xs">
                            Chưa có nhân viên nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: Weekly scheduling board -->
            <!-- Day 3 Tour Target: scheduler-card -->
            <div class="lg:col-span-2">
                <Card id="scheduler-card" class="shadow-sm border-indigo-100 bg-gradient-to-br from-indigo-50/20 to-white dark:from-slate-900/50 dark:to-slate-900">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5">
                            <Calendar class="size-5 text-indigo-600" />
                            Bảng Xếp Lịch Làm Việc Hàng Tuần (Weekly Scheduler)
                        </CardTitle>
                        <CardDescription>Xây dựng các ca làm việc và xếp lịch để nhân viên bấm giờ chấm công hàng ngày.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-4">
                        <!-- Shifts listing brief -->
                        <div class="grid grid-cols-3 gap-3 mb-5">
                            <div v-for="s in shifts" :key="s.id" class="p-3 bg-white border dark:bg-slate-950 rounded-xl shadow-sm text-center flex flex-col justify-center items-center">
                                <Clock class="size-4 text-indigo-600 mb-1" />
                                <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200">{{ s.name }}</span>
                                <span class="text-[9px] text-slate-400 font-mono mt-0.5">{{ s.start }} - {{ s.end }}</span>
                            </div>
                        </div>

                        <!-- Time Grid Table -->
                        <div class="border rounded-2xl overflow-hidden bg-white">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900 border-b text-[10px] uppercase font-bold tracking-wider text-slate-500">
                                        <th class="p-3.5 border-r w-[120px]">Thứ trong tuần</th>
                                        <th class="p-3.5">Lịch xếp ca nhân sự hôm nay</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="day in weekDays" :key="day.key" class="hover:bg-slate-50/50 transition-colors">
                                        <td class="p-3.5 font-bold border-r text-slate-700 bg-slate-50/30">{{ day.label }}</td>
                                        <td class="p-3.5 flex flex-wrap gap-2 items-center">
                                            <!-- Load assigned schedules -->
                                            <div
                                                v-for="(s, idx) in schedules.filter(sc => sc.day === day.key)"
                                                :key="idx"
                                                class="px-2.5 py-1.5 rounded-lg border bg-indigo-50/30 border-indigo-100 flex items-center gap-1.5"
                                            >
                                                <span class="size-1.5 rounded-full bg-indigo-600" />
                                                <span class="font-bold text-[10px] text-slate-800">{{ s.employee_name }}</span>
                                                <span class="text-[9px] text-slate-400 font-mono">({{ s.shift_name }})</span>
                                            </div>

                                            <div v-if="!schedules.some(sc => sc.day === day.key)" class="text-[10px] text-slate-400 italic">
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
    </div>
</template>
