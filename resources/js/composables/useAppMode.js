import { usePage } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const isOffline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);

if (typeof window !== 'undefined') {
    window.addEventListener('online', () => { isOffline.value = false; });
    window.addEventListener('offline', () => { isOffline.value = true; });
    window.addEventListener('app:network-error', () => { isOffline.value = true; });
}

export function useAppMode() {
    const page = usePage();

    const isDesktop = computed(() => {
        if (typeof window !== 'undefined' && window.desktopAPI?.isDesktop) {
            return true;
        }
        return Boolean(page.props.desktop?.is_desktop);
    });

    const isReadOnly = computed(() => {
        return isOffline.value || Boolean(page.props.desktop?.is_offline);
    });

    return {
        isOffline,
        isDesktop,
        isReadOnly,
    };
}
