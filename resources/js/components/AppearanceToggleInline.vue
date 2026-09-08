<script setup lang="ts">
import { Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50/80 px-2 py-1 text-slate-600 shadow-xs transition-colors hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800"
        :title="resolvedAppearance === 'dark' ? 'Chế độ sáng' : 'Chế độ tối'"
    >
        <Sun class="size-4 text-slate-500 dark:text-slate-400" />
        <span
            class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer rounded-full border border-slate-300 bg-slate-200 p-0.5 transition-colors duration-200 dark:border-slate-700 dark:bg-slate-800"
        >
            <span
                class="pointer-events-none inline-block size-2.5 rounded-full bg-white shadow-xs transition-transform duration-200"
                :class="resolvedAppearance === 'dark' ? 'translate-x-3 bg-blue-500' : 'translate-x-0 bg-white'"
            />
        </span>
    </button>
</template>

