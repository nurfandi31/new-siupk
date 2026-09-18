<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../Components/AppBadge.vue';
import AppButton from '../Components/AppButton.vue';
import AppCard from '../Components/AppCard.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppModal from '../Components/AppModal.vue';
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

const trendSummary = computed(() => {
    if (!props.trend.length) return { cair: 0, terima: 0, delta: 0, months: 0 };
    const cair = props.trend.reduce((s, r) => s + Number(r.cair ?? r.disbursed ?? r.amount ?? 0), 0);
    const terima = props.trend.reduce((s, r) => s + Number(r.terima ?? r.received ?? r.installment ?? 0), 0);
    const delta = cair === 0 ? 0 : Math.round(((terima - cair) / cair) * 100);
    return { cair, terima, delta, months: props.trend.length };
});

const trendSlices = computed(() => {
    const palette = ['#0891b2', '#f97316', '#10b981', '#6366f1', '#ef4444', '#a855f7'];
    const sliced = props.trend.length > 6 ? props.trend.slice(-6) : props.trend;
    const total = sliced.reduce((s, r) => s + Number(r.cair ?? r.disbursed ?? r.amount ?? 0), 0);
    let acc = 0;
    return sliced.map((row, idx) => {
        const value = Number(row.cair ?? row.disbursed ?? row.amount ?? 0);
        const pct = total > 0 ? (value / total) * 100 : 0;
        const start = acc;
        acc += pct;
        return {
            label: row.label ?? row.month ?? '',
            value,
            pct: Math.round(pct * 100) / 100,
            start: Math.round(start * 100) / 100,
            color: palette[idx % palette.length],
        };
    });
});

const pendingKey = ref(null);
const open = ref(false);
const borrowerFilter = ref('semua');

function hasPipelineQuery() {
    if (typeof window === 'undefined') return false;
    return new URL(window.location.href).searchParams.has('pipeline');
}

function syncOpenFromServer() {
    if (props.pipeline_modal_key !== null && props.pipeline_modal !== null && hasPipelineQuery()) {
        open.value = true;
        pendingKey.value = null;
        borrowerFilter.value = 'semua';
    }
}

const filteredPipelineRows = computed(() => {
    const rows = props.pipeline_modal?.rows ?? [];
    if (borrowerFilter.value === 'semua') return rows;
    return rows.filter((r) => r.borrower_type === borrowerFilter.value);
});

const pipelineBreakdown = computed(() => {
    const rows = props.pipeline_modal?.rows ?? [];
    return {
        kelompok: rows.filter((r) => r.borrower_type === 'kelompok').length,
        individu: rows.filter((r) => r.borrower_type === 'individu').length,
    };
});

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
            if (props.pipeline_modal_key === null) pendingKey.value = null;
        },
    });
}

