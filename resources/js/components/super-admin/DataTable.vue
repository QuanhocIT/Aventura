<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import EmptyState from './EmptyState.vue';

export interface Column {
    key: string;
    label: string;
    align?: 'left' | 'center' | 'right';
    width?: string;
    class?: string;
}

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: any[];
        loading?: boolean;
        emptyIcon?: Component;
        emptyTitle?: string;
        emptyDescription?: string;
        hoverable?: boolean;
        striped?: boolean;
        animated?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        hoverable: true,
        striped: true,
        animated: true,
        emptyTitle: 'Không có dữ liệu',
    },
);

function getCellValue(row: any, key: string): any {
    return key.split('.').reduce((o, k) => o?.[k], row);
}
</script>

<template>
    <Card :class="cn('overflow-hidden', props.class)">
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-border/60 bg-muted/20 backdrop-blur-sm">
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                :class="
                                    cn(
                                        'px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-muted-foreground',
                                        col.align === 'center' && 'text-center',
                                        col.align === 'right' && 'text-right',
                                        col.width,
                                        col.class,
                                    )
                                "
                            >
                                {{ col.label }}
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="loading">
                        <tr v-for="i in 5" :key="i" class="border-b border-border/30 last:border-0">
                            <td v-for="col in columns" :key="col.key" class="px-5 py-4">
                                <Skeleton class="h-4 w-full max-w-[120px]" />
                            </td>
                        </tr>
                    </tbody>

                    <tbody v-else-if="rows.length > 0">
                        <template v-for="(row, idx) in rows" :key="row.id ?? idx">
                            <tr
                                :class="
                                    cn(
                                        'border-b border-border/30 last:border-0 transition-all duration-200',
                                        hoverable && 'hover:bg-muted/20 hover:shadow-[0_2px_12px_rgba(0,0,0,0.04)] dark:hover:shadow-[0_2px_12px_rgba(0,0,0,0.15)] hover:-translate-y-px',
                                        striped && idx % 2 !== 0 && 'bg-muted/[0.06]',
                                    )
                                "
                            >
                                <td
                                    v-for="col in columns"
                                    :key="col.key"
                                    :class="
                                        cn(
                                            'px-5 py-4',
                                            col.align === 'center' && 'text-center',
                                            col.align === 'right' && 'text-right',
                                            col.width,
                                        )
                                    "
                                >
                                    <slot :name="`cell-${col.key}`" :row="row" :value="getCellValue(row, col.key)">
                                        {{ getCellValue(row, col.key) }}
                                    </slot>
                                </td>
                            </tr>
                            <tr v-if="$slots['row-expand']">
                                <td :colspan="columns.length" class="p-0">
                                    <slot name="row-expand" :row="row" />
                                </td>
                            </tr>
                        </template>
                    </tbody>

                    <tbody v-else>
                        <tr>
                            <td :colspan="columns.length">
                                <slot name="empty">
                                    <EmptyState
                                        :icon="emptyIcon"
                                        :title="emptyTitle"
                                        :description="emptyDescription"
                                    />
                                </slot>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <slot name="pagination" />
        </CardContent>
    </Card>
</template>
