<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    links: Array<{ url: string | null; label: string; active: boolean }>;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div
        v-if="links.length > 3"
        :class="
            cn(
                'flex flex-wrap justify-center gap-1 border-t border-border/60 px-4 py-3',
                props.class,
            )
        "
    >
        <Link
            v-for="link in links"
            :key="link.label"
            :href="link.url ?? '#'"
            preserve-state
            :class="
                cn(
                    'inline-flex items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-200 hover:scale-105 active:scale-95',
                    link.active
                        ? 'scale-105 border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/20'
                        : 'border-border/60 text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                    !link.url && 'pointer-events-none opacity-40',
                )
            "
        >
            <span v-html="link.label" />
        </Link>
    </div>
</template>
