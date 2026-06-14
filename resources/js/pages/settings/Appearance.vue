<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Palette } from 'lucide-vue-next';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-6">
        <Card class="w-full border border-neutral-200/60 dark:border-neutral-800/60 shadow-xs rounded-2xl overflow-hidden bg-white/70 dark:bg-neutral-900/40 backdrop-blur-md">
            <CardHeader class="flex flex-row items-center gap-4 border-b border-neutral-100 dark:border-neutral-800 pb-5 px-6 pt-6">
                <div class="p-2.5 bg-neutral-100 dark:bg-neutral-800 text-neutral-800 dark:text-neutral-200 rounded-xl shrink-0">
                    <Palette class="w-5 h-5" />
                </div>
                <div class="space-y-0.5">
                    <CardTitle class="text-lg font-black text-neutral-900 dark:text-neutral-50">Thiết lập giao diện</CardTitle>
                    <CardDescription class="text-xs text-neutral-500 dark:text-neutral-400">Tùy chỉnh chế độ hiển thị sáng, tối hoặc theo hệ thống của bạn</CardDescription>
                </div>
            </CardHeader>
            <CardContent class="p-6">
                <AppearanceTabs />
            </CardContent>
        </Card>
    </div>
</template>
