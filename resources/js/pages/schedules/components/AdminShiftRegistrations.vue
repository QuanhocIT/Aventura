<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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
            dateStr: `${yyyy}-${mm}-${dd}`,
        };
    });
});

const approveRegistration = (regId: number) => {
    isApprovingReg.value = regId;
    router.post(
        '/schedules/approve-registration',
        {
            registration_id: regId,
        },
        {
            onSuccess: () => {
                import('vue-sonner').then((m) =>
                    m.toast.success('Đã xếp lịch trực từ ca rảnh thành công!'),
                );
            },
            onError: (errors: any) => {
                const errorMsg = errors.error || 'Có lỗi xảy ra khi xếp ca.';
                import('vue-sonner').then((m) => m.toast.error(errorMsg));
            },
            onFinish: () => {
                isApprovingReg.value = null;
            },
        },
    );
};
</script>

<template>
    <Card class="shadow-sm print:hidden">
        <CardHeader class="border-b pb-3">
            <CardTitle
                class="dark:text-emerald-450 flex items-center gap-1.5 text-base text-emerald-600"
            >
                <Clock class="size-5" />
                Tổng Hợp Đăng Ký Ca Rảnh Của Nhân Sự Tuần Này
            </CardTitle>
            <CardDescription
                >Xem nhanh danh sách nhân viên rảnh và có thể đi làm tại từng ca
                trong tuần.</CardDescription
            >
        </CardHeader>
        <CardContent class="p-4">
            <div
                class="overflow-hidden rounded-2xl border bg-white dark:bg-slate-950"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="border-b bg-slate-50 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                        >
                            <th class="w-[120px] border-r p-3.5">
                                Thứ trong tuần
                            </th>
                            <th class="p-3.5">
                                Danh sách nhân viên đăng ký rảnh theo ca
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
                            <td class="p-3.5">
                                <div
                                    class="grid grid-cols-1 gap-3 md:grid-cols-3"
                                >
                                    <div
                                        v-for="shift in shifts"
                                        :key="shift.id"
                                        class="rounded-xl border border-slate-100 bg-slate-50/30 p-2 dark:border-slate-800 dark:bg-slate-900/10"
                                    >
                                        <div
                                            class="text-indigo-650 flex items-center gap-1 text-[10px] font-bold dark:text-indigo-400"
                                        >
                                            <span
                                                class="size-1 rounded-full bg-indigo-500"
                                            />
                                            {{ shift.name.split(' (')[0] }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <button
                                                v-for="r in registrations?.filter(
                                                    (reg) =>
                                                        reg.day === day.key &&
                                                        reg.shift_id ===
                                                            shift.id,
                                                )"
                                                :key="r.id"
                                                @click="
                                                    approveRegistration(r.id)
                                                "
                                                :disabled="
                                                    isApprovingReg === r.id
                                                "
                                                class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-emerald-100 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-600 transition-all hover:bg-emerald-100 active:scale-95 disabled:opacity-50 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400"
                                                title="Click để duyệt nhanh ca rảnh và xếp lịch trực cho nhân sự này"
                                            >
                                                <span>{{
                                                    r.employee_name
                                                }}</span>
                                                <span
                                                    class="shrink-0 text-[8px] font-bold text-emerald-500 opacity-80"
                                                    >(+ Xếp)</span
                                                >
                                            </button>
                                            <span
                                                v-if="
                                                    !registrations?.some(
                                                        (reg) =>
                                                            reg.day ===
                                                                day.key &&
                                                            reg.shift_id ===
                                                                shift.id,
                                                    )
                                                "
                                                class="text-[10px] font-medium text-slate-400 italic"
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
