<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../Components/AppBadge.vue';
import AppButton from '../Components/AppButton.vue';
import AppCard from '../Components/AppCard.vue';
import AppEmptyState from '../Components/AppEmptyState.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppModal from '../Components/AppModal.vue';
import TrendBarChart from '../Components/TrendBarChart.vue';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    unitName: { type: String, default: null },
    as_of: { type: String, required: true },
    cards: { type: Array, required: true },
    pipeline: { type: Array, required: true },
    trend: { type: Array, required: true },
    recent_journals: { type: Array, required: true },
    upcoming_due: { type: Array, required: true },
    overdue_summary: { type: Object, required: true },
    counts: { type: Object, required: true },
    pipeline_modal: { type: Object, default: null },
    pipeline_modal_key: { type: String, default: null },
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const numberFmt = new Intl.NumberFormat('id-ID');

function formatValue(card) {
    if (card.format === 'money') return money.format(Math.round(Number(card.value || 0)));
    return numberFmt.format(Number(card.value || 0));
}

function formatMoney(value) {
    return money.format(Math.round(Number(value || 0)));
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(`${value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const pipelineTotal = computed(() => props.pipeline.reduce((sum, row) => sum + Number(row.count || 0), 0));

// Two independent states:
//   `pendingKey`  — set the moment the user clicks a pipeline card. The
//                   dialog MUST NOT open until data arrives.
//   `open`        — gated by props.pipeline_modal arrival. Becomes true only
//                   after the server payload lands, so the modal never shows
//                   empty/stale content.
//
// Closing is instant: `closePipeline()` flips `open=false` directly and fires
// a fire-and-forget server cleanup.
const pendingKey = ref(null);
const open = ref(false);

function hasPipelineQuery() {
    if (typeof window === 'undefined') return false;
    return new URL(window.location.href).searchParams.has('pipeline');
}

// Auto-open when the URL requests a modal AND the data has arrived. This
// covers the deep-link / refresh case.
function syncOpenFromServer() {
    if (props.pipeline_modal_key !== null && props.pipeline_modal !== null && hasPipelineQuery()) {
        open.value = true;
        pendingKey.value = null;
    }
}

watch(() => [props.pipeline_modal_key, props.pipeline_modal], () => {
    syncOpenFromServer();
}, { immediate: true });

function openPipeline(stage) {
    pendingKey.value = stage.key ?? stage.status;
    router.get('/dashboard', { pipeline: pendingKey.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['pipeline_modal', 'pipeline_modal_key'],
        onFinish: () => {
            // If the response didn't bring a modal payload (e.g. invalid key),
            // clear pending so we don't sit on a stale request.
            if (props.pipeline_modal_key === null) pendingKey.value = null;
        },
    });
}

function closePipeline() {
    open.value = false;
    pendingKey.value = null;
    // Force-clear any leftover overflow lock from AppModal's own watcher
    // before the server roundtrip completes. Otherwise the page stays
    // un-scrollable until Inertia finishes its partial visit.
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        if (url.searchParams.has('pipeline')) {
            url.searchParams.delete('pipeline');
            window.history.replaceState({}, '', url.toString());
        }
    }
    router.get('/dashboard', {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['pipeline_modal', 'pipeline_modal_key'],
        onFinish: () => {
            if (props.pipeline_modal_key === null) pendingKey.value = null;
        },
    });
}

function amountForRow(row) {
    if (row.allocated_amount !== null && row.allocated_amount !== undefined) return row.allocated_amount;
    if (row.verification_amount !== null && row.verification_amount !== undefined) return row.verification_amount;
    if (row.proposed_amount !== null && row.proposed_amount !== undefined) return row.proposed_amount;
    return row.principal_amount;
}

function dateForRow(row) {
    return row.disbursed_at ?? row.funded_at ?? row.verified_at ?? row.proposed_at ?? null;
}

const quickActions = [
    { label: 'Register Proposal', href: '/lending/loans/create', icon: 'assignment_add' },
    { label: 'Jurnal Angsuran', href: '/accounting/journal-entries/installment', icon: 'payments' },
    { label: 'Jurnal Umum', href: '/accounting/journal-entries/create', icon: 'receipt_long' },
    { label: 'E-Budgeting', href: '/budgeting', icon: 'account_balance_wallet' },
];

const sourceLabel = {
    loan: 'Pinjaman',
    installment: 'Angsuran',
    manual: 'Manual',
    general: 'Umum',
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout :unit-name="unitName">
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Ringkasan operasional</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">
                        {{ unitName || 'Dashboard' }}
                    </h1>
                    <p class="mt-1 text-on-surface-variant">
                        Data live per {{ formatDate(as_of) }} · {{ counts.active_loans }} pinjaman aktif ·
                        {{ counts.members }} anggota
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-for="action in quickActions" :key="action.href" :href="action.href">
                        <AppButton variant="secondary" size="compact" :icon="action.icon">{{ action.label }}</AppButton>
                    </Link>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="KPI utama">
                <AppCard v-for="card in cards" :key="card.key" bordered>
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div
                            class="grid size-10 place-items-center rounded-lg"
                            :class="card.tone === 'error' ? 'bg-error-container text-on-error-container' : 'bg-primary-fixed/40 text-primary'"
                        >
                            <AppIcon :name="card.icon" />
                        </div>
                        <AppBadge v-if="card.tone === 'error'" tone="error">Perhatian</AppBadge>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.tone === 'error' ? 'text-error' : 'text-primary'">
                        {{ formatValue(card) }}
                    </p>
                    <p v-if="card.hint" class="mt-1 text-xs text-on-surface-variant">{{ card.hint }}</p>
                </AppCard>
            </section>

            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
                <section class="card-shadow flex min-h-0 flex-col rounded-xl bg-surface-container-lowest p-6 xl:col-span-2">
                    <header class="mb-4 flex shrink-0 items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Tren 6 Bulan</h2>
                            <p class="text-sm text-on-surface-variant">Pencairan vs penerimaan angsuran</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold text-on-surface-variant">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary" />Cair</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-secondary" />Terima</span>
                        </div>
                    </header>

                    <div v-if="trend.length" class="min-h-[14rem] flex-1">
                        <TrendBarChart :data="trend" />
                    </div>
                    <div v-else class="flex flex-1 items-center">
                        <AppEmptyState icon="show_chart" title="Belum ada tren" description="Pencairan dan angsuran akan tampil di sini." />
                    </div>
                </section>

                <section class="card-shadow flex min-h-0 flex-col rounded-xl bg-surface-container-lowest p-6">
                    <header class="mb-4 flex shrink-0 items-center justify-between">
                        <h2 class="text-lg font-bold text-primary">Pipeline Pinjaman</h2>
                        <AppBadge tone="primary">{{ pipelineTotal }}</AppBadge>
                    </header>
                    <div class="flex flex-1 flex-col justify-between gap-3">
                        <AppButton
                            v-for="stage in pipeline"
                            :key="stage.status"
                            variant="secondary"
                            class="!min-h-0 !justify-between !rounded-xl !px-4 !py-3 !text-left"
                            @click="openPipeline(stage)"
                        >
                            <span>
                                <p class="font-semibold text-primary">{{ stage.label }}</p>
                                <p class="text-xs text-on-surface-variant">{{ formatMoney(stage.amount) }}</p>
                            </span>
                            <span class="text-xl font-bold text-primary">{{ stage.count }}</span>
                        </AppButton>
                    </div>
                </section>
            </div>

            <!-- Jurnal + Jatuh Tempo: fixed max height, internal scroll -->
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
                <section class="card-shadow flex max-h-[28rem] min-h-0 flex-col rounded-xl bg-surface-container-lowest xl:col-span-2">
                    <header class="flex shrink-0 items-center justify-between border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Jurnal Terbaru</h2>
                            <p class="text-sm text-on-surface-variant">Posted, {{ recent_journals.length }} entri terakhir</p>
                        </div>
                        <Link href="/accounting/journal-entries/create">
                            <AppButton variant="ghost" size="compact">Buat jurnal</AppButton>
                        </Link>
                    </header>

                    <div v-if="recent_journals.length" class="min-h-0 flex-1 overflow-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-surface-container-low text-on-surface-variant">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">Tanggal</th>
                                    <th class="px-6 py-3 font-semibold">No / Uraian</th>
                                    <th class="px-6 py-3 font-semibold">Sumber</th>
                                    <th class="px-6 py-3 text-right font-semibold">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in recent_journals"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant"
                                >
                                    <td class="whitespace-nowrap px-6 py-3 text-on-surface-variant">{{ formatDate(row.transaction_date) }}</td>
                                    <td class="px-6 py-3">
                                        <p class="font-semibold text-primary">{{ row.journal_number || `#${row.row_id}` }}</p>
                                        <p class="line-clamp-1 text-xs text-on-surface-variant">{{ row.description || '—' }}</p>
                                    </td>
                                    <td class="px-6 py-3">
                                        <AppBadge tone="neutral">{{ sourceLabel[row.source_type] || row.source_type || '—' }}</AppBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right font-semibold text-primary">
                                        {{ formatMoney(row.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="flex flex-1 items-center p-6">
                        <AppEmptyState icon="receipt_long" title="Belum ada jurnal posted" description="Transaksi yang di-post akan tampil di sini." />
                    </div>
                </section>

                <section class="card-shadow flex max-h-[28rem] min-h-0 flex-col rounded-xl bg-surface-container-lowest">
                    <header class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant px-6 py-4">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-primary">
                            <AppIcon name="event_note" class="text-tertiary" />
                            Jatuh Tempo
                        </h2>
                        <AppBadge v-if="overdue_summary.count" tone="error">{{ overdue_summary.count }} lewat</AppBadge>
                    </header>

                    <div v-if="upcoming_due.length" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 py-4">
                        <article
                            v-for="item in upcoming_due"
                            :key="item.row_id"
                            class="rounded-lg border-l-4 bg-surface-container-low p-3"
                            :class="item.overdue ? 'border-error' : 'border-tertiary'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-primary">{{ item.borrower }}</p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{ item.loan_number || 'Pinjaman' }} · {{ formatDate(item.due_date) }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm font-bold" :class="item.overdue ? 'text-error' : 'text-primary'">
                                    {{ formatMoney(item.amount) }}
                                </p>
                            </div>
                        </article>
                    </div>
                    <div v-else class="flex flex-1 items-center px-6 py-4">
                        <AppEmptyState icon="event_available" title="Tidak ada jatuh tempo 14 hari" />
                    </div>

                    <div class="shrink-0 border-t border-outline-variant px-6 py-4">
                        <Link href="/accounting/journal-entries/installment" class="block">
                            <AppButton variant="secondary" class="w-full" icon="payments">Catat angsuran</AppButton>
                        </Link>
                    </div>
                </section>
            </div>

            <section class="relative overflow-hidden rounded-xl bg-primary p-6 text-on-primary">
                <AppIcon name="verified" class="absolute -bottom-8 -right-5 text-[8rem] text-on-primary/5" />
                <div class="relative space-y-2">
                    <h2 class="text-lg font-bold">Siap operasional</h2>
                    <p class="text-sm leading-6 text-primary-fixed-dim">
                        KPI dihitung dari jurnal posted dan jadwal angsuran pinjaman aktif — tanpa salinan saldo legacy.
                    </p>
                    <Link href="/accounting/tax-estimate" class="inline-flex text-sm font-bold text-on-primary underline-offset-2 hover:underline">
                        Lihat taksiran pajak →
                    </Link>
                </div>
            </section>
        </div>

        <AppModal
            :model-value="open"
            :title="`Pinjaman · ${pipeline_modal?.label ?? ''}`"
            size="lg"
            @update:model-value="(value) => { if (!value) closePipeline(); }"
        >
            <div v-if="pipeline_modal" class="space-y-4">
                <p class="text-sm text-on-surface-variant">
                    Menampilkan
                    <span class="font-semibold text-primary">{{ pipeline_modal.rows.length }}</span>
                    dari
                    <span class="font-semibold text-primary">{{ pipeline_modal.total }}</span>
                    pinjaman pada tahap
                    <span class="font-semibold text-primary">{{ pipeline_modal.label }}</span>.
                </p>

                <div v-if="pipeline_modal.rows.length" class="overflow-hidden rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Kelompok &amp; Desa</th>
                                <th class="px-4 py-3 font-semibold">Tgl</th>
                                <th class="px-4 py-3 text-right font-semibold">Nominal</th>
                                <th class="px-4 py-3 text-right font-semibold">Sisa Pokok</th>
                                <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in pipeline_modal.rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant"
                            >
                                <td class="px-4 py-3 align-top">
                                    <p class="font-semibold text-primary">{{ row.group_name }}</p>
                                    <p v-if="row.group_address" class="mt-0.5 text-xs text-on-surface-variant">{{ row.group_address }}</p>
                                    <p class="mt-0.5 text-[10px] uppercase tracking-wider text-outline">#{{ row.id }} · {{ row.product_code || '—' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top text-on-surface-variant">{{ formatDate(dateForRow(row)) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top font-semibold text-primary">{{ formatMoney(amountForRow(row)) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                    <span v-if="row.principal_remaining > 0" class="font-semibold text-primary">{{ formatMoney(row.principal_remaining) }}</span>
                                    <span v-else class="text-on-surface-variant">—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                    <Link :href="`/lending/loans/${row.row_id}`" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10">Detail →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <AppEmptyState
                    v-else
                    icon="inbox"
                    title="Belum ada pinjaman"
                    :description="`Tidak ada pinjaman pada tahap ${pipeline_modal.label}.`"
                />

                <p v-if="pipeline_modal.total > pipeline_modal.limit" class="text-xs text-on-surface-variant">
                    Preview terbatas {{ pipeline_modal.limit }} baris. Buka halaman penuh untuk melihat semua data.
                </p>
            </div>

            <template #footer>
                <Link v-if="pipeline_modal_key" :href="`/lending/loans?tab=${pipeline_modal_key}`">
                    <AppButton variant="secondary" icon="open_in_new">Lihat semua di Tahapan Perguliran</AppButton>
                </Link>
                <AppButton variant="primary" @click="closePipeline">Tutup</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
