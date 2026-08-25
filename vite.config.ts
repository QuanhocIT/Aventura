import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        host: '127.0.0.1',
    },
    build: {
        cssMinify: 'lightningcss',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/vue') || id.includes('node_modules/@vue')) {
                        return 'vue-core';
                    }
                    if (id.includes('node_modules/@inertiajs')) {
                        return 'inertia';
                    }
                    if (id.includes('node_modules/lucide')) {
                        return 'icons';
                    }
                    // Leaflet chỉ được dynamic-import ở các trang bản đồ — tách chunk
                    // riêng để không bị gộp vào vendor tải ngay từ trang đầu tiên.
                    if (id.includes('node_modules/leaflet')) {
                        return 'leaflet';
                    }
                    if (id.includes('node_modules')) {
                        return 'vendor';
                    }
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
