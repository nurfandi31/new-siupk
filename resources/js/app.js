import './bootstrap';
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import AppOfflineBanner from './Components/AppOfflineBanner.vue';
import DesktopSplashScreen from './Components/DesktopSplashScreen.vue';
import DesktopTitleBar from './Components/DesktopTitleBar.vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/index.esm.js';

if (typeof window !== 'undefined' && window.desktopAPI?.onNavigate) {
    window.desktopAPI.onNavigate((url) => {
        if (url && typeof url === 'string') {
            router.visit(url);
        }
    });
}

createInertiaApp({
    title: (title) => title ? `${title} - siupk Next` : 'siupk Next',
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(DesktopTitleBar), h(DesktopSplashScreen), h(AppOfflineBanner), h(App, props)]),
        });
        app.use(plugin);
        app.use(ZiggyVue);
        app.mount(el);

        router.on('exception', (event) => {
            const err = event.detail?.exception;
            if (err && (err.message === 'Network Error' || !navigator.onLine)) {
                event.preventDefault();
                window.dispatchEvent(
                    new CustomEvent('app:network-error', {
                        detail: { message: 'Navigasi gagal: koneksi server terputus.' },
                    }),
                );
            }
        });
    },
    progress: {
        color: 'var(--color-secondary)',
    },
});
