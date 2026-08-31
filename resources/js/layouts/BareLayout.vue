<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import FlashToast from '@/components/FlashToast.vue';
import { Toaster } from '@/components/ui/sonner';

const page = usePage();
</script>

<template>
    <div :key="String(page.component)" class="page-enter flex flex-1 flex-col">
        <slot />
    </div>
    <Toaster />
    <FlashToast />
    <ConfirmDialog />
</template>
