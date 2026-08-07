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
        fallbackPollInterval = setInterval(() => {
            router.reload({
                only: ['qrOrders', 'tablesData', 'externalOrders', 'kitchenReadyItems'],
            });
        }, 6000);
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

    const startHeartbeat = () => {
        wsCheckInterval = setInterval(() => {
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
        }, 10000);
    };

    onMounted(() => {
        startHeartbeat();

        const restId = restaurantId();

        if (window.Echo && restId) {
            window.Echo.private(`restaurant.${restId}`)
                .listen('.OrderCreated', () => {
                    router.reload({
                        only: ['qrOrders', 'tablesData', 'externalOrders', 'kitchenReadyItems'],
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
            window.Echo.channel(`restaurant.${restId}`)
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

            window.Echo.channel(`kitchen.${restId}`)
                .listen('.kitchen.updated', () => {
                    router.reload({
                        only: ['kitchenReadyItems', 'tablesData'],
                    });
                });
        }
    });

    onUnmounted(() => {
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
