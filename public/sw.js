/**
 * Service Worker Aventura — offline-first cho tài nguyên tĩnh.
 * - Asset build (JS/CSS có hash) : cache-first, bất biến nên an toàn tuyệt đối.
 * - Trang HTML/API              : luôn qua mạng (tránh stale CSRF/dữ liệu).
 * Hàng đợi đơn offline xử lý ở tầng ứng dụng (useOfflineQueue), không ở đây.
 */
const CACHE_NAME = 'aventura-assets-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Chỉ cache asset build (immutable — tên file chứa hash) cùng origin
    const isBuildAsset = url.origin === self.location.origin && url.pathname.startsWith('/build/');

    if (event.request.method !== 'GET' || !isBuildAsset) {
        return; // để trình duyệt xử lý bình thường
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(event.request);

            if (cached) {
                return cached;
            }

            const response = await fetch(event.request);

            if (response.ok) {
                cache.put(event.request, response.clone());
            }

            return response;
        })
    );
});
