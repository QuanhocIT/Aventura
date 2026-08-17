<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { ButtonVariants } from '@/components/ui/button';

interface Props {
    fallbackHref?: string;
    label?: string;
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    showLabel?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    fallbackHref: '',
    label: 'Quay lại',
    variant: 'outline',
    size: 'sm',
    showLabel: true,
    class: '',
});

function handleBack() {
    const hasHistory =
        typeof window !== 'undefined' &&
        window.history.length > 1 &&
        document.referrer &&
        document.referrer.includes(window.location.origin) &&
        document.referrer !== window.location.href;

    if (hasHistory) {
        window.history.back();

        return;
    }

    if (props.fallbackHref) {
        router.visit(props.fallbackHref);

        return;
    }

    // Default fallback: calculate parent route or go to /dashboard
    if (typeof window !== 'undefined') {
        const pathSegments = window.location.pathname
            .split('/')
            .filter(Boolean);
        if (pathSegments.length > 1) {
            pathSegments.pop();
            router.visit('/' + pathSegments.join('/'));

            return;
        }
    }

    router.visit('/dashboard');
}
</script>

<template>
    <Button
        type="button"
        :variant="variant"
        :size="size"
        :class="['inline-flex items-center gap-1.5 transition-all', props.class]"
        @click="handleBack"
        :title="label"
    >
        <ArrowLeft class="size-4 shrink-0" />
        <span v-if="showLabel">{{ label }}</span>
    </Button>
</template>
