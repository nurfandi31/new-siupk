<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface UnitRow {
    code: string;
    name: string;
    type: string;
    address: string;
}

interface PengurusRow {
    name: string;
    role: string;
    email: string | null;
    phone: string | null;
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    date_formatted: string;
    identity: {
        legal_name: string;
        short_name: string | null;
        brand_short: string | null;
        district_name: string;
        regency_name: string;
        province_name: string;
        address: string;
        phone: string;
        email: string;
        contact_email_secondary: string;
        website: string;
        registration_number: string;
        tax_number: string;
        logo_url: string | null;
        operational_start_date: string | null;
        operational_since_year: number | null;
        manager_name: string;
        secretary_name: string;
        treasurer_name: string;
        verifier_name: string;
        manager_title: string;
        secretary_title: string;
        treasurer_title: string;
        verifier_title: string;
    };
    units: UnitRow[];
    units_count: number;
    pengurus: PengurusRow[];
    statistics: {
        units_count: number;
        pengurus_count: number;
    };
    filters: { year: number; month: number };
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));

const monthOptions = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list: { value: string; label: string }[] = [];
    for (let y = current + 1; y >= current - 5; y -= 1) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

function apply(): void {
    router.get(
        '/regulatory/ojk/profile',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
    });
    return `/regulatory/ojk/profile/pdf?${q.toString()}`;
});

