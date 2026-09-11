<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import RegencyLayout from '../../../Layouts/RegencyLayout.vue';
import { useMoney } from '../../../composables/useMoney';

const { money } = useMoney();

const props = defineProps({
    report: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    selected_tenant_id: { type: [Number, String], default: '' },
    regency_name: { type: String, default: 'Kabupaten' },
});

const selectedTenant = ref(props.selected_tenant_id || '');

const tenantOptions = computed(() => [
    { value: '', label: 'Semua Kecamatan (Gabungan)' },
    ...(props.report.kecamatans || []).map(kec => ({
        value: kec.tenant_id,
        label: kec.name,
    })),
]);

watch(selectedTenant, () => {
    router.get('/regency/reports/calk', {
        year: props.year,
        month: props.month || '',
        tenant_id: selectedTenant.value || '',
    }, { preserveState: true });
});
</script>

<template>
    <Head :title="`CALK Konsolidasi - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header & Filter -->
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Catatan Atas Laporan Keuangan (CALK)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Kabupaten {{ regency_name }} · {{ report.period?.period_label }} · {{ report.is_consolidated ? 'Gabungan Seluruh Kecamatan' : 'Kecamatan Terpilih' }}
                    </p>
                </div>
            </div>

            <ReportPeriodFilter :year="year" :month="month"
                baseUrl="/regency/reports/calk"
                pdfUrl="/regency/reports/calk/pdf"
                :extra="{ tenant_id: selectedTenant || '' }"
            >
                <template #extra>
                    <SmartSelect
                        v-model="selectedTenant"
                        :options="tenantOptions"
                        label="Kecamatan"
                        value-key="value"
                        label-key="label"
                        hide-label
                    />
                </template>
            </ReportPeriodFilter>

            <!-- Highlights -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard v-for="h in report.highlights" :key="h.key">
                    <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ h.label }}</span>
                    <p
                        class="mt-2 text-2xl font-bold"
                        :class="h.value < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ money(h.value) }}
                    </p>
                </AppCard>
            </div>

            <!-- I. Gambaran Umum & Kebijakan -->
            <AppCard class="p-6">
                <h2 class="text-base font-bold text-primary">I. Gambaran Umum Wilayah Kabupaten</h2>
                <div class="mt-3 space-y-2 text-sm text-on-surface leading-relaxed">
                    <p>
                        Laporan keuangan konsolidasi Kabupaten <strong>{{ regency_name }}</strong> mencakup seluruh unit pengelola kegiatan (UPK DBM / BUMDesma) yang berada dalam naungan administrasi kabupaten.
                    </p>
                    <p>
                        Penggabungan laporan menggunakan basis data shard tunggal yang memastikan standarisasi bagan akun standar (Chart of Accounts), eliminasi transaksi intra-kabupaten, serta kepatuhan pada regulasi pelaporan keuangan dana bergulir masyarakat.
                    </p>
                </div>
            </AppCard>

            <!-- II. Kebijakan Akuntansi Standar -->
            <AppCard class="p-6">
                <h2 class="text-base font-bold text-primary">II. Kebijakan Akuntansi Standar yang Diterapkan</h2>
                <div class="mt-3 space-y-3 text-sm text-on-surface">
                    <div class="flex gap-3">
                        <span class="font-bold text-primary">1.</span>
                        <p><strong>Dasar Penyusunan:</strong> Laporan disajikan menggunakan basis akrual dan standar akuntansi entitas tanpa akuntabilitas publik (SAK ETAP / EP) yang disesuaikan untuk lembaga pengelola DBM.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="font-bold text-primary">2.</span>
                        <p><strong>Kas dan Setara Kas:</strong> Mencakup kas di brankas kasir unit kerja dan saldo giro/tabungan di bank operasional yang dapat ditarik sewaktu-waktu.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="font-bold text-primary">3.</span>
                        <p><strong>Pinjaman Bergulir:</strong> Dicatat sebesar nilai pokok pinjaman yang disalurkan kepada kelompok pemanfaat, dikurangi cadangan penyisihan penghapusan pinjaman ragu-ragu jika ada.</p>
                    </div>
                    <div class="flex gap-3">
                        <span class="font-bold text-primary">4.</span>
                        <p><strong>Pendapatan Jasa Pinjaman:</strong> Diakui saat angsuran diterima atau pada saat jatuh tempo tagihan sesuai jadwal pengembalian kelompok.</p>
                    </div>
                </div>
            </AppCard>

            <!-- III. Rekapitulasi per Kecamatan -->
            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">III. Rincian & Partisipasi per Kecamatan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Nama Kecamatan</th>
                                <th class="px-6 py-3 text-right">Kas & Bank</th>
                                <th class="px-6 py-3 text-right">Pinjaman Aktif</th>
                                <th class="px-6 py-3 text-right">Sisa Pokok</th>
                                <th class="px-6 py-3 text-right">Kelompok</th>
                                <th class="px-6 py-3 text-right">Anggota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="kec in report.kecamatans" :key="kec.tenant_id" class="hover:bg-surface-container-low/40">
                                <td class="px-6 py-3 font-mono text-xs">{{ kec.district_code || kec.code }}</td>
                                <td class="px-6 py-3 font-bold text-primary">{{ kec.name }}</td>
                                <td class="px-6 py-3 text-right font-semibold">{{ money(kec.cash) }}</td>
                                <td class="px-6 py-3 text-right">{{ kec.active_loans }}</td>
                                <td class="px-6 py-3 text-right">{{ money(kec.active_principal) }}</td>
                                <td class="px-6 py-3 text-right">{{ kec.groups_count }}</td>
                                <td class="px-6 py-3 text-right">{{ kec.members_count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
