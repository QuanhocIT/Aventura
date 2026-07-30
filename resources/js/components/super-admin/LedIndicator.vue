<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        status: 'online' | 'offline' | 'warning' | 'maintenance';
        label?: string;
        sublabel?: string;
        size?: 'sm' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'sm' },
);

const colorMap: Record<string, { ping: string; dot: string; glow: string }> = {
    online: {
        ping: 'bg-emerald-400',
        dot: 'bg-emerald-500',
        glow: 'shadow-[0_0_8px_#10b981]',
    },
    offline: {
        ping: 'bg-rose-400',
        dot: 'bg-rose-500',
        glow: 'shadow-[0_0_8px_#f43f5e]',
    },
    warning: {
        ping: 'bg-amber-400',
        dot: 'bg-amber-500',
        glow: 'shadow-[0_0_8px_#f59e0b]',
    },
    maintenance: {
        ping: 'bg-violet-400',
        dot: 'bg-violet-500',
        glow: 'shadow-[0_0_8px_#8b5cf6]',
    },
};
</script>

<template>
    <div :class="cn('flex items-center gap-2', props.class)">
        <span
            class="relative flex shrink-0"
            :class="size === 'sm' ? 'size-2' : 'size-3'"
        >
            <span
                :class="[
                    'absolute inline-flex h-full w-full rounded-full opacity-75',
                    status !== 'offline' ? 'animate-ping' : '',
                    colorMap[status].ping,
                ]"
            />
            <span
                :class="[
                    'relative inline-flex h-full w-full rounded-full',
                    colorMap[status].dot,
                    colorMap[status].glow,
                ]"
            />
        </span>
        <div v-if="label" class="min-w-0">
            <p
                class="text-[9px] font-extrabold tracking-wider text-muted-foreground uppercase"
            >
                {{ label }}
            </p>
            <p
                v-if="sublabel"
                class="truncate text-[10px] font-black text-slate-800 dark:text-slate-200"
            >
                {{ sublabel }}
            </p>
        </div>
    </div>
</template>
