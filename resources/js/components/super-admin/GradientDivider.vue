<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        :class="
            cn(
                'h-px w-full bg-gradient-to-r from-transparent via-primary/20 to-transparent',
                props.class,
            )
        "
    />
</template>
