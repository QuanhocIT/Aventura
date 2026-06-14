<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, Sparkles, LogIn, LogOut, CheckCircle2, AlertCircle, HelpCircle } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    todayActiveAssignment: any;
    currentTime: string;
}>();

const emit = defineEmits<{
    (e: 'check-in'): void;
}>();

const liveDuration = ref('00:00:00');
let durationInterval: any = null;

const parseDateTimeStr = (str: string) => {
    const [time, date] = str.split(' ');
    const [h, i, s] = time.split(':').map(Number);
    const [d, m, y] = date.split('/').map(Number);

    return new Date(y, m - 1, d, h, i, s);
};

const startLiveDurationTimer = (checkInStr: string) => {
    const checkIn = parseDateTimeStr(checkInStr);
    
    if (durationInterval) {
clearInterval(durationInterval);
}
    
    const updateTimer = () => {
        const diffMs = new Date().getTime() - checkIn.getTime();

        if (diffMs < 0) {
            liveDuration.value = '00:00:00';

            return;
        }

        const totalSecs = Math.floor(diffMs / 1000);
        const hrs = Math.floor(totalSecs / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        const secs = totalSecs % 60;
        liveDuration.value = `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };
    
    updateTimer();
    durationInterval = setInterval(updateTimer, 1000);
};

const handleCheckOut = () => {
    if (confirm('Bạn chắc chắn muốn check-out ra ca trực hiện tại? Hệ thống sẽ ghi nhận giờ chấm công của bạn.')) {
        router.post('/schedules/check-out', {}, {
            onFinish: () => {
                if (durationInterval) {
clearInterval(durationInterval);
}
            }
        });
    }
};

watch(() => props.todayActiveAssignment, (newAssign) => {
    if (newAssign && newAssign.status === 'checked_in' && newAssign.check_in_at) {
        startLiveDurationTimer(newAssign.check_in_at);
    } else {
        if (durationInterval) {
            clearInterval(durationInterval);
            durationInterval = null;
        }

        liveDuration.value = '00:00:00';
    }
}, { immediate: true });

onMounted(() => {
    if (props.todayActiveAssignment && props.todayActiveAssignment.status === 'checked_in' && props.todayActiveAssignment.check_in_at) {
        startLiveDurationTimer(props.todayActiveAssignment.check_in_at);
    }
});

onUnmounted(() => {
    if (durationInterval) {
clearInterval(durationInterval);
}
});
</script>

<template>
    <Card class="shadow-md border-indigo-100 bg-gradient-to-b from-indigo-50/20 to-white dark:from-slate-900/50 dark:to-slate-900 h-full flex flex-col justify-between">
        <CardHeader class="pb-3 border-b text-center">
            <CardTitle class="text-base text-indigo-650 flex items-center justify-center gap-1.5">
                <Clock class="size-5" />
                Giao Diện Bấm Giờ Chấm Công
            </CardTitle>
            <CardDescription>Bấm giờ để chấm công thời gian làm việc thực tế cho ca trực của bạn hôm nay.</CardDescription>
        </CardHeader>

        <CardContent class="p-6 flex flex-col items-center justify-center flex-1 space-y-6">
            <!-- Clock Display Face -->
            <div class="relative flex flex-col items-center justify-center h-44 w-44 rounded-full border-4 border-indigo-200 bg-white dark:bg-slate-950 shadow-inner">
                <div class="absolute inset-0 rounded-full bg-indigo-500/5 blur-xs"></div>
                <span class="text-xs font-bold text-slate-400 dark:text-slate-500 font-mono uppercase tracking-widest text-[9px]">CA HÔM NAY</span>
                <span class="text-[26px] font-black text-indigo-600 dark:text-indigo-400 tracking-tight mt-1 font-mono leading-none">{{ currentTime }}</span>
                <!-- Live Status Indicator -->
                <div class="mt-2 shrink-0">
                    <span v-if="todayActiveAssignment?.status === 'checked_in'" class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-emerald-555 text-white animate-pulse">
                        Đang làm việc
                    </span>
                    <span v-else-if="todayActiveAssignment?.status === 'completed'" class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-indigo-500 text-white">
                        Đã hoàn thành ca
                    </span>
                    <span v-else class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase bg-slate-300 text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Chờ vào ca
                    </span>
                </div>
            </div>

            <!-- Active Shift Info Roster Block -->
            <div v-if="todayActiveAssignment" class="w-full bg-slate-50 dark:bg-slate-900/60 border rounded-2xl p-4 text-center">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Ca trực gán hôm nay</h4>
                <p class="text-sm font-black text-slate-800 dark:text-slate-200 mt-1">{{ todayActiveAssignment.shift_name }}</p>
                <p class="text-xs font-mono font-semibold text-indigo-600 dark:text-indigo-400 mt-0.5">({{ todayActiveAssignment.shift_time }})</p>

                <!-- Check-in details if working -->
                <div v-if="todayActiveAssignment.status === 'checked_in'" class="border-t border-slate-200 dark:border-slate-800 mt-3 pt-3 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-medium">Bắt đầu lúc:</span>
                        <span class="font-bold text-slate-700 dark:text-slate-300 font-mono">{{ todayActiveAssignment.check_in_at?.split(' ')[0] }}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-medium">Số giờ làm:</span>
                        <span class="font-black text-emerald-600 dark:text-emerald-400 font-mono text-sm tracking-tight">{{ liveDuration }}</span>
                    </div>
                </div>
            </div>

            <!-- Illustration if no shift scheduled today -->
            <div v-else class="text-center py-6">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 dark:bg-slate-900 mx-auto mb-3 text-slate-400">
                    <Sparkles class="size-6" />
                </div>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">Nghỉ ngơi thật tốt nhé!</p>
                <p class="text-[11px] text-muted-foreground mt-1 max-w-[200px] mx-auto">Hôm nay bạn không được gán ca làm việc nào. Tận hưởng ngày nghỉ vui vẻ!</p>
            </div>
        </CardContent>

        <!-- Interactive Buttons Block -->
        <CardContent class="pb-6 pt-0 border-t border-indigo-50/60 bg-slate-50/50 dark:bg-slate-950/20 rounded-b-2xl p-6">
            <!-- Check In Action Button -->
            <Button 
                v-if="todayActiveAssignment?.can_check_in"
                @click="emit('check-in')" 
                class="w-full h-12 text-sm font-black bg-indigo-600 hover:bg-indigo-700 text-white shadow-md active:scale-98 animate-pulse flex items-center justify-center gap-1.5 cursor-pointer"
            >
                <LogIn class="size-4" />
                BẤM GIỜ VÀO CA (CHECK IN)
            </Button>
            
            <!-- Check Out Action Button -->
            <Button 
                v-else-if="todayActiveAssignment?.can_check_out"
                @click="handleCheckOut" 
                variant="destructive"
                class="w-full h-12 text-sm font-black bg-rose-600 hover:bg-rose-700 text-white shadow-md active:scale-98 flex items-center justify-center gap-1.5 cursor-pointer"
            >
                <LogOut class="size-4" />
                BẤM GIỜ RA CA (CHECK OUT)
            </Button>

            <!-- Completed state description -->
            <div v-else-if="todayActiveAssignment?.status === 'completed'" class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-xl flex items-start gap-2 text-[11px] text-indigo-700 dark:text-indigo-400 border border-indigo-100/50">
                <CheckCircle2 class="size-4 shrink-0 text-indigo-600 dark:text-indigo-400 mt-0.5" />
                <p><strong>Đã ghi nhận công:</strong> Bạn đã hoàn thành ca trực hôm nay thành công. Dữ liệu thời gian đã được lưu trữ bảo mật để tự động tính lương cuối tháng.</p>
            </div>

            <!-- Awaiting scheduled check-in time block -->
            <div v-else-if="todayActiveAssignment && todayActiveAssignment.status === 'scheduled' && !todayActiveAssignment.can_check_in" class="p-3 bg-amber-50/50 dark:bg-amber-950/20 rounded-xl flex items-start gap-2 text-[11px] text-amber-700 dark:text-amber-400 border border-amber-100/50">
                <AlertCircle class="size-4 shrink-0 text-amber-500 mt-0.5" />
                <p><strong>Chờ giờ check-in:</strong> Lịch xếp ca trực của bạn chưa đến khung thời gian mở khóa. Vui lòng quay lại check-in trước giờ vào ca tối đa 30 phút.</p>
            </div>

            <!-- System rules brief -->
            <div v-else class="text-[10px] text-slate-400 dark:text-slate-500 flex items-start gap-1.5">
                <HelpCircle class="size-3.5 shrink-0 mt-0.5" />
                <p>Hệ thống tự động đồng bộ hóa chấm công với Spatie ACL. Bấm giờ ra ca sẽ chặn truy cập hệ thống để bảo an an ninh vận hành.</p>
            </div>
        </CardContent>
    </Card>
</template>
