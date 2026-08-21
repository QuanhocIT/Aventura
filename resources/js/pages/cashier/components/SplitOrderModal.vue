<script setup lang="ts">
import { X, Plus, Minus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import type { TableItem, OrderItem } from '../types';

const props = defineProps<{
    showSplitModal: boolean;
    activeTable: TableItem | null;
    tablesData: TableItem[];
    splitTableId: number | null;
    splitItems: OrderItem[];
    isSubmittingSplit: boolean;
    splitProjection: any;
}>();

const emit = defineEmits<{
    (e: 'update:showSplitModal', val: boolean): void;
    (e: 'update:splitTableId', val: number | null): void;
    (e: 'processSplit'): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <Teleport to="body">
    <div
        v-if="showSplitModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="animate-fade-in flex w-full max-w-lg flex-col gap-6 overflow-hidden rounded-3xl border bg-white p-6 shadow-2xl dark:bg-slate-900"
        >
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-2 text-base font-black text-slate-800 dark:text-slate-100"
                >
                    Tách đơn hàng (Bàn {{ activeTable?.name }})
                </h3>
                <Button
                    variant="ghost"
                    size="icon"
                    class="rounded-xl"
                    @click="emit('update:showSplitModal', false)"
                >
                    <X class="size-5" />
                </Button>
            </div>

            <div class="flex flex-col gap-4 text-left">
                <div>
                    <label
                        class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Chọn bàn trống để chuyển món sang:
                    </label>
                    <select
                        :value="splitTableId ?? ''"
                        @change="
                            emit(
                                'update:splitTableId',
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ) || null,
                            )
                        "
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <option value="">-- Chọn bàn trống --</option>
                        <option
                            v-for="t in tablesData.filter(
                                (tbl) =>
                                    tbl.status === 'available' &&
                                    tbl.id !== activeTable?.id,
                            )"
                            :key="t.id"
                            :value="t.id"
                        >
                            Bàn {{ t.name }} ({{ t.area || 'Chung' }} -
                            {{ t.capacity }} chỗ)
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-400"
                    >
                        Chọn món và số lượng cần tách:
                    </label>
                    <div
                        class="max-h-56 overflow-y-auto rounded-2xl border border-slate-200 p-2 dark:border-slate-800"
                    >
                        <div
                            v-for="(item, idx) in splitItems"
                            :key="idx"
                            class="flex items-center justify-between border-b border-slate-100 p-2 last:border-0 dark:border-slate-800"
                        >
                            <span
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{
                                    item.product_name ||
                                    `Món #${item.product_id}`
                                }}
                            </span>
                            <div class="flex items-center gap-2">
                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-6 rounded-lg"
                                    :disabled="item.quantity <= 0"
                                    @click="item.quantity--"
                                >
                                    <Minus class="size-3" />
                                </Button>
                                <span
                                    class="w-4 text-center font-mono text-xs font-bold"
                                    >{{ item.quantity }}</span
                                >
                                <Button
                                    size="icon"
                                    variant="outline"
                                    class="size-6 rounded-lg"
                                    @click="item.quantity++"
                                >
                                    <Plus class="size-3" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dự tính tiền 2 đơn sau tách -->
                <template v-if="splitProjection && splitProjection.hasItems">
                    <Separator />
                    <div class="grid grid-cols-2 gap-2 text-[10px]">
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50/50 p-2.5"
                        >
                            <div class="mb-1.5 font-bold text-slate-600">
                                Đơn gốc (còn lại)
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Tạm tính:</span
                                >
                                <span class="font-mono"
                                    >{{
                                        numberFormat(
                                            splitProjection.origSubtotal,
                                        )
                                    }}đ</span
                                >
                            </div>
                            <div
                                v-if="splitProjection.origDiscount > 0"
                                class="flex justify-between text-emerald-600"
                            >
                                <span>Giảm giá:</span>
                                <span class="font-mono"
                                    >-{{
                                        numberFormat(
                                            splitProjection.origDiscount,
                                        )
                                    }}đ</span
                                >
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t pt-1 font-black"
                            >
                                <span>Tổng:</span>
                                <span class="font-mono text-rose-600"
                                    >{{
                                        numberFormat(splitProjection.origTotal)
                                    }}đ</span
                                >
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-rose-200 bg-rose-50/30 p-2.5"
                        >
                            <div class="mb-1.5 font-bold text-rose-600">
                                Đơn tách mới
                            </div>
                            <div class="flex justify-between">
                                <span class="text-muted-foreground"
                                    >Tạm tính:</span
                                >
                                <span class="font-mono"
                                    >{{
                                        numberFormat(
                                            splitProjection.splitSubtotal,
                                        )
                                    }}đ</span
                                >
                            </div>
                            <div
                                v-if="splitProjection.splitDiscount > 0"
                                class="flex justify-between text-emerald-600"
                            >
                                <span>Giảm giá:</span>
                                <span class="font-mono"
                                    >-{{
                                        numberFormat(
                                            splitProjection.splitDiscount,
                                        )
                                    }}đ</span
                                >
                            </div>
                            <div
                                class="mt-1 flex justify-between border-t pt-1 font-black"
                            >
                                <span>Tổng:</span>
                                <span class="font-mono text-rose-600"
                                    >{{
                                        numberFormat(
                                            splitProjection.splitTotal,
                                        )
                                    }}đ</span
                                >
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    class="flex-1 rounded-xl text-xs"
                    @click="emit('update:showSplitModal', false)"
                >
                    Hủy
                </Button>
                <Button
                    class="flex-1 rounded-xl bg-rose-600 text-xs hover:bg-rose-700"
                    :disabled="
                        !splitTableId ||
                        isSubmittingSplit ||
                        !splitProjection?.hasItems
                    "
                    @click="emit('processSplit')"
                >
                    {{
                        isSubmittingSplit
                            ? 'Đang xử lý...'
                            : 'Xác nhận Tách đơn'
                    }}
                </Button>
            </div>
        </div>
    </div>
    </Teleport>
</template>
