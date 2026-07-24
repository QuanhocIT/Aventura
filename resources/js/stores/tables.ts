import { defineStore } from 'pinia';

// ── Types ────────────────────────────────────────────────────────────────────

export type TableStatus =
    | 'available'
    | 'occupied'
    | 'reserved'
    | 'cleaning';

export interface RestaurantTable {
    id: number;
    name: string;
    area_name: string;
    capacity: number;
    status: TableStatus;
    current_order_id: number | null;
    occupied_since: string | null;
}

// ── Store ────────────────────────────────────────────────────────────────────

export const useTablesStore = defineStore('tables', {
    state: () => ({
        tables: [] as RestaurantTable[],
        lastUpdated: null as Date | null,
    }),

    getters: {
        availableTables: (state) =>
            state.tables.filter((t) => t.status === 'available'),

        occupiedTables: (state) =>
            state.tables.filter((t) => t.status === 'occupied'),

        reservedTables: (state) =>
            state.tables.filter((t) => t.status === 'reserved'),

        availableCount: (state) =>
            state.tables.filter((t) => t.status === 'available').length,

        byArea: (state) => (areaName: string) =>
            state.tables.filter((t) => t.area_name === areaName),

        byId: (state) => (tableId: number) =>
            state.tables.find((t) => t.id === tableId) ?? null,

        areas: (state): string[] =>
            [...new Set(state.tables.map((t) => t.area_name))],
    },

    actions: {
        setTables(tables: RestaurantTable[]) {
            this.tables = tables;
            this.lastUpdated = new Date();
        },

        updateTableStatus(
            tableId: number,
            status: TableStatus,
            currentOrderId?: number | null,
        ) {
            const table = this.tables.find((t) => t.id === tableId);
            if (table) {
                table.status = status;
                if (currentOrderId !== undefined) {
                    table.current_order_id = currentOrderId;
                }
                if (status === 'occupied' && !table.occupied_since) {
                    table.occupied_since = new Date().toISOString();
                }
                if (status === 'available') {
                    table.occupied_since = null;
                    table.current_order_id = null;
                }
                this.lastUpdated = new Date();
            }
        },
    },
});
