<script setup lang="ts">
import {
    Calendar,
    Sparkles,
    CheckCircle2,
    AlertCircle,
    Ban,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import PunctualityHeatmap from './PunctualityHeatmap.vue';

const props = defineProps<{
    monthlyAssignments?: any[];
    shifts?: Array<{ id: number; name: string; start: string; end: string }>;
}>();

const analyticsData = computed(() => {
    if (!props.monthlyAssignments || props.monthlyAssignments.length === 0) {
        return {
            totalAssignments: 0,
            onTimeCount: 0,
            lateCount: 0,
            absentCount: 0,
            punctualityRate: 0,
            employeeStats: [],
        };
    }

    const assignments = props.monthlyAssignments;
    const total = assignments.length;

    const punctualityChecked = assignments.filter(
        (a) => a.status === 'completed' || a.status === 'checked_in',
    );
    const late = punctualityChecked.filter(
        (a) => a.late_minutes && a.late_minutes > 0,
    );
    const absent = assignments.filter((a) => a.status === 'absent');
    const onTime = punctualityChecked.length - late.length;

    const punctualityRate =
        punctualityChecked.length > 0
            ? Math.round((onTime / punctualityChecked.length) * 100)
            : 0;

    const employeeMap: Record<
        number,
        {
            name: string;
            code: string;
            jobTitle: string;
            compensationType: string;
            payRate: number;
            baseSalary: number;
            totalHours: number;
            completedCount: number;
            lateCount: number;
        }
    > = {};

    assignments.forEach((a) => {
        if (!employeeMap[a.employee_id]) {
            employeeMap[a.employee_id] = {
                name: a.employee_name,
                code: a.employee_code,
                jobTitle: a.job_title,
                compensationType: a.compensation_type,
                payRate: a.pay_rate,
                baseSalary: a.base_salary,
                totalHours: 0,
                completedCount: 0,
                lateCount: 0,
            };
        }

        const emp = employeeMap[a.employee_id];

        if (a.status === 'completed') {
            emp.completedCount++;
            emp.totalHours += a.duration_hours;
        } else if (a.status === 'checked_in') {
            emp.totalHours += a.duration_hours;
        }

        if (a.late_minutes && a.late_minutes > 0) {
            emp.lateCount++;
        }
    });

    const employeeStats = Object.values(employeeMap).map((emp) => {
        let estimatedWage = 0;

        if (emp.compensationType === 'hourly') {
            estimatedWage = emp.totalHours * emp.payRate;
        } else if (emp.compensationType === 'shift') {
            estimatedWage = emp.completedCount * emp.payRate;
        } else {
            estimatedWage = (emp.baseSalary / 208) * emp.totalHours;
        }

        return {
            ...emp,
            totalHours: Math.round(emp.totalHours * 100) / 100,
            estimatedWage: Math.round(estimatedWage),
        };
    });

    return {
        totalAssignments: total,
        onTimeCount: onTime,
        lateCount: late.length,
        absentCount: absent.length,
        punctualityRate,
        employeeStats,
    };
});
</script>

<template>
    <div>
        <!-- Analytics Summary KPIs -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
            <Card
                class="shadow-xs transition-transform hover:translate-y-[-2px]"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Ca làm tháng này</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex items-center justify-between pb-3">
                    <span
                        class="font-mono text-3xl font-black text-slate-800 dark:text-slate-100"
                        >{{ analyticsData.totalAssignments }}</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800"
                    >
                        <Calendar class="size-4" />
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-indigo-150 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Tỷ lệ đúng giờ</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex items-center justify-between pb-3">
                    <span
                        class="font-mono text-3xl font-black text-indigo-600 dark:text-indigo-400"
                        >{{ analyticsData.punctualityRate }}%</span
                    >
                    <div
                        class="text-indigo-650 flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-950/40 dark:text-indigo-400"
                    >
                        <Sparkles class="size-4 animate-pulse" />
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-emerald-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="font-sans text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Đúng giờ</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex items-center justify-between pb-3">
                    <span
                        class="font-mono text-3xl font-black text-emerald-600 dark:text-emerald-400"
                        >{{ analyticsData.onTimeCount }}</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40"
                    >
                        <CheckCircle2 class="size-4" />
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-amber-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-amber-950/20"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-amber-500 uppercase"
                        >Đi trễ</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex items-center justify-between pb-3">
                    <span
                        class="font-mono text-3xl font-black text-amber-600 dark:text-amber-400"
                        >{{ analyticsData.lateCount }}</span
                    >
                    <div
                        class="text-amber-650 flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950/40"
                    >
                        <AlertCircle class="size-4" />
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-rose-100 shadow-xs transition-transform hover:translate-y-[-2px] dark:border-rose-950/20"
            >
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >Vắng mặt</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex items-center justify-between pb-3">
                    <span
                        class="font-mono text-3xl font-black text-rose-600 dark:text-rose-400"
                        >{{ analyticsData.absentCount }}</span
                    >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/40"
                    >
                        <Ban class="size-4" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Visual Punctuality Heatmap -->
        <PunctualityHeatmap
            :monthly-assignments="monthlyAssignments"
            :shifts="shifts"
        />

        <!-- Detailed Table -->
        <Card class="mt-6 shadow-sm">
            <CardHeader class="border-b pb-3">
                <CardTitle
                    class="text-indigo-655 flex items-center gap-1.5 text-base"
                >
                    <Users class="text-indigo-650 size-5" />
                    Báo Cáo Tổng Hợp Công & Dự Tính Lương Trong Tháng
                </CardTitle>
                <CardDescription
                    >Bảng thống kê số giờ làm việc thực tế, số lần đi trễ và mức
                    lương tích lũy ước tính của nhân viên.</CardDescription
                >
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="analyticsData.employeeStats.length > 0"
                    class="overflow-x-auto"
                >
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-100 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:bg-slate-950"
                            >
                                <th class="p-3.5">Nhân viên</th>
                                <th class="p-3.5">Hình thức lương</th>
                                <th class="p-3.5">Số ca hoàn thành</th>
                                <th class="p-3.5">Tổng số giờ làm</th>
                                <th class="p-3.5">Số lần đi trễ</th>
                                <th class="p-3.5 text-right">
                                    Lương tích lũy dự tính
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="emp in analyticsData.employeeStats"
                                :key="emp.code"
                                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                            >
                                <td class="p-3.5">
                                    <div
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ emp.name }}
                                    </div>
                                    <div
                                        class="mt-0.5 font-mono text-[10px] text-slate-400"
                                    >
                                        {{ emp.code }} · {{ emp.jobTitle }}
                                    </div>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded px-2 py-0.5 text-[10px] font-bold"
                                        :class="[
                                            emp.compensationType === 'hourly'
                                                ? 'border border-blue-100 bg-blue-50 text-blue-600 dark:bg-blue-950/20 dark:text-blue-400'
                                                : '',
                                            emp.compensationType === 'shift'
                                                ? 'border border-amber-100 bg-amber-50 text-amber-600 dark:bg-amber-950/20 dark:text-amber-400'
                                                : '',
                                            emp.compensationType === 'fixed'
                                                ? 'border border-purple-100 bg-purple-50 text-purple-600 dark:bg-purple-950/20 dark:text-purple-400'
                                                : '',
                                        ]"
                                    >
                                        {{
                                            emp.compensationType === 'hourly'
                                                ? 'Theo giờ'
                                                : emp.compensationType ===
                                                    'shift'
                                                  ? 'Theo ca'
                                                  : 'Lương cứng'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="dark:text-slate-350 p-3.5 font-mono text-slate-600"
                                >
                                    {{ emp.completedCount }} ca
                                </td>
                                <td
                                    class="p-3.5 font-mono font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ emp.totalHours }} giờ
                                </td>
                                <td class="p-3.5 font-mono">
                                    <span
                                        v-if="emp.lateCount > 0"
                                        class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 font-bold text-amber-600 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
                                    >
                                        {{ emp.lateCount }} lần
                                    </span>
                                    <span
                                        v-else
                                        class="font-bold text-emerald-600"
                                        >0</span
                                    >
                                </td>
                                <td
                                    class="text-indigo-650 p-3.5 text-right font-mono text-sm font-black dark:text-indigo-400"
                                >
                                    {{
                                        emp.estimatedWage.toLocaleString(
                                            'vi-VN',
                                        )
                                    }}
                                    ₫
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="py-16 text-center text-slate-400">
                    <p class="text-xs font-semibold">
                        Chưa có dữ liệu thống kê nào trong tháng
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
