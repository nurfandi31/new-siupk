<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    active: {
        type: String,
        default: '',
        validator: (v) => ['', 'beranda', 'berita', 'halaman', 'kontak'].includes(v),
    },
});

const showScrollTop = ref(false);

const onScroll = () => {
    showScrollTop.value = window.scrollY > 480;
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3 md:gap-4 md:px-6 md:py-3.5 lg:px-8">
            <!-- LEFT: Brand -->
            <Link href="/" class="flex min-w-0 items-center gap-2.5 sm:gap-3" aria-label="Kembali ke beranda">
                <div class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md sm:size-10 md:size-11">
                    <img
                        v-if="organization.logo_url"
                        :src="organization.logo_url"
                        :alt="`Logo ${organization.name}`"
                        class="size-full object-contain"
                    >
                    <span v-else class="text-sm font-extrabold text-on-primary-container sm:text-base md:text-lg">{{ organization.name.charAt(0).toUpperCase() }}</span>
                </div>
                <div class="min-w-0 hidden sm:block">
                    <p class="truncate text-xs font-bold leading-tight text-on-surface sm:text-sm md:text-base">{{ organization.name }}</p>
                    <p v-if="organization.regency_name || organization.district_name" class="hidden truncate text-[10.5px] text-on-surface-variant md:block">
                        {{ [organization.district_name, organization.regency_name].filter(Boolean).join(', ') }}
                    </p>
                </div>
            </Link>

            <!-- RIGHT: Nav pills (uniform sizing, responsif) -->
            <nav class="flex items-center gap-1 sm:gap-1.5 md:gap-2">
                <Link
                    href="/"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-full px-2.5 text-[12px] font-semibold transition-all duration-300 sm:min-h-10 sm:gap-1.5 sm:px-3 sm:text-sm md:gap-2 md:px-4"
                    :class="active === 'beranda' ? 'bg-primary text-on-primary shadow-md' : 'bg-primary-container text-on-primary-container hover:bg-primary/15'"
                    aria-label="Kembali ke beranda"
                >
                    <AppIcon name="home" class="text-base sm:text-lg" />
                    <span class="hidden sm:inline">Beranda</span>
                </Link>
                <Link
                    href="/berita"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-full px-2.5 text-[12px] font-semibold transition-all duration-300 sm:min-h-10 sm:gap-1.5 sm:px-3 sm:text-sm md:gap-2 md:px-4"
                    :class="active === 'berita' ? 'bg-primary text-on-primary shadow-md' : 'bg-primary-container text-on-primary-container hover:bg-primary/15'"
                >
                    <AppIcon name="article" class="text-base sm:text-lg" />
                    <span class="hidden sm:inline">Berita</span>
                </Link>
                <Link
                    href="/halaman"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-full px-2.5 text-[12px] font-semibold transition-all duration-300 sm:min-h-10 sm:gap-1.5 sm:px-3 sm:text-sm md:gap-2 md:px-4"
                    :class="active === 'halaman' ? 'bg-primary text-on-primary shadow-md' : 'bg-primary-container text-on-primary-container hover:bg-primary/15'"
                >
                    <AppIcon name="description" class="text-base sm:text-lg" />
                    <span class="hidden sm:inline">Halaman</span>
                </Link>
                <Link
                    href="/kontak"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-full px-2.5 text-[12px] font-semibold transition-all duration-300 sm:min-h-10 sm:gap-1.5 sm:px-3 sm:text-sm md:gap-2 md:px-4"
                    :class="active === 'kontak' ? 'bg-primary text-on-primary shadow-md' : 'bg-primary-container text-on-primary-container hover:bg-primary/15'"
                >
                    <AppIcon name="mail" class="text-base sm:text-lg" />
                    <span class="hidden sm:inline">Kontak</span>
                </Link>
                <Link
                    href="/login"
                    class="inline-flex min-h-9 min-w-9 items-center justify-center gap-1.5 rounded-full bg-primary px-2.5 text-[12px] font-semibold text-on-primary shadow-md transition-all duration-300 hover:-translate-y-0.5 hover:bg-primary-deep sm:min-h-10 sm:gap-1.5 sm:px-3 sm:text-sm md:gap-2 md:px-4"
                    aria-label="Masuk ke sistem"
                >
                    <AppIcon name="login" class="text-base sm:text-lg" />
                    <span class="hidden sm:inline">Masuk</span>
                </Link>
            </nav>
        </div>
    </header>

    <!-- Scroll-to-top floating button (muncul setelah scroll 480px) -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        leave-active-class="transition-all duration-200 ease-in"
        enter-from-class="translate-y-3 scale-90 opacity-0"
        enter-to-class="translate-y-0 scale-100 opacity-100"
        leave-from-class="translate-y-0 scale-100 opacity-100"
        leave-to-class="translate-y-3 scale-90 opacity-0"
    >
        <button
            v-show="showScrollTop"
            type="button"
            @click="scrollToTop"
            class="fixed bottom-5 right-5 z-50 inline-flex size-12 items-center justify-center rounded-full bg-primary text-on-primary shadow-md ring-1 ring-primary/20 transition hover:bg-primary-deep focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 sm:bottom-6 sm:right-6"
            aria-label="Kembali ke atas halaman"
            title="Kembali ke atas"
        >
            <AppIcon name="arrow_upward" class="text-2xl" />
        </button>
    </Transition>
</template>
