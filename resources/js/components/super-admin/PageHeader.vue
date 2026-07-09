<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    title: string;
    subtitle?: string;
    icon?: Component;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('space-y-4', props.class)">
        <div
            class="group flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <slot name="breadcrumb" />
                <h1
                    class="flex items-center gap-2.5 text-2xl font-bold tracking-tight"
                >
                    <div
                        v-if="icon"
                        class="flex size-8 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 transition-all duration-300 group-hover:scale-105 group-hover:shadow-md group-hover:shadow-primary/10"
                    >
                        <component
                            :is="icon"
                            class="size-5 shrink-0 text-primary/80"
                        />
                    </div>
                    <span
                        class="bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent dark:from-white dark:to-slate-300"
                    >
                        {{ title }}
                    </span>
                </h1>
                <p v-if="subtitle" class="text-sm text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <div
            class="h-px w-full bg-gradient-to-r from-transparent via-primary/20 to-transparent"
        />
    </div>
</template>