function formatDate(value: string | null): string {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleDateString('id-ID', {
            day: '2-digit',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return value;
    }
}
</script>

<template>
    <Head title="Profil Kelembagaan OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Laporan OJK · Profil
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Profil Kelembagaan</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Untuk periode yang berakhir pada {{ date_formatted }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            Cetak PDF
                        </AppButton>
                    </a>
                </div>
            </div>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tahun</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Bulan</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <AppCard class="p-6">
                <div class="flex items-start gap-4">
                    <div v-if="identity.logo_url" class="flex-shrink-0">
                        <img :src="identity.logo_url" alt="Logo Lembaga" class="h-20 w-20 object-contain" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold text-primary">{{ identity.legal_name }}</h2>
                        <p v-if="identity.brand_short" class="text-sm text-on-surface-variant">
                            {{ identity.brand_short }}
                        </p>
                        <p v-if="identity.district_name || identity.regency_name" class="mt-2 text-sm text-on-surface-variant">
                            {{ identity.district_name }}<span v-if="identity.regency_name"> · {{ identity.regency_name }}</span>
                        </p>
                        <p v-if="identity.address" class="mt-1 text-sm text-on-surface-variant">{{ identity.address }}</p>
                    </div>
                </div>
            </AppCard>

            <!-- Statistik ringkas -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Unit Desa</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ statistics.units_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pengurus</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ statistics.pengurus_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Beroperasi Sejak</p>
                    <p class="mt-2 text-lg font-bold text-primary">
                        {{ identity.operational_since_year ?? '—' }}
                    </p>
                    <p v-if="identity.operational_start_date" class="mt-1 text-xs text-on-surface-variant">
                        {{ formatDate(identity.operational_start_date) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Periode Laporan</p>
                    <p class="mt-2 text-base font-bold text-primary">{{ period_label }}</p>
                </AppCard>
            </div>

            <!-- Identitas Lembaga -->
            <AppCard class="p-5">
                <h3 class="mb-4 border-b border-outline-variant pb-2 text-base font-bold uppercase tracking-wide text-primary">
                    1. Identitas Lembaga
                </h3>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Nama Lembaga</dt>
                        <dd class="font-medium text-on-surface">{{ identity.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Nama Singkat</dt>
                        <dd class="font-medium text-on-surface">{{ identity.short_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Nomor Registrasi / SK Kemenkumham</dt>
                        <dd class="font-medium text-on-surface">{{ identity.registration_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">NPWP</dt>
                        <dd class="font-medium text-on-surface">{{ identity.tax_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">NIB / Nomor Izin Berusaha</dt>
                        <dd class="font-medium text-on-surface">{{ identity.registration_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">No. Izin Operasional</dt>
                        <dd class="font-medium text-on-surface">{{ identity.registration_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Tanggal Izin / Mulai Operasi</dt>
                        <dd class="font-medium text-on-surface">
                            {{ formatDate(identity.operational_start_date) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Jenis Lembaga Jasa Keuangan (LJK)</dt>
                        <dd class="font-medium text-on-surface">BUMDesma Lembaga Keuangan Desa</dd>
                    </div>
                </dl>
            </AppCard>

            <!-- Alamat & Kontak -->
            <AppCard class="p-5">
                <h3 class="mb-4 border-b border-outline-variant pb-2 text-base font-bold uppercase tracking-wide text-primary">
                    2. Alamat Lengkap & Kontak
                </h3>
                <dl class="grid grid-cols-1 gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Alamat</dt>
                        <dd class="font-medium text-on-surface">{{ identity.address || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Kecamatan</dt>
                        <dd class="font-medium text-on-surface">{{ identity.district_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Kabupaten / Kota</dt>
                        <dd class="font-medium text-on-surface">{{ identity.regency_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Provinsi</dt>
                        <dd class="font-medium text-on-surface">{{ identity.province_name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Telepon</dt>
                        <dd class="font-medium text-on-surface">{{ identity.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Email Utama</dt>
                        <dd class="font-medium text-on-surface">{{ identity.email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Email Cadangan</dt>
                        <dd class="font-medium text-on-surface">{{ identity.contact_email_secondary || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Website</dt>
                        <dd class="font-medium text-on-surface">{{ identity.website || '—' }}</dd>
                    </div>
                </dl>
            </AppCard>

            <!-- Susunan Pengurus -->
            <AppCard class="p-5">
                <h3 class="mb-4 border-b border-outline-variant pb-2 text-base font-bold uppercase tracking-wide text-primary">
                    3. Susunan Pengurus
                </h3>
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">No</th>
                            <th class="px-3 py-2 text-left">Nama</th>
                            <th class="px-3 py-2 text-left">Jabatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="pengurus.length === 0">
                            <td colspan="3" class="px-3 py-6 text-center text-on-surface-variant">
                                Belum ada data pengurus.
                            </td>
                        </tr>
                        <tr v-for="(p, idx) in pengurus" :key="idx" class="border-t border-outline-variant/30">
                            <td class="px-3 py-2 text-center">{{ idx + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">{{ p.name }}</td>
                            <td class="px-3 py-2">{{ p.role }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>

            <!-- Wilayah Kerja -->
            <AppCard class="p-5">
                <h3 class="mb-4 border-b border-outline-variant pb-2 text-base font-bold uppercase tracking-wide text-primary">
                    4. Wilayah Kerja & Unit Desa
                </h3>
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left w-12">No</th>
                            <th class="px-3 py-2 text-left">Kode</th>
                            <th class="px-3 py-2 text-left">Nama Unit</th>
                            <th class="px-3 py-2 text-left">Tipe</th>
                            <th class="px-3 py-2 text-left">Alamat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="units.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-on-surface-variant">
                                Belum ada data unit desa.
                            </td>
                        </tr>
                        <tr v-for="(u, idx) in units" :key="u.code" class="border-t border-outline-variant/30">
                            <td class="px-3 py-2 text-center">{{ idx + 1 }}</td>
                            <td class="px-3 py-2 font-mono">{{ u.code }}</td>
                            <td class="px-3 py-2 font-semibold">{{ u.name }}</td>
                            <td class="px-3 py-2">{{ u.type || '—' }}</td>
                            <td class="px-3 py-2 text-on-surface-variant">{{ u.address || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </AppCard>

            <AppCard class="p-5">
                <h3 class="mb-3 text-base font-bold uppercase tracking-wide text-primary">
                    5. Tanda Tangan Pengesahan
                </h3>
                <p class="mb-3 text-sm text-on-surface-variant">
                    Laporan ini disusun dan disahkan oleh pengurus pada tanggal {{ date_formatted }}.
                </p>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div v-if="identity.manager_name" class="text-center">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">
                            {{ identity.manager_title }}
                        </p>
                        <div class="mt-12 border-t border-outline pt-1 font-semibold text-on-surface">
                            {{ identity.manager_name }}
                        </div>
                    </div>
                    <div v-if="identity.secretary_name" class="text-center">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">
                            {{ identity.secretary_title }}
                        </p>
                        <div class="mt-12 border-t border-outline pt-1 font-semibold text-on-surface">
                            {{ identity.secretary_name }}
                        </div>
                    </div>
                    <div v-if="identity.treasurer_name" class="text-center">
                        <p class="text-xs font-bold uppercase tracking-wider text-primary">
                            {{ identity.treasurer_title }}
                        </p>
                        <div class="mt-12 border-t border-outline pt-1 font-semibold text-on-surface">
                            {{ identity.treasurer_name }}
                        </div>
                    </div>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>