import { router } from '@inertiajs/vue3';
import axios from 'axios';
import type { Ref } from 'vue';
import { ref, computed } from 'vue';
import type { TableItem, CustomerItem } from '../types';

export function useCashierPayment(
    activeTable: Ref<TableItem | null>,
    isCartOpen: Ref<boolean>,
    toast: (msg: string, type?: 'success' | 'error') => void,
) {
    const showPaymentModal = ref(false);
    const paymentMethod = ref<
        'cash' | 'bank_transfer' | 'card' | 'ewallet' | 'debt'
    >('cash');
    const cashReceived = ref<number>(0);
    const searchCustomerPhone = ref('');
    const isSearchingCustomer = ref(false);
    const foundCustomer = ref<CustomerItem | null>(null);
    const loyaltyPointsToRedeem = ref<number>(0);
    const voucherCode = ref('');
    const isApplyingVoucher = ref(false);
    const bypassRequired = ref(false);
    const bypassMessage = ref('');
    const bypassCode = ref('');
    const appliedVoucherName = ref('');
    const isPaying = ref(false);

    const paymentMethods = [
        { id: 'cash' as const, label: '💵 Tiền mặt' },
        { id: 'bank_transfer' as const, label: '🏦 Chuyển khoản QR' },
        { id: 'card' as const, label: '💳 Thẻ ATM/POS' },
        { id: 'ewallet' as const, label: '📱 Ví điện tử' },
        { id: 'debt' as const, label: '📝 Ghi nợ VIP/B2B' },
    ];

    const cashDenominations = [50000, 100000, 200000, 500000];

    const changeAmount = computed(() => {
        const total = activeTable.value?.active_order?.total_amount ?? 0;

        return Math.max(0, cashReceived.value - total);
    });

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
            toast('Chưa thể thanh toán: đơn hàng vẫn còn món chưa được phục vụ.', 'error');

            return;
        }

        paymentMethod.value = 'cash';
        cashReceived.value = activeTable.value?.active_order?.total_amount ?? 0;
        foundCustomer.value = null;
        searchCustomerPhone.value = '';
        loyaltyPointsToRedeem.value = 0;
        voucherCode.value = '';
        bypassRequired.value = false;
        bypassMessage.value = '';
        bypassCode.value = '';
        appliedVoucherName.value = '';
        showPaymentModal.value = true;
    };

    const applyVoucher = () => {
        const orderId = activeTable.value?.active_order?.id;
        if (!voucherCode.value.trim() || !orderId) {
            toast('Vui lòng nhập mã khuyến mãi / voucher.', 'error');

            return;
        }

        isApplyingVoucher.value = true;
        axios
            .post('/api/promotions/apply', {
                order_id: orderId,
                code: voucherCode.value.trim().toUpperCase(),
                bypass_code: bypassCode.value ? bypassCode.value.trim() : undefined,
            })
            .then((res) => {
                const data = res.data;
                toast(data.message || 'Áp dụng mã khuyến mãi thành công!');
                bypassRequired.value = false;
                bypassMessage.value = '';
                bypassCode.value = '';
                appliedVoucherName.value = data.promotion_name || voucherCode.value.toUpperCase();
                voucherCode.value = '';

                if (activeTable.value?.active_order) {
                    if (data.discount_amount !== undefined) {
                        activeTable.value.active_order.discount_amount = data.discount_amount;
                    }
                    if (data.total_amount !== undefined) {
                        activeTable.value.active_order.total_amount = data.total_amount;
                        cashReceived.value = data.total_amount;
                    }
                }
                router.reload({ only: ['tablesData'] });
            })
            .catch((err) => {
                const data = err.response?.data;
                if (data?.status === 'requires_bypass') {
                    bypassRequired.value = true;
                    bypassMessage.value = data.message || 'Yêu cầu nhập mã phê duyệt của Quản lý.';
                    toast(data.message || 'Phát hiện cảnh báo: Yêu cầu mã duyệt của Quản lý.', 'error');
                } else {
                    toast(data?.message || data?.error || 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn.', 'error');
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
            toast('Chưa thể thanh toán: đơn hàng vẫn còn món chưa được phục vụ.', 'error');

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

        isPaying.value = true;
        axios
            .post(`/orders/${activeTable.value.active_order.id}/pay`, {
                payment_method: paymentMethod.value,
                cash_received: cashReceived.value,
                change_amount: changeAmount.value,
                customer_id: foundCustomer.value
                    ? foundCustomer.value.id
                    : null,
                redeem_points: redeemPoints > 0 ? redeemPoints : undefined,
            })
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
                    err.response?.data?.error || err.response?.data?.message || 'Lỗi xử lý thanh toán.',
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
        bypassRequired,
        bypassMessage,
        bypassCode,
        appliedVoucherName,
        isPaying,
        paymentMethods,
        cashDenominations,
        changeAmount,
        searchCustomer,
        clearCustomerSelection,
        applyVoucher,
        openPayment,
        processPayment,
    };
}
