<script setup lang="ts">
import { ref, computed } from 'vue';
import { CalendarDays, Calendar, RefreshCw } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    myWeeklySchedules?: any[];
}>();

const emit = defineEmits<{
    (e: 'open-swap', shift: any): void;
}>();

const rosterPage = ref(1);
const rosterPerPage = 10;
const rosterTotalPages = computed(() => Math.ceil((props.myWeeklySchedules?.length || 0) / rosterPerPage));
const paginatedRoster = computed(() => {
    if (!props.myWeeklySchedules) return [];
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
    scheduled: 'bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30',
    checked_in: 'bg-emerald-50 text-emerald-600 border border-emerald-200 animate-pulse dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30',
    completed: 'bg-indigo-50 text-indigo-600 border border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30',
    absent: 'bg-rose-50 text-rose-600 border border-rose-200 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/30',
    leave_approved: 'bg-slate-50 text-slate-600 border border-slate-200 dark:bg-slate-950/20 dark:text-slate-400 dark:border-slate-800',
};
</script>

<template>
    <Card class="shadow-sm">
        <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
            <div>
                <CardTitle class="text-base flex items-center gap-1.5 text-indigo-600">
                    <CalendarDays class="size-5" />
                    Lịch Xếp Ca Trực Cá Nhân Trong Tuần
                </CardTitle>
                <CardDescription>Lịch trình phân phối ca trực của bạn được chốt bởi Quản lý hàng tuần.</CardDescription>
            </div>
        </CardHeader>
        
        <CardContent class="p-0">
            <div v-if="myWeeklySchedules?.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div 
                    v-for="ws in paginatedRoster" 
                    :key="ws.id" 
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors gap-3"
                >
                    <div class="flex items-center gap-3">
                        <!-- Colored weekday initials circle -->
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-black">
                            {{ ws.day_vn.split(' ').pop() }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-sm">{{ ws.day_vn }}</p>
                                <span class="text-[10px] text-slate-400 font-mono">{{ ws.date }}</span>
                            </div>
                            <div class="text-xs font-semibold text-slate-500 mt-0.5 flex items-center gap-1">
                                Ca: <span class="text-indigo-600 dark:text-indigo-400 font-bold font-mono">{{ ws.shift_name }}</span> 
                                · Khung giờ: <span class="font-mono text-slate-600 dark:text-slate-400">{{ ws.shift_time }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Status Tag -->
                    <div class="flex items-center gap-3 self-end sm:self-center font-bold">
                        <!-- Times of check-in/out -->
                        <div v-if="ws.check_in_at" class="text-right text-[10px] font-mono text-slate-400 leading-tight">
                            <div>Vào: {{ ws.check_in_at }}</div>
                            <div v-if="ws.check_out_at">Ra: {{ ws.check_out_at }}</div>
                        </div>

                        <button 
                            v-if="ws.status === 'scheduled'"
                            type="button"
                            @click="emit('open-swap', ws)"
                            class="h-7 px-2 border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-slate-900 hover:bg-indigo-55 bg-indigo-50/30 dark:hover:bg-slate-800 text-indigo-650 dark:text-indigo-400 text-[10px] rounded-lg font-bold flex items-center gap-1 cursor-pointer select-none active:scale-95 transition-all"
                        >
                            <RefreshCw class="size-3" />
                            Đổi ca
                        </button>

                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0 font-sans" :class="statusColors[ws.status]">
                            {{ statusLabels[ws.status] }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div v-if="rosterTotalPages > 1" class="flex items-center justify-between p-4 border-t bg-slate-50/30 dark:bg-slate-900/10">
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    Hiển thị trang <strong>{{ rosterPage }}</strong> / <strong>{{ rosterTotalPages }}</strong> (Tổng số <strong>{{ myWeeklySchedules?.length }}</strong> ca)
                </span>
                <div class="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="rosterPage === 1"
                        @click="rosterPage--"
                        class="h-7 text-xs font-semibold cursor-pointer select-none active:scale-95 disabled:opacity-50"
                    >
                        Trước
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="rosterPage === rosterTotalPages"
                        @click="rosterPage++"
                        class="h-7 text-xs font-semibold cursor-pointer select-none active:scale-95 disabled:opacity-50"
                    >
                        Sau
                    </Button>
                </div>
            </div>
            <div v-else-if="!myWeeklySchedules || !myWeeklySchedules.length" class="py-24 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted mx-auto mb-3">
                    <Calendar class="size-7 text-muted-foreground/40" />
                </div>
                <p class="text-sm font-semibold">Chưa có lịch trực tuần này</p>
                <p class="mt-1 text-xs text-muted-foreground">Vui lòng liên hệ với Quản lý cửa hàng để kiểm tra việc xếp ca làm việc của bạn.</p>
            </div>
        </CardContent>
    </Card>
</template>
