<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

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

// ===== Permission computed =====
const canEdit = computed(() => can('loans.manage') && props.loan.status === 'draft');
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
    principal_grace_months: Number(props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(props.loan.interest_grace_months ?? 0),
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
    principal_grace_months: Number(props.loan.principal_grace_months ?? 0),
    interest_grace_months: Number(props.loan.interest_grace_months ?? 0),
    rounding_step: props.loan.rounding_step !== null && props.loan.rounding_step !== undefined ? String(props.loan.rounding_step) : '',
});
function openRescheduleModal() {
    rescheduleForm.rescheduled_at = props.today;
    rescheduleForm.term_months = props.loan.term_months ?? 12;
    rescheduleForm.service_rate_total = Number(props.loan.service_rate_total ?? 0);
    rescheduleForm.installment_method = props.loan.installment_method ?? 'flat';
    rescheduleForm.principal_frequency = props.loan.principal_frequency ?? 'monthly';
    rescheduleForm.interest_frequency = props.loan.interest_frequency ?? 'monthly';
    rescheduleForm.principal_grace_months = Number(props.loan.principal_grace_months ?? 0);
    rescheduleForm.interest_grace_months = Number(props.loan.interest_grace_months ?? 0);
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

// ===== Documents =====
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

const docSearch = ref('');

// ===== Documents modal =====
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

const STAGE_META = {
    proposal: { label: 'Proposal', description: 'Dokumen yang disiapkan pada tahap pengajuan proposal.', tone: 'warning' },
    verification: { label: 'Verifikasi', description: 'Dokumen yang disiapkan pada tahap verifikasi lapangan.', tone: 'primary' },
    disbursement: { label: 'Pencairan', description: 'Dokumen yang disiapkan pada tahap pencairan & penyaluran.', tone: 'success' },
};
const STAGE_ICON = { proposal: 'inventory_2', verification: 'task_alt', disbursement: 'payments' };

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
        buckets[d.stage].push(d);
    }
    return buckets;
});
const visibleStageKeys = computed(() => ['proposal', 'verification', 'disbursement'].filter((stage) => documentsByStage.value[stage].length > 0));

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

// ===== Tab system (single page, switch content) =====
const activeTab = ref('overview');

const tabs = computed(() => {
    const items = [
        { key: 'overview', label: 'Ringkasan', icon: 'dashboard' },
        { key: 'beneficiaries', label: 'Pemanfaat', icon: 'groups', badge: props.loan.beneficiaries.length || null },
    ];
    if (installmentRows.value.length > 0) {
        items.push({ key: 'schedule', label: 'Jadwal', icon: 'event', badge: installmentRows.value.length || null });
    }
    if (paymentRows.value.length > 0) {
        items.push({ key: 'payments', label: 'Pembayaran', icon: 'receipt_long', badge: paymentRows.value.length || null });
    }
    if (canShowVerifyForm.value || canShowApproveForm.value || canShowDisburseForm.value) {
        items.push({ key: 'actions', label: 'Aksi', icon: 'bolt', badge: 'Aksi' });
    }
    return items;
});

// ===== Summary metrics =====
const principalPaid = computed(() => Number(props.loan.principal_paid ?? 0));
const interestPaid = computed(() => Number(props.loan.total_interest_paid ?? 0));
const interestDue = computed(() => Number(props.loan.total_interest_due ?? 0));
const principalRemaining = computed(() => Number(props.loan.principal_remaining ?? 0));
const totalPaid = computed(() => principalPaid.value + interestPaid.value);
const totalDue = computed(() => Number(props.loan.principal_amount ?? 0) + interestDue.value);
const outstandingTotal = computed(() => principalRemaining.value + Math.max(0, interestDue.value - interestPaid.value));
const progressPercent = computed(() => {
    const p = Number(props.loan.progress_percent ?? 0);
    return Math.min(100, Math.max(0, p));
});

