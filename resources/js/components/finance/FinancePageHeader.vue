<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon: Component;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <div
                class="mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15"
            >
                <component :is="icon" class="size-5" aria-hidden="true" />
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    {{ title }}
                </h1>
                <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <slot name="actions" />
        </div>
    </div>
</template>
