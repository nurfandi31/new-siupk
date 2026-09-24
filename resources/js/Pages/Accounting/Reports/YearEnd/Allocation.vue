<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppTextarea from '../../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    is_preview: { type: Boolean, default: true },
    year: { type: Number, required: true },
    surplus: { type: Number, required: true },
    lines: { type: Array, required: true },
    totals: { type: Object, required: true },
    notes: { type: String, default: '' },
    default_lines: { type: Array, required: true },
    account_targets: { type: Object, required: true },
    summary: { type: Object, required: true },
    yearOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const pctForm = useForm({
    year: props.year,
    notes: props.notes || '',
    percentages: Object.fromEntries(props.lines.map((l) => [l.key, l.percentage])),
});

function applyYear(newYear) {
    router.get(
        '/accounting/year-end/allocation',
        { year: newYear },
        { preserveScroll: true, replace: true },
    );
}

const pdfHref = computed(() => {
    const params = new URLSearchParams();
    params.set('year', String(props.year));
    return `/accounting/year-end/allocation/pdf?${params.toString()}`;
});

function saveNotes() {
    pctForm.year = props.year;
    pctForm.put('/accounting/year-end/allocation/notes', { preserveScroll: true });
}
</script>

<template>
    <Head title="Alokasi Laba Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan · Tutup Buku
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Alokasi Laba</h1>
                    <p class="text-sm text-on-surface-variant">
                        Tahun Buku {{ year }} · Simulasi alokasi surplus laba
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge tone="warning">PREVIEW / SIMULASI</AppBadge>
                    <AppBadge :tone="totals.remaining < 0.01 ? 'success' : 'error'">
                        Teralokasi {{ totals.pct_allocated.toFixed(2) }}%
                    </AppBadge>
                    <a :href="pdfHref" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                    </a>
                </div>
            </div>

            <AppCard class="p-4">
                <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-end lg:gap-3">
                    <div class="grid w-full flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
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
                                Total Surplus (Laba Ditahan)
                            </label>
                            <input
                                readonly
                                class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                                :value="money.format(surplus)"
                            />
                        </div>
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Surplus</p>
                    <p class="mt-2 text-xl font-bold" :class="summary.total_surplus < 0 ? 'text-error' : 'text-primary'">
                        {{ money.format(summary.total_surplus) }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Dialokasikan</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(summary.total_allocated) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Persentase Teralokasi</p>
                    <p class="mt-2 text-xl font-bold" :class="summary.pct_allocated < 99.9 ? 'text-error' : 'text-primary'">
                        {{ summary.pct_allocated.toFixed(2) }}%
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-12">No</th>
                                <th class="px-3 py-2 text-left">Uraian</th>
                                <th class="px-3 py-2 text-right w-32">Persentase (%)</th>
                                <th class="px-3 py-2 text-right w-40">Nominal (Rp)</th>
                                <th class="px-3 py-2 text-left w-64">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in lines" :key="line.key" class="border-t border-outline-variant/30">
                                <td class="px-3 py-2 tabular-nums">{{ line.no }}</td>
                                <td class="px-3 py-2 font-medium">
                                    {{ line.label }}
                                    <span v-if="line.key === 'ditahan'" class="ml-1 text-xs italic text-on-surface-variant">
                                        (sisa)
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ line.percentage.toFixed(2) }}%
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money.format(line.amount) }}
                                </td>
                                <td class="px-3 py-2 text-xs text-on-surface-variant">{{ line.note }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td colspan="2" class="px-3 py-2">Total Dialokasikan</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ totals.pct_allocated.toFixed(2) }}%</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ money.format(totals.allocated) }}</td>
                                <td class="px-3 py-2"></td>
                            </tr>
                            <tr class="bg-surface-container font-bold">
                                <td colspan="2" class="px-3 py-2">Sisa (belum dialokasikan)</td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ (100 - totals.pct_allocated).toFixed(2) }}%
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="totals.remaining < 0.01 ? 'text-primary' : 'text-error'">
                                    {{ money.format(totals.remaining) }}
                                </td>
                                <td class="px-3 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Akun Tujuan (referensi jurnal alokasi)
                </h2>
                <ul class="space-y-2 text-sm">
                    <li v-for="(acc, key) in account_targets" :key="key" class="flex justify-between">
                        <span class="text-on-surface-variant">{{ key }}:</span>
                        <span class="font-medium">{{ acc.code }} · {{ acc.name || '(tidak ada)' }}</span>
                    </li>
                </ul>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">Catatan manajemen</h2>
                <form class="space-y-3" @submit.prevent="saveNotes">
                    <AppTextarea
                        v-model="pctForm.notes"
                        label="Catatan proses / kebijakan alokasi"
                        :error="pctForm.errors.notes"
                        placeholder="Misal: Persentase sesuai keputusan RAT, catatan tentang proses…"
                    />
                    <div class="flex justify-end">
                        <AppButton type="submit" icon="save" :loading="pctForm.processing">Simpan catatan</AppButton>
                    </div>
                </form>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">
                    <strong class="text-on-surface">Catatan:</strong> Laporan ini adalah PREVIEW/SIMULASI.
                    Jurnal alokasi sebenarnya diproses oleh modul Tutup Buku (PeriodClose) setelah pengesahan RAT.
                    Pos &ldquo;Laba Ditahan (sisa)&rdquo; dihitung otomatis sebagai sisa surplus yang
                    belum dialokasikan ke pos lain.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>