<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface NearSettlementRow {
    loan_row_id: number;
    loan_id: number;
    loan_number: string;
    product_code: string;
    product_name: string;
    borrower_kind: 'Individu' | 'Kelompok';
    borrower_name: string;
    borrower_identifier: string | null;
    village_name: string | null;
    address: string | null;
    phone: string | null;
    term_months: number;
    principal: number;
    remaining_principal: number;
    installment_number: number;
    remaining_tenor: number;
    next_due_date: string;
    estimated_settlement_date: string | null;
    days_to_next_due: number;
    is_overdue: boolean;
    status: string;
    status_tone: 'success' | 'warning' | 'error' | 'neutral';
}

interface NearSettlementTotals {
    count: number;
    principal_total: number;
    remaining_principal_total: number;
    remaining_tenor_sum: number;
    remaining_tenor_avg: number;
}

const props = defineProps<{
    as_of: string;
    horizon_end: string;
    horizon_days: number;
    max_remaining_tenor: number;
    borrower_scope: string | null;
    period_label: string;
    identity: { legal_name: string; short_name: string | null };
    rows: NearSettlementRow[];
    totals: NearSettlementTotals;
    filters: { as_of: string; horizon_days: number; scope: string };
}>();

const selectedAsOf = ref(props.filters.as_of || props.as_of);
const selectedHorizon = ref(String(props.filters.horizon_days || props.horizon_days || 90));
const selectedScope = ref(props.filters.scope || 'all');

const horizonOptions = [
    { value: '30', label: '30 hari ke depan' },
    { value: '60', label: '60 hari ke depan' },
    { value: '90', label: '90 hari ke depan' },
    { value: '120', label: '120 hari ke depan' },
    { value: '180', label: '180 hari ke depan' },
];

const scopeOptions = [
    { value: 'all', label: 'Semua (Kelompok + Individu)' },
    { value: 'group', label: 'Kelompok saja' },
    { value: 'member', label: 'Individu saja' },
];

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const formatMoney = (v: number | string | null | undefined): string => money.format(Number(v ?? 0));

function apply(): void {
    router.get(
        '/lending/reports/near-settlement',
        {
            as_of: selectedAsOf.value,
            horizon_days: selectedHorizon.value,
            scope: selectedScope.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        as_of: selectedAsOf.value,
        horizon_days: selectedHorizon.value,
        scope: selectedScope.value,
    });
    return `/lending/reports/near-settlement/pdf?${q.toString()}`;
});

function toneClass(tone: string): string {
    if (tone === 'success') return 'bg-emerald-100 text-emerald-800';
    if (tone === 'warning') return 'bg-amber-100 text-amber-800';
    if (tone === 'error') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-700';
}

function formatDate(s: string | null | undefined): string {
    if (!s) return '—';
    return s;
}
</script>

