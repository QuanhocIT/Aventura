import './lib/echo';
import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';

import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeAutoTablePagination } from '@/lib/autoTablePagination';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title: string) => (title ? `${title} - ${appName}` : appName),
    resolve: (name: string) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

        return resolvePageComponent(`./pages/${name}.vue`, pages).then(
            (module) => {
                const page = module.default;

                // Fix project-wide layout metadata object bug (converts plain layout props objects into real Inertia layouts)
                if (
                    page.layout &&
                    typeof page.layout === 'object' &&
                    !Array.isArray(page.layout) &&
                    !page.layout.render &&
                    !page.layout.setup &&
                    !page.layout.__file
                ) {
                    const layoutProps = page.layout;

                    if (name.startsWith('auth/')) {
                        page.layout = [AuthLayout, layoutProps];
                    } else if (name.startsWith('settings/')) {
                        page.layout = [AppLayout, SettingsLayout];

                        if (layoutProps.breadcrumbs) {
                            page.layoutProps = {
                                breadcrumbs: layoutProps.breadcrumbs,
                            };
                        }
                    }
                }

                if (page.layout === undefined) {
                    switch (true) {
                        case name === 'Welcome':
                            page.layout = GuestLayout;
                            break;
                        case name === 'Khach':
                        case name.startsWith('customers/'):
                        case name === 'auth/Login':
                        case name === 'auth/Register':
                        case name === 'auth/ChooseRestaurant':
                        case name === 'auth/TwoFactorChallenge':
                        case name === 'auth/ConfirmPassword':
                        case name === 'super-admin/security/ConfirmTwoFactor':
                            page.layout = BareLayout;
                            break;
                        case name.startsWith('auth/'):
                            page.layout = AuthLayout;
                            break;
                        case name.startsWith('settings/'):
                            page.layout = [AppLayout, SettingsLayout];
                            break;
                        default:
                            page.layout = AppLayout;
                            break;
                    }
                }

                return page;
            },
        );
    },
    setup({ el, App, props, plugin }: any) {
        const pinia = createPinia();
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#E97316',
    },
});

initializeTheme();
initializeFlashToast();
initializeAutoTablePagination();

// PWA: cache asset tĩnh qua Service Worker (offline-first cho JS/CSS build)
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Không hỗ trợ / bị chặn — app vẫn hoạt động bình thường
        });
    });
}

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (
            error.response &&
            error.response.status === 403 &&
            error.response.data &&
            error.response.data.error === 'SHIFT_EXPIRED'
        ) {
            window.dispatchEvent(
                new CustomEvent('shift-expired', {
                    detail: error.response.data,
                }),
            );
        }

        return Promise.reject(error);
    },
);

router.on('invalid', (event: any) => {
    const response = event.detail.response;

    if (response && response.status === 403) {
        let data = response.data;

        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch {
                // giữ nguyên chuỗi nếu không phải JSON
            }
        }

        if (data && data.error === 'SHIFT_EXPIRED') {
            event.preventDefault();
            window.dispatchEvent(
                new CustomEvent('shift-expired', { detail: data }),
            );
        }
    }
});

// Global Event Listener: Tự động kích hoạt Bảng chọn Lịch cho tất cả các ô input[type="date"] và icon cuốn lịch toàn hệ thống
if (typeof window !== 'undefined') {
    document.addEventListener('click', (e: MouseEvent) => {
        const target = e.target as HTMLElement | null;

        if (!target) {
            return;
        }

        // 1. Nhấp trực tiếp vào input[type="date"]
        if (target instanceof HTMLInputElement && target.type === 'date') {
            if (typeof target.showPicker === 'function') {
                try {
                    target.showPicker();
                } catch {
                    // Đã mở hoặc trình duyệt chặn popup liên tiếp
                }
            }

            return;
        }

        // 2. Nhấp vào icon cuốn lịch hoặc vùng container chứa ô input[type="date"]
        const parent = target.closest('.relative, div, label');

        if (parent) {
            const dateInput = parent.querySelector(
                'input[type="date"]',
            ) as HTMLInputElement | null;

            if (dateInput && typeof dateInput.showPicker === 'function') {
                try {
                    dateInput.showPicker();
                } catch {
                    // Đã mở
                }
            }
        }
    });
}
