<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        description?: string;
        icon?: Component;
        tone?: 'indigo' | 'emerald' | 'amber' | 'rose' | 'sky' | 'violet';
        trend?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { tone: 'indigo' },
);

const toneClasses: Record<string, { icon: string; value: string }> = {
    indigo: { icon: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-300', value: 'text-indigo-700 dark:text-indigo-300' },
    emerald: { icon: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300', value: 'text-emerald-700 dark:text-emerald-300' },
    amber: { icon: 'bg-amber-500/10 text-amber-600 dark:text-amber-300', value: 'text-amber-700 dark:text-amber-300' },
    rose: { icon: 'bg-rose-500/10 text-rose-600 dark:text-rose-300', value: 'text-rose-700 dark:text-rose-300' },
    sky: { icon: 'bg-sky-500/10 text-sky-600 dark:text-sky-300', value: 'text-sky-700 dark:text-sky-300' },
    violet: { icon: 'bg-violet-500/10 text-violet-600 dark:text-violet-300', value: 'text-violet-700 dark:text-violet-300' },
};
</script>

<template>
    <article :class="cn('dashboard-kpi-card', props.class)">
        <div class="min-w-0">
            <p class="dashboard-card-label">{{ label }}</p>
            <p :class="['dashboard-kpi-value', toneClasses[tone].value]">
                {{ value }}
            </p>
            <p v-if="description" class="dashboard-card-meta">{{ description }}</p>
            <p v-if="trend" class="dashboard-kpi-trend">{{ trend }}</p>
        </div>
        <span v-if="icon" :class="['dashboard-kpi-icon', toneClasses[tone].icon]">
            <component :is="icon" class="size-5" />
        </span>
    </article>
</template>
