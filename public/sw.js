/**
 * Service Worker Aventura — offline-first cho tài nguyên tĩnh.
 * - Asset build (JS/CSS có hash) : cache-first, bất biến nên an toàn tuyệt đối.
 * - Hình ảnh tĩnh (.webp, .svg, .png, .jpg) & fonts : stale-while-revalidate / cache-first.
 * - Trang HTML / API             : luôn qua mạng (tránh stale CSRF/dữ liệu).
 */
const CACHE_NAME = 'aventura-assets-v2';

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

    if (event.request.method !== 'GET') {
        return;
    }

    const isSameOrigin = url.origin === self.location.origin;
    const isBuildAsset = isSameOrigin && url.pathname.startsWith('/build/');
    const isStaticImage = isSameOrigin && (
        url.pathname.startsWith('/images/') ||
        url.pathname.match(/\.(webp|svg|png|jpg|jpeg|ico)$/i)
    );
    const isExternalFont = url.hostname.includes('fonts.gstatic.com') || url.hostname.includes('fonts.googleapis.com');

    // Chỉ cache asset tĩnh (build assets, images, fonts)
    if (!isBuildAsset && !isStaticImage && !isExternalFont) {
        return;
    }

    event.respondWith(
        caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(event.request);

            if (cached) {
                // Với ảnh tĩnh: trả về bản cache ngay, đồng thời cập nhật ngầm nếu có thay đổi (Stale-While-Revalidate)
                if (isStaticImage) {
                    fetch(event.request)
                        .then((networkRes) => {
                            if (networkRes.ok) {
                                cache.put(event.request, networkRes);
                            }
                        })
                        .catch(() => {});
                }
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
