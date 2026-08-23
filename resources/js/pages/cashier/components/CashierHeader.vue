<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Coffee, User, Clock } from 'lucide-vue-next';
import { computed } from 'vue';
import BranchContextSelector from '@/components/BranchContextSelector.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    employee?: { full_name: string } | null;
    shiftInfo?: {
        active_shift: { shift_name: string } | null;
        shift_revenue: number;
    } | null;
    currentTime: string;
    currentDate: string;
    wsConnected: boolean;
}>();

const emit = defineEmits<{
    (e: 'openSelfService', tab: 'schedule' | 'leave' | 'complaint'): void;
}>();

const page = usePage();
const restaurantName = computed(
    () => (page.props.auth?.user as any)?.restaurant?.name || 'Aventura POS',
);

const isManager = computed(() => {
    const roles = (page.props.auth?.user as any)?.roles ?? [];

    return roles.some((r: string) =>
        ['owner', 'manager', 'accountant', 'super_admin'].includes(r),
    );
});

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <div
        class="flex flex-col items-start justify-between gap-4 rounded-3xl border border-slate-200/50 bg-white/70 p-6 shadow-sm backdrop-blur-md md:flex-row md:items-center dark:border-slate-800/80 dark:bg-slate-900/80"
    >
        <div>
            <h1
                class="flex items-center gap-2.5 text-2xl font-black text-slate-800 dark:text-slate-100"
            >
                <Coffee class="size-6 text-indigo-600" />
                {{ restaurantName.toLowerCase().includes('pos') ? restaurantName : restaurantName + ' POS' }}
                <span
                    class="ml-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold transition-all duration-300"
                    :class="
                        wsConnected
                            ? 'border border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/40 dark:text-emerald-400'
                            : 'animate-pulse border border-amber-200 bg-amber-100 text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/40 dark:text-amber-400'
                    "
                    :title="
                        wsConnected
                            ? 'Đã kết nối WebSockets Realtime'
                            : 'Mạng yếu - Tự động tải lại dự phòng (Polling)'
                    "
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="wsConnected ? 'bg-emerald-500' : 'bg-amber-500'"
                    ></span>
                    {{ wsConnected ? 'Realtime' : 'Polling (Dự phòng)' }}
                </span>
            </h1>
            <p
                class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-muted-foreground"
            >
                <BranchContextSelector compact :selectable="false" />
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <span
                    >Nhân viên phục vụ:
                    <span class="font-bold text-slate-700 dark:text-slate-300">
                        {{ employee?.full_name ?? 'Chưa cập nhật' }}
                    </span>
                </span>
                <span
                    v-if="shiftInfo?.active_shift"
                    class="text-slate-300 dark:text-slate-700"
                    >|</span
                >
                <span v-if="shiftInfo?.active_shift"
                    >Ca làm việc:
                    <span
                        class="font-bold text-indigo-600 dark:text-indigo-400"
                    >
                        {{ shiftInfo.active_shift.shift_name }}
                    </span>
                </span>
                <span v-if="isManager" class="text-slate-300 dark:text-slate-700">|</span>
                <span v-if="isManager"
                    >Doanh thu ca này:
                    <span
                        class="font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ numberFormat(shiftInfo?.shift_revenue ?? 0) }}đ
                    </span>
                </span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Button
                variant="outline"
                class="gap-2 rounded-2xl text-xs font-bold"
                @click="emit('openSelfService', 'schedule')"
            >
                <User class="size-4 text-indigo-600" />
                Hành chính & Tự phục vụ
            </Button>

            <div
                class="flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-2.5 dark:bg-slate-800"
            >
                <Clock class="size-4 text-slate-500" />
                <div class="text-left font-mono">
                    <span
                        class="text-sm font-bold text-slate-800 dark:text-slate-200"
                        >{{ currentTime }}</span
                    >
                    <span class="ml-2 text-[10px] text-muted-foreground">{{
                        currentDate
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
