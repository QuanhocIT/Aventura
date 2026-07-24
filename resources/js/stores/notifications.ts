import { defineStore } from 'pinia';

// ── Types ────────────────────────────────────────────────────────────────────

export type NotificationSeverity = 'info' | 'warning' | 'error' | 'success';

export type NotificationType =
    | 'order_new'
    | 'order_updated'
    | 'order_cancelled'
    | 'qr_pending'
    | 'fraud_alert'
    | 'kitchen_late'
    | 'table_status'
    | 'shift_ending'
    | 'system';

export interface AppNotification {
    id: string;
    type: NotificationType;
    title: string;
    message: string;
    severity: NotificationSeverity;
    read: boolean;
    created_at: Date;
    /** Deep-link route để navigate khi click */
    route?: string;
    /** Dữ liệu gốc từ WebSocket event */
    payload?: Record<string, unknown>;
}

type PushInput = Omit<AppNotification, 'id' | 'read' | 'created_at'>;

const MAX_NOTIFICATIONS = 50;

// ── Store ────────────────────────────────────────────────────────────────────

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        notifications: [] as AppNotification[],
    }),

    getters: {
        unreadCount: (state) =>
            state.notifications.filter((n) => !n.read).length,

        unreadAlerts: (state) =>
            state.notifications.filter(
                (n) => !n.read && n.severity !== 'info' && n.severity !== 'success',
            ),

        latestUnread: (state): AppNotification | null =>
            state.notifications.find((n) => !n.read) ?? null,

        byType: (state) => (type: NotificationType) =>
            state.notifications.filter((n) => n.type === type),
    },

    actions: {
        /**
         * Thêm thông báo mới vào đầu danh sách.
         * Tự động trim xuống MAX_NOTIFICATIONS khi quá giới hạn.
         */
        push(notification: PushInput) {
            this.notifications.unshift({
                ...notification,
                id: crypto.randomUUID(),
                read: false,
                created_at: new Date(),
            });

            // Giới hạn bộ nhớ
            if (this.notifications.length > MAX_NOTIFICATIONS) {
                this.notifications.splice(MAX_NOTIFICATIONS);
            }
        },

        markRead(id: string) {
            const n = this.notifications.find((n) => n.id === id);
            if (n) n.read = true;
        },

        markAllRead() {
            this.notifications.forEach((n) => {
                n.read = true;
            });
        },

        dismiss(id: string) {
            this.notifications = this.notifications.filter((n) => n.id !== id);
        },

        clearAll() {
            this.notifications = [];
        },

        /**
         * Helper: push từ WebSocket order event
         */
        pushOrderEvent(
            eventType: 'created' | 'updated' | 'cancelled',
            orderNumber: string,
            tableName: string,
        ) {
            const configs: Record<typeof eventType, PushInput> = {
                created: {
                    type: 'order_new',
                    title: 'Đơn hàng mới',
                    message: `Đơn ${orderNumber} — Bàn ${tableName}`,
                    severity: 'info',
                    route: '/orders',
                },
                updated: {
                    type: 'order_updated',
                    title: 'Đơn hàng cập nhật',
                    message: `Đơn ${orderNumber} đã được cập nhật`,
                    severity: 'info',
                    route: '/orders',
                },
                cancelled: {
                    type: 'order_cancelled',
                    title: 'Đơn hàng bị hủy',
                    message: `Đơn ${orderNumber} — Bàn ${tableName} đã bị hủy`,
                    severity: 'warning',
                    route: '/orders',
                },
            };
            this.push(configs[eventType]);
        },

        /**
         * Helper: push cảnh báo bếp quá giờ SLA
         */
        pushKitchenLate(itemName: string, tableName: string, minutesLate: number) {
            this.push({
                type: 'kitchen_late',
                title: 'Món vượt SLA',
                message: `${itemName} (Bàn ${tableName}) trễ ${minutesLate} phút`,
                severity: 'error',
                route: '/kitchen',
            });
        },
    },
});
