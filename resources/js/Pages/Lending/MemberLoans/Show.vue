<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import AppPageHeader from '../../../Components/Lending/AppPageHeader.vue';
import AppDetailGrid from '../../../Components/Lending/AppDetailGrid.vue';
import AppStatGrid from '../../../Components/Lending/AppStatGrid.vue';
import AppStageTracker from '../../../Components/Lending/AppStageTracker.vue';

const props = defineProps({
    loan: { type: Object, required: true },
    card_url: { type: String, default: null },
    settlement_letter_url: { type: String, default: null },
    disbursement_account: { type: Object, default: null },
    disbursementAccounts: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    can: { type: Object, default: () => ({}) },
});

const moneyFmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

function fMoney(value) { if (value === null || value === undefined || value === '') return '—'; return moneyFmt.format(Number(value)); }
function fDate(value) { if (!value) return '—'; const d = new Date(value); return Number.isNaN(d.getTime()) ? '—' : dateFmt.format(d); }
function fDateTime(value) { if (!value) return '—'; const d = new Date(value); return Number.isNaN(d.getTime()) ? '—' : dateTimeFmt.format(d); }
function fNumber(value) { if (value === null || value === undefined || value === '') return '—'; return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value)); }
function percent(value) { return `${Number(value ?? 0).toFixed(2)}%`; }

function can(action) { return Boolean((props.can ?? {})[action]); }

const statusLabel = computed(() => {
    const map = {
        draft: 'Proposal',
        verified: 'Terverifikasi',
        waiting: 'Menunggu Pencairan',
        approved: 'Disetujui',
        active: 'Aktif',
        disbursed: 'Dicairkan',
        completed: 'Lunas',
        written_off: 'Dihapusbukukan',
        rescheduled: 'Reschedule',
        rejected: 'Tidak Layak',
    };
    return map[props.loan.status] ?? props.loan.status;
});
const statusVariant = computed(() => {
    const map = {
        draft: 'warning',
        verified: 'info-soft',
        waiting: 'info-soft',
        approved: 'primary',
        active: 'success-soft',
        disbursed: 'success-soft',
        completed: 'success',
        written_off: 'error',
        rescheduled: 'warning',
        rejected: 'error',
    };
    return map[props.loan.status] ?? 'neutral';
});
const pageTone = computed(() => {
    switch (statusVariant.value) {
        case 'primary': return 'primary';
        case 'success':
        case 'success-soft': return 'success';
        case 'warning': return 'warning';
        case 'error': return 'error';
        default: return 'neutral';
    }
});

const pipeline = computed(() => [
    { key: 'draft', label: 'Proposal', description: 'Pengajuan baru' },
    { key: 'verified', label: 'Verifikasi', description: 'Hasil verifikasi lapangan' },
    { key: 'waiting', label: 'Pencairan', description: 'Alokasi dana siap dicairkan' },
    { key: 'disbursed', label: 'Aktif', description: 'Pinjaman berjalan & angsuran' },
    { key: 'completed', label: 'Lunas', description: 'Validasi pelunasan' },
]);
const stageKey = computed(() => {
    if (props.loan.status === 'completed') return 'completed';
    if (['active', 'disbursed'].includes(props.loan.status)) return 'disbursed';
    if (['waiting', 'approved'].includes(props.loan.status)) return 'waiting';
    if (props.loan.status === 'verified') return 'verified';
    return 'draft';
});

// ===== Permission =====
const canVerify = computed(() => can('loans.verify') && props.loan.status === 'draft');
const canApprove = computed(() => can('loans.approve') && props.loan.status === 'verified');
const canDisburse = computed(() => can('loans.disburse') && ['waiting', 'approved'].includes(props.loan.status));
const canRevert = computed(() => can('loans.manage') && ['verified', 'waiting', 'approved'].includes(props.loan.status));
const canReject = computed(() => can('loans.manage') && ['draft', 'verified'].includes(props.loan.status));
const canComplete = computed(() => (can('loans.complete_director') || can('loans.manage')) && ['active', 'disbursed'].includes(props.loan.status));
const canWriteOff = computed(() => can('loans.write_off') && ['active', 'disbursed'].includes(props.loan.status));
const canReschedule = computed(() => (can('loans.reschedule_director') || can('loans.manage')) && ['active', 'disbursed'].includes(props.loan.status));
const hasActions = computed(() => canVerify.value || canApprove.value || canDisburse.value || canRevert.value || canReject.value || canComplete.value || canWriteOff.value || canReschedule.value);

