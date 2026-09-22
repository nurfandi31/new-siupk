<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AppFilterPill from '../../../Components/AppFilterPill.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';
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
    today: { type: String, required: true },
});

const { can } = useCan();

const moneyFmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

function currency(value) { return moneyFmt.format(Number(value ?? 0)); }
function formatDate(value) { if (!value) return '—'; const d = new Date(value); return Number.isNaN(d.getTime()) ? '—' : dateFmt.format(d); }
function formatDateTime(value) { if (!value) return '—'; const d = new Date(value); return Number.isNaN(d.getTime()) ? '—' : dateTimeFmt.format(d); }
function percent(value) { return `${Number(value ?? 0).toFixed(2)}%`; }
function monthlyRate(value, termMonths) {
    const rate = Number(value ?? 0);
    const months = Number(termMonths ?? 0);
    if (!months) return '—';
    return `${(rate / months).toFixed(2)}% / bulan`;
}
function systemLabel(frequency, graceMonths) {
    const label = frequencyLabels[frequency] || frequency || '—';
    return Number(graceMonths ?? 0) > 0 ? `${label} · Grace ${graceMonths} bulan` : label;
}
function roundingLabel(step, productRounding) {
    const effective = step !== null && step !== undefined && step !== '' ? step : productRounding;
    if (!effective || effective === '0' || effective === 'decimal_2') return 'Tanpa Pembulatan';
    if (effective === '100') return 'Rp 100';
    if (effective === '500') return 'Rp 500';
    if (effective === '1000') return 'Rp 1.000';
    if (effective === '5000') return 'Rp 5.000';
    if (effective === '10000') return 'Rp 10.000';
    if (effective === '50000') return 'Rp 50.000';
    return String(effective);
}

const roundingOptions = [
    { value: '', label: 'Default Produk' },
    { value: '0', label: 'Tanpa Pembulatan (2 Desimal)' },
    { value: '100', label: 'Rp 100' },
    { value: '500', label: 'Rp 500 (<=250: 0, >250: 500)' },
    { value: '1000', label: 'Rp 1.000' },
    { value: '5000', label: 'Rp 5.000' },
    { value: '10000', label: 'Rp 10.000' },
    { value: '50000', label: 'Rp 50.000' },
];

const installmentMethodOptions = [
    { value: 'flat', label: 'Flat' },
    { value: 'annuity', label: 'Anuitas' },
    { value: 'effective', label: 'Efektif' },
];

const frequencyOptions = [
    { value: 'weekly', label: 'Frekuensi — Mingguan' },
    { value: 'biweekly', label: 'Frekuensi — Dua Mingguan' },
    { value: 'monthly', label: 'Frekuensi — Bulanan' },
    { value: 'bimonthly', label: 'Frekuensi — Tiap 2 Bulan' },
    { value: 'quarterly', label: 'Frekuensi — Tiap 3 Bulan' },
    { value: 'every_4_months', label: 'Frekuensi — Tiap 4 Bulan' },
    { value: 'every_5_months', label: 'Frekuensi — Tiap 5 Bulan' },
    { value: 'every_6_months', label: 'Frekuensi — Tiap 6 Bulan' },
    { value: 'every_7_months', label: 'Frekuensi — Tiap 7 Bulan' },
    { value: 'every_8_months', label: 'Frekuensi — Tiap 8 Bulan' },
    { value: 'every_9_months', label: 'Frekuensi — Tiap 9 Bulan' },
    { value: 'every_10_months', label: 'Frekuensi — Tiap 10 Bulan' },
    { value: 'every_11_months', label: 'Frekuensi — Tiap 11 Bulan' },
    { value: 'every_12_months', label: 'Frekuensi — Tiap 12 Bulan' },
    { value: 'every_24_months', label: 'Frekuensi — Tiap 24 Bulan' },
    { value: 'every_36_months', label: 'Frekuensi — Tiap 36 Bulan' },
    { value: 'at_maturity', label: 'Lainnya — Sekaligus di Akhir' },
];

const graceOptions = [
    { value: 0, label: 'Tanpa Penundaan' },
    { value: 1, label: 'M1 — Angsuran ditunda 1 bulan' },
    { value: 2, label: 'M2 — Pokok ditunda 2 bulan' },
    { value: 3, label: 'M3 — Pokok ditunda 3 bulan' },
    { value: 6, label: 'M6 — Pokok ditunda 6 bulan' },
    { value: 12, label: 'M12 — Pokok ditunda 12 bulan' },
    { value: 24, label: 'M24 — Pokok ditunda 24 bulan' },
];

const frequencyLabels = Object.fromEntries(frequencyOptions.map((option) => [option.value, option.label.replace('Frekuensi — ', '')]));

// ===== Status & permission =====
const status = computed(() => props.loan.status);
const isPendingCompletion = computed(() =>
    Boolean(props.loan.is_pending_completion) || (
        ['active', 'disbursed'].includes(status.value) &&
        (Number(props.loan.principal_remaining ?? 0) <= 0 || (props.loan.total_installments > 0 && props.loan.paid_installments >= props.loan.total_installments))
    )
);
const statusMeta = computed(() => {
    if (isPendingCompletion.value) return { tone: 'warning', label: 'Lunas (Belum Validasi)', description: 'Seluruh angsuran pokok telah dilunasi (100%). Menunggu validasi pelunasan oleh petugas.' };
    switch (status.value) {
        case 'draft': return { tone: 'warning', label: 'Proposal', description: 'Belum diverifikasi. Lengkapi catatan verifikasi lalu lanjut ke tahap berikutnya.' };
        case 'verified': return { tone: 'primary', label: 'Tahap Verifikasi', description: 'Sudah diverifikasi. Tetapkan alokasi per anggota dan jadwal pencairan.' };
        case 'waiting':
        case 'approved': return { tone: 'info-soft', label: 'Menunggu Pencairan', description: 'Alokasi sudah ditetapkan. Catat pencairan saat dana siap dicairkan.' };
        case 'active':
        case 'disbursed': return { tone: 'success-soft', label: 'Pinjaman Aktif', description: 'Pinjaman berjalan. Pantau jadwal angsuran dan pembayaran.' };
        case 'completed': return { tone: 'success', label: 'Lunas', description: 'Pinjaman telah resmi divalidasi dan dilunasi.' };
        case 'rescheduled': return { tone: 'warning', label: 'Reschedule', description: 'Pinjaman ditutup lewat penjadwalan ulang. Sisa pokok dialihkan ke pinjaman baru.' };
        case 'written_off': return { tone: 'error', label: 'Dihapus', description: 'Piutang dihapusbukukan. Sisa pokok dicatat sebagai penghapusan.' };
        default: return { tone: 'neutral', label: status.value, description: '' };
    }
});

const pageTone = computed(() => {
    switch (statusMeta.value.tone) {
        case 'primary': return 'primary';
        case 'success':
        case 'success-soft': return 'success';
        case 'warning': return 'warning';
        case 'error': return 'error';
        default: return 'neutral';
    }
});

// Stage tracker pipeline (untuk kelompok).
const pipeline = computed(() => [
    { key: 'draft', label: 'Proposal', description: 'Pengajuan baru' },
    { key: 'verified', label: 'Verifikasi', description: 'Hasil verifikasi lapangan' },
    { key: 'waiting', label: 'Alokasi', description: 'Penetapan plafon & jadwal' },
    { key: 'disbursed', label: 'Pencairan', description: 'Dana dicairkan ke kelompok' },
    { key: 'completed', label: 'Lunas', description: 'Validasi pelunasan' },
]);
const stageKey = computed(() => {
    if (status.value === 'completed') return 'completed';
    if (['active', 'disbursed'].includes(status.value)) return 'disbursed';
    if (['waiting', 'approved'].includes(status.value)) return 'waiting';
    if (status.value === 'verified') return 'verified';
    if (status.value === 'draft') return 'draft';
    return 'draft';
});

// ===== Permission computed =====
const canEdit = computed(() => can('loans.manage') && ['draft', 'verified'].includes(props.loan.status));
const canDeleteProposal = computed(() => (can('loans.manage') || can('loans.propose')) && props.loan.status === 'draft');
const canRemoveBeneficiary = computed(() => can('loans.manage') && ['draft', 'verified'].includes(props.loan.status));
const canVerifyBeneficiary = computed(() => can('loans.verify') && props.loan.status === 'draft');
const canAllocatePerBeneficiary = computed(() => can('loans.approve') && props.loan.status === 'verified');
const canShowAllocatedAmount = computed(() => ['verified', 'waiting', 'approved', 'active', 'disbursed', 'completed', 'written_off', 'rescheduled'].includes(props.loan.status));
const canShowVerifiedAmount = computed(() => ['draft', 'verified', 'waiting', 'approved', 'active', 'disbursed', 'completed', 'written_off', 'rescheduled'].includes(props.loan.status));
const canRevert = computed(() => can('loans.manage') && ['verified', 'waiting', 'approved'].includes(props.loan.status));
const isActiveLoan = computed(() => ['active', 'disbursed'].includes(props.loan.status));
const canReschedule = computed(() => (can('loans.reschedule_director') || can('loans.manage')) && isActiveLoan.value && Number(props.loan.principal_remaining) > 0);
const canWriteOff = computed(() => can('loans.write_off') && isActiveLoan.value && Number(props.loan.principal_remaining) > 0);
const canWriteOffBeneficiary = computed(() => can('loans.write_off') && isActiveLoan.value && Number(props.loan.principal_remaining) > 0);
const canCancelReschedule = computed(() =>
    can('loans.manage')
    && props.loan.rescheduled_from_loan_row_id !== null
    && props.loan.rescheduled_from_loan_row_id !== undefined
    && isActiveLoan.value
    && Number(props.loan.principal_paid) === 0
);
const canDisburseAction = computed(() => can('loans.disburse'));
const canPrintDocument = computed(() => can('loans.view'));
const canShowVerifyForm = computed(() => can('loans.verify') && props.loan.status === 'draft');
const canShowApproveForm = computed(() => can('loans.approve') && props.loan.status === 'verified');
const canShowDisburseForm = computed(() => can('loans.disburse') && ['waiting', 'approved'].includes(props.loan.status));
const canCompleteAction = computed(() => (can('loans.complete_director') || can('loans.manage')) && (isPendingCompletion.value || (isActiveLoan.value && Number(props.loan.principal_remaining ?? 0) <= 0)));

