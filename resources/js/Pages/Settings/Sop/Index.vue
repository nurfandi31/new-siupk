<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';

const props = defineProps({
    kolek: {
        type: Object,
        required: true,
        // { levels: [...summary], rows: [...5 rows associative] }
    },
});

// Pakai useForm untuk handle submit + error display
const kolekForm = useForm({
    rows: props.kolek.rows.map((r) => ({ ...r })),
});

function submitKolek() {
    const payload = {};
    kolekForm.rows.forEach((row, idx) => {
        const i = idx + 1;
        payload[`nama_kolek${i}`] = row.nama ?? '';
        payload[`pros_kolek${i}`] = row.prosentase ?? '';
        payload[`durasi${i}`] = row.durasi ?? '';
        payload[`satuan${i}`] = row.satuan || 'bulan';
    });

    kolekForm
        .transform(() => payload)
        .put('/settings/sop/kolek', {
            preserveScroll: true,
        });
}

const tampilkanLevels = computed(() => props.kolek.levels.filter((l) => l.aktif));
</script>

<template>
    <Head title="SOP Lembaga" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 pb-12">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold tracking-tight text-primary sm:text-3xl">SOP Lembaga</h1>
                <p class="text-sm text-on-surface-variant sm:text-base">
                    Personalisasi aturan operasional standar lembaga: kolektabilitas, sistem simpanan, asuransi, dan redaksi dokumen.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <AppButton variant="primary" size="sm" tag="a" href="/settings" icon="arrow_back">Kembali ke Pengaturan</AppButton>
            </div>

            <!-- Tabs (vertikal sederhana, satu tab dulu: Kolek) -->
            <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="border-b border-outline-variant bg-outline-variant/40 px-5 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-primary">
                        <AppIcon name="rule" tone="primary" :container-size="9" />
                        Kolektabilitas
                    </div>
                </div>

                <div class="space-y-6 p-5 sm:p-7">
                    <div class="flex items-start gap-3">
                        <AppIcon name="rule" tone="primary" :container-size="11" />
                        <div>
                            <h2 class="text-xl font-bold text-primary">Kolektabilitas Pinjaman</h2>
                            <p class="text-sm text-on-surface-variant">Atur tingkat kolektabilitas (Lancar → Macet) dan bobot CKPN per tingkat. Dipakai di laporan kolek &amp; cadangan penghapusan.</p>
                        </div>
                    </div>

                    <!-- Info notes (mirror SIUPK view) -->
                    <div class="space-y-2">
                        <div class="rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info">
                            <strong>Catatan:</strong> Durasi adalah kurang dari (<). Contoh: bila di isi 3 bulan, maka tunggakan &lt; 3 bulan akan masuk ke tingkat tsb.
                        </div>
                        <div class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                            <strong>Catatan:</strong> Apabila sistem hanya menggunakan tiga tingkat kolektibilitas, maka isian untuk Kolek Tingkat 4 dan Kolek Tingkat 5 dapat dikosongkan.
                        </div>
                    </div>

                    <form class="space-y-5" @submit.prevent="submitKolek">
                        <div v-for="(row, idx) in kolekForm.rows" :key="idx" class="grid gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/50 p-4 sm:grid-cols-2 md:grid-cols-4">
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-on-surface-variant">Kolek Tingkat {{ idx + 1 }}</label>
                                <AppInput v-model="row.nama" :placeholder="`Nama kolek (mis. ${idx === 0 ? 'Lancar' : idx === 1 ? 'Kurang Lancar' : idx === 2 ? 'Diragukan' : idx === 3 ? 'Macet' : 'Tidak Aktif'})`" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-on-surface-variant">Prosentase (%)</label>
                                <AppInput v-model="row.prosentase" type="number" step="0.01" :min="0" :max="100" placeholder="mis. 1, 10, 50, 100" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-on-surface-variant">Durasi</label>
                                <AppInput v-model="row.durasi" type="number" step="0.01" :min="0" placeholder="mis. 3, 6" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xs font-medium text-on-surface-variant">Satuan</label>
                                <select v-model="row.satuan" class="block w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                                    <option value="hari">Hari</option>
                                    <option value="bulan">Bulan</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant pt-4">
                            <p class="text-xs text-on-surface-variant">
                                <AppBadge tone="info" variant="soft" class="mr-2">CKPN</AppBadge>
                                Prosentase di setiap tingkat akan dipakai sebagai pengali pada laporan Cadangan Penghapusan Piutang (CKPN).
                            </p>
                            <AppButton type="submit" :loading="kolekForm.processing" :disabled="kolekForm.processing" icon="save">Simpan Kolektabilitas</AppButton>
                        </div>
                    </form>

                    <!-- Preview ringkasan rule aktif -->
                    <div v-if="tampilkanLevels.length" class="space-y-2 rounded-2xl border border-outline-variant bg-surface-container-low/30 p-4">
                        <h3 class="text-sm font-bold text-primary">Ringkasan Kolek Aktif</h3>
                        <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                            <div v-for="lvl in tampilkanLevels" :key="lvl.level" class="flex items-center justify-between gap-2 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2">
                                <div>
                                    <p class="text-sm font-bold text-primary">Tingkat {{ lvl.level }} — {{ lvl.nama }}</p>
                                    <p class="text-xs text-on-surface-variant">Ambang: {{ lvl.durasi_bulan }} bulan · CKPN {{ lvl.prosentase }}%</p>
                                </div>
                                <AppBadge tone="primary" variant="soft">{{ lvl.prosentase }}%</AppBadge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder untuk modul SOP lain -->
            <div class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest p-5 text-center">
                <AppIcon name="construction" class="text-3xl text-on-surface-variant" />
                <p class="mt-2 text-sm font-bold text-primary">Modul SOP lain menyusul</p>
                <p class="mt-1 text-xs text-on-surface-variant">Pengaturan sistem simpanan, asuransi, redaksi SPK lengkap, dan CALK akan ditambahkan pada fase berikutnya.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
