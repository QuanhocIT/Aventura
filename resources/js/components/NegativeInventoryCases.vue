<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

import { Card } from '@/components/ui/card';

type NegativeCase = {
    id: number;
    branch_name?: string | null;
    ingredient_name?: string | null;
    unit_symbol?: string | null;
    status?: string;
    negative_quantity: number;
    on_hand: number;
    estimated_value: number;
    detected_at?: string | null;
};

const props = defineProps<{
    cases?: NegativeCase[];
    title?: string;
    limit?: number;
}>();

const cases = computed(() => props.cases ?? []);
</script>

<template>
    <Card
        v-if="cases.length > 0"
        class="border-amber-300/80 bg-amber-50/40 p-4 shadow-xs dark:border-amber-700/60 dark:bg-amber-950/20"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <AlertTriangle class="size-4.5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-amber-900 dark:text-amber-200">
                            {{ title || 'Âm nguyên liệu tại chi nhánh' }}
                        </span>
                        <span class="inline-flex items-center rounded-full bg-amber-200 px-2.5 py-0.5 text-xs font-black text-amber-900 dark:bg-amber-900 dark:text-amber-200">
                            {{ cases.length }}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-amber-800/90 dark:text-amber-300/80">
                        Phát hiện {{ cases.length }} nguyên liệu có số lượng tồn thực tế bị âm. Vui lòng kiểm tra và nhập bù kho để cân bằng số liệu.
                    </p>
                </div>
            </div>
            <Link
                href="/inventory/negative-stock"
                class="inline-flex shrink-0 items-center text-xs font-bold text-indigo-600 transition-colors hover:text-indigo-800 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                Mở trung tâm xử lý →
            </Link>
        </div>
    </Card>
</template>

