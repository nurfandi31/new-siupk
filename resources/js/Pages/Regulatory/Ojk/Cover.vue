<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    date_formatted: string;
    identity: {
        legal_name: string;
        short_name: string | null;
        district_name: string;
        regency_name: string;
        address: string;
        phone: string;
        email: string;
        registration_number: string;
        tax_number: string;
        logo_url: string | null;
        manager_name: string;
        secretary_name: string;
        treasurer_name: string;
        manager_title: string;
        secretary_title: string;
        treasurer_title: string;
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
        '/regulatory/ojk/cover',
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
    return `/regulatory/ojk/cover/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Cover OJK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Laporan OJK · Sampul
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Cover Laporan OJK</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Halaman sampul untuk periode {{ period_label }}
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

            <AppCard class="overflow-hidden p-0">
                <div class="border-2 border-primary p-8 sm:p-12">
                    <div class="flex flex-col items-center text-center">
                        <div v-if="identity.logo_url" class="mb-6">
                            <img :src="identity.logo_url" alt="Logo Lembaga" class="h-24 w-24 object-contain" />
                        </div>
                        <div v-else class="mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-primary/10 text-2xl font-bold text-primary">
                            {{ (identity.short_name ?? identity.legal_name ?? 'L').slice(0, 1).toUpperCase() }}
                        </div>
                        <p class="text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant">
                            Otoritas Jasa Keuangan
                        </p>
                        <p class="mt-1 text-xs uppercase tracking-widest text-on-surface-variant">
                            Laporan Pelaporan Keuangan
                        </p>
                        <h2 class="mt-8 text-2xl font-bold uppercase text-primary sm:text-3xl">
                            {{ identity.legal_name }}
                        </h2>
                        <p v-if="identity.district_name" class="mt-1 text-sm uppercase tracking-widest text-on-surface-variant">
                            {{ identity.district_name }}
                        </p>
                        <p v-if="identity.address" class="mt-2 max-w-md text-xs text-on-surface-variant">
                            {{ identity.address }}
                        </p>

                        <div class="mt-12 border-t-2 border-b-2 border-primary py-4">
                            <h3 class="text-xl font-bold uppercase tracking-wider text-primary sm:text-2xl">
                                Laporan Keuangan
                            </h3>
                            <p class="mt-1 text-base font-semibold text-primary sm:text-lg">
                                Periode {{ period_label }}
                            </p>
                        </div>

                        <p class="mt-8 max-w-md text-xs text-on-surface-variant">
                            Disampaikan kepada Otoritas Jasa Keuangan Republik Indonesia
                            untuk memenuhi ketentuan pelaporan keuangan lembaga.
                        </p>
                    </div>
                </div>
            </AppCard>

            <AppCard class="p-4">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Identitas Lembaga
                </h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Nama Lembaga</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.legal_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Nama Singkat</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.short_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">No. Registrasi</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.registration_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">NPWP</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.tax_number || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Alamat</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.address || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Kecamatan / Kabupaten</dt>
                        <dd class="font-semibold text-on-surface">
                            {{ identity.district_name || '—' }}<span v-if="identity.regency_name"> · {{ identity.regency_name }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Telepon</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wider text-on-surface-variant">Email</dt>
                        <dd class="font-semibold text-on-surface">{{ identity.email || '—' }}</dd>
                    </div>
                </dl>
            </AppCard>

            <AppCard class="p-4">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Susunan Pengurus (Cover)
                </h3>
                <ul class="space-y-1 text-sm text-on-surface">
                    <li v-if="identity.manager_name">
                        <span class="text-on-surface-variant">{{ identity.manager_title }}:</span>
                        <span class="ml-2 font-semibold">{{ identity.manager_name }}</span>
                    </li>
                    <li v-if="identity.secretary_name">
                        <span class="text-on-surface-variant">{{ identity.secretary_title }}:</span>
                        <span class="ml-2 font-semibold">{{ identity.secretary_name }}</span>
                    </li>
                    <li v-if="identity.treasurer_name">
                        <span class="text-on-surface-variant">{{ identity.treasurer_title }}:</span>
                        <span class="ml-2 font-semibold">{{ identity.treasurer_name }}</span>
                    </li>
                </ul>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>