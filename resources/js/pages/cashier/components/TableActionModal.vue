<script setup lang="ts">
import { ArrowRightLeft, GitMerge, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { TableItem } from '../types';

const props = defineProps<{
    open: boolean;
    mode: 'move' | 'merge';
    activeTable: TableItem | null;
    availableTables: TableItem[];
    mergeCandidates: TableItem[];
    selectedTableId: number | null;
    selectedTargetOrderId: number | null;
    isSubmitting: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'update:selectedTableId', value: number | null): void;
    (e: 'update:selectedTargetOrderId', value: number | null): void;
    (e: 'submit'): void;
}>();

const numberFormat = (value: number) =>
    new Intl.NumberFormat('vi-VN').format(value);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        @click.self="emit('update:open', false)"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
        >
            <header
                class="flex items-start justify-between gap-4 border-b border-slate-100 p-5 dark:border-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-2xl"
                        :class="
                            mode === 'move'
                                ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300'
                                : 'bg-violet-100 text-violet-600 dark:bg-violet-950/50 dark:text-violet-300'
                        "
                    >
                        <ArrowRightLeft v-if="mode === 'move'" class="size-5" />
                        <GitMerge v-else class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white">
                            {{ mode === 'move' ? 'Chuyển bàn' : 'Gộp bàn' }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Bàn {{ activeTable?.name || 'hiện tại' }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    @click="emit('update:open', false)"
                >
                    <X class="size-5" />
                </button>
            </header>

            <div class="space-y-4 p-5">
                <template v-if="mode === 'move'">
                    <p class="text-xs leading-5 text-slate-500">
                        Chọn một bàn đang trống để chuyển toàn bộ đơn hiện tại sang.
                    </p>
                    <div
                        v-if="availableTables.length"
                        class="grid max-h-64 grid-cols-2 gap-2 overflow-y-auto"
                    >
                        <button
                            v-for="table in availableTables"
                            :key="table.id"
                            type="button"
                            class="rounded-2xl border p-3 text-left transition-all hover:border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/30"
                            :class="
                                selectedTableId === table.id
                                    ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/20 dark:bg-indigo-950/40'
                                    : 'border-slate-200 dark:border-slate-700'
                            "
                            @click="emit('update:selectedTableId', table.id)"
                        >
                            <span class="block text-sm font-black text-slate-800 dark:text-slate-100">
                                Bàn {{ table.name }}
                            </span>
                            <span class="mt-1 block text-[11px] text-slate-500">
                                {{ table.area || 'Khu vực chung' }} · {{ table.capacity }} chỗ
                            </span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-xs text-slate-500 dark:border-slate-700"
                    >
                        Hiện không còn bàn trống trong chi nhánh.
                    </p>
                </template>

                <template v-else>
                    <p class="text-xs leading-5 text-slate-500">
                        Chọn bàn đích để chuyển toàn bộ món của bàn hiện tại vào cùng một đơn.
                        Đơn nguồn sẽ được đóng lại sau khi gộp.
                    </p>
                    <div
                        v-if="mergeCandidates.length"
                        class="max-h-64 space-y-2 overflow-y-auto"
                    >
                        <button
                            v-for="table in mergeCandidates"
                            :key="table.id"
                            type="button"
                            class="w-full rounded-2xl border p-3 text-left transition-all hover:border-violet-400 hover:bg-violet-50 dark:hover:bg-violet-950/30"
                            :class="
                                selectedTargetOrderId === table.active_order?.id
                                    ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-500/20 dark:bg-violet-950/40'
                                    : 'border-slate-200 dark:border-slate-700'
                            "
                            @click="
                                emit(
                                    'update:selectedTargetOrderId',
                                    table.active_order?.id ?? null,
                                )
                            "
                        >
                            <span class="block text-sm font-black text-slate-800 dark:text-slate-100">
                                Gộp vào Bàn {{ table.name }}
                            </span>
                            <span class="mt-1 block text-[11px] text-slate-500">
                                {{ table.active_order?.order_number }} ·
                                {{ table.active_order?.items.length || 0 }} món ·
                                {{ numberFormat(table.active_order?.total_amount || 0) }}đ
                            </span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="rounded-2xl border border-dashed border-slate-300 p-6 text-center text-xs text-slate-500 dark:border-slate-700"
                    >
                        Không có bàn nào có đơn chưa thanh toán để gộp.
                    </p>
                </template>
            </div>

            <footer
                class="flex justify-end gap-2 border-t border-slate-100 p-5 dark:border-slate-800"
            >
                <Button
                    variant="outline"
                    class="rounded-xl text-xs"
                    @click="emit('update:open', false)"
                >
                    Hủy
                </Button>
                <Button
                    class="rounded-xl text-xs text-white"
                    :class="
                        mode === 'move'
                            ? 'bg-indigo-600 hover:bg-indigo-700'
                            : 'bg-violet-600 hover:bg-violet-700'
                    "
                    :disabled="
                        isSubmitting ||
                        (mode === 'move'
                            ? !selectedTableId
                            : !selectedTargetOrderId)
                    "
                    @click="emit('submit')"
                >
                    {{
                        isSubmitting
                            ? 'Đang xử lý...'
                            : mode === 'move'
                              ? 'Xác nhận chuyển bàn'
                              : 'Xác nhận gộp bàn'
                    }}
                </Button>
            </footer>
        </div>
    </div>
</template>
