import { defineStore } from 'pinia';

// ── Types ────────────────────────────────────────────────────────────────────

export type OrderStatus =
    | 'pending'
    | 'confirmed'
    | 'preparing'
    | 'completed'
    | 'cancelled';

export interface ActiveOrder {
    id: number;
    order_number: string;
    table_id: number | null;
    table_name: string;
    status: OrderStatus;
    total_amount: number;
    cashier_name: string;
    created_at: string;
    updated_at: string;
}

// ── Store ────────────────────────────────────────────────────────────────────

export const useOrdersStore = defineStore('orders', {
    state: () => ({
        activeOrders: [] as ActiveOrder[],
        lastSyncedAt: null as Date | null,
        isSyncing: false,
    }),

    getters: {
        pendingCount: (state) =>
            state.activeOrders.filter((o) => o.status === 'pending').length,

        preparingCount: (state) =>
            state.activeOrders.filter((o) => o.status === 'preparing').length,

        byTable: (state) => (tableId: number) =>
            state.activeOrders.filter((o) => o.table_id === tableId),

        byStatus: (state) => (status: OrderStatus) =>
            state.activeOrders.filter((o) => o.status === status),
    },

    actions: {
        setOrders(orders: ActiveOrder[]) {
            this.activeOrders = orders;
            this.lastSyncedAt = new Date();
        },

        addOrder(order: ActiveOrder) {
            const exists = this.activeOrders.find((o) => o.id === order.id);

            if (!exists) {
                this.activeOrders.unshift(order);
            }
        },

        updateOrderStatus(orderId: number, status: OrderStatus) {
            const order = this.activeOrders.find((o) => o.id === orderId);

            if (order) {
                order.status = status;
                order.updated_at = new Date().toISOString();
            }
        },

        removeOrder(orderId: number) {
            this.activeOrders = this.activeOrders.filter(
                (o) => o.id !== orderId,
            );
        },

        /**
         * Áp dụng WebSocket event từ kitchen/order channel —
         * gọi hàm này thay vì router.reload() để tránh full-page re-render.
         */
        applyRealtimeEvent(
            eventType: 'created' | 'updated' | 'cancelled',
            order: ActiveOrder,
        ) {
            switch (eventType) {
                case 'created':
                    this.addOrder(order);
                    break;
                case 'updated':
                    this.updateOrderStatus(order.id, order.status);
                    break;
                case 'cancelled':
                    this.removeOrder(order.id);
                    break;
            }
        },
    },
});