// ===== Computed data =====
const auditStageHistories = computed(() => {
    const histories = props.loan.status_histories || [];
    const preferred = ['draft', 'verified', 'waiting', 'active', 'disbursed'];
    return preferred.map((status) => histories.filter((history) => history.to_status === status).at(-1) || null).filter(Boolean);
});
const auditOtherHistories = computed(() => {
    const auditStatuses = ['draft', 'verified', 'waiting', 'active', 'disbursed'];
    return [...(props.loan.status_histories || [])].filter((history) => !auditStatuses.includes(history.to_status)).reverse();
});
const auditHistoryModalOpen = ref(false);

const totalAllocation = computed(() => props.loan.beneficiaries.reduce((sum, b) => sum + Number(b.allocated_amount ?? 0), 0));
const totalProposal = computed(() => props.loan.beneficiaries.reduce((sum, b) => sum + Number(b.proposed_amount ?? 0), 0));
const verifiedAmountTotal = computed(() => props.loan.beneficiaries.reduce((sum, b) => sum + Number(b.verified_amount ?? b.allocated_amount ?? 0), 0));
const installmentNumber = (n) => (n ? `Angsuran #${n}` : '—');

const beneficiaryColumnCount = computed(() => 1 + 1 + (canShowVerifiedAmount.value ? 1 : 0) + (canShowAllocatedAmount.value ? 1 : 0) + (canRemoveBeneficiary.value ? 1 : 0) + (canWriteOffBeneficiary.value ? 1 : 0));
const verifiedTotalColspan = computed(() => beneficiaryColumnCount.value - 1);

const installmentRows = computed(() => {
    const byNumber = new Map();
    for (const row of props.loan.installments) {
        const key = row.installment_number;
        const existing = byNumber.get(key) || { installment_number: key, due_date: row.due_date, principal_due: 0, principal_paid: 0, interest_due: 0, interest_paid: 0, status: row.status, paid_at: row.paid_at };
        existing.principal_due = Math.max(existing.principal_due, Number(row.principal_due));
        existing.interest_due += Number(row.interest_due);
        existing.principal_paid += Number(row.principal_paid);
        existing.interest_paid += Number(row.interest_paid);
        if (row.component === 'principal') existing.status = row.status;
        byNumber.set(key, existing);
    }
    const sorted = Array.from(byNumber.values()).sort((a, b) => a.installment_number - b.installment_number);
    let targetPokok = 0;
    let targetJasa = 0;
    return sorted.map((r) => {
        targetPokok += r.principal_due;
        targetJasa += r.interest_due;
        return { ...r, target_pokok: targetPokok, target_jasa: targetJasa };
    });
});

