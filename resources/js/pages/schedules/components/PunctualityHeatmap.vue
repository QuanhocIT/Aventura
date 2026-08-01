<script setup lang="ts">
import { Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    monthlyAssignments?: Array<{
        scheduled_date: string;
        shift_id: number;
        late_minutes: number | null;
    }>;
    shifts?: Array<{
        id: number;
        name: string;
        start: string;
        end: string;
    }>;
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

// Heatmap Data
const heatmapData = computed(() => {
    const grid: Record<
        string,
        Record<number, { total: number; late: number }>
    > = {};
    const days = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];
    days.forEach((day) => {
        grid[day] = {};

        if (props.shifts) {
            props.shifts.forEach((shift) => {
                grid[day][shift.id] = { total: 0, late: 0 };
            });
        }
    });

    if (!props.monthlyAssignments) {
        return grid;
    }

    props.monthlyAssignments.forEach((a) => {
        const date = new Date(a.scheduled_date);
        const dayIndex = date.getDay();
        const dayNames = [
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];
        const dayName = dayNames[dayIndex];

        const shiftId = a.shift_id;

        if (grid[dayName] && shiftId && grid[dayName][shiftId]) {
            grid[dayName][shiftId].total++;

            if (a.late_minutes && a.late_minutes > 0) {
                grid[dayName][shiftId].late++;
            }
        }
    });

    return grid;
});

const getHeatmapColor = (day: string, shiftId: number) => {
    const cell = heatmapData.value[day]?.[shiftId];

    if (!cell || cell.total === 0) {
        return 'bg-slate-50 dark:bg-slate-900/25 text-slate-400 dark:text-slate-600';
    }

    const rate = cell.late / cell.total;

    if (rate === 0) {
        return 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30';
    }

    if (rate <= 0.15) {
        return 'bg-amber-50 dark:bg-amber-950/20 text-amber-605 dark:text-amber-400 border border-amber-100 dark:border-amber-900/30';
    }

    if (rate <= 0.3) {
        return 'bg-orange-50 dark:bg-orange-950/20 text-orange-605 dark:text-orange-400 border border-orange-100 dark:border-orange-900/30';
    }

    if (rate <= 0.5) {
        return 'bg-rose-50 dark:bg-rose-950/20 text-rose-605 dark:text-rose-400 border border-rose-100 dark:border-rose-900/30';
    }

    return 'bg-rose-500 text-white font-bold border border-rose-600';
};
</script>

<template>
    <!-- Visual Punctuality Heatmap -->
    <Card class="mb-6 shadow-sm print:hidden">
        <CardHeader class="border-b pb-3">
            <CardTitle
                class="flex items-center gap-1.5 text-base text-indigo-600"
            >
                <Clock class="text-indigo-650 size-5" />
                Biểu Đồ Nhiệt Đi Trễ & Đúng Giờ Theo Ca (Heatmap)
            </CardTitle>
            <CardDescription
                >Trực quan hóa tỷ lệ đi trễ theo các ngày trong tuần và ca trực
                để tối ưu hóa thời gian vận hành.</CardDescription
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
                            <th class="w-[150px] border-r p-3">
                                Ngày trong tuần
                            </th>
                            <th
                                v-for="shift in shifts"
                                :key="shift.id"
                                class="border-r p-3 text-center"
                            >
                                <div>{{ shift.name.split(' (')[0] }}</div>
                                <div
                                    class="font-mono text-[9px] text-slate-400"
                                >
                                    ({{ shift.start }} - {{ shift.end }})
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <tr
                            v-for="day in weekDays"
                            :key="day.key"
                            class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        >
                            <td
                                class="dark:text-slate-350 border-r bg-slate-50/30 p-3 font-bold text-slate-700"
                            >
                                {{ day.label }}
                            </td>
                            <td
                                v-for="shift in shifts"
                                :key="shift.id"
                                class="border-r p-3 text-center transition-all"
                                :class="getHeatmapColor(day.key, shift.id)"
                            >
                                <div class="font-mono font-bold">
                                    <template
                                        v-if="
                                            heatmapData[day.key]?.[shift.id]
                                                ?.total > 0
                                        "
                                    >
                                        {{
                                            heatmapData[day.key][shift.id].late
                                        }}/{{
                                            heatmapData[day.key][shift.id].total
                                        }}
                                        trễ
                                        <div
                                            class="text-[9px] font-normal opacity-75"
                                        >
                                            ({{
                                                Math.round(
                                                    (heatmapData[day.key][
                                                        shift.id
                                                    ].late /
                                                        heatmapData[day.key][
                                                            shift.id
                                                        ].total) *
                                                        100,
                                                )
                                            }}%)
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span
                                            class="font-normal text-slate-300 dark:text-slate-700"
                                            >—</span
                                        >
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Legend -->
            <div
                class="mt-4 flex flex-wrap items-center gap-4 text-[10px] text-slate-500"
            >
                <span class="font-bold tracking-wider text-slate-400 uppercase"
                    >Chú thích tỉ lệ trễ:</span
                >
                <div class="flex items-center gap-1">
                    <span
                        class="bg-emerald-55 size-3 rounded border border-emerald-200 dark:bg-emerald-950/20"
                    ></span>
                    <span>0% (Hoàn hảo)</span>
                </div>
                <div class="flex items-center gap-1">
                    <span
                        class="bg-amber-55 size-3 rounded border border-amber-200 dark:bg-amber-950/20"
                    ></span>
                    <span>&le; 15%</span>
                </div>
                <div class="flex items-center gap-1">
                    <span
                        class="bg-orange-55 size-3 rounded border border-orange-200 dark:bg-orange-950/20"
                    ></span>
                    <span>&le; 30%</span>
                </div>
                <div class="flex items-center gap-1">
                    <span
                        class="bg-rose-55 size-3 rounded border border-rose-200 dark:bg-rose-950/20"
                    ></span>
                    <span>&le; 50%</span>
                </div>
                <div class="flex items-center gap-1">
                    <span
                        class="size-3 rounded border border-rose-600 bg-rose-500"
                    ></span>
                    <span>&gt; 50% (Báo động)</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
