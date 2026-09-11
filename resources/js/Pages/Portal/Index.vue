<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppModal from '../../Components/AppModal.vue';
import SmartDataTable from '../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useMoney } from '../../composables/useMoney';

const props = defineProps({
    profile: { type: Object, required: true },
    loanSummary: { type: Object, required: true },
    loans: { type: Object, required: true },
    officers: { type: Array, default: () => [] },
    activeGroups: { type: Array, default: () => [] },
});

const { money } = useMoney();
const expandedLoan = ref(null);
const loanSearch = computed(() => props.loans.search ?? '');
const detailOpen = computed({
    get: () => selectedLoan.value !== null,
    set: (value) => { if (!value) selectedLoan.value = null; },
});
const selectedLoan = ref(null);

const stats = computed(() => [
    { label: 'Total Pinjaman', value: String(props.loanSummary.total || 0), icon: 'receipt_long', tone: 'primary' },
    { label: 'Nominal Disalurkan', value: money(props.loanSummary.total_disbursed), icon: 'payments', tone: 'success' },
    { label: 'Pinjaman Aktif', value: String(props.loanSummary.active_count || 0), icon: 'schedule', tone: 'warning' },
]);

const loanStatus = {
    draft: { label: 'Proposal', tone: 'neutral' },
    verified: { label: 'Verifikasi', tone: 'warning' },
    waiting: { label: 'Menunggu Pencairan', tone: 'warning' },
    approved: { label: 'Disetujui', tone: 'warning' },
    active: { label: 'Aktif', tone: 'success' },
    disbursed: { label: 'Aktif', tone: 'success' },
    completed: { label: 'Lunas', tone: 'primary' },
    rescheduled: { label: 'Reschedule', tone: 'neutral' },
    cancelled: { label: 'Batal', tone: 'error' },
    written_off: { label: 'Dihapus', tone: 'error' },
};
const positionLabels = { chair: 'Ketua', secretary: 'Sekretaris', treasurer: 'Bendahara' };

