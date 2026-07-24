<script setup lang="ts">
import { X, Calendar, User, AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineProps<{
    showSelfServiceModal: boolean;
    selfServiceTab: 'schedule' | 'leave' | 'complaint';
    employee?: { id: number; full_name: string } | null;
    colleagues: Array<{ id: number; full_name: string; job_title: string | null }>;
    regDay: string;
    regShiftName: string;
    leaveType: string;
    leaveStart: string;
    leaveEnd: string;
    leaveReason: string;
    complaintTargetId: number | null;
    complaintType: string;
    complaintDescription: string;
}>();

const emit = defineEmits<{
    (e: 'update:showSelfServiceModal', val: boolean): void;
    (e: 'update:selfServiceTab', tab: 'schedule' | 'leave' | 'complaint'): void;
    (e: 'update:regDay', val: string): void;
    (e: 'update:regShiftName', val: string): void;
    (e: 'update:leaveType', val: string): void;
    (e: 'update:leaveStart', val: string): void;
    (e: 'update:leaveEnd', val: string): void;
    (e: 'update:leaveReason', val: string): void;
    (e: 'update:complaintTargetId', val: number | null): void;
    (e: 'update:complaintType', val: string): void;
    (e: 'update:complaintDescription', val: string): void;
    (e: 'handleRegisterSchedule'): void;
    (e: 'handleLeaveRequest'): void;
    (e: 'handleComplaint'): void;
}>();
</script>

<template>
    <div
        v-if="showSelfServiceModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div class="animate-fade-in flex w-full max-w-lg flex-col gap-6 overflow-hidden rounded-3xl border bg-white p-6 shadow-2xl dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-black text-slate-800 dark:text-slate-100">
                    <User class="size-5 text-indigo-600" />
                    Cổng Hành Chính Tự Phục Vụ Nhân Viên
                </h3>
                <Button
                    variant="ghost"
                    size="icon"
                    class="rounded-xl"
                    @click="emit('update:showSelfServiceModal', false)"
                >
                    <X class="size-5" />
                </Button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex rounded-2xl bg-slate-100 p-1 dark:bg-slate-800">
                <button
                    @click="emit('update:selfServiceTab', 'schedule')"
                    class="flex-1 rounded-xl py-2 text-xs font-bold transition-all"
                    :class="selfServiceTab === 'schedule' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-900' : 'text-slate-500'"
                >
                    📅 Đăng ký ca
                </button>
                <button
                    @click="emit('update:selfServiceTab', 'leave')"
                    class="flex-1 rounded-xl py-2 text-xs font-bold transition-all"
                    :class="selfServiceTab === 'leave' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-900' : 'text-slate-500'"
                >
                    📝 Xin nghỉ phép
                </button>
                <button
                    @click="emit('update:selfServiceTab', 'complaint')"
                    class="flex-1 rounded-xl py-2 text-xs font-bold transition-all"
                    :class="selfServiceTab === 'complaint' ? 'bg-white text-rose-600 shadow-sm dark:bg-slate-900' : 'text-slate-500'"
                >
                    ⚠️ Gửi khiếu nại
                </button>
            </div>

            <!-- Content Tab Đăng ký ca -->
            <div v-if="selfServiceTab === 'schedule'" class="flex flex-col gap-4 text-left">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Ngày làm việc mong muốn:</label>
                    <select
                        :value="regDay"
                        @change="emit('update:regDay', ($event.target as HTMLSelectElement).value)"
                        class="h-10 w-full rounded-xl border bg-white px-3 text-xs font-bold dark:bg-slate-950"
                    >
                        <option value="Thứ 2">Thứ 2</option>
                        <option value="Thứ 3">Thứ 3</option>
                        <option value="Thứ 4">Thứ 4</option>
                        <option value="Thứ 5">Thứ 5</option>
                        <option value="Thứ 6">Thứ 6</option>
                        <option value="Thứ 7">Thứ 7</option>
                        <option value="Chủ Nhật">Chủ Nhật</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Tên ca làm mong muốn:</label>
                    <Input
                        type="text"
                        placeholder="VD: Ca Sáng, Ca Tối, Ca Gãy..."
                        :value="regShiftName"
                        @input="emit('update:regShiftName', ($event.target as HTMLInputElement).value)"
                        class="h-10 rounded-xl text-xs font-bold"
                    />
                </div>

                <Button
                    class="mt-2 rounded-xl bg-indigo-600 text-xs font-bold hover:bg-indigo-700"
                    :disabled="!regShiftName.trim()"
                    @click="emit('handleRegisterSchedule')"
                >
                    Đăng Ký Lịch Làm
                </Button>
            </div>

            <!-- Content Tab Xin nghỉ phép -->
            <div v-else-if="selfServiceTab === 'leave'" class="flex flex-col gap-4 text-left">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Loại nghỉ phép:</label>
                    <select
                        :value="leaveType"
                        @change="emit('update:leaveType', ($event.target as HTMLSelectElement).value)"
                        class="h-10 w-full rounded-xl border bg-white px-3 text-xs font-bold dark:bg-slate-950"
                    >
                        <option value="annual">Nghỉ phép năm</option>
                        <option value="sick">Nghỉ ốm đau</option>
                        <option value="unpaid">Nghỉ không lương</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-600">Từ ngày:</label>
                        <Input
                            type="date"
                            :value="leaveStart"
                            @input="emit('update:leaveStart', ($event.target as HTMLInputElement).value)"
                            class="h-10 rounded-xl text-xs"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-slate-600">Đến ngày:</label>
                        <Input
                            type="date"
                            :value="leaveEnd"
                            @input="emit('update:leaveEnd', ($event.target as HTMLInputElement).value)"
                            class="h-10 rounded-xl text-xs"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Lý do nghỉ phép:</label>
                    <Input
                        type="text"
                        placeholder="Ghi rõ lý do..."
                        :value="leaveReason"
                        @input="emit('update:leaveReason', ($event.target as HTMLInputElement).value)"
                        class="h-10 rounded-xl text-xs"
                    />
                </div>

                <Button
                    class="mt-2 rounded-xl bg-indigo-600 text-xs font-bold hover:bg-indigo-700"
                    :disabled="!leaveStart || !leaveEnd || !leaveReason.trim()"
                    @click="emit('handleLeaveRequest')"
                >
                    Nộp Đơn Xin Nghỉ
                </Button>
            </div>

            <!-- Content Tab Khiếu nại ẩn danh -->
            <div v-else-if="selfServiceTab === 'complaint'" class="flex flex-col gap-4 text-left">
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Đối tượng phản ánh:</label>
                    <select
                        :value="complaintTargetId ?? ''"
                        @change="emit('update:complaintTargetId', Number(($event.target as HTMLSelectElement).value) || null)"
                        class="h-10 w-full rounded-xl border bg-white px-3 text-xs font-bold dark:bg-slate-950"
                    >
                        <option value="">-- Chọn đồng nghiệp / quản lý --</option>
                        <option v-for="c in colleagues" :key="c.id" :value="c.id">
                            {{ c.full_name }} ({{ c.job_title || 'Nhân viên' }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Loại vi phạm / Vấn đề:</label>
                    <select
                        :value="complaintType"
                        @change="emit('update:complaintType', ($event.target as HTMLSelectElement).value)"
                        class="h-10 w-full rounded-xl border bg-white px-3 text-xs font-bold dark:bg-slate-950"
                    >
                        <option value="attitude">Thái độ làm việc / Đi muộn</option>
                        <option value="fraud">Gian lận / Thất thoát tiền quỹ</option>
                        <option value="conflict">Xung đột cá nhân trong ca</option>
                        <option value="other">Vấn đề khác</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600">Mô tả sự việc chi tiết:</label>
                    <textarea
                        rows="3"
                        placeholder="Mô tả sự việc..."
                        :value="complaintDescription"
                        @input="emit('update:complaintDescription', ($event.target as HTMLTextAreaElement).value)"
                        class="w-full rounded-xl border p-2.5 text-xs font-medium dark:bg-slate-950"
                    ></textarea>
                </div>

                <Button
                    class="mt-2 rounded-xl bg-rose-600 text-xs font-bold hover:bg-rose-700 text-white"
                    :disabled="!complaintTargetId || !complaintType || !complaintDescription.trim()"
                    @click="emit('handleComplaint')"
                >
                    Gửi Khiếu Nại Ẩn Danh
                </Button>
            </div>
        </div>
    </div>
</template>