const frequencyLabels = {
    weekly: 'Mingguan',
    biweekly: 'Dua Mingguan',
    monthly: 'Bulanan',
    bimonthly: 'Tiap 2 Bulan',
    quarterly: 'Tiap 3 Bulan',
    every_4_months: 'Tiap 4 Bulan',
    every_5_months: 'Tiap 5 Bulan',
    every_6_months: 'Tiap 6 Bulan',
    every_12_months: 'Tiap 12 Bulan',
    at_maturity: 'Sekaligus di Akhir',
};

// ===== Stat cards =====
const statCards = computed(() => [
    {
        key: 'principal', label: 'Plafon Pinjaman', icon: 'payments',
        iconBg: 'bg-primary-container/20 text-primary',
        value: fMoney(props.loan.proposed_amount ?? props.loan.principal_amount),
        sublabel: `${props.loan.term_months} bln · ${percent(props.loan.service_rate_total)}`,
    },
    {
        key: 'remaining', label: 'Sisa Pokok', icon: 'account_balance_wallet',
        iconBg: props.loan.principal_remaining > 0 ? 'bg-tertiary-fixed/30 text-tertiary' : 'bg-secondary-container/30 text-secondary',
        value: fMoney(props.loan.principal_remaining),
        sublabel: props.loan.principal_remaining > 0 ? `Dari ${fMoney(props.loan.principal_amount)}` : 'Lunas',
    },
    {
        key: 'installments', label: 'Angsuran Lunas', icon: 'task_alt',
        iconBg: 'bg-secondary-container/30 text-secondary',
        value: `${props.loan.paid_installments} / ${props.loan.total_installments}`,
        progress: props.loan.progress_percent,
        progressLabel: 'Progress pelunasan',
        progressClass: 'bg-secondary',
        sublabel: props.loan.next_due_date ? `Berikutnya ${fDate(props.loan.next_due_date)}` : 'Tidak ada jadwal',
    },
    {
        key: 'interest', label: 'Jasa Terbayar', icon: 'percent',
        iconBg: 'bg-primary-container/20 text-primary',
        value: fMoney(props.loan.total_interest_paid),
        sublabel: `Total jasa ${fMoney(props.loan.total_interest_due)}`,
    },
    {
        key: 'frequency', label: 'Sistem Angsuran', icon: 'event_repeat',
        iconBg: 'bg-surface-container-high text-on-surface-variant',
        value: frequencyLabels[props.loan.principal_frequency] ?? (props.loan.principal_frequency || '—'),
        sublabel: `Metode ${props.loan.installment_method || 'flat'}`,
    },
]);

// ===== Identitas fields =====
const borrowerIdentityFields = computed(() => [
    { key: 'full_name', label: 'Nama Lengkap', value: props.loan.member?.full_name },
    { key: 'member_number', label: 'Nomor Anggota', value: props.loan.member?.member_number },
    { key: 'nik', label: 'NIK', value: props.loan.member?.nik },
    { key: 'village', label: 'Desa', value: props.loan.member?.village?.name },
    { key: 'address', label: 'Alamat', value: props.loan.member?.address, span: 2 },
]);

const loanIdentityFields = computed(() => [
    { key: 'loan_number', label: 'No. Pinjaman', value: props.loan.loan_number || `#${props.loan.row_id}` },
    { key: 'product', label: 'Produk', value: props.loan.product ? `${props.loan.product.name} · ${props.loan.product.code}` : '—' },
    { key: 'proposed_at', label: 'Tanggal Pengajuan', value: fDate(props.loan.proposed_at) },
    { key: 'verified_at', label: 'Tanggal Verifikasi', value: fDate(props.loan.verified_at) },
    { key: 'approved_at', label: 'Tanggal Penetapan', value: fDate(props.loan.approved_at) },
    { key: 'funded_at', label: 'Rencana Pencairan', value: fDate(props.loan.funded_at) },
    { key: 'disbursed_at', label: 'Tanggal Cair', value: fDate(props.loan.disbursed_at) },
    { key: 'completed_at', label: 'Tanggal Selesai', value: fDate(props.loan.completed_at) },
    { key: 'rate', label: 'Tingkat Jasa Total', value: percent(props.loan.service_rate_total) },
]);

