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
    job_title: 'Thu NgÃ¢n'
});

const handleRoleChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    if (val === 'cashier') {
        employeeForm.job_title = 'Thu NgÃ¢n';
    } else if (val === 'kitchen') {
        employeeForm.job_title = 'NhÃ¢n ViÃªn Báº¿p';
    } else if (val === 'manager') {
        employeeForm.job_title = 'Quáº£n LÃ½ Cá»­a HÃ ng';
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
    owner: 'Chá»§ quÃ¡n',
    manager: 'Quáº£n lÃ½',
    cashier: 'Thu ngÃ¢n (Cashier)',
    kitchen: 'Äáº§u báº¿p/Báº¿p (Kitchen)',
    staff: 'NhÃ¢n viÃªn phá»¥c vá»¥'
};

const roleColors: Record<string, string> = {
    owner: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400',
    manager: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-400',
    cashier: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400',
    kitchen: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400',
    staff: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
};

const weekDays = [
    { key: 'Monday', label: 'Thá»© Hai' },
    { key: 'Tuesday', label: 'Thá»© Ba' },
    { key: 'Wednesday', label: 'Thá»© TÆ°' },
    { key: 'Thursday', label: 'Thá»© NÄƒm' },
    { key: 'Friday', label: 'Thá»© SÃ¡u' },
    { key: 'Saturday', label: 'Thá»© Báº£y' },
    { key: 'Sunday', label: 'Chá»§ Nháº­t' }
];
</script>

<template>
    <Head title="NhÃ¢n sá»± & Lá»‹ch biá»ƒu" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Users class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Há»‡ Thá»‘ng Quáº£n LÃ½ NhÃ¢n Sá»± & Xáº¿p Ca</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        ThÃªm nhÃ¢n viÃªn má»›i, phÃ¢n quyá»n truy cáº­p há»‡ thá»‘ng vÃ  quáº£n lÃ½ lá»‹ch lÃ m viá»‡c hÃ ng tuáº§n cá»§a cá»­a hÃ ng.
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
                ThÃªm nhÃ¢n sá»± má»›i
            </Button>
        </div>

        <!-- Add Employee Form Modal Overlay -->
        <div v-if="showAddEmployee" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-1.5">
                        <UserCheck class="size-5 text-indigo-600" />
                        Táº¡o tÃ i khoáº£n nhÃ¢n viÃªn má»›i
                    </CardTitle>
                    <CardDescription>NhÃ¢n viÃªn cÃ³ thá»ƒ Ä‘Äƒng nháº­p báº±ng email nÃ y vá»›i máº­t kháº©u máº·c Ä‘á»‹nh: <strong>12345678</strong></CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitEmployee" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="emp-name">Há» vÃ  tÃªn <span class="text-rose-500">*</span></Label>
                            <Input id="emp-name" v-model="employeeForm.name" placeholder="VÃ­ dá»¥: Nguyá»…n VÄƒn Thu NgÃ¢n" required />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-email">Email Ä‘Äƒng nháº­p <span class="text-rose-500">*</span></Label>
                                <Input id="emp-email" type="email" v-model="employeeForm.email" placeholder="VÃ­ dá»¥: thungan1@aventura.vn" required />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-phone">Sá»‘ Ä‘iá»‡n thoáº¡i</Label>
                                <Input id="emp-phone" v-model="employeeForm.phone" placeholder="Sá»‘ Ä‘iá»‡n thoáº¡i..." />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="emp-role">PhÃ¢n quyá»n há»‡ thá»‘ng</Label>
                                <select
                                    id="emp-role"
                                    v-model="employeeForm.role"
                                    @change="handleRoleChange"
                                    required
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                >
                                    <option value="cashier">Thu ngÃ¢n (BÃ¡n hÃ ng)</option>
                                    <option value="kitchen">NhÃ  báº¿p (Chuáº©n bá»‹ mÃ³n)</option>
                                    <option value="manager">Quáº£n lÃ½ cá»­a hÃ ng</option>
                                </select>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="emp-title">Chá»©c danh cÃ´ng viá»‡c</Label>
                                <Input id="emp-title" v-model="employeeForm.job_title" required />
                            </div>
                        </div>

                        <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 rounded-xl flex items-start gap-2 text-[11px] text-indigo-700 dark:text-indigo-400">
                            <ShieldCheck class="size-4 shrink-0 mt-0.5" />
                            <p><strong>PhÃ¢n quyá»n hoáº¡t Ä‘á»™ng:</strong> Thu ngÃ¢n chá»‰ cÃ³ quyá»n táº¡o vÃ  thanh toÃ¡n Ä‘Æ¡n. Báº¿p chá»‰ cÃ³ quyá»n nháº­n Ä‘Æ¡n náº¥u Äƒn. Quáº£n lÃ½ cÃ³ thÃªm quyá»n xem bÃ¡o cÃ¡o.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddEmployee = false">Há»§y</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="employeeForm.processing">
                                {{ employeeForm.processing ? 'Äang táº¡o...' : 'Táº¡o nhÃ¢n viÃªn' }}
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
                            Danh SÃ¡ch NhÃ¢n Sá»± ({{ employees.length }})
                        </CardTitle>
                        <CardDescription class="text-[11px]">TÃ i khoáº£n nhÃ¢n viÃªn Ä‘Æ°á»£c phÃ¢n quyá»n Ä‘Äƒng nháº­p</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-if="employees.length">
                            <div v-for="emp in employees" :key="emp.id" class="p-4 flex flex-col gap-2.5">
                                <div class="flex justify-between items-start gap-2">
                                    <div>
                                        <p class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ emp.full_name }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ emp.employee_code }} Â· {{ emp.job_title }}</p>
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
                            ChÆ°a cÃ³ nhÃ¢n viÃªn nÃ o.
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
                            Báº£ng Xáº¿p Lá»‹ch LÃ m Viá»‡c HÃ ng Tuáº§n (Weekly Scheduler)
                        </CardTitle>
                        <CardDescription>XÃ¢y dá»±ng cÃ¡c ca lÃ m viá»‡c vÃ  xáº¿p lá»‹ch Ä‘á»ƒ nhÃ¢n viÃªn báº¥m giá» cháº¥m cÃ´ng hÃ ng ngÃ y.</CardDescription>
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
                                        <th class="p-3.5 border-r w-[120px]">Thá»© trong tuáº§n</th>
                                        <th class="p-3.5">Lá»‹ch xáº¿p ca nhÃ¢n sá»± hÃ´m nay</th>
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
                                                KhÃ´ng cÃ³ ca xáº¿p
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
