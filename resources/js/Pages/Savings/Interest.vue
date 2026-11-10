<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppConfirmDialog from '../../Components/AppConfirmDialog.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useMoney } from '../../composables/useMoney';
import { useI18n } from '../../i18n';

const { t } = useI18n();
const { money } = useMoney();
const { confirm } = useConfirm();

const props = defineProps({
    preview: { type: Object, required: true },
    runs: { type: Object, required: true },
    filters: { type: Object, required: true },
    monthLabels: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
});

const today = new Date().toLocaleDateString('en-CA');
const now = new Date();
const year = ref(props.filters.year);
const month = ref(props.filters.month);
const cif = ref(props.filters.cif ?? '');

const yearOptions = computed(() => {
    const current = now.getFullYear();

    return Array.from({ length: 8 }, (_, index) => {
        const value = current - index;
        return { value, label: String(value) };
    });
});

const monthOptions = computed(() =>
    Object.entries(props.monthLabels).map(([value, label]) => ({ value: Number(value), label })),
);

const postForm = useForm({
    year: props.filters.year,
    month: props.filters.month,
    cif: props.filters.cif ?? '',
});

const hasExistingRun = computed(() => props.preview.existing_run !== null);
const periodClosed = computed(() => String(props.preview.period.posting_date) <= today);
const postableRows = computed(() => props.preview.rows.filter((row) => row.is_postable));
const canPost = computed(() => periodClosed.value && postableRows.value.length > 0);

