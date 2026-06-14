<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    registrations?: any[];
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
}>();

const isApprovingReg = ref<number | null>(null);

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
</script>

<template>
    <Card class="shadow-sm print:hidden">
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
</template>
