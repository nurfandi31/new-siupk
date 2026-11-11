<script setup lang="ts">
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface IdentityBag {
    legal_name?: string;
    short_name?: string | null;
    logo_url?: string | null;
    registration_number?: string;
    address?: string;
    district_name?: string;
    regency_name?: string;
    phone?: string;
    email?: string;
    manager_name?: string;
    manager_title?: string;
    treasurer_name?: string;
    treasurer_title?: string;
}

interface HealthRow {
    no: number;
    aspect: string;
    ratio: string;
    result: number | null;
    unit: string;
    score: number;
    status: string;
    criteria: string[];
    details: { label: string; value: number | null }[];
}

const props = defineProps<{
    year: number;
    month: number;
    framework: '19' | '41';
    framework_label: string;
    title: string;
    period_label: string;
    as_of: string | null;
    identity: IdentityBag;
    parameters: { label: string; value: number | null }[];
    values: Record<string, number>;
    detail_kolek: Record<string, number>;
    rows: HealthRow[];
    composite_score: number;
    composite_max: number;
    rating: string;
    supervision_status: string | null;
    supervision_basis: string | null;
    summary_label: string;
    generated_at: string | null;
    filters: { year: number; month: number; framework: '19' | '41' };
    error?: string;
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedFramework = ref<string>(props.filters.framework ?? '41');

const monthOptions = [
    { value: '1', label: t('Januari') },
    { value: '2', label: t('Februari') },
    { value: '3', label: t('Maret') },
    { value: '4', label: t('April') },
    { value: '5', label: t('Mei') },
    { value: '6', label: t('Juni') },
    { value: '7', label: t('Juli') },
    { value: '8', label: t('Agustus') },
    { value: '9', label: t('September') },
    { value: '10', label: t('Oktober') },
    { value: '11', label: t('November') },
    { value: '12', label: t('Desember') },
];

const frameworkOptions = [
    { value: '41', label: 'POJK 41/2024 + POJK 49/2024' },
    { value: '19', label: 'POJK 19/2021' },
];

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

function formatMoney(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return money.format(Number(value));
}

function formatPercent(value: number | null | undefined): string {
    if (value === null || value === undefined) return '-';
    return `${money.format(Number(value))}%`;
}

function apply(): void {
    router.get(
        '/regulatory/ojk/health',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            framework: selectedFramework.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        framework: selectedFramework.value,
    });
    return `/regulatory/ojk/health/pdf?${q.toString()}`;
});

const hasData = computed(() => props.rows.length > 0);
const isHealthy = computed(() => ['Sehat', 'Cukup Sehat'].includes(props.rating) || props.rating.includes('PK 1') || props.rating.includes('PK 2') || props.rating.includes('PK 3'));
const ratingTone = computed<'success' | 'warning' | 'error'>(() => {
    if (props.rating.includes('PK 5') || props.rating === 'Tidak Sehat') return 'error';
    if (props.rating.includes('PK 4') || props.rating === 'Kurang Sehat') return 'warning';
    return isHealthy.value ? 'success' : 'warning';
});

function statusClass(status: string): string {
    if (['Sehat', 'Memenuhi', 'Positif', 'Asumsi Normal'].includes(status)) return 'bg-secondary/15 text-secondary';
    if (['Cukup Sehat'].includes(status)) return 'bg-tertiary/15 text-tertiary';
    return 'bg-error/15 text-error';
}
</script>

