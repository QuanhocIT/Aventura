<script setup lang="ts">
import { CalendarDays, Calendar, RefreshCw } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

const props = defineProps<{
    myWeeklySchedules?: any[];
}>();

const emit = defineEmits<{
    (e: 'open-swap', shift: any): void;
}>();

const rosterPage = ref(1);
const rosterPerPage = 10;
const rosterTotalPages = computed(() =>
    Math.ceil((props.myWeeklySchedules?.length || 0) / rosterPerPage),
);
const paginatedRoster = computed(() => {
    if (!props.myWeeklySchedules) {
        return [];
    }

    const start = (rosterPage.value - 1) * rosterPerPage;
    const end = start + rosterPerPage;

    return props.myWeeklySchedules.slice(start, end);
});

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
            class="flex flex-row items-center justify-between border-b pb-3"
        >
            <div>
                <CardTitle
                    class="flex items-center gap-1.5 text-base text-indigo-600"
                >
                    <CalendarDays class="size-5" />
                    Lịch Xếp Ca Trực Cá Nhân Trong Tuần
                </CardTitle>
                <CardDescription
                    >Lịch trình phân phối ca trực của bạn được chốt bởi Quản lý
                    hàng tuần.</CardDescription
                >
            </div>
        </CardHeader>

        <CardContent class="p-0">
            <div
                v-if="myWeeklySchedules?.length"
                class="divide-y divide-slate-100 dark:divide-slate-800"
            >
                <div
                    v-for="ws in paginatedRoster"
                    :key="ws.id"
                    class="flex flex-col gap-3 p-4 transition-colors hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-slate-900/30"
                >
                    <div class="flex items-center gap-3">
                        <!-- Colored weekday initials circle -->
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-xs font-black text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                        >
                            {{ ws.day_vn.split(' ').pop() }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold">{{ ws.day_vn }}</p>
                                <span
                                    class="font-mono text-[10px] text-slate-400"
                                    >{{ ws.date }}</span
                                >
                            </div>
                            <div
                                class="mt-0.5 flex items-center gap-1 text-xs font-semibold text-slate-500"
                            >
                                Ca:
                                <span
                                    class="font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                    >{{ ws.shift_name }}</span
                                >
                                · Khung giờ:
                                <span
                                    class="font-mono text-slate-600 dark:text-slate-400"
                                    >{{ ws.shift_time }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Status Tag -->
                    <div
                        class="flex items-center gap-3 self-end font-bold sm:self-center"
                    >
                        <!-- Times of check-in/out -->
                        <div
                            v-if="ws.check_in_at"
                            class="text-right font-mono text-[10px] leading-tight text-slate-400"
                        >
                            <div>Vào: {{ ws.check_in_at }}</div>
                            <div v-if="ws.check_out_at">
                                Ra: {{ ws.check_out_at }}
                            </div>
                        </div>

                        <button
                            v-if="ws.status === 'scheduled'"
                            type="button"
                            @click="emit('open-swap', ws)"
                            class="hover:bg-indigo-55 text-indigo-650 flex h-7 cursor-pointer items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50/30 bg-white px-2 text-[10px] font-bold transition-all select-none active:scale-95 dark:border-indigo-800 dark:bg-slate-900 dark:text-indigo-400 dark:hover:bg-slate-800"
                        >
                            <RefreshCw class="size-3" />
                            Đổi ca
                        </button>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 font-sans text-[10px] font-extrabold uppercase"
                            :class="statusColors[ws.status]"
                        >
                            {{ statusLabels[ws.status] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div
                v-if="rosterTotalPages > 1"
                class="flex items-center justify-between border-t bg-slate-50/30 p-4 dark:bg-slate-900/10"
            >
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Hiển thị trang <strong>{{ rosterPage }}</strong> /
                    <strong>{{ rosterTotalPages }}</strong> (Tổng số
                    <strong>{{ myWeeklySchedules?.length }}</strong> ca)
                </span>
                <div class="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="rosterPage === 1"
                        @click="rosterPage--"
                        class="h-7 cursor-pointer text-xs font-semibold select-none active:scale-95 disabled:opacity-50"
                    >
                        Trước
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="rosterPage === rosterTotalPages"
                        @click="rosterPage++"
                        class="h-7 cursor-pointer text-xs font-semibold select-none active:scale-95 disabled:opacity-50"
                    >
                        Sau
                    </Button>
                </div>
            </div>
            <div
                v-else-if="!myWeeklySchedules || !myWeeklySchedules.length"
                class="py-24 text-center"
            >
                <div
                    class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <Calendar class="size-7 text-muted-foreground/40" />
                </div>
                <p class="text-sm font-semibold">Chưa có lịch trực tuần này</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Vui lòng liên hệ với Quản lý cửa hàng để kiểm tra việc xếp
                    ca làm việc của bạn.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