function closePipeline() {
    open.value = false;
    pendingKey.value = null;
    if (typeof document !== 'undefined') document.body.style.overflow = '';
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

const sourceLabel = {
    loan: 'Pinjaman',
    installment: 'Angsuran',
    manual: 'Manual',
    general: 'Umum',
};

const kpiTones = {
    saldo_kas: 'emerald',
    saldo_bank: 'emerald',
    cash_balance: 'emerald',
    cash: 'emerald',
    outstanding: 'cyan',
    outstanding_pokok: 'cyan',
    principal_outstanding: 'cyan',
    tunggakan: 'rose',
    overdue: 'rose',
    lewat_jatuh_tempo: 'rose',
    anggota: 'violet',
    members: 'violet',
    active_members: 'violet',
};
const kpiBox = {
    emerald: 'bg-emerald-600 text-white ring-1 ring-emerald-700/30',
    cyan: 'bg-cyan-700 text-white ring-1 ring-cyan-800/30',
    rose: 'bg-rose-600 text-white ring-1 ring-rose-700/30',
    violet: 'bg-violet-600 text-white ring-1 ring-violet-700/30',
    amber: 'bg-amber-500 text-amber-950 ring-1 ring-amber-600/30',
};
const kpiIconBadge = {
    emerald: 'bg-white/20 text-white',
    cyan: 'bg-white/20 text-white',
    rose: 'bg-white/20 text-white',
    violet: 'bg-white/20 text-white',
    amber: 'bg-black/10 text-amber-950',
};
const kpiText = {
    emerald: 'text-white',
    cyan: 'text-white',
    rose: 'text-white',
    violet: 'text-white',
    amber: 'text-amber-950',
};
const kpiTextFallback = 'text-on-surface';

function cardKpiTone(card) {
    if (card.tone === 'error') return 'rose';
    return kpiTones[card.key] || 'cyan';
}

function cardKpiBoxClass(card) {
    return kpiBox[cardKpiTone(card)] || kpiBox.cyan;
}

function cardKpiIconBadgeClass(card) {
    return kpiIconBadge[cardKpiTone(card)] || kpiIconBadge.cyan;
}

function cardKpiTextClass(card) {
    return kpiText[cardKpiTone(card)] || kpiTextFallback;
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout :unit-name="unitName">
        <div class="mx-auto w-full max-w-6xl space-y-4">
            <!-- 1) Header halaman standar (seperti halaman konten lain) -->
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <AppIcon name="space_dashboard" class="text-2xl text-primary" />
                        <h1 class="truncate text-xl font-bold leading-tight text-primary sm:text-2xl">
                            {{ unitName || 'Dashboard' }}
                        </h1>
                    </div>
                    <p class="mt-1 text-xs text-on-surface-variant sm:text-sm">
                        Ringkasan operasional · per {{ formatDate(as_of) }}
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary-container px-2.5 py-1 font-semibold text-on-secondary-container">
                        <span class="size-1.5 rounded-full bg-secondary" />{{ counts.active_loans }} pinjaman aktif
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-container px-2.5 py-1 font-semibold text-on-primary-container">
                        <span class="size-1.5 rounded-full bg-primary" />{{ counts.members }} anggota
                    </span>
                </div>
            </div>

            <!-- 2) KPI - 4 kolom ukuran standar (padding cukup, ikon besar) -->
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <article
                    v-for="card in cards"
                    :key="card.key"
                    class="rounded-xl px-4 py-4 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                    :class="cardKpiBoxClass(card)"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="grid size-12 shrink-0 place-items-center rounded-xl"
                            :class="cardKpiIconBadgeClass(card)"
                        >
                            <AppIcon :name="card.icon" class="text-2xl" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[11px] font-bold uppercase leading-none tracking-wider opacity-80" :class="cardKpiTextClass(card)">{{ card.label }}</p>
                            <p class="mt-1.5 truncate text-2xl font-extrabold leading-tight tabular-nums" :class="cardKpiTextClass(card)">{{ formatValue(card) }}</p>
                        </div>
                    </div>
                    <div v-if="card.breakdown" class="mt-3 flex items-center gap-1.5">
                        <span
                            v-for="(count, label) in card.breakdown"
                            :key="label"
                            class="inline-flex items-center gap-1 rounded-md bg-white/15 px-1.5 py-0.5 text-[10px] font-bold tabular-nums backdrop-blur-sm"
                            :class="cardKpiTextClass(card)"
                        >
                            <span class="opacity-75">{{ label }}</span>
                            <span class="opacity-100">{{ count }}</span>
                        </span>
                    </div>
                    <p v-else-if="card.hint" class="mt-2 truncate text-[11px] leading-tight opacity-80" :class="cardKpiTextClass(card)">{{ card.hint }}</p>
                </article>
            </section>

            <!-- 3) Tren & Pipeline - 2 kolom side-by-side ukuran standar -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <!-- Tren -->
                <AppCard :padded="false" class="lg:col-span-3">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="monitoring" tone="success" :container-size="10" container-shape="rounded" />
                            <h2 class="truncate text-base font-bold leading-none text-primary">Tren {{ trendSummary.months }} Bulan</h2>
                        </div>
                        <Link href="/accounting/reports" class="shrink-0 text-xs font-bold text-primary hover:underline">Detail →</Link>
                    </template>

                    <div v-if="trendSlices.length" class="flex items-center gap-6 px-6 py-6">
                        <div class="relative shrink-0 pie-shadow rounded-full" :style="{ width: '170px', height: '170px' }">
                            <div
                                class="absolute inset-0 rounded-full"
                                :style="{ background: `conic-gradient(${trendSlices.map(s => `${s.color} ${s.start}% ${s.start + s.pct}%`).join(', ')})` }"
                            />
                            <div
                                class="absolute inset-0 rounded-full"
                                style="background: conic-gradient(from -90deg, rgb(255 255 255 / 0.18) 0deg, rgb(255 255 255 / 0) 30%, rgb(0 0 0 / 0.06) 100%); mix-blend-mode: overlay;"
                            />
                            <div
                                class="absolute inset-0 rounded-full"
                                style="box-shadow: inset 0 0 0 1.5px rgb(255 255 255 / 60%), 0 1px 2px rgb(0 0 0 / 0.06), 0 4px 12px rgb(0 0 0 / 0.08);"
                            />
                            <template v-for="(slice, idx) in trendSlices" :key="`lbl-${idx}`">
                                <span
                                    v-if="slice.pct >= 8"
                                    class="pointer-events-none absolute text-xs font-bold tabular-nums text-white"
                                    style="text-shadow: 0 1px 2px rgb(0 0 0 / 50%);"
                                    :style="{
                                        left: `${50 + 34 * Math.cos((slice.start + slice.pct / 2) * 2 * Math.PI / 100 - Math.PI / 2)}%`,
                                        top: `${50 + 34 * Math.sin((slice.start + slice.pct / 2) * 2 * Math.PI / 100 - Math.PI / 2)}%`,
                                        transform: 'translate(-50%, -50%)',
                                    }"
                                >
                                    {{ slice.pct.toFixed(2) }}%
                                </span>
                            </template>
                        </div>
                        <ul class="min-w-0 flex-1 space-y-2 text-sm">
                            <li v-for="(slice, idx) in trendSlices" :key="idx" class="flex items-center justify-between gap-3">
                                <span class="inline-flex min-w-0 items-center gap-2">
                                    <span class="size-3 shrink-0 rounded-sm ring-1 ring-outline-variant/30" :style="{ background: slice.color }" />
                                    <span class="truncate font-semibold text-primary">{{ slice.label }}</span>
                                </span>
                                <span class="shrink-0 font-bold tabular-nums text-primary">{{ slice.pct.toFixed(2) }}%</span>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="py-8 text-center text-sm text-on-surface-variant">
                        Belum ada data tren
                    </div>
                </AppCard>

                <!-- Pipeline -->
                <AppCard :padded="false" class="lg:col-span-2">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="timeline" tone="success" :container-size="10" container-shape="rounded" />
                            <h2 class="truncate text-base font-bold leading-none text-primary">Pipeline</h2>
                        </div>
                        <Link href="/lending/loans" class="shrink-0 text-xs font-bold text-primary hover:underline">Lihat →</Link>
                    </template>
                    <div class="grid grid-cols-2 divide-x divide-outline-variant/40 sm:grid-cols-4 lg:grid-cols-2">
                        <button
                            v-for="(stage, idx) in pipeline"
                            :key="stage.status"
                            type="button"
                            class="group relative flex flex-col items-center gap-1.5 px-3 py-5 text-center transition-colors hover:bg-primary-container/30 focus:outline-none focus:bg-primary-container/40"
                            :class="[
                                (idx === 2) ? 'border-t border-outline-variant/40 sm:border-t-0 lg:border-t' : '',
                                (idx === 3) ? 'border-t border-outline-variant/40 sm:border-t-0 lg:border-t-0' : '',
                                idx < 2 ? 'border-t border-outline-variant/40 sm:border-t-0 lg:border-t-0' : '',
                            ]"
                            @click="openPipeline(stage)"
                        >
                            <span
                                class="absolute left-1/2 top-1.5 size-1.5 -translate-x-1/2 rounded-full transition-transform group-hover:scale-150"
                                :class="stage.count > 0 ? 'bg-secondary' : 'bg-outline-variant/50'"
                            />
                            <span class="mt-1 text-[11px] font-bold uppercase leading-none tracking-wider text-on-surface-variant">{{ stage.label }}</span>
                            <span class="text-3xl font-extrabold leading-tight tabular-nums text-primary">{{ stage.count }}</span>
                            <span class="truncate text-xs font-semibold tabular-nums leading-none text-primary">{{ formatMoney(stage.amount) }}</span>
                        </button>
                    </div>
                    <div v-if="!pipeline.length" class="py-8 text-center text-sm text-on-surface-variant">
                        Belum ada pinjaman
                    </div>
                </AppCard>
            </div>

            <!-- 4) Jurnal + Jatuh Tempo - 2 kolom ukuran standar -->
            <div class="grid grid-cols-1 items-stretch gap-4 lg:grid-cols-5">
                <!-- Jurnal -->
                <AppCard class="flex flex-col lg:col-span-3" :padded="false">
                    <template #header>
                        <div class="flex min-w-0 items-center gap-2">
                            <AppIcon name="receipt_long" tone="success" :container-size="10" container-shape="rounded" />
                            <h2 class="truncate text-base font-bold leading-none text-primary">Jurnal Terbaru</h2>
                            <span class="text-xs text-on-surface-variant">({{ recent_journals.length }})</span>
                        </div>
                        <Link href="/accounting/journal-entries/create" class="shrink-0">
                            <AppButton variant="primary" icon="add">Buat</AppButton>
                        </Link>
                    </template>
                    <div v-if="recent_journals.length" class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Tgl</th>
                                    <th class="px-4 py-3 font-semibold">No / Uraian</th>
                                    <th class="hidden px-4 py-3 font-semibold sm:table-cell">Sumber</th>
                                    <th class="px-4 py-3 text-right font-semibold">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in recent_journals"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant transition hover:bg-surface-container-low/50"
                                >
                                    <td class="whitespace-nowrap px-4 py-3 align-top text-xs text-on-surface-variant">{{ formatDate(row.transaction_date) }}</td>
                                    <td class="px-4 py-3 align-top">
                                        <p class="truncate text-sm font-bold leading-tight text-primary">{{ row.journal_number || `#${row.row_id}` }}</p>
                                        <p class="truncate text-xs leading-tight text-on-surface-variant">{{ row.description || '—' }}</p>
                                    </td>
                                    <td class="hidden px-4 py-3 align-top sm:table-cell">
                                        <span class="inline-flex items-center rounded bg-surface-container-low px-2 py-1 text-xs font-semibold text-on-surface-variant">
                                            {{ sourceLabel[row.source_type] || row.source_type || '—' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right align-top text-sm font-bold tabular-nums text-primary">
                                        {{ formatMoney(row.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="flex items-center justify-center px-4 py-10 text-center">
                        <div>
                            <AppIcon name="receipt_long" tone="neutral" :container-size="12" container-shape="rounded" />
                            <p class="mt-2 text-sm font-semibold text-on-surface-variant">Belum ada jurnal posted</p>
                        </div>
                    </div>
                </AppCard>

                <!-- Jatuh Tempo -->
                <AppCard class="flex flex-col lg:col-span-2" :padded="false">
                    <template #header>
                        <div class="flex min-w-0 items-center gap-2">
                            <AppIcon name="event_note" tone="tertiary" :container-size="10" container-shape="rounded" />
                            <h2 class="truncate text-base font-bold leading-none text-primary">Jatuh Tempo</h2>
                        </div>
                        <AppBadge v-if="overdue_summary.count" tone="error">{{ overdue_summary.count }} lewat</AppBadge>
                    </template>
                    <div v-if="upcoming_due.length" class="flex-1 space-y-2 overflow-y-auto p-3">
                        <article
                            v-for="item in upcoming_due"
                            :key="item.row_id"
                            class="flex items-start justify-between gap-2 rounded-lg border-l-4 bg-surface-container-low px-3 py-2.5 transition hover:bg-primary-container/20"
                            :class="item.overdue ? 'border-error' : 'border-tertiary'"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold leading-tight text-primary">{{ item.borrower }}</p>
                                <p class="truncate text-xs leading-tight text-on-surface-variant">{{ item.loan_number || 'Pinjaman' }} · {{ formatDate(item.due_date) }}</p>
                            </div>
                            <p class="shrink-0 text-sm font-bold tabular-nums leading-tight" :class="item.overdue ? 'text-error' : 'text-primary'">
                                {{ formatMoney(item.amount) }}
                            </p>
                        </article>
                    </div>
                    <div v-else class="flex flex-1 items-center justify-center px-4 py-10 text-center">
                        <div>
                            <AppIcon name="event_available" tone="neutral" :container-size="12" container-shape="rounded" />
                            <p class="mt-2 text-sm font-semibold text-on-surface-variant">Tidak ada 14 hari ke depan</p>
                        </div>
                    </div>
                    <div class="shrink-0 border-t border-outline-variant p-3">
                        <Link href="/accounting/journal-entries/installment" class="block">
                            <AppButton variant="secondary" icon="payments" class="w-full">Catat angsuran</AppButton>
                        </Link>
                    </div>
                </AppCard>
            </div>

            <!-- 5) Footer info -->
            <section class="flex items-center justify-between gap-3 rounded-lg border border-primary/20 bg-surface-container-low px-4 py-3">
                <div class="flex min-w-0 items-center gap-2">
                    <AppIcon name="verified" tone="primary" :container-size="9" container-shape="rounded" class="shrink-0" />
                    <p class="truncate text-sm text-on-surface-variant">KPI dihitung dari jurnal posted &amp; jadwal angsuran aktif</p>
                </div>
                <Link href="/accounting/tax-estimate" class="shrink-0">
                    <AppButton variant="ghost" icon-after="arrow_forward">Taksiran pajak</AppButton>
                </Link>
            </section>
        </div>

        <!-- Modal pipeline -->
        <AppModal
            :model-value="open"
            :title="`Pinjaman · ${pipeline_modal?.label ?? ''}`"
            size="lg"
            @update:model-value="(value) => { if (!value) closePipeline(); }"
        >
            <div v-if="pipeline_modal" class="space-y-3">
                <p class="text-xs text-on-surface-variant">
                    Menampilkan
                    <span class="font-semibold text-primary">{{ filteredPipelineRows.length }}</span>
                    dari
                    <span class="font-semibold text-primary">{{ pipeline_modal.total }}</span>
                    pinjaman pada tahap
                    <span class="font-semibold text-primary">{{ pipeline_modal.label }}</span>.
                </p>

                <div class="flex items-center gap-1 rounded-lg bg-surface-container-low p-1 chip-shadow ring-1 ring-outline-variant/40">
                    <button
                        v-for="opt in [
                            { value: 'semua', label: 'Semua', icon: 'all_inclusive', count: pipeline_modal.rows.length },
                            { value: 'kelompok', label: 'Kelompok', icon: 'groups', count: pipelineBreakdown.kelompok },
                            { value: 'individu', label: 'Individu', icon: 'person', count: pipelineBreakdown.individu },
                        ]"
                        :key="opt.value"
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-bold transition-all"
                        :class="borrowerFilter === opt.value ? 'bg-surface-container-lowest text-primary shadow-sm ring-1 ring-outline-variant/40' : 'text-on-surface-variant hover:bg-surface-container-lowest/60 hover:text-primary'"
                        @click="borrowerFilter = opt.value"
                    >
                        <AppIcon :name="opt.icon" class="text-base" />
                        <span>{{ opt.label }}</span>
                        <span class="rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-primary">{{ opt.count }}</span>
                    </button>
                </div>

                <div v-if="filteredPipelineRows.length" class="overflow-hidden rounded-lg border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-[10px] uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 font-semibold">Peminfaat</th>
                                <th class="px-3 py-2 font-semibold">Tgl</th>
                                <th class="px-3 py-2 text-right font-semibold">Nominal</th>
                                <th class="px-3 py-2 text-right font-semibold">Sisa Pokok</th>
                                <th class="px-3 py-2 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredPipelineRows"
                                :key="row.row_id"
                                class="border-t border-outline-variant"
                            >
                                <td class="px-3 py-2 align-top">
                                    <div class="flex items-start gap-2">
                                        <span
                                            class="mt-0.5 inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider"
                                            :class="row.borrower_type === 'kelompok' ? 'bg-tertiary-container/30 text-on-tertiary-container' : 'bg-secondary-container/40 text-on-secondary-container'"
                                        >
                                            <AppIcon :name="row.borrower_type === 'kelompok' ? 'groups' : 'person'" class="text-[10px]" />
                                            {{ row.borrower_type }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-[11px] font-semibold leading-tight text-primary">{{ row.borrower_type === 'individu' ? (row.member_name || row.group_name) : row.group_name }}</p>
                                            <p v-if="row.borrower_type === 'kelompok' && row.group_address" class="mt-0.5 truncate text-[10px] text-on-surface-variant">{{ row.group_address }}</p>
                                            <p class="mt-0.5 text-[9px] uppercase tracking-wider text-outline">#{{ row.id }} · {{ row.product_code || '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 align-top text-[11px] text-on-surface-variant">{{ formatDate(dateForRow(row)) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right align-top text-[11px] font-semibold text-primary">{{ formatMoney(amountForRow(row)) }}</td>
                                <td class="whitespace-nowrap px-3 py-2 text-right align-top">
                                    <span v-if="row.principal_remaining > 0" class="text-[11px] font-semibold text-primary">{{ formatMoney(row.principal_remaining) }}</span>
                                    <span v-else class="text-[11px] text-on-surface-variant">—</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-right align-top">
                                    <Link :href="`/lending/loans/${row.row_id}`" class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-bold text-primary hover:bg-primary/10">Detail →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="rounded-lg border border-dashed border-outline-variant py-8 text-center">
                    <AppIcon name="inbox" tone="neutral" :container-size="14" container-shape="rounded" />
                    <p class="mt-2 text-sm font-semibold text-on-surface-variant">Belum ada pinjaman</p>
                    <p class="text-[11px] text-on-surface-variant">Tidak ada pinjaman {{ borrowerFilter === 'semua' ? '' : `(${borrowerFilter}) ` }}pada tahap {{ pipeline_modal.label }}.</p>
                </div>

                <p v-if="pipeline_modal.total > pipeline_modal.limit" class="text-[11px] text-on-surface-variant">
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
