<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        eyebrow?: string;
        icon?: Component;
        showHeader?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { showHeader: true },
);
</script>

<template>
    <main :class="cn('dashboard-shell', props.class)">
        <header v-if="showHeader" class="dashboard-page-header">
            <div class="min-w-0">
                <p v-if="eyebrow" class="dashboard-eyebrow">{{ eyebrow }}</p>
                <h1 class="dashboard-page-title">
                    <span v-if="icon" class="dashboard-title-icon">
                        <component :is="icon" class="size-5" />
                    </span>
                    <span>{{ title }}</span>
                </h1>
                <p v-if="description" class="dashboard-page-description">
                    {{ description }}
                </p>
            </div>
            <div v-if="$slots.actions" class="dashboard-page-actions">
                <slot name="actions" />
            </div>
        </header>

        <div v-if="$slots.filters" class="dashboard-filterbar">
            <slot name="filters" />
        </div>

        <slot />
    </main>
</template>
