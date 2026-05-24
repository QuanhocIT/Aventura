import './lib/echo';
import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

router.on('success', (event: { detail: { page: { props: Record<string, any> } } }) => {
    const announcement = (event.detail.page.props as Record<string, any>)?.flash?.success;

    if (announcement) {
        toast.success(String(announcement));
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title: string) => (title ? `${title} - ${appName}` : appName),
    layout: (name: string) => {
        switch (true) {
            case name === 'Welcome':
                return GuestLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
initializeFlashToast();
