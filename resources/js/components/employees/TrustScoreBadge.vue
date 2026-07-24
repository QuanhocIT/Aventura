<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    score: number;
    showLabel?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showLabel: true,
});

const config = computed(() => {
    if (props.score >= 85) {
        return {
            cls: 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800',
            label: 'Xuất sắc',
            icon: '✦',
        };
    }

    if (props.score >= 70) {
        return {
            cls: 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800',
            label: 'Tốt',
            icon: '●',
        };
    }

    if (props.score >= 55) {
        return {
            cls: 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800',
            label: 'Cần chú ý',
            icon: '⚠',
        };
    }

    if (props.score >= 40) {
        return {
            cls: 'bg-orange-100 text-orange-800 border-orange-300 dark:bg-orange-950/40 dark:text-orange-300 dark:border-orange-800',
            label: 'Nguy cơ cao',
            icon: '⚠',
        };
    }

    return {
        cls: 'bg-rose-100 text-rose-800 border-rose-300 animate-pulse dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800',
        label: 'Nghiêm trọng',
        icon: '🚨',
    };
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold transition-all shadow-sm"
        :class="config.cls"
        :title="`Điểm tín nhiệm nhân viên: ${score}/100`"
    >
        <span>{{ config.icon }}</span>
        <span>{{ score.toFixed(0) }}</span>
        <span v-if="showLabel" class="text-[10px] font-semibold opacity-90">
            · {{ config.label }}
        </span>
    </span>
</template>
