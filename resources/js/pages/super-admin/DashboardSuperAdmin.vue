<script setup lang="ts">
// ...existing code...
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard Super Admin" />
    <div class="flex flex-col items-center justify-center h-full">
        <h1 class="text-3xl font-bold mb-4">Dashboard Super Admin</h1>
        <p>Chào mừng bạn đến với trang quản trị cao cấp!</p>
    </div>
</template>
// ...existing code...
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
// Chuyển hướng sang Dashboard mới có đầy đủ tính năng
onMounted(() => router.visit('/super-admin/dashboard', { replace: true }));
</script>
<template><div /></template>
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
// Chuyển hướng sang Dashboard mới có đầy đủ tính năng
onMounted(() => router.visit('/super-admin/dashboard', { replace: true }));
</script>
<template><div /></template>
// ...existing code...
