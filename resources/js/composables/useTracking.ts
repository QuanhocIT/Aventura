/**
 * Tích hợp Google Analytics 4 (gtag) + Facebook Pixel (fbq) cho storefront.
 * Script chỉ được nạp khi nhà hàng đã cấu hình ID trong Cài đặt → Tích hợp;
 * mọi hàm track đều no-op an toàn khi chưa bật tích hợp.
 */

interface TrackingConfig {
    ga_measurement_id?: string | null;
    fb_pixel_id?: string | null;
}

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...args: unknown[]) => void;
        fbq?: ((...args: unknown[]) => void) & { queue?: unknown[]; loaded?: boolean };
        _fbq?: unknown;
    }
}

let initialized = false;

export function useTracking(config: TrackingConfig) {
    const hasGa = Boolean(config.ga_measurement_id);
    const hasPixel = Boolean(config.fb_pixel_id);

    function init(): void {
        if (initialized || (!hasGa && !hasPixel)) {
return;
}

        initialized = true;

        if (hasGa) {
            const script = document.createElement('script');
            script.async = true;
            script.src = `https://www.googletagmanager.com/gtag/js?id=${config.ga_measurement_id}`;
            document.head.appendChild(script);

            window.dataLayer = window.dataLayer || [];
            window.gtag = function gtag(...args: unknown[]) {
                window.dataLayer!.push(args);
            };
            window.gtag('js', new Date());
            window.gtag('config', config.ga_measurement_id);
        }

        if (hasPixel) {
            if (!window.fbq) {
                const fbq: Window['fbq'] = ((...args: unknown[]) => {
                    fbq!.queue!.push(args);
                }) as NonNullable<Window['fbq']>;
                fbq.queue = [];
                fbq.loaded = true;
                window.fbq = fbq;
                window._fbq = fbq;

                const script = document.createElement('script');
                script.async = true;
                script.src = 'https://connect.facebook.net/en_US/fbevents.js';
                document.head.appendChild(script);
            }

            window.fbq!('init', config.fb_pixel_id);
            window.fbq!('track', 'PageView');
        }
    }

    function trackAddToCart(name: string, price: number): void {
        if (hasGa && window.gtag) {
            window.gtag('event', 'add_to_cart', {
                currency: 'VND',
                value: price,
                items: [{ item_name: name, price }],
            });
        }

        if (hasPixel && window.fbq) {
            window.fbq('track', 'AddToCart', { content_name: name, value: price, currency: 'VND' });
        }
    }

    function trackBeginCheckout(total: number): void {
        if (hasGa && window.gtag) {
            window.gtag('event', 'begin_checkout', { currency: 'VND', value: total });
        }

        if (hasPixel && window.fbq) {
            window.fbq('track', 'InitiateCheckout', { value: total, currency: 'VND' });
        }
    }

    function trackPurchase(orderNumber: string, total: number): void {
        if (hasGa && window.gtag) {
            window.gtag('event', 'purchase', {
                transaction_id: orderNumber,
                currency: 'VND',
                value: total,
            });
        }

        if (hasPixel && window.fbq) {
            window.fbq('track', 'Purchase', { value: total, currency: 'VND' });
        }
    }

    return { init, trackAddToCart, trackBeginCheckout, trackPurchase };
}
