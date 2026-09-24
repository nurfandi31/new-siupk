<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppTextarea from '../../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    period: { type: Object, required: true },
    year: { type: Number, required: true },
    identity: { type: Object, required: true },
    is_preview: { type: Boolean, default: true },
    chapters: { type: Array, required: true },
    highlights: { type: Array, required: true },
    allocation_summary: { type: Object, required: true },
    closing_journal_summary: { type: Object, required: true },
    balance_sheet: { type: Object, required: true },
    income_statement: { type: Object, required: true },
    policies: { type: Array, required: true },
    yearOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
});

const allowEdit = computed(() => props.can_edit && can('reports.manage'));

const form = useForm({
    year: props.year,
    notes: Object.fromEntries(props.chapters.map((c) => [c.key, c.body])),
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

const pdfHref = computed(() => {
    const params = new URLSearchParams();
    params.set('year', String(props.year));
    return `/accounting/year-end/calk/pdf?${params.toString()}`;
});

function applyYear(newYear) {
    router.get(
        '/accounting/year-end/calk',
        { year: newYear },
        { preserveScroll: true, replace: true },
    );
}

function save() {
    form.year = props.year;
    form.put('/accounting/year-end/calk/notes', { preserveScroll: true });
}
</script>

<template>
    <Head title="CALK Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pelaporan · Tutup Buku
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Catatan Atas Laporan Keuangan — Tutup Buku</h1>
                    <p class="text-sm text-on-surface-variant">
                        Tahun Buku {{ year }} · khusus pelaporan tutup buku akhir tahun
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-warning/20 px-3 py-1 text-xs font-semibold text-warning">
                        PREVIEW / SIMULASI
                    </span>
                    <a :href="pdfHref" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                    </a>
                </div>
            </div>

            <AppCard class="p-4">
                <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-2">
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
                            Periode
                        </label>
                        <input
                            readonly
                            class="w-full rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-sm font-semibold"
                            :value="period.period_label"
                        />
                    </div>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Identitas entitas</h2>
                <p class="mt-2 text-lg font-bold text-primary">{{ identity.legal_name }}</p>
                <p v-if="identity.address" class="text-sm text-on-surface-variant">{{ identity.address }}</p>
                <p v-if="identity.registration_number || identity.tax_number" class="mt-1 text-xs text-on-surface-variant">
                    <span v-if="identity.registration_number">Registrasi {{ identity.registration_number }}</span>
                    <span v-if="identity.registration_number && identity.tax_number"> · </span>
                    <span v-if="identity.tax_number">NPWP {{ identity.tax_number }}</span>
                </p>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AppCard v-for="h in highlights" :key="h.key">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ h.label }}</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="h.amount < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ formatMoney(h.amount) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Kebijakan Akuntansi (ringkas)
                </h2>
                <ol class="list-decimal space-y-2 pl-5 text-sm text-on-surface">
                    <li v-for="(p, i) in policies" :key="i">{{ p }}</li>
                </ol>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Ringkasan Jurnal Tutup Buku
                </h2>
                <ul class="space-y-1 text-sm">
                    <li>Jumlah entry: <strong>{{ closing_journal_summary.entries }}</strong></li>
                    <li>Total pendapatan ditutup: <strong>{{ formatMoney(closing_journal_summary.revenue) }}</strong></li>
                    <li>Total beban ditutup: <strong>{{ formatMoney(closing_journal_summary.expense) }}</strong></li>
                    <li>Surplus/(Defisit) ditutup: <strong>{{ formatMoney(closing_journal_summary.surplus) }}</strong></li>
                    <li>Status: <strong>{{ closing_journal_summary.balanced ? 'Debit = Kredit (seimbang)' : 'Tidak seimbang' }}</strong></li>
                </ul>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Ringkasan Alokasi Laba
                </h2>
                <ul class="space-y-1 text-sm">
                    <li>Total Surplus: <strong>{{ formatMoney(allocation_summary.surplus) }}</strong></li>
                    <li v-for="line in allocation_summary.lines" :key="line.key">
                        {{ line.label }} ({{ line.percentage.toFixed(2) }}%):
                        <strong>{{ formatMoney(line.amount) }}</strong>
                    </li>
                    <li class="border-t border-outline-variant/30 pt-1 font-semibold">
                        Total Dialokasikan: {{ formatMoney(allocation_summary.totals.allocated) }}
                        ({{ allocation_summary.totals.pct_allocated.toFixed(2) }}%)
                    </li>
                </ul>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    Catatan per Bab
                </h2>
                <form v-if="allowEdit" class="space-y-4" @submit.prevent="save">
                    <div v-for="chapter in chapters" :key="chapter.key" class="space-y-2">
                        <label class="block text-xs font-bold uppercase tracking-wide text-on-surface-variant">
                            {{ chapter.title }}
                        </label>
                        <AppTextarea
                            v-model="form.notes[chapter.key]"
                            :error="form.errors[`notes.${chapter.key}`]"
                            placeholder="Tulis catatan untuk bab ini…"
                        />
                    </div>
                    <div class="flex justify-end">
                        <AppButton type="submit" icon="save" :loading="form.processing">Simpan catatan</AppButton>
                    </div>
                </form>
                <div v-else class="space-y-4">
                    <article
                        v-for="chapter in chapters"
                        :key="chapter.key"
                        class="space-y-2 border-l-2 border-primary/30 pl-4"
                    >
                        <h3 class="text-sm font-bold text-primary">{{ chapter.title }}</h3>
                        <p class="whitespace-pre-wrap text-sm text-on-surface">{{ chapter.body }}</p>
                    </article>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <p class="text-xs text-on-surface-variant">
                    <strong class="text-on-surface">Catatan:</strong> CALK ini adalah bagian dari pelaporan
                    tutup buku akhir tahun. Pastikan setiap bab direview oleh manajemen/direksi sebelum
                    penerbitan resmi. Setelah tutup buku benar-benar dijalankan (PeriodClose), catatan akan
                    direfleksikan ke laporan final.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>