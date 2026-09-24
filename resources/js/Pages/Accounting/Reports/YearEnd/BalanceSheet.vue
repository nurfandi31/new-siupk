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
    sections: { type: Array, required: true },
    totals: { type: Object, required: true },
    balanced: { type: Boolean, required: true },
    yearOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function fmt(value) {
    if (value < 0) return `(${money.format(Math.abs(value))})`;
    return money.format(value);
}

function sectionTotalLabel(l1) {
    if (l1.account_type === 'asset') return 'Jumlah Aset';
    if (l1.account_type === 'liability') return 'Jumlah Utang';
    if (l1.account_type === 'equity') return 'Jumlah Modal';
    return `Jumlah ${l1.name}`;
}

function applyYear(newYear) {
    router.get(
        '/accounting/year-end/balance-sheet',
        { year: newYear },
        { preserveScroll: true, replace: true },
    );
}

const pdfHref = computed(() => {
    const params = new URLSearchParams();
    params.set('year', String(props.year));
    return `/accounting/year-end/balance-sheet/pdf?${params.toString()}`;
});
</script>

<template>
    <Head title="Neraca Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan · Tutup Buku
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Neraca Tutup Buku</h1>
                    <p class="text-sm text-on-surface-variant">
                        Posisi per {{ as_of }} · Tahun Buku {{ year }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <AppBadge tone="warning">PREVIEW / SIMULASI</AppBadge>
                    <AppBadge :tone="balanced ? 'success' : 'error'">
                        {{ balanced ? 'Aset = Liabilitas+Ekuitas' : 'Tidak seimbang' }}
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
                            Laba Tahun Berjalan (sudah dipindah)
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            :value="money.format(totals.net_income)"
                        />
                    </div>
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Aktiva</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(totals.assets) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pasiva</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ money.format(totals.liabilities_equity) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Selisih</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="Math.abs(totals.assets - totals.liabilities_equity) < 0.01 ? 'text-primary' : 'text-error'"
                    >
                        {{ money.format(totals.assets - totals.liabilities_equity) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="overflow-hidden p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                        <tr>
                            <th class="px-3 py-2 text-left w-28">Kode</th>
                            <th class="px-3 py-2 text-left">Nama Akun</th>
                            <th class="px-3 py-2 text-right w-40">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="sections.length === 0">
                            <td colspan="3" class="px-3 py-8 text-center text-on-surface-variant">
                                Belum ada data neraca.
                            </td>
                        </tr>
                        <template v-for="l1 in sections" :key="l1.code">
                            <tr class="bg-primary text-on-primary">
                                <td class="px-3 py-2 font-bold" colspan="3">{{ l1.code }}. {{ l1.name }}</td>
                            </tr>
                            <template v-for="l2 in l1.children" :key="l2.code">
                                <tr class="bg-surface-container-high font-semibold">
                                    <td class="px-3 py-1.5">{{ l2.code }}</td>
                                    <td class="px-3 py-1.5" colspan="2">{{ l2.name }}</td>
                                </tr>
                                <tr
                                    v-for="l3 in l2.children"
                                    :key="l3.code"
                                    class="border-t border-outline-variant/30"
                                >
                                    <td class="px-3 py-1.5 pl-6 tabular-nums">{{ l3.code }}</td>
                                    <td class="px-3 py-1.5">{{ l3.name }}</td>
                                    <td class="px-3 py-1.5 text-right tabular-nums" :class="l3.balance < 0 ? 'text-error' : ''">
                                        {{ fmt(l3.balance) }}
                                    </td>
                                </tr>
                            </template>
                            <tr class="border-t border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="2">{{ sectionTotalLabel(l1) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums" :class="l1.balance < 0 ? 'text-error' : ''">
                                    {{ fmt(l1.balance) }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-outline bg-surface-container font-bold">
                            <td class="px-3 py-2" colspan="2">Jumlah Liabilitas + Ekuitas</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                {{ money.format(totals.liabilities_equity) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <p class="border-t border-outline-variant/40 px-4 py-3 text-xs text-on-surface-variant">
                    Saldo Laba Ditahan (termasuk laba tahun ini):
                    <span class="font-semibold text-on-surface">
                        {{ money.format(totals.retained_balance) }}
                    </span>
                </p>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">
                    <strong class="text-on-surface">Catatan:</strong> Neraca ini adalah PREVIEW/SIMULASI yang
                    ditampilkan SETELAH jurnal tutup buku diposting. Akun nominal (pendapatan &amp; beban) sudah
                    dinolkan dan laba tahun berjalan sudah direklas ke Laba Ditahan. Posisi akhir sesuai Neraca
                    posisi 31 Desember {{ year }}.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>