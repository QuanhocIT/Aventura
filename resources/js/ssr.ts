import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, type DefineComponent } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import BareLayout from '@/layouts/BareLayout.vue';
import GuestLayout from '@/layouts/GuestLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title: string) => (title ? `${title} - ${appName}` : appName),
        resolve: (name: string) => {
            const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true });
            const pageModule = pages[`./pages/${name}.vue`].default;
            const page = pageModule;

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
        },
        setup({ App, props, plugin }: any) {
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin);
        },
    })
);
