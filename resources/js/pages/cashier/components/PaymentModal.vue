<script setup lang="ts">
import {
    DollarSign,
    X,
    CheckCircle2 as CheckIcon,
    Search,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import type { TableItem, CustomerItem } from '../types';

const props = defineProps<{
    showPaymentModal: boolean;
    activeTable: TableItem | null;
    paymentMethod: string;
    cashReceived: number;
    changeAmount: number;
    searchCustomerPhone: string;
    isSearchingCustomer: boolean;
    foundCustomer: CustomerItem | null;
    loyaltyPointsToRedeem: number;
    isPaying: boolean;
    paymentMethods: Array<{ id: string; label: string }>;
    cashDenominations: number[];
}>();

const emit = defineEmits<{
    (e: 'update:showPaymentModal', val: boolean): void;
    (e: 'update:paymentMethod', val: string): void;
    (e: 'update:cashReceived', val: number): void;
    (e: 'update:searchCustomerPhone', val: string): void;
    (e: 'update:loyaltyPointsToRedeem', val: number): void;
    (e: 'searchCustomer'): void;
    (e: 'clearCustomerSelection'): void;
    (e: 'processPayment'): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <div
        v-if="showPaymentModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
    >
        <div
            class="animate-fade-in flex w-full max-w-md flex-col gap-6 overflow-hidden rounded-3xl border bg-white p-6 shadow-2xl dark:bg-slate-900"
        >
            <div class="flex items-center justify-between">
                <h3
                    class="flex items-center gap-2 text-base font-black text-slate-800 dark:text-slate-100"
                >
                    <DollarSign class="size-5 text-emerald-600" />
                    Xác nhận Thanh toán hóa đơn
                </h3>
                <Button
                    variant="ghost"
                    size="icon"
                    class="rounded-xl"
                    @click="emit('update:showPaymentModal', false)"
                >
                    <X class="size-5" />
                </Button>
            </div>

            <!-- Nội dung thanh toán -->
            <div class="flex flex-col gap-4 text-left">
                <div
                    class="rounded-2xl border bg-slate-50 p-4 dark:bg-slate-950"
                >
                    <div class="mb-1 flex justify-between text-xs">
                        <span class="text-slate-500">Mã hóa đơn:</span>
                        <span class="font-bold">{{
                            activeTable?.active_order?.order_number
                        }}</span>
                    </div>
                    <div class="mb-1 flex justify-between text-xs">
                        <span class="text-slate-500">Bàn:</span>
                        <span class="font-bold"
                            >Bàn {{ activeTable?.name }}</span
                        >
                    </div>

                    <Separator class="my-2" />

                    <!-- Discount breakdown -->
                    <template
                        v-if="
                            activeTable?.active_order?.discount_amount &&
                            activeTable.active_order.discount_amount > 0
                        "
                    >
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="text-slate-500">Tạm tính:</span>
                            <span class="font-mono"
                                >{{
                                    numberFormat(
                                        activeTable.active_order.subtotal,
                                    )
                                }}đ</span
                            >
                        </div>
                        <div class="mb-2 flex justify-between text-xs">
                            <span
                                class="flex items-center gap-1 font-bold text-emerald-600"
                            >
                                <CheckIcon class="size-3" /> Giảm giá voucher:
                            </span>
                            <span class="font-mono font-bold text-emerald-600">
                                -{{
                                    numberFormat(
                                        activeTable.active_order
                                            .discount_amount,
                                    )
                                }}đ
                            </span>
                        </div>
                        <Separator class="mb-2" />
                    </template>

                    <div class="flex items-center justify-between">
                        <span class="text-xs font-black"
                            >Số tiền cần thanh toán:</span
                        >
                        <span
                            class="font-mono text-lg font-black text-indigo-600"
                        >
                            {{
                                numberFormat(
                                    activeTable?.active_order?.total_amount ??
                                        0,
                                )
                            }}đ
                        </span>
                    </div>
                </div>

                <!-- Tra cứu khách hàng tích điểm -->
                <div class="mt-1 flex flex-col gap-2 border-t pt-3 text-left">
                    <span class="text-xs font-bold text-slate-500"
                        >Tích điểm thành viên:</span
                    >
                    <div v-if="!foundCustomer" class="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Nhập SĐT khách hàng..."
                            :value="searchCustomerPhone"
                            @input="
                                emit(
                                    'update:searchCustomerPhone',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                            @keyup.enter="emit('searchCustomer')"
                            class="h-9 rounded-xl text-xs"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-9 rounded-xl"
                            :disabled="isSearchingCustomer"
                            @click="emit('searchCustomer')"
                        >
                            <Search class="mr-1 size-4 shrink-0" />
                            Tìm
                        </Button>
                    </div>

                    <div
                        v-else
                        class="border-indigo-150 flex items-center justify-between rounded-xl border bg-indigo-50/50 p-3 dark:bg-indigo-950/20"
                    >
                        <div class="flex flex-col text-left text-xs">
                            <span
                                class="font-bold text-slate-800 dark:text-slate-200"
                            >
                                👤 {{ foundCustomer.full_name }}
                            </span>
                            <span
                                class="mt-0.5 text-[10px] font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                SĐT: {{ foundCustomer.phone }} • Hạng:
                                {{
                                    foundCustomer.membership_level === 'diamond'
                                        ? '💎 Kim Cương (-10%)'
                                        : foundCustomer.membership_level ===
                                            'gold'
                                          ? '⭐ Vàng (-5%)'
                                          : '🥈 Bạc'
                                }}
                                • Điểm: {{ foundCustomer.loyalty_points }} pt
                            </span>
                        </div>

                        <Button
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="h-6 w-6 rounded-lg text-rose-500 hover:bg-rose-50 hover:text-rose-600"
                            @click="emit('clearCustomerSelection')"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </div>

                <!-- Đổi điểm loyalty khi thanh toán -->
                <div
                    v-if="
                        foundCustomer && (foundCustomer.loyalty_points ?? 0) > 0
                    "
                    class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900/30 dark:bg-amber-950/20"
                >
                    <span
                        class="flex items-center gap-1.5 text-xs font-bold text-amber-700 dark:text-amber-400"
                    >
                        🎁 Đổi điểm thưởng giảm giá
                        <span
                            class="ml-auto font-mono text-[10px] text-amber-600"
                        >
                            Có sẵn: {{ foundCustomer.loyalty_points }} điểm
                        </span>
                    </span>
                    <div class="flex items-center gap-2">
                        <Input
                            type="number"
                            :value="loyaltyPointsToRedeem"
                            @input="
                                emit(
                                    'update:loyaltyPointsToRedeem',
                                    Number(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                )
                            "
                            :min="0"
                            :max="foundCustomer.loyalty_points"
                            placeholder="Nhập số điểm muốn đổi..."
                            class="h-9 flex-1 rounded-xl text-xs font-bold"
                        />
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-9 rounded-xl border-amber-300 text-xs text-amber-700 hover:bg-amber-100"
                            @click="
                                emit(
                                    'update:loyaltyPointsToRedeem',
                                    foundCustomer.loyalty_points ?? 0,
                                )
                            "
                        >
                            Đổi tất cả
                        </Button>
                    </div>
                    <p
                        v-if="loyaltyPointsToRedeem > 0"
                        class="text-[10px] text-amber-600 dark:text-amber-400"
                    >
                        ✓ Đổi {{ loyaltyPointsToRedeem }} điểm → Backend tự động
                        tính giảm giá tương ứng
                    </p>
                </div>

                <!-- Phương thức thanh toán -->
                <div class="flex flex-col gap-2">
                    <span class="text-xs font-bold text-slate-500"
                        >Phương thức thanh toán:</span
                    >
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            v-for="m in paymentMethods"
                            :key="m.id"
                            variant="outline"
                            class="h-10 rounded-xl text-xs"
                            :class="
                                paymentMethod === m.id
                                    ? 'border-indigo-600 bg-indigo-50 text-indigo-600'
                                    : ''
                            "
                            @click="emit('update:paymentMethod', m.id)"
                        >
                            {{ m.label }}
                        </Button>
                    </div>
                </div>

                <!-- Nhập tiền khách đưa nếu Tiền mặt -->
                <div
                    v-if="paymentMethod === 'cash'"
                    class="flex flex-col gap-2"
                >
                    <span class="text-xs font-bold text-slate-500"
                        >Số tiền khách đưa:</span
                    >
                    <Input
                        type="number"
                        :value="cashReceived"
                        @input="
                            emit(
                                'update:cashReceived',
                                Number(
                                    ($event.target as HTMLInputElement).value,
                                ),
                            )
                        "
                        class="h-10 rounded-xl font-mono text-xs font-bold"
                    />

                    <!-- Gợi ý mệnh giá -->
                    <div class="flex flex-wrap gap-1">
                        <Button
                            v-for="denom in cashDenominations"
                            :key="denom"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-7 rounded-lg border-slate-200 px-2.5 text-[10px] font-bold dark:border-slate-800"
                            @click="emit('update:cashReceived', denom)"
                        >
                            {{ numberFormat(denom) }}đ
                        </Button>
                    </div>

                    <div
                        class="mt-1 flex justify-between text-xs font-bold text-emerald-600"
                    >
                        <span>Tiền thối lại:</span>
                        <span class="font-mono"
                            >{{ numberFormat(changeAmount) }}đ</span
                        >
                    </div>
                </div>

                <!-- Thông tin ghi nợ VIP/B2B -->
                <div
                    v-if="paymentMethod === 'debt'"
                    class="flex flex-col gap-2 rounded-xl p-1 text-left text-xs"
                >
                    <div
                        v-if="!foundCustomer"
                        class="rounded-lg border border-rose-100 bg-rose-50 p-2.5 font-bold text-rose-500"
                    >
                        ⚠️ Giao dịch ghi nợ yêu cầu chọn khách hàng trước.
                    </div>
                    <div
                        v-else-if="
                            !foundCustomer.is_vip && !foundCustomer.is_b2b
                        "
                        class="rounded-lg border border-rose-100 bg-rose-50 p-2.5 font-bold text-rose-500"
                    >
                        ⚠️ Khách hàng này không được cấp quyền mua nợ (Không
                        phải VIP/B2B).
                    </div>
                    <div
                        v-else
                        class="flex flex-col gap-1.5 rounded-xl border bg-slate-50 p-3 dark:bg-slate-900/20"
                    >
                        <div class="flex justify-between">
                            <span class="text-slate-500"
                                >Hạn mức nợ tối đa:</span
                            >
                            <span class="font-mono font-bold"
                                >{{
                                    numberFormat(
                                        foundCustomer.credit_limit ?? 0,
                                    )
                                }}đ</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Dư nợ hiện tại:</span>
                            <span class="font-mono font-bold text-rose-500"
                                >{{
                                    numberFormat(
                                        foundCustomer.current_debt ?? 0,
                                    )
                                }}đ</span
                            >
                        </div>
                        <div
                            class="flex justify-between border-t pt-1.5 dark:border-slate-800"
                        >
                            <span class="font-bold text-slate-500"
                                >Khả năng nợ còn lại:</span
                            >
                            <span
                                class="font-mono font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{
                                    numberFormat(
                                        (foundCustomer.credit_limit ?? 0) -
                                            (foundCustomer.current_debt ?? 0),
                                    )
                                }}đ
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <Button
                    variant="outline"
                    class="flex-1 rounded-xl text-xs"
                    @click="emit('update:showPaymentModal', false)"
                >
                    Hủy
                </Button>
                <Button
                    class="flex-1 rounded-xl bg-emerald-600 text-xs hover:bg-emerald-700"
                    :disabled="
                        isPaying ||
                        (paymentMethod === 'debt' &&
                            (!foundCustomer ||
                                (!foundCustomer.is_vip &&
                                    !foundCustomer.is_b2b)))
                    "
                    @click="emit('processPayment')"
                >
                    {{
                        isPaying ? 'Đang thanh toán...' : 'Xác nhận Thanh toán'
                    }}
                </Button>
            </div>
        </div>
    </div>
</template>
