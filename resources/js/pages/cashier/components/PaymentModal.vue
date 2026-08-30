<script setup lang="ts">
import {
    DollarSign,
    X,
    CheckCircle2 as CheckIcon,
    Search,
    Ticket,
    Tag,
    ShieldAlert,
    Loader2,
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import type { AvailableVoucher } from '../composables/useCashierPayment';
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
    voucherCode?: string;
    availableVouchers?: AvailableVoucher[];
    isLoadingVouchers?: boolean;
    isApplyingVoucher?: boolean;
    isCheckingVoucher?: boolean;
    voucherPreview?: {
        valid: boolean;
        message?: string;
        promotion?: {
            name: string;
            type: string;
            value: number;
            remaining_budget: number | null;
            usage_count: number;
            usage_limit: number | null;
        };
    } | null;
    customerNotFound: boolean;
    showCreateCustomerForm: boolean;
    newCustomerName: string;
    isCreatingCustomer: boolean;
    bypassRequired?: boolean;
    bypassMessage?: string;
    bypassCode?: string;
    appliedVoucherName?: string;
    isPaying: boolean;
    paymentMethods: Array<{ id: string; label: string }>;
    cashDenominations: number[];
    multiPayments?: Array<{
        payment_method: 'cash' | 'bank_transfer' | 'card' | 'ewallet';
        amount: number;
        cash_received?: number;
        change_amount?: number;
    }>;
    multiTotalPaid?: number;
    multiRemainingBalance?: number;
}>();

const emit = defineEmits<{
    (e: 'update:showPaymentModal', val: boolean): void;
    (e: 'update:paymentMethod', val: string): void;
    (e: 'update:cashReceived', val: number): void;
    (e: 'update:searchCustomerPhone', val: string): void;
    (e: 'update:loyaltyPointsToRedeem', val: number): void;
    (e: 'update:newCustomerName', val: string): void;
    (e: 'update:voucherCode', val: string): void;
    (e: 'update:bypassCode', val: string): void;
    (e: 'searchCustomer'): void;
    (e: 'startCreateCustomer'): void;
    (e: 'cancelCreateCustomer'): void;
    (e: 'createCustomer'): void;
    (e: 'clearCustomerSearchStatus'): void;
    (e: 'clearCustomerSelection'): void;
    (e: 'applyVoucher'): void;
    (e: 'previewVoucher'): void;
    (e: 'processPayment'): void;
    (e: 'addMultiPayment'): void;
    (e: 'removeMultiPayment', index: number): void;
}>();

