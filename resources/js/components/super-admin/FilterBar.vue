<script setup lang="ts">
import { Filter } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <Card
        :class="
            cn(
                'overflow-hidden transition-all duration-300 hover:border-primary/20',
                props.class,
            )
        "
    >
        <CardContent class="relative p-4">
            <div class="flex flex-wrap items-end gap-3">
                <div
                    class="flex shrink-0 items-center gap-1.5 text-muted-foreground"
                >
                    <Filter class="size-4 text-primary/60" />
                </div>
                <slot />
            </div>
            <div
                v-if="$slots.actions"
                class="mt-3 flex items-center gap-2 border-t border-border/40 pt-3"
            >
                <slot name="actions" />
            </div>
        </CardContent>
    </Card>
</template>
