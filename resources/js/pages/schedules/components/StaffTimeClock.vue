<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Clock,
    Sparkles,
    LogIn,
    LogOut,
    CheckCircle2,
    AlertCircle,
    HelpCircle,
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';

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

const handleCheckOut = async () => {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn chắc chắn muốn check-out ra ca trực hiện tại? Hệ thống sẽ ghi nhận giờ chấm công của bạn.',
            variant: 'default',
        })
    ) {
        router.post(
            '/schedules/check-out',
            {},
            {
                onFinish: () => {
                    if (durationInterval) {
                        clearInterval(durationInterval);
                    }
                },
            },
        );
    }
};

watch(
    () => props.todayActiveAssignment,
    (newAssign) => {
        if (
            newAssign &&
            newAssign.status === 'checked_in' &&
            newAssign.check_in_at
        ) {
            startLiveDurationTimer(newAssign.check_in_at);
        } else {
            if (durationInterval) {
                clearInterval(durationInterval);
                durationInterval = null;
            }

            liveDuration.value = '00:00:00';
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (
        props.todayActiveAssignment &&
        props.todayActiveAssignment.status === 'checked_in' &&
        props.todayActiveAssignment.check_in_at
    ) {
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
    <Card
        class="flex h-full flex-col justify-between border-indigo-100 bg-gradient-to-b from-indigo-50/20 to-white shadow-md dark:from-slate-900/50 dark:to-slate-900"
    >
        <CardHeader class="border-b pb-3 text-center">
            <CardTitle
                class="text-indigo-650 flex items-center justify-center gap-1.5 text-base"
            >
                <Clock class="size-5" />
                Giao Diện Bấm Giờ Chấm Công
            </CardTitle>
            <CardDescription
                >Bấm giờ để chấm công thời gian làm việc thực tế cho ca trực của
                bạn hôm nay.</CardDescription
            >
        </CardHeader>

        <CardContent
            class="flex flex-1 flex-col items-center justify-center space-y-6 p-6"
        >
            <!-- Clock Display Face -->
            <div
                class="relative flex h-44 w-44 flex-col items-center justify-center rounded-full border-4 border-indigo-200 bg-white shadow-inner dark:bg-slate-950"
            >
                <div
                    class="absolute inset-0 rounded-full bg-indigo-500/5 blur-xs"
                ></div>
                <span
                    class="font-mono text-xs text-[9px] font-bold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                    >CA HÔM NAY</span
                >
                <span
                    class="mt-1 font-mono text-[26px] leading-none font-black tracking-tight text-indigo-600 dark:text-indigo-400"
                    >{{ currentTime }}</span
                >
                <!-- Live Status Indicator -->
                <div class="mt-2 shrink-0">
                    <span
                        v-if="todayActiveAssignment?.status === 'checked_in'"
                        class="bg-emerald-555 animate-pulse rounded-full px-2 py-0.5 text-[9px] font-extrabold text-white uppercase"
                    >
                        Đang làm việc
                    </span>
                    <span
                        v-else-if="
                            todayActiveAssignment?.status === 'completed'
                        "
                        class="rounded-full bg-indigo-500 px-2 py-0.5 text-[9px] font-extrabold text-white uppercase"
                    >
                        Đã hoàn thành ca
                    </span>
                    <span
                        v-else
                        class="rounded-full bg-slate-300 px-2 py-0.5 text-[9px] font-extrabold text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-400"
                    >
                        Chờ vào ca
                    </span>
                </div>
            </div>

            <!-- Active Shift Info Roster Block -->
            <div
                v-if="todayActiveAssignment"
                class="w-full rounded-2xl border bg-slate-50 p-4 text-center dark:bg-slate-900/60"
            >
                <h4
                    class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                >
                    Ca trực gán hôm nay
                </h4>
                <p
                    class="mt-1 text-sm font-black text-slate-800 dark:text-slate-200"
                >
                    {{ todayActiveAssignment.shift_name }}
                </p>
                <p
                    class="mt-0.5 font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                >
                    ({{ todayActiveAssignment.shift_time }})
                </p>

                <!-- Check-in details if working -->
                <div
                    v-if="todayActiveAssignment.status === 'checked_in'"
                    class="mt-3 space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800"
                >
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-400"
                            >Bắt đầu lúc:</span
                        >
                        <span
                            class="font-mono font-bold text-slate-700 dark:text-slate-300"
                            >{{
                                todayActiveAssignment.check_in_at?.split(' ')[0]
                            }}</span
                        >
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-400"
                            >Số giờ làm:</span
                        >
                        <span
                            class="font-mono text-sm font-black tracking-tight text-emerald-600 dark:text-emerald-400"
                            >{{ liveDuration }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Illustration if no shift scheduled today -->
            <div v-else class="py-6 text-center">
                <div
                    class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-50 text-slate-400 dark:bg-slate-900"
                >
                    <Sparkles class="size-6" />
                </div>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    Nghỉ ngơi thật tốt nhé!
                </p>
                <p
                    class="mx-auto mt-1 max-w-[200px] text-[11px] text-muted-foreground"
                >
                    Hôm nay bạn không được gán ca làm việc nào. Tận hưởng ngày
                    nghỉ vui vẻ!
                </p>
            </div>
        </CardContent>

        <!-- Interactive Buttons Block -->
        <CardContent
            class="rounded-b-2xl border-t border-indigo-50/60 bg-slate-50/50 p-6 pt-0 pb-6 dark:bg-slate-950/20"
        >
            <!-- Check In Action Button -->
            <Button
                v-if="todayActiveAssignment?.can_check_in"
                @click="emit('check-in')"
                class="flex h-12 w-full animate-pulse cursor-pointer items-center justify-center gap-1.5 bg-indigo-600 text-sm font-black text-white shadow-md hover:bg-indigo-700 active:scale-98"
            >
                <LogIn class="size-4" />
                BẤM GIỜ VÀO CA (CHECK IN)
            </Button>

            <!-- Check Out Action Button -->
            <Button
                v-else-if="todayActiveAssignment?.can_check_out"
                @click="handleCheckOut"
                variant="destructive"
                class="flex h-12 w-full cursor-pointer items-center justify-center gap-1.5 bg-rose-600 text-sm font-black text-white shadow-md hover:bg-rose-700 active:scale-98"
            >
                <LogOut class="size-4" />
                BẤM GIỜ RA CA (CHECK OUT)
            </Button>

            <!-- Completed state description -->
            <div
                v-else-if="todayActiveAssignment?.status === 'completed'"
                class="flex items-start gap-2 rounded-xl border border-indigo-100/50 bg-indigo-50 p-3 text-[11px] text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-400"
            >
                <CheckCircle2
                    class="mt-0.5 size-4 shrink-0 text-indigo-600 dark:text-indigo-400"
                />
                <p>
                    <strong>Đã ghi nhận công:</strong> Bạn đã hoàn thành ca trực
                    hôm nay thành công. Dữ liệu thời gian đã được lưu trữ bảo
                    mật để tự động tính lương cuối tháng.
                </p>
            </div>

            <!-- Awaiting scheduled check-in time block -->
            <div
                v-else-if="
                    todayActiveAssignment &&
                    todayActiveAssignment.status === 'scheduled' &&
                    !todayActiveAssignment.can_check_in
                "
                class="flex items-start gap-2 rounded-xl border border-amber-100/50 bg-amber-50/50 p-3 text-[11px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
            >
                <AlertCircle class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <p>
                    <strong>Chờ giờ check-in:</strong> Lịch xếp ca trực của bạn
                    chưa đến khung thời gian mở khóa. Vui lòng quay lại check-in
                    trước giờ vào ca tối đa 30 phút.
                </p>
            </div>

            <!-- System rules brief -->
            <div
                v-else
                class="flex items-start gap-1.5 text-[10px] text-slate-400 dark:text-slate-500"
            >
                <HelpCircle class="mt-0.5 size-3.5 shrink-0" />
                <p>
                    Hệ thống tự động đồng bộ hóa chấm công với Spatie ACL. Bấm
                    giờ ra ca sẽ chặn truy cập hệ thống để bảo an an ninh vận
                    hành.
                </p>
            </div>
        </CardContent>
    </Card>
</template>
