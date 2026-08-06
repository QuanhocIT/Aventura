<script setup lang="ts">
import {
    Users,
    Search,
    RefreshCw,
    Printer,
    LogOut,
    Calendar,
    Crown,
    X,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
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

type Assignment = {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string;
    job_title: string;
    shift_id: number;
    shift_name: string;
    shift_time: string;
    shift_start?: string | null;
    shift_end?: string | null;
    scheduled_date: string;
    requested_time?: string | null;
    requested_at?: string | null;
    check_in_at: string | null;
    check_out_at: string | null;
    status:
        | 'scheduled'
        | 'checked_in'
        | 'completed'
        | 'absent'
        | 'leave_approved';
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
    (
        e: 'open-override',
        assignment: Assignment,
        action: 'check_in' | 'check_out' | 'absent',
    ): void;
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

    return props.assignments.filter(
        (a) =>
            a.employee_name.toLowerCase().includes(query) ||
            a.employee_code.toLowerCase().includes(query) ||
            a.shift_name.toLowerCase().includes(query),
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

    const shift = props.shifts.find((s) => s.id === a.shift_id);

    if (!shift) {
        return null;
    }

    const shiftStart = new Date(`${a.scheduled_date}T${shift.start}`);
    const graceEnd = new Date(shiftStart.getTime() + 5 * 60_000); // 5 min grace
    const checkIn = parseDateTimeStr(a.check_in_at);
    const diffMin = Math.round(
        (checkIn.getTime() - graceEnd.getTime()) / 60_000,
    );

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
</script>

<template>
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
                    >Bảng giám sát trực quan các lượt bấm giờ vào ca và ra ca
                    thực tế của nhân sự.</CardDescription
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
                    class="flex h-8 shrink-0 cursor-pointer items-center gap-1 border-slate-200 text-xs text-slate-600"
                >
                    <Printer class="size-3.5" />
                    In báo cáo
                </Button>

                <!-- Export CSV -->
                <a
                    :href="'/schedules/export?date=' + adminDate"
                    class="text-indigo-650 inline-flex h-8 shrink-0 items-center justify-center gap-1 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold shadow-xs hover:bg-slate-50 dark:text-indigo-400"
                    target="_blank"
                >
                    <LogOut
                        class="text-indigo-650 size-3.5 rotate-90 dark:text-indigo-400"
                    />
                    Xuất Excel (CSV)
                </a>
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
                    @click="emit('refresh')"
                    variant="ghost"
                    size="icon"
                    class="h-9 w-9 shrink-0 cursor-pointer text-slate-500 hover:text-indigo-600"
                    title="Tải lại dữ liệu"
                >
                    <RefreshCw class="size-4" />
                </Button>
            </div>

            <!-- Attendance Registry Table -->
            <div v-if="filteredAssignments.length" class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                        >
                            <th class="p-3.5">Nhân viên</th>
                            <th class="p-3.5">Ca trực xếp lịch</th>
                            <th class="p-3.5">Thời gian gửi yêu cầu</th>
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
                                    class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    <span>{{ a.employee_name }}</span>
                                    <Crown
                                        v-if="a.is_shift_leader"
                                        class="size-3.5 shrink-0 animate-bounce fill-amber-500 text-amber-500"
                                        title="Trưởng ca trực"
                                    />
                                </div>
                                <div class="mt-0.5 text-[10px] text-slate-400">
                                    {{ a.employee_code }} · {{ a.job_title }}
                                </div>

                                <!-- Selfie Check-in Thumbnail -->
                                <div
                                    v-if="a.check_in_photo_path"
                                    class="mt-1 flex items-center gap-1"
                                >
                                    <span class="text-slate-450 text-[9px]"
                                        >Selfie:</span
                                    >
                                    <img
                                        :src="a.check_in_photo_path"
                                        alt="Selfie Check-in"
                                        class="h-8 w-8 cursor-zoom-in rounded-md border border-slate-200 object-cover transition-transform hover:scale-105"
                                        @click="
                                            viewSelfieUrl =
                                                a.check_in_photo_path
                                        "
                                    />
                                </div>
                            </td>
                            <td class="p-3.5">
                                <span
                                    class="rounded bg-indigo-50 px-2 py-0.5 font-mono font-semibold text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
                                >
                                    {{ a.shift_name }} {{ a.shift_start ? `(${a.shift_start} - ${a.shift_end})` : '' }}
                                </span>
                            </td>
                            <td class="p-3.5 font-mono text-slate-600 dark:text-slate-300">
                                <div v-if="a.requested_time || a.requested_at" class="flex flex-col gap-0.5">
                                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ a.requested_time || a.requested_at?.split(' ')[0] }}
                                    </span>
                                    <span v-if="a.requested_at?.split(' ')[1]" class="text-[10px] text-slate-400">
                                        {{ a.requested_at?.split(' ')[1] }}
                                    </span>
                                </div>
                                <div v-else class="text-slate-300 dark:text-slate-700">
                                    —
                                </div>
                            </td>
                            <td
                                class="text-slate-665 p-3.5 font-mono dark:text-slate-300"
                            >
                                <div
                                    v-if="a.check_in_at"
                                    class="flex flex-col gap-0.5"
                                >
                                    <div class="flex items-center gap-1">
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
                                        >⚠ Trễ {{ lateMinutes(a) }} phút</span
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
                                class="text-slate-665 p-3.5 font-mono dark:text-slate-300"
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
                                    v-else-if="a.status === 'checked_in'"
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
                                <!-- Toggle Shift Leader Button -->
                                <button
                                    @click="emit('toggle-leader', a.id)"
                                    :class="[
                                        'inline-flex cursor-pointer items-center justify-center rounded border p-1 text-[10px] font-bold shadow-xs transition active:scale-95',
                                        a.is_shift_leader
                                            ? 'border-amber-600 bg-amber-500 text-white hover:bg-amber-600'
                                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900',
                                    ]"
                                    :title="
                                        a.is_shift_leader
                                            ? 'Hủy vai trò Trưởng ca'
                                            : 'Gán làm Trưởng ca'
                                    "
                                >
                                    <Crown class="size-3.5" />
                                </button>

                                <!-- Actions based on status -->
                                <template v-if="['scheduled', 'pending_checkin'].includes(a.status)">
                                    <button
                                        @click="
                                            emit('open-override', a, 'check_in')
                                        "
                                        class="inline-flex cursor-pointer items-center justify-center rounded bg-emerald-600 px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition hover:bg-emerald-700 active:scale-95"
                                        title="Xác nhận check-in cho nhân sự"
                                    >
                                        Xác nhận check-in
                                    </button>
                                    <button
                                        @click="
                                            emit('open-override', a, 'absent')
                                        "
                                        class="bg-rose-55 inline-flex cursor-pointer items-center justify-center rounded border border-rose-200 px-2.5 py-1 text-[10px] font-bold text-rose-600 transition hover:bg-rose-100 active:scale-95"
                                        title="Báo vắng trực"
                                    >
                                        Báo Vắng
                                    </button>
                                </template>
                                <template v-else-if="a.status === 'checked_in'">
                                    <button
                                        @click="
                                            emit(
                                                'open-override',
                                                a,
                                                'check_out',
                                            )
                                        "
                                        class="bg-indigo-605 inline-flex cursor-pointer items-center justify-center rounded px-2.5 py-1 text-[10px] font-bold text-white shadow-xs transition hover:bg-indigo-700 active:scale-95"
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
                    Nhà hàng của bạn không có lịch xếp ca làm việc hoặc không
                    trùng điều kiện tìm kiếm.
                </p>
            </div>
        </CardContent>
    </Card>

    <!-- Selfie Lightbox Modal -->
    <div
        v-if="viewSelfieUrl"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-xs"
        @click="viewSelfieUrl = null"
    >
        <div
            class="relative w-full max-w-lg animate-in overflow-hidden rounded-2xl bg-white p-2 shadow-2xl duration-200 zoom-in-95 fade-in dark:bg-slate-900"
            @click.stop
        >
            <button
                class="absolute top-4 right-4 cursor-pointer rounded-full bg-black/50 p-2 text-white transition hover:bg-black/70"
                @click="viewSelfieUrl = null"
            >
                <X class="size-4" />
            </button>
            <img
                :src="viewSelfieUrl"
                alt="Selfie check-in high-res"
                class="h-auto max-h-[80vh] w-full rounded-xl object-contain"
            />
            <div class="p-3 text-center text-xs font-semibold text-slate-500">
                Ảnh tự sướng đối chiếu chấm công
            </div>
        </div>
    </div>
</template>
