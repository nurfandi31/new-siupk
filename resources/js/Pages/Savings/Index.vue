<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import SmartDataTable from '../../Components/SmartDataTable.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../composables/useCan';
import { useMoney } from '../../composables/useMoney';
import { useI18n } from '../../i18n';

const { t } = useI18n();
const { can } = useCan();
const { money } = useMoney();

const props = defineProps({
    accounts: { type: Object, required: true },
    stats: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    perPage: { type: Number, default: 15 },
});

const product = ref(props.filters.product || '');
const status = ref(props.filters.status || '');

const statusOptions = [
    { value: '', label: t('Semua status') },
    { value: 'active', label: t('Aktif') },
    { value: 'closed', label: t('Ditutup') },
];

const productOptions = computed(() => [
    { value: '', label: t('Semua jenis') },
    ...props.products,
]);

const tableUrl = computed(() => {
    const params = new URLSearchParams();
    if (product.value) params.set('product', product.value);
    if (status.value) params.set('status', status.value);
    const query = params.toString();

    return query ? `/simpanan?${query}` : '/simpanan';
});

const columns = [
    { key: 'account_number', label: 'Nomor Rekening', sortable: true },
    { key: 'member', label: 'Anggota' },
    { key: 'product', label: 'Jenis Simpanan' },
    { key: 'balance', label: 'Saldo', sortable: true, class: 'text-right', tdClass: 'text-right' },
    { key: 'status', label: 'Status' },
    { key: 'opened_at', label: 'Dibuka', sortable: true },
];

