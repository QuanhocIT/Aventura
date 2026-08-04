<script setup lang="ts">
import {
    ArrowLeft,
    CheckCircle2 as CheckIcon,
    Minus,
    Plus,
    ShoppingCart,
    Trash2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { OrderItem, TableItem } from '../types';

const props = defineProps<{
    activeTable: TableItem | null;
    cartItems: OrderItem[];
    cartNote: string;
    totalCartAmount: number;
    totalCartQty: number;
    isNotified: boolean;
    isSubmitting: boolean;
    canProcessPayments: boolean;
    canSplitOrders: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:cartNote', val: string): void;
    (e: 'update:drawerStep', step: 'select' | 'confirm'): void;
    (e: 'increaseQty', item: OrderItem): void;
    (e: 'decreaseQty', item: OrderItem): void;
    (e: 'removeItem', item: OrderItem): void;
    (e: 'submitOrder'): void;
    (e: 'openPayment'): void;
    (e: 'sendToKitchen'): void;
    (e: 'openSplitOrder'): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <section
        class="flex min-h-[32rem] flex-col overflow-hidden rounded-3xl border border-indigo-200 bg-white shadow-xl shadow-indigo-950/10 dark:border-indigo-500/30 dark:bg-slate-900/60"
    >
        <header
            class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800"
        >
            <div class="flex items-center gap-3 text-left">
                <div
                    class="flex size-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-600/20 dark:text-indigo-300"
                >
                    <ShoppingCart class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2
                            class="text-lg font-black text-slate-900 dark:text-slate-100"
                        >
                            Đang xem món đã chọn
                        </h2>
                        <span
                            class="rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-black text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300"
                        >
                            {{ totalCartQty }} món
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Bàn {{ activeTable?.name || '' }} ·
                        {{ activeTable?.area || 'Chưa chọn bàn' }}
                    </p>
                </div>
            </div>

            <Button
                variant="outline"
                class="rounded-xl border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-700 dark:bg-transparent dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                @click="emit('update:drawerStep', 'select')"
            >
                <ArrowLeft class="mr-2 size-4" />
                Thêm món
            </Button>
        </header>

        <div class="flex-1 overflow-y-auto p-5 lg:p-6">
            <div
                v-if="cartItems.length === 0"
                class="flex min-h-64 flex-col items-center justify-center text-slate-400 dark:text-slate-500"
            >
                <ShoppingCart class="size-12 stroke-1" />
                <p class="mt-2 text-xs font-bold">Chưa có món nào trong đơn</p>
            </div>

            <div v-else class="space-y-5">
                <div class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="(item, idx) in cartItems"
                        :key="idx"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/45"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 text-left">
                                <p
                                    class="truncate text-sm font-black text-slate-900 dark:text-slate-100"
                                >
                                    {{ item.product_name }}
                                </p>
                                <p
                                    class="mt-1 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ numberFormat(item.price) }}đ
                                </p>
                            </div>

                            <div
                                class="flex shrink-0 items-center gap-1 rounded-xl bg-slate-200 p-1 dark:bg-slate-800"
                            >
                                <button
                                    type="button"
                                    class="flex size-7 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-300 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                                    @click="emit('decreaseQty', item)"
                                >
                                    <Minus class="size-3.5" />
                                </button>
                                <span
                                    class="w-6 text-center font-mono text-xs font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ item.quantity }}
                                </span>
                                <button
                                    type="button"
                                    class="flex size-7 items-center justify-center rounded-lg bg-indigo-600 text-white transition-colors hover:bg-indigo-500"
                                    @click="emit('increaseQty', item)"
                                >
                                    <Plus class="size-3.5" />
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <Input
                                type="text"
                                placeholder="Ghi chú món..."
                                :value="item.notes"
                                @input="
                                    item.notes = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                                class="h-9 rounded-xl border-slate-200 bg-white text-xs text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
                            />
                            <button
                                v-if="!item.id"
                                type="button"
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl text-rose-500 transition-colors hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                title="Xóa món"
                                @click="emit('removeItem', item)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </article>
                </div>

                <div class="text-left">
                    <label
                        class="mb-2 block text-[11px] font-bold text-slate-500 dark:text-slate-400"
                        >Ghi chú đơn hàng</label
                    >
                    <Input
                        type="text"
                        placeholder="Nhập ghi chú chung cho toàn bộ đơn..."
                        :value="cartNote"
                        @input="
                            emit(
                                'update:cartNote',
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                        class="h-10 rounded-xl border-slate-200 bg-white text-xs text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-100 dark:placeholder:text-slate-500"
                    />
                </div>
            </div>
        </div>

        <footer
            class="border-t border-slate-200 bg-slate-50/80 px-5 py-4 lg:px-6 dark:border-slate-800 dark:bg-slate-950/40"
        >
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-400">Tổng số món</span>
                <span class="font-bold text-slate-900 dark:text-slate-100"
                    >{{ totalCartQty }} món</span
                >
            </div>

            <div class="mt-2 flex items-center justify-between">
                <span
                    class="text-base font-black text-slate-900 dark:text-slate-100"
                    >Tổng cộng</span
                >
                <span
                    class="font-mono text-lg font-black text-indigo-600 dark:text-indigo-400"
                    >{{ numberFormat(totalCartAmount) }}đ</span
                >
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <Button
                    v-if="!isNotified"
                    class="h-11 rounded-xl bg-indigo-600 text-xs font-black text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 sm:col-span-2"
                    :disabled="cartItems.length === 0 || isSubmitting"
                    @click="emit('submitOrder')"
                >
                    <CheckIcon class="mr-2 size-4" />
                    {{ isSubmitting ? 'Đang gửi...' : 'Thông báo cho bếp' }}
                </Button>

                <Button
                    v-else-if="canProcessPayments"
                    class="h-11 rounded-xl bg-emerald-600 text-xs font-black text-white hover:bg-emerald-500 sm:col-span-2"
                    @click="emit('openPayment')"
                >
                    Thanh toán
                </Button>

                <Button
                    v-if="
                        isNotified &&
                        activeTable?.active_order?.status === 'pending'
                    "
                    variant="outline"
                    class="h-10 rounded-xl border-amber-500/50 bg-transparent text-xs font-bold text-amber-300 hover:bg-amber-500/10"
                    @click="emit('sendToKitchen')"
                >
                    Khóa đơn &amp; Báo bếp
                </Button>

                <Button
                    v-if="isNotified && canSplitOrders"
                    variant="outline"
                    class="h-10 rounded-xl border-rose-500/50 bg-transparent text-xs font-bold text-rose-300 hover:bg-rose-500/10"
                    @click="emit('openSplitOrder')"
                >
                    Tách đơn
                </Button>
            </div>
        </footer>
    </section>
</template>
