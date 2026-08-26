<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        accentColor?: 'violet' | 'sky' | 'emerald' | 'rose' | 'amber' | 'cyan';
        class?: HTMLAttributes['class'];
    }>(),
    { accentColor: 'violet' },
);

const accentStyles: Record<string, string> = {
    violet: 'border-violet-500/20 bg-gradient-to-br from-violet-500/[0.04] via-indigo-500/[0.03] to-sky-500/[0.04]',
    sky: 'border-sky-500/20 bg-gradient-to-br from-sky-500/[0.04] via-cyan-500/[0.03] to-indigo-500/[0.04]',
    emerald:
        'border-emerald-500/20 bg-gradient-to-br from-emerald-500/[0.04] via-teal-500/[0.03] to-cyan-500/[0.04]',
    rose: 'border-rose-500/20 bg-gradient-to-br from-rose-500/[0.04] via-pink-500/[0.03] to-amber-500/[0.04]',
    amber: 'border-amber-500/20 bg-gradient-to-br from-amber-500/[0.04] via-orange-500/[0.03] to-rose-500/[0.04]',
    cyan: 'border-cyan-500/20 bg-gradient-to-br from-cyan-500/[0.04] via-sky-500/[0.03] to-blue-500/[0.04]',
};
</script>

<template>
    <div
        :class="
            cn(
                'dashboard-card-frame space-y-4 rounded-3xl border p-6 shadow-xs backdrop-blur-xl transition-all duration-300',
                accentStyles[accentColor],
                props.class,
            )
        "
    >
        <slot />
    </div>
</template>
