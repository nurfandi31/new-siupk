<script setup>
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';

const model = defineModel({ type: Boolean, default: false });

const isMac = computed(() => {
    return typeof navigator !== 'undefined' && /Mac|iPhone|iPod|iPad/.test(navigator.platform);
});

const modifierKey = computed(() => (isMac.value ? 'Option' : 'Alt'));
const cmdKey = computed(() => (isMac.value ? 'Cmd' : 'Ctrl'));

const shortcutGroups = computed(() => [
    {
        title: 'Pencarian & Bantuan',
        icon: 'search',
        items: [
            { keys: [cmdKey.value, 'K'], description: 'Buka Pencarian Cepat / Command Palette' },
            { keys: [modifierKey.value, 'A'], description: 'Buka / Tutup Ariel AI Assistant' },
            { keys: ['Shift', '?'], description: 'Buka Bantuan Pintasan Keyboard' },
            { keys: ['Esc'], description: 'Tutup Dialog / Modal / Menu Terbuka' },
        ],
    },
    {
        title: 'Navigasi Menu Utama',
        icon: 'navigation',
        items: [
            { keys: [modifierKey.value, 'D'], description: 'Ke Halaman Dashboard' },
            { keys: [modifierKey.value, 'J'], description: 'Ke Halaman Jurnal Umum' },
            { keys: [modifierKey.value, 'L'], description: 'Ke Halaman Pinjaman & Pembiayaan' },
            { keys: [modifierKey.value, 'M'], description: 'Ke Halaman Data Nasabah / Anggota' },
            { keys: [modifierKey.value, 'G'], description: 'Ke Halaman Data Kelompok' },
            { keys: [modifierKey.value, 'R'], description: 'Ke Halaman Laporan Keuangan' },
            { keys: [modifierKey.value, 'B'], description: 'Ke Halaman E-Budgeting' },
            { keys: [modifierKey.value, 'T'], description: 'Ke Halaman Tutup Buku' },
        ],
    },
    {
        title: 'Aksi Cepat & Sistem',
        icon: 'bolt',
        items: [
            { keys: [modifierKey.value, 'S'], description: 'Sinkronisasi Data Lokal (Desktop / Cloud)' },
            { keys: [modifierKey.value, 'N'], description: 'Buka / Tutup Notifikasi' },
            { keys: [modifierKey.value, 'P'], description: 'Cetak Laporan / Halaman Aktif' },
        ],
    },
]);
</script>

<template>
    <AppModal v-model="model" title="Pintasan Keyboard (Shortcuts)" size="lg">
        <div class="space-y-6">
            <p class="text-xs text-on-surface-variant leading-relaxed">
                Gunakan kombinasi tombol berikut untuk mempercepat navigasi dan pengoperasian aplikasi di <strong>Desktop</strong> maupun <strong>Website</strong>.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="group in shortcutGroups"
                    :key="group.title"
                    class="rounded-xl border border-outline-variant bg-surface-container-low/40 p-4"
                >
                    <div class="flex items-center gap-2 pb-2.5 border-b border-outline-variant/60">
                        <AppIcon :name="group.icon" class="text-primary text-base" />
                        <h4 class="text-xs font-bold uppercase tracking-wider text-primary">
                            {{ group.title }}
                        </h4>
                    </div>

                    <ul class="mt-3 space-y-2.5">
                        <li
                            v-for="(item, idx) in group.items"
                            :key="idx"
                            class="flex items-center justify-between gap-3 text-xs"
                        >
                            <span class="text-on-surface-variant leading-tight truncate">
                                {{ item.description }}
                            </span>
                            <div class="flex items-center gap-1 shrink-0">
                                <template v-for="(k, kIdx) in item.keys" :key="kIdx">
                                    <kbd class="inline-flex min-w-[22px] items-center justify-center rounded border border-outline-variant/80 bg-surface-container-high px-1.5 py-0.5 font-mono text-[11px] font-semibold text-primary shadow-sm">
                                        {{ k }}
                                    </kbd>
                                    <span v-if="kIdx < item.keys.length - 1" class="text-[10px] text-on-surface-variant font-bold">+</span>
                                </template>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="rounded-lg bg-surface-container-high/60 px-3.5 py-2.5 text-[11px] text-on-surface-variant flex items-center gap-2">
                <AppIcon name="info" class="text-primary text-sm shrink-0" />
                <span>
                    <strong>Tips:</strong> Pintasan kombinasi <code>{{ modifierKey }}</code> dapat ditekan kapan saja tanpa mengganggu pengetikan formulir.
                </span>
            </div>
        </div>
    </AppModal>
</template>