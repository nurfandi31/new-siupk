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

/* -------------------------------------------------------------------------- */
/*                                  Types                                     */
/* -------------------------------------------------------------------------- */

interface IdentityBag {
    legal_name: string;
    short_name?: string | null;
    logo_url?: string | null;
    registration_number?: string;
    address?: string;
    phone?: string;
    district_name?: string;
    regency_name?: string;
    manager_name?: string;
    manager_title?: string;
    treasurer_name?: string;
    treasurer_title?: string;
}

interface CollectibilityBucket {
    code: 1 | 2 | 3 | 4 | 5;
    label: string;
    days_min: number;
    days_max: number | null;
    days_label: string;
    rate: number;
    rate_pct: number;
    loan_count: number;
    outstanding: number;
    share_pct: number;
    allowance_required: number;
    allowance_formed: number;
    selisih: number;
}

interface TotalsBag {
    loan_count: number;
    outstanding: number;
    allowance_required: number;
    allowance_formed: number;
    selisih: number;
}

interface FiltersBag {
    year: number;
    month: number;
    scope: 'all' | 'group' | 'member';
}

const props = defineProps<{
    year: number;
    month: number;
    period_label: string;
    as_of: string | null;
    identity: IdentityBag;
    borrower_scope: 'all' | 'group' | 'member';
    buckets: CollectibilityBucket[];
    rows: unknown[];
    totals: TotalsBag;
    generated_at: string | null;
    tenant_id: number;
    filters: FiltersBag;
    error?: string;
}>();

/* -------------------------------------------------------------------------- */
/*                                 State                                      */
/* -------------------------------------------------------------------------- */

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedScope = ref<string>(props.filters.scope ?? 'all');

const money = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const monthOptions = computed(() => {
    const opts: { value: string; label: string }[] = [];
    const labels: Record<number, string> = {
         1: t('Januari'), 2: t('Februari'), 3: t('Maret'), 4: t('April'),
         5: t('Mei'), 6: t('Juni'), 7: t('Juli'), 8: t('Agustus'),
         9: t('September'), 10: t('Oktober'), 11: t('November'), 12: t('Desember'),
    };
    for (let m = 1; m <= 12; m++) {
        opts.push({ value: String(m), label: labels[m] });
    }
    return opts;
});

const scopeOptions = [
    { value: 'all', label: t('Semua') },
    { value: 'group', label: t('Kelompok') },
    { value: 'member', label: t('Individu') },
];

/* -------------------------------------------------------------------------- */
/*                                Computed                                    */
/* -------------------------------------------------------------------------- */

const hasData = computed<boolean>(() => props.buckets.length > 0);

const toneForSelisih = (selisih: number): 'success' | 'error' | 'warning' => {
    if (selisih >= 0) return 'success';
    return 'error';
};

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function apply(): void {
    router.get(
        '/regulatory/ojk/collectibility',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            scope: selectedScope.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed<string>(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        scope: selectedScope.value,
    });
    return `/regulatory/ojk/collectibility/pdf?${q.toString()}`;
});
</script>

<template>
    <Head :title="t('Kolektibilitas OJK (KBP)')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        {{ t('OJK · Regulatory') }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ t('Kolektibilitas OJK (KBP)') }}</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ props.period_label }} {{ t('· per') }} {{ props.as_of ?? '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <AppBadge :tone="hasData ? 'success' : 'warning'">
                        {{ hasData ? t('Data tersedia') : t('Belum ada data') }}
                    </AppBadge>
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            {{ t('Cetak PDF') }}
                        </AppButton>
                    </a>
                </div>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">{{ t('Gagal membangun laporan:') }}</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            {{ t('Tahun') }}
                        </label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            {{ t('Bulan') }}
                        </label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            {{ t('Jenis Pinjaman') }}
                        </label>
                        <SmartSelect v-model="selectedScope" :options="scopeOptions" hide-label @update:model-value="apply" />
                    </div>
                </div>
            </AppCard>

            <!-- KPI -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">{{ t('Total Pinjaman') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ Number(props.totals.loan_count ?? 0) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">{{ t('Total Outstanding') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.totals.outstanding) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">{{ t('Cadangan Wajib') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.totals.allowance_required) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">{{ t('Cadangan Dibentuk') }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.totals.allowance_formed) }}</p>
                </AppCard>
            </div>

            <!-- Tabel kolektibilitas -->
            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant/40 bg-surface-container-low px-4 py-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ t('Klasifikasi Kolektibilitas OJK (5 Golongan)') }}
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">{{ t('No') }}</th>
                                <th class="px-3 py-2 text-left">{{ t('Golongan') }}</th>
                                <th class="px-3 py-2 text-left">{{ t('Hari Keterlambatan') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('Jumlah Pinjaman') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('Outstanding') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('% Portofolio') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('% Penyisihan') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('Cadangan Wajib') }}</th>
                                <th class="px-3 py-2 text-right">{{ t('Cadangan Dibentuk') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!hasData">
                                <td colspan="9" class="px-3 py-8 text-center text-on-surface-variant">
                                    {{ t('Belum ada data pinjaman aktif untuk periode ini.') }}
                                </td>
                            </tr>
                            <tr v-for="(b, idx) in props.buckets" :key="b.code" class="border-t border-outline-variant/20">
                                <td class="px-3 py-2 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-2 font-semibold">{{ b.label }}</td>
                                <td class="px-3 py-2">{{ b.days_label }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Number(b.loan_count) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(b.outstanding) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Number(b.share_pct).toFixed(2) }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Number(b.rate_pct).toFixed(2) }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(b.allowance_required) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(b.allowance_formed) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td colspan="3" class="px-3 py-2">TOTAL</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ Number(props.totals.loan_count) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.outstanding) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">100.00%</td>
                                <td></td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.allowance_required) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ fmt(props.totals.allowance_formed) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="border-t border-outline-variant/40 bg-surface-container-lowest px-4 py-3 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-on-surface-variant">{{ t('Selisih (Dibentuk − Wajib):') }}</span>
                        <AppBadge :tone="toneForSelisih(Number(props.totals.selisih))">
                            {{ fmt(props.totals.selisih) }}
                        </AppBadge>
                    </div>
                </div>
            </AppCard>

            <p class="text-xs text-on-surface-variant">
                <b>{{ t('Keterangan:') }}</b> {{ t('Cadangan Wajib dihitung dari outstanding × tarif OJK (Lancar 0.5% / DPK 3% / Kurang Lancar 10% / Diragukan 50% / Macet 100%). Cadangan Dibentuk adalah saldo akun 1.1.04.* (Cadangan Kerugian Piutang) yang didistribusikan secara proporsional ke tiap golongan.') }}
            </p>

            <p v-if="generated_at" class="text-right text-[11px] text-on-surface-variant">
                {{ t('Digenerate pada') }} {{ generated_at }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
