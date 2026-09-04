import { router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

export function useCashierRealtime(restaurantId: () => number | undefined) {
    const wsConnected = ref(false);
    const pollingActive = ref(true);
    let wsCheckInterval: ReturnType<typeof setInterval> | null = null;
    let pollInterval: ReturnType<typeof setInterval> | null = null;
    let isReloading = false;

    // Keys cần reload cho bảng điều khiển Thu ngân
    const cashierDataKeys = [
        'qrOrders',
        'tablesData',
        'externalOrders',
        'kitchenReadyItems',
        'shiftInfo',
    ];

    const reloadData = (onlyKeys: string[] = cashierDataKeys) => {
        if (isReloading) {
            return;
        }

        isReloading = true;
        router.reload({
            only: onlyKeys,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isReloading = false;
            },
        });
    };

    const configurePolling = (intervalMs: number) => {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        pollingActive.value = true;
        pollInterval = setInterval(() => {
            reloadData();
        }, intervalMs);
    };

    const checkConnection = () => {
        if (
            window.Echo &&
            window.Echo.connector &&
            window.Echo.connector.pusher
        ) {
            const state = window.Echo.connector.pusher.connection?.state;
            const isConnected = state === 'connected';
            wsConnected.value = isConnected;

            if (isConnected) {
                // Khi WebSocket hoạt động: duy trì nhịp heartbeat 8s/lần để Keep-Alive Session
                // tránh bị hết hạn phiên (419 Expired) sau 120 phút và làm lưới an toàn
                configurePolling(8000);
            } else {
                // Khi WebSocket chưa/không kết nối: polling nhanh 3s/lần để dữ liệu luôn tức thời
                configurePolling(3000);
            }
        } else {
            wsConnected.value = false;
            configurePolling(3000);
        }
    };

    const startHeartbeat = () => {
        checkConnection();
        wsCheckInterval = setInterval(checkConnection, 5000);
    };

    const onQrOrdersUpdated = () => {
        reloadData(['qrOrders', 'tablesData']);
    };

    onMounted(() => {
        window.addEventListener('qr-orders-updated', onQrOrdersUpdated);
        startHeartbeat();

        const restId = restaurantId();

        if (window.Echo && restId) {
            // Lắng nghe kênh nhà hàng (dành cho nhân viên/thu ngân)
            window.Echo.private(`restaurant.${restId}`)
                .listen('.temporary_order.created', () => {
                    reloadData(['qrOrders', 'tablesData']);
                })
                .listen('.temporary_order.updated', () => {
                    reloadData(['qrOrders', 'tablesData']);
                })
                .listen('.order.paid', () => {
                    reloadData([
                        'tablesData',
                        'completedHistory',
                        'shiftInfo',
                        'kitchenReadyItems',
                    ]);
                })
                .listen('.order.status_updated', () => {
                    reloadData([
                        'tablesData',
                        'externalOrders',
                        'kitchenReadyItems',
                        'shiftInfo',
                    ]);
                })
                .listen('.kitchen.waiter_called', () => {
                    reloadData(['kitchenReadyItems', 'tablesData']);
                })
                .listen('.kitchen.item_cancelled', () => {
                    reloadData(['kitchenReadyItems', 'tablesData']);
                })
                .listen('.payment.requested', () => {
                    reloadData(['tablesData']);
                });

            // Lắng nghe kênh bếp
            window.Echo.private(`kitchen.${restId}`)
                .listen('.kitchen.updated', () => {
                    reloadData(['kitchenReadyItems', 'tablesData']);
                })
                .listen('.kitchen.item_cancelled', () => {
                    reloadData(['kitchenReadyItems', 'tablesData']);
                });
        }
    });

    onUnmounted(() => {
        window.removeEventListener('qr-orders-updated', onQrOrdersUpdated);

        if (wsCheckInterval) {
            clearInterval(wsCheckInterval);
            wsCheckInterval = null;
        }

        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }

        const restId = restaurantId();

        if (window.Echo && restId) {
            window.Echo.leave(`restaurant.${restId}`);
            window.Echo.leave(`kitchen.${restId}`);
        }
    });

    return {
        wsConnected,
        pollingActive,
    };
}