// ===== Borrower / product facts =====
const loanFacts = computed(() => [
    { label: 'No. Pinjaman', value: props.loan.loan_number || `#${props.loan.row_id}`, icon: 'tag' },
    { label: 'Produk', value: props.loan.product ? `${props.loan.product.name}` : '—', sub: props.loan.product?.code || '', icon: 'inventory_2' },
    { label: 'Kelompok', value: props.loan.group?.name || 'Pinjaman Perorangan', sub: props.loan.group?.address || '', icon: 'groups' },
    { label: 'Desa', value: props.loan.group?.village?.name || '—', icon: 'location_on' },
    { label: 'Tanggal Pengajuan', value: formatDate(props.loan.proposed_at), icon: 'event' },
    { label: 'Tanggal Verifikasi', value: formatDate(props.loan.verified_at), icon: 'verified' },
    { label: 'Tanggal Penetapan', value: formatDate(props.loan.approved_at), icon: 'fact_check' },
    { label: 'Tanggal Cair', value: formatDate(props.loan.disbursed_at), icon: 'payments' },
    { label: 'Tanggal Selesai', value: formatDate(props.loan.completed_at), icon: 'task_alt' },
    { label: 'Tingkat Jasa', value: percent(props.loan.service_rate_total), sub: monthlyRate(props.loan.service_rate_total, props.loan.term_months), icon: 'percent' },
    { label: 'Pembulatan', value: roundingLabel(props.loan.rounding_step, props.loan.product?.rounding_method), icon: 'straighten' },
    { label: 'Jangka Waktu', value: `${props.loan.term_months} bulan`, icon: 'schedule' },
]);

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
        <div class="mx-auto max-w-[1280px] space-y-5 pb-12">
            <!-- ============================================== -->
            <!-- COMPACT HEADER BAR                                -->
            <!-- ============================================== -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <nav class="flex items-center gap-2 text-xs text-on-surface-variant" aria-label="Breadcrumb">
                    <Link href="/lending/loans">
                        <AppButton type="button" variant="secondary" icon="arrow_back" size="compact">Kembali ke Daftar</AppButton>
                    </Link>
                </nav>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton v-if="canCompleteAction" variant="success" icon="task_alt" size="compact" @click="openCompleteModal">Validasi Lunas</AppButton>
                    <AppButton v-if="canReschedule" variant="secondary" icon="event_repeat" size="compact" @click="openRescheduleModal">Reschedule</AppButton>
                    <AppButton v-if="canCancelReschedule" variant="danger" icon="undo" size="compact" @click="openCancelRescheduleModal">Batalkan</AppButton>
                    <AppButton v-if="canWriteOff" variant="danger" icon="delete_sweep" size="compact" @click="openWriteOffModal">Hapus Piutang</AppButton>
                    <AppButton v-if="canEdit" type="button" class="!bg-yellow-500 !text-white hover:!bg-yellow-600" icon="edit" size="compact" @click="openEditModal">Edit Proposal</AppButton>
                    <AppButton v-if="canDeleteProposal" type="button" variant="danger" icon="delete_outline" size="compact" @click="confirmDeleteLoan">Hapus Proposal</AppButton>
                    <AppButton type="button" variant="secondary" icon="history" size="compact" @click="auditHistoryModalOpen = true">Riwayat &amp; Audit</AppButton>
                    <AppButton v-if="canPrintDocument" type="button" variant="secondary" icon="description" size="compact" @click="documentModalOpen = true">
                        Dokumen
                        <span v-if="availableDocuments.length" class="ml-1 rounded-full bg-primary/15 px-1.5 py-0.5 text-[10px] font-bold text-primary">{{ availableDocuments.length }}</span>
                    </AppButton>
                </div>
            </div>

            <!-- ============================================== -->
            <!-- HERO PANEL (minimalist, single block)             -->
            <!-- ============================================== -->
            <section class="rounded-2xl bg-primary-container/25 px-5 py-4 shadow-md sm:px-6 sm:py-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate whitespace-nowrap text-xl font-extrabold tracking-tight text-on-surface sm:text-2xl" :title="loan.loan_number || `#${loan.row_id}`">
                            {{ loan.loan_number || `Pinjaman #${loan.row_id}` }}
                        </h1>
                        <p class="mt-0.5 truncate text-sm text-on-surface-variant">
                            {{ loan.product?.name || '—' }}
                            <span v-if="loan.group?.name" class="text-outline"> · </span>
                            {{ loan.group?.name || 'Pinjaman perorangan' }}
                            <span v-if="loan.group?.village?.name" class="text-outline"> · </span>
                            {{ loan.group?.village?.name }}
                        </p>
                        <p v-if="statusMeta.description" class="mt-1 truncate text-xs text-on-surface-variant" :title="statusMeta.description">
                            {{ statusMeta.description }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <AppBadge :tone="statusMeta.tone">{{ statusMeta.label }}</AppBadge>
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-container px-2.5 py-0.5 text-[11px] font-semibold text-on-surface-variant">
                            <AppIcon :name="loan.group?.name ? 'groups' : 'person'" class="text-sm" />
                            {{ loan.group?.name ? 'Pinjaman Kelompok' : 'Pinjaman Perorangan' }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- METRIC STRIP (clean horizontal cards)             -->
            <!-- ============================================== -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl bg-surface-container-lowest p-4 shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Plafon</p>
                        <AppIcon name="account_balance" class="text-base text-on-surface-variant" />
                    </div>
                    <p class="mt-2 text-xl font-extrabold tabular-nums tracking-tight text-on-surface">{{ currency(loan.principal_amount) }}</p>
                    <p class="mt-0.5 text-[11px] text-on-surface-variant">Total pinjaman disetujui</p>
                </div>

                <div class="rounded-2xl bg-surface-container-lowest p-4 shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Sisa Pokok</p>
                        <AppIcon name="account_balance_wallet" class="text-base text-on-surface-variant" />
                    </div>
                    <p class="mt-2 text-xl font-extrabold tabular-nums tracking-tight text-on-surface">{{ currency(principalRemaining) }}</p>
                    <p class="mt-0.5 text-[11px] text-on-surface-variant">
                        <template v-if="principalRemaining > 0">dari {{ currency(loan.principal_amount) }}</template>
                        <template v-else><span class="font-semibold text-secondary">Lunas</span></template>
                    </p>
                </div>

                <div class="rounded-2xl bg-surface-container-lowest p-4 shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Progress</p>
                        <AppIcon name="donut_large" class="text-base text-on-surface-variant" />
                    </div>
                    <p class="mt-2 text-xl font-extrabold tabular-nums tracking-tight text-on-surface">{{ loan.paid_installments }}<span class="text-base font-bold text-on-surface-variant">/{{ loan.total_installments }}</span></p>
                    <div class="mt-2 flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-container-high">
                            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: `${progressPercent}%` }"></div>
                        </div>
                        <span class="text-[11px] font-bold tabular-nums text-on-surface-variant">{{ progressPercent.toFixed(0) }}%</span>
                    </div>
                </div>

                <div class="rounded-2xl bg-surface-container-lowest p-4 shadow-md">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Sisa Tagihan</p>
                        <AppIcon name="receipt_long" class="text-base text-on-surface-variant" />
                    </div>
                    <p class="mt-2 text-xl font-extrabold tabular-nums tracking-tight text-on-surface">{{ currency(outstandingTotal) }}</p>
                    <p class="mt-0.5 text-[11px] text-on-surface-variant">Pokok + jasa belum dibayar</p>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- TAB BAR (segmented control)                       -->
            <!-- ============================================== -->
            <div class="-mx-1 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest px-1.5 py-1.5 shadow-sm">
                <nav class="flex flex-nowrap items-stretch gap-1.5" aria-label="Bagian detail pinjaman">
                    <button
                        v-for="tab in tabs"
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

            <!-- ============================================== -->
            <!-- TAB CONTENT: OVERVIEW                             -->
            <!-- ============================================== -->
            <section v-show="activeTab === 'overview'" class="grid gap-5 lg:grid-cols-3">
                <!-- LEFT: Loan facts (densed facts grid) -->
                <div class="lg:col-span-2">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">Informasi Pinjaman</h2>
                    <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                        <dl class="divide-y divide-outline-variant/60">
                            <div v-for="fact in loanFacts" :key="fact.label" class="flex items-start gap-3 px-4 py-3">
                                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg bg-surface-container text-on-surface-variant">
                                    <AppIcon :name="fact.icon" class="text-base" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <dt class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ fact.label }}</dt>
                                    <dd class="mt-0.5 text-sm font-semibold text-on-surface">{{ fact.value }}</dd>
                                    <dd v-if="fact.sub" class="text-[11px] text-on-surface-variant">{{ fact.sub }}</dd>
                                </div>
                            </div>
                        </dl>
                    </div>

                    <div v-if="loan.verification_notes && status === 'verified'" class="mt-4 flex items-start gap-3 rounded-2xl border border-primary/30 bg-primary-container/20 p-4">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-container text-primary">
                            <AppIcon name="sticky_note_2" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary">Catatan Verifikasi</p>
                            <p class="mt-1 text-sm text-on-surface">{{ loan.verification_notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Committee (single compact card) -->
                <div>
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-on-surface-variant">Pengurus Kelompok</h2>
                    <div class="rounded-2xl bg-surface-container-lowest p-4 shadow-md">
                        <div v-if="committeeEditable" class="space-y-3">
                            <SmartSelect v-model="committeeForm.chair_id" label="Ketua" placeholder="Pilih ketua" :options="committeeOptions" :error="committeeForm.errors.chair_id" required />
                            <SmartSelect v-model="committeeForm.secretary_id" label="Sekretaris" placeholder="Pilih sekretaris" :options="committeeOptions" :error="committeeForm.errors.secretary_id" required />
                            <SmartSelect v-model="committeeForm.treasurer_id" label="Bendahara" placeholder="Pilih bendahara" :options="committeeOptions" :error="committeeForm.errors.treasurer_id" required />
                            <div class="flex justify-end pt-1">
                                <AppButton type="button" icon="save" :disabled="!committeeReady || committeeForm.processing" @click="openCommitteeConfirm">Simpan Pengurus</AppButton>
                            </div>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="(entry, position) in loan.committee" :key="position" class="flex items-center gap-3 rounded-xl bg-surface-container-low/60 px-3 py-2.5">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-surface text-on-surface-variant">
                                    <AppIcon :name="position === 'chair' ? 'workspace_premium' : position === 'secretary' ? 'edit_note' : 'savings'" class="text-base" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">{{ committeeLabels[position] || position }}</p>
                                    <p class="text-sm font-semibold text-on-surface">{{ entry?.name || '—' }}</p>
                                    <p v-if="entry?.snapshot_at" class="text-[10px] text-on-surface-variant">Sejak {{ formatDate(entry.snapshot_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- TAB CONTENT: PEMANFAAT                            -->
            <!-- ============================================== -->
            <section v-show="activeTab === 'beneficiaries'">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Daftar Pemanfaat</h2>
                    <p class="text-xs text-on-surface-variant">{{ beneficiaryStats.total }} anggota · Total alokasi <span class="font-bold text-on-surface">{{ currency(beneficiaryStats.allocated) }}</span></p>
                </div>
                <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-container-low text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <tr>
                                    <th class="py-3 px-4">Anggota</th>
                                    <th class="py-3 px-4 text-right">Pengajuan</th>
                                    <th v-if="canShowVerifiedAmount" class="py-3 px-4 text-right">Verifikasi</th>
                                    <th v-if="canShowAllocatedAmount" class="py-3 px-4 text-right">Alokasi</th>
                                    <th v-if="canRemoveBeneficiary || canWriteOffBeneficiary" class="py-3 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                <tr v-for="b in loan.beneficiaries" :key="b.row_id" class="transition hover:bg-surface-container-low/50">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-container text-xs font-bold text-primary">
                                                {{ (b.name || '?').charAt(0).toUpperCase() }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-on-surface">{{ b.name || '—' }}</p>
                                                <p class="text-[11px] text-on-surface-variant">{{ b.nik || '—' }} · #{{ b.member_id ?? b.member_row_id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface">{{ currency(b.proposed_amount ?? b.allocated_amount) }}</td>
                                    <td v-if="canShowVerifiedAmount" class="py-3 px-4 text-right">
                                        <AppCurrencyInput v-if="canVerifyBeneficiary" v-model="verifyForm.verified_amounts[String(b.member_row_id)]" label="" hide-label :min="0" :error="verifyForm.errors[`verified_amounts.${b.member_row_id}`]" />
                                        <span v-else class="font-semibold tabular-nums text-on-surface">{{ currency(b.verified_amount ?? 0) }}</span>
                                    </td>
                                    <td v-if="canShowAllocatedAmount" class="py-3 px-4 text-right">
                                        <AppCurrencyInput v-if="canAllocatePerBeneficiary" :model-value="getAllocatedAmount(b.member_row_id)" @update:model-value="(value) => setAllocatedAmount(b.member_row_id, value)" label="" hide-label :min="0" :max="loan.principal_amount" :error="approveForm.errors[`beneficiaries.${b.member_row_id}.allocated_amount`]" placeholder="0" />
                                        <span v-else class="font-semibold tabular-nums text-on-surface">{{ currency(b.allocated_amount ?? 0) }}</span>
                                    </td>
                                    <td v-if="canRemoveBeneficiary || canWriteOffBeneficiary" class="py-3 px-4 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <AppIconButton v-if="canRemoveBeneficiary" size="sm" rounded="full" name="delete_outline" tone="danger" :tooltip="`Hapus ${b.name}`" @click="confirmRemoveBeneficiary(b)" />
                                            <template v-if="canWriteOffBeneficiary">
                                                <div v-if="b.written_off_at" class="flex flex-col items-end gap-0.5">
                                                    <AppBadge tone="error">Dihapus</AppBadge>
                                                    <span class="text-[10px] text-on-surface-variant">{{ formatDate(b.written_off_at) }}</span>
                                                </div>
                                                <AppIconButton v-else size="sm" rounded="full" name="delete_sweep" tone="danger" :tooltip="`Hapus bukukan ${b.name}`" @click="openBeneficiaryWriteOffModal(b)" />
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="canShowVerifiedAmount || canShowAllocatedAmount" class="bg-surface-container-low">
                                <tr>
                                    <td class="py-3 px-4 text-right text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Total</td>
                                    <td class="py-3 px-4 text-right text-sm font-extrabold tabular-nums text-on-surface">{{ currency(totalProposal) }}</td>
                                    <td v-if="canShowVerifiedAmount" class="py-3 px-4 text-right text-sm font-extrabold tabular-nums text-on-surface">
                                        <span v-if="status === 'draft'">{{ currency(verifiedTotal) }}</span>
                                        <span v-else>{{ currency(verifiedAmountTotal) }}</span>
                                    </td>
                                    <td v-if="canShowAllocatedAmount" class="py-3 px-4 text-right text-sm font-extrabold tabular-nums text-on-surface">
                                        <span v-if="canAllocatePerBeneficiary">{{ currency(approveTotal) }}</span>
                                        <span v-else>{{ currency(totalAllocation) }}</span>
                                    </td>
                                    <td v-if="canRemoveBeneficiary || canWriteOffBeneficiary"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="removeError" class="px-4 py-2 text-xs text-error">{{ removeError }}</p>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- TAB CONTENT: AKSI                                 -->
            <!-- ============================================== -->
            <section v-if="canShowVerifyForm || canShowApproveForm || canShowDisburseForm" v-show="activeTab === 'actions'" class="space-y-5">
                <div v-if="canShowVerifyForm" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-6 py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-primary-container text-primary">
                            <AppIcon name="verified" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Form Verifikasi</h3>
                            <p class="text-xs text-on-surface-variant">Lengkapi tanggal dan catatan verifikasi.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="submitVerify">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="verifyForm.verified_at" label="Tanggal Verifikasi" :max="today" :error="verifyForm.errors.verified_at" required />
                            <AppCurrencyInput v-model="verifyForm.verification_amount" @update:model-value="verifyTotalTouched = true" label="Nominal Verifikasi Total" :min="0" :error="verifyForm.errors.verification_amount" hint="Kosongkan untuk konfirmasi plafon penuh." />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppInput v-model="verifyForm.term_months" label="Rekomendasi Jangka Waktu (bulan)" icon="schedule" type="number" min="1" max="120" :error="verifyForm.errors.term_months" />
                            <AppCurrencyInput v-model="verifyForm.service_rate_total" label="Pros Jasa Total (%)" icon="percent" :min="0" :max="100" :error="verifyForm.errors.service_rate_total" />
                            <SmartSelect v-model="verifyForm.principal_frequency" label="Sistem Pokok" :options="frequencyOptions" :error="verifyForm.errors.principal_frequency" />
                            <SmartSelect v-model="verifyForm.principal_grace_months" label="Grace Pokok" :options="graceOptions" :error="verifyForm.errors.principal_grace_months" />
                            <SmartSelect v-model="verifyForm.interest_frequency" label="Sistem Jasa" :options="frequencyOptions" :error="verifyForm.errors.interest_frequency" />
                            <SmartSelect v-model="verifyForm.interest_grace_months" label="Grace Jasa" :options="graceOptions" :error="verifyForm.errors.interest_grace_months" />
                        </div>
                        <AppTextarea v-model="verifyForm.verification_notes" label="Catatan Verifikasi (opsional)" :error="verifyForm.errors.verification_notes" placeholder="Hasil pemeriksaan lapangan, kelengkapan dokumen, dll." />
                        <div class="flex justify-end pt-1">
                            <AppButton type="submit" :loading="verifyForm.processing">Simpan &amp; Verifikasi</AppButton>
                        </div>
                    </form>
                </div>

                <div v-if="canShowApproveForm" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-6 py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-tertiary-fixed/30 text-tertiary">
                            <AppIcon name="fact_check" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Penetapan Alokasi</h3>
                            <p class="text-xs text-on-surface-variant">Standar verifikasi: <span class="font-semibold text-on-surface">{{ currency(verifiedAmountTotal) }}</span></p>
                        </div>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="submitApprove">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppDatePicker v-model="approveForm.approved_at" label="Tanggal Penetapan" :max="today" :error="approveForm.errors.approved_at" required />
                            <AppDatePicker v-model="approveForm.planned_disbursed_at" label="Rencana Tanggal Cair" :min="approveForm.approved_at" :error="approveForm.errors.planned_disbursed_at" required />
                            <AppCurrencyInput v-model="approveForm.allocated_principal" @update:model-value="approveTotalTouched = true" label="Plafon Alokasi" icon="payments" :min="0" :max="loan.proposed_amount ?? loan.principal_amount" required :error="approveForm.errors.allocated_principal" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppInput v-model="approveForm.term_months" label="Jangka Waktu (bulan)" icon="schedule" type="number" min="1" max="120" required :error="approveForm.errors.term_months" />
                            <AppCurrencyInput v-model="approveForm.service_rate_total" label="Pros Jasa (%)" icon="percent" :min="0" :max="100" required :error="approveForm.errors.service_rate_total" />
                            <SmartSelect v-model="approveForm.principal_frequency" label="Sistem Pokok" :options="frequencyOptions" required :error="approveForm.errors.principal_frequency" />
                            <SmartSelect v-model="approveForm.principal_grace_months" label="Grace Pokok" :options="graceOptions" required :error="approveForm.errors.principal_grace_months" />
                            <SmartSelect v-model="approveForm.interest_frequency" label="Sistem Jasa" :options="frequencyOptions" required :error="approveForm.errors.interest_frequency" />
                            <SmartSelect v-model="approveForm.interest_grace_months" label="Grace Jasa" :options="graceOptions" required :error="approveForm.errors.interest_grace_months" />
                        </div>
                        <p v-if="approveTotal > approveForm.allocated_principal" class="text-xs text-error">Total alokasi per anggota ({{ currency(approveTotal) }}) melebihi plafon kelompok ({{ currency(approveForm.allocated_principal) }}).</p>
                        <AppTextarea v-model="approveForm.allocation_notes" label="Catatan Penetapan (opsional)" :error="approveForm.errors.allocation_notes" placeholder="Akan muncul di riwayat status." />
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-2">
                                <AppButton v-if="canRevert" type="button" variant="secondary" @click="openRevertModal">Kembalikan ke Draft</AppButton>
                                <AppButton type="submit" :loading="approveForm.processing">Simpan &amp; Alokasikan</AppButton>
                            </div>
                        </div>
                    </form>
                </div>

                <div v-if="canShowDisburseForm" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="flex items-center gap-3 border-b border-outline-variant/60 px-6 py-4">
                        <span class="flex size-9 items-center justify-center rounded-xl bg-secondary-container text-secondary">
                            <AppIcon name="payments" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Form Pencairan</h3>
                            <p class="text-xs text-on-surface-variant">Catat tanggal dan akun sumber dana saat pinjaman dicairkan.</p>
                        </div>
                    </div>
                    <form class="space-y-4 p-6" @submit.prevent="submitDisburse">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="disburseForm.disbursed_at" label="Tanggal Cair" :max="today" :error="disburseForm.errors.disbursed_at" required />
                            <SmartSelect v-model="disburseForm.disbursement_account_row_id" :options="disbursementAccounts" label="Akun Sumber Dana" placeholder="Pilih akun kas/bank" :error="disburseForm.errors.disbursement_account_row_id" required />
                        </div>
                        <AppTextarea v-model="disburseForm.disbursement_notes" label="Catatan Pencairan (opsional)" :error="disburseForm.errors.disbursement_notes" placeholder="Referensi transfer, nomor slip, dll." />
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <Link :href="backUrl"><AppButton type="button" variant="secondary">Kembali</AppButton></Link>
                            <div class="flex flex-wrap items-center gap-2">
                                <AppButton v-if="canRevert" type="button" variant="secondary" @click="openRevertModal">Kembalikan ke Draft</AppButton>
                                <AppButton v-if="canDisburseAction" type="submit" :loading="disburseForm.processing">Catat Pencairan</AppButton>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- TAB CONTENT: JADWAL ANGSURAN                     -->
            <!-- ============================================== -->
            <section v-if="installmentRows.length" v-show="activeTab === 'schedule'">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Jadwal Angsuran</h2>
                    <p class="text-xs text-on-surface-variant">{{ installmentRows.length }} angsuran · Sisa pokok {{ currency(principalRemaining) }}</p>
                </div>
                <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-container-low text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <tr>
                                    <th class="py-3 px-4">#</th>
                                    <th class="py-3 px-4">Jatuh Tempo</th>
                                    <th class="py-3 px-4 text-right">Pokok</th>
                                    <th class="py-3 px-4 text-right">Jasa</th>
                                    <th class="py-3 px-4 text-right">Kumulatif Pokok</th>
                                    <th class="py-3 px-4 text-right">Kumulatif Jasa</th>
                                    <th class="py-3 px-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                <tr v-for="row in installmentRows" :key="row.installment_number" class="transition hover:bg-surface-container-low/50">
                                    <td class="py-3 px-4 text-on-surface-variant">{{ installmentNumber(row.installment_number) }}</td>
                                    <td class="py-3 px-4 text-on-surface-variant">{{ formatDate(row.due_date) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface">{{ currency(row.principal_due) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface">{{ currency(row.interest_due) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface-variant">{{ currency(row.target_pokok) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface-variant">{{ currency(row.target_jasa) }}</td>
                                    <td class="py-3 px-4 text-right">
                                        <AppBadge :tone="row.status === 'paid' ? 'success' : 'neutral'">{{ row.status === 'paid' ? 'Lunas' : 'Belum' }}</AppBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ============================================== -->
            <!-- TAB CONTENT: PEMBAYARAN                           -->
            <!-- ============================================== -->
            <section v-if="paymentRows.length" v-show="activeTab === 'payments'">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Riwayat Pembayaran</h2>
                    <p class="text-xs text-on-surface-variant">{{ paymentRows.length }} kali pembayaran</p>
                </div>
                <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-md">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-container-low text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">
                                <tr>
                                    <th class="py-3 px-4">Tanggal</th>
                                    <th class="py-3 px-4 text-right">Pokok</th>
                                    <th class="py-3 px-4 text-right">Jasa</th>
                                    <th class="py-3 px-4 text-right">Σ Pokok</th>
                                    <th class="py-3 px-4 text-right">Σ Jasa</th>
                                    <th class="py-3 px-4 text-right">Sisa Pokok</th>
                                    <th class="py-3 px-4 text-right">Sisa Jasa</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                <tr v-for="(row, idx) in paymentRows" :key="idx" class="transition hover:bg-surface-container-low/50">
                                    <td class="py-3 px-4 text-on-surface-variant">{{ formatDate(row.paid_at) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface">{{ currency(row.pokok) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface">{{ currency(row.jasa) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface-variant">{{ currency(row.sum_pokok) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-on-surface-variant">{{ currency(row.sum_jasa) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-error">{{ currency(row.tunggakan_pokok) }}</td>
                                    <td class="py-3 px-4 text-right tabular-nums text-error">{{ currency(row.tunggakan_jasa) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </div>

        <!-- ============== MODALS ============== -->
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
    </AuthenticatedLayout>
</template>