const numberFormat = (val: number) =>
    new Intl.NumberFormat('vi-VN').format(val);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="animate-fade-in my-auto flex max-h-[90vh] w-full max-w-md flex-col overflow-hidden rounded-3xl border bg-white shadow-2xl dark:bg-slate-900"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-100 p-5 pb-4 dark:border-slate-800"
                >
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
                <div
                    class="flex flex-1 flex-col gap-4 overflow-y-auto p-5 text-left"
                >
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
                                    <CheckIcon class="size-3" /> Giảm giá
                                    voucher:
                                </span>
                                <span
                                    class="font-mono font-bold text-emerald-600"
                                >
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
                                        activeTable?.active_order
                                            ?.total_amount ?? 0,
                                    )
                                }}đ
                            </span>
                        </div>
                    </div>

                    <!-- Khuyến mãi / Áp dụng Voucher -->
                    <div
                        class="mt-1 flex flex-col gap-2 border-t pt-3 text-left"
                    >
                        <span
                            class="flex items-center gap-1.5 text-xs font-bold text-slate-500"
                        >
                            <Ticket
                                class="size-3.5 text-indigo-600 dark:text-indigo-400"
                            />
                            Chọn mã khuyến mãi / Voucher:
                        </span>

                        <div class="flex gap-2">
                            <select
                                :value="voucherCode ?? ''"
                                @change="
                                    emit(
                                        'update:voucherCode',
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    );
                                    emit('previewVoucher');
                                "
                                class="h-9 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                :disabled="
                                    isLoadingVouchers ||
                                    isApplyingVoucher ||
                                    !availableVouchers?.length
                                "
                            >
                                <option value="">
                                    {{
                                        isLoadingVouchers
                                            ? 'Đang tải mã ưu đãi...'
                                            : availableVouchers?.length
                                              ? 'Chọn mã ưu đãi...'
                                              : 'Không có mã phù hợp với đơn'
                                    }}
                                </option>
                                <option
                                    v-for="voucher in availableVouchers"
                                    :key="voucher.id"
                                    :value="voucher.code"
                                >
                                    {{ voucher.code }} — {{ voucher.name }} (-{{
                                        voucher.discount_label
                                    }})
                                </option>
                            </select>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="h-9 shrink-0 rounded-xl border-indigo-200 bg-indigo-50/50 text-xs font-bold text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-300"
                                :disabled="
                                    isApplyingVoucher ||
                                    !(voucherCode && voucherCode.trim())
                                "
                                @click="emit('applyVoucher')"
                            >
                                <Loader2
                                    v-if="isApplyingVoucher"
                                    class="mr-1 size-3.5 animate-spin"
                                />
                                <Tag
                                    v-else
                                    class="mr-1 size-3.5 shrink-0 text-indigo-600 dark:text-indigo-400"
                                />
                                Áp dụng
                            </Button>
                        </div>

                        <p
                            v-if="
                                !isLoadingVouchers && !availableVouchers?.length
                            "
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            Chỉ các mã đúng chi nhánh, còn hiệu lực và đủ điều
                            kiện đơn hàng mới hiển thị ở đây.
                        </p>

                        <!-- Xem trước mã trước khi áp: dùng /api/promotions/validate,
                         endpoint vốn đã có sẵn nhưng chưa màn hình nào gọi tới. -->
                        <div
                            v-if="isCheckingVoucher"
                            class="flex items-center gap-2 text-[11px] text-slate-500"
                        >
                            <Loader2 class="size-3.5 animate-spin" /> Đang kiểm
                            tra mã...
                        </div>
                        <div
                            v-else-if="voucherPreview && !appliedVoucherName"
                            :class="[
                                'rounded-xl border p-2.5 text-[11px]',
                                voucherPreview.valid
                                    ? 'border-sky-200 bg-sky-50/70 text-sky-800 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-300'
                                    : 'border-rose-200 bg-rose-50/70 text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300',
                            ]"
                        >
                            <template
                                v-if="
                                    voucherPreview.valid &&
                                    voucherPreview.promotion
                                "
                            >
                                <span class="font-bold">{{
                                    voucherPreview.promotion.name
                                }}</span>
                                <span class="ml-1">
                                    — giảm
                                    {{
                                        voucherPreview.promotion.type ===
                                        'percent'
                                            ? `${voucherPreview.promotion.value}%`
                                            : `${numberFormat(voucherPreview.promotion.value)}đ`
                                    }}
                                </span>
                                <div class="mt-0.5 opacity-80">
                                    Đã dùng
                                    {{ voucherPreview.promotion.usage_count }}
                                    <template
                                        v-if="
                                            voucherPreview.promotion.usage_limit
                                        "
                                    >
                                        /
                                        {{
                                            voucherPreview.promotion.usage_limit
                                        }}
                                    </template>
                                    lượt
                                    <template
                                        v-if="
                                            voucherPreview.promotion
                                                .remaining_budget !== null
                                        "
                                    >
                                        · Ngân sách còn
                                        {{
                                            numberFormat(
                                                voucherPreview.promotion
                                                    .remaining_budget,
                                            )
                                        }}đ
                                    </template>
                                </div>
                            </template>
                            <span v-else class="font-bold">{{
                                voucherPreview.message
                            }}</span>
                        </div>

                        <!-- Hiển thị mã đã được áp dụng thành công -->
                        <div
                            v-if="
                                appliedVoucherName ||
                                (activeTable?.active_order?.discount_amount &&
                                    activeTable.active_order.discount_amount >
                                        0)
                            "
                            class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/70 p-2.5 text-xs text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300"
                        >
                            <CheckIcon
                                class="size-4 shrink-0 text-emerald-600"
                            />
                            <span class="font-bold">
                                Đã giảm
                                {{
                                    numberFormat(
                                        activeTable?.active_order
                                            ?.discount_amount ?? 0,
                                    )
                                }}đ
                                <span v-if="appliedVoucherName"
                                    >({{ appliedVoucherName }})</span
                                >
                            </span>
                        </div>

                        <!-- Cảnh báo mã yêu cầu Quản lý xác thực (Bypass) -->
                        <div
                            v-if="bypassRequired"
                            class="flex flex-col gap-2 rounded-xl border border-amber-200 bg-amber-50/80 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            <span
                                class="flex items-center gap-1 font-bold text-amber-700 dark:text-amber-400"
                            >
                                <ShieldAlert class="size-4 text-amber-600" />
                                Yêu cầu phê duyệt từ Quản lý
                            </span>
                            <p
                                class="text-[11px] leading-4 text-amber-700 dark:text-amber-300"
                            >
                                {{ bypassMessage }}
                            </p>
                            <div class="flex gap-2">
                                <Input
                                    type="password"
                                    placeholder="Mã PIN Quản lý..."
                                    :value="bypassCode"
                                    @input="
                                        emit(
                                            'update:bypassCode',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                    @keyup.enter="emit('applyVoucher')"
                                    class="h-8 rounded-lg text-xs"
                                />
                                <Button
                                    type="button"
                                    size="sm"
                                    class="h-8 rounded-lg bg-amber-600 text-xs font-bold text-white hover:bg-amber-700"
                                    :disabled="
                                        isApplyingVoucher ||
                                        !(bypassCode && bypassCode.trim())
                                    "
                                    @click="emit('applyVoucher')"
                                >
                                    Xác nhận
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Tra cứu khách hàng tích điểm -->
                    <div
                        class="mt-1 flex flex-col gap-2 border-t pt-3 text-left"
                    >
                        <span class="text-xs font-bold text-slate-500"
                            >Tích điểm thành viên:</span
                        >
                        <div v-if="!foundCustomer" class="flex flex-col gap-2">
                            <div class="flex gap-2">
                                <Input
                                    type="text"
                                    placeholder="Nhập SĐT khách hàng..."
                                    :value="searchCustomerPhone"
                                    @input="
                                        emit(
                                            'update:searchCustomerPhone',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        );
                                        emit('clearCustomerSearchStatus');
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
                                v-if="
                                    customerNotFound && !showCreateCustomerForm
                                "
                                class="flex flex-col gap-2 rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-xs dark:border-rose-900/50 dark:bg-rose-950/20"
                            >
                                <span
                                    class="font-bold text-rose-700 dark:text-rose-300"
                                >
                                    Không tìm thấy khách hàng với số điện thoại
                                    này.
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="h-9 w-full rounded-xl border-indigo-200 bg-white text-xs font-bold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-900/50 dark:bg-slate-900 dark:text-indigo-300"
                                    @click="emit('startCreateCustomer')"
                                >
                                    + Tạo khách hàng thân quen
                                </Button>
                            </div>

                            <div
                                v-if="showCreateCustomerForm"
                                class="flex flex-col gap-2 rounded-xl border border-indigo-200 bg-indigo-50/60 p-3 text-xs dark:border-indigo-900/50 dark:bg-indigo-950/20"
                            >
                                <span
                                    class="font-bold text-indigo-700 dark:text-indigo-300"
                                >
                                    Tạo khách hàng thân quen
                                </span>
                                <div
                                    class="rounded-lg bg-white/70 px-3 py-2 text-[11px] text-slate-600 dark:bg-slate-900/70 dark:text-slate-300"
                                >
                                    Số điện thoại:
                                    <strong>{{ searchCustomerPhone }}</strong>
                                </div>
                                <Input
                                    type="text"
                                    placeholder="Nhập tên khách hàng..."
                                    :value="newCustomerName"
                                    @input="
                                        emit(
                                            'update:newCustomerName',
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                    @keyup.enter="emit('createCustomer')"
                                    class="h-9 rounded-xl text-xs"
                                    autofocus
                                />
                                <div class="flex gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 flex-1 rounded-lg text-xs"
                                        :disabled="isCreatingCustomer"
                                        @click="emit('cancelCreateCustomer')"
                                    >
                                        Hủy
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="h-8 flex-1 rounded-lg bg-indigo-600 text-xs text-white hover:bg-indigo-700"
                                        :disabled="
                                            isCreatingCustomer ||
                                            !newCustomerName.trim() ||
                                            !searchCustomerPhone.trim()
                                        "
                                        @click="emit('createCustomer')"
                                    >
                                        <Loader2
                                            v-if="isCreatingCustomer"
                                            class="mr-1 size-3.5 animate-spin"
                                        />
                                        {{
                                            isCreatingCustomer
                                                ? 'Đang tạo...'
                                                : 'Tạo & gắn vào đơn'
                                        }}
                                    </Button>
                                </div>
                            </div>
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
                                        foundCustomer.membership_level ===
                                        'diamond'
                                            ? '💎 Kim Cương (-10%)'
                                            : foundCustomer.membership_level ===
                                                'gold'
                                              ? '⭐ Vàng (-5%)'
                                              : '🥈 Bạc'
                                    }}
                                    • Điểm:
                                    {{ foundCustomer.loyalty_points }} pt
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
                            foundCustomer &&
                            (foundCustomer.loyalty_points ?? 0) > 0
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
                                        Math.min(
                                            foundCustomer.loyalty_points ?? 0,
                                            Math.ceil(
                                                (activeTable?.active_order
                                                    ?.total_amount ?? 0) / 100,
                                            ),
                                        ),
                                    )
                                "
                            >
                                Đổi tối đa cho đơn
                            </Button>
                        </div>
                        <p
                            v-if="loyaltyPointsToRedeem > 0"
                            class="text-[10px] text-amber-600 dark:text-amber-400"
                        >
                            ✓ Đổi {{ loyaltyPointsToRedeem }} điểm ≈ Giảm
                            {{ numberFormat(loyaltyPointsToRedeem * 100) }}đ
                            <span
                                v-if="
                                    loyaltyPointsToRedeem * 100 >=
                                    (activeTable?.active_order?.total_amount ??
                                        0)
                                "
                                class="font-bold text-emerald-600"
                            >
                                (Đủ thanh toán 100% đơn hàng)
                            </span>
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
                                        ($event.target as HTMLInputElement)
                                            .value,
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

                    <!-- Thanh toán kết hợp (Multi-Tender) -->
                    <div
                        v-if="paymentMethod === 'multi'"
                        class="flex flex-col gap-3 rounded-2xl border border-indigo-200 bg-indigo-50/50 p-3.5 text-left text-xs dark:border-indigo-900/50 dark:bg-indigo-950/20"
                    >
                        <div
                            class="flex items-center justify-between font-bold text-indigo-700 dark:text-indigo-300"
                        >
                            <span>🔀 Nhập số tiền từng phương thức:</span>
                            <span
                                class="font-mono text-[11px]"
                                :class="
                                    (multiRemainingBalance ?? 0) > 0
                                        ? 'text-amber-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{
                                    (multiRemainingBalance ?? 0) > 0
                                        ? `Còn thiếu: ${numberFormat(multiRemainingBalance ?? 0)}đ`
                                        : '✓ Đã nhập đủ tiền'
                                }}
                            </span>
                        </div>

                        <div
                            v-for="(p, idx) in multiPayments"
                            :key="idx"
                            class="flex items-center gap-2 rounded-xl border bg-white p-2 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <select
                                v-model="p.payment_method"
                                class="h-8 rounded-lg border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <option value="cash">💵 Tiền mặt</option>
                                <option value="bank_transfer">
                                    🏦 QR Chuyển khoản
                                </option>
                                <option value="card">💳 Thẻ ATM/POS</option>
                                <option value="ewallet">📱 Ví điện tử</option>
                            </select>
                            <Input
                                type="number"
                                v-model.number="p.amount"
                                placeholder="Số tiền..."
                                class="h-8 flex-1 font-mono text-xs font-bold"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="h-7 w-7 text-rose-500 hover:bg-rose-50"
                                @click="emit('removeMultiPayment', idx)"
                                v-if="(multiPayments?.length ?? 0) > 1"
                            >
                                <X class="size-3.5" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 rounded-xl border-dashed border-indigo-300 text-xs font-bold text-indigo-600 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-400"
                            @click="emit('addMultiPayment')"
                        >
                            + Thêm phương thức thanh toán
                        </Button>
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
                                <span class="text-slate-500"
                                    >Dư nợ hiện tại:</span
                                >
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
                                                (foundCustomer.current_debt ??
                                                    0),
                                        )
                                    }}đ
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex shrink-0 gap-2 border-t border-slate-100 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                >
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
                            isPaying
                                ? 'Đang thanh toán...'
                                : 'Xác nhận Thanh toán'
                        }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
