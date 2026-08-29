import axios from 'axios';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type QueueMethod = 'post' | 'patch';

type PendingMutation = {
    id: string;
    method: QueueMethod;
    url: string;
    payload: Record<string, unknown>;
    created_at: number;
    attempts: number;
    status: 'pending' | 'syncing' | 'failed';
    error?: string;
};

const STORAGE_KEY = 'aventura:offline-mutations:v1';

function readQueue(): PendingMutation[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function useOfflineMutationQueue() {
    const queue = ref<PendingMutation[]>(readQueue());
    let isFlushing = false;

    const persist = () => {
        if (typeof window !== 'undefined') {
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify(queue.value),
            );
        }
    };

    const pendingCount = computed(() => queue.value.length);
    const failedCount = computed(
        () => queue.value.filter((item) => item.status === 'failed').length,
    );

    const enqueue = (
        url: string,
        payload: Record<string, unknown>,
        method: QueueMethod = 'post',
    ) => {
        queue.value.push({
            id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            method,
            url,
            payload,
            created_at: Date.now(),
            attempts: 0,
            status: 'pending',
        });
        persist();
    };

    const flush = async () => {
        if (
            isFlushing ||
            (typeof navigator !== 'undefined' && !navigator.onLine)
        ) {
            return;
        }

        isFlushing = true;

        for (const item of [...queue.value]) {
            if (item.status === 'failed') {
                continue;
            }

            item.status = 'syncing';
            item.attempts += 1;
            persist();

            try {
                await axios[item.method](item.url, item.payload);
                queue.value = queue.value.filter(
                    (queued) => queued.id !== item.id,
                );
                persist();
            } catch (error: any) {
                const status = error?.response?.status;
                item.status =
                    status && status !== 409 && status !== 429
                        ? 'failed'
                        : 'pending';
                item.error =
                    error?.response?.data?.message ?? 'Chưa thể đồng bộ';
                persist();

                if (!status) {
                    break;
                }
            }
        }

        isFlushing = false;
    };

    const retryFailed = () => {
        queue.value.forEach((item) => {
            item.status = 'pending';
            item.error = undefined;
        });
        persist();
        void flush();
    };

    onMounted(() => {
        window.addEventListener('online', flush);
        void flush();
    });
    onBeforeUnmount(() => window.removeEventListener('online', flush));

    return { queue, pendingCount, failedCount, enqueue, flush, retryFailed };
}
