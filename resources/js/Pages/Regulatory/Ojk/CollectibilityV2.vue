<script setup lang="ts">
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

interface BucketMeta {
    code: 1 | 2 | 3 | 4 | 5;
    label: string;
    days_label: string;
    rate_pct: number;
}

interface ProductBucket {
    code: 1 | 2 | 3 | 4 | 5;
    label: string;
    days_label: string;
    rate_pct: number;
    loan_count: number;
    outstanding: number;
    share_pct: number;
    allowance_required: number;
    allowance_formed: number;
    selisih: number;
}

interface ProductReport {
    product_row_id: number;
    product_code: string;
    product_name: string;
    loan_count: number;
    outstanding: number;
    buckets: ProductBucket[];
    allowance_required: number;
    allowance_formed: number;
    selisih: number;
}

interface GrandTotal {
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
    buckets_meta: BucketMeta[];
    products: ProductReport[];
    grand_total: GrandTotal;
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
        1: 'Januari', 2: 'Februari', 3: 'Maret', 4: 'April',
        5: 'Mei', 6: 'Juni', 7: 'Juli', 8: 'Agustus',
        9: 'September', 10: 'Oktober', 11: 'November', 12: 'Desember',
    };
    for (let m = 1; m <= 12; m++) {
        opts.push({ value: String(m), label: labels[m] });
    }
    return opts;
});

const scopeOptions = [
    { value: 'all', label: 'Semua' },
    { value: 'group', label: 'Kelompok' },
    { value: 'member', label: 'Individu' },
];

/* -------------------------------------------------------------------------- */
/*                                Computed                                    */
/* -------------------------------------------------------------------------- */

const hasData = computed<boolean>(() => props.products.length > 0);

function fmt(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : Number(value ?? 0);
    if (!Number.isFinite(n)) return money.format(0);
    if (n < 0) return `(${money.format(Math.abs(n))})`;
    return money.format(n);
}

function apply(): void {
    router.get(
        '/regulatory/ojk/collectibility-v2',
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
    return `/regulatory/ojk/collectibility-v2/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Kolektibilitas OJK v2 (KBP2)" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        OJK · Regulatory
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Kolektibilitas OJK v2 (KBP2)</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ props.period_label }} · per {{ props.as_of ?? '-' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <AppBadge :tone="hasData ? 'success' : 'warning'">
                        {{ hasData ? `${props.products.length} produk` : 'Belum ada data' }}
                    </AppBadge>
                    <a :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline">
                            <span class="material-symbols-outlined mr-1.5 text-base">picture_as_pdf</span>
                            Cetak PDF
                        </AppButton>
                    </a>
                </div>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">Gagal membangun laporan:</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Tahun
                        </label>
                        <SmartSelect v-model="selectedYear" :options="yearOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Bulan
                        </label>
                        <SmartSelect v-model="selectedMonth" :options="monthOptions" hide-label @update:model-value="apply" />
                    </div>
                    <div class="space-y-1.5">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">
                            Jenis Pinjaman
                        </label>
                        <SmartSelect v-model="selectedScope" :options="scopeOptions" hide-label @update:model-value="apply" />
                    </div>
                </div>
            </AppCard>

            <!-- Grand Total KPI -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Pinjaman</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ Number(props.grand_total.loan_count) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Outstanding</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.grand_total.outstanding) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Cadangan Wajib</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.grand_total.allowance_required) }}</p>
                </AppCard>
                <AppCard class="p-4">
                    <p class="text-xs uppercase tracking-wider text-on-surface-variant">Total Cadangan Dibentuk</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ fmt(props.grand_total.allowance_formed) }}</p>
                </AppCard>
            </div>

            <!-- Per produk -->
            <div v-if="hasData" class="space-y-4">
                <div v-for="prod in props.products" :key="prod.product_row_id" class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-on-surface">
                            {{ prod.product_name }} ({{ prod.product_code }})
                        </h2>
                        <span class="text-xs text-on-surface-variant">
                            {{ Number(prod.loan_count) }} pinjaman ·
                            outstanding {{ fmt(prod.outstanding) }}
                        </span>
                    </div>

                    <AppCard class="overflow-x-auto p-0">
                        <table class="w-full text-sm border-collapse">
                            <thead class="bg-surface-variant/40 text-on-surface font-semibold">
                                <tr class="border-b border-outline-variant/30 text-center">
                                    <th rowspan="2" class="p-3 text-left">Golongan</th>
                                    <th rowspan="2" class="p-3 text-left">Hari</th>
                                    <th colspan="2" class="p-3 border-l border-outline-variant/20">Posisi Pinjaman</th>
                                    <th colspan="3" class="p-3 border-l border-outline-variant/20">CKPN</th>
                                </tr>
                                <tr class="border-b border-outline-variant/30 text-center text-[11px] text-on-surface-variant">
                                    <th class="p-2 border-l border-outline-variant/20 text-right">Jumlah</th>
                                    <th class="p-2 text-right">Outstanding</th>
                                    <th class="p-2 border-l border-outline-variant/20 text-right">%</th>
                                    <th class="p-2 text-right">Wajib</th>
                                    <th class="p-2 text-right">Dibentuk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/20">
                                <tr v-for="bk in prod.buckets" :key="bk.code" class="hover:bg-surface-variant/20">
                                    <td class="p-3 font-medium">{{ bk.label }}</td>
                                    <td class="p-3">{{ bk.days_label }}</td>
                                    <td class="p-3 text-right tabular-nums border-l border-outline-variant/20">
                                        {{ Number(bk.loan_count) }}
                                    </td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(bk.outstanding) }}</td>
                                    <td class="p-3 text-right tabular-nums border-l border-outline-variant/20">
                                        {{ Number(bk.rate_pct).toFixed(2) }}%
                                    </td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(bk.allowance_required) }}</td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(bk.allowance_formed) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-surface-variant/40 font-bold text-on-surface">
                                <tr class="border-t border-outline-variant/40">
                                    <td class="p-3" colspan="3">TOTAL {{ prod.product_code }}</td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(prod.outstanding) }}</td>
                                    <td class="p-3 text-right tabular-nums border-l border-outline-variant/20">
                                        {{ Number(prod.allowance_required).toFixed(2) }}
                                    </td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(prod.allowance_required) }}</td>
                                    <td class="p-3 text-right tabular-nums">{{ fmt(prod.allowance_formed) }}</td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="p-3 text-right text-on-surface-variant">Selisih:</td>
                                    <td class="p-3 text-right">
                                        <AppBadge :tone="prod.selisih >= 0 ? 'success' : 'error'">
                                            {{ fmt(prod.selisih) }}
                                        </AppBadge>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </AppCard>
                </div>
            </div>

            <p v-if="!hasData" class="text-center text-sm text-on-surface-variant">
                Belum ada data pinjaman aktif untuk periode ini.
            </p>

            <p v-if="generated_at" class="text-right text-[11px] text-on-surface-variant">
                Digenerate pada {{ generated_at }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