const loanColumns = [
    { key: 'loan_number', label: 'Nomor', class: 'w-36' },
    { key: 'disbursed_at', label: 'Tanggal Cair', class: 'w-32' },
    { key: 'status', label: 'Status', class: 'w-36' },
    { key: 'principal_amount', label: 'Pokok', class: 'w-32 text-right' },
    { key: 'term_months', label: 'Tenor', class: 'w-20' },
    { key: 'paid', label: 'Sudah Dibayar', class: 'w-36 text-right' },
    { key: 'arrears', label: 'Kondisi', class: 'w-36' },
    { key: 'detail', label: 'Detail', class: 'w-20' },
];
function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Portal Saya" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex items-start gap-4">
                <AppIcon name="account_circle" tone="primary" :container-size="12" container-shape="pill" />
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Portal Saya</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">Ringkasan data keanggotaan dan pinjaman Anda.</p>
                </div>
            </header>

            <AppCard>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Nama</p><p class="mt-1 font-semibold text-primary">{{ profile.name || '—' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Nomor Anggota</p><p class="mt-1 font-semibold">{{ profile.member_number || '—' }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Status</p><AppBadge class="mt-2" :tone="profile.status === 'active' ? 'success' : 'neutral'">{{ profile.status === 'active' ? 'Aktif' : (profile.status || '—') }}</AppBadge></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Bergabung</p><p class="mt-1 font-semibold">{{ formatDate(profile.registered_at) }}</p></div>
                    <div><p class="text-xs font-bold uppercase text-on-surface-variant">Unit Organisasi</p><p class="mt-1 font-semibold">{{ profile.organization_unit || '—' }}</p></div>
                </div>
            </AppCard>

            <div class="grid gap-4 sm:grid-cols-3">
                <AppCard v-for="stat in stats" :key="stat.label">
                    <div class="flex items-center gap-3">
                        <AppIcon :name="stat.icon" :tone="stat.tone" />
                        <div><p class="text-xs font-bold uppercase text-on-surface-variant">{{ stat.label }}</p><p class="mt-1 text-xl font-bold text-primary">{{ stat.value }}</p></div>
                    </div>
                </AppCard>
            </div>

            <AppCard>
                <template #header>
                    <h2 class="font-bold text-primary">Riwayat Pinjaman</h2>
                </template>
                <div class="lg:px-6 lg:-mx-6">
                    <SmartDataTable
                        :rows="loans.data"
                        :columns="loanColumns"
                        :pagination="loans"
                        url="/portal"
                        :search="loanSearch"
                        :per-page="loans.per_page || 15"
                        :sort="loans.sort || 'disbursed_at'"
                        :direction="loans.direction || 'desc'"
                        search-placeholder="Cari nomor pinjaman..."
                        empty-title="Belum ada pinjaman"
                        empty-description="Riwayat pinjaman Anda akan tampil di sini."
                    >
                        <template #cell-disbursed_at="{ row }">{{ formatDate(row.disbursed_at) }}</template>
                        <template #cell-status="{ row }"><AppBadge :tone="(loanStatus[row.status] || loanStatus.draft).tone">{{ (loanStatus[row.status] || { label: row.status }).label }}</AppBadge></template>
                        <template #cell-principal_amount="{ row }">{{ money(row.principal_amount) }}</template>
                        <template #cell-term_months="{ row }">{{ row.term_months }} bulan</template>
                        <template #cell-paid="{ row }">{{ money(row.paid) }}</template>
                        <template #cell-arrears="{ row }"><AppBadge :tone="row.has_arrears ? 'warning' : 'success'">{{ row.has_arrears ? 'Ada tunggakan' : 'Lancar' }}</AppBadge></template>
                        <template #cell-detail="{ row }">
                            <AppButton variant="ghost" size="compact" icon="visibility" tooltip="Detail Angsuran" @click="selectedLoan = row" />
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>

            <AppModal v-model="detailOpen" :title="`Detail Pinjaman ${selectedLoan?.loan_number || selectedLoan?.id || ''}`" size="full">
                <div class="space-y-6">
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div><dt class="text-on-surface-variant">Pokok</dt><dd class="font-semibold">{{ money(selectedLoan?.principal_amount) }}</dd></div>
                        <div><dt class="text-on-surface-variant">Sisa Pokok</dt><dd class="font-semibold">{{ money((selectedLoan?.principal_due || 0) - (selectedLoan?.principal_paid || 0)) }}</dd></div>
                        <div><dt class="text-on-surface-variant">Sisa Jasa</dt><dd class="font-semibold">{{ money((selectedLoan?.interest_due || 0) - (selectedLoan?.interest_paid || 0)) }}</dd></div>
                        <div><dt class="text-on-surface-variant">Sisa Denda</dt><dd class="font-semibold">{{ money((selectedLoan?.penalty_due || 0) - (selectedLoan?.penalty_paid || 0)) }}</dd></div>
                    </dl>
                    <div class="overflow-x-auto rounded-xl bg-surface-container-low">
                        <table class="w-full min-w-[42rem] text-left text-sm">
                            <thead class="bg-surface-container-high text-xs uppercase text-on-surface-variant">
                                <tr><th class="px-4 py-3">Angsuran</th><th class="px-4 py-3">Jatuh Tempo</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dibayar Pada</th><th class="px-4 py-3 text-right">Pokok</th><th class="px-4 py-3 text-right">Jasa</th><th class="px-4 py-3 text-right">Denda</th><th class="px-4 py-3 text-right">Sisa</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="installment in selectedLoan?.installments || []" :key="installment.installment_number" class="border-t border-outline-variant">
                                    <td class="px-4 py-3 font-semibold">#{{ installment.installment_number }}</td>
                                    <td class="px-4 py-3">{{ formatDate(installment.due_date) }}</td>
                                    <td class="px-4 py-3"><AppBadge :tone="installment.status === 'paid' ? 'success' : (installment.status === 'partial' ? 'warning' : 'neutral')">{{ installment.status || '—' }}</AppBadge></td>
                                    <td class="px-4 py-3">{{ formatDate(installment.paid_at) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money(installment.principal_due) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money(installment.interest_due) }}</td>
                                    <td class="px-4 py-3 text-right">{{ money(installment.penalty_due) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ money(installment.due - installment.paid) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <section>
                        <h3 class="text-sm font-bold uppercase text-on-surface-variant">Daftar Pemanfaat</h3>
                        <AppEmptyState
                            v-if="!selectedLoan?.beneficiaries?.length"
                            icon="group"
                            title="Tidak ada data pemanfaat"
                            class="mt-3"
                        />
                        <div v-else class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                                    <tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3 text-right">Bagian</th></tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    <tr v-for="beneficiary in selectedLoan.beneficiaries" :key="beneficiary.member_row_id">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold">{{ beneficiary.name }}</span>
                                                <AppBadge v-if="beneficiary.is_self" tone="primary">Anda</AppBadge>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">{{ beneficiary.is_self ? money(beneficiary.allocated_amount) : '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <template #footer><AppButton variant="secondary" @click="detailOpen = false">Tutup</AppButton></template>
            </AppModal>

            <AppCard>
                <template #header><h2 class="font-bold text-primary">Riwayat Pengurus</h2></template>
                <ul v-if="officers.length" class="space-y-3">
                    <li v-for="officer in officers" :key="`${officer.group_row_id}-${officer.position}-${officer.started_at}`" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-container-low p-4">
                        <div><p class="font-semibold">{{ officer.group_name || '—' }}</p><p class="text-xs text-on-surface-variant">{{ positionLabels[officer.position] || officer.position }} · {{ formatDate(officer.started_at) }} s.d. {{ formatDate(officer.ended_at) }}</p></div>
                        <AppBadge :tone="officer.ended_at ? 'neutral' : 'success'">{{ officer.ended_at ? 'Selesai' : 'Aktif' }}</AppBadge>
                    </li>
                </ul>
                <AppEmptyState v-else icon="groups" title="Belum ada riwayat pengurus" description="Riwayat kepengurusan kelompok akan tampil di sini." />
            </AppCard>

            <AppCard>
                <template #header><h2 class="font-bold text-primary">Anggota Kelompok Yang Dipimpin</h2></template>
                <div v-if="activeGroups.length" class="space-y-5">
                    <section v-for="group in activeGroups" :key="group.group_name">
                        <h3 class="text-sm font-bold uppercase text-on-surface-variant">{{ group.group_name }}</h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="member in group.members" :key="`${group.group_name}-${member.member_number}`" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface-container-low p-4">
                                <div><p class="font-semibold">{{ member.name || '—' }}</p><p class="text-xs text-on-surface-variant">{{ member.member_number || '—' }}</p></div>
                                <AppBadge :tone="member.status === 'active' ? 'success' : 'neutral'">{{ member.status === 'active' ? 'Aktif' : (member.status || '—') }}</AppBadge>
                            </li>
                        </ul>
                        <AppEmptyState v-if="!group.members.length" icon="group" title="Belum ada anggota lain" description="Kelompok aktif ini belum memiliki anggota lain." />
                    </section>
                </div>
                <AppEmptyState v-else icon="groups" title="Tidak ada kepengurusan aktif" description="Daftar anggota kelompok hanya tampil saat Anda pengurus aktif." />
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