function loadPeriod() {
    router.get('/simpanan/bunga', {
        year: year.value,
        month: month.value,
        cif: cif.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

async function postInterest() {
    const ok = await confirm({
        title: t('Posting Bunga Periode Ini?'),
        message: t('Bunga, pajak, dan biaya admin akan dibukukan ke setiap rekening aktif yang belum diproses. Komponen yang sudah ada akan dilewati.'),
        confirmLabel: t('Posting Bunga'),
        variant: 'primary',
        icon: 'percent',
    });
    if (!ok) return;

    postForm.year = props.filters.year;
    postForm.month = props.filters.month;
    postForm.cif = props.filters.cif ?? '';
    postForm.post('/simpanan/bunga', { preserveScroll: true });
}

async function voidRun(run) {
    const ok = await confirm({
        title: t('Batalkan Proses Bunga?'),
        message: t('Seluruh transaksi bunga, pajak, dan admin pada periode ') + run.period + t(' akan dihapus dan saldo dihitung ulang.'),
        confirmLabel: t('Batalkan'),
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(`/simpanan/bunga/${run.row_id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('Bunga & Pajak Simpanan')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ t('Bunga & Pajak Simpanan') }}</h1>
                        <AppBadge tone="success-soft">{{ preview.period.method_label }}</AppBadge>
                        <AppBadge tone="neutral">{{ preview.period.days }} {{ t('hari') }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        {{ t('Periode') }} {{ preview.period.range_label }} · {{ t('posting') }} {{ preview.period.posting_date }}
                    </p>
                </div>
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-end">
                    <div class="w-full sm:w-28">
                        <SmartSelect v-model="year" :label="t('Tahun')" :options="yearOptions" />
                    </div>
                    <div class="w-full sm:w-36">
                        <SmartSelect v-model="month" :label="t('Bulan')" :options="monthOptions" />
                    </div>
                    <div class="col-span-2 w-full sm:w-44">
                        <AppInput v-model="cif" :label="t('CIF / Rekening')" icon="filter_alt" :placeholder="t('Semua rekening')" />
                    </div>
                    <AppButton variant="secondary" icon="search" class="col-span-2 w-full justify-center sm:w-auto" @click="loadPeriod">
                        {{ t('Tampilkan') }}
                    </AppButton>
                </div>
            </header>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard class="border border-outline-variant">
                    <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Total Bunga') }}</p>
                    <p class="mt-1 text-2xl font-black text-secondary">{{ money(preview.totals.gross_interest) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ preview.account_count }} {{ t('rekening dihitung') }}</p>
                </AppCard>
                <AppCard class="border border-outline-variant">
                    <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Pajak Bunga') }}</p>
                    <p class="mt-1 text-2xl font-black text-tertiary">{{ money(preview.totals.tax_total) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('Tarif') }} {{ preview.settings.tax_rate }}%</p>
                </AppCard>
                <AppCard class="border border-outline-variant">
                    <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Biaya Admin') }}</p>
                    <p class="mt-1 text-2xl font-black text-on-surface">{{ money(preview.totals.admin_total) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ t('Ditagih setiap periode') }}</p>
                </AppCard>
                <AppCard class="border border-outline-variant bg-gradient-to-br from-secondary-container/60 to-surface-container-low">
                    <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Bunga Neto') }}</p>
                    <p class="mt-1 text-2xl font-black text-on-surface">{{ money(preview.totals.net_total) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ preview.period.label }}</p>
                </AppCard>
            </section>

            <AppCard v-if="hasExistingRun" class="border border-secondary/40 bg-secondary-container/30">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div class="flex items-start gap-3">
                        <AppIcon name="verified" tone="success" />
                        <div>
                            <p class="font-semibold text-on-surface">{{ t('Bunga periode ini sudah diposting') }}</p>
                            <p class="text-sm text-on-surface-variant">
                                {{ preview.existing_run.posted_at }} · {{ preview.existing_run.accounts_count }} {{ t('rekening') }} ·
                                {{ t('posting') }} {{ preview.existing_run.posting_date }} ·
                                {{ t('neto') }} {{ money(preview.existing_run.net_total) }}
                            </p>
                            <p v-if="postableRows.length > 0" class="mt-1 text-xs font-semibold text-secondary">
                                {{ postableRows.length }} {{ t('rekening masih dapat diproses (top-up / CIF belum tercakup).') }}
                            </p>
                        </div>
                    </div>
                    <AppButton variant="danger" icon="undo" @click="voidRun(preview.existing_run)">
                        {{ t('Batalkan Proses') }}
                    </AppButton>
                </div>
            </AppCard>

            <AppCard :padded="false">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <AppIcon name="calculate" tone="primary" />
                            <span class="font-semibold text-on-surface">{{ t('Pratinjau Perhitungan') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <AppBadge tone="neutral">{{ preview.rows.length }} {{ t('baris') }}</AppBadge>
                            <AppBadge v-if="postableRows.length" tone="success-soft">
                                {{ postableRows.length }} {{ t('siap posting') }}
                            </AppBadge>
                            <AppButton
                                v-if="canPost"
                                variant="primary"
                                icon="percent"
                                class="w-full justify-center sm:w-auto"
                                :loading="postForm.processing"
                                @click="postInterest"
                            >
                                {{ hasExistingRun ? t('Lanjutkan Posting') : t('Posting Bunga') }}
                            </AppButton>
                        </div>
                    </div>
                </template>

                <div v-if="!periodClosed" class="flex items-start gap-2 border-b border-outline-variant bg-warning/10 px-4 py-3 text-xs text-on-surface-variant">
                    <AppIcon name="info" class="mt-0.5 text-warning" />
                    <p>{{ t('Tanggal posting periode ini belum tiba. Posting bunga tersedia mulai') }} {{ preview.period.posting_date }}.</p>
                </div>

                <div class="max-h-[560px] overflow-auto">
                    <table class="w-full min-w-[920px] divide-y divide-outline-variant text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Rekening') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Saldo Awal') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Dasar Bunga') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Bunga') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Pajak') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Admin') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Neto') }}</th>
                                <th class="px-4 py-3 text-center font-semibold">{{ t('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            <tr v-for="row in preview.rows" :key="row.account_row_id" class="transition hover:bg-surface-container-low/60">
                                <td class="px-4 py-3">
                                    <p class="font-mono text-xs font-bold text-primary">{{ row.account_number }}</p>
                                    <p class="mt-0.5 max-w-[220px] truncate font-medium text-on-surface">{{ row.member_name }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ row.product_name }}</p>
                                    <p v-if="row.reason" class="mt-1 text-xs text-tertiary">{{ row.reason }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-on-surface-variant">{{ money(row.opening_balance) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-on-surface">{{ money(row.basis) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-secondary">{{ money(row.gross_interest) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-tertiary">{{ money(row.tax_total) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-on-surface-variant">{{ money(row.admin_fee) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-on-surface">{{ money(row.net_total) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <AppBadge :tone="row.is_postable ? 'success-soft' : 'neutral'">
                                            {{ row.is_postable ? t('Siap') : t('Lewat') }}
                                        </AppBadge>
                                        <AppBadge v-if="row.posted_interest" tone="primary-soft">{{ t('Bunga ✓') }}</AppBadge>
                                        <AppBadge v-if="row.posted_tax" tone="primary-soft">{{ t('Pajak ✓') }}</AppBadge>
                                        <AppBadge v-if="row.posted_admin" tone="primary-soft">{{ t('Admin ✓') }}</AppBadge>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="preview.rows.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-on-surface-variant">
                                    {{ t('Tidak ada rekening aktif pada periode ini.') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="preview.rows.length" class="bg-surface-container-low font-bold text-on-surface">
                            <tr>
                                <td class="px-4 py-3 text-right" colspan="3">{{ t('Total') }}</td>
                                <td class="px-4 py-3 text-right text-secondary">{{ money(preview.totals.gross_interest) }}</td>
                                <td class="px-4 py-3 text-right text-tertiary">{{ money(preview.totals.tax_total) }}</td>
                                <td class="px-4 py-3 text-right text-on-surface-variant">{{ money(preview.totals.admin_total) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(preview.totals.net_total) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <AppBadge tone="success-soft">{{ postableRows.length }} {{ t('siap') }}</AppBadge>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-outline-variant p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-2xl text-sm text-on-surface-variant">
                        <p class="font-semibold text-on-surface">{{ t('Catatan metode') }}: {{ preview.period.method_label }}</p>
                        <p>
                            {{ t('Bunga dihitung dari dasar saldo × tarif per bulan. Pajak dikenakan bila bunga mencapai minimum pajak.') }}
                            <span v-if="preview.settings.minimum_balance > 0">
                                {{ t('Minimum saldo bunga') }} {{ money(preview.settings.minimum_balance) }}.
                            </span>
                            <span v-if="preview.settings.minimum_taxable_interest > 0">
                                {{ t('Minimum bunga kena pajak') }} {{ money(preview.settings.minimum_taxable_interest) }}.
                            </span>
                        </p>
                        <p class="mt-1 text-xs">
                            {{ t('Tanggal bunga') }} {{ preview.period.interest_day }} ·
                            {{ t('Posting') }} {{ preview.period.posting_date }} ·
                            {{ t('Rentang') }} {{ preview.period.range_label }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-surface-container-low px-4 py-3 text-right text-sm">
                        <p class="text-xs uppercase tracking-wide text-on-surface-variant">{{ t('Akan diposting') }}</p>
                        <p class="font-bold text-on-surface">{{ money(preview.postable_totals.net_total) }}</p>
                    </div>
                </div>
            </AppCard>

            <AppCard :padded="false">
                <template #header>
                    <div class="flex items-center gap-2">
                        <AppIcon name="history" tone="primary" />
                        <span class="font-semibold text-on-surface">{{ t('Riwayat Proses Bunga') }}</span>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] divide-y divide-outline-variant text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Periode') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Rentang') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Metode') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Rekening') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Bunga') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Pajak') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Admin') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Neto') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            <tr v-for="run in runs.data" :key="run.row_id">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-on-surface">{{ run.period }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ run.posted_at }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant">
                                    {{ run.period_start }} — {{ run.period_end }}
                                    <span class="block">{{ t('Posting') }} {{ run.posting_date }}</span>
                                </td>
                                <td class="px-4 py-3 text-on-surface-variant">{{ run.method_label }}</td>
                                <td class="px-4 py-3 text-right text-on-surface">{{ run.accounts_count }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-secondary">{{ money(run.gross_interest) }}</td>
                                <td class="px-4 py-3 text-right text-tertiary">{{ money(run.tax_total) }}</td>
                                <td class="px-4 py-3 text-right text-on-surface-variant">{{ money(run.admin_total) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-on-surface">{{ money(run.net_total) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <AppButton variant="ghost" size="compact" icon="undo" @click="voidRun(run)">{{ t('Batalkan') }}</AppButton>
                                </td>
                            </tr>
                            <tr v-if="runs.data.length === 0">
                                <td colspan="9" class="px-4 py-10 text-center text-on-surface-variant">
                                    {{ t('Belum ada proses bunga yang diposting.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="runs.last_page > 1" class="flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant px-4 py-3 text-sm">
                    <p class="text-on-surface-variant">{{ t('Halaman') }} {{ runs.current_page }} / {{ runs.last_page }}</p>
                    <div class="flex gap-2">
                        <Link v-if="runs.prev_page_url" :href="runs.prev_page_url" preserve-scroll>
                            <AppButton variant="secondary" size="compact" icon="chevron_left">{{ t('Sebelumnya') }}</AppButton>
                        </Link>
                        <Link v-if="runs.next_page_url" :href="runs.next_page_url" preserve-scroll>
                            <AppButton variant="secondary" size="compact" icon="chevron_right">{{ t('Berikutnya') }}</AppButton>
                        </Link>
                    </div>
                </div>
            </AppCard>
        </div>

        <AppConfirmDialog />
    </AuthenticatedLayout>
</template>
