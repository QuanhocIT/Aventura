<script setup lang="ts">
import { Clock, AlertTriangle } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps<{
    sentAtRaw: string;
    sentAtFormatted: string;
    prepMinutes: number;
}>();

const nowTime = ref(new Date());
let interval: ReturnType<typeof setInterval> | null = null;

const getMinutesElapsed = () => {
    if (!props.sentAtRaw) {
        return 0;
    }

    const diffMs = nowTime.value.getTime() - new Date(props.sentAtRaw).getTime();

    return Math.max(0, Math.floor(diffMs / 60000));
};

const elapsed = computed(() => getMinutesElapsed());

const slaLevel = computed((): 'ok' | 'warn' | 'late' => {
    const el = elapsed.value;

    if (el >= props.prepMinutes * 1.5) {
        return 'late';
    }

    if (el >= props.prepMinutes) {
        return 'warn';
    }

    return 'ok';
});

onMounted(() => {
    // Cập nhật bộ đếm cục bộ mỗi 10 giây
    interval = setInterval(() => {
        nowTime.value = new Date();
    }, 10000);
});

onUnmounted(() => {
    if (interval) {
        clearInterval(interval);
    }
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 text-[10px] font-semibold text-muted-foreground">
        <span class="flex items-center gap-1">
            <Clock class="size-3 text-indigo-500" />
            Nhận: {{ sentAtFormatted }}
        </span>
        <span>•</span>

        <!-- Cảnh báo SLA theo thời gian chuẩn riêng của món -->
        <span
            v-if="slaLevel === 'late'"
            class="flex items-center gap-0.5 rounded bg-red-500/10 px-1.5 py-0.5 font-extrabold text-red-500"
        >
            <AlertTriangle class="size-3 shrink-0 text-red-500" />
            Chờ {{ elapsed }}p / chuẩn {{ prepMinutes }}p!
        </span>
        <span
            v-else-if="slaLevel === 'warn'"
            class="flex items-center gap-0.5 rounded bg-amber-400/10 px-1.5 py-0.5 font-extrabold text-amber-600 dark:text-amber-400"
        >
            <Clock class="size-3 shrink-0" />
            Chờ {{ elapsed }}p / chuẩn {{ prepMinutes }}p
        </span>
        <span
            v-else
            class="font-medium text-slate-500"
        >
            Chờ {{ elapsed }}p / {{ prepMinutes }}p
        </span>
    </div>
</template>
