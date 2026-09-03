import { router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

export function useCashierRealtime(restaurantId: () => number | undefined) {
    const wsConnected = ref(true);
    const pollingActive = ref(false);
    let wsCheckInterval: ReturnType<typeof setInterval> | null = null;
    let fallbackPollInterval: ReturnType<typeof setInterval> | null = null;

    const triggerPollingFallback = () => {
        if (pollingActive.value) {
            return;
        }

        pollingActive.value = true;

        const reloadData = () => {
            router.reload({
                only: [
                    'qrOrders',
                    'tablesData',
                    'externalOrders',
                    'kitchenReadyItems',
                ],
                preserveScroll: true,
                preserveState: true,
            });
        };

        // Kích hoạt ngay sau 500ms, sau đó định kỳ 2.5s/lần
        setTimeout(reloadData, 500);
        fallbackPollInterval = setInterval(reloadData, 2500);
    };

    const stopPollingFallback = () => {
        if (!pollingActive.value) {
            return;
        }

        pollingActive.value = false;

        if (fallbackPollInterval) {
            clearInterval(fallbackPollInterval);
            fallbackPollInterval = null;
        }
    };

    const checkConnection = () => {
        if (
            window.Echo &&
            window.Echo.connector &&
            window.Echo.connector.pusher
        ) {
            const state = window.Echo.connector.pusher.connection.state;
            wsConnected.value = state === 'connected';

            if (state === 'disconnected' || state === 'failed') {
                triggerPollingFallback();
            } else if (state === 'connected') {
                stopPollingFallback();
            }
        } else {
            wsConnected.value = false;
            triggerPollingFallback();
        }
    };

    const startHeartbeat = () => {
        checkConnection();
        wsCheckInterval = setInterval(checkConnection, 5000);
    };

    const onQrOrdersUpdated = () => {
        router.reload({
            only: ['qrOrders', 'tablesData'],
            preserveScroll: true,
            preserveState: true,
        });
    };

    onMounted(() => {
        window.addEventListener('qr-orders-updated', onQrOrdersUpdated);
        startHeartbeat();

        const restId = restaurantId();

        if (window.Echo && restId) {
            window.Echo.private(`restaurant.${restId}`)
                .listen('.OrderCreated', () => {
                    router.reload({
                        only: [
                            'qrOrders',
                            'tablesData',
                            'externalOrders',
                            'kitchenReadyItems',
                        ],
                    });
                })
                .listen('.OrderStatusUpdated', () => {
                    router.reload({
                        only: [
                            'tablesData',
                            'completedHistory',
                            'externalOrders',
                            'kitchenReadyItems',
                        ],
                    });
                });

            // QR temporary orders use a public restaurant channel so the cashier
            // screen refreshes when a customer submits, confirms a revision, or
            // when staff rejects/approves the request.
            window.Echo.private(`restaurant.${restId}`)
                .listen('.temporary_order.created', () => {
                    router.reload({
                        only: ['qrOrders', 'tablesData'],
                    });
                })
                .listen('.temporary_order.updated', () => {
                    router.reload({
                        only: ['qrOrders', 'tablesData'],
                    });
                });

            window.Echo.private(`kitchen.${restId}`).listen(
                '.kitchen.updated',
                () => {
                    router.reload({
                        only: ['kitchenReadyItems', 'tablesData'],
                    });
                },
            );
        }
    });

    onUnmounted(() => {
        window.removeEventListener('qr-orders-updated', onQrOrdersUpdated);

        if (wsCheckInterval) {
            clearInterval(wsCheckInterval);
        }

        stopPollingFallback();
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
