import '../css/app.css';

import ConfirmDialogHost from '@/components/ui/confirm/ConfirmDialogHost.vue';
import Toaster from '@/components/ui/toast/Toaster.vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';
import { usePermissions } from './composables/usePermissions';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(App, props), h(Toaster), h(ConfirmDialogHost)]),
        });

        // Add global properties for permissions and roles
        app.config.globalProperties.$can = (permission: string) => {
            const { can } = usePermissions();
            return can(permission);
        };

        app.config.globalProperties.$is = (role: string) => {
            const { is } = usePermissions();
            return is(role);
        };

        app.use(plugin).use(ZiggyVue).mount(el);
    },
    progress: {
        color: '#0000FF',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
