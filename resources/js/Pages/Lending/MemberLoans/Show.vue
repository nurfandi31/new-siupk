<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import AppPageHeader from '../../../Components/Lending/AppPageHeader.vue';
import AppDetailGrid from '../../../Components/Lending/AppDetailGrid.vue';

const props = defineProps({
    loan: { type: Object, required: true },
    card_url: { type: String, default: null },
    settlement_letter_url: { type: String, default: null },
    disbursement_account: { type: Object, default: null },
    disbursementAccounts: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    can: { type: Object, default: () => ({}) },
    documents: { type: Array, default: () => [] },
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

function stageStatusOf(stage, idx) {
    const currentIdx = pipeline.value.findIndex((s) => s.key === stageKey.value);
    if (currentIdx < 0) return 'upcoming';
    if (idx < currentIdx) return 'done';
    if (idx === currentIdx) return 'current';
    return 'upcoming';
}

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
const canPrintDocument = computed(() => can('loans.view'));

const canEditProposal = computed(() => can('loans.manage') && ['draft', 'verified'].includes(props.loan.status));
const canDeleteProposal = computed(() => (can('loans.manage') || can('loans.propose')) && props.loan.status === 'draft');
const showProposalManager = computed(() => canEditProposal.value || canDeleteProposal.value);

const editHref = computed(() => (canEditProposal.value ? `/lending/member-loans/${props.loan.row_id}/edit` : null));

const deleteOpen = ref(false);
const deleteProcessing = ref(false);
async function confirmDelete() {
    deleteProcessing.value = true;
    router.delete(`/lending/member-loans/${props.loan.row_id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteOpen.value = false; },
        onError: () => { deleteProcessing.value = false; },
        onFinish: () => { deleteProcessing.value = false; },
    });
}

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

const installmentMethodLabels = {
    flat: 'Flat',
    annuity: 'Anuitas',
    effective: 'Efektif Menurun',
    declining: 'Menurun',
};

const installmentMethodMeta = {
    flat: { tone: 'primary', icon: 'straighten', description: 'Bunga dihitung dari plafond awal.' },
    annuity: { tone: 'tertiary', icon: 'all_inclusive', description: 'Angsuran tetap tiap periode.' },
    effective: { tone: 'secondary', icon: 'trending_down', description: 'Bunga efektif dari sisa pokok.' },
    declining: { tone: 'secondary', icon: 'trending_down', description: 'Bunga menurun sesuai sisa pokok.' },
};

// ===== Stat cards =====
// Didesain ulang dengan gaya Material 3 modern: tiap kartu punya accent tone,
// eyebrow kontekstual, suffix unit, delta ringkas, dan footer informatif.
const loanAmount = Number(props.loan.proposed_amount ?? props.loan.principal_amount ?? 0);
const principalRemaining = Number(props.loan.principal_remaining ?? 0);
const principalAmount = Number(props.loan.principal_amount ?? 0);
const paidInstallments = Number(props.loan.paid_installments ?? 0);
const totalInstallments = Number(props.loan.total_installments ?? 0);
const termMonths = Number(props.loan.term_months ?? 0);
const serviceRate = Number(props.loan.service_rate_total ?? 0);
const totalInterestPaid = Number(props.loan.total_interest_paid ?? 0);
const totalInterestDue = Number(props.loan.total_interest_due ?? 0);
const progressPercent = Number(props.loan.progress_percent ?? 0);

const principalPaidRatio = principalAmount > 0
    ? Math.min(100, Math.round(((principalAmount - principalRemaining) / principalAmount) * 100))
    : 0;

const statCards = computed(() => [
    {
        key: 'principal',
        label: 'Plafon Pinjaman',
        eyebrow: props.loan.loan_number || `ID #${props.loan.row_id}`,
        icon: 'payments',
        iconBg: 'bg-primary-container/35 text-primary',
        accent: 'primary',
        value: fMoney(loanAmount),
        suffix: 'IDR',
        sublabel: `${termMonths} bulan · tingkat jasa ${percent(serviceRate)}`,
        footer: 'Nilai pokok yang disepakati',
        footerIcon: 'info',
    },
    {
        key: 'remaining',
        label: 'Sisa Pokok',
        eyebrow: props.loan.principal_remaining > 0 ? 'Belum tertagih' : 'Selesai',
        icon: props.loan.principal_remaining > 0 ? 'account_balance_wallet' : 'task_alt',
        iconBg: props.loan.principal_remaining > 0
            ? 'bg-tertiary-fixed/40 text-tertiary-deep'
            : 'bg-secondary-container/40 text-secondary',
        accent: props.loan.principal_remaining > 0 ? 'tertiary' : 'secondary',
        value: fMoney(principalRemaining),
        suffix: 'IDR',
        sublabel: principalAmount > 0 ? `Dari ${fMoney(principalAmount)}` : '—',
        delta: principalAmount > 0 ? `${principalPaidRatio}% terbayar` : null,
        deltaTone: principalPaidRatio >= 100 ? 'flat' : 'down',
        deltaClass: principalPaidRatio >= 100
            ? 'bg-emerald-500/15 text-emerald-700'
            : 'bg-tertiary-fixed/30 text-tertiary-deep',
        footer: principalAmount > 0
            ? `Sisa ${Math.max(0, 100 - principalPaidRatio)}% dari plafond`
            : 'Tidak ada sisa pinjaman',
        footerIcon: 'account_balance_wallet',
    },
    {
        key: 'installments',
        label: 'Angsuran Lunas',
        eyebrow: totalInstallments > 0 ? `${totalInstallments} periode` : 'Belum dijadwalkan',
        icon: 'event_available',
        iconBg: 'bg-secondary-container/40 text-secondary',
        accent: 'secondary',
        value: `${paidInstallments} / ${totalInstallments}`,
        suffix: paidInstallments === 1 ? 'angsuran' : 'angsuran',
        sublabel: props.loan.next_due_date
            ? `Jatuh tempo berikutnya ${fDate(props.loan.next_due_date)}`
            : 'Tidak ada jadwal tersisa',
        progress: progressPercent,
        progressLabel: 'Progress pelunasan',
        progressClass: 'bg-secondary',
        footer: totalInstallments > 0
            ? `${Math.max(0, totalInstallments - paidInstallments)} angsuran tersisa`
            : 'Menunggu penjadwalan',
        footerIcon: 'schedule',
    },
    {
        key: 'interest',
        label: 'Jasa Terbayar',
        eyebrow: `Total jasa ${fMoney(totalInterestDue)}`,
        icon: 'percent',
        iconBg: 'bg-primary-container/35 text-primary',
        accent: 'primary',
        value: fMoney(totalInterestPaid),
        suffix: 'IDR',
        sublabel: totalInterestDue > 0
            ? `${Math.min(100, Math.round((totalInterestPaid / totalInterestDue) * 100))}% dari total jasa`
            : '—',
        delta: totalInterestDue > 0
            ? `${fMoney(Math.max(0, totalInterestDue - totalInterestPaid))} sisa`
            : null,
        deltaTone: totalInterestDue - totalInterestPaid <= 0 ? 'flat' : 'down',
        deltaClass: totalInterestDue - totalInterestPaid <= 0
            ? 'bg-emerald-500/15 text-emerald-700'
            : 'bg-primary-container/25 text-primary',
        footer: 'Akumulasi jasa yang sudah dibayar',
        footerIcon: 'savings',
    },
    {
        key: 'installment_method',
        label: 'Metode Angsuran',
        eyebrow: 'Sistem pembayaran',
        icon: installmentMethodMeta[props.loan.installment_method]?.icon ?? 'calculate',
        iconBg: 'bg-tertiary-fixed/40 text-tertiary-deep',
        accent: 'tertiary',
        value: installmentMethodLabels[props.loan.installment_method] ?? (props.loan.installment_method || '—'),
        sublabel: installmentMethodMeta[props.loan.installment_method]?.description
            ?? 'Metode perhitungan jasa pinjaman.',
        footer: `Pokok: ${frequencyLabels[props.loan.principal_frequency] ?? (props.loan.principal_frequency || '—')}`,
        footerIcon: 'autorenew',
    },
]);

// ===== Identitas fields =====
const borrowerIdentityFields = computed(() => [
    { key: 'full_name', label: 'Nama Lengkap', value: props.loan.member?.full_name, hint: `ID Pinjaman: ${props.loan.loan_number || `#${props.loan.row_id}`}` },
    { key: 'member_number', label: 'Nomor Anggota', value: props.loan.member?.member_number },
    { key: 'nik', label: 'NIK', value: props.loan.member?.nik },
    { key: 'village', label: 'Desa', value: props.loan.member?.village?.name },
    { key: 'address', label: 'Alamat', value: props.loan.member?.address, span: 2 },
]);

const loanIdentityFields = computed(() => [
    { key: 'proposed_at', label: 'Tanggal Pengajuan', value: fDate(props.loan.proposed_at) },
    { key: 'verified_at', label: 'Tanggal Verifikasi', value: fDate(props.loan.verified_at) },
    { key: 'approved_at', label: 'Tanggal Penetapan', value: fDate(props.loan.approved_at) },
    { key: 'funded_at', label: 'Rencana Pencairan', value: fDate(props.loan.funded_at) },
    { key: 'disbursed_at', label: 'Tanggal Cair', value: fDate(props.loan.disbursed_at) },
    { key: 'completed_at', label: 'Tanggal Selesai', value: fDate(props.loan.completed_at) },
]);

// ===== Forms & modals =====
// Pilihan sistem angsuran (sama dengan SIUPK: dropdown dinamis di server, fallback di klien).
const FREQUENCY_OPTIONS = [
    { value: 'monthly', label: 'Bulanan' },
    { value: 'weekly', label: 'Mingguan' },
    { value: 'biweekly', label: 'Dua Mingguan' },
    { value: 'at_maturity', label: 'Sekaligus (Jatuh Tempo)' },
];

// ===== Forms (form inline di tab Aksi, bukan popup) =====
// State form & submit handler untuk form yang ditampilkan di section tab "Aksi".
const verifyForm = ref({
    verified_at: props.today,
    verification_amount: Number(props.loan.principal_amount ?? 0),
    term_months: Number(props.loan.term_months ?? 12),
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: Number(props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(props.loan.interest_grace_months ?? 0),
    verification_notes: 'Verifikasi pinjaman individu.',
});
const verifyProcessing = ref(false);
function submitVerify() {
    verifyProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/verify`, verifyForm.value, {
        preserveScroll: true,
        onFinish: () => { verifyProcessing.value = false; },
    });
}

const approveForm = ref({
    approved_at: props.today,
    planned_disbursed_at: props.today,
    allocation_amount: Number(props.loan.principal_amount ?? 0),
    term_months: Number(props.loan.term_months ?? 12),
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: Number(props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(props.loan.interest_grace_months ?? 0),
    spk_no: props.loan.spk_no ?? '',
    allocation_notes: '',
});
const approveProcessing = ref(false);
function submitApprove() {
    approveProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/approve`, approveForm.value, {
        preserveScroll: true,
        onFinish: () => { approveProcessing.value = false; },
    });
}

const disburseForm = ref({
    disbursed_at: props.today,
    disbursement_account_row_id: '',
    disbursement_notes: '',
    spk_no: '',
    disbursement_slot: '',
});
const disburseProcessing = ref(false);
function submitDisburse() {
    disburseProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/disburse`, disburseForm.value, {
        preserveScroll: true,
        onFinish: () => { disburseProcessing.value = false; },
    });
}

const hasAnyAction = computed(() =>
    canVerify.value || canApprove.value || canDisburse.value || canComplete.value
    || canWriteOff.value || canReschedule.value || canReject.value || canRevert.value,
);

const revertProcessing = ref(false);
function submitRevert() {
    revertProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/revert`, {}, {
        preserveScroll: true,
        onFinish: () => { revertProcessing.value = false; },
    });
}

const rejectForm = ref({ notes: '' });
const rejectProcessing = ref(false);
function submitReject() {
    rejectProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/reject`, rejectForm.value, {
        preserveScroll: true,
        onFinish: () => { rejectProcessing.value = false; },
    });
}

const completeForm = ref({ completed_at: props.today, notes: '' });
const completeProcessing = ref(false);
function submitComplete() {
    completeProcessing.value = true;
    router.patch(`/lending/member-loans/${props.loan.row_id}/complete`, completeForm.value, {
        preserveScroll: true,
        onFinish: () => { completeProcessing.value = false; },
    });
}

const writeOffForm = ref({ written_off_at: props.today, reason: '' });
const writeOffProcessing = ref(false);
function submitWriteOff() {
    writeOffProcessing.value = true;
    router.post(`/lending/member-loans/${props.loan.row_id}/write-off`, writeOffForm.value, {
        preserveScroll: true,
        onFinish: () => { writeOffProcessing.value = false; },
    });
}

const rescheduleForm = ref({
    rescheduled_at: props.today,
    term_months: props.loan.term_months ?? 12,
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    installment_method: props.loan.installment_method ?? 'flat',
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    notes: '',
});
const rescheduleProcessing = ref(false);
function submitReschedule() {
    rescheduleProcessing.value = true;
    router.post(`/lending/member-loans/${props.loan.row_id}/reschedule`, rescheduleForm.value, {
        preserveScroll: true,
        onFinish: () => { rescheduleProcessing.value = false; },
    });
}

// ===== Tabs =====
const activeTab = ref('overview');
const detailTabs = computed(() => [
    { key: 'overview', label: 'Ringkasan', icon: 'dashboard' },
    ...(props.loan.installments?.length ? [{ key: 'schedule', label: 'Jadwal Angsuran', icon: 'calendar_month', badge: props.loan.installments.length }] : []),
    ...(props.loan.payments?.length ? [{ key: 'payments', label: 'Pembayaran', icon: 'receipt_long', badge: props.loan.payments.length }] : []),
    ...(props.loan.status_histories?.length ? [{ key: 'history', label: 'Riwayat Status', icon: 'history', badge: props.loan.status_histories.length }] : []),
    ...(hasAnyAction.value ? [{ key: 'actions', label: 'Aksi', icon: 'bolt' }] : []),
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

// ===== Documents modal =====
const STAGE_INDIVIDUAL_PROPOSAL = ['draft', 'verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_VERIFICATION = ['verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_DISBURSEMENT = ['waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_SETTLEMENT = ['completed', 'written_off'];

const STAGE_META = {
    individual_proposal: { label: 'Proposal', description: 'Dokumen yang disiapkan pada tahap pengajuan proposal.', tone: 'warning' },
    individual_verification: { label: 'Verifikasi', description: 'Dokumen verifikasi lapangan.', tone: 'primary' },
    individual_disbursement: { label: 'Pencairan', description: 'Dokumen tahap pencairan & penyaluran.', tone: 'success' },
    individual_settlement: { label: 'Penyelesaian', description: 'Dokumen setelah pinjaman selesai.', tone: 'neutral' },
};
const STAGE_KEYS = ['individual_proposal', 'individual_verification', 'individual_disbursement', 'individual_settlement'];

const docSearch = ref('');
const documentModalOpen = ref(false);
const activeDocument = ref(null);
const documentPreviewKey = ref(0);

function openDocumentModal(doc) {
    activeDocument.value = doc;
    documentPreviewKey.value += 1;
    documentModalOpen.value = true;
}
function closeDocumentModal() {
    documentModalOpen.value = false;
    activeDocument.value = null;
}

const availableDocuments = computed(() => {
    const statusValue = props.loan.status;
    const stageAllowed = (stage) => {
        if (stage === 'individual_proposal') return STAGE_INDIVIDUAL_PROPOSAL.includes(statusValue);
        if (stage === 'individual_verification') return STAGE_INDIVIDUAL_VERIFICATION.includes(statusValue);
        if (stage === 'individual_disbursement') return STAGE_INDIVIDUAL_DISBURSEMENT.includes(statusValue);
        if (stage === 'individual_settlement') return STAGE_INDIVIDUAL_SETTLEMENT.includes(statusValue);
        return false;
    };
    return (props.documents ?? [])
        .filter((d) => stageAllowed(d.stage))
        .map((d) => ({ ...d, url: `/lending/member-loans/${props.loan.row_id}/documents/${d.key}` }));
});

const documentsByStage = computed(() => {
    const docs = availableDocuments.value;
    const query = docSearch.value.trim().toLowerCase();
    const filtered = query ? docs.filter((d) => d.label.toLowerCase().includes(query) || d.key.includes(query)) : docs;
    const buckets = { individual_proposal: [], individual_verification: [], individual_disbursement: [], individual_settlement: [] };
    for (const d of filtered) {
        if (buckets[d.stage]) buckets[d.stage].push(d);
    }
    return buckets;
});

const visibleStageKeys = computed(() => STAGE_KEYS.filter((stage) => documentsByStage.value[stage].length > 0));
</script>

<template>
    <Head title="Detail Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto w-full max-w-7xl space-y-5 pb-12">
            <!-- COMPACT HEADER BAR (plain flex, seperti Loans/Show.vue) -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex items-center gap-2 text-xs text-on-surface-variant" aria-label="Breadcrumb">
                    <Link :href="backUrl">
                        <AppButton type="button" variant="secondary" icon="arrow_back" size="compact">Kembali ke Daftar</AppButton>
                    </Link>
                </nav>
                <div class="flex flex-wrap items-center gap-2">
                    <a v-if="card_url" :href="card_url" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="credit_card" size="compact">
                            <span class="hidden sm:inline">Kartu Angsuran</span>
                            <span class="sm:hidden">Kartu</span>
                        </AppButton>
                    </a>
                    <a v-if="settlement_letter_url" :href="settlement_letter_url" target="_blank" rel="noopener">
                        <AppButton variant="secondary" icon="verified" size="compact">
                            <span class="hidden md:inline">Surat Keterangan Lunas</span>
                            <span class="md:hidden">SKL</span>
                        </AppButton>
                    </a>
                    <AppButton v-if="canPrintDocument" type="button" variant="secondary" icon="description" size="compact" @click="documentModalOpen = true">
                        <span class="hidden sm:inline">Cetak Dokumen</span>
                        <span class="sm:hidden">Cetak</span>
                        <span v-if="availableDocuments.length" class="ml-1 rounded-full bg-primary/15 px-1.5 py-0.5 text-[10px] font-bold text-primary">{{ availableDocuments.length }}</span>
                    </AppButton>
                </div>
            </div>

            <!-- ============== RINGKASAN PINJAMAN (REDESAIN) ============== -->
            <section aria-label="Ringkasan metrik pinjaman" class="space-y-3">
                <!-- Header section -->
                <div class="flex flex-wrap items-center justify-between gap-2 px-0.5">
                    <div class="flex min-w-0 items-center gap-2">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary-container/30 text-primary">
                            <AppIcon name="space_dashboard" class="text-base" />
                        </span>
                        <div class="min-w-0 leading-tight">
                            <h2 class="truncate text-sm font-bold tracking-tight text-on-surface">Ringkasan Pinjaman</h2>
                            <p class="truncate text-[11px] text-on-surface-variant">Snapshot metrik utama pinjaman</p>
                        </div>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary-container/30 px-2.5 py-1 text-[11px] font-bold text-primary">
                        <AppIcon name="monitoring" class="text-[12px] leading-none" />
                        <span>Live · {{ loan.installments?.length ?? 0 }} entri</span>
                    </span>
                </div>

                <!-- Grid utama: 4 kolom di desktop -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4 lg:auto-rows-fr">
                    <!-- HERO: Plafon Pinjaman (2×2 di desktop) -->
                    <article
                        class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-primary-container/25 p-4 shadow-md sm:p-5 lg:col-span-2 lg:row-span-2"
                    >
                        <!-- Zona 1: Header + identitas -->
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Plafon Pinjaman</p>
                                <p class="mt-0.5 truncate text-base font-extrabold tracking-tight text-on-surface sm:text-lg" :title="loan.loan_number || `#${loan.row_id}`">
                                    {{ loan.loan_number || `#${loan.row_id}` }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <AppBadge :tone="statusVariant">{{ statusLabel }}</AppBadge>
                                    <AppBadge tone="primary-soft">Individu</AppBadge>
                                    <span v-if="loan.member?.full_name" class="hidden min-w-0 items-center gap-1 text-[11px] font-semibold text-on-surface sm:inline-flex">
                                        <AppIcon name="person" class="text-[13px] leading-none text-primary" />
                                        <span class="truncate" :title="loan.member.full_name">{{ loan.member.full_name }}</span>
                                    </span>
                                </div>
                            </div>
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary shadow-md">
                                <AppIcon name="payments" class="text-[20px] leading-none" />
                            </span>
                        </header>

                        <!-- Zona 2: Nilai utama (dipisah border tipis atas) -->
                        <div class="mt-4 border-t border-outline-variant/40 pt-4">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-on-surface-variant">Nilai Pokok Disepakati</p>
                            <p class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight text-on-surface sm:text-3xl lg:text-4xl">
                                {{ fMoney(loanAmount) }}
                            </p>
                            <p class="mt-1 text-[11px] font-semibold text-on-surface-variant">
                                IDR · {{ termMonths }} bulan · tingkat jasa {{ percent(serviceRate) }}
                            </p>
                        </div>

                        <!-- Zona 3: Spec strip bawah (auto ke bawah) -->
                        <dl class="mt-auto grid grid-cols-3 gap-1.5 pt-4 sm:gap-2">
                            <div class="min-w-0 rounded-lg bg-surface-container-low px-2 py-2 sm:px-2.5">
                                <dt class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">Produk</dt>
                                <dd class="mt-0.5 truncate text-[11px] font-bold text-on-surface" :title="loan.product?.name">{{ loan.product?.name ?? '—' }}</dd>
                            </div>
                            <div class="min-w-0 rounded-lg bg-surface-container-low px-2 py-2 sm:px-2.5">
                                <dt class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">Jangka</dt>
                                <dd class="mt-0.5 text-[11px] font-bold text-on-surface">{{ termMonths }} bulan</dd>
                            </div>
                            <div class="min-w-0 rounded-lg bg-surface-container-low px-2 py-2 sm:px-2.5">
                                <dt class="text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">Tingkat Jasa</dt>
                                <dd class="mt-0.5 text-[11px] font-bold tabular-nums text-on-surface">{{ percent(serviceRate) }}</dd>
                            </div>
                        </dl>
                    </article>

                    <!-- Sisa Pokok -->
                    <article class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 shadow-md transition hover:-translate-y-0.5 sm:p-5">
                        <header class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Sisa Pokok</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-on-surface-variant/80">{{ principalRemaining > 0 ? 'Belum tertagih' : 'Lunas' }}</p>
                            </div>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-tertiary-fixed/40 text-tertiary-deep">
                                <AppIcon :name="principalRemaining > 0 ? 'account_balance_wallet' : 'task_alt'" class="text-[15px] leading-none" />
                            </span>
                        </header>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <p class="text-xl font-extrabold tabular-nums tracking-tight text-on-surface sm:text-2xl">{{ fMoney(principalRemaining) }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">IDR</span>
                        </div>

                        <div class="mt-2 flex items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="h-1.5 overflow-hidden rounded-full bg-surface-container-high">
                                    <div class="h-full rounded-full bg-tertiary transition-all duration-500" :style="{ width: `${principalPaidRatio}%` }"></div>
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full bg-tertiary-fixed/40 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-tertiary-deep">
                                {{ principalPaidRatio }}%
                            </span>
                        </div>

                        <p class="mt-auto pt-3 text-[11px] leading-snug text-on-surface-variant">
                            {{ principalPaidRatio >= 100 ? 'Pokok sudah lunas' : `Sisa ${Math.max(0, 100 - principalPaidRatio)}% dari plafond` }}
                        </p>
                    </article>

                    <!-- Angsuran Lunas -->
                    <article class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 shadow-md transition hover:-translate-y-0.5 sm:p-5">
                        <header class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Angsuran Lunas</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-on-surface-variant/80">{{ totalInstallments > 0 ? `${totalInstallments} periode` : 'Belum dijadwalkan' }}</p>
                            </div>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-secondary-container/40 text-secondary">
                                <AppIcon name="event_available" class="text-[15px] leading-none" />
                            </span>
                        </header>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <p class="text-xl font-extrabold tabular-nums tracking-tight text-on-surface sm:text-2xl">
                                {{ paidInstallments }}<span class="text-on-surface-variant/60">/{{ totalInstallments }}</span>
                            </p>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">angsuran</span>
                        </div>

                        <div class="mt-2">
                            <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <span>Progress pelunasan</span>
                                <span class="tabular-nums text-on-surface">{{ progressPercent }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-container-high">
                                <div class="h-full rounded-full bg-secondary transition-all duration-500" :style="{ width: `${Math.min(100, Math.max(0, progressPercent))}%` }"></div>
                            </div>
                        </div>

                        <p class="mt-auto pt-3 text-[11px] leading-snug text-on-surface-variant">
                            {{ Math.max(0, totalInstallments - paidInstallments) }} angsuran tersisa · Berikutnya {{ props.loan.next_due_date ? fDate(props.loan.next_due_date) : '—' }}
                        </p>
                    </article>

                    <!-- Jasa Terbayar -->
                    <article class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 shadow-md transition hover:-translate-y-0.5 sm:p-5">
                        <header class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Jasa Terbayar</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-on-surface-variant/80">Total jasa {{ fMoney(totalInterestDue) }}</p>
                            </div>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-container/35 text-primary">
                                <AppIcon name="percent" class="text-[15px] leading-none" />
                            </span>
                        </header>

                        <div class="mt-3 flex items-baseline gap-1.5">
                            <p class="text-xl font-extrabold tabular-nums tracking-tight text-on-surface sm:text-2xl">{{ fMoney(totalInterestPaid) }}</p>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">IDR</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-on-surface-variant">{{ totalInterestDue > 0 ? `${Math.min(100, Math.round((totalInterestPaid / totalInterestDue) * 100))}% dari total jasa` : '—' }}</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-emerald-700">
                                <AppIcon name="savings" class="text-[12px] leading-none" />
                                {{ fMoney(Math.max(0, totalInterestDue - totalInterestPaid)) }}
                            </span>
                        </div>

                        <p class="mt-auto pt-3 text-[11px] leading-snug text-on-surface-variant">
                            Akumulasi jasa yang sudah dibayar
                        </p>
                    </article>

                    <!-- Metode Angsuran -->
                    <article class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-surface-container-lowest p-4 shadow-md transition hover:-translate-y-0.5 sm:p-5">
                        <header class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">Metode Angsuran</p>
                                <p class="mt-0.5 text-[11px] font-semibold text-on-surface-variant/80">Sistem pembayaran</p>
                            </div>
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-tertiary-fixed/40 text-tertiary-deep">
                                <AppIcon :name="installmentMethodMeta[props.loan.installment_method]?.icon ?? 'calculate'" class="text-[15px] leading-none" />
                            </span>
                        </header>

                        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1">
                            <p class="text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl">
                                {{ installmentMethodLabels[props.loan.installment_method] ?? (props.loan.installment_method || '—') }}
                            </p>
                            <AppBadge tone="warning-soft">{{ installmentMethodMeta[props.loan.installment_method]?.icon ? 'Aktif' : 'Standar' }}</AppBadge>
                        </div>

                        <p class="mt-1 text-[11px] leading-snug text-on-surface-variant">
                            {{ installmentMethodMeta[props.loan.installment_method]?.description ?? 'Metode perhitungan jasa pinjaman.' }}
                        </p>

                        <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 pt-3 text-[11px] text-on-surface-variant">
                            <span class="inline-flex items-center gap-1">
                                <AppIcon name="autorenew" class="text-[12px] leading-none text-primary" />
                                Pokok: {{ frequencyLabels[props.loan.principal_frequency] ?? (props.loan.principal_frequency || '—') }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <AppIcon name="percent" class="text-[12px] leading-none text-tertiary" />
                                Jasa: {{ frequencyLabels[props.loan.interest_frequency] ?? (props.loan.interest_frequency || '—') }}
                            </span>
                            <span v-if="(props.loan.principal_grace_months ?? 0) > 0 || (props.loan.interest_grace_months ?? 0) > 0" class="inline-flex items-center gap-1">
                                <AppIcon name="hourglass_empty" class="text-[12px] leading-none text-on-surface-variant" />
                                Grace · P{{ props.loan.principal_grace_months ?? 0 }} / J{{ props.loan.interest_grace_months ?? 0 }} bln
                            </span>
                        </div>
                    </article>
                </div>
            </section>

            <!-- TAB BAR (segmented control, dibungkus card seperti Loans/Show.vue) -->
            <div class="-mx-1 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest px-1.5 py-1.5 shadow-sm">
                <nav class="flex flex-nowrap items-stretch gap-1.5" aria-label="Bagian detail pinjaman individu">
                    <button
                        v-for="tab in detailTabs"
                        :key="tab.key"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-bold transition sm:px-5"
                        :class="activeTab === tab.key
                            ? 'bg-primary text-on-primary shadow-sm'
                            : 'text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface'"
                        @click="activeTab = tab.key"
                    >
                        <AppIcon :name="tab.icon" class="text-lg" />
                        <span>{{ tab.label }}</span>
                        <span
                            v-if="tab.badge"
                            :class="['rounded-full px-2 py-0.5 text-[11px] font-extrabold', activeTab === tab.key ? 'bg-on-primary/20 text-on-primary' : 'bg-surface-container-high text-on-surface-variant']"
                        >
                            {{ tab.badge }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- ============== TAB RINGKASAN ============== -->
            <div v-if="activeTab === 'overview'" class="space-y-5">
                <!-- Kelola Proposal (modern, prominent) -->
                <article v-if="showProposalManager" class="overflow-hidden rounded-2xl border border-primary/30 bg-primary-container/20 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-primary/30 bg-primary-container/30 px-4 py-3 sm:px-5">
                        <div class="flex items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-on-primary shadow-sm">
                                <AppIcon name="edit_note" class="text-lg" />
                            </span>
                            <div>
                                <h2 class="text-sm font-bold text-on-surface">Kelola Proposal</h2>
                                <p class="text-[11px] text-on-surface-variant">Proposal masih bisa diubah atau dihapus selama belum masuk tahap alokasi dana.</p>
                            </div>
                        </div>
                        <AppBadge tone="primary-soft">Tahap Proposal</AppBadge>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-5">
                        <p class="max-w-2xl text-sm text-on-surface-variant">
                            Ubah parameter pinjaman (plafon, jangka, sistem angsuran, atau catatan verifikasi) atau hapus proposal jika pendaftaran salah. Tindakan hapus bersifat permanen dan akan menghapus seluruh rancangan angsuran.
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link v-if="canEditProposal" :href="editHref">
                                <AppButton variant="secondary" icon="edit">Edit Proposal</AppButton>
                            </Link>
                            <AppButton v-if="canDeleteProposal" variant="danger" icon="delete_outline" @click="deleteOpen = true">Hapus Proposal</AppButton>
                        </div>
                    </div>
                </article>

                <!-- Identitas + Timeline (2 kolom di desktop) -->
                <div class="grid gap-5 xl:grid-cols-3">
                    <!-- Data Peminjam (2/3) -->
                    <article class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md xl:col-span-2">
                        <header class="flex items-start gap-3 border-b border-outline-variant/60 px-4 py-4 sm:px-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-container text-primary">
                                <AppIcon name="person" class="text-xl" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-base font-bold text-on-surface">Data Peminjam</h2>
                                    <AppBadge tone="primary-soft">Anggota</AppBadge>
                                </div>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Informasi identitas anggota peminjam.</p>
                            </div>
                            <!-- Avatar inisial (disembunyikan di mobile biar hemat ruang) -->
                            <div v-if="loan.member?.full_name" class="hidden size-12 shrink-0 items-center justify-center rounded-full bg-primary text-base font-extrabold text-on-primary sm:flex">
                                {{ loan.member.full_name.split(' ').map(s => s[0]).slice(0, 2).join('').toUpperCase() }}
                            </div>
                        </header>
                        <div class="px-4 py-4 sm:px-5">
                            <AppDetailGrid :fields="borrowerIdentityFields" :columns="2" />
                        </div>
                    </article>

                    <!-- Informasi Pinjaman / Timeline (1/3) -->
                    <article class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                        <header class="flex items-start gap-3 border-b border-outline-variant/60 px-4 py-4 sm:px-5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-tertiary-fixed/30 text-tertiary">
                                <AppIcon name="event" class="text-xl" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-on-surface">Timeline Pinjaman</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Tanggal penting siklus pinjaman.</p>
                            </div>
                        </header>
                        <div class="px-4 py-4 sm:px-5">
                            <AppDetailGrid :fields="loanIdentityFields" :columns="1" />
                        </div>
                    </article>
                </div>
            </div>

            <!-- ============== TAB JADWAL ANGSURAN ============== -->
            <AppCard v-if="activeTab === 'schedule'" :padded="false">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4 sm:px-6 sm:pt-6">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Jadwal Angsuran</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ loan.installments?.length ?? 0 }} entri angsuran · Sisa pokok {{ fMoney(loan.principal_remaining) }}</p>
                        </div>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-b-xl">
                    <table class="w-full min-w-[640px] text-left text-sm">
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
            <AppCard v-if="activeTab === 'payments'" :padded="false">
                <template #header>
                    <div class="px-4 pt-4 sm:px-6 sm:pt-6">
                        <h2 class="text-base font-bold text-on-surface">Tabel Pembayaran</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">{{ paymentRows.length }} kali pembayaran tercatat.</p>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-b-xl">
                    <table class="w-full min-w-[720px] text-left text-sm">
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

            <!-- ============== TAB RIWAYAT STATUS ============== -->
            <AppCard v-if="activeTab === 'history'" :padded="false">
                <template #header>
                    <div class="px-4 pt-4 sm:px-6 sm:pt-6">
                        <h2 class="text-base font-bold text-on-surface">Riwayat Status</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">Timeline perubahan status dan parameter audit.</p>
                    </div>
                </template>

                <!-- Aksi inline pindah ke tab strip di atas (samping kanan daftar tab). -->
                <div class="overflow-x-auto rounded-b-xl">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">Waktu</th>
                                <th class="py-3 px-4">Perubahan</th>
                                <th class="py-3 px-4">Nominal</th>
                                <th class="py-3 px-4">Jangka · Jasa · Sistem</th>
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
                                <td class="py-3 px-4 text-on-surface-variant">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        <span v-if="h.term_months !== null"><AppIcon name="schedule" class="mr-1 text-sm align-middle" />{{ h.term_months }} bln</span>
                                        <span v-if="h.service_rate_total !== null"><AppIcon name="percent" class="mr-1 text-sm align-middle" />{{ percent(h.service_rate_total) }}</span>
                                        <span v-if="h.principal_frequency"><AppIcon name="autorenew" class="mr-1 text-sm align-middle" />{{ frequencyLabels[h.principal_frequency] ?? h.principal_frequency }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-on-surface-variant">{{ h.notes || '—' }}</td>
                                <td class="py-3 px-4 text-on-surface">{{ h.changed_by_user_name || `#${h.changed_by_user_id}` }}</td>
                            </tr>
                            <tr v-if="!loan.status_histories?.length">
                                <td colspan="6" class="py-6 px-4 text-center text-on-surface-variant">Belum ada perubahan status.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- ============== TAB AKSI (form inline, bukan popup) ============== -->
            <section v-if="activeTab === 'actions'" class="space-y-5">
                <!-- Form Verifikasi (draft → verified) -->
                <div v-if="canVerify" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-primary-container text-primary">
                            <AppIcon name="verified" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Form Verifikasi</h3>
                            <p class="text-xs text-on-surface-variant">Lengkapi tanggal, parameter pendanaan, dan catatan verifikasi.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitVerify">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Verifikasi</span>
                                <input v-model="verifyForm.verified_at" type="date" required :max="today" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nominal Verifikasi (Rp)</span>
                                <input v-model.number="verifyForm.verification_amount" type="number" min="0" step="1000" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Jangka (bulan)</span>
                                <input v-model.number="verifyForm.term_months" type="number" min="1" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Prosentase Jasa (%)</span>
                                <input v-model.number="verifyForm.service_rate_total" type="number" min="0" max="5000" step="0.01" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Sistem Angs. Pokok</span>
                                <select v-model="verifyForm.principal_frequency" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                                    <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Sistem Angs. Jasa</span>
                                <select v-model="verifyForm.interest_frequency" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                                    <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Grace Pokok (bulan)</span>
                                <input v-model.number="verifyForm.principal_grace_months" type="number" min="0" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Grace Jasa (bulan)</span>
                                <input v-model.number="verifyForm.interest_grace_months" type="number" min="0" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan Verifikasi <span class="text-error">*</span></span>
                            <textarea v-model="verifyForm.verification_notes" rows="3" required minlength="3" maxlength="5000" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                            <p v-if="verifyForm.errors.verification_notes" class="mt-1 text-xs text-error">{{ verifyForm.errors.verification_notes }}</p>
                            <p class="mt-1 text-[10px] text-on-surface-variant">Wajib diisi untuk audit trail.</p>
                        </label>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-2">
                                <AppButton v-if="canReject" type="button" variant="danger" icon="block" @click="submitReject">Tidak Layak</AppButton>
                                <AppButton type="submit" :loading="verifyProcessing" icon="check">Simpan & Verifikasi</AppButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Form Penetapan Alokasi (verified → waiting) -->
                <div v-if="canApprove" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-tertiary-fixed/30 text-tertiary">
                            <AppIcon name="fact_check" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Penetapan Alokasi</h3>
                            <p class="text-xs text-on-surface-variant">Tetapkan tanggal persetujuan & parameter pinjaman final.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitApprove">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Persetujuan</span>
                                <input v-model="approveForm.approved_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Rencana Tanggal Cair</span>
                                <input v-model="approveForm.planned_disbursed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alokasi Rp (Final)</span>
                                <input v-model.number="approveForm.allocation_amount" type="number" min="0" step="1000" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Jangka (bulan)</span>
                                <input v-model.number="approveForm.term_months" type="number" min="1" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Prosentase Jasa (%)</span>
                                <input v-model.number="approveForm.service_rate_total" type="number" min="0" max="5000" step="0.01" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nomor SPK (opsional)</span>
                                <input v-model="approveForm.spk_no" type="text" maxlength="80" placeholder="mis. SPK/2024/001" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Sistem Angs. Pokok</span>
                                <select v-model="approveForm.principal_frequency" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                                    <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Sistem Angs. Jasa</span>
                                <select v-model="approveForm.interest_frequency" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                                    <option v-for="opt in FREQUENCY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Grace Pokok (bulan)</span>
                                <input v-model.number="approveForm.principal_grace_months" type="number" min="0" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Grace Jasa (bulan)</span>
                                <input v-model.number="approveForm.interest_grace_months" type="number" min="0" max="120" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan Alokasi</span>
                            <textarea v-model="approveForm.allocation_notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-2">
                                <AppButton v-if="canRevert" type="button" variant="ghost" icon="undo" @click="submitRevert">Kembalikan ke Proposal</AppButton>
                                <AppButton type="submit" :loading="approveProcessing" icon="assignment_turned_in">Simpan & Alokasikan</AppButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Form Pencairan (waiting → active) -->
                <div v-if="canDisburse" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-secondary-container text-secondary">
                            <AppIcon name="payments" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Form Pencairan</h3>
                            <p class="text-xs text-on-surface-variant">Catat tanggal dan akun sumber dana saat pinjaman dicairkan.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitDisburse">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Cair</span>
                                <input v-model="disburseForm.disbursed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Rekening Sumber Dana</span>
                                <select v-model="disburseForm.disbursement_account_row_id" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                                    <option value="">Pilih rekening…</option>
                                    <option v-for="acc in disbursementAccountOptions" :key="acc.value" :value="acc.value">{{ acc.label }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nomor SPK (opsional)</span>
                                <input v-model="disburseForm.spk_no" type="text" maxlength="80" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Waktu & Tempat Pencairan (opsional)</span>
                                <input v-model="disburseForm.disbursement_slot" type="text" maxlength="120" placeholder="mis. 09:00 WIB · Kantor BUMDes" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan Pencairan</span>
                            <textarea v-model="disburseForm.disbursement_notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-2">
                                <AppButton v-if="canRevert" type="button" variant="ghost" icon="undo" @click="submitRevert">Kembalikan ke Proposal</AppButton>
                                <AppButton type="submit" :loading="disburseProcessing" icon="payments">Catat Pencairan</AppButton>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Validasi Lunas (active/disbursed → completed) -->
                <div v-if="canComplete" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-tertiary-fixed/30 text-tertiary">
                            <AppIcon name="task_alt" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Validasi Pelunasan</h3>
                            <p class="text-xs text-on-surface-variant">Konfirmasi tanggal pinjaman dilunasi seluruhnya.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitComplete">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Lunas</span>
                            <input v-model="completeForm.completed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="completeForm.notes" rows="2" maxlength="500" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end pt-1">
                            <AppButton type="submit" :loading="completeProcessing" icon="task_alt">Tandai Lunas</AppButton>
                        </div>
                    </form>
                </div>

                <!-- Hapus Piutang (active/disbursed → written_off) -->
                <div v-if="canWriteOff" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-error-container text-error">
                            <AppIcon name="delete_sweep" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Hapus Piutang</h3>
                            <p class="text-xs text-on-surface-variant">Tandai pinjaman sebagai piutang yang dihapus (write-off).</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitWriteOff">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Penghapusan</span>
                            <input v-model="writeOffForm.written_off_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan Penghapusan</span>
                            <textarea v-model="writeOffForm.reason" rows="3" required maxlength="500" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end pt-1">
                            <AppButton type="submit" variant="danger" :loading="writeOffProcessing" icon="delete_sweep">Hapus Piutang</AppButton>
                        </div>
                    </form>
                </div>

                <!-- Reschedule (active/disbursed → rescheduled) -->
                <div v-if="canReschedule" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-primary-container text-primary">
                            <AppIcon name="event_repeat" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Reschedule Pinjaman</h3>
                            <p class="text-xs text-on-surface-variant">Sisa pokok akan dialihkan ke pinjaman baru dengan jangka waktu baru.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitReschedule">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Reschedule</span>
                                <input v-model="rescheduleForm.rescheduled_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Jangka Baru (bulan)</span>
                                <input v-model.number="rescheduleForm.term_months" type="number" min="1" max="120" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none">
                            </label>
                        </div>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan Reschedule</span>
                            <textarea v-model="rescheduleForm.notes" rows="2" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end pt-1">
                            <AppButton type="submit" :loading="rescheduleProcessing" icon="event_repeat">Reschedule</AppButton>
                        </div>
                    </form>
                </div>

                <!-- Tidak Layak (draft/verified → rejected) -->
                <div v-if="canReject && !canVerify && !canApprove" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-error-container text-error">
                            <AppIcon name="block" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Tandai Tidak Layak</h3>
                            <p class="text-xs text-on-surface-variant">Pinjaman akan ditandai sebagai Tidak Layak dan tidak dapat dicairkan.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-4 sm:p-6" @submit.prevent="submitReject">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan Penolakan</span>
                            <textarea v-model="rejectForm.notes" rows="3" maxlength="5000" class="mt-1 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end pt-1">
                            <AppButton type="submit" variant="danger" :loading="rejectProcessing" icon="block">Tolak Pinjaman</AppButton>
                        </div>
                    </form>
                </div>

                <!-- Kembalikan ke Proposal (verified/waiting/approved → draft) -->
                <div v-if="canRevert && !canApprove && !canDisburse" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-4 py-3 sm:px-6 sm:py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-surface-container-high text-on-surface">
                            <AppIcon name="undo" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Kembalikan ke Proposal</h3>
                            <p class="text-xs text-on-surface-variant">Status pinjaman akan dikembalikan ke Proposal (Draft).</p>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <p class="text-sm text-on-surface-variant">Klik tombol di bawah untuk mengembalikan status pinjaman ke tahap proposal.</p>
                        <div class="mt-4 flex justify-end">
                            <AppButton type="button" :loading="revertProcessing" icon="undo" @click="submitRevert">Kembalikan ke Proposal</AppButton>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- ============== MODALS (Teleport body) ============== -->
        <Teleport to="body">
            <!-- Form verifikasi/alokasi/cairkan/lunas/hapus/reschedule/tolak/kembalikan
                 semuanya sudah dipindahkan ke tab "Aksi" sebagai form inline (lihat section di atas).
            </div>

            <div v-if="deleteOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="deleteOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-error-container text-error">
                            <AppIcon name="warning" class="text-xl" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-on-surface">Hapus Proposal Pinjaman?</h3>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                Proposal <strong>{{ loan.loan_number || `#${loan.row_id}` }}</strong> akan dihapus permanen bersama rancangan jadwal angsuran. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <AppButton variant="secondary" type="button" :disabled="deleteProcessing" @click="deleteOpen = false">Batal</AppButton>
                        <AppButton variant="danger" icon="delete_outline" :disabled="deleteProcessing" @click="confirmDelete">
                            {{ deleteProcessing ? 'Menghapus…' : 'Ya, Hapus Proposal' }}
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- ============== MODAL CETAK DOKUMEN ============== -->
            <AppModal v-model="documentModalOpen" :title="`Cetak Dokumen — ${loan.loan_number || `#${loan.row_id}`}`" size="fullscreen">
                <div class="-m-5 sm:-m-6 grid h-[calc(100vh-8rem)] grid-cols-1 md:grid-cols-[320px_minmax(0,1fr)] md:h-[calc(100vh-7rem)]">
                    <div class="flex min-h-0 flex-col border-b border-outline-variant md:border-b-0 md:border-r">
                        <div class="space-y-3 border-b border-outline-variant p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Daftar Dokumen</p>
                                <span class="text-[10px] font-semibold text-on-surface-variant">{{ availableDocuments.length }} dokumen</span>
                            </div>
                            <AppInput v-model="docSearch" type="search" icon="search" label="Cari dokumen" :hide-label="true" placeholder="Cari dokumen..." />
                        </div>
                        <div class="flex-1 overflow-y-auto p-3">
                            <div v-if="visibleStageKeys.length === 0" class="grid place-items-center px-2 py-8 text-center text-sm text-on-surface-variant">
                                <div>
                                    <AppIcon name="search_off" class="text-3xl" />
                                    <p class="mt-2 font-semibold">Tidak ada dokumen cocok</p>
                                    <p class="mt-1 text-xs">Coba reset filter atau ubah kata kunci pencarian.</p>
                                </div>
                            </div>
                            <div v-for="stage in visibleStageKeys" :key="stage" class="mb-4 last:mb-0">
                                <div class="flex items-center gap-2 px-1 pb-2">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-on-surface">{{ STAGE_META[stage].label }}</h3>
                                    <span class="ml-auto text-[10px] font-semibold text-on-surface-variant">{{ documentsByStage[stage].length }}</span>
                                </div>
                                <ul class="space-y-1">
                                    <li v-for="doc in documentsByStage[stage]" :key="doc.key">
                                        <button
                                            type="button"
                                            class="group flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition hover:bg-surface-container-low"
                                            :class="activeDocument?.key === doc.key && 'bg-primary-container/30 text-primary'"
                                            @click="openDocumentModal(doc)"
                                        >
                                            <AppIcon :name="doc.icon" class="text-base shrink-0" :class="activeDocument?.key === doc.key ? 'text-primary' : 'text-on-surface-variant group-hover:text-primary'" />
                                            <span class="min-w-0 flex-1 truncate text-xs font-semibold">{{ doc.label }}</span>
                                            <AppIcon name="chevron_right" class="text-base shrink-0 text-on-surface-variant" />
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="flex min-h-0 flex-col bg-surface-container-lowest">
                        <div v-if="activeDocument" class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant bg-surface px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-on-surface">{{ activeDocument.label }}</p>
                                <p class="mt-0.5 text-[11px] text-on-surface-variant">PDF · Tahap {{ STAGE_META[activeDocument.stage].label }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <a :href="activeDocument.url" target="_blank" rel="noopener" class="inline-flex size-9 items-center justify-center rounded-lg border border-outline-variant bg-surface text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary" title="Buka di tab baru" aria-label="Buka di tab baru">
                                    <AppIcon name="open_in_new" />
                                </a>
                                <a :href="activeDocument.url" :download="`${activeDocument.key}.pdf`" class="inline-flex size-9 items-center justify-center rounded-lg border border-outline-variant bg-surface text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary" title="Unduh PDF" aria-label="Unduh PDF">
                                    <AppIcon name="download" />
                                </a>
                                <a :href="activeDocument.url" target="_blank" rel="noopener" onclick="setTimeout(() => this.contentWindow && this.contentWindow.print && this.contentWindow.print(), 1500);" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-outline-variant bg-surface px-3 text-xs font-bold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary" title="Cetak" aria-label="Cetak">
                                    <AppIcon name="print" class="text-base" />
                                    <span class="hidden sm:inline">Cetak</span>
                                </a>
                            </div>
                        </div>
                        <div class="relative flex-1 overflow-hidden">
                            <iframe
                                v-if="activeDocument"
                                :key="documentPreviewKey"
                                :src="activeDocument.url"
                                class="h-full w-full border-0 bg-white"
                                :title="activeDocument.label"
                            ></iframe>
                            <div v-else class="grid h-full place-items-center p-8 text-center text-on-surface-variant">
                                <div>
                                    <AppIcon name="picture_as_pdf" class="text-5xl text-outline" />
                                    <p class="mt-3 text-sm font-semibold">Pilih dokumen di daftar untuk preview</p>
                                    <p class="mt-1 text-xs">atau klik tombol unduh/cetak untuk tindakan cepat.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <AppButton variant="secondary" @click="closeDocumentModal">Tutup</AppButton>
                </template>
            </AppModal>
        </Teleport>
    </AuthenticatedLayout>
</template>
