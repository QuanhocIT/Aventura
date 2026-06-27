import './lib/echo';
import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h  } from 'vue';
import type {DefineComponent} from 'vue';
import { toast } from 'vue-sonner';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';
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
    resolve: (name: string) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

        return resolvePageComponent(`./pages/${name}.vue`, pages).then((module) => {
            const page = module.default;

            // Fix project-wide layout metadata object bug (converts plain layout props objects into real Inertia layouts)
            if (page.layout && typeof page.layout === 'object' && !Array.isArray(page.layout) && !page.layout.render && !page.layout.setup && !page.layout.__file) {
                const layoutProps = page.layout;

                if (name.startsWith('auth/')) {
                    page.layout = [AuthLayout, layoutProps];
                } else if (name.startsWith('settings/')) {
                    page.layout = [AppLayout, SettingsLayout];

                    if (layoutProps.breadcrumbs) {
                        page.layoutProps = { breadcrumbs: layoutProps.breadcrumbs };
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
        });
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


