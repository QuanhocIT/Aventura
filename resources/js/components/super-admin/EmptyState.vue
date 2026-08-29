<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        icon?: Component;
        title: string;
        description?: string;
        class?: HTMLAttributes['class'];
    }>(),
    { icon: Inbox as any },
);
</script>

<template>
    <div
        :class="
            cn(
                'dashboard-empty-state flex flex-col items-center justify-center py-12',
                props.class,
            )
        "
    >
        <div class="relative">
            <div
                class="absolute inset-0 rounded-2xl bg-gradient-to-br from-primary/5 to-violet-500/5 blur-xl"
            />
            <div class="float-glow relative rounded-2xl bg-muted/20 p-4">
                <component
                    :is="icon"
                    class="size-10 text-muted-foreground/60"
                />
            </div>
        </div>
        <p class="mt-3 text-sm font-semibold text-muted-foreground">
            {{ title }}
        </p>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-center text-xs text-muted-foreground/70"
        >
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
