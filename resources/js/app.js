import './bootstrap';
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, defineComponent, h } from 'vue';
import AppOfflineBanner from './Components/AppOfflineBanner.vue';
import DesktopSplashScreen from './Components/DesktopSplashScreen.vue';
import DesktopTitleBar from './Components/DesktopTitleBar.vue';
import { initI18n, useI18n } from './i18n';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/index.esm.js';

initI18n();

if (typeof window !== 'undefined' && window.desktopAPI?.onNavigate) {
    window.desktopAPI.onNavigate((url) => {
        if (url && typeof url === 'string') {
            router.visit(url);
        }
    });
}

/*
 * Banyak label disusun saat `setup` (kolom tabel, item menu, opsi filter),
 * sehingga `t()` di template saja tidak cukup ketika bahasa diganti. Wrapper
 * ini memberi `key` pada komponen halaman berdasarkan locale aktif agar
 * seluruh pohon halaman (termasuk layout) di-mount ulang tanpa reload jaringan.
 */
const localizedPages = new Map();

function localizedPage(name, component) {
    // `resolvePageComponent` mengembalikan module ESM; Inertia biasanya
    // mengambil `.default`, tetapi wrapper ini menerima module lebih dulu.
    const page = component?.default ?? component;
    if (!page) return page;

    if (!localizedPages.has(name)) {
        localizedPages.set(name, defineComponent({
            name: `LocalizedPage:${name}`,
            inheritAttrs: false,
            setup(_, { attrs, slots }) {
                const { locale } = useI18n();
                return () => h(page, { ...attrs, key: `${name}:${locale.value}` }, slots);
            },
        }));
    }

    return localizedPages.get(name);
}

const appTitle = (title) => {
    const brand = 'siupk Next';
    const value = typeof title === 'string' ? title.trim() : '';

    if (!value) return brand;
    return value.toLowerCase().includes(brand.toLowerCase()) ? value : `${value} - ${brand}`;
};

createInertiaApp({
    title: appTitle,
    resolve: (name) => resolvePageComponent(
        `./Pages/${name}.vue`,
        import.meta.glob('./Pages/**/*.vue'),
    ).then((component) => localizedPage(name, component)),

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