<template>
    <Head title="Pinjaman Mendekati Jatuh Tempo" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Lending — Operasional</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Pinjaman Mendekati Jatuh Tempo</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ period_label }} — {{ identity.legal_name }}
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

            <!-- Filters -->
            <AppCard class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Tanggal Acuan (as_of)</label>
                        <input
                            v-model="selectedAsOf"
                            type="date"
                            class="block w-full rounded-lg border border-outline-variant bg-surface px-3 py-1.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                            @change="apply"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Horizon (hari ke depan)</label>
                        <SmartSelect v-model="selectedHorizon" :options="horizonOptions" @update:model-value="apply" hide-label />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Subjek Pinjaman</label>
                        <SmartSelect v-model="selectedScope" :options="scopeOptions" @update:model-value="apply" hide-label />
                    </div>
                </div>
            </AppCard>

            <!-- KPI tiles -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Pinjaman</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.count }}</p>
                    <p class="mt-1 text-[11px] text-on-surface-variant">Sisa tenor ≤ {{ max_remaining_tenor }} angsuran</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.principal_total) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Sisa Pokok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.remaining_principal_total) }}</p>
                    <p class="mt-1 text-[11px] text-on-surface-variant">Estimasi kembali ke kas</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Rata-rata Sisa Tenor</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.remaining_tenor_avg }}</p>
                    <p class="mt-1 text-[11px] text-on-surface-variant">angsuran</p>
                </AppCard>
            </div>

            <!-- Context banner -->
            <AppCard class="p-4 bg-surface-container-low">
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div>
                        <span class="font-bold text-primary">Konteks:</span>
                        <span class="text-on-surface">
                            Pinjaman aktif dengan angsuran berikutnya jatuh tempo antara
                            <strong>{{ formatDate(as_of) }}</strong> s.d. <strong>{{ formatDate(horizon_end) }}</strong>
                        </span>
                    </div>
                    <div class="text-xs text-on-surface-variant">
                        Sort: <span class="font-semibold text-primary">tgl jatuh tempo paling dekat di atas</span>
                    </div>
                </div>
            </AppCard>

            <!-- Table -->
            <AppCard class="overflow-x-auto p-0">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                        <tr class="border-b border-outline-variant/30 text-center">
                            <th class="p-3 text-left">No</th>
                            <th class="p-3 text-left">No. Kontrak</th>
                            <th class="p-3 text-left">Subjek</th>
                            <th class="p-3 text-left">Peminjam / Kelompok</th>
                            <th class="p-3 text-left">Alamat</th>
                            <th class="p-3 text-left">Telpon</th>
                            <th class="p-3 text-right">Pokok</th>
                            <th class="p-3 text-right">Sisa Pokok</th>
                            <th class="p-3 text-center">Jangka (bln)</th>
                            <th class="p-3 text-center">Sisa Tenor</th>
                            <th class="p-3 text-center">Tgl Jatuh Tempo Berikutnya</th>
                            <th class="p-3 text-center">Sisa Waktu (hari)</th>
                            <th class="p-3 text-center">Tgl Estimasi Lunas</th>
                            <th class="p-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/20">
                        <tr v-for="(row, idx) in rows" :key="row.loan_row_id" class="hover:bg-surface-variant/10">
                            <td class="p-2.5 px-3 text-center">{{ idx + 1 }}</td>
                            <td class="p-2.5 font-medium text-on-surface">{{ row.loan_number }}</td>
                            <td class="p-2.5">
                                <AppBadge :tone="row.borrower_kind === 'Individu' ? 'info-soft' : 'success-soft'">
                                    {{ row.borrower_kind }}
                                </AppBadge>
                            </td>
                            <td class="p-2.5">
                                <div class="font-medium text-on-surface">{{ row.borrower_name }}</div>
                                <div class="text-[11px] text-on-surface-variant">
                                    {{ row.borrower_identifier || '—' }}<span v-if="row.village_name"> • {{ row.village_name }}</span>
                                </div>
                            </td>
                            <td class="p-2.5 text-[11px] text-on-surface-variant">{{ row.address || '—' }}</td>
                            <td class="p-2.5 text-[11px] text-on-surface-variant">{{ row.phone || '—' }}</td>
                            <td class="p-2.5 text-right">{{ formatMoney(row.principal) }}</td>
                            <td class="p-2.5 text-right font-semibold text-primary">{{ formatMoney(row.remaining_principal) }}</td>
                            <td class="p-2.5 text-center">{{ row.term_months || 0 }}</td>
                            <td class="p-2.5 text-center">{{ row.installment_number }}</td>
                            <td class="p-2.5 text-center font-semibold">{{ row.remaining_tenor }}</td>
                            <td class="p-2.5 text-center" :class="row.is_overdue ? 'text-error font-semibold' : 'text-on-surface'">
                                {{ formatDate(row.next_due_date) }}
                            </td>
                            <td class="p-2.5 text-center" :class="row.is_overdue ? 'text-error font-semibold' : 'text-on-surface-variant'">
                                {{ row.is_overdue ? `Overdue ${row.days_to_next_due}` : row.days_to_next_due }}
                            </td>
                            <td class="p-2.5 text-center">{{ formatDate(row.estimated_settlement_date) }}</td>
                            <td class="p-2.5 text-center">
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase', toneClass(row.status_tone)]">
                                    {{ row.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length > 0" class="bg-surface-variant/40 font-bold text-on-surface">
                        <tr class="border-t border-outline-variant/40">
                            <td colspan="6" class="p-3">TOTAL</td>
                            <td class="p-3 text-right">{{ formatMoney(totals.principal_total) }}</td>
                            <td class="p-3 text-right text-primary">{{ formatMoney(totals.remaining_principal_total) }}</td>
                            <td colspan="5" class="p-3 text-center text-on-surface-variant">
                                {{ totals.count }} pinjaman
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </AppCard>

            <AppCard v-if="rows.length === 0" class="p-8 text-center">
                <AppEmptyState
                    icon="event_available"
                    title="Tidak ada pinjaman mendekati jatuh tempo"
                    description="Tidak ada pinjaman aktif dengan sisa tenor ≤ 3 angsuran yang jatuh tempo dalam horizon yang dipilih."
                />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>