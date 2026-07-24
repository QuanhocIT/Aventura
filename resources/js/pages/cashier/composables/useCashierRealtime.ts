import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useCashierRealtime(restaurantId: () => number | undefined) {
    const wsConnected = ref(true);
    const pollingActive = ref(false);
    let wsCheckInterval: ReturnType<typeof setInterval> | null = null;
    let fallbackPollInterval: ReturnType<typeof setInterval> | null = null;

    const triggerPollingFallback = () => {
        if (pollingActive.value) return;
        pollingActive.value = true;
        fallbackPollInterval = setInterval(() => {
            router.reload({ only: ['qrOrders', 'tablesData', 'externalOrders'] });
        }, 8000);
    };

    const stopPollingFallback = () => {
        if (!pollingActive.value) return;
        pollingActive.value = false;
        if (fallbackPollInterval) {
            clearInterval(fallbackPollInterval);
            fallbackPollInterval = null;
        }
    };

    const startHeartbeat = () => {
        wsCheckInterval = setInterval(() => {
            if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                const state = window.Echo.connector.pusher.connection.state;
                wsConnected.value = (state === 'connected');
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
                    router.reload({ only: ['qrOrders', 'tablesData', 'externalOrders'] });
                })
                .listen('.OrderStatusUpdated', () => {
                    router.reload({ only: ['tablesData', 'completedHistory', 'externalOrders'] });
                });
        }
    });

    onUnmounted(() => {
        if (wsCheckInterval) clearInterval(wsCheckInterval);
        stopPollingFallback();
        const restId = restaurantId();
        if (window.Echo && restId) {
            window.Echo.leave(`restaurant.${restId}`);
        }
    });

    return {
        wsConnected,
        pollingActive,
    };
}
