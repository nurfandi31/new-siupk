<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';

const page = usePage();
const isDesktop = ref(false);
const isMaximized = ref(false);
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true);
const isSyncing = ref(false);
const isClosing = ref(false);
const syncMessage = ref('');

let removeMaximizeListener = null;
let removeCloseListener = null;

function checkOnline() {
    isOnline.value = typeof navigator !== 'undefined' ? navigator.onLine : true;
}

const currentUserName = computed(() => {
    return page.props.auth?.user?.name || 'Petugas';
});

async function triggerSync() {
    if (isSyncing.value || !isOnline.value) return;
    isSyncing.value = true;
    syncMessage.value = 'Menyinkronkan...';
    try {
        const res = await fetch('/desktop/sync/trigger', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            syncMessage.value = 'Sinkronisasi berhasil!';
            window.desktopAPI?.sendNotification?.({
                title: 'Sinkronisasi Berhasil',
                body: `Data lokal diperbarui dari cloud oleh ${currentUserName.value}.`,
                url: '/dashboard',
            });
            setTimeout(() => { syncMessage.value = ''; }, 3000);
        } else {
            syncMessage.value = 'Sinkronisasi gagal.';
            setTimeout(() => { syncMessage.value = ''; }, 3000);
        }
    } catch {
        syncMessage.value = 'Koneksi gagal.';
        setTimeout(() => { syncMessage.value = ''; }, 3000);
    } finally {
        isSyncing.value = false;
    }
}

function handleMinimize() {
    window.desktopAPI?.minimize?.();
}

function handleMaximize() {
    window.desktopAPI?.maximize?.();
}

async function handleClose() {
    if (isClosing.value) return;
    isClosing.value = true;

    // Trigger visual Goodbye / Exit Screen
    window.dispatchEvent(new CustomEvent('desktop:closing', {
        detail: { message: 'Menyimpan sesi & mengamankan data...' }
    }));

    const user = page.props.auth?.user;
    if (user) {
        // Logged in user: perform clean logout before closing desktop window
        try {
            router.post('/logout', {}, {
                onFinish: () => {
                    window.desktopAPI?.close?.();
                },
                onError: () => {
                    window.desktopAPI?.close?.();
                },
            });

            // Fallback safety timeout if request takes too long
            setTimeout(() => {
                window.desktopAPI?.close?.();
            }, 1200);
        } catch {
            window.desktopAPI?.close?.();
        }
    } else {
        // Guest user: close window immediately
        window.desktopAPI?.close?.();
    }
}

onMounted(async () => {
    isDesktop.value = Boolean(window.desktopAPI?.isDesktop) || Boolean(page.props.desktop?.is_desktop);

    if (window.desktopAPI?.isMaximized) {
        isMaximized.value = await window.desktopAPI.isMaximized();
    }

    if (window.desktopAPI?.onMaximizeChange) {
        removeMaximizeListener = window.desktopAPI.onMaximizeChange((maximized) => {
            isMaximized.value = maximized;
        });
    }

    if (window.desktopAPI?.onCloseRequested) {
        removeCloseListener = window.desktopAPI.onCloseRequested(() => {
            handleClose();
        });
    }

    window.addEventListener('online', checkOnline);
    window.addEventListener('offline', checkOnline);
    window.addEventListener('app:trigger-sync', triggerSync);
});

onUnmounted(() => {
    if (removeMaximizeListener) {
        removeMaximizeListener();
    }
    if (removeCloseListener) {
        removeCloseListener();
    }
    window.removeEventListener('online', checkOnline);
    window.removeEventListener('offline', checkOnline);
    window.removeEventListener('app:trigger-sync', triggerSync);
});

const tenantName = computed(() => {
    return page.props.tenant?.name || page.props.unitName || 'siupk Next Desktop';
});
</script>

<template>
    <header
        v-if="isDesktop"
        class="sticky top-0 z-[150] flex h-9 w-full select-none items-center justify-between border-b border-slate-800 bg-slate-950/95 px-3 text-xs text-slate-300 backdrop-blur-md"
        style="-webkit-app-region: drag;"
    >
        <!-- Left Side: App Indicator & Status -->
        <div class="flex items-center gap-2.5" style="-webkit-app-region: no-drag;">
            <div class="flex items-center gap-1.5 font-bold tracking-tight text-white">
                <div class="flex h-5 w-5 items-center justify-center rounded bg-emerald-600 text-[10px] font-black text-white shadow-sm">
                    S
                </div>
                <span class="hidden sm:inline">siupk</span>
            </div>

            <div class="h-3 w-px bg-slate-800"></div>

            <!-- Online/Offline Indicator Badge -->
            <div
                v-if="isOnline"
                class="flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-400"
                title="Aplikasi terhubung langsung dengan server cloud"
            >
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                <span>Online</span>
            </div>

            <div
                v-else
                class="flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-400"
                title="Mode offline lokal aktif; input, edit, dan delete tetap diizinkan"
            >
                <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                <span>Offline (Input Diizinkan)</span>
            </div>

            <!-- Quick Sync Button -->
            <button
                v-if="isOnline"
                type="button"
                class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[11px] text-slate-400 transition hover:bg-slate-800 hover:text-slate-200"
                :disabled="isSyncing || isClosing"
                title="Sinkronkan data dari cloud server"
                @click="triggerSync"
            >
                <svg
                    class="h-3 w-3"
                    :class="{ 'animate-spin text-emerald-400': isSyncing }"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>{{ syncMessage || 'Sinkron' }}</span>
            </button>
        </div>

        <!-- Center: Draggable Window Title -->
        <div class="pointer-events-none truncate px-4 text-center font-medium text-slate-400">
            {{ tenantName }}
        </div>

        <!-- Right Side: Native Window Controls (Minimize / Maximize / Close) -->
        <div class="flex items-stretch -mr-3 h-full" style="-webkit-app-region: no-drag;">
            <!-- Minimize -->
            <button
                type="button"
                class="flex w-10 items-center justify-center text-slate-400 transition hover:bg-slate-800 hover:text-white"
                title="Minimize"
                aria-label="Minimize"
                :disabled="isClosing"
                @click="handleMinimize"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
            </button>

            <!-- Maximize / Restore -->
            <button
                type="button"
                class="flex w-10 items-center justify-center text-slate-400 transition hover:bg-slate-800 hover:text-white"
                :title="isMaximized ? 'Restore' : 'Maximize'"
                :aria-label="isMaximized ? 'Restore' : 'Maximize'"
                :disabled="isClosing"
                @click="handleMaximize"
            >
                <svg v-if="!isMaximized" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect x="5" y="5" width="14" height="14" rx="1.5" stroke-width="2" />
                </svg>
                <svg v-else class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect x="7" y="7" width="12" height="12" rx="1" stroke-width="1.8" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5h8a2 2 0 012 2v8" />
                </svg>
            </button>

            <!-- Close (Logout & Exit) -->
            <button
                type="button"
                class="flex w-11 items-center justify-center text-slate-400 transition hover:bg-rose-600 hover:text-white"
                :class="{ 'opacity-70 cursor-wait': isClosing }"
                title="Keluar & Tutup Aplikasi"
                aria-label="Keluar & Tutup Aplikasi"
                :disabled="isClosing"
                @click="handleClose"
            >
                <svg v-if="!isClosing" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <svg v-else class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </div>
    </header>
</template>