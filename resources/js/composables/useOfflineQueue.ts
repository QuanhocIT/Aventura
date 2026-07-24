import axios from 'axios';
import { ref } from 'vue';

/**
 * Hàng đợi đơn offline: khi mất mạng, request được lưu vào localStorage và
 * tự động gửi lại khi có mạng trở lại (sự kiện 'online' hoặc lần tải trang sau).
 *
 * Hỗ trợ phát hiện xung đột tồn kho (HTTP 409) và gọi callback giải quyết.
 */

const STORAGE_KEY = 'aventura_offline_queue';

export type QueuedRequest = {
    id: string;
    url: string;
    payload: Record<string, unknown>;
    queuedAt: string;
};

export type StockConflict = {
    product_name: string;
    requested: number;
    available_stock: number;
};

export type ConflictResponse = {
    item: QueuedRequest;
    conflicts: StockConflict[];
};

const pendingCount = ref(0);
const isOnline = ref(
    typeof navigator !== 'undefined' ? navigator.onLine : true,
);
const activeConflicts = ref<ConflictResponse[]>([]);
let initialized = false;

function readQueue(): QueuedRequest[] {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function writeQueue(queue: QueuedRequest[]): void {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
    pendingCount.value = queue.length;
}

/** Gửi lại lần lượt các request đã xếp hàng; giữ lại những cái vẫn thất bại vì mạng hoặc bị conflict. */
async function flushQueue(
    onFlushed?: (item: QueuedRequest, response: unknown) => void,
    onConflict?: (conflict: ConflictResponse) => void,
): Promise<void> {
    const queue = readQueue();

    if (queue.length === 0) {
        return;
    }

    const remaining: QueuedRequest[] = [];

    for (const item of queue) {
        try {
            const { data } = await axios.post(item.url, {
                ...item.payload,
                conflict_check: true,
            });
            onFlushed?.(item, data);
        } catch (error: any) {
            if (!error.response) {
                // Vẫn mất mạng — giữ lại, dừng flush
                remaining.push(item, ...queue.slice(queue.indexOf(item) + 1));
                break;
            }

            if (error.response.status === 409) {
                // CONFLICT (Xung đột tồn kho): lưu vào activeConflicts để Modal xử lý
                const conflictData: ConflictResponse = {
                    item,
                    conflicts: error.response.data.conflicts ?? [],
                };
                activeConflicts.value.push(conflictData);
                onConflict?.(conflictData);
                // Giữ lại item trong queue để người dùng quyết định qua Modal
                remaining.push(item);
                continue;
            }

            // Server từ chối khác (4xx/5xx) — bỏ khỏi hàng đợi, không retry vô hạn
        }
    }

    writeQueue(remaining);
}

export function useOfflineQueue(
    onFlushed?: (item: QueuedRequest, response: unknown) => void,
    onConflict?: (conflict: ConflictResponse) => void,
) {
    if (!initialized && typeof window !== 'undefined') {
        initialized = true;
        pendingCount.value = readQueue().length;

        window.addEventListener('online', () => {
            isOnline.value = true;
            flushQueue(onFlushed, onConflict);
        });

        window.addEventListener('offline', () => {
            isOnline.value = false;
        });

        // Gửi nốt hàng đợi còn sót từ phiên trước
        if (navigator.onLine) {
            flushQueue(onFlushed, onConflict);
        }
    }

    /** POST có hàng đợi: lỗi mạng → xếp hàng, lỗi server → ném lại cho caller xử lý. */
    async function postWithQueue(
        url: string,
        payload: Record<string, unknown>,
    ): Promise<{ queued: boolean; data?: any }> {
        try {
            const { data } = await axios.post(url, payload);

            return { queued: false, data };
        } catch (error: any) {
            if (error.response) {
                throw error;
            }

            const queue = readQueue();
            queue.push({
                id: `q_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                url,
                payload,
                queuedAt: new Date().toISOString(),
            });
            writeQueue(queue);

            return { queued: true };
        }
    }

    function removeQueuedItem(id: string) {
        const queue = readQueue().filter((i) => i.id !== id);
        writeQueue(queue);
        activeConflicts.value = activeConflicts.value.filter((c) => c.item.id !== id);
    }

    return {
        postWithQueue,
        flushQueue,
        removeQueuedItem,
        pendingCount,
        isOnline,
        activeConflicts,
    };
}
