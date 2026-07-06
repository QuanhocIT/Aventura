<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        status: string;
        variant?: 'pill' | 'dot';
        size?: 'sm' | 'md';
        class?: HTMLAttributes['class'];
    }>(),
    { variant: 'pill', size: 'md' },
);

const colorMap: Record<string, string> = {
    active: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    processed: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    resolved: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    published: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    approved: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    paid: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    healthy: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    up: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    created: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    sent: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    generated: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    open: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    info: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/40',
    suspended: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    pending: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    warning: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    in_progress: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    sending: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    medium: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    expired: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    failed: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    rejected: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    orphaned: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    breached: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    critical: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    high: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    down: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    deleted: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
    draft: 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700/40',
    closed: 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700/40',
    inactive: 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700/40',
    cancelled: 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700/40',
    updated: 'bg-indigo-100/80 text-indigo-800 border-indigo-200/50 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800/40',
    low: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    maintenance: 'bg-violet-100/80 text-violet-800 border-violet-200/50 dark:bg-violet-950/40 dark:text-violet-300 dark:border-violet-800/40',
};

const dotColorMap: Record<string, string> = {
    active: 'bg-emerald-500', processed: 'bg-emerald-500', resolved: 'bg-emerald-500', published: 'bg-emerald-500', approved: 'bg-emerald-500', paid: 'bg-emerald-500', healthy: 'bg-emerald-500', up: 'bg-emerald-500', low: 'bg-emerald-500',
    created: 'bg-sky-500', sent: 'bg-sky-500', generated: 'bg-sky-500', open: 'bg-sky-500', info: 'bg-sky-500',
    suspended: 'bg-amber-500', pending: 'bg-amber-500', warning: 'bg-amber-500', in_progress: 'bg-amber-500', sending: 'bg-amber-500', medium: 'bg-amber-500',
    expired: 'bg-rose-500', failed: 'bg-rose-500', rejected: 'bg-rose-500', orphaned: 'bg-rose-500', breached: 'bg-rose-500', critical: 'bg-rose-500', high: 'bg-rose-500', down: 'bg-rose-500', deleted: 'bg-rose-500',
    draft: 'bg-slate-400', closed: 'bg-slate-400', inactive: 'bg-slate-400', cancelled: 'bg-slate-400',
    updated: 'bg-indigo-500',
    maintenance: 'bg-violet-500',
};

const fallback = 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300 dark:border-slate-700/40';
const fallbackDot = 'bg-slate-400';

const badgeClass = computed(() =>
    colorMap[props.status] ?? fallback,
);

const dotClass = computed(() =>
    dotColorMap[props.status] ?? fallbackDot,
);
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full border font-medium whitespace-nowrap transition-all duration-200 hover:scale-105',
                size === 'sm' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-xs',
                variant === 'pill' ? badgeClass : 'bg-transparent border-transparent text-foreground',
                props.class,
            )
        "
    >
        <span
            v-if="variant === 'dot'"
            :class="cn(
                'size-2 rounded-full shrink-0',
                dotClass,
                ['active', 'up', 'healthy', 'online', 'processed'].includes(status) && 'animate-pulse',
            )"
        />
        <slot>{{ status }}</slot>
    </span>
</template>
