<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const isDesktop = ref(false);
const showSplash = ref(false);
const showExit = ref(false);
const splashProgress = ref(15);
const splashStatus = ref('Memuat modul sistem...');
const exitStatus = ref('Menyimpan sesi & mengamankan data...');

let progressInterval = null;

const tenantName = computed(() => {
    return page.props.tenant?.name || page.props.unitName || 'siupk Next';
});

function triggerExitScreen(detail = {}) {
    showExit.value = true;
    if (detail.message) {
        exitStatus.value = detail.message;
    }
}

onMounted(() => {
    isDesktop.value = Boolean(window.desktopAPI?.isDesktop) || Boolean(page.props.desktop?.is_desktop);

    // Only show opening splash screen on initial desktop app launch
    const hasShownSplash = sessionStorage.getItem('siupk_desktop_splash_shown');
    if (isDesktop.value && !hasShownSplash) {
        showSplash.value = true;
        sessionStorage.setItem('siupk_desktop_splash_shown', 'true');

        // Progress bar simulation for smooth startup feeling
        progressInterval = setInterval(() => {
            if (splashProgress.value < 90) {
                splashProgress.value += 25;
                if (splashProgress.value >= 40 && splashProgress.value < 70) {
                    splashStatus.value = 'Menyiapkan database lokal...';
                } else if (splashProgress.value >= 70) {
                    splashStatus.value = 'Memeriksa otentikasi...';
                }
            }
        }, 300);

        setTimeout(() => {
            splashProgress.value = 100;
            splashStatus.value = 'Siap!';
            setTimeout(() => {
                showSplash.value = false;
                if (progressInterval) clearInterval(progressInterval);
            }, 400);
        }, 1600);
    }

    // Listen for desktop closing event
    window.addEventListener('desktop:closing', (e) => {
        triggerExitScreen(e.detail || {});
    });
});

onUnmounted(() => {
    if (progressInterval) {
        clearInterval(progressInterval);
    }
    window.removeEventListener('desktop:closing', triggerExitScreen);
});
</script>

<template>
    <!-- 1. Opening Splash Screen -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-500 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-105 pointer-events-none"
    >
        <div
            v-if="showSplash"
            class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-slate-950 text-white select-none"
            style="-webkit-app-region: drag;"
        >
            <!-- Background Ambient Glow -->
            <div class="absolute -top-24 -left-24 h-96 w-96 rounded-full bg-emerald-500/15 blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-emerald-600/15 blur-3xl"></div>

            <div class="relative flex flex-col items-center text-center px-6" style="-webkit-app-region: no-drag;">
                <!-- Animated App Logo -->
                <div class="relative mb-6 flex items-center justify-center">
                    <div class="absolute -inset-4 rounded-3xl bg-emerald-500/20 blur-xl animate-pulse"></div>
                    <div class="relative flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 shadow-2xl shadow-emerald-900/50 ring-1 ring-white/20">
                        <span class="text-3xl font-black tracking-wider text-white">S</span>
                    </div>
                </div>

                <!-- Welcome Text -->
                <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">
                    Selamat Datang
                </h1>
                <p class="mt-1 text-sm font-semibold tracking-wide text-emerald-400">
                    siupk Next Desktop
                </p>
                <p class="mt-0.5 text-xs text-slate-400 max-w-xs truncate">
                    {{ tenantName }}
                </p>

                <!-- Loading Bar & Status -->
                <div class="mt-8 w-64">
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-800 ring-1 ring-white/10">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300 ease-out"
                            :style="{ width: `${splashProgress}%` }"
                        ></div>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ splashStatus }}</span>
                        <span class="font-mono text-emerald-400">{{ splashProgress }}%</span>
                    </div>
                </div>
            </div>

            <!-- Footer copyright / version -->
            <div class="absolute bottom-6 text-[11px] text-slate-400">
                Sistem Informasi Dana Bergulir Masyarakat
            </div>
        </div>
    </Transition>

    <!-- 2. Closing / Exit Screen -->
    <Transition
        enter-active-class="transition duration-250 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showExit"
            class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-slate-950/95 backdrop-blur-xl text-white select-none cursor-wait"
            style="-webkit-app-region: drag;"
        >
            <div class="relative flex flex-col items-center text-center px-6" style="-webkit-app-region: no-drag;">
                <!-- Animated Exit Spinner -->
                <div class="relative mb-5 flex items-center justify-center">
                    <div class="absolute -inset-3 rounded-full bg-rose-500/15 blur-lg animate-pulse"></div>
                    <div class="relative flex h-16 w-16 items-center justify-center rounded-full border-2 border-slate-700 bg-slate-900 shadow-xl">
                        <svg class="h-7 w-7 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>

                <h2 class="text-xl font-bold tracking-tight text-white">
                    Sampai Jumpa!
                </h2>
                <p class="mt-1 text-xs text-slate-400">
                    {{ exitStatus }}
                </p>

                <div class="mt-6 flex items-center gap-2 text-[11px] text-slate-400 font-medium">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    <span>Menutup aplikasi dengan aman...</span>
                </div>
            </div>
        </div>
    </Transition>
</template>