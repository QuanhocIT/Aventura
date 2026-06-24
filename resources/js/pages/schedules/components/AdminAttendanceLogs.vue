<script setup lang="ts">
import { Users, Search, RefreshCw, Printer, LogOut, Calendar, Crown, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    check_in_photo_path: string | null;
};

const props = defineProps<{
    assignments: Assignment[];
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
    selectedDate: string;
}>();

const emit = defineEmits<{
    (e: 'open-override', assignment: Assignment, action: 'check_in' | 'check_out' | 'absent'): void;
    (e: 'toggle-leader', assignmentId: number): void;
    (e: 'change-date', date: string): void;
    (e: 'refresh'): void;
}>();

const adminDate = ref(props.selectedDate);
const searchQuery = ref('');
const viewSelfieUrl = ref<string | null>(null);

const handleDateChange = () => {
    emit('change-date', adminDate.value);
};

const filteredAssignments = computed(() => {
    if (!props.assignments) {
return [];
}

    if (!searchQuery.value.trim()) {
return props.assignments;
}
    
    const query = searchQuery.value.toLowerCase().trim();

    return props.assignments.filter(a => 
        a.employee_name.toLowerCase().includes(query) || 
        a.employee_code.toLowerCase().includes(query) || 
        a.shift_name.toLowerCase().includes(query)
    );
});

const parseDateTimeStr = (str: string) => {
    const [time, date] = str.split(' ');
    const [h, i, s] = time.split(':').map(Number);
    const [d, m, y] = date.split('/').map(Number);

    return new Date(y, m - 1, d, h, i, s);
};

function lateMinutes(a: Assignment): number | null {
    if (!a.check_in_at || !props.shifts) {
return null;
}

    const shift = props.shifts.find(s => s.id === a.shift_id);

    if (!shift) {
return null;
}

    const shiftStart = new Date(`${a.scheduled_date}T${shift.start}`);
    const graceEnd   = new Date(shiftStart.getTime() + 5 * 60_000); // 5 min grace
    const checkIn = parseDateTimeStr(a.check_in_at);
    const diffMin = Math.round((checkIn.getTime() - graceEnd.getTime()) / 60_000);

    return diffMin > 0 ? diffMin : null;
}

const printRoster = () => {
    window.print();
};

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
</script>

<template>
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
                    class="h-8 text-xs shrink-0 flex items-center gap-1 text-slate-600 border-slate-200 cursor-pointer"
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
                <Button @click="emit('refresh')" variant="ghost" size="icon" class="h-9 w-9 shrink-0 text-slate-500 hover:text-indigo-600 cursor-pointer" title="Tải lại dữ liệu">
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
                                
                                <!-- Selfie Check-in Thumbnail -->
                                <div v-if="a.check_in_photo_path" class="mt-1 flex items-center gap-1">
                                    <span class="text-[9px] text-slate-450">Selfie:</span>
                                    <img 
                                        :src="a.check_in_photo_path" 
                                        alt="Selfie Check-in" 
                                        class="h-8 w-8 object-cover rounded-md border border-slate-200 cursor-zoom-in hover:scale-105 transition-transform"
                                        @click="viewSelfieUrl = a.check_in_photo_path"
                                    />
                                </div>
                            </td>
                            <td class="p-3.5">
                                <span class="font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/30 px-2 py-0.5 rounded font-mono">{{ a.shift_name }}</span>
                            </td>
                            <td class="p-3.5 font-mono text-slate-500">{{ a.shift_time }}</td>
                            <td class="p-3.5 font-mono text-slate-665 dark:text-slate-300">
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
                            <td class="p-3.5 font-mono text-slate-665 dark:text-slate-300">
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
                                    @click="emit('toggle-leader', a.id)"
                                    :class="[
                                        'inline-flex cursor-pointer items-center justify-center rounded p-1 text-[10px] font-bold border transition active:scale-95 shadow-xs',
                                        a.is_shift_leader 
                                            ? 'bg-amber-500 hover:bg-amber-600 text-white border-amber-600' 
                                            : 'bg-white hover:bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-900 dark:border-slate-800'
                                    ]"
                                    :title="a.is_shift_leader ? 'Hủy vai trò Trưởng ca' : 'Gán làm Trưởng ca'"
                                >
                                    <Crown class="size-3.5" />
                                </button>

                                <!-- Actions based on status -->
                                <template v-if="a.status === 'scheduled'">
                                    <button 
                                        @click="emit('open-override', a, 'check_in')"
                                        class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition active:scale-95 shadow-xs"
                                        title="Check-in hộ nhân sự"
                                    >
                                        Check-in hộ
                                    </button>
                                    <button 
                                        @click="emit('open-override', a, 'absent')"
                                        class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-rose-55 text-rose-600 hover:bg-rose-100 border border-rose-200 transition active:scale-95"
                                        title="Báo vắng trực"
                                    >
                                        Báo Vắng
                                    </button>
                                </template>
                                <template v-else-if="a.status === 'checked_in'">
                                    <button 
                                        @click="emit('open-override', a, 'check_out')"
                                        class="inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold bg-indigo-605 hover:bg-indigo-700 text-white transition active:scale-95 shadow-xs"
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

    <!-- Selfie Lightbox Modal -->
    <div v-if="viewSelfieUrl" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4" @click="viewSelfieUrl = null">
        <div class="relative max-w-lg w-full bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl p-2 animate-in fade-in zoom-in-95 duration-200" @click.stop>
            <button class="absolute top-4 right-4 p-2 bg-black/50 hover:bg-black/70 text-white rounded-full transition cursor-pointer" @click="viewSelfieUrl = null">
                <X class="size-4" />
            </button>
            <img :src="viewSelfieUrl" alt="Selfie check-in high-res" class="w-full h-auto max-h-[80vh] object-contain rounded-xl" />
            <div class="p-3 text-center text-xs font-semibold text-slate-500">Ảnh tự sướng đối chiếu chấm công</div>
        </div>
    </div>
</template>
