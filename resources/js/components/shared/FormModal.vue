<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Standardizes the "modal overlay + Card + form" pattern already used across ~30 pages
 * (Create/Edit dialogs for products, employees, expenses, etc.), which previously
 * re-implemented the same overlay markup, submit-button disabled/spinner state, and
 * error display by hand in every page. New forms should use this instead of copying an
 * existing page's modal markup; existing pages can migrate opportunistically.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        processing?: boolean;
        submitLabel?: string;
        processingLabel?: string;
        cancelLabel?: string;
        /** Cross-field / server-side errors not tied to a specific rendered field. */
        formError?: string | null;
        maxWidthClass?: string;
    }>(),
    {
        description: undefined,
        processing: false,
        submitLabel: 'Lưu',
        processingLabel: 'Đang lưu...',
        cancelLabel: 'Hủy',
        formError: null,
        maxWidthClass: 'max-w-md',
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [];
    cancel: [];
}>();

function close() {
    emit('update:open', false);
    emit('cancel');
}

function onSubmit() {
    if (!props.processing) {
        emit('submit');
    }
}

const cardWidthClass = computed(() => props.maxWidthClass);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs"
        @keydown.esc="close"
    >
        <Card :class="['w-full animate-in fade-in zoom-in-95 duration-150 rounded-2xl border-border', cardWidthClass]">
            <CardHeader class="pb-3 border-b border-border/60">
                <CardTitle class="text-base font-extrabold">{{ title }}</CardTitle>
                <CardDescription v-if="description">{{ description }}</CardDescription>
            </CardHeader>
            <CardContent class="p-5">
                <form @submit.prevent="onSubmit" class="space-y-4">
                    <div
                        v-if="formError"
                        role="alert"
                        aria-live="polite"
                        class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-400"
                    >
                        {{ formError }}
                    </div>

                    <slot />

                    <div class="flex justify-end gap-2 pt-2 border-t border-border/60">
                        <slot name="footer-start" />
                        <Button type="button" variant="outline" class="rounded-xl" @click="close">
                            {{ cancelLabel }}
                        </Button>
                        <Button
                            type="submit"
                            class="rounded-xl font-bold cursor-pointer"
                            :disabled="processing"
                        >
                            <Loader2 v-if="processing" class="size-3.5 animate-spin mr-1.5" />
                            {{ processing ? processingLabel : submitLabel }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
