<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title?: string;
        statusText?: string;
        statusColor?: 'emerald' | 'amber' | 'rose';
        class?: HTMLAttributes['class'];
    }>(),
    { statusColor: 'emerald' },
);

const statusStyles: Record<string, string> = {
    emerald: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
    amber: 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    rose: 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
};

const statusDot: Record<string, string> = {
    emerald: 'bg-emerald-400',
    amber: 'bg-amber-400',
    rose: 'bg-rose-400',
};
</script>

<template>
    <div
        :class="
            cn(
                'relative overflow-hidden rounded-3xl border border-slate-800 bg-slate-950 p-6 text-slate-200 shadow-2xl transition-all duration-300 hover:border-slate-700/60',
                props.class,
            )
        "
    >
        <!-- Mac dots -->
        <div
            class="absolute top-4 left-5 flex items-center gap-1.5 select-none"
        >
            <span
                class="size-3 rounded-full bg-rose-500/90 shadow-[0_0_8px_#f43f5e]"
            />
            <span
                class="size-3 rounded-full bg-amber-500/90 shadow-[0_0_8px_#f59e0b]"
            />
            <span
                class="size-3 rounded-full bg-emerald-500/90 shadow-[0_0_8px_#10b981]"
            />
            <span
                v-if="title"
                class="ml-2 font-mono text-[9px] font-extrabold tracking-wider text-slate-500 uppercase"
            >
                {{ title }}
            </span>
        </div>

        <!-- Status tag -->
        <div
            v-if="statusText"
            :class="[
                'absolute top-3.5 right-5 flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[9px] font-bold',
                statusStyles[statusColor],
            ]"
        >
            <span
                :class="[
                    'size-1.5 animate-pulse rounded-full',
                    statusDot[statusColor],
                ]"
            />
            {{ statusText }}
        </div>

        <!-- Content -->
        <div class="mt-8 font-mono text-sm">
            <slot />
        </div>
    </div>
</template>