const paymentRows = computed(() => {
    const payments = props.loan.payments || [];
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

// ===== Stat cards (KPI strip) =====
const statCards = computed(() => {
    const loan = props.loan;
    const cards = [
        {
            key: 'principal', label: 'Plafon Pinjaman', icon: 'payments',
            iconBg: 'bg-primary-container/20 text-primary',
            value: currency(loan.proposed_amount ?? loan.principal_amount),
            sublabel: `${loan.term_months} bln · ${percent(loan.service_rate_total)}`,
        },
        {
            key: 'remaining', label: 'Sisa Pokok', icon: 'account_balance_wallet',
            iconBg: loan.principal_remaining > 0 ? 'bg-tertiary-fixed/30 text-tertiary' : 'bg-secondary-container/30 text-secondary',
            value: currency(loan.principal_remaining),
            sublabel: loan.principal_remaining > 0 ? `Dari ${currency(loan.principal_amount)}` : 'Lunas',
        },
        {
            key: 'installments', label: 'Angsuran Lunas', icon: 'task_alt',
            iconBg: 'bg-secondary-container/30 text-secondary',
            value: `${loan.paid_installments} / ${loan.total_installments}`,
            progress: loan.progress_percent,
            progressLabel: 'Progress pelunasan',
            progressClass: 'bg-secondary',
            sublabel: loan.next_due_date ? `Berikutnya ${formatDate(loan.next_due_date)}` : 'Tidak ada jadwal',
        },
        {
            key: 'interest', label: 'Jasa Terbayar', icon: 'percent',
            iconBg: 'bg-primary-container/20 text-primary',
            value: currency(loan.total_interest_paid),
            sublabel: `Total jasa ${currency(loan.total_interest_due)}`,
        },
        {
            key: 'frequency', label: 'Sistem Angsuran', icon: 'event_repeat',
            iconBg: 'bg-surface-container-high text-on-surface-variant',
            value: frequencyLabels[loan.principal_frequency] || loan.principal_frequency || '—',
            sublabel: `Metode ${loan.installment_method || 'flat'}`,
        },
    ];
    return cards;
});

// ===== Loan identity fields (AppDetailGrid) =====
const loanIdentityFields = computed(() => [
    { key: 'loan_number', label: 'No. Pinjaman', value: props.loan.loan_number || `#${props.loan.row_id}` },
    { key: 'product', label: 'Produk', value: props.loan.product ? `${props.loan.product.name} · ${props.loan.product.code}` : '—' },
    { key: 'group', label: 'Kelompok', value: props.loan.group?.name || '—', hint: props.loan.group?.address },
    { key: 'village', label: 'Desa', value: props.loan.group?.village?.name || '—' },
    { key: 'proposed_at', label: 'Tanggal Pengajuan', value: formatDate(props.loan.proposed_at) },
    { key: 'verified_at', label: 'Tanggal Verifikasi', value: formatDate(props.loan.verified_at) },
    { key: 'approved_at', label: 'Tanggal Penetapan', value: formatDate(props.loan.approved_at) },
    { key: 'funded_at', label: 'Rencana Pencairan', value: formatDate(props.loan.funded_at) },
    { key: 'disbursed_at', label: 'Tanggal Cair', value: formatDate(props.loan.disbursed_at) },
    { key: 'completed_at', label: 'Tanggal Selesai', value: formatDate(props.loan.completed_at) },
    { key: 'rate', label: 'Tingkat Jasa Total', value: percent(props.loan.service_rate_total), hint: monthlyRate(props.loan.service_rate_total, props.loan.term_months) },
    { key: 'rounding', label: 'Pembulatan', value: roundingLabel(props.loan.rounding_step, props.loan.product?.rounding_method) },
]);

// ===== Forms =====
const verifyForm = useForm({
    verified_at: props.today,
    verification_amount: props.loan.proposed_amount ?? props.loan.principal_amount,
    term_months: Number(props.loan.term_months ?? 0),
    service_rate_total: Number(props.loan.service_rate_total ?? props.loan.service_rate ?? 0),
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: Number(props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(props.loan.interest_grace_months ?? 0),
    verification_notes: props.loan.verification_notes ?? '',
    verified_amounts: Object.fromEntries((props.loan.beneficiaries ?? []).map((b) => [String(b.member_row_id), Number(b.verified_amount ?? b.proposed_amount ?? b.allocated_amount ?? 0)])),
});
const verifiedTotal = computed(() => Object.values(verifyForm.verified_amounts ?? {}).reduce((sum, value) => sum + Number(value || 0), 0));
const verifyTotalTouched = ref(false);
watch(verifiedTotal, (value) => {
    if (!verifyTotalTouched.value) verifyForm.verification_amount = value;
}, { immediate: false });

const lastVerifiedHistory = [...(props.loan.status_histories ?? [])].reverse().find((history) => history.to_status === 'verified');
const approveForm = useForm({
    approved_at: props.today,
    planned_disbursed_at: props.loan.funded_at ?? props.today,
    allocated_principal: Number(props.loan.proposed_amount ?? props.loan.principal_amount ?? 0),
    term_months: Number(lastVerifiedHistory?.term_months ?? props.loan.term_months ?? 0),
    service_rate_total: Number(lastVerifiedHistory?.service_rate_total ?? props.loan.service_rate_total ?? props.loan.service_rate ?? 0),
    principal_frequency: lastVerifiedHistory?.principal_frequency ?? props.loan.principal_frequency ?? 'monthly',
    interest_frequency: lastVerifiedHistory?.interest_frequency ?? props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: Number(lastVerifiedHistory?.principal_grace_months ?? props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(lastVerifiedHistory?.interest_grace_months ?? props.loan.interest_grace_months ?? 0),
    allocation_notes: '',
    beneficiaries: props.loan.beneficiaries.map((b) => ({ member_row_id: b.member_row_id, name: b.name, allocated_amount: Number(b.allocated_amount ?? b.verified_amount ?? b.proposed_amount ?? 0) })),
});
const approveTotal = computed(() => (approveForm.beneficiaries ?? []).reduce((sum, row) => sum + Number(row.allocated_amount || 0), 0));
const approveTotalTouched = ref(false);
watch(approveTotal, (value) => {
    if (!approveTotalTouched.value) approveForm.allocated_principal = value;
}, { immediate: false });

const disburseForm = useForm({
    disbursed_at: props.today,
    disbursement_account_row_id: props.loan.disbursement_account_row_id ?? '',
    disbursement_notes: props.loan.disbursement_notes ?? '',
});

const editForm = useForm({
    proposed_at: props.loan.proposed_at ?? props.today,
    principal_amount: Number(props.loan.proposed_amount ?? props.loan.principal_amount ?? 0),
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    term_months: props.loan.term_months ?? '',
    installment_method: props.loan.installment_method ?? 'flat',
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: props.loan.principal_grace_months ?? 0,
    interest_grace_months: props.loan.interest_grace_months ?? 0,
    rounding_step: props.loan.rounding_step !== null && props.loan.rounding_step !== undefined ? String(props.loan.rounding_step) : '',
    beneficiary_amounts: Object.fromEntries((props.loan.beneficiaries ?? []).map((b) => [String(b.member_row_id), Number(b.allocated_amount ?? 0)])),
});
const editTotal = computed(() => Object.values(editForm.beneficiary_amounts).reduce((sum, value) => sum + Number(value || 0), 0));

const editModalOpen = ref(false);
const editTotalTouched = ref(false);
const removeError = ref('');

const committeeEditable = computed(() => can('loans.manage') && props.loan.committee_editable === true);
const committeeOptions = computed(() => props.loan.committee_member_options ?? []);
const committeeForm = useForm({ chair_id: '', secretary_id: '', treasurer_id: '' });
const committeeConfirmOpen = ref(false);
const committeeLabels = { chair: 'Ketua', secretary: 'Sekretaris', treasurer: 'Bendahara' };
const committeeReady = computed(() =>
    Number(committeeForm.chair_id) > 0
    && Number(committeeForm.secretary_id) > 0
    && Number(committeeForm.treasurer_id) > 0
    && Number(committeeForm.chair_id) !== Number(committeeForm.secretary_id)
    && Number(committeeForm.chair_id) !== Number(committeeForm.treasurer_id)
    && Number(committeeForm.secretary_id) !== Number(committeeForm.treasurer_id)
);
function memberLabel(id) { return committeeOptions.value.find((o) => Number(o.value) === Number(id))?.label || '—'; }
function openCommitteeConfirm() { if (committeeReady.value) committeeConfirmOpen.value = true; }
function submitCommittee() {
    committeeForm.patch(`/lending/loans/${props.loan.row_id}/committee`, { preserveScroll: true, onSuccess: () => { committeeConfirmOpen.value = false; } });
}

// ===== Revert modal =====
const revertModalOpen = ref(false);
const revertProcessing = ref(false);
const revertError = ref('');
function openRevertModal() { revertError.value = ''; revertModalOpen.value = true; }
function confirmRevert() {
    revertProcessing.value = true;
    revertError.value = '';
    router.patch(`/lending/loans/${props.loan.row_id}/revert`, {}, {
        preserveScroll: true,
        onFinish: () => { revertProcessing.value = false; },
        onError: (errors) => { revertError.value = errors?.error || 'Gagal mengembalikan pinjaman.'; },
    });
}

// ===== Reschedule modal =====
const rescheduleModalOpen = ref(false);
const rescheduleForm = useForm({
    rescheduled_at: props.today,
    term_months: props.loan.term_months ?? 12,
    service_rate_total: Number(props.loan.service_rate_total ?? 0),
    installment_method: props.loan.installment_method ?? 'flat',
    principal_frequency: props.loan.principal_frequency ?? 'monthly',
    interest_frequency: props.loan.interest_frequency ?? 'monthly',
    principal_grace_months: props.loan.principal_grace_months ?? 0,
    interest_grace_months: props.loan.interest_grace_months ?? 0,
    rounding_step: props.loan.rounding_step !== null && props.loan.rounding_step !== undefined ? String(props.loan.rounding_step) : '',
});
function openRescheduleModal() {
    rescheduleForm.rescheduled_at = props.today;
    rescheduleForm.term_months = props.loan.term_months ?? 12;
    rescheduleForm.service_rate_total = Number(props.loan.service_rate_total ?? 0);
    rescheduleForm.installment_method = props.loan.installment_method ?? 'flat';
    rescheduleForm.principal_frequency = props.loan.principal_frequency ?? 'monthly';
    rescheduleForm.interest_frequency = props.loan.interest_frequency ?? 'monthly';
    rescheduleForm.principal_grace_months = props.loan.principal_grace_months ?? 0;
    rescheduleForm.interest_grace_months = props.loan.interest_grace_months ?? 0;
    rescheduleForm.rounding_step = props.loan.rounding_step !== null && props.loan.rounding_step !== undefined ? String(props.loan.rounding_step) : '';
    rescheduleForm.clearErrors();
    rescheduleModalOpen.value = true;
}
function submitReschedule() {
    rescheduleForm.post(`/lending/loans/${props.loan.row_id}/reschedule`, { preserveScroll: true, onSuccess: () => { rescheduleModalOpen.value = false; } });
}

// ===== WriteOff modal =====
const writeOffModalOpen = ref(false);
const writeOffForm = useForm({ written_off_at: props.today, reason: '' });
function openWriteOffModal() {
    writeOffForm.written_off_at = props.today;
    writeOffForm.reason = '';
    writeOffForm.clearErrors();
    writeOffModalOpen.value = true;
}
function submitWriteOff() {
    writeOffForm.post(`/lending/loans/${props.loan.row_id}/write-off`, { preserveScroll: true, onSuccess: () => { writeOffModalOpen.value = false; } });
}

// ===== CancelReschedule modal =====
const cancelRescheduleModalOpen = ref(false);
const cancelRescheduleForm = useForm({ reason: '' });
function openCancelRescheduleModal() {
    cancelRescheduleForm.reason = '';
    cancelRescheduleForm.clearErrors();
    cancelRescheduleModalOpen.value = true;
}
function submitCancelReschedule() {
    cancelRescheduleForm.post(`/lending/loans/${props.loan.row_id}/cancel-reschedule`, { preserveScroll: true, onSuccess: () => { cancelRescheduleModalOpen.value = false; } });
}

// ===== Beneficiary write-off modal =====
const beneficiaryWriteOffModalOpen = ref(false);
const beneficiaryWriteOffTarget = ref(null);
const beneficiaryWriteOffForm = useForm({ written_off_at: props.today, reason: '', installment_number: 1 });
function openBeneficiaryWriteOffModal(b) {
    beneficiaryWriteOffTarget.value = b;
    const nextUnpaid = (props.loan.installments || []).find((i) => i.component === 'principal' && Number(i.principal_due) > Number(i.principal_paid));
    beneficiaryWriteOffForm.installment_number = nextUnpaid ? Number(nextUnpaid.installment_number) : 1;
    beneficiaryWriteOffForm.written_off_at = props.today;
    beneficiaryWriteOffForm.reason = '';
    beneficiaryWriteOffForm.clearErrors();
    beneficiaryWriteOffModalOpen.value = true;
}
function submitBeneficiaryWriteOff() {
    if (!beneficiaryWriteOffTarget.value) return;
    const target = beneficiaryWriteOffTarget.value;
    beneficiaryWriteOffForm.post(`/lending/loans/${props.loan.row_id}/beneficiaries/${target.member_row_id}/write-off`, { preserveScroll: true, onSuccess: () => { beneficiaryWriteOffModalOpen.value = false; } });
}

// ===== Complete modal =====
const completeModalOpen = ref(false);
const completeForm = useForm({ completed_at: props.today, notes: '' });
function openCompleteModal() {
    completeForm.completed_at = props.today;
    completeForm.notes = '';
    completeForm.clearErrors();
    completeModalOpen.value = true;
}
function submitComplete() {
    completeForm.patch(`/lending/loans/${props.loan.row_id}/complete`, { preserveScroll: true, onSuccess: () => { completeModalOpen.value = false; } });
}

// ===== Edit modal =====
function openEditModal() {
    editForm.beneficiary_amounts = Object.fromEntries((props.loan.beneficiaries ?? []).map((b) => [String(b.member_row_id), Number(b.allocated_amount ?? 0)]));
    editForm.rounding_step = props.loan.rounding_step !== null && props.loan.rounding_step !== undefined ? String(props.loan.rounding_step) : '';
    editForm.clearErrors();
    editModalOpen.value = true;
}
function submitEdit() {
    editForm.put(`/lending/loans/${props.loan.row_id}`, { preserveScroll: true, onSuccess: () => { editModalOpen.value = false; } });
}
function submitVerify() { verifyForm.patch(`/lending/loans/${props.loan.row_id}/verify`, { preserveScroll: true }); }
function submitApprove() { approveForm.patch(`/lending/loans/${props.loan.row_id}/approve`, { preserveScroll: true }); }
function submitDisburse() { disburseForm.patch(`/lending/loans/${props.loan.row_id}/disburse`, { preserveScroll: true }); }

function getAllocatedAmount(memberRowId) {
    const row = approveForm.beneficiaries.find((entry) => entry.member_row_id === memberRowId);
    return row ? Number(row.allocated_amount || 0) : 0;
}
function setAllocatedAmount(memberRowId, value) {
    const row = approveForm.beneficiaries.find((entry) => entry.member_row_id === memberRowId);
    if (row) row.allocated_amount = Number(value) || 0;
}

const { confirm: confirmAction } = useConfirm();
async function confirmRemoveBeneficiary(beneficiary) {
    if (!await confirmAction({ title: 'Hapus Pemanfaat', message: `Hapus ${beneficiary.name} dari daftar pemanfaat?` })) return;
    removeError.value = '';
    router.delete(`/lending/loans/${props.loan.row_id}/beneficiaries/${beneficiary.member_row_id}`, {
        preserveScroll: true,
        onError: (errors) => { removeError.value = errors?.error || 'Gagal menghapus pemanfaat.'; },
    });
}
async function confirmDeleteLoan() {
    if (!await confirmAction({ title: 'Hapus Proposal Pinjaman', message: 'Apakah Anda yakin ingin menghapus proposal pinjaman ini? Seluruh data pengajuan, jadwal angsuran rancangan, dan data pemanfaat akan dihapus permanen.', confirmText: 'Ya, Hapus Proposal', variant: 'danger' })) return;
    router.delete(`/lending/loans/${props.loan.row_id}`);
}

// ===== Documents & tabs =====
const STAGE_PROPOSAL = ['draft', 'verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_VERIFICATION = ['verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_DISBURSEMENT = ['waiting', 'approved', 'active', 'disbursed', 'completed'];

const LOAN_DOCUMENTS = [
    { key: 'cover_proposal', label: 'Cover Proposal', stage: 'proposal', icon: 'auto_stories' },
    { key: 'pengajuan_kredit', label: 'Surat Pengajuan Kredit', stage: 'proposal', icon: 'mail' },
    { key: 'profil_kelompok', label: 'Profil Kelompok', stage: 'proposal', icon: 'groups' },
    { key: 'susunan_pengurus', label: 'Susunan Pengurus Kelompok', stage: 'proposal', icon: 'badge' },
    { key: 'daftar_pemanfaat', label: 'Daftar Pemanfaat & Alokasi', stage: 'proposal', icon: 'list_alt' },
    { key: 'pernyataan_tanggung_renteng', label: 'Pernyataan Tanggung Renteng', stage: 'proposal', icon: 'handshake' },
    { key: 'check', label: 'Checklist Proposal', stage: 'proposal', icon: 'checklist' },
    { key: 'anggota', label: 'Daftar Anggota Kelompok', stage: 'proposal', icon: 'people' },
    { key: 'ktp', label: 'Cetak KTP Pemanfaat', stage: 'proposal', icon: 'badge' },
    { key: 'catatan_bimbingan', label: 'Catatan Bimbingan Kelompok', stage: 'proposal', icon: 'support_agent' },
    { key: 'rekomendasi_kredit', label: 'Surat Rekomendasi Kredit', stage: 'verification', icon: 'verified' },
    { key: 'ba_musyawarah', label: 'Berita Acara Musyawarah', stage: 'verification', icon: 'groups' },
    { key: 'surat_verifikasi', label: 'Surat Undangan Verifikasi', stage: 'verification', icon: 'mark_email_read' },
    { key: 'surat_kelayakan', label: 'Surat Kelayakan Piutang', stage: 'verification', icon: 'verified_user' },
    { key: 'form_verifikasi', label: 'Form Verifikasi Kelompok', stage: 'verification', icon: 'assignment' },
    { key: 'form_verifikasi_anggota', label: 'Form Verifikasi Anggota', stage: 'verification', icon: 'assignment_ind' },
    { key: 'daftar_hadir_verifikasi', label: 'Daftar Hadir Verifikasi', stage: 'verification', icon: 'event_available' },
    { key: 'cover_pencairan', label: 'Cover Pencairan', stage: 'disbursement', icon: 'auto_stories' },
    { key: 'spk', label: 'Surat Perjanjian Kredit (SPK)', stage: 'disbursement', icon: 'gavel' },
    { key: 'berita_acara_pencairan', label: 'Berita Acara Pencairan', stage: 'disbursement', icon: 'fact_check' },
    { key: 'ba_pendanaan', label: 'BA Rapat Pendanaan', stage: 'disbursement', icon: 'fact_check' },
    { key: 'rencana_angsuran', label: 'Rencana Angsuran', stage: 'disbursement', icon: 'calendar_month' },
    { key: 'kartu_angsuran_anggota', label: 'Kartu Angsuran per Anggota', stage: 'disbursement', icon: 'credit_card' },
    { key: 'pemberitahuan_desa', label: 'Pemberitahuan ke Desa', stage: 'disbursement', icon: 'campaign' },
    { key: 'peserta_asuransi', label: 'Daftar Peserta Asuransi', stage: 'disbursement', icon: 'health_and_safety' },
    { key: 'tanda_terima', label: 'Tanda Terima Dana', stage: 'disbursement', icon: 'task_alt' },
    { key: 'kuitansi_pencairan', label: 'Kuitansi Pencairan', stage: 'disbursement', icon: 'receipt_long' },
    { key: 'kuitansi_anggota', label: 'Kuitansi per Anggota', stage: 'disbursement', icon: 'receipt' },
    { key: 'tagihan', label: 'Surat Tagihan', stage: 'disbursement', icon: 'request_quote' },
    { key: 'surat_ahli_waris', label: 'Surat Pernyataan Ahli Waris', stage: 'disbursement', icon: 'family_restroom' },
    { key: 'surat_kuasa', label: 'Surat Kuasa SPK', stage: 'disbursement', icon: 'assignment_late' },
    { key: 'tanggung_renteng_kematian', label: 'Surat Pernyataan TR Kematian', stage: 'disbursement', icon: 'volunteer_activism' },
    { key: 'iptw', label: 'Daftar Penerima IPTW', stage: 'disbursement', icon: 'savings' },
    { key: 'rekening_koran', label: 'Rekening Koran Pinjaman', stage: 'disbursement', icon: 'account_balance' },
    { key: 'pernyataan_peminjam', label: 'Surat Pengakuan Utang Peminjam', stage: 'disbursement', icon: 'history_edu' },
    { key: 'daftar_hadir_pencairan', label: 'Daftar Hadir Pencairan', stage: 'disbursement', icon: 'event_available' },
];

const activeTab = ref('overview');
const docStageFilter = ref('all');
const docSearch = ref('');

const detailTabs = computed(() => [
    { key: 'overview', label: 'Ringkasan', icon: 'dashboard' },
    { key: 'beneficiaries', label: 'Pemanfaat', icon: 'groups', badge: props.loan.beneficiaries?.length ?? 0 },
    ...(canShowVerifyForm.value || canShowApproveForm.value || canShowDisburseForm.value ? [{ key: 'actions', label: 'Aksi', icon: 'bolt' }] : []),
    ...(installmentRows.value.length > 0 ? [{ key: 'schedule', label: 'Jadwal Angsuran', icon: 'calendar_month' }] : []),
    ...(paymentRows.value.length > 0 ? [{ key: 'payments', label: 'Pembayaran', icon: 'receipt_long', badge: paymentRows.value.length }] : []),
    ...(canPrintDocument.value ? [{ key: 'documents', label: 'Dokumen Cetak', icon: 'description', badge: availableDocuments.value.length }] : []),
]);

const STAGE_META = {
    proposal: { label: 'Proposal', description: 'Dokumen yang disiapkan pada tahap pengajuan proposal.', tone: 'warning' },
    verification: { label: 'Verifikasi', description: 'Dokumen yang disiapkan pada tahap verifikasi lapangan.', tone: 'primary' },
    disbursement: { label: 'Pencairan', description: 'Dokumen yang disiapkan pada tahap pencairan & penyaluran.', tone: 'success' },
};
const STAGE_ICON = { proposal: 'inventory_2', verification: 'task_alt', disbursement: 'payments' };
const STAGE_ICON_BG = {
    proposal: 'bg-warning-container text-on-warning-container',
    verification: 'bg-primary-container text-primary',
    disbursement: 'bg-secondary-container text-secondary',
};

const availableDocuments = computed(() => {
    const statusValue = props.loan.status;
    const stageAllowed = (stage) => {
        if (stage === 'proposal') return STAGE_PROPOSAL.includes(statusValue);
        if (stage === 'verification') return STAGE_VERIFICATION.includes(statusValue);
        if (stage === 'disbursement') return STAGE_DISBURSEMENT.includes(statusValue);
        return false;
    };
    return LOAN_DOCUMENTS.filter((d) => stageAllowed(d.stage)).map((d) => ({ ...d, url: `/lending/loans/${props.loan.row_id}/documents/${d.key}` }));
});

const documentsByStage = computed(() => {
    const docs = availableDocuments.value;
    const query = docSearch.value.trim().toLowerCase();
    const filtered = query ? docs.filter((d) => d.label.toLowerCase().includes(query) || d.key.includes(query)) : docs;
    const buckets = { proposal: [], verification: [], disbursement: [] };
    for (const d of filtered) {
        if (docStageFilter.value !== 'all' && docStageFilter.value !== d.stage) continue;
        buckets[d.stage].push(d);
    }
    return buckets;
});
const visibleStageKeys = computed(() => ['proposal', 'verification', 'disbursement'].filter((stage) => documentsByStage.value[stage].length > 0));
const documentStageCounts = computed(() => {
    const counts = { proposal: 0, verification: 0, disbursement: 0 };
    for (const d of availableDocuments.value) counts[d.stage] += 1;
    return counts;
});
const documentStagePills = computed(() => [
    { value: 'all', label: 'Semua', count: availableDocuments.value.length },
    { value: 'proposal', label: 'Proposal', count: documentStageCounts.value.proposal },
    { value: 'verification', label: 'Verifikasi', count: documentStageCounts.value.verification },
    { value: 'disbursement', label: 'Pencairan', count: documentStageCounts.value.disbursement },
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
        case 'rescheduled':
        case 'written_off': return 'lunas';
        default: return 'proposal';
    }
});
const backUrl = computed(() => `/lending/loans?tab=${backTab.value}`);

// ===== Identitas peminjam (cards for borrowers) =====
const beneficiaryStats = computed(() => ({
    total: props.loan.beneficiaries.length,
    proposed: totalProposal.value,
    verified: verifiedAmountTotal.value,
    allocated: totalAllocation.value,
}));
</script>

<template>
    <Head title="Detail Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 pb-12">
            <AppPageHeader
                :back-href="backUrl"
                back-label="Kembali ke daftar pinjaman"
                :title="loan.loan_number || `Pinjaman #${loan.row_id}`"
                :subtitle="`${loan.product?.name || '—'} · ${loan.group?.name || 'Pinjaman perorangan'} · Diajukan ${formatDate(loan.proposed_at)}`"
                :breadcrumbs="[
                    { label: 'Pinjaman', href: '/lending/loans' },
                    { label: backTab.charAt(0).toUpperCase() + backTab.slice(1), href: backUrl },
                    { label: loan.loan_number || `#${loan.row_id}` },
                ]"
                :tone="pageTone"
            >
                <template #badges>
                    <AppBadge :tone="statusMeta.tone">{{ statusMeta.label }}</AppBadge>
                    <AppBadge tone="primary-soft">Kelompok</AppBadge>
                </template>

                <template #actions>
                    <a v-if="card_url" :href="card_url" target="_blank" rel="noopener">
                        <AppButton type="button" variant="secondary" icon="credit_card" size="compact">Kartu Angsuran</AppButton>
                    </a>
                    <a v-if="settlement_letter_url" :href="settlement_letter_url" target="_blank" rel="noopener">
                        <AppButton type="button" variant="secondary" icon="verified" size="compact">Surat Keterangan Lunas</AppButton>
                    </a>
                    <AppButton type="button" variant="ghost" icon="history" size="compact" @click="auditHistoryModalOpen = true">Riwayat</AppButton>
                    <AppButton v-if="canCompleteAction" variant="success" icon="task_alt" @click="openCompleteModal">Validasi Lunas</AppButton>
                    <AppButton v-if="canEdit" variant="secondary" icon="edit" @click="openEditModal">Edit Proposal</AppButton>
                    <AppButton v-if="canDeleteProposal" variant="danger" icon="delete_outline" @click="confirmDeleteLoan">Hapus Proposal</AppButton>
                    <AppButton v-if="canReschedule" variant="secondary" icon="event_repeat" @click="openRescheduleModal">Reschedule</AppButton>
                    <AppButton v-if="canCancelReschedule" variant="danger" icon="undo" @click="openCancelRescheduleModal">Batalkan Reschedule</AppButton>
                    <AppButton v-if="canWriteOff" variant="danger" icon="delete_sweep" @click="openWriteOffModal">Penghapusan Piutang</AppButton>
                </template>
            </AppPageHeader>

            <AppStatGrid :stats="statCards" />

            <AppCard>
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Tahapan Pinjaman</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ statusMeta.description }}</p>
                        </div>
                        <AppBadge :tone="statusMeta.tone" class="capitalize">{{ statusMeta.label }}</AppBadge>
                    </div>
                </template>
                <AppStageTracker :stages="pipeline" :current-key="stageKey" />
            </AppCard>

            <div class="border-b border-outline-variant">
                <AppTabs v-model="activeTab" :items="detailTabs" variant="underline" aria-label="Bagian detail pinjaman" />
            </div>

            <!-- ============== TAB RINGKASAN ============== -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <div class="grid gap-6 xl:grid-cols-3">
                    <AppCard class="xl:col-span-2">
                        <template #header>
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Data Peminjam &amp; Produk</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Informasi pinjaman, kelompok, dan desa.</p>
                            </div>
                        </template>
                        <AppDetailGrid :fields="loanIdentityFields" :columns="3" />
                    </AppCard>

                    <AppCard>
                        <template #header>
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Pengurus Kelompok</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">
                                    {{ committeeEditable ? 'Belum terisi — pilih dan simpan.' : 'Snapshot pengurus saat pinjaman.' }}
                                </p>
                            </div>
                        </template>
                        <div v-if="committeeEditable" class="space-y-3">
                            <div class="grid gap-3">
                                <SmartSelect v-model="committeeForm.chair_id" label="Ketua" placeholder="Pilih ketua" :options="committeeOptions" :error="committeeForm.errors.chair_id" required />
                                <SmartSelect v-model="committeeForm.secretary_id" label="Sekretaris" placeholder="Pilih sekretaris" :options="committeeOptions" :error="committeeForm.errors.secretary_id" required />
                                <SmartSelect v-model="committeeForm.treasurer_id" label="Bendahara" placeholder="Pilih bendahara" :options="committeeOptions" :error="committeeForm.errors.treasurer_id" required />
                            </div>
                            <div class="flex justify-end">
                                <AppButton type="button" icon="save" :disabled="!committeeReady || committeeForm.processing" @click="openCommitteeConfirm">Simpan Pengurus</AppButton>
                            </div>
                        </div>
                        <div v-else class="grid gap-3">
                            <div v-for="(entry, position) in loan.committee" :key="position" class="rounded-xl border border-outline-variant bg-surface-container-low/40 px-4 py-3">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">{{ committeeLabels[position] || position }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-on-surface">{{ entry?.name || '—' }}</p>
                                <p v-if="entry?.snapshot_at" class="mt-0.5 text-[11px] text-on-surface-variant">Sejak {{ formatDate(entry.snapshot_at) }}</p>
                            </div>
                        </div>
                    </AppCard>
                </div>

                <div v-if="loan.verification_notes && status === 'verified'" class="rounded-xl border border-outline-variant bg-surface-container-low/40 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Catatan Verifikasi</p>
                    <p class="mt-1 text-sm text-on-surface">{{ loan.verification_notes }}</p>
                </div>
            </div>

            <!-- ============== TAB PEMANFAAT ============== -->
            <AppCard v-if="activeTab === 'beneficiaries'">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Daftar Pemanfaat</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ beneficiaryStats.total }} anggota terdaftar · Total alokasi {{ currency(beneficiaryStats.allocated) }}</p>
                        </div>
                    </div>
                </template>
                <div class="overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">Nama / NIK</th>
                                <th class="py-3 px-4 text-right">Pengajuan (Rp)</th>
                                <th v-if="canShowVerifiedAmount" class="py-3 px-4 text-right">Verifikasi (Rp)</th>
                                <th v-if="canShowAllocatedAmount" class="py-3 px-4 text-right">Alokasi (Rp)</th>
                                <th v-if="canRemoveBeneficiary" class="py-3 px-4 text-right">Aksi</th>
                                <th v-if="canWriteOffBeneficiary" class="py-3 px-4 text-right">Penghapusan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="b in loan.beneficiaries" :key="b.row_id">
                                <td class="py-3 px-4">
                                    <p class="font-semibold text-on-surface">{{ b.name || '—' }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ b.nik || '' }} · #{{ b.member_id ?? b.member_row_id }}</p>
                                </td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ currency(b.proposed_amount ?? b.allocated_amount) }}</td>
                                <td v-if="canShowVerifiedAmount" class="py-3 px-4 text-right">
                                    <AppCurrencyInput v-if="canVerifyBeneficiary" v-model="verifyForm.verified_amounts[String(b.member_row_id)]" label="" hide-label :min="0" :error="verifyForm.errors[`verified_amounts.${b.member_row_id}`]" />
                                    <span v-else class="font-semibold text-on-surface">{{ currency(b.verified_amount ?? 0) }}</span>
                                </td>
                                <td v-if="canShowAllocatedAmount" class="py-3 px-4 text-right">
                                    <AppCurrencyInput v-if="canAllocatePerBeneficiary" :model-value="getAllocatedAmount(b.member_row_id)" @update:model-value="(value) => setAllocatedAmount(b.member_row_id, value)" label="" hide-label :min="0" :max="loan.principal_amount" :error="approveForm.errors[`beneficiaries.${b.member_row_id}.allocated_amount`]" placeholder="0" />
                                    <span v-else class="font-semibold text-on-surface">{{ currency(b.allocated_amount ?? 0) }}</span>
                                </td>
                                <td v-if="canRemoveBeneficiary" class="py-3 px-4 text-right">
                                    <AppIconButton size="sm" rounded="full" name="delete_outline" tone="danger" :tooltip="`Hapus ${b.name}`" @click="confirmRemoveBeneficiary(b)" />
                                </td>
                                <td v-if="canWriteOffBeneficiary" class="py-3 px-4 text-right">
                                    <div v-if="b.written_off_at" class="flex flex-col items-end gap-1">
                                        <AppBadge tone="error">Dihapus</AppBadge>
                                        <span class="text-xs text-on-surface-variant">{{ formatDate(b.written_off_at) }}</span>
                                        <span class="text-xs font-semibold text-on-surface">{{ currency(b.written_off_amount) }}</span>
                                    </div>
                                    <AppIconButton v-else size="sm" rounded="full" name="delete_sweep" tone="danger" :tooltip="`Hapus bukukan ${b.name}`" @click="openBeneficiaryWriteOffModal(b)" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="canShowVerifiedAmount">
                            <tr class="bg-surface-container-low">
                                <td class="py-3 px-4 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total</td>
                                <td class="py-3 px-4 text-right text-base font-bold text-on-surface">{{ currency(totalProposal) }}</td>
                                <td class="py-3 px-4 text-right text-base font-bold text-on-surface">
                                    <span v-if="status === 'draft'">{{ currency(verifiedTotal) }}</span>
                                    <span v-else>{{ currency(verifiedAmountTotal) }}</span>
                                </td>
                                <td v-if="canShowAllocatedAmount" class="py-3 px-4 text-right text-base font-bold text-on-surface">
                                    <span v-if="canAllocatePerBeneficiary">{{ currency(approveTotal) }}</span>
                                    <span v-else>{{ currency(totalAllocation) }}</span>
                                </td>
                                <td v-if="canRemoveBeneficiary"></td>
                                <td v-if="canWriteOffBeneficiary"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p v-if="removeError" class="mt-3 text-sm text-error">{{ removeError }}</p>
            </AppCard>

            <!-- ============== TAB AKSI (form verifikasi/approve/disburse) ============== -->
            <div v-if="activeTab === 'actions'" class="space-y-6">
                <AppCard v-if="canShowVerifyForm">
                    <template #header>
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Form Verifikasi</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Lengkapi tanggal dan catatan. Nominal verifikasi per pemanfaat diatur di tab Pemanfaat.</p>
                        </div>
                    </template>
                    <form class="space-y-5" @submit.prevent="submitVerify">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="verifyForm.verified_at" label="Tanggal Verifikasi" :max="today" :error="verifyForm.errors.verified_at" required />
                            <AppCurrencyInput v-model="verifyForm.verification_amount" @update:model-value="verifyTotalTouched = true" label="Nominal Verifikasi Total (opsional)" :min="0" :error="verifyForm.errors.verification_amount" hint="Kosongkan untuk konfirmasi plafon penuh." />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppInput v-model="verifyForm.term_months" label="Rekomendasi Jangka Waktu (bulan)" icon="schedule" type="number" min="1" max="120" :error="verifyForm.errors.term_months" />
                            <AppCurrencyInput v-model="verifyForm.service_rate_total" label="Rekomendasi Pros Jasa Total (%)" icon="percent" :min="0" :max="100" :error="verifyForm.errors.service_rate_total" />
                            <SmartSelect v-model="verifyForm.principal_frequency" label="Sistem Angs. Pokok" :options="frequencyOptions" :error="verifyForm.errors.principal_frequency" />
                            <SmartSelect v-model="verifyForm.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" :error="verifyForm.errors.principal_grace_months" />
                            <SmartSelect v-model="verifyForm.interest_frequency" label="Sistem Angs. Jasa" :options="frequencyOptions" :error="verifyForm.errors.interest_frequency" />
                            <SmartSelect v-model="verifyForm.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" :error="verifyForm.errors.interest_grace_months" />
                        </div>
                        <AppTextarea v-model="verifyForm.verification_notes" label="Catatan Verifikasi (opsional)" :error="verifyForm.errors.verification_notes" placeholder="Hasil pemeriksaan lapangan, kelengkapan dokumen, dll." />
                        <div class="flex justify-end">
                            <AppButton type="submit" :loading="verifyForm.processing">Simpan &amp; Lanjut Verifikasi</AppButton>
                        </div>
                    </form>
                </AppCard>

                <AppCard v-if="canShowApproveForm">
                    <template #header>
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Form Penetapan Alokasi</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Atur plafon kelompok dan nominal per anggota di tab Pemanfaat. Standar verifikasi: {{ currency(verifiedAmountTotal) }}.</p>
                        </div>
                    </template>
                    <form class="space-y-5" @submit.prevent="submitApprove">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppDatePicker v-model="approveForm.approved_at" label="Tanggal Penetapan" :max="today" :error="approveForm.errors.approved_at" required />
                            <AppDatePicker v-model="approveForm.planned_disbursed_at" label="Rencana Tanggal Cair" :min="approveForm.approved_at" :error="approveForm.errors.planned_disbursed_at" required />
                            <AppCurrencyInput v-model="approveForm.allocated_principal" @update:model-value="approveTotalTouched = true" label="Plafon Alokasi Kelompok" icon="payments" :min="0" :max="loan.proposed_amount ?? loan.principal_amount" required :error="approveForm.errors.allocated_principal" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppInput v-model="approveForm.term_months" label="Jangka Waktu Disetujui (bulan)" icon="schedule" type="number" min="1" max="120" required :error="approveForm.errors.term_months" />
                            <AppCurrencyInput v-model="approveForm.service_rate_total" label="Pros Jasa Total Disetujui (%)" icon="percent" :min="0" :max="100" required :error="approveForm.errors.service_rate_total" />
                            <SmartSelect v-model="approveForm.principal_frequency" label="Sistem Angs. Pokok Disetujui" :options="frequencyOptions" required :error="approveForm.errors.principal_frequency" />
                            <SmartSelect v-model="approveForm.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" required :error="approveForm.errors.principal_grace_months" />
                            <SmartSelect v-model="approveForm.interest_frequency" label="Sistem Angs. Jasa" :options="frequencyOptions" required :error="approveForm.errors.interest_frequency" />
                            <SmartSelect v-model="approveForm.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" required :error="approveForm.errors.interest_grace_months" />
                        </div>
                        <p v-if="approveTotal > approveForm.allocated_principal" class="text-sm text-error">Total alokasi per anggota ({{ currency(approveTotal) }}) melebihi plafon alokasi kelompok ({{ currency(approveForm.allocated_principal) }}).</p>
                        <AppTextarea v-model="approveForm.allocation_notes" label="Catatan Penetapan (opsional)" :error="approveForm.errors.allocation_notes" placeholder="Akan muncul di riwayat status. Kosongkan untuk catatan otomatis." />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-3">
                                <AppButton v-if="canRevert" type="button" variant="secondary" @click="openRevertModal">Kembalikan ke Draft</AppButton>
                                <AppButton type="submit" :loading="approveForm.processing">Simpan &amp; Alokasikan</AppButton>
                            </div>
                        </div>
                    </form>
                </AppCard>

                <AppCard v-if="canShowDisburseForm">
                    <template #header>
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Form Pencairan</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Catat tanggal dan akun sumber dana saat pinjaman dicairkan.</p>
                        </div>
                    </template>
                    <form class="space-y-5" @submit.prevent="submitDisburse">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="disburseForm.disbursed_at" label="Tanggal Cair" :max="today" :error="disburseForm.errors.disbursed_at" required />
                            <SmartSelect v-model="disburseForm.disbursement_account_row_id" :options="disbursementAccounts" label="Akun Sumber Dana" placeholder="Pilih akun kas/bank" :error="disburseForm.errors.disbursement_account_row_id" required />
                        </div>
                        <AppTextarea v-model="disburseForm.disbursement_notes" label="Catatan Pencairan (opsional)" :error="disburseForm.errors.disbursement_notes" placeholder="Referensi transfer, nomor slip, dll." />
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-3">
                                <AppButton v-if="canRevert" type="button" variant="secondary" @click="openRevertModal">Kembalikan ke Draft</AppButton>
                                <AppButton v-if="canDisburseAction" type="submit" :loading="disburseForm.processing">Catat Pencairan</AppButton>
                            </div>
                        </div>
                    </form>
                </AppCard>
            </div>

            <!-- ============== TAB JADWAL ANGSURAN ============== -->
            <AppCard v-if="activeTab === 'schedule' && installmentRows.length">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-on-surface">Jadwal Angsuran</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Sisa pokok {{ currency(loan.principal_remaining) }} · Jasa terbayar {{ currency(loan.total_interest_paid) }} / {{ currency(loan.total_interest_due) }}</p>
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
                                <th class="py-3 px-4 text-right">Target Pokok</th>
                                <th class="py-3 px-4 text-right">Target Jasa</th>
                                <th class="py-3 px-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="row in installmentRows" :key="row.installment_number">
                                <td class="py-3 px-4 text-on-surface-variant">{{ installmentNumber(row.installment_number) }}</td>
                                <td class="py-3 px-4 text-on-surface-variant">{{ formatDate(row.due_date) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ currency(row.principal_due) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ currency(row.interest_due) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ currency(row.target_pokok) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ currency(row.target_jasa) }}</td>
                                <td class="py-3 px-4 text-right">
                                    <AppBadge :tone="row.status === 'paid' ? 'success' : 'neutral'">{{ row.status === 'paid' ? 'Lunas' : 'Belum' }}</AppBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- ============== TAB PEMBAYARAN ============== -->
            <AppCard v-if="activeTab === 'payments' && paymentRows.length">
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
                                <td class="py-3 px-4 text-on-surface-variant">{{ formatDate(row.paid_at) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ currency(row.pokok) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface">{{ currency(row.jasa) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ currency(row.sum_pokok) }}</td>
                                <td class="py-3 px-4 text-right text-on-surface-variant">{{ currency(row.sum_jasa) }}</td>
                                <td class="py-3 px-4 text-right text-error">{{ currency(row.tunggakan_pokok) }}</td>
                                <td class="py-3 px-4 text-right text-error">{{ currency(row.tunggakan_jasa) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- ============== TAB DOKUMEN CETAK ============== -->
            <div v-if="activeTab === 'documents' && canPrintDocument" class="space-y-6">
                <AppCard>
                    <template #header>
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-on-surface">Dokumen Cetak</h2>
                                <p class="mt-0.5 text-xs text-on-surface-variant">
                                    Pilih dokumen untuk membuka PDF di tab baru. Dokumen mengikuti tahap pinjamanan saat ini
                                    (<strong>{{ statusMeta.label }}</strong>) — total tersedia
                                    <strong>{{ availableDocuments.length }}</strong> dokumen.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <AppInput v-model="docSearch" type="search" icon="search" label="Cari dokumen" :hide-label="true" placeholder="Cari dokumen..." class="w-full sm:w-56" />
                            </div>
                        </div>
                    </template>
                    <div class="border-b border-outline-variant pb-3">
                        <AppFilterPill v-model="docStageFilter" :items="documentStagePills" variant="solid" size="compact" aria-label="Filter tahap dokumen" />
                    </div>
                </AppCard>

                <AppCard v-for="stage in visibleStageKeys" :key="stage" :data-stage="stage">
                    <template #header>
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="STAGE_ICON_BG[stage]">
                                <AppIcon :name="STAGE_ICON[stage]" class="text-lg" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-on-surface">{{ STAGE_META[stage].label }}</h3>
                                <p class="text-xs text-on-surface-variant">{{ STAGE_META[stage].description }}</p>
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ documentsByStage[stage].length }}</span>
                        </div>
                    </template>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="doc in documentsByStage[stage]" :key="doc.key" class="group">
                            <a :href="doc.url" target="_blank" rel="noopener" class="flex items-center gap-3 py-2.5 px-1 -mx-1 rounded-md transition-colors hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                                <AppIcon :name="doc.icon" class="text-lg text-on-surface-variant group-hover:text-primary shrink-0" />
                                <span class="min-w-0 flex-1 truncate text-sm text-on-surface group-hover:text-primary">{{ doc.label }}</span>
                                <span class="hidden text-xs uppercase tracking-wider text-on-surface-variant sm:inline">PDF</span>
                                <AppIcon name="chevron_right" class="text-base text-on-surface-variant group-hover:text-primary shrink-0" />
                            </a>
                        </li>
                    </ul>
                </AppCard>

                <p v-if="visibleStageKeys.length === 0 && availableDocuments.length > 0" class="rounded-xl border border-outline-variant bg-surface-container-low p-6 text-center text-sm text-on-surface-variant">
                    Tidak ada dokumen yang cocok dengan filter. Coba reset filter di atas.
                </p>

                <AppEmptyState v-if="availableDocuments.length === 0" icon="description" title="Belum ada dokumen untuk tahap ini" :description="`Pinjamankan status '${statusMeta.label}' belum memiliki dokumen yang tersedia.`" />
                <AppEmptyState v-else-if="documentsByStage.proposal.length === 0 && documentsByStage.verification.length === 0 && documentsByStage.disbursement.length === 0" icon="search_off" title="Tidak ada dokumen cocok" :description="`Pencarian '${docSearch}' tidak menemukan dokumen pada tahap yang dipilih.`" />
            </div>

            <!-- ============== MODALS ============== -->
            <AppModal v-model="committeeConfirmOpen" title="Simpan pengurus?">
                <p class="text-sm text-on-surface">Setelah disimpan, pengurus pinjaman <strong>tidak dapat diganti</strong>.</p>
                <ul class="mt-3 space-y-1 text-sm text-on-surface">
                    <li><span class="font-semibold">Ketua:</span> {{ memberLabel(committeeForm.chair_id) }}</li>
                    <li><span class="font-semibold">Sekretaris:</span> {{ memberLabel(committeeForm.secretary_id) }}</li>
                    <li><span class="font-semibold">Bendahara:</span> {{ memberLabel(committeeForm.treasurer_id) }}</li>
                </ul>
                <template #footer>
                    <AppButton variant="secondary" :disabled="committeeForm.processing" @click="committeeConfirmOpen = false">Batal</AppButton>
                    <AppButton :loading="committeeForm.processing" @click="submitCommittee">Ya, simpan</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="auditHistoryModalOpen" title="Riwayat & Audit Parameter Pinjaman" size="lg">
                <div class="space-y-6">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-left text-[11px] sm:min-w-[56rem] sm:text-sm">
                            <thead class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                <tr>
                                    <th class="py-3 pr-4">Parameter</th>
                                    <th class="py-3 px-4">1. Proposal (P)</th>
                                    <th class="py-3 px-4">2. Verifikasi (V)</th>
                                    <th class="py-3 px-4">3. Penetapan Alokasi (W)</th>
                                    <th class="py-3 pl-4">4. Realisasi Pencairan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tanggal &amp; Status</th>
                                    <td v-for="history in auditStageHistories" :key="`status-${history.to_status}-${history.changed_at}`" class="py-3 px-4 first:pl-4 last:pl-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <AppBadge :tone="history.to_status === 'draft' ? 'warning' : history.to_status === 'verified' ? 'primary' : history.to_status === 'waiting' ? 'info-soft' : 'success'">
                                                {{ history.to_status === 'draft' ? 'P / Proposal' : history.to_status === 'verified' ? 'V / Verifikasi' : history.to_status === 'waiting' ? 'W / Tunggu' : 'Pencairan' }}
                                            </AppBadge>
                                            <span class="text-xs text-on-surface-variant">{{ formatDateTime(history.changed_at) }}</span>
                                        </div>
                                    </td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">Belum tersedia</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Plafon / Nominal</th>
                                    <td v-for="history in auditStageHistories" :key="`amount-${history.to_status}-${history.changed_at}`" class="py-3 px-4 font-semibold text-on-surface">{{ currency(history.principal_amount) }}</td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jangka Waktu</th>
                                    <td v-for="history in auditStageHistories" :key="`term-${history.to_status}-${history.changed_at}`" class="py-3 px-4">{{ history.term_months ?? '—' }} Bulan</td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pros Jasa / Bunga</th>
                                    <td v-for="history in auditStageHistories" :key="`rate-${history.to_status}-${history.changed_at}`" class="py-3 px-4">
                                        {{ percent(history.service_rate_total) }} total · {{ monthlyRate(history.service_rate_total, history.term_months) }}
                                    </td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sistem Pokok</th>
                                    <td v-for="history in auditStageHistories" :key="`principal-system-${history.to_status}-${history.changed_at}`" class="py-3 px-4">
                                        {{ systemLabel(history.principal_frequency, history.principal_grace_months) }}
                                    </td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Sistem Jasa</th>
                                    <td v-for="history in auditStageHistories" :key="`interest-system-${history.to_status}-${history.changed_at}`" class="py-3 px-4">
                                        {{ systemLabel(history.interest_frequency, history.interest_grace_months) }}
                                    </td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Petugas / Penanggung Jawab</th>
                                    <td v-for="history in auditStageHistories" :key="`user-${history.to_status}-${history.changed_at}`" class="py-3 px-4">{{ history.changed_by_user_name || (history.changed_by_user_id ? `#${history.changed_by_user_id}` : '—') }}</td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                                <tr>
                                    <th class="py-3 pr-4 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Catatan / Hasil Keputusan</th>
                                    <td v-for="history in auditStageHistories" :key="`notes-${history.to_status}-${history.changed_at}`" class="py-3 px-4 text-on-surface-variant">{{ history.notes || '—' }}</td>
                                    <td v-if="auditStageHistories.length < 4" class="py-3 pl-4 text-on-surface-variant" :colspan="4 - auditStageHistories.length">—</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="auditOtherHistories.length > 0">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Timeline Tambahan</h3>
                        <ol class="relative mt-4 ml-3 space-y-4 border-l-2 border-outline-variant pl-6">
                            <li v-for="history in auditOtherHistories" :key="`other-${history.to_status}-${history.changed_at}`" class="relative">
                                <span class="absolute -left-[37px] top-0 flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-on-primary">·</span>
                                <p class="text-sm font-semibold text-on-surface">{{ history.from_status || 'awal' }} → {{ history.to_status }}</p>
                                <p class="text-xs text-on-surface-variant">{{ formatDateTime(history.changed_at) }}</p>
                                <p v-if="history.notes" class="mt-1 text-sm text-on-surface">{{ history.notes }}</p>
                            </li>
                        </ol>
                    </div>
                </div>
                <template #footer>
                    <AppButton variant="secondary" @click="auditHistoryModalOpen = false">Tutup</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="revertModalOpen" title="Kembalikan ke Draft?" size="sm">
                <p class="text-sm text-on-surface-variant">Pinjaman akan dikembalikan ke status proposal. Data yang sudah diinput akan dipertahankan dan menjadi nilai default saat form dibuka kembali.</p>
                <p v-if="revertError" class="mt-3 text-sm text-error">{{ revertError }}</p>
                <template #footer>
                    <AppButton type="button" variant="secondary" @click="revertModalOpen = false" :disabled="revertProcessing">Batal</AppButton>
                    <AppButton type="button" variant="danger" @click="confirmRevert" :loading="revertProcessing">Kembalikan</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="rescheduleModalOpen" title="Reschedule Pinjaman" size="lg">
                <p class="mb-4 text-sm text-on-surface-variant">
                    Pinjaman ini akan ditutup status <strong>Reschedule</strong> dan diganti pinjaman baru dengan alokasi sisa pokok
                    <strong>{{ currency(loan.principal_remaining) }}</strong>.
                </p>
                <form class="space-y-5" @submit.prevent="submitReschedule">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="rescheduleForm.rescheduled_at" label="Tanggal Reschedule" :max="today" :error="rescheduleForm.errors.rescheduled_at" required />
                        <AppCurrencyInput :model-value="loan.principal_remaining" label="Sisa Pokok (alokasi baru)" icon="payments" :min="0" readonly />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput v-model="rescheduleForm.term_months" label="Jangka Waktu (bulan)" icon="schedule" type="number" inputmode="numeric" min="1" max="120" required :error="rescheduleForm.errors.term_months" />
                        <AppCurrencyInput v-model="rescheduleForm.service_rate_total" label="Prosentase Jasa Total (%)" icon="percent" :min="0" :max="100" required :error="rescheduleForm.errors.service_rate_total" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <SmartSelect v-model="rescheduleForm.installment_method" label="Metode Hitung Jasa" :options="installmentMethodOptions" required :error="rescheduleForm.errors.installment_method" />
                        <SmartSelect v-model="rescheduleForm.principal_frequency" label="Angsuran Pokok" :options="frequencyOptions" required :error="rescheduleForm.errors.principal_frequency" />
                        <SmartSelect v-model="rescheduleForm.interest_frequency" label="Angsuran Jasa" :options="frequencyOptions" required :error="rescheduleForm.errors.interest_frequency" />
                        <SmartSelect v-model="rescheduleForm.rounding_step" label="Pembulatan Angsuran" :options="roundingOptions" :error="rescheduleForm.errors.rounding_step" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <SmartSelect v-model="rescheduleForm.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" required :error="rescheduleForm.errors.principal_grace_months" />
                        <SmartSelect v-model="rescheduleForm.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" required :error="rescheduleForm.errors.interest_grace_months" />
                    </div>
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="rescheduleModalOpen = false" :disabled="rescheduleForm.processing">Batal</AppButton>
                    <AppButton :loading="rescheduleForm.processing" @click="submitReschedule">Reschedule Pinjaman</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="writeOffModalOpen" title="Penghapusan Pinjaman" size="md">
                <p class="mb-4 text-sm text-on-surface-variant">
                    Piutang sisa pokok <strong>{{ currency(loan.principal_remaining) }}</strong> akan dihapusbukukan.
                    Status pinjaman menjadi <strong>Dihapus</strong>. Tindakan ini tidak dapat dibatalkan.
                </p>
                <form class="space-y-5" @submit.prevent="submitWriteOff">
                    <AppDatePicker v-model="writeOffForm.written_off_at" label="Tanggal Penghapusan" :max="today" :error="writeOffForm.errors.written_off_at" required />
                    <AppCurrencyInput :model-value="loan.principal_remaining" label="Sisa Pokok Dihapus" icon="payments" readonly />
                    <AppTextarea v-model="writeOffForm.reason" label="Alasan Penghapusan" :error="writeOffForm.errors.reason" required placeholder="Dasar penghapusan, hasil musyawarah, dll." />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="writeOffModalOpen = false" :disabled="writeOffForm.processing">Batal</AppButton>
                    <AppButton variant="danger" :loading="writeOffForm.processing" @click="submitWriteOff">Hapus Bukukan Piutang</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="beneficiaryWriteOffModalOpen" title="Penghapusan Pemanfaat" size="md">
                <p v-if="beneficiaryWriteOffTarget" class="mb-4 text-sm text-on-surface-variant">
                    Pemanfaat <strong>{{ beneficiaryWriteOffTarget.name || '—' }}</strong> akan dihapusbukukan.
                    Sisa pokok alokasi <strong>{{ currency(beneficiaryWriteOffTarget.allocated_amount) }}</strong>
                    akan dicatat sebagai penghapusan piutang. Jadwal angsuran setelah posisi penghapusan akan disesuaikan
                    secara proporsional. Tindakan ini tidak dapat dibatalkan.
                </p>
                <form class="space-y-5" @submit.prevent="submitBeneficiaryWriteOff">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="beneficiaryWriteOffForm.written_off_at" label="Tanggal Penghapusan" :max="today" :error="beneficiaryWriteOffForm.errors.written_off_at" required />
                        <AppInput v-model="beneficiaryWriteOffForm.installment_number" label="Sisipkan Setelah Angsuran Ke-" icon="schedule" type="number" inputmode="numeric" min="1" required :error="beneficiaryWriteOffForm.errors.installment_number" />
                    </div>
                    <AppCurrencyInput :model-value="beneficiaryWriteOffTarget ? beneficiaryWriteOffTarget.allocated_amount : 0" label="Alokasi Pokok Pemanfaat" icon="payments" readonly />
                    <AppTextarea v-model="beneficiaryWriteOffForm.reason" label="Alasan Penghapusan" :error="beneficiaryWriteOffForm.errors.reason" required placeholder="Misal: Anggota meninggal dunia, hasil musyawarah kelompok, dll." />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="beneficiaryWriteOffModalOpen = false" :disabled="beneficiaryWriteOffForm.processing">Batal</AppButton>
                    <AppButton variant="danger" icon="delete_sweep" :loading="beneficiaryWriteOffForm.processing" @click="submitBeneficiaryWriteOff">Hapus Bukukan Pemanfaat</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="cancelRescheduleModalOpen" title="Batalkan Reschedule" size="md">
                <p class="mb-4 text-sm text-on-surface-variant">
                    Tindakan ini akan menghapus pinjaman baru hasil reschedule dan mengembalikan pinjaman asal ke status aktif sebelumnya. Hanya dapat dilakukan sebelum ada angsuran dibayar di pinjaman baru. Kedua jurnal reschedule akan ditandai dihapus (soft-delete) untuk audit trail.
                </p>
                <form class="space-y-5" @submit.prevent="submitCancelReschedule">
                    <AppTextarea v-model="cancelRescheduleForm.reason" label="Alasan Pembatalan" :error="cancelRescheduleForm.errors.reason" required placeholder="Misal: Salah input suku bunga, akan dilakukan reschedule ulang dengan parameter benar." />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="cancelRescheduleModalOpen = false" :disabled="cancelRescheduleForm.processing">Batal</AppButton>
                    <AppButton variant="danger" icon="undo" :loading="cancelRescheduleForm.processing" @click="submitCancelReschedule">Batalkan Reschedule</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="completeModalOpen" title="Validasi Pelunasan Pinjaman" size="md">
                <p class="mb-4 text-sm text-on-surface-variant">
                    Seluruh angsuran pokok pinjaman ini telah dilunasi (100%). Lakukan validasi pelunasan untuk secara resmi mengubah status pinjaman menjadi <strong>Lunas</strong>.
                </p>
                <form class="space-y-5" @submit.prevent="submitComplete">
                    <AppDatePicker v-model="completeForm.completed_at" label="Tanggal Pelunasan" :max="today" :error="completeForm.errors.completed_at" required />
                    <AppTextarea v-model="completeForm.notes" label="Catatan Validasi Pelunasan (opsional)" placeholder="Misal: Berkas pelunasan dan kartu angsuran telah diverifikasi lengkap." :error="completeForm.errors.notes" />
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="completeModalOpen = false" :disabled="completeForm.processing">Batal</AppButton>
                    <AppButton variant="primary" icon="verified" :loading="completeForm.processing" @click="submitComplete">Validasi &amp; Selesaikan Pinjaman</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="editModalOpen" title="Edit Proposal Pinjaman" size="lg">
                <form class="space-y-5" @submit.prevent="submitEdit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="editForm.proposed_at" label="Tanggal Pengajuan" :max="today" :error="editForm.errors.proposed_at" required />
                        <AppCurrencyInput v-model="editForm.principal_amount" label="Plafon Pinjaman" icon="payments" :min="0" required :error="editForm.errors.principal_amount" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppCurrencyInput v-model="editForm.service_rate_total" label="Prosentase Jasa Total (%)" icon="percent" :min="0" :max="100" required :error="editForm.errors.service_rate_total" />
                        <AppInput v-model="editForm.term_months" label="Jangka Waktu (bulan)" icon="schedule" type="number" inputmode="numeric" min="1" max="120" required :error="editForm.errors.term_months" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <SmartSelect v-model="editForm.installment_method" label="Metode Hitung Jasa" :options="installmentMethodOptions" required :error="editForm.errors.installment_method" />
                        <SmartSelect v-model="editForm.principal_frequency" label="Angsuran Pokok" :options="frequencyOptions" required :error="editForm.errors.principal_frequency" />
                        <SmartSelect v-model="editForm.interest_frequency" label="Angsuran Jasa" :options="frequencyOptions" required :error="editForm.errors.interest_frequency" />
                        <SmartSelect v-model="editForm.rounding_step" label="Pembulatan Angsuran" :options="roundingOptions" :error="editForm.errors.rounding_step" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <SmartSelect v-model="editForm.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" required :error="editForm.errors.principal_grace_months" />
                        <SmartSelect v-model="editForm.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" required :error="editForm.errors.interest_grace_months" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface">Pengajuan per Pemanfaat</h3>
                        <p class="mt-1 text-xs text-on-surface-variant">Sesuaikan nominal pengajuan masing-masing pemanfaat. Total pengajuan tidak boleh melebihi plafon pinjaman.</p>
                        <div v-if="loan.beneficiaries.length === 0" class="mt-3 rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Belum ada pemanfaat.</div>
                        <div v-else class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                    <tr>
                                        <th class="py-3 px-4">Nama / NIK</th>
                                        <th class="py-3 px-4 text-right">Pengajuan (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    <tr v-for="b in loan.beneficiaries" :key="b.member_row_id">
                                        <td class="py-3 px-4">
                                            <p class="font-semibold text-on-surface">{{ b.name || '—' }}</p>
                                            <p class="text-xs text-on-surface-variant">{{ b.nik || '' }} · #{{ b.member_id ?? b.member_row_id }}</p>
                                        </td>
                                        <td class="py-3 px-4 text-right">
                                            <AppCurrencyInput v-model="editForm.beneficiary_amounts[String(b.member_row_id)]" label="" hide-label :min="0" :error="editForm.errors[`beneficiary_amounts.${b.member_row_id}`]" />
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-surface-container-low">
                                        <td class="py-3 px-4 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Pengajuan</td>
                                        <td class="py-3 px-4 text-right text-base font-bold text-on-surface">{{ currency(editTotal) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p v-if="editForm.errors.beneficiary_amounts" class="mt-2 text-sm text-error">{{ editForm.errors.beneficiary_amounts }}</p>
                    </div>
                </form>
                <template #footer>
                    <AppButton variant="secondary" @click="editModalOpen = false">Batal</AppButton>
                    <AppButton :loading="editForm.processing" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</AppButton>
                </template>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
