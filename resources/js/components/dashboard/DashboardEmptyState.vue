<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        icon?: Component;
        class?: HTMLAttributes['class'];
    }>(),
    {
        title: 'Chưa có dữ liệu trong khoảng thời gian này',
        description: 'Hãy thử chọn khoảng thời gian hoặc bộ lọc khác.',
        icon: Inbox as any,
    },
);
</script>

<template>
    <div :class="cn('dashboard-empty-state', props.class)">
        <span class="dashboard-empty-state__icon">
            <component :is="icon" class="size-6" />
        </span>
        <p class="dashboard-empty-state__title">{{ title }}</p>
        <p v-if="description" class="dashboard-empty-state__description">{{ description }}</p>
        <div v-if="$slots.action" class="mt-4">
            <slot name="action" />
        </div>
    </div>
</template>
