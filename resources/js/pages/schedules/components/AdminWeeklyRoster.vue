<script setup lang="ts">
import { CalendarDays, ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    weeklyAssignments?: any[];
}>();

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
</script>

<template>
    <Card class="shadow-sm print:hidden">
        <CardHeader
            class="flex flex-row items-center justify-between border-b pb-3"
        >
            <div>
                <CardTitle
                    class="flex items-center gap-1.5 text-base text-indigo-600"
                >
                    <CalendarDays class="size-5" />
                    Roster Toàn Hệ Thống Tuần Này
                </CardTitle>
                <CardDescription
                    >Tổng quan nhanh phân công ca trực từ Thứ 2 đến Chủ nhật của
                    mọi nhân viên.</CardDescription
                >
            </div>
            <a
                href="/employees"
                class="flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 hover:underline"
            >
                Đi tới xếp lịch <ArrowLeft class="size-3 rotate-180" />
            </a>
        </CardHeader>
        <CardContent class="p-4">
            <div
                class="overflow-hidden rounded-2xl border bg-white dark:bg-slate-950"
            >
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="bg-slate-55 border-b text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-900"
                        >
                            <th class="w-[120px] border-r p-3.5">
                                Thứ trong tuần
                            </th>
                            <th class="p-3.5">Danh sách phân ca nhân viên</th>
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
                            <td class="flex flex-wrap items-center gap-2 p-3.5">
                                <div
                                    v-for="(
                                        s, idx
                                    ) in weeklyAssignments?.filter(
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
                                </div>
                                <div
                                    v-if="
                                        !weeklyAssignments?.some(
                                            (sc) => sc.day === day.key,
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
</template>
