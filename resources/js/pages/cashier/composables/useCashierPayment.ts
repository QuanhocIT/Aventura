import { ref, computed, Ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { TableItem, CustomerItem } from '../types';

export function useCashierPayment(
    activeTable: Ref<TableItem | null>,
    isCartOpen: Ref<boolean>,
    toast: (msg: string, type?: 'success' | 'error') => void
) {
    const showPaymentModal = ref(false);
    const paymentMethod = ref<'cash' | 'bank_transfer' | 'card' | 'ewallet' | 'debt'>('cash');
    const cashReceived = ref<number>(0);
    const searchCustomerPhone = ref('');
    const isSearchingCustomer = ref(false);
    const foundCustomer = ref<CustomerItem | null>(null);
    const loyaltyPointsToRedeem = ref<number>(0);
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
        if (!searchCustomerPhone.value.trim()) return;
        isSearchingCustomer.value = true;
        axios
            .get('/api/customers/search', {
                params: { phone: searchCustomerPhone.value.trim() },
            })
            .then((res) => {
                if (res.data.customer) {
                    foundCustomer.value = res.data.customer;
                    toast(`Đã tìm thấy khách hàng: ${res.data.customer.full_name}`);
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

    const openPayment = () => {
        paymentMethod.value = 'cash';
        cashReceived.value = activeTable.value?.active_order?.total_amount ?? 0;
        foundCustomer.value = null;
        searchCustomerPhone.value = '';
        loyaltyPointsToRedeem.value = 0;
        showPaymentModal.value = true;
    };

    const processPayment = () => {
        if (!activeTable.value?.active_order || isPaying.value) return;

        const redeemPoints = Math.max(0, Math.floor(loyaltyPointsToRedeem.value ?? 0));
        if (redeemPoints > 0 && foundCustomer.value) {
            const availablePoints = foundCustomer.value.loyalty_points ?? 0;
            if (redeemPoints > availablePoints) {
                toast(`Không đủ điểm: Khách chỉ có ${availablePoints} điểm.`, 'error');
                return;
            }
        }

        isPaying.value = true;
        axios
            .post(`/orders/${activeTable.value.active_order.id}/pay`, {
                payment_method: paymentMethod.value,
                cash_received: cashReceived.value,
                change_amount: changeAmount.value,
                customer_id: foundCustomer.value ? foundCustomer.value.id : null,
                redeem_points: redeemPoints > 0 ? redeemPoints : undefined,
            })
            .then(() => {
                showPaymentModal.value = false;
                isCartOpen.value = false;
                loyaltyPointsToRedeem.value = 0;
                const msg = redeemPoints > 0
                    ? `Thanh toán thành công! Đã đổi ${redeemPoints} điểm loyalty.`
                    : 'Đã thanh toán hóa đơn thành công. Bàn đã chuyển sang trạng thái trống.';
                toast(msg);
                router.reload({
                    only: ['tablesData', 'shiftInfo', 'completedHistory'],
                });
            })
            .catch((err) => {
                toast(
                    err.response?.data?.message || 'Lỗi xử lý thanh toán.',
                    'error'
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
        isPaying,
        paymentMethods,
        cashDenominations,
        changeAmount,
        searchCustomer,
        clearCustomerSelection,
        openPayment,
        processPayment,
    };
}
