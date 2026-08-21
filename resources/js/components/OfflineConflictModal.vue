<script setup lang="ts">
import { AlertTriangle, Trash2, X } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { useOfflineQueue } from '@/composables/useOfflineQueue';
import type { ConflictResponse } from '@/composables/useOfflineQueue';

interface Props {
    conflict: ConflictResponse | null;
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits(['close', 'resolved']);

const { removeQueuedItem } = useOfflineQueue();

const handleDiscard = () => {
    if (props.conflict) {
        removeQueuedItem(props.conflict.item.id);
        toast.info('Đã hủy bỏ đơn hàng ngoại tuyến bị xung đột.');
        emit('resolved');
        emit('close');
    }
};
</script>

<template>
    <Teleport to="body">
    <div
        v-if="open && conflict"
        class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="relative w-full max-w-lg overflow-hidden rounded-3xl border border-rose-200 bg-card p-6 shadow-2xl dark:border-rose-900"
        >
            <button
                @click="emit('close')"
                class="absolute top-4 right-4 rounded-full p-1 text-muted-foreground hover:bg-muted"
            >
                <X class="size-5" />
            </button>

            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 dark:bg-rose-500/20"
                >
                    <AlertTriangle class="size-6 animate-pulse" />
                </div>
                <div>
                    <h2 class="text-lg font-extrabold text-foreground">
                        Xung Đột Tồn Kho (Đơn Offline)
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Một số món ăn không đủ số lượng trong kho tại thời điểm
                        đồng bộ lại.
                    </p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                <div
                    v-for="(c, idx) in conflict.conflicts"
                    :key="idx"
                    class="flex items-center justify-between rounded-xl border border-rose-200/60 bg-rose-50/40 p-3 text-xs dark:border-rose-900/40 dark:bg-rose-950/20"
                >
                    <div>
                        <p class="font-bold text-foreground">
                            {{ c.product_name }}
                        </p>
                        <p class="mt-0.5 text-muted-foreground">
                            Đã đặt:
                            <strong class="text-rose-600">{{
                                c.requested
                            }}</strong>
                            · Còn khả dụng:
                            <strong class="text-emerald-600">{{
                                c.available_stock
                            }}</strong>
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="mt-6 flex items-center justify-end gap-3 border-t border-border pt-4"
            >
                <button
                    @click="handleDiscard"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                >
                    <Trash2 class="size-4" />
                    Hủy đơn hàng này
                </button>
            </div>
        </div>
    </div>
    </Teleport>
</template>
