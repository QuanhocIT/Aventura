import { router } from '@inertiajs/vue3';
import axios from 'axios';
import type { Ref } from 'vue';
import { ref, computed } from 'vue';
import type { TableItem, CustomerItem } from '../types';

export type AvailableVoucher = {
    id: number;
    code: string;
    name: string;
    type: 'percent' | 'fixed_amount';
    value: number;
    min_order_amount: number;
    max_discount_amount: number;
    discount_label: string;
};

export function useCashierPayment(
    activeTable: Ref<TableItem | null>,
    isCartOpen: Ref<boolean>,
    toast: (msg: string, type?: 'success' | 'error') => void,
) {
    const showPaymentModal = ref(false);
    const paymentMethod = ref<
        'cash' | 'bank_transfer' | 'card' | 'ewallet' | 'debt' | 'multi'
    >('cash');
    const cashReceived = ref<number>(0);
    const multiPayments = ref<
        Array<{
            payment_method: 'cash' | 'bank_transfer' | 'card' | 'ewallet';
            amount: number;
            cash_received?: number;
            change_amount?: number;
        }>
    >([
        {
            payment_method: 'cash',
            amount: 0,
            cash_received: 0,
            change_amount: 0,
        },
        { payment_method: 'bank_transfer', amount: 0 },
    ]);
    const searchCustomerPhone = ref('');
    const isSearchingCustomer = ref(false);
    const foundCustomer = ref<CustomerItem | null>(null);
    const loyaltyPointsToRedeem = ref<number>(0);
    const voucherCode = ref('');
    const isApplyingVoucher = ref(false);
    // Xem trước mã: dùng endpoint /api/promotions/validate vốn đã tồn tại từ đầu
    // nhưng chưa được màn hình nào gọi tới.
    const voucherPreview = ref<{
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
    } | null>(null);
    const isCheckingVoucher = ref(false);
    const bypassRequired = ref(false);
    const bypassMessage = ref('');
    const bypassCode = ref('');
    const appliedVoucherName = ref('');
    const availableVouchers = ref<AvailableVoucher[]>([]);
    const isLoadingVouchers = ref(false);
    const isPaying = ref(false);

    const paymentMethods = [
        { id: 'cash' as const, label: '💵 Tiền mặt' },
        { id: 'bank_transfer' as const, label: '🏦 Chuyển khoản QR' },
        { id: 'card' as const, label: '💳 Thẻ ATM/POS' },
        { id: 'ewallet' as const, label: '📱 Ví điện tử' },
        { id: 'debt' as const, label: '📝 Ghi nợ VIP/B2B' },
        { id: 'multi' as const, label: '🔀 Thanh toán kết hợp (Multi-Tender)' },
    ];

    const cashDenominations = [50000, 100000, 200000, 500000];

    const changeAmount = computed(() => {
        const total = activeTable.value?.active_order?.total_amount ?? 0;

        return Math.max(0, cashReceived.value - total);
    });

    const multiTotalPaid = computed(() => {
        return multiPayments.value.reduce(
            (sum, item) => sum + (Number(item.amount) || 0),
            0,
        );
    });

    const multiRemainingBalance = computed(() => {
        const total = activeTable.value?.active_order?.total_amount ?? 0;

        return Math.max(0, total - multiTotalPaid.value);
    });

    const addMultiPayment = () => {
        multiPayments.value.push({
            payment_method: 'bank_transfer',
            amount: multiRemainingBalance.value,
        });
    };

    const removeMultiPayment = (index: number) => {
        if (multiPayments.value.length > 1) {
            multiPayments.value.splice(index, 1);
        }
    };

    const searchCustomer = () => {
        if (!searchCustomerPhone.value.trim()) {
            return;
        }

        isSearchingCustomer.value = true;
        axios
            .get('/api/customers/search', {
                params: { phone: searchCustomerPhone.value.trim() },
            })
            .then((res) => {
                if (res.data.customer) {
                    foundCustomer.value = res.data.customer;
                    toast(
                        `Đã tìm thấy khách hàng: ${res.data.customer.full_name}`,
                    );
                } else {
                    toast('Không tìm thấy thông tin khách hàng.', 'error');
                }
            })
            .catch(() => {
                toast('Lỗi khi tra cứu khách hàng.', 'error');
            })
            .finally(() => {
                isSearchingCustomer.value = false;
            });
    };

    const clearCustomerSelection = () => {
        foundCustomer.value = null;
        searchCustomerPhone.value = '';
    };

    const loadAvailableVouchers = (orderId: number) => {
        isLoadingVouchers.value = true;
        availableVouchers.value = [];

        axios
            .get('/api/promotions/available', {
                params: { order_id: orderId },
            })
            .then((res) => {
                availableVouchers.value = res.data.promotions ?? [];
            })
            .catch((err) => {
                toast(
                    err.response?.data?.message ||
                        'Không thể tải danh sách mã ưu đãi.',
                    'error',
                );
            })
            .finally(() => {
                isLoadingVouchers.value = false;
            });
    };

    const canPayActiveOrder = () => {
        const order = activeTable.value?.active_order;
        const serviceItems = (order?.items ?? []).filter(
            (item) => item.status !== 'cancelled',
        );

        return Boolean(
            order?.payment_status === 'unpaid' &&
            serviceItems.length > 0 &&
            serviceItems.every((item) => Boolean(item.served_at)),
        );
    };

    const openPayment = () => {
        if (!canPayActiveOrder()) {
            toast(
                'Chưa thể thanh toán: đơn hàng vẫn còn món chưa được phục vụ.',
                'error',
            );

            return;
        }

        paymentMethod.value = 'cash';
        const orderTotal = activeTable.value?.active_order?.total_amount ?? 0;
        cashReceived.value = orderTotal;
        multiPayments.value = [
            {
                payment_method: 'cash',
                amount: Math.floor(orderTotal / 2),
                cash_received: Math.floor(orderTotal / 2),
                change_amount: 0,
            },
            {
                payment_method: 'bank_transfer',
                amount: Math.ceil(orderTotal / 2),
            },
        ];
        foundCustomer.value = null;
        searchCustomerPhone.value = '';
        loyaltyPointsToRedeem.value = 0;
        voucherCode.value = '';
        bypassRequired.value = false;
        bypassMessage.value = '';
        bypassCode.value = '';
        appliedVoucherName.value = '';
        voucherPreview.value = null;

        const orderId = activeTable.value?.active_order?.id;

        if (orderId) {
            loadAvailableVouchers(orderId);
        }

        showPaymentModal.value = true;
    };

    const previewVoucher = () => {
        const code = voucherCode.value.trim().toUpperCase();
        const orderId = activeTable.value?.active_order?.id;

        if (!code) {
            voucherPreview.value = null;

            return;
        }

        isCheckingVoucher.value = true;
        axios
            .post('/api/promotions/validate', { code, order_id: orderId })
            .then((res) => {
                voucherPreview.value = res.data;
            })
            .catch(() => {
                voucherPreview.value = null;
            })
            .finally(() => {
                isCheckingVoucher.value = false;
            });
    };

    const applyVoucher = () => {
        const orderId = activeTable.value?.active_order?.id;

        if (!voucherCode.value.trim() || !orderId) {
            toast('Vui lòng chọn mã khuyến mãi / voucher.', 'error');

            return;
        }

        isApplyingVoucher.value = true;
        axios
            .post('/api/promotions/apply', {
                order_id: orderId,
                code: voucherCode.value.trim().toUpperCase(),
                bypass_code: bypassCode.value
                    ? bypassCode.value.trim()
                    : undefined,
            })
            .then((res) => {
                const data = res.data;
                toast(data.message || 'Áp dụng mã khuyến mãi thành công!');
                bypassRequired.value = false;
                bypassMessage.value = '';
                bypassCode.value = '';
                appliedVoucherName.value =
                    data.promotion_name || voucherCode.value.toUpperCase();
                voucherCode.value = '';
                voucherPreview.value = null;

                // Voucher cộng dồn với ưu đãi hội viên / điểm tích lũy đã áp
                // trước đó — backend cảnh báo khi tổng vượt ngưỡng cấu hình.
                if (data.warning) {
                    toast(data.warning, 'error');
                }

                if (activeTable.value?.active_order) {
                    if (data.discount_amount !== undefined) {
                        activeTable.value.active_order.discount_amount =
                            data.discount_amount;
                    }

                    if (data.total_amount !== undefined) {
                        activeTable.value.active_order.total_amount =
                            data.total_amount;
                        cashReceived.value = data.total_amount;
                    }
                }

                router.reload({ only: ['tablesData'] });
            })
            .catch((err) => {
                const data = err.response?.data;

                if (data?.status === 'requires_bypass') {
                    bypassRequired.value = true;
                    bypassMessage.value =
                        data.message ||
                        'Yêu cầu nhập mã phê duyệt của Quản lý.';
                    toast(
                        data.message ||
                            'Phát hiện cảnh báo: Yêu cầu mã duyệt của Quản lý.',
                        'error',
                    );
                } else {
                    toast(
                        data?.message ||
                            data?.error ||
                            'Mã khuyến mãi không hợp lệ hoặc đã hết hạn.',
                        'error',
                    );
                }
            })
            .finally(() => {
                isApplyingVoucher.value = false;
            });
    };

    const processPayment = () => {
        if (!activeTable.value?.active_order || isPaying.value) {
            return;
        }

        if (!canPayActiveOrder()) {
            toast(
                'Chưa thể thanh toán: đơn hàng vẫn còn món chưa được phục vụ.',
                'error',
            );

            return;
        }

        const redeemPoints = Math.max(
            0,
            Math.floor(loyaltyPointsToRedeem.value ?? 0),
        );

        if (redeemPoints > 0 && foundCustomer.value) {
            const availablePoints = foundCustomer.value.loyalty_points ?? 0;

            if (redeemPoints > availablePoints) {
                toast(
                    `Không đủ điểm: Khách chỉ có ${availablePoints} điểm.`,
                    'error',
                );

                return;
            }
        }

        const payload: any = {
            payment_method: paymentMethod.value,
            cash_received: cashReceived.value,
            change_amount: changeAmount.value,
            customer_id: foundCustomer.value ? foundCustomer.value.id : null,
            redeem_points: redeemPoints > 0 ? redeemPoints : undefined,
        };

        if (paymentMethod.value === 'multi') {
            const validPayments = multiPayments.value.filter(
                (p) => Number(p.amount) > 0,
            );

            if (validPayments.length === 0) {
                toast(
                    'Vui lòng nhập số tiền cho ít nhất 1 phương thức thanh toán.',
                    'error',
                );

                return;
            }

            const total = activeTable.value?.active_order?.total_amount ?? 0;

            if (multiTotalPaid.value < total) {
                toast(
                    `Tổng số tiền thanh toán (${multiTotalPaid.value.toLocaleString()}đ) còn thiếu ${(total - multiTotalPaid.value).toLocaleString()}đ`,
                    'error',
                );

                return;
            }

            payload.payments = validPayments;
        }

        isPaying.value = true;
        axios
            .post(`/orders/${activeTable.value.active_order.id}/pay`, payload)
            .then(() => {
                showPaymentModal.value = false;
                isCartOpen.value = false;
                loyaltyPointsToRedeem.value = 0;
                const msg =
                    redeemPoints > 0
                        ? `Thanh toán thành công! Đã đổi ${redeemPoints} điểm loyalty.`
                        : 'Đã thanh toán hóa đơn thành công. Bàn đã chuyển sang trạng thái trống.';
                toast(msg);
                router.reload({
                    only: ['tablesData', 'shiftInfo', 'completedHistory'],
                });
            })
            .catch((err) => {
                toast(
                    err.response?.data?.error ||
                        err.response?.data?.message ||
                        'Lỗi xử lý thanh toán.',
                    'error',
                );
            })
            .finally(() => {
                isPaying.value = false;
            });
    };

    return {
        showPaymentModal,
        paymentMethod,
        cashReceived,
        searchCustomerPhone,
        isSearchingCustomer,
        foundCustomer,
        loyaltyPointsToRedeem,
        voucherCode,
        isApplyingVoucher,
        isCheckingVoucher,
        voucherPreview,
        previewVoucher,
        bypassRequired,
        bypassMessage,
        bypassCode,
        appliedVoucherName,
        availableVouchers,
        isLoadingVouchers,
        isPaying,
        paymentMethods,
        cashDenominations,
        changeAmount,
        multiPayments,
        multiTotalPaid,
        multiRemainingBalance,
        addMultiPayment,
        removeMultiPayment,
        searchCustomer,
        clearCustomerSelection,
        applyVoucher,
        openPayment,
        processPayment,
    };
}
