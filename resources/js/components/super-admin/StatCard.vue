<script setup lang="ts">
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { computed, toRef } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useCountUp } from '@/composables/useCountUp';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        icon: Component;
        color?: string;
        trend?: 'up' | 'down' | 'neutral';
        change?: string;
        loading?: boolean;
        clickable?: boolean;
        sparkline?: number[];
        class?: HTMLAttributes['class'];
    }>(),
    { color: 'sky', clickable: false },
);

defineEmits<{ click: [] }>();

const numericValue = computed(() =>
    typeof props.value === 'number' ? props.value : NaN,
);

const targetRef = toRef(() =>
    isNaN(numericValue.value) ? 0 : numericValue.value,
);
const displayedValue = useCountUp(targetRef);

const colorClasses: Record<
    string,
    { icon: string; gradient: string; glow: string; sparkline: string }
> = {
    sky: {
        icon: 'bg-sky-500/10 text-sky-600 border-sky-500/20 dark:text-sky-400',
        gradient: 'from-sky-600 to-cyan-500 dark:from-sky-400 dark:to-cyan-300',
        glow: 'shadow-[0_0_30px_rgba(14,165,233,0.1)]',
        sparkline: '#0ea5e9',
    },
    emerald: {
        icon: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20 dark:text-emerald-400',
        gradient:
            'from-emerald-600 to-teal-500 dark:from-emerald-400 dark:to-teal-300',
        glow: 'shadow-[0_0_30px_rgba(16,185,129,0.1)]',
        sparkline: '#10b981',
    },
    violet: {
        icon: 'bg-violet-500/10 text-violet-600 border-violet-500/20 dark:text-violet-400',
        gradient:
            'from-violet-600 to-purple-500 dark:from-violet-400 dark:to-purple-300',
        glow: 'shadow-[0_0_30px_rgba(139,92,246,0.1)]',
        sparkline: '#8b5cf6',
    },
    purple: {
        icon: 'bg-purple-500/10 text-purple-600 border-purple-500/20 dark:text-purple-400',
        gradient:
            'from-purple-600 to-fuchsia-500 dark:from-purple-400 dark:to-fuchsia-300',
        glow: 'shadow-[0_0_30px_rgba(168,85,247,0.1)]',
        sparkline: '#a855f7',
    },
    amber: {
        icon: 'bg-amber-500/10 text-amber-600 border-amber-500/20 dark:text-amber-400',
        gradient:
            'from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-300',
        glow: 'shadow-[0_0_30px_rgba(245,158,11,0.1)]',
        sparkline: '#f59e0b',
    },
    rose: {
        icon: 'bg-rose-500/10 text-rose-600 border-rose-500/20 dark:text-rose-400',
        gradient:
            'from-rose-600 to-pink-500 dark:from-rose-400 dark:to-pink-300',
        glow: 'shadow-[0_0_30px_rgba(244,63,94,0.1)]',
        sparkline: '#f43f5e',
    },
    cyan: {
        icon: 'bg-cyan-500/10 text-cyan-600 border-cyan-500/20 dark:text-cyan-400',
        gradient: 'from-cyan-600 to-sky-500 dark:from-cyan-400 dark:to-sky-300',
        glow: 'shadow-[0_0_30px_rgba(6,182,212,0.1)]',
        sparkline: '#06b6d4',
    },
    indigo: {
        icon: 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20 dark:text-indigo-400',
        gradient:
            'from-indigo-600 to-blue-500 dark:from-indigo-400 dark:to-blue-300',
        glow: 'shadow-[0_0_30px_rgba(99,102,241,0.1)]',
        sparkline: '#6366f1',
    },
    blue: {
        icon: 'bg-blue-500/10 text-blue-600 border-blue-500/20 dark:text-blue-400',
        gradient: 'from-blue-600 to-sky-500 dark:from-blue-400 dark:to-sky-300',
        glow: 'shadow-[0_0_30px_rgba(59,130,246,0.1)]',
        sparkline: '#3b82f6',
    },
};

const colors = computed(() => colorClasses[props.color] ?? colorClasses.sky);

const trendIcon = computed(() => {
    if (props.trend === 'up') {
        return TrendingUp;
    }

    if (props.trend === 'down') {
        return TrendingDown;
    }

    return Minus;
});

const trendColor = computed(() => {
    if (props.trend === 'up') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (props.trend === 'down') {
        return 'text-rose-600 dark:text-rose-400';
    }

    return 'text-muted-foreground';
});

const sparklinePoints = computed(() => {
    if (!props.sparkline?.length) {
        return '';
    }

    const max = Math.max(...props.sparkline);
    const min = Math.min(...props.sparkline);
    const range = max - min || 1;

    return props.sparkline
        .map((v, i) => {
            const x = (i / (props.sparkline!.length - 1)) * 100;
            const y = 18 - ((v - min) / range) * 16;

            return `${x},${y}`;
        })
        .join(' ');
});
</script>

<template>
    <div
        :class="
            cn(
                'group relative overflow-hidden rounded-2xl border border-white/[0.12] bg-card/90 p-4 shadow-[var(--shadow-premium)] backdrop-blur-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-white/20 hover:shadow-[var(--shadow-elevated)] dark:border-white/[0.08] dark:bg-card/80',
                clickable && 'cursor-pointer',
                props.class,
            )
        "
        @click="clickable && $emit('click')"
    >
        <!-- Hover glow overlay -->
        <div
            :class="
                cn(
                    'pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-500 group-hover:opacity-100',
                    colors.glow,
                )
            "
        />

        <template v-if="loading">
            <div class="flex items-center gap-4">
                <Skeleton class="size-11 rounded-xl" />
                <div class="flex-1 space-y-2">
                    <Skeleton class="h-3 w-20" />
                    <Skeleton class="h-6 w-16" />
                </div>
            </div>
        </template>

        <template v-else>
            <div class="relative flex items-center gap-4">
                <div
                    :class="
                        cn(
                            'flex items-center justify-center rounded-xl border p-2.5 transition-all duration-300 group-hover:scale-110 group-hover:shadow-lg',
                            colors.icon,
                        )
                    "
                >
                    <component :is="icon" class="size-5" />
                </div>
                <div class="flex min-w-0 flex-col">
                    <span
                        class="text-[10px] font-medium tracking-wider text-muted-foreground uppercase"
                    >
                        {{ label }}
                    </span>
                    <span
                        :class="
                            cn(
                                'bg-gradient-to-r bg-clip-text text-xl font-bold tracking-tight text-transparent tabular-nums',
                                colors.gradient,
                            )
                        "
                    >
                        {{ typeof value === 'number' ? displayedValue : value }}
                    </span>
                </div>
            </div>

            <!-- Sparkline -->
            <svg
                v-if="sparkline?.length"
                class="mt-2 h-3 w-full opacity-40 transition-opacity group-hover:opacity-70"
                viewBox="0 0 100 20"
                preserveAspectRatio="none"
            >
                <polyline
                    :points="sparklinePoints"
                    fill="none"
                    :stroke="colors.sparkline"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>

            <div
                v-if="change || trend"
                class="relative mt-3 flex items-center gap-1.5 text-xs"
            >
                <component
                    :is="trendIcon"
                    v-if="trend"
                    :class="
                        cn(
                            'size-3.5 transition-transform group-hover:animate-bounce',
                            trendColor,
                        )
                    "
                />
                <span v-if="change" :class="trendColor">{{ change }}</span>
            </div>
        </template>
    </div>
</template>
