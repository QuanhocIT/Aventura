import { router } from '@inertiajs/vue3';
import axios from 'axios';
import type { Ref } from 'vue';
import { ref, computed, watch } from 'vue';
import type { TableItem, OrderItem } from '../types';

export function useCashierTables(
    tablesData: () => TableItem[],
    cartItems: Ref<OrderItem[]>,
    cartNote: Ref<string>,
    isCartOpen: Ref<boolean>,
    isNotified: Ref<boolean>,
    toast: (msg: string, type?: 'success' | 'error') => void,
) {
    const activeTable = ref<TableItem | null>(null);
    const drawerStep = ref<'select' | 'confirm'>('select');
    const selectedArea = ref('all');
    const selectedStatus = ref<'all' | 'occupied' | 'available'>('all');
    const showSplitModal = ref(false);
    const splitTableId = ref<number | null>(null);
    const splitItems = ref<OrderItem[]>([]);
    const isSubmittingSplit = ref(false);
    const tableAction = ref<'move' | 'merge' | null>(null);
    const selectedMoveTableId = ref<number | null>(null);
    const selectedMergeTargetOrderId = ref<number | null>(null);
    const isSubmittingTableAction = ref(false);

    const areaList = computed(() => {
        const areas = new Set<string>();
        tablesData().forEach((t) => {
            if (t.area) {
                areas.add(t.area);
            }
        });

        return Array.from(areas);
    });

    const filteredTables = computed(() =>
        tablesData().filter((table) => {
            const matchesArea =
                selectedArea.value === 'all' ||
                table.area === selectedArea.value;
            const matchesStatus =
                selectedStatus.value === 'all' ||
                table.status === selectedStatus.value;

            return matchesArea && matchesStatus;
        }),
    );

    const availableTables = computed(() =>
        tablesData().filter(
            (table) =>
                table.id !== activeTable.value?.id &&
                table.status === 'available' &&
                !table.active_order,
        ),
    );

    const mergeCandidates = computed(() =>
        tablesData().filter((table) => {
            const order = table.active_order;

            return Boolean(
                table.id !== activeTable.value?.id &&
                    order &&
                    order.payment_status !== 'paid' &&
                    !['completed', 'cancelled'].includes(order.status),
            );
        }),
    );

    watch(
        () => tablesData(),
        (nextTables) => {
            if (!activeTable.value) {
                return;
            }

            const updatedTable = nextTables.find(
                (table) => table.id === activeTable.value?.id,
            );

            if (!updatedTable) {
                return;
            }

            activeTable.value = updatedTable;

            if (!updatedTable.active_order) {
                cartItems.value = [];
                isCartOpen.value = false;
                drawerStep.value = 'select';
                isNotified.value = false;

                return;
            }

            if (isCartOpen.value && drawerStep.value === 'confirm') {
                cartItems.value = updatedTable.active_order.items
                    .filter((item) => item.status !== 'cancelled')
                    .map((item) => ({ ...item }));
            }
        },
        { deep: true },
    );

    const openTableOrder = (table: TableItem) => {
        activeTable.value = table;
        isCartOpen.value = true;

        if (table.active_order) {
            cartItems.value = table.active_order.items
                .filter((item) => item.status !== 'cancelled')
                .map((item) => ({ ...item }));
            cartNote.value = table.active_order.note || '';
            drawerStep.value = 'confirm';
            isNotified.value = true;
        } else {
            cartItems.value = [];
            cartNote.value = '';
            drawerStep.value = 'select';
            isNotified.value = false;
        }
    };

    const openSplitOrder = () => {
        if (!activeTable.value?.active_order) {
            return;
        }

        const validItems = activeTable.value.active_order.items
            .filter((item) => item.status !== 'cancelled');

        const totalOriginalQty = validItems.reduce((sum, i) => sum + i.quantity, 0);

        if (totalOriginalQty <= 1) {
            toast('Đơn hàng chỉ có 1 món, không thể tách đơn. Đơn gốc phải còn lại tối thiểu 1 món.', 'error');

            return;
        }

        splitItems.value = validItems.map((i) => ({
            ...i,
            max_quantity: i.quantity,
            quantity: 0,
        }));
        splitTableId.value = null;
        showSplitModal.value = true;
    };

    const openMoveTable = () => {
        if (!activeTable.value?.active_order) {
            return;
        }

        selectedMoveTableId.value = null;
        tableAction.value = 'move';
    };

    const openMergeTable = () => {
        if (!activeTable.value?.active_order) {
            return;
        }

        selectedMergeTargetOrderId.value = null;
        tableAction.value = 'merge';
    };

    const closeTableAction = () => {
        tableAction.value = null;
        selectedMoveTableId.value = null;
        selectedMergeTargetOrderId.value = null;
    };

    const finishTableAction = (message: string) => {
        closeTableAction();
        isCartOpen.value = false;
        activeTable.value = null;
        drawerStep.value = 'select';
        toast(message);
    };

    const processMoveTable = () => {
        const order = activeTable.value?.active_order;

        if (!order || !selectedMoveTableId.value || isSubmittingTableAction.value) {
            return;
        }

        isSubmittingTableAction.value = true;
        router.post(
            `/orders/${order.id}/move-table`,
            { table_id: selectedMoveTableId.value },
            {
                preserveScroll: true,
                onSuccess: () => finishTableAction('Đã chuyển bàn thành công.'),
                onError: (errors: Record<string, string | string[]>) => {
                    const message = Object.values(errors)[0];
                    toast(
                        Array.isArray(message)
                            ? message[0]
                            : String(message || 'Không thể chuyển bàn.'),
                        'error',
                    );
                },
                onFinish: () => {
                    isSubmittingTableAction.value = false;
                },
            },
        );
    };

    const processMergeTable = () => {
        const order = activeTable.value?.active_order;

        if (
            !order ||
            !selectedMergeTargetOrderId.value ||
            isSubmittingTableAction.value
        ) {
            return;
        }

        isSubmittingTableAction.value = true;
        router.post(
            `/orders/${order.id}/merge`,
            { target_order_id: selectedMergeTargetOrderId.value },
            {
                preserveScroll: true,
                onSuccess: () => finishTableAction('Đã gộp hai bàn thành công.'),
                onError: (errors: Record<string, string | string[]>) => {
                    const message = Object.values(errors)[0];
                    toast(
                        Array.isArray(message)
                            ? message[0]
                            : String(message || 'Không thể gộp bàn.'),
                        'error',
                    );
                },
                onFinish: () => {
                    isSubmittingTableAction.value = false;
                },
            },
        );
    };

    const splitProjection = computed(() => {
        if (!activeTable.value?.active_order) {
            return null;
        }

        const origOrder = activeTable.value.active_order;
        const totalOriginalQty = splitItems.value.reduce(
            (sum, si) => sum + (si.max_quantity ?? si.quantity),
            0,
        );
        const totalSplitQty = splitItems.value.reduce(
            (sum, si) => sum + si.quantity,
            0,
        );
        const totalRemainingQty = totalOriginalQty - totalSplitQty;
        const hasItems = totalSplitQty > 0 && totalRemainingQty >= 1;

        let splitSubtotal = 0;
        splitItems.value.forEach((si) => {
            splitSubtotal += si.price * si.quantity;
        });

        const origSubtotal = Math.max(0, origOrder.subtotal - splitSubtotal);
        const ratio =
            origOrder.subtotal > 0
                ? origOrder.discount_amount / origOrder.subtotal
                : 0;

        const origDiscount = Math.round(origSubtotal * ratio);
        const splitDiscount = Math.round(splitSubtotal * ratio);

        const origTotal = Math.max(0, origSubtotal - origDiscount);
        const splitTotal = Math.max(0, splitSubtotal - splitDiscount);

        return {
            hasItems,
            totalOriginalQty,
            totalSplitQty,
            totalRemainingQty,
            origSubtotal,
            origDiscount,
            origTotal,
            splitSubtotal,
            splitDiscount,
            splitTotal,
        };
    });

    const processSplit = () => {
        if (
            !activeTable.value?.active_order ||
            !splitTableId.value ||
            isSubmittingSplit.value
        ) {
            return;
        }

        const totalOriginalQty = splitItems.value.reduce(
            (sum, si) => sum + (si.max_quantity ?? si.quantity),
            0,
        );
        const totalSplitQty = splitItems.value.reduce(
            (sum, si) => sum + si.quantity,
            0,
        );
        const totalRemainingQty = totalOriginalQty - totalSplitQty;

        if (totalSplitQty <= 0) {
            toast('Vui lòng chọn ít nhất 1 món để tách!', 'error');

            return;
        }

        if (totalRemainingQty < 1) {
            toast('Không thể tách toàn bộ đơn — Đơn gốc phải còn lại tối thiểu 1 món!', 'error');

            return;
        }

        const itemsToSplit = splitItems.value
            .filter((si) => si.quantity > 0)
            .map((si) => ({
                order_item_id: si.id,
                quantity: si.quantity,
            }));

        isSubmittingSplit.value = true;

        router.post(
            `/orders/${activeTable.value.active_order.id}/split`,
            {
                table_id: splitTableId.value,
                items: itemsToSplit,
            },
            {
                onSuccess: () => {
                    showSplitModal.value = false;
                    isCartOpen.value = false;
                    toast('Đã tách đơn sang bàn trống thành công!');
                },
                onFinish: () => {
                    isSubmittingSplit.value = false;
                },
            },
        );
    };

    const callPayment = async () => {
        const order = activeTable.value?.active_order;

        if (!order) {
            return;
        }

        try {
            const res = await axios.post(`/orders/${order.id}/request-payment`);

            if (res.data?.success) {
                if (activeTable.value) {
                    activeTable.value.is_payment_requested = true;

                    if (activeTable.value.active_order) {
                        activeTable.value.active_order.is_payment_requested = true;
                    }
                }

                toast(res.data.message || 'Đã gửi thông báo gọi thanh toán cho Thu ngân!');
                router.reload({ only: ['tablesData'] });
            }
        } catch (err: any) {
            const message =
                err.response?.data?.message || 'Không thể gửi yêu cầu thanh toán.';
            toast(String(message), 'error');
        }
    };

    return {
        activeTable,
        drawerStep,
        selectedArea,
        selectedStatus,
        areaList,
        filteredTables,
        openTableOrder,
        showSplitModal,
        splitTableId,
        splitItems,
        isSubmittingSplit,
        splitProjection,
        openSplitOrder,
        processSplit,
        tableAction,
        availableTables,
        mergeCandidates,
        selectedMoveTableId,
        selectedMergeTargetOrderId,
        isSubmittingTableAction,
        openMoveTable,
        openMergeTable,
        closeTableAction,
        processMoveTable,
        processMergeTable,
        callPayment,
    };
}
