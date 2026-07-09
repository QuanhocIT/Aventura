<script setup lang="ts">
import { AlertTriangle, HelpCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { confirmState, resolveConfirm } from '@/composables/useConfirm';

function onOpenChange(open: boolean) {
    if (!open) {
        resolveConfirm(false);
    }
}
</script>

<template>
    <Dialog :open="confirmState.open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div class="flex items-start gap-3">
                    <div
                        class="shrink-0 rounded-xl p-2.5"
                        :class="
                            confirmState.variant === 'destructive'
                                ? 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400'
                                : 'bg-primary/10 text-primary'
                        "
                    >
                        <component
                            :is="
                                confirmState.variant === 'destructive'
                                    ? AlertTriangle
                                    : HelpCircle
                            "
                            class="h-5 w-5"
                        />
                    </div>
                    <div class="space-y-1.5 pt-0.5">
                        <DialogTitle class="text-base">{{
                            confirmState.title
                        }}</DialogTitle>
                        <DialogDescription
                            v-if="confirmState.description"
                            class="text-xs leading-relaxed whitespace-pre-line"
                        >
                            {{ confirmState.description }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="resolveConfirm(false)"
                >
                    {{ confirmState.cancelText }}
                </Button>
                <Button
                    :variant="
                        confirmState.variant === 'destructive'
                            ? 'destructive'
                            : 'default'
                    "
                    size="sm"
                    class="text-xs"
                    autofocus
                    @click="resolveConfirm(true)"
                >
                    {{ confirmState.confirmText }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
