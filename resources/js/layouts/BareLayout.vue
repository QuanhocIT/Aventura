<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div :key="String(page.component)" class="page-enter flex flex-1 flex-col">
        <slot />
    </div>
    <Toaster />
    <ConfirmDialog />
</template>
