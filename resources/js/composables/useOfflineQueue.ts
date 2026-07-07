import axios from 'axios';
import { ref } from 'vue';

/**
 * Hàng đợi đơn offline: khi mất mạng, request được lưu vào localStorage và
 * tự động gửi lại khi có mạng trở lại (sự kiện 'online' hoặc lần tải trang sau).
 *
 *   const { postWithQueue, pendingCount, isOnline } = useOfflineQueue();
 *   const result = await postWithQueue('/api/online/x/checkout', payload);
 *   if (result.queued) { ...hiện thông báo "sẽ gửi khi có mạng"... }
 */

const STORAGE_KEY = 'aventura_offline_queue';

type QueuedRequest = { id: string; url: string; payload: Record<string, unknown>; queuedAt: string };

const pendingCount = ref(0);
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
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

/** Gửi lại lần lượt các request đã xếp hàng; giữ lại những cái vẫn thất bại vì mạng. */
async function flushQueue(onFlushed?: (item: QueuedRequest, response: unknown) => void): Promise<void> {
    const queue = readQueue();

    if (queue.length === 0) {
        return;
    }

    const remaining: QueuedRequest[] = [];

    for (const item of queue) {
        try {
            const { data } = await axios.post(item.url, item.payload);
            onFlushed?.(item, data);
        } catch (error: any) {
            if (!error.response) {
                // Vẫn mất mạng — giữ lại, dừng flush
                remaining.push(item, ...queue.slice(queue.indexOf(item) + 1));
                break;
            }
            // Server từ chối (4xx/5xx) — bỏ khỏi hàng đợi, không retry vô hạn
        }
    }

    writeQueue(remaining);
}

export function useOfflineQueue(onFlushed?: (item: QueuedRequest, response: unknown) => void) {
    if (!initialized && typeof window !== 'undefined') {
        initialized = true;
        pendingCount.value = readQueue().length;

        window.addEventListener('online', () => {
            isOnline.value = true;
            flushQueue(onFlushed);
        });

        window.addEventListener('offline', () => {
            isOnline.value = false;
        });

        // Gửi nốt hàng đợi còn sót từ phiên trước
        if (navigator.onLine) {
            flushQueue(onFlushed);
        }
    }

    /** POST có hàng đợi: lỗi mạng → xếp hàng, lỗi server → ném lại cho caller xử lý. */
    async function postWithQueue(url: string, payload: Record<string, unknown>): Promise<{ queued: boolean; data?: any }> {
        try {
            const { data } = await axios.post(url, payload);

            return { queued: false, data };
        } catch (error: any) {
            if (error.response) {
                throw error; // lỗi nghiệp vụ từ server — không phải việc của hàng đợi
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

    return { postWithQueue, flushQueue, pendingCount, isOnline };
}
