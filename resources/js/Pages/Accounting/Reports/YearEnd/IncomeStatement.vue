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
    as_of: { type: String, required: true },
    groups: { type: Array, required: true },
    summary: { type: Object, required: true },
    kpi: { type: Object, required: true },
    notes: { type: String, default: '' },
    yearOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function applyYear(newYear) {
    router.get(
        '/accounting/year-end/income-statement',
        { year: newYear },
        { preserveScroll: true, replace: true },
    );
}

const pdfHref = computed(() => {
    const params = new URLSearchParams();
    params.set('year', String(props.year));
    return `/accounting/year-end/income-statement/pdf?${params.toString()}`;
});
</script>

<template>
    <Head title="Laba Rugi Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan · Tutup Buku
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Laporan Laba Rugi Tutup Buku</h1>
                    <p class="text-sm text-on-surface-variant">
                        Tahun Buku {{ year }} · per {{ as_of }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge tone="warning">PREVIEW / SIMULASI</AppBadge>
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
                            Tanggal As-of
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            :value="as_of"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                            Status Akun Nominal
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            value="Bersaldo 0 setelah tutup buku"
                        />
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pendapatan</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(kpi.total_revenue) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Beban</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(kpi.total_expense) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Surplus/(Defisit)</p>
                    <p class="mt-2 text-xl font-bold" :class="kpi.surplus_deficit < 0 ? 'text-error' : 'text-primary'">
                        {{ money.format(kpi.surplus_deficit) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Margin</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="kpi.margin_pct < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ kpi.margin_pct.toFixed(2) }}%
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Rekening</th>
                                <th class="px-3 py-2 text-right">Tahun {{ year }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="groups.length === 0">
                                <td colspan="2" class="px-3 py-8 text-center text-on-surface-variant">
                                    Belum ada pendapatan/beban.
                                </td>
                            </tr>
                            <template v-for="group in groups" :key="group.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-2" colspan="2">{{ group.code }}. {{ group.name }}</td>
                                </tr>
                                <tr
                                    v-for="row in group.children"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5 pl-6">
                                        <span class="font-medium">{{ row.code }}</span>
                                        <span class="text-on-surface-variant"> · {{ row.name }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">
                                        {{ money.format(row.ytd) }}
                                    </td>
                                </tr>
                                <tr class="bg-surface-container-low text-sm font-semibold">
                                    <td class="px-3 py-1.5">Jumlah {{ group.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums">{{ money.format(group.ytd) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="font-semibold">
                            <tr class="border-t-2 border-outline bg-surface-container">
                                <td class="px-3 py-2">A. Laba Rugi OPERASIONAL (Pendapatan Ops − Beban Ops)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.operating) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">B. Laba Rugi NON OPERASIONAL</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.non_operating) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">C. Laba Rugi Sebelum Taksiran Pajak (A + B)</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.before_tax) }}</td>
                            </tr>
                            <tr class="bg-surface-container">
                                <td class="px-3 py-2">Beban Pajak</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.tax) }}</td>
                            </tr>
                            <tr class="bg-primary/10 text-primary">
                                <td class="px-3 py-2">D. Laba (Rugi) Bersih</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(summary.after_tax) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">{{ notes }}</p>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">
                    <strong class="text-on-surface">Catatan:</strong> Laba Rugi tutup buku menampilkan ringkasan
                    akhir tahun. Setelah jurnal tutup buku diposting, akun nominal (pendapatan &amp; beban)
                    bersaldo 0 dan saldonya pindah ke Ikhtisar Laba Rugi lalu ke Laba Ditahan.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>