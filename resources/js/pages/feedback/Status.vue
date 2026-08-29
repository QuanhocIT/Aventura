<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    restaurant: { id: number; name: string };
    feedback: {
        rating: number;
        content: string | null;
        status: 'new' | 'reviewed' | 'resolved';
        resolution_notes: string | null;
        compensation_voucher: string | null;
        created_at: string | null;
        updated_at: string | null;
    };
}>();

const statusLabel = computed(
    () =>
        ({
            new: 'Đã tiếp nhận',
            reviewed: 'Đang được xem xét',
            resolved: 'Đã xử lý',
        })[props.feedback.status],
);
</script>

<template>
    <Head :title="`Phản hồi · ${restaurant.name}`" />

    <main
        class="flex min-h-screen items-center justify-center bg-slate-50 p-4 dark:bg-slate-950"
    >
        <section
            class="w-full max-w-lg rounded-2xl border bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex items-center gap-3">
                <MessageSquare class="size-8 text-primary" />
                <div>
                    <p class="text-sm font-semibold text-primary">
                        {{ restaurant.name }}
                    </p>
                    <h1 class="text-2xl font-bold">Theo dõi phản hồi</h1>
                </div>
            </div>

            <div
                class="mt-6 flex items-center gap-3 rounded-xl bg-muted/50 p-4"
            >
                <CheckCircle2
                    v-if="feedback.status === 'resolved'"
                    class="size-7 text-emerald-600"
                />
                <Clock3 v-else class="size-7 text-amber-600" />
                <div>
                    <p class="font-semibold">{{ statusLabel }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ feedback.rating }}/5 sao
                    </p>
                </div>
            </div>

            <blockquote
                v-if="feedback.content"
                class="mt-5 rounded-lg border-l-4 border-primary bg-muted/30 p-4 text-sm italic"
            >
                “{{ feedback.content }}”
            </blockquote>

            <div
                v-if="feedback.resolution_notes"
                class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"
            >
                <p class="font-semibold">Phản hồi từ nhà hàng</p>
                <p class="mt-1 whitespace-pre-line">
                    {{ feedback.resolution_notes }}
                </p>
            </div>

            <div
                v-if="feedback.compensation_voucher"
                class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
            >
                Mã voucher đền bù:
                <strong>{{ feedback.compensation_voucher }}</strong>
            </div>
        </section>
    </main>
</template>
