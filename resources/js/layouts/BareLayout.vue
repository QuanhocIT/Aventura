<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <slot />
    <Toaster />
    <ConfirmDialog />
</template>