// ===== Forms & modals =====
const verifyOpen = ref(false);
const verifyForm = ref({
    verified_at: props.today,
    verification_amount: Number(props.loan.principal_amount ?? 0),
    verification_notes: 'Verifikasi pinjaman individu.',
});
function submitVerify() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/verify`, verifyForm.value, {
        preserveScroll: true,
        onSuccess: () => { verifyOpen.value = false; },
    });
}

const approveOpen = ref(false);
const approveForm = ref({
    approved_at: props.today,
    planned_disbursed_at: props.today,
    term_months: props.loan.term_months,
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    allocation_notes: '',
});
function submitApprove() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/approve`, approveForm.value, {
        preserveScroll: true,
        onSuccess: () => { approveOpen.value = false; },
    });
}

const disburseOpen = ref(false);
const disburseForm = ref({
    disbursed_at: props.today,
    disbursement_account_row_id: '',
    disbursement_notes: '',
    spk_no: '',
    disbursement_slot: '',
});
function submitDisburse() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/disburse`, disburseForm.value, {
        preserveScroll: true,
        onSuccess: () => { disburseOpen.value = false; },
    });
}

const revertOpen = ref(false);
const revertProcessing = ref(false);
function submitRevert() {
    revertProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/revert`, {}, {
        preserveScroll: true,
        onSuccess: () => { revertOpen.value = false; },
        onFinish: () => { revertProcessing.value = false; },
    });
}

const rejectOpen = ref(false);
const rejectForm = ref({ notes: '' });
function submitReject() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/reject`, rejectForm.value, {
        preserveScroll: true,
        onSuccess: () => { rejectOpen.value = false; },
    });
}

const completeOpen = ref(false);
const completeForm = ref({ completed_at: props.today, notes: '' });
function submitComplete() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/complete`, completeForm.value, {
        preserveScroll: true,
        onSuccess: () => { completeOpen.value = false; },
    });
}

const writeOffOpen = ref(false);
const writeOffForm = ref({ written_off_at: props.today, reason: '' });
function submitWriteOff() {
    router.post(`/lending/member-loans/${props.loan.row_id}/write-off`, writeOffForm.value, {
        preserveScroll: true,
        onSuccess: () => { writeOffOpen.value = false; },
    });
}

const rescheduleOpen = ref(false);
const rescheduleForm = ref({
    rescheduled_at: props.today,
    term_months: props.loan.term_months ?? 12,
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    installment_method: props.loan.installment_method ?? 'flat',
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    notes: '',
});
function submitReschedule() {
    router.post(`/lending/member-loans/${props.loan.row_id}/reschedule`, rescheduleForm.value, {
        preserveScroll: true,
        onSuccess: () => { rescheduleOpen.value = false; },
    });
}

