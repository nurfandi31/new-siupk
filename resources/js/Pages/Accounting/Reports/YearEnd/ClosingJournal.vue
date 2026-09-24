<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AuthenticatedLayout from '../../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    is_preview: { type: Boolean, default: true },
    year: { type: Number, required: true },
    entries: { type: Array, required: true },
    rows: { type: Array, required: true },
    revenue_rows: { type: Array, required: true },
    expense_rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    summary_account: { type: Object, required: true },
    retained_account: { type: Object, required: true },
    balanced: { type: Boolean, required: true },
    row_count: { type: Number, required: true },
    yearOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function applyYear(newYear) {
    router.get(
        '/accounting/year-end/journal',
        { year: newYear },
        { preserveScroll: true, replace: true },
    );
}

const pdfHref = computed(() => {
    const params = new URLSearchParams();
    params.set('year', String(props.year));
    return `/accounting/year-end/journal/pdf?${params.toString()}`;
});
</script>

<template>
    <Head title="Jurnal Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan · Tutup Buku
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Jurnal Tutup Buku</h1>
                    <p class="text-sm text-on-surface-variant">
                        Tahun Buku {{ year }} · 31 Desember {{ year }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge tone="warning">PREVIEW / SIMULASI</AppBadge>
                    <AppBadge :tone="balanced ? 'success' : 'error'">
                        {{ balanced ? 'Debit = Kredit' : 'Tidak seimbang' }}
                    </AppBadge>
                    <a :href="pdfHref" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                    </a>
                </div>
            </div>

            <AppCard class="p-4">
                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                            Tahun Buku
                        </label>
                        <select
                            class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm"
                            :value="filters.year"
                            @change="applyYear(Number($event.target.value))"
                        >
                            <option v-for="opt in yearOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                            Total Pendapatan
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            :value="money.format(totals.revenue)"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                            Total Beban
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            :value="money.format(totals.expense)"
                        />
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Surplus/(Defisit)</p>
                    <p class="mt-2 text-xl font-bold" :class="totals.surplus < 0 ? 'text-error' : 'text-primary'">
                        {{ money.format(totals.surplus) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Debit</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(totals.debit) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Kredit</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(totals.credit) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jumlah Baris</p>
                    <p class="mt-2 text-xl font-bold">{{ row_count }}</p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 w-12">No</th>
                                <th class="px-3 py-2 w-28">Tanggal</th>
                                <th class="px-3 py-2 w-32">Ref</th>
                                <th class="px-3 py-2 w-24">Kode</th>
                                <th class="px-3 py-2">Nama Akun</th>
                                <th class="px-3 py-2">Keterangan</th>
                                <th class="px-3 py-2 text-right w-32">Debit</th>
                                <th class="px-3 py-2 text-right w-32">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows" :key="row.no ?? row.entry_no">
                                <tr v-if="row.is_header" class="bg-primary/10">
                                    <td colspan="8" class="px-3 py-2 font-semibold text-primary">
                                        Entry #{{ row.entry_no }} · {{ row.reference }} — {{ row.description }}
                                    </td>
                                </tr>
                                <tr v-else class="border-t border-outline-variant/40">
                                    <td class="px-3 py-1.5 tabular-nums">{{ row.no }}</td>
                                    <td class="px-3 py-1.5 tabular-nums">{{ row.date }}</td>
                                    <td class="px-3 py-1.5 font-mono text-xs">{{ row.reference }}</td>
                                    <td class="px-3 py-1.5 font-medium tabular-nums">{{ row.account_code }}</td>
                                    <td class="px-3 py-1.5">{{ row.account_name }}</td>
                                    <td class="px-3 py-1.5 text-xs text-on-surface-variant">{{ row.memo }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">
                                        {{ row.debit ? money.format(row.debit) : '' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">
                                        {{ row.credit ? money.format(row.credit) : '' }}
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td colspan="6" class="px-3 py-2">Total Jurnal Tutup Buku</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.debit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.credit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Ringkasan Proses
                </h2>
                <ol class="list-decimal space-y-2 pl-5 text-sm text-on-surface">
                    <li>
                        <strong>Entry 1 — Tutup Pendapatan:</strong>
                        Debit akun pendapatan ({{ revenue_rows.length }} akun, total {{ money.format(totals.revenue) }}),
                        Kredit {{ summary_account.code }} Ikhtisar Laba Rugi.
                    </li>
                    <li>
                        <strong>Entry 2 — Tutup Beban:</strong>
                        Debit {{ summary_account.code }} Ikhtisar Laba Rugi ({{ money.format(totals.expense) }}),
                        Kredit akun beban ({{ expense_rows.length }} akun).
                    </li>
                    <li>
                        <strong>Entry 3 — Pindahkan Saldo ke Laba Ditahan:</strong>
                        Saldo {{ summary_account.code }} ({{ money.format(Math.abs(totals.surplus)) }})
                        {{ totals.surplus >= 0 ? 'dikreditkan ke' : 'di-debit dari' }}
                        {{ retained_account.code }} Laba Ditahan.
                    </li>
                </ol>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">
                    <strong class="text-on-surface">Catatan:</strong> Jurnal ini adalah PREVIEW/SIMULASI yang
                    di-generate otomatis dari saldo akun nominal per 31 Desember {{ year }}. Jurnal sebenarnya akan
                    di-posting oleh modul Tutup Buku (PeriodClose) jika administrator menjalankan proses tutup tahun.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>