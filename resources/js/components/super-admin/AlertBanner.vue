<script setup lang="ts">
import { Siren, AlertTriangle, Info, CheckCircle2, X } from 'lucide-vue-next';
import type { Component, HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        severity: 'critical' | 'warning' | 'info' | 'success';
        title: string;
        message?: string;
        icon?: Component;
        dismissible?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { dismissible: false },
);

defineEmits<{ dismiss: [] }>();

const styles: Record<string, { card: string; icon: string; badge: string }> = {
    critical: {
        card: 'border-rose-500/30 bg-rose-500/[0.06] dark:bg-rose-500/[0.04]',
        icon: 'text-rose-500',
        badge: 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20',
    },
    warning: {
        card: 'border-amber-500/30 bg-amber-500/[0.06] dark:bg-amber-500/[0.04]',
        icon: 'text-amber-500',
        badge: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20',
    },
    info: {
        card: 'border-sky-500/30 bg-sky-500/[0.06] dark:bg-sky-500/[0.04]',
        icon: 'text-sky-500',
        badge: 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20',
    },
    success: {
        card: 'border-emerald-500/30 bg-emerald-500/[0.06] dark:bg-emerald-500/[0.04]',
        icon: 'text-emerald-500',
        badge: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20',
    },
};

const defaultIcons: Record<string, Component> = {
    critical: Siren,
    warning: AlertTriangle,
    info: Info,
    success: CheckCircle2,
};

const resolvedIcon = computed(() => props.icon ?? defaultIcons[props.severity]);
</script>

<template>
    <div
        :class="
            cn(
                'animate-enter flex items-start gap-3 rounded-2xl border px-4 py-3 transition-all duration-300',
                styles[severity].card,
                props.class,
            )
        "
    >
        <component
            :is="resolvedIcon"
            :class="[
                'mt-0.5 size-4.5 shrink-0 animate-pulse',
                styles[severity].icon,
            ]"
        />
        <div class="min-w-0 flex-1 space-y-0.5">
            <div class="flex flex-wrap items-center gap-2">
                <p
                    class="text-xs font-black text-slate-800 dark:text-slate-100"
                >
                    {{ title }}
                </p>
                <span
                    :class="[
                        'rounded-full border px-1.5 py-0.5 text-[9px] font-extrabold tracking-wider uppercase',
                        styles[severity].badge,
                    ]"
                >
                    <slot name="badge">{{ severity }}</slot>
                </span>
            </div>
            <p
                v-if="message"
                class="text-[11px] leading-relaxed font-medium text-muted-foreground"
            >
                {{ message }}
            </p>
            <slot />
        </div>
        <button
            v-if="dismissible"
            class="shrink-0 text-muted-foreground transition-colors hover:text-foreground"
            @click="$emit('dismiss')"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