<template>
    <Head :title="title" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('OJK • Regulatory') }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ title }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ framework_label }} · {{ period_label }} · per {{ as_of ?? '-' }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge :tone="ratingTone">{{ rating }}</AppBadge>
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            {{ t('Cetak PDF') }}
                        </AppButton>
                    </a>
                </div>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error"><span class="font-bold">{{ t('Gagal membangun laporan:') }}</span> {{ error }}</p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Tahun') }}</label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Bulan') }}</label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Kerangka POJK') }}</label>
                        <SmartSelect v-model="selectedFramework" :options="frameworkOptions" hide-label @update:model-value="apply" />
                    </div>
                </div>
            </AppCard>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ summary_label }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">
                        {{ composite_score }} <span class="text-sm font-medium text-on-surface-variant">/ {{ composite_max }}</span>
                    </p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Peringkat Komposit') }}</p>
                    <p class="mt-2 text-lg font-bold text-on-surface">{{ rating }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('NPL Neto') }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="values.npl_neto > 5 ? 'text-error' : 'text-secondary'">
                        {{ formatPercent(values.npl_neto) }}
                    </p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Coverage PPAP') }}</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatPercent(values.coverage_ppap) }}</p>
                </AppCard>
            </div>

            <AppCard v-if="supervision_status" class="p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Status Pengawasan POJK 49/2024') }}</p>
                        <p class="mt-1 text-lg font-bold text-on-surface">{{ supervision_status }}</p>
                    </div>
                    <p class="max-w-xl text-xs text-on-surface-variant">{{ supervision_basis }}</p>
                </div>
            </AppCard>

            <AppCard class="p-0">
                <div class="border-b border-outline-variant/30 px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-primary">{{ t('A. Parameter Keuangan Utama') }}</h2>
                </div>
                <div class="grid grid-cols-1 divide-y divide-outline-variant/20 sm:grid-cols-2 sm:divide-y-0">
                    <div v-for="(p, idx) in parameters" :key="p.label" class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm" :class="idx % 2 === 0 ? 'sm:border-r sm:border-outline-variant/20' : ''">
                        <span class="text-on-surface-variant">{{ p.label }}</span>
                        <span class="font-semibold text-on-surface">{{ formatMoney(p.value) }}</span>
                    </div>
                </div>
            </AppCard>

            <AppCard class="overflow-x-auto p-0">
                <div class="border-b border-outline-variant/30 px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-primary">{{ t('B. Matriks Analisis Rasio & Skoring') }}</h2>
                </div>
                <table class="w-full border-collapse text-left text-xs">
                    <thead class="bg-surface-variant/40 text-center font-semibold text-on-surface">
                        <tr class="border-b border-outline-variant/30">
                            <th class="p-2.5" width="4%">{{ t('No') }}</th>
                            <th class="p-2.5" width="15%">{{ t('Aspek') }}</th>
                            <th class="p-2.5" width="21%">{{ t('Rasio') }}</th>
                            <th class="p-2.5" width="24%">{{ t('Parameter') }}</th>
                            <th class="p-2.5" width="18%">{{ t('Klasifikasi / Kriteria') }}</th>
                            <th class="p-2.5 text-right" width="8%">{{ t('Hasil') }}</th>
                            <th class="p-2.5 text-right" width="8%">{{ t('Skor') }}</th>
                            <th class="p-2.5" width="10%">{{ t('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="row in rows" :key="row.no" class="align-top">
                            <td class="p-2.5 text-center font-semibold">{{ row.no }}</td>
                            <td class="p-2.5">{{ row.aspect }}</td>
                            <td class="p-2.5 font-medium text-on-surface">{{ row.ratio }}</td>
                            <td class="p-2.5">
                                <div v-for="d in row.details" :key="d.label" class="flex justify-between gap-3 py-0.5">
                                    <span class="text-on-surface-variant">{{ d.label }}</span>
                                    <span class="whitespace-nowrap font-medium">{{ d.value === null ? '-' : formatMoney(d.value) }}</span>
                                </div>
                            </td>
                            <td class="p-2.5 text-[11px] text-on-surface-variant">
                                <div v-for="c in row.criteria" :key="c">{{ c }}</div>
                            </td>
                            <td class="p-2.5 text-right font-semibold">
                                {{ row.result === null ? '-' : formatPercent(row.result) }}
                            </td>
                            <td class="p-2.5 text-right font-bold">{{ row.score }}</td>
                            <td class="p-2.5 text-center">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusClass(row.status)">
                                    {{ row.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!hasData">
                            <td colspan="8" class="p-6 text-center text-sm text-on-surface-variant">
                                {{ t('Belum ada data untuk periode ini.') }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="hasData" class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="5" class="p-3 text-right">{{ summary_label }}</td>
                            <td colspan="2" class="p-3 text-right text-primary">{{ composite_score }} / {{ composite_max }}</td>
                            <td class="p-3 text-center">{{ rating }}</td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <p class="text-xs text-on-surface-variant">
                Dicetak: {{ generated_at ?? '-' }}. Nilai bersumber dari neraca OJK, laba rugi OJK, kolektibilitas, dan CKPN pada periode terpilih.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