// ===== Documents & tabs =====
const STAGE_INDIVIDUAL_VERIFICATION = ['verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_DISBURSEMENT = ['waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_SETTLEMENT = ['completed', 'written_off'];

const INDIVIDUAL_DOCUMENTS = [
    { key: 'analisis_keputusan_kredit', label: 'Analisa & Keputusan Kredit', stage: 'individual_verification', icon: 'analytics', stageLabel: 'Verifikasi' },
    { key: 'surat_pemberitahuan', label: 'Surat Pemberitahuan Kredit (SP2K)', stage: 'individual_disbursement', icon: 'campaign', stageLabel: 'Pencairan' },
    { key: 'spk_individu', label: 'Surat Perjanjian Kredit', stage: 'individual_disbursement', icon: 'gavel', stageLabel: 'Pencairan' },
    { key: 'kuitansi_pencairan_individu', label: 'Kuitansi Pencairan', stage: 'individual_disbursement', icon: 'receipt_long', stageLabel: 'Pencairan' },
    { key: 'berita_acara_pencairan_individu', label: 'Berita Acara Pencairan', stage: 'individual_disbursement', icon: 'fact_check', stageLabel: 'Pencairan' },
    { key: 'tanda_terima_jaminan', label: 'Tanda Terima Jaminan', stage: 'individual_disbursement', icon: 'inventory_2', stageLabel: 'Pencairan' },
    { key: 'pengikat_diri_penjamin', label: 'Surat Pengikat Diri Penjamin', stage: 'individual_disbursement', icon: 'volunteer_activism', stageLabel: 'Pencairan' },
    { key: 'surat_pernyataan_suami', label: 'Surat Pernyataan Suami/Istri', stage: 'individual_disbursement', icon: 'favorite', stageLabel: 'Pencairan' },
    { key: 'bukti_pengembalian_jaminan', label: 'Bukti Pengembalian Jaminan', stage: 'individual_settlement', icon: 'assignment_return', stageLabel: 'Pelunasan' },
];

const availableDocuments = computed(() => {
    const statusValue = props.loan.status;
    return INDIVIDUAL_DOCUMENTS.filter((doc) => {
        if (doc.stage === 'individual_verification') return STAGE_INDIVIDUAL_VERIFICATION.includes(statusValue);
        if (doc.stage === 'individual_disbursement') return STAGE_INDIVIDUAL_DISBURSEMENT.includes(statusValue);
        if (doc.stage === 'individual_settlement') return STAGE_INDIVIDUAL_SETTLEMENT.includes(statusValue);
        return false;
    });
});
const documentsByStage = computed(() => {
    const groups = {};
    for (const doc of availableDocuments.value) {
        if (!groups[doc.stage]) groups[doc.stage] = [];
        groups[doc.stage].push(doc);
    }
    return groups;
});
const docUrl = (key) => route('lending.member-loans.documents.print', { loan: props.loan.row_id, type: key });

// ===== Tabs =====
const activeTab = ref('overview');
const detailTabs = computed(() => [
    { key: 'overview', label: 'Ringkasan', icon: 'dashboard' },
    ...(props.loan.installments?.length ? [{ key: 'schedule', label: 'Jadwal Angsuran', icon: 'calendar_month', badge: props.loan.installments.length }] : []),
    ...(props.loan.payments?.length ? [{ key: 'payments', label: 'Pembayaran', icon: 'receipt_long', badge: props.loan.payments.length }] : []),
    { key: 'documents', label: 'Dokumen Cetak', icon: 'description', badge: availableDocuments.value.length },
    ...(props.loan.status_histories?.length ? [{ key: 'history', label: 'Riwayat Status', icon: 'history', badge: props.loan.status_histories.length }] : []),
]);

// ===== Navigation =====
const backTab = computed(() => {
    switch (props.loan.status) {
        case 'draft': return 'proposal';
        case 'verified': return 'verifikasi';
        case 'waiting':
        case 'approved': return 'waiting';
        case 'active':
        case 'disbursed': return 'aktif';
        case 'completed':
        case 'written_off':
        case 'rescheduled': return 'lunas';
        default: return 'proposal';
    }
});
const backUrl = computed(() => `/lending/member-loans?tab=${backTab.value}`);

const disbursementAccountOptions = computed(() => (props.disbursementAccounts ?? []).map((acc) => ({
    value: acc.row_id,
    label: `${acc.code} — ${acc.name}`,
})));

const installmentRows = computed(() => {
    const byNumber = new Map();
    for (const row of props.loan.installments ?? []) {
        const key = row.installment_number;
        const existing = byNumber.get(key) || { installment_number: key, due_date: row.due_date, principal_due: 0, principal_paid: 0, interest_due: 0, interest_paid: 0, status: row.status, paid_at: row.paid_at };
        existing.principal_due = Math.max(existing.principal_due, Number(row.principal_due));
        existing.interest_due += Number(row.interest_due);
        existing.principal_paid += Number(row.principal_paid);
        existing.interest_paid += Number(row.interest_paid);
        if (row.component === 'principal') existing.status = row.status;
        byNumber.set(key, existing);
    }
    return Array.from(byNumber.values()).sort((a, b) => a.installment_number - b.installment_number);
});

const paymentRows = computed(() => {
    const payments = props.loan.payments ?? [];
    const sorted = [...payments].sort((a, b) => new Date(a.paid_at) - new Date(b.paid_at));
    const totalPokok = Number(props.loan.principal_amount || 0);
    const totalJasa = Number(props.loan.total_interest_due || 0);
    let sumPokok = 0;
    let sumJasa = 0;
    return sorted.map((p) => {
        sumPokok += Number(p.principal_paid || 0);
        sumJasa += Number(p.interest_paid || 0);
        return {
            paid_at: p.paid_at,
            pokok: Number(p.principal_paid || 0),
            jasa: Number(p.interest_paid || 0),
            sum_pokok: sumPokok,
            sum_jasa: sumJasa,
            tunggakan_pokok: Math.max(0, totalPokok - sumPokok),
            tunggakan_jasa: Math.max(0, totalJasa - sumJasa),
        };
    });
});
</script>

<template>
    <Head title="Detail Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 pb-12">
            <AppPageHeader
                :back-href="backUrl"
                back-label="Kembali ke daftar pinjaman individu"
                :title="loan.loan_number || `Pinjam #${loan.row_id}`"
                :subtitle="loan.member?.full_name ? `${loan.member.full_name} · ${loan.product?.name || '—'}` : `Detail pinjaman perorangan · ${loan.product?.name || '—'}`"
                :breadcrumbs="[
                    { label: 'Pinjaman Individu', href: '/lending/member-loans' },
                    { label: backTab.charAt(0).toUpperCase() + backTab.slice(1), href: backUrl },
                    { label: loan.loan_number || `#${loan.row_id}` },
                ]"
                :tone="pageTone"
            >
                <template #badges>
                    <AppBadge :tone="statusVariant">{{ statusLabel }}</AppBadge>
                    <AppBadge tone="primary-soft">Individu</AppBadge>
                </template>

                <template #actions>
                    <a v-if="card_url" :href="card_url" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="credit_card" size="compact">Kartu Angsuran</AppButton>
                    </a>
                    <a v-if="settlement_letter_url" :href="settlement_letter_url" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="verified" size="compact">Surat Keterangan Lunas</AppButton>
                    </a>
                    <AppButton v-if="canVerify" variant="primary" icon="check" @click="verifyOpen = true">Verifikasi</AppButton>
                    <AppButton v-if="canApprove" variant="primary" icon="assignment_turned_in" @click="approveOpen = true">Alokasikan</AppButton>
                    <AppButton v-if="canDisburse" variant="primary" icon="payments" @click="disburseOpen = true">Catat Pencairan</AppButton>
                    <AppButton v-if="canComplete" variant="success" icon="task_alt" @click="completeOpen = true">Validasi Lunas</AppButton>
                    <AppButton v-if="canWriteOff" variant="secondary" icon="delete_sweep" @click="writeOffOpen = true">Hapus Piutang</AppButton>
                    <AppButton v-if="canReschedule" variant="secondary" icon="event_repeat" @click="rescheduleOpen = true">Reschedule</AppButton>
                    <AppButton v-if="canReject" variant="danger" icon="block" @click="rejectOpen = true">Tidak Layak</AppButton>
                    <AppButton v-if="canRevert" variant="ghost" icon="undo" @click="revertOpen = true">Kembalikan ke Proposal</AppButton>
                </template>
            </AppPageHeader>

            <AppStatGrid :stats="statCards" />

            <AppCard>
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Tahapan Pinjaman</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Posisi pinjaman individu pada pipeline perguliran.</p>
                        </div>
                        <AppBadge :tone="statusVariant" class="capitalize">{{ statusLabel }}</AppBadge>
                    </div>
                </template>
                <AppStageTracker :stages="pipeline" :current-key="stageKey" />
            </AppCard>

            <div class="border-b border-outline-variant">
                <AppTabs v-model="activeTab" :items="detailTabs" variant="underline" aria-label="Bagian detail pinjaman individu" />
            </div>

            <!-- ============== TAB RINGKASAN ============== -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <div class="grid gap-6 xl:grid-cols-3">
                    <AppCard class="xl:col-span-2">
                        <template #header>
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Data Peminjam</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Informasi identitas anggota peminjam.</p>
                            </div>
                        </template>
                        <AppDetailGrid :fields="borrowerIdentityFields" :columns="2" />
                    </AppCard>

                    <AppCard>
                        <template #header>
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Informasi Pinjaman</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">No. pinjaman &amp; parameter utama.</p>
                            </div>
                        </template>
                        <AppDetailGrid :fields="loanIdentityFields" :columns="1" />
                    </AppCard>
                </div>
            </div>

            <!-- ============== TAB JADWAL ANGSURAN ============== -->
            <AppCard v-if="activeTab === 'schedule'">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Jadwal Angsuran</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ loan.installments?.length ?? 0 }} entri angsuran · Sisa pokok {{ fMoney(loan.principal_remaining) }}</p>
                        </div>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">#</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-right">Pokok</th>
                                <th class="py-3 px-4 text-right">Jasa</th>
                                <th class="py-3 px-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="row in installmentRows" :key="row.installment_number">
                                <td class="py-3 px-4 text-on-surface-variant">Angsuran #{{ row.installment_number }}</td>
                                <td class="py-3 px-4 text-on-surface-variant">{{ fDate(row.due_date) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ fNumber(row.principal_due) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ fNumber(row.interest_due) }}</td>
                                <td class="py-3 px-4 text-right">
                                    <AppBadge :tone="row.status === 'paid' ? 'success' : (row.status === 'overdue' ? 'error' : 'warning')">{{ row.status }}</AppBadge>
                                </td>
                            </tr>
                            <tr v-if="!installmentRows.length">
                                <td colspan="5" class="py-6 px-4 text-center text-on-surface-variant">Belum ada jadwal angsuran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- ============== TAB PEMBAYARAN ============== -->
            <AppCard v-if="activeTab === 'payments'">
                <template #header>
                    <div>
                        <h2 class="text-base font-bold text-on-surface">Tabel Pembayaran</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">{{ paymentRows.length }} kali pembayaran tercatat.</p>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">Tanggal Pembayaran</th>
                                <th class="py-3 px-4 text-right">Pokok</th>
                                <th class="py-3 px-4 text-right">Jasa</th>
                                <th class="py-3 px-4 text-right">Sum Pokok</th>
                                <th class="py-3 px-4 text-right">Sum Jasa</th>
                                <th class="py-3 px-4 text-right">Tunggakan Pokok</th>
                                <th class="py-3 px-4 text-right">Tunggakan Jasa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="(row, idx) in paymentRows" :key="idx">
                                <td class="py-3 px-4 text-on-surface-variant">{{ fDate(row.paid_at) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ fNumber(row.pokok) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ fNumber(row.jasa) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ fNumber(row.sum_pokok) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ fNumber(row.sum_jasa) }}</td>
                                <td class="py-3 px-4 text-right text-error">{{ fNumber(row.tunggakan_pokok) }}</td>
                                <td class="py-3 px-4 text-right text-error">{{ fNumber(row.tunggakan_jasa) }}</td>
                            </tr>
                            <tr v-if="!paymentRows.length">
                                <td colspan="7" class="py-6 px-4 text-center text-on-surface-variant">Belum ada pembayaran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- ============== TAB DOKUMEN CETAK ============== -->
            <div v-if="activeTab === 'documents'" class="space-y-6">
                <AppCard v-if="availableDocuments.length">
                    <template #header>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Dokumen Cetak</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Pilih dokumen untuk membuka PDF di tab baru. Untuk tanda terima & bukti jaminan, pastikan data jaminan sudah diisi sebelum cetak.</p>
                            </div>
                            <span class="text-xs font-semibold text-on-surface-variant">{{ availableDocuments.length }} dokumen tersedia</span>
                        </div>
                    </template>
                    <div class="space-y-5">
                        <div v-for="(docs, stage) in documentsByStage" :key="stage">
                            <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-on-surface-variant">{{ docs[0]?.stageLabel }}</h3>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <a v-for="doc in docs" :key="doc.key" :href="docUrl(doc.key)" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 transition hover:border-primary hover:bg-surface-container-low">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary-container/20 text-primary">
                                        <AppIcon :name="doc.icon" class="text-lg" />
                                    </span>
                                    <span class="flex-1 text-sm font-medium text-on-surface">{{ doc.label }}</span>
                                    <AppIcon name="open_in_new" class="text-base text-on-surface-variant" />
                                </a>
                            </div>
                        </div>
                    </div>
                </AppCard>
                <AppEmptyState v-else icon="description" title="Belum ada dokumen untuk tahap ini" description="Pinjaman individu pada status saat ini belum memiliki dokumen cetak yang tersedia." />
            </div>

            <!-- ============== TAB RIWAYAT STATUS ============== -->
            <AppCard v-if="activeTab === 'history'">
                <template #header>
                    <div>
                        <h2 class="text-base font-bold text-on-surface">Riwayat Status</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">Timeline perubahan status dan parameter audit.</p>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Perubahan</th>
                                <th class="py-3 px-4">Nominal</th>
                                <th class="py-3 px-4">Catatan</th>
                                <th class="py-3 px-4">Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="(h, idx) in loan.status_histories" :key="idx">
                                <td class="py-3 px-4 text-on-surface-variant">{{ fDateTime(h.changed_at) }}</td>
                                <td class="py-3 px-4">
                                    <span class="font-semibold text-on-surface">{{ h.from_status ?? '—' }}</span>
                                    <span class="mx-1 text-on-surface-variant">→</span>
                                    <span class="font-semibold text-primary">{{ h.to_status }}</span>
                                </td>
                                <td class="py-3 px-4 tabular-nums text-on-surface">{{ h.principal_amount !== null ? fNumber(h.principal_amount) : '—' }}</td>
                                <td class="py-3 px-4 text-on-surface-variant">{{ h.notes || '—' }}</td>
                                <td class="py-3 px-4 text-on-surface">{{ h.changed_by_user_name || `#${h.changed_by_user_id}` }}</td>
                            </tr>
                            <tr v-if="!loan.status_histories?.length">
                                <td colspan="5" class="py-6 px-4 text-center text-on-surface-variant">Belum ada perubahan status.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>

        <!-- ============== MODALS (Teleport body) ============== -->
        <Teleport to="body">
            <div v-if="verifyOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="verifyOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Verifikasi Proposal</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Konfirmasi bahwa proposal pinjaman individu ini telah diverifikasi.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitVerify">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Verifikasi</span>
                            <input v-model="verifyForm.verified_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nominal Verifikasi</span>
                            <input v-model.number="verifyForm.verification_amount" type="number" min="0" step="0.01" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="verifyForm.verification_notes" rows="3" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="verifyOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="check">Verifikasi</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="approveOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="approveOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Alokasikan Pinjaman</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Tetapkan tanggal pencairan dan parameter pinjaman.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitApprove">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Persetujuan</span>
                            <input v-model="approveForm.approved_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Rencana Tanggal Cair</span>
                            <input v-model="approveForm.planned_disbursed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan Alokasi</span>
                            <textarea v-model="approveForm.allocation_notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="approveOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="assignment_turned_in">Setujui</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="disburseOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="disburseOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Catat Pencairan</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitDisburse">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Cair</span>
                            <input v-model="disburseForm.disbursed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Rekening Sumber Dana</span>
                            <select v-model="disburseForm.disbursement_account_row_id" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                                <option value="">Pilih rekening…</option>
                                <option v-for="acc in disbursementAccountOptions" :key="acc.value" :value="acc.value">{{ acc.label }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nomor SPK (opsional)</span>
                            <input v-model="disburseForm.spk_no" type="text" maxlength="80" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Waktu &amp; Tempat Pencairan (opsional)</span>
                            <input v-model="disburseForm.disbursement_slot" type="text" maxlength="120" placeholder="mis. 09:00 WIB · Kantor BUMDes" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="disburseForm.disbursement_notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="disburseOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="payments">Catat Pencairan</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="completeOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="completeOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Validasi Pelunasan</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitComplete">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Lunas</span>
                            <input v-model="completeForm.completed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="completeForm.notes" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="completeOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="task_alt">Tandai Lunas</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="writeOffOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="writeOffOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Hapus Piutang</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitWriteOff">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Penghapusan</span>
                            <input v-model="writeOffForm.written_off_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan</span>
                            <textarea v-model="writeOffForm.reason" rows="3" required maxlength="500" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="writeOffOpen = false">Batal</AppButton>
                            <AppButton variant="danger" type="submit" icon="delete_sweep">Hapus Piutang</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="rescheduleOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="rescheduleOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Reschedule Pinjaman</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Sisa pokok akan dialihkan ke pinjaman baru.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitReschedule">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Reschedule</span>
                            <input v-model="rescheduleForm.rescheduled_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Jangka Baru (bulan)</span>
                            <input v-model.number="rescheduleForm.term_months" type="number" min="1" max="120" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="rescheduleForm.notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="rescheduleOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="event_repeat">Reschedule</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="rejectOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="rejectOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Tidak Layak</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Pinjaman akan ditandai sebagai Tidak Layak dan tidak dapat dicairkan.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitReject">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan Penolakan</span>
                            <textarea v-model="rejectForm.notes" rows="3" maxlength="5000" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="rejectOpen = false">Batal</AppButton>
                            <AppButton variant="danger" type="submit" icon="block">Tolak</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="revertOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="revertOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl ring-1 ring-outline-variant/40">
                    <h3 class="text-lg font-bold text-on-surface">Kembalikan ke Proposal</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Status pinjaman akan dikembalikan ke Proposal (Draft).</p>
                    <div class="mt-4 flex justify-end gap-2">
                        <AppButton variant="secondary" type="button" @click="revertOpen = false">Batal</AppButton>
                        <AppButton variant="primary" :disabled="revertProcessing" icon="undo" @click="submitRevert">Ya, Kembalikan</AppButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
