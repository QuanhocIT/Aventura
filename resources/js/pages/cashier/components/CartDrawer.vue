<script setup lang="ts">
import {
    X,
    ShoppingCart,
    Plus,
    Minus,
    Trash2,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { OrderItem, TableItem } from '../types';

const props = defineProps<{
    isCartOpen: boolean;
    activeTable: TableItem | null;
    drawerStep: 'select' | 'confirm';
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
    (e: 'update:isCartOpen', val: boolean): void;
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

const serviceItems = computed(() =>
    (props.activeTable?.active_order?.items ?? []).filter(
        (item) => item.status !== 'cancelled',
    ),
);
const canPay = computed(() => {
    const order = props.activeTable?.active_order;

    return Boolean(
        order?.payment_status === 'unpaid' &&
            serviceItems.value.length > 0 &&
            serviceItems.value.every((item) => Boolean(item.served_at)),
    );
});
const paymentBlockMessage = computed(() => {
    if (props.activeTable?.active_order?.payment_status !== 'unpaid') {
        return 'Đơn đã thanh toán';
    }

    return 'Chờ phục vụ đủ món';
});
</script>

<template>
    <div
        v-if="isCartOpen"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white shadow-2xl transition-all duration-300 dark:bg-slate-900"
    >
        <!-- Header Drawer -->
        <div
            class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex size-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                >
                    <ShoppingCart class="size-5" />
                </div>
                <div class="text-left">
                    <h3 class="font-black text-slate-800 dark:text-slate-100">
                        {{
                            activeTable
                                ? `Bàn ${activeTable.name}`
                                : 'Chi tiết giỏ hàng'
                        }}
                    </h3>
                    <span class="text-[10px] text-muted-foreground">
                        {{ activeTable?.area || 'Chưa chọn bàn' }}
                    </span>
                </div>
            </div>

            <Button
                variant="ghost"
                size="icon"
                class="rounded-xl"
                @click="emit('update:isCartOpen', false)"
            >
                <X class="size-5" />
            </Button>
        </div>

        <!-- Danh sách món ăn trong giỏ -->
        <div class="flex-1 overflow-y-auto p-4">
            <div
                v-if="cartItems.length === 0"
                class="flex h-64 flex-col items-center justify-center text-slate-400"
            >
                <ShoppingCart class="size-12 stroke-1" />
                <p class="mt-2 text-xs font-bold">
                    Chưa có món nào trong giỏ hàng
                </p>
                <p class="text-[10px]">Vui lòng chọn món từ menu bên trái</p>
            </div>

            <div v-else class="flex flex-col gap-3">
                <div
                    v-for="(item, idx) in cartItems"
                    :key="idx"
                    class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/50 p-3 dark:border-slate-800/60 dark:bg-slate-950/40"
                >
                    <div class="flex flex-col text-left">
                        <span
                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            {{ item.product_name }}
                        </span>
                        <span
                            class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ numberFormat(item.price) }}đ
                        </span>
                        <Input
                            type="text"
                            placeholder="Ghi chú món..."
                            :value="item.notes"
                            @input="
                                item.notes = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                            class="mt-1.5 h-7 rounded-lg text-[10px]"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <div
                            class="flex items-center gap-1 rounded-xl bg-white p-1 shadow-sm dark:bg-slate-800"
                        >
                            <button
                                @click="emit('decreaseQty', item)"
                                class="flex size-6 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                <Minus class="size-3" />
                            </button>
                            <span
                                class="w-5 text-center font-mono text-xs font-bold"
                            >
                                {{ item.quantity }}
                            </span>
                            <button
                                @click="emit('increaseQty', item)"
                                class="flex size-6 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700"
                            >
                                <Plus class="size-3" />
                            </button>
                        </div>

                        <button
                            v-if="!item.id"
                            @click="emit('removeItem', item)"
                            class="flex size-7 items-center justify-center rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="mt-2 text-left">
                    <label
                        class="mb-1 block text-[11px] font-bold text-slate-500"
                        >Ghi chú đơn hàng:</label
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
                        class="h-9 rounded-xl text-xs"
                    />
                </div>
            </div>
        </div>

        <!-- Footer Drawer -->
        <div
            class="flex flex-col gap-3 border-t border-slate-200 p-4 dark:border-slate-800"
        >
            <div class="flex justify-between text-xs font-medium">
                <span class="text-slate-500">Tổng số món:</span>
                <span class="font-bold">{{ totalCartQty }} món</span>
            </div>

            <div class="flex justify-between text-base font-black">
                <span>Tổng cộng:</span>
                <span class="font-mono text-indigo-600 dark:text-indigo-400">
                    {{ numberFormat(totalCartAmount) }}đ
                </span>
            </div>

            <!-- Các nút hành động -->
            <div class="flex gap-2">
                <Button
                    v-if="drawerStep === 'select'"
                    class="flex-1 rounded-xl bg-indigo-600 text-xs hover:bg-indigo-700"
                    :disabled="cartItems.length === 0"
                    @click="emit('update:drawerStep', 'confirm')"
                >
                    Xác nhận đặt món
                </Button>

                <template v-else-if="drawerStep === 'confirm'">
                    <Button
                        variant="outline"
                        class="flex-1 rounded-xl border-indigo-200 text-xs text-indigo-600 hover:bg-indigo-50"
                        @click="emit('update:drawerStep', 'select')"
                    >
                        Thêm món
                    </Button>

                    <Button
                        v-if="!isNotified"
                        class="flex-1 rounded-xl bg-indigo-600 text-xs hover:bg-indigo-700"
                        :disabled="isSubmitting"
                        @click="emit('submitOrder')"
                    >
                        {{ isSubmitting ? 'Đang gửi...' : 'Thông báo' }}
                    </Button>

                    <Button
                        v-else-if="canProcessPayments && canPay"
                        class="flex-1 rounded-xl bg-emerald-600 text-xs hover:bg-emerald-700"
                        @click="emit('openPayment')"
                    >
                        Thanh toán
                    </Button>

                    <Button
                        v-else-if="canProcessPayments"
                        disabled
                        class="flex-1 cursor-not-allowed bg-slate-200 text-xs font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        :title="paymentBlockMessage"
                    >
                        {{ paymentBlockMessage }}
                    </Button>
                </template>
            </div>

            <!-- Phím bổ sung khi đã thông báo -->
            <div
                class="flex gap-2"
                v-if="
                    drawerStep === 'confirm' &&
                    activeTable?.active_order &&
                    isNotified
                "
            >
                <Button
                    variant="outline"
                    class="flex-1 rounded-xl border-amber-200 text-xs text-amber-600 hover:bg-amber-50"
                    v-if="activeTable.active_order.status === 'pending'"
                    @click="emit('sendToKitchen')"
                >
                    Khóa đơn & Báo Bếp
                </Button>

                <Button
                    variant="outline"
                    class="flex-1 rounded-xl border-rose-200 text-xs text-rose-600 hover:bg-rose-50"
                    v-if="canSplitOrders"
                    @click="emit('openSplitOrder')"
                >
                    Tách đơn
                </Button>
            </div>
        </div>
    </div>
</template>
