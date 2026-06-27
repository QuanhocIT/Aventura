<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        value: number;
        max?: number;
        color?: 'emerald' | 'amber' | 'rose' | 'sky' | 'violet' | 'primary' | 'auto';
        size?: 'sm' | 'md';
        showLabel?: boolean;
        animated?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { max: 100, color: 'auto', size: 'sm', showLabel: false, animated: false },
);

const percentage = computed(() => {
    if (props.max <= 0) return 0;
    return Math.min(Math.round((props.value / props.max) * 100), 100);
});

const resolvedColor = computed(() => {
    if (props.color !== 'auto') return props.color;
    if (percentage.value >= 95) return 'rose';
    if (percentage.value >= 80) return 'amber';
    return 'emerald';
});

const barColorClass: Record<string, string> = {
    emerald: 'from-emerald-500 to-emerald-400',
    amber: 'from-amber-500 to-amber-400',
    rose: 'from-rose-500 to-rose-400',
    sky: 'from-sky-500 to-sky-400',
    violet: 'from-violet-500 to-violet-400',
    primary: 'from-primary to-primary/80',
};
</script>

<template>
    <div :class="cn('flex items-center gap-2', props.class)">
        <div :class="cn('flex-1 overflow-hidden rounded-full bg-muted/30', size === 'sm' ? 'h-1.5' : 'h-2.5')">
            <div
                class="relative h-full overflow-hidden rounded-full"
                :style="{ width: `${percentage}%` }"
            >
                <div
                    :class="
                        cn(
                            'absolute inset-0 bg-gradient-to-r transition-all duration-500',
                            barColorClass[resolvedColor] ?? barColorClass.emerald,
                        )
                    "
                />
                <div
                    v-if="animated"
                    class="absolute inset-0 animate-[shimmer_2s_ease_infinite] bg-gradient-to-r from-transparent via-white/20 to-transparent bg-[length:200%_100%]"
                />
            </div>
        </div>
        <span
            v-if="showLabel"
            :class="cn('text-[10px] font-mono tabular-nums text-muted-foreground shrink-0')"
        >
            {{ percentage }}%
        </span>
    </div>
</template>