function applyFilters() {
    router.get('/simpanan', {
        product: product.value || undefined,
        status: status.value || undefined,
        per_page: props.perPage,
        sort: props.filters.sort || undefined,
        direction: props.filters.direction || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilters() {
    product.value = '';
    status.value = '';
    router.get('/simpanan', {}, { preserveState: true, preserveScroll: true, replace: true });
}

function statusLabel(value) {
    return value === 'closed' ? t('Ditutup') : t('Aktif');
}
</script>

<template>
    <Head :title="t('Simpanan')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ t('Simpanan') }}</h1>
                        <AppBadge tone="success-soft">{{ t('Modul Lokasi') }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        {{ t('Rekening simpanan anggota, mutasi setoran/penarikan, serta bunga dan pajak per periode.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="can('simpanan.manage')" href="/simpanan/bunga">
                        <AppButton variant="secondary" icon="percent">{{ t('Bunga & Pajak') }}</AppButton>
                    </Link>
                    <Link v-if="can('simpanan.manage')" href="/simpanan/create">
                        <AppButton variant="primary" icon="add_card">{{ t('Buka Rekening') }}</AppButton>
                    </Link>
                </div>
            </header>

            <section class="relative overflow-hidden rounded-3xl border border-outline-variant bg-gradient-to-br from-secondary-container via-surface-container-low to-primary-container p-5 sm:p-8">
                <div class="pointer-events-none absolute -right-16 -top-20 size-56 rounded-full bg-surface/40 blur-2xl" aria-hidden="true" />
                <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 place-items-center rounded-2xl bg-surface/70 text-secondary shadow-sm">
                                <AppIcon name="savings" class="text-2xl" />
                            </span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Total Saldo Simpanan') }}</p>
                                <p class="text-3xl font-black leading-tight text-on-surface sm:text-4xl">{{ money(stats.total_balance) }}</p>
                            </div>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-surface/70 px-4 py-3 backdrop-blur">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Rekening Aktif') }}</p>
                                <p class="mt-1 text-lg font-bold text-on-surface">{{ stats.active_count }} <span class="text-sm font-medium text-on-surface-variant">/ {{ stats.account_count }}</span></p>
                            </div>
                            <div class="rounded-2xl bg-surface/70 px-4 py-3 backdrop-blur">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Setoran Bulan Ini') }}</p>
                                <p class="mt-1 text-lg font-bold text-secondary">{{ money(stats.month_inflow) }}</p>
                            </div>
                            <div class="rounded-2xl bg-surface/70 px-4 py-3 backdrop-blur">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Penarikan Bulan Ini') }}</p>
                                <p class="mt-1 text-lg font-bold text-tertiary">{{ money(stats.month_outflow) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl bg-surface/80 p-4 backdrop-blur">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">{{ t('Komposisi per Jenis') }}</p>
                        <div v-if="stats.by_product.length" class="mt-3 space-y-3">
                            <div v-for="item in stats.by_product" :key="item.product_row_id">
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-semibold text-on-surface">{{ item.name }}</span>
                                    <span class="whitespace-nowrap font-bold text-on-surface">{{ money(item.balance) }}</span>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-container">
                                        <div class="h-full rounded-full bg-secondary" :style="{ width: `${stats.total_balance > 0 ? Math.max(3, Math.round((item.balance / stats.total_balance) * 100)) : 0}%` }" />
                                    </div>
                                    <span class="text-[11px] font-semibold text-on-surface-variant">{{ item.accounts }} {{ t('rek') }}</span>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-sm text-on-surface-variant">{{ t('Belum ada rekening simpanan.') }}</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard class="border border-outline-variant">
                    <div class="flex items-center gap-3">
                        <AppIcon name="account_balance_wallet" tone="success" container-size="10" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Total Saldo') }}</p>
                            <p class="truncate text-lg font-bold text-on-surface">{{ money(stats.total_balance) }}</p>
                        </div>
                    </div>
                </AppCard>
                <AppCard class="border border-outline-variant">
                    <div class="flex items-center gap-3">
                        <AppIcon name="group" tone="primary" container-size="10" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Rekening Aktif') }}</p>
                            <p class="text-lg font-bold text-on-surface">{{ stats.active_count }}</p>
                        </div>
                    </div>
                </AppCard>
                <AppCard class="border border-outline-variant">
                    <div class="flex items-center gap-3">
                        <AppIcon name="south_west" tone="success" container-size="10" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Setoran Bulan Ini') }}</p>
                            <p class="truncate text-lg font-bold text-secondary">{{ money(stats.month_inflow) }}</p>
                        </div>
                    </div>
                </AppCard>
                <AppCard class="border border-outline-variant">
                    <div class="flex items-center gap-3">
                        <AppIcon name="north_east" tone="warning" container-size="10" />
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Penarikan Bulan Ini') }}</p>
                            <p class="truncate text-lg font-bold text-tertiary">{{ money(stats.month_outflow) }}</p>
                        </div>
                    </div>
                </AppCard>
            </div>

            <AppCard :padded="false" class="border border-outline-variant">
                <div class="flex flex-col gap-3 border-b border-outline-variant p-4 sm:flex-row sm:items-end">
                    <div class="grid flex-1 gap-3 sm:grid-cols-2">
                        <SmartSelect v-model="product" :label="t('Jenis Simpanan')" :options="productOptions" searchable @update:model-value="applyFilters" />
                        <SmartSelect v-model="status" :label="t('Status Rekening')" :options="statusOptions" @update:model-value="applyFilters" />
                    </div>
                    <AppButton variant="ghost" icon="restart_alt" @click="resetFilters">{{ t('Reset') }}</AppButton>
                </div>

                <SmartDataTable
                    :rows="accounts.data"
                    :columns="columns"
                    :pagination="accounts"
                    :url="tableUrl"
                    :search="filters.search"
                    :per-page="perPage"
                    :sort="filters.sort"
                    :direction="filters.direction"
                    :row-href="(row) => `/simpanan/${row.row_id}`"
                    :search-placeholder="t('Cari nomor rekening, nama, atau NIK')"
                    :empty-title="t('Belum ada rekening simpanan')"
                    :empty-description="t('Buka rekening pertama untuk anggota yang membutuhkan layanan simpanan.')"
                >
                    <template #cell-account_number="{ row }">
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-bold text-primary">{{ row.account_number }}</p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ row.product.name }}</p>
                        </div>
                    </template>
                    <template #cell-member="{ row }">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-on-surface">{{ row.member.name }}</p>
                            <p class="text-xs text-on-surface-variant">{{ row.member.member_number || '—' }}</p>
                        </div>
                    </template>
                    <template #cell-product="{ row }">
                        <AppBadge :tone="['umum', 'program'].includes(row.product.category) ? 'success-soft' : 'primary-soft'">{{ row.product.category_label }}</AppBadge>
                    </template>
                    <template #cell-balance="{ row }">
                        <span class="font-bold" :class="row.balance > 0 ? 'text-on-surface' : 'text-on-surface-variant'">{{ money(row.balance) }}</span>
                    </template>
                    <template #cell-status="{ row }">
                        <AppBadge :tone="row.status === 'active' ? 'success-soft' : 'neutral'">{{ statusLabel(row.status) }}</AppBadge>
                    </template>
                    <template #cell-opened_at="{ row }">
                        <span class="whitespace-nowrap text-on-surface-variant">{{ row.opened_at || '—' }}</span>
                    </template>
                    <template #actions="{ row }">
                        <Link :href="`/simpanan/${row.row_id}`">
                            <AppIconButton name="chevron_right" tone="primary" size="sm" :tooltip="t('Buka rekening')" />
                        </Link>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
