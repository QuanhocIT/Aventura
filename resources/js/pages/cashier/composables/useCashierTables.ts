import { ref, computed, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { TableItem, OrderItem } from '../types';

export function useCashierTables(
    tablesData: () => TableItem[],
    cartItems: Ref<OrderItem[]>,
    cartNote: Ref<string>,
    isCartOpen: Ref<boolean>,
    isNotified: Ref<boolean>,
    toast: (msg: string, type?: 'success' | 'error') => void
) {
    const activeTable = ref<TableItem | null>(null);
    const drawerStep = ref<'select' | 'confirm'>('select');
    const selectedArea = ref('all');
    const showSplitModal = ref(false);
    const splitTableId = ref<number | null>(null);
    const splitItems = ref<OrderItem[]>([]);
    const isSubmittingSplit = ref(false);

    const areaList = computed(() => {
        const areas = new Set<string>();
        tablesData().forEach((t) => {
            if (t.area) areas.add(t.area);
        });
        return Array.from(areas);
    });

    const filteredTables = computed(() => {
        if (selectedArea.value === 'all') return tablesData();
        return tablesData().filter((t) => t.area === selectedArea.value);
    });

    const openTableOrder = (table: TableItem) => {
        activeTable.value = table;
        isCartOpen.value = true;
        if (table.active_order) {
            cartItems.value = table.active_order.items.map((item) => ({ ...item }));
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
        if (!activeTable.value?.active_order) return;
        splitItems.value = activeTable.value.active_order.items.map((i) => ({
            ...i,
            quantity: 1,
        }));
        splitTableId.value = null;
        showSplitModal.value = true;
    };

    const splitProjection = computed(() => {
        if (!activeTable.value?.active_order) return null;
        const origOrder = activeTable.value.active_order;
        const hasItems = splitItems.value.some((si) => si.quantity > 0);

        let splitSubtotal = 0;
        splitItems.value.forEach((si) => {
            splitSubtotal += si.price * si.quantity;
        });

        const origSubtotal = Math.max(0, origOrder.subtotal - splitSubtotal);
        const ratio = origOrder.subtotal > 0 ? origOrder.discount_amount / origOrder.subtotal : 0;

        const origDiscount = Math.round(origSubtotal * ratio);
        const splitDiscount = Math.round(splitSubtotal * ratio);

        const origTotal = Math.max(0, origSubtotal - origDiscount);
        const splitTotal = Math.max(0, splitSubtotal - splitDiscount);

        return {
            hasItems,
            origSubtotal,
            origDiscount,
            origTotal,
            splitSubtotal,
            splitDiscount,
            splitTotal,
        };
    });

    const processSplit = () => {
        if (!activeTable.value?.active_order || !splitTableId.value || isSubmittingSplit.value) {
            return;
        }

        const itemsToSplit = splitItems.value
            .filter((si) => si.quantity > 0)
            .map((si) => ({
                order_item_id: si.id,
                quantity: si.quantity,
            }));

        if (itemsToSplit.length === 0) {
            toast('Vui lòng chọn ít nhất 1 món để tách!', 'error');
            return;
        }

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
            }
        );
    };

    return {
        activeTable,
        drawerStep,
        selectedArea,
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
    };
}
