<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card/90 dark:bg-card/80 text-card-foreground flex flex-col gap-6 rounded-2xl border border-white/[0.12] dark:border-white/[0.08] py-6 shadow-[var(--shadow-premium)] backdrop-blur-sm transition-all duration-300 hover:shadow-[var(--shadow-elevated)] hover:border-white/20 hover:-translate-y-0.5',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
