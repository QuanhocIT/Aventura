<script setup lang="ts">
import { BarChart3 } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import DashboardEmptyState from '@/components/dashboard/DashboardEmptyState.vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        empty?: boolean;
        emptyTitle?: string;
        emptyDescription?: string;
        class?: HTMLAttributes['class'];
    }>(),
    {
        icon: BarChart3 as any,
        empty: false,
        emptyTitle: 'Chưa có dữ liệu trong khoảng thời gian này',
        emptyDescription: 'Hãy thử chọn khoảng thời gian khác để xem biểu đồ.',
    },
);
</script>

<template>
    <section
        :class="cn('dashboard-card-frame dashboard-chart-card', props.class)"
    >
        <header class="dashboard-card-header">
            <div class="flex min-w-0 items-start gap-3">
                <span
                    class="dashboard-section-icon dashboard-section-icon--indigo"
                >
                    <component :is="icon" class="size-4" />
                </span>
                <div class="min-w-0">
                    <h2 class="dashboard-card-title">{{ title }}</h2>
                    <p v-if="description" class="dashboard-card-description">
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot name="actions" />
        </header>
        <div class="dashboard-card-body dashboard-chart-card__body">
            <DashboardEmptyState
                v-if="empty"
                :title="emptyTitle"
                :description="emptyDescription"
            />
            <slot v-else />
        </div>
        <footer v-if="$slots.footer" class="dashboard-card-footer">
            <slot name="footer" />
        </footer>
    </section>
</template>
