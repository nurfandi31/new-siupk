<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    loans: { type: Object, required: true },
    tab: { type: String, default: 'proposal' },
    columns: { type: Array, required: true },
    search: { type: String, default: '' },
    perPage: { type: [Number, String], default: 15 },
    sort: { type: String, default: '' },
    direction: { type: String, default: 'desc' },
});

const tabs = [
    { key: 'proposal', label: 'Proposal', icon: 'inventory_2' },
    { key: 'verifikasi', label: 'Verifikasi', icon: 'task_alt' },
    { key: 'waiting', label: 'Waiting', icon: 'hourglass_top' },
    { key: 'aktif', label: 'Aktif', icon: 'payments' },
    { key: 'lunas', label: 'Lunas', icon: 'verified' },
];

const emptyMessages = {
    proposal: { title: 'Belum ada proposal individu', description: 'Belum ada pengajuan pinjaman individu yang baru didaftarkan.' },
    verifikasi: { title: 'Belum ada verifikasi', description: 'Tidak ada pinjaman individu yang sedang menunggu verifikasi.' },
    waiting: { title: 'Belum ada waiting', description: 'Tidak ada pinjaman individu yang menunggu keputusan pendanaan.' },
    aktif: { title: 'Belum ada pinjaman aktif', description: 'Tidak ada pinjaman individu yang sedang aktif berjalan.' },
    lunas: { title: 'Belum ada pinjaman lunas', description: 'Tidak ada pinjaman individu yang telah dilunasi.' },
};

const moneyFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatNumber(value) { if (value === null || value === undefined || value === '') return '—'; return moneyFormatter.format(Number(value)); }
function formatServiceRate(value) { return `${Number(value ?? 0).toFixed(2)}%`; }
function formatDate(value) { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date); }

function switchTab(tabKey) { router.get('/lending/member-loans', { tab: tabKey }, { preserveState: false }); }

const emptyMessage = computed(() => emptyMessages[props.tab] ?? { title: 'Belum ada data', description: 'Tidak ditemukan data pinjaman individu.' });
</script>

<template>
    <Head title="Tahapan Perguliran Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6 pb-12">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl">
                        Tahapan Perguliran Individu
                    </h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Pantau pergerakan pinjaman perorangan dari pengajuan hingga pelunasan.
                    </p>
                </div>
                <Link href="/lending/member-loans/create">
                    <AppButton icon="add">Register Proposal Individu</AppButton>
                </Link>
            </div>

            <div class="border-b border-outline-variant">
                <AppTabs
                    :model-value="tab"
                    :items="tabs"
                    variant="underline"
                    align="end"
                    aria-label="Tabs Status Pinjaman Individu"
                    @update:model-value="switchTab($event)"
                />
            </div>

            <AppCard :padded="false">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3 text-xs text-on-surface-variant">
                    <span>Total {{ loans?.total ?? 0 }} pinjaman pada tahap <strong class="text-on-surface">{{ tab.charAt(0).toUpperCase() + tab.slice(1) }}</strong>.</span>
                </div>
                <SmartDataTable
                    :rows="loans.data"
                    :columns="columns"
                    :pagination="loans"
                    :url="`/lending/member-loans?tab=${tab}`"
                    :search="search"
                    :per-page="perPage"
                    :sort="sort"
                    :direction="direction"
                    :row-href="(row) => `/lending/member-loans/${row.row_id}`"
                    search-label="Cari pinjaman individu"
                    search-placeholder="Cari nama anggota, NIK, atau nomor anggota"
                    :empty-title="emptyMessage.title"
                    :empty-description="emptyMessage.description"
                >
                    <template #cell-loan_number="{ row }">
                        <span class="font-bold text-on-surface">{{ row.loan_number || `#${row.row_id}` }}</span>
                    </template>

                    <template #cell-member_name="{ row }">
                        <div>
                            <p class="font-bold text-on-surface">{{ row.member_name }}</p>
                            <p class="text-xs text-on-surface-variant">
                                {{ row.member_number || '' }}<span v-if="row.nik"> · NIK {{ row.nik }}</span>
                                <span v-if="row.village_name"> · {{ row.village_name }}</span>
                            </p>
                        </div>
                    </template>

                    <template #cell-principal_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.principal_amount) }}</span>
                    </template>

                    <template #cell-proposed_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.proposed_amount) }}</span>
                    </template>

                    <template #cell-verification_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.verification_amount) }}</span>
                    </template>

                    <template #cell-allocated_amount="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.allocated_amount) }}</span>
                    </template>

                    <template #cell-principal_remaining="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.principal_remaining) }}</span>
                    </template>

                    <template #cell-total_interest_paid="{ row }">
                        <span class="tabular-nums font-bold text-on-surface">{{ formatNumber(row.total_interest_paid) }}</span>
                    </template>

                    <template #cell-service_rate="{ row }">
                        <span>{{ formatServiceRate(row.service_rate) }}</span>
                    </template>

                    <template #cell-proposed_at="{ row }">
                        <span>{{ formatDate(row.proposed_at) }}</span>
                    </template>

                    <template #cell-verified_at="{ row }">
                        <span>{{ formatDate(row.verified_at) }}</span>
                    </template>

                    <template #cell-funded_at="{ row }">
                        <span>{{ formatDate(row.funded_at) }}</span>
                    </template>

                    <template #cell-disbursed_at="{ row }">
                        <span>{{ formatDate(row.disbursed_at) }}</span>
                    </template>

                    <template #cell-completed_at="{ row }">
                        <span>{{ formatDate(row.completed_at) }}</span>
                    </template>

                    <template #cell-next_due_date="{ row }">
                        <span>{{ formatDate(row.next_due_date) }}</span>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
