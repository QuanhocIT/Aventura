<script setup lang="ts">
import { Input } from '@/components/ui/input';
import Pagination from '@/components/super-admin/Pagination.vue';
import { Search } from 'lucide-vue-next';

/**
 * Standardizes the "search header + table + pagination + empty state" shell repeated
 * across list pages (products, employees, expenses, etc.). Callers own the actual
 * columns/rows via slots — this only unifies the surrounding chrome so every list page
 * doesn't hand-roll its own search box, empty state, and pagination wiring.
 */
withDefaults(
    defineProps<{
        searchQuery?: string;
        searchPlaceholder?: string;
        isEmpty?: boolean;
        emptyMessage?: string;
        paginationLinks?: Array<{ url: string | null; label: string; active: boolean }>;
    }>(),
    {
        searchQuery: '',
        searchPlaceholder: 'Tìm kiếm...',
        isEmpty: false,
        emptyMessage: 'Không có dữ liệu.',
        paginationLinks: () => [],
    },
);

defineEmits<{
    'update:searchQuery': [value: string];
}>();
</script>

<template>
    <div class="rounded-2xl border border-border bg-card overflow-hidden">
        <div v-if="$slots.filters || searchQuery !== undefined" class="flex flex-wrap items-center gap-2 p-4 border-b border-border/60">
            <div class="relative flex-1 min-w-[200px]">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                <Input
                    :model-value="searchQuery"
                    @update:model-value="$emit('update:searchQuery', String($event))"
                    :placeholder="searchPlaceholder"
                    class="pl-9 rounded-xl"
                />
            </div>
            <slot name="filters" />
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <slot name="head" />
                </thead>
                <tbody>
                    <slot />
                </tbody>
            </table>
        </div>

        <div v-if="isEmpty" class="px-5 py-16 text-center text-xs font-semibold text-muted-foreground">
            {{ emptyMessage }}
        </div>

        <Pagination v-if="paginationLinks.length" :links="paginationLinks" />
    </div>
</template>
