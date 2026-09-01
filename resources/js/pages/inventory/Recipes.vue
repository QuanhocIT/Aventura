<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InventoryIndex from './Index.vue';

defineOptions({
    layout: AppLayout,
    inheritAttrs: false,
});

const page = usePage();
</script>

<template>
    <InventoryIndex v-bind="page.props" view="recipes" />
</template>
