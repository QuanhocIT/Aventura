<script setup lang="ts">
import { MapPin } from 'lucide-vue-next';
import { computed } from 'vue';
import { useBranchContext } from '@/composables/useBranchContext';

const props = withDefaults(
    defineProps<{
        compact?: boolean;
        selectable?: boolean;
    }>(),
    {
        compact: false,
        selectable: true,
    },
);

const {
    branches,
    activeBranchId,
    currentBranchName,
    isAllBranches,
    isOwner,
    switchBranch,
} = useBranchContext();

const canSelect = computed(
    () => props.selectable && isOwner.value && branches.value.length > 0,
);

const handleChange = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;

    switchBranch(value === 'all' ? null : Number(value));
};
</script>

<template>
    <div
        v-if="branches.length"
        class="flex min-w-0 items-center gap-1.5"
        :class="compact ? 'text-xs' : 'text-[11px]'"
    >
        <MapPin class="size-3.5 shrink-0 text-indigo-500" />
        <span v-if="!compact" class="hidden font-medium tracking-wider text-muted-foreground uppercase sm:inline">
            Chi nhánh hiện tại:
        </span>

        <select
            v-if="canSelect"
            :value="isAllBranches ? 'all' : String(activeBranchId)"
            @change="handleChange"
            class="h-8 min-w-0 max-w-[220px] cursor-pointer rounded-lg border border-border bg-background px-2.5 py-1 font-semibold text-slate-800 shadow-sm transition-colors hover:bg-accent focus:ring-1 focus:ring-ring focus:outline-none dark:text-slate-200"
            :aria-label="compact ? 'Chi nhánh hiện tại' : undefined"
        >
            <option value="all">Toàn chuỗi</option>
            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                {{ branch.name }}
            </option>
        </select>

        <span
            v-else
            class="truncate font-bold text-slate-700 dark:text-slate-200"
            :title="currentBranchName"
        >
            {{ currentBranchName }}
        </span>
    </div>
</template>
