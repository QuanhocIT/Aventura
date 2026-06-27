<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
// Chuyển hướng sang Dashboard mới có đầy đủ tính năng
onMounted(() => router.visit('/super-admin/dashboard', { replace: true }));
</script>
<template><div /></template>
