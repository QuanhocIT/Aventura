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
                title: 'Giao diện',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Giao diện" />

    <h1 class="sr-only">Giao diện</h1>

    <div class="space-y-6">
        <Card
            class="w-full overflow-hidden rounded-2xl border border-neutral-200/60 bg-white/70 shadow-xs backdrop-blur-md dark:border-neutral-800/60 dark:bg-neutral-900/40"
        >
            <CardHeader
                class="flex flex-row items-center gap-4 border-b border-neutral-100 px-6 pt-6 pb-5 dark:border-neutral-800"
            >
                <div
                    class="shrink-0 rounded-xl bg-neutral-100 p-2.5 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-200"
                >
                    <Palette class="h-5 w-5" />
                </div>
                <div class="space-y-0.5">
                    <CardTitle
                        class="text-lg font-black text-neutral-900 dark:text-neutral-50"
                        >Thiết lập giao diện</CardTitle
                    >
                    <CardDescription
                        class="text-xs text-neutral-500 dark:text-neutral-400"
                        >Tùy chỉnh chế độ hiển thị sáng, tối hoặc theo hệ thống
                        của bạn</CardDescription
                    >
                </div>
            </CardHeader>
            <CardContent class="p-6 lg:p-8">
                <AppearanceTabs />
            </CardContent>
        </Card>
    </div>
</template>
