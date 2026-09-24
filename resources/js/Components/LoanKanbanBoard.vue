<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppButton from './AppButton.vue';
import AppDatePicker from './AppDatePicker.vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';
import AppTextarea from './AppTextarea.vue';
import AppCurrencyInput from './AppCurrencyInput.vue';
import SmartSelect from './SmartSelect.vue';
import LoanBeneficiaryAmountTable from './LoanBeneficiaryAmountTable.vue';
import LoanKanbanCard from './LoanKanbanCard.vue';
import LoanKanbanColumn from './LoanKanbanColumn.vue';
import { useCan } from '../composables/useCan';
import { useConfirm } from '../composables/useConfirm';
import { useMoney } from '../composables/useMoney';

const props = defineProps({
    columns: { type: Object, required: true },
    disbursementAccounts: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    today: { type: String, required: true },
});

const emit = defineEmits(['refresh']);

const { can } = useCan();
const { confirm: confirmAction } = useConfirm();
const { money } = useMoney();

const draggedCard = ref(null);
const dropTarget = ref(null);
const actionModalOpen = ref(false);
const activeAction = ref(null);
const activeLoan = ref(null);

const kanbanTabs = [
    { key: 'proposal', label: 'Proposal', icon: 'inventory_2', accent: 'neutral' },
    { key: 'verifikasi', label: 'Verifikasi', icon: 'task_alt', accent: 'primary' },
    { key: 'waiting', label: 'Waiting', icon: 'hourglass_top', accent: 'warning' },
    { key: 'aktif', label: 'Aktif', icon: 'payments', accent: 'success' },
    { key: 'lunas', label: 'Lunas', icon: 'verified', accent: 'neutral' },
];

const ACTION_TRANSITIONS = {
    proposal: {
        verify: { to: 'verifikasi', label: 'Verifikasi', permission: 'loans.verify' },
    },
    verifikasi: {
        allocate: { to: 'waiting', label: 'Setujui & Alokasi', permission: 'loans.approve' },
        revert: { to: 'proposal', label: 'Kembalikan ke Proposal', permission: 'loans.manage' },
    },
    waiting: {
        disburse: { to: 'aktif', label: 'Catat Pencairan', permission: 'loans.disburse' },
        revert: { to: 'proposal', label: 'Kembalikan ke Proposal', permission: 'loans.manage' },
    },
};

const verifyForm = useForm({
    verified_at: props.today,
    verification_amount: null,
    verification_notes: '',
    verified_amounts: {},
    from_kanban: true,
});

const approveForm = useForm({
    approved_at: props.today,
    planned_disbursed_at: props.today,
    allocated_principal: 0,
    allocation_notes: '',
    beneficiaries: [],
    from_kanban: true,
});

const disburseForm = useForm({
    disbursed_at: props.today,
    disbursement_account_row_id: '',
    disbursement_notes: '',
    from_kanban: true,
});

const totalCards = computed(() => Object.values(props.columns).flat().length);
const totalNominal = computed(() => Object.values(props.columns).flat().reduce((sum, loan) => {
    const amount = Number(loan.principal_amount
        ?? loan.proposed_amount
        ?? loan.verification_amount
        ?? loan.allocated_amount
        ?? 0);
    return sum + amount;
}, 0));

const principalRemainingTotal = computed(() => Object.values(props.columns).flat().reduce((sum, loan) => {
    return sum + Number(loan.principal_remaining ?? 0);
}, 0));

const activeLoanCount = computed(() => (props.columns.aktif ?? []).length);
const verificationCount = computed(() => (props.columns.verifikasi ?? []).length);

const verifyBeneficiaryTotal = computed(() => Object.values(verifyForm.verified_amounts).reduce((sum, value) => sum + Number(value || 0), 0));
const approveBeneficiaryTotal = computed(() => approveForm.beneficiaries.reduce((sum, row) => sum + Number(row.allocated_amount || 0), 0));
const verifyBeneficiaries = computed(() => (activeLoan.value?.beneficiaries ?? []).map((b) => ({
    ...b,
    verified_amount: verifyForm.verified_amounts[String(b.member_row_id)] ?? 0,
})));

const modalTitle = computed(() => {
    if (!activeAction.value || !activeLoan.value) return '';
    return `${activeAction.value.label} — ${activeLoan.value.loan_number || '#' + activeLoan.value.row_id}`;
});

function cardColumn(loan) {
    if (['completed', 'written_off', 'rescheduled'].includes(loan.status)) return 'lunas';
    if (['active', 'disbursed'].includes(loan.status) && Number(loan.principal_remaining || 0) <= 0) return 'lunas';
    if (loan.status === 'draft') return 'proposal';
    if (loan.status === 'verified') return 'verifikasi';
    if (['waiting', 'approved'].includes(loan.status)) return 'waiting';
    return 'aktif';
}

function availableActions(loan) {
    const columnKey = cardColumn(loan);
    const actions = ACTION_TRANSITIONS[columnKey] ?? {};
    return Object.entries(actions)
        .filter(([, config]) => can(config.permission))
        .map(([key, config]) => ({ key, ...config }));
}

function canDrop(sourceColumn, targetColumn) {
    if (sourceColumn === targetColumn) return false;

    const allowed = {
        proposal: ['verifikasi'],
        verifikasi: ['waiting', 'proposal'],
        waiting: ['aktif', 'proposal'],
    };

    return (allowed[sourceColumn] ?? []).includes(targetColumn);
}

function openAction(actionKey, loan) {
    activeAction.value = ACTION_TRANSITIONS[cardColumn(loan)][actionKey];
    activeLoan.value = loan;

    if (actionKey === 'verify') {
        verifyForm.verification_amount = Number(loan.proposed_amount ?? loan.principal_amount ?? 0);
        verifyForm.verification_notes = '';
        verifyForm.verified_amounts = Object.fromEntries(
            (loan.beneficiaries ?? []).map((beneficiary) => [
                String(beneficiary.member_row_id),
                Number(beneficiary.verified_amount ?? beneficiary.proposed_amount ?? beneficiary.allocated_amount ?? 0),
            ]),
        );
        verifyForm.clearErrors();
    }
    if (actionKey === 'allocate') {
        approveForm.approved_at = props.today;
        approveForm.planned_disbursed_at = props.today;
        approveForm.allocated_principal = Number(loan.verification_amount ?? loan.proposed_amount ?? loan.principal_amount ?? 0);
        approveForm.allocation_notes = '';
        approveForm.beneficiaries = (loan.beneficiaries ?? []).map((b) => ({
            member_row_id: b.member_row_id,
            allocated_amount: Number(b.verified_amount ?? b.proposed_amount ?? b.allocated_amount ?? 0),
        }));
        approveForm.allocated_principal = approveBeneficiaryTotal.value;
        approveForm.clearErrors();
    }
    if (actionKey === 'disburse') {
        disburseForm.disbursed_at = props.today;
        disburseForm.disbursement_account_row_id = '';
        disburseForm.disbursement_notes = '';
        disburseForm.clearErrors();
    }
    if (actionKey !== 'revert') {
        actionModalOpen.value = true;
        return;
    }
    confirmAction({
        title: 'Kembalikan ke Proposal',
        message: `Pinjaman ${loan.loan_number || '#' + loan.row_id} akan dikembalikan ke status draft. Lanjutkan?`,
        confirmLabel: 'Ya, Kembalikan',
        cancelLabel: 'Batal',
        variant: 'warning',
    }).then((confirmed) => {
        if (!confirmed) return;
        router.patch(`/lending/loans/${loan.row_id}/revert?from_kanban=1`, {}, { preserveScroll: true, onSuccess: () => emit('refresh') });
    });
}

function handleDrop(targetColumn) {
    if (!draggedCard.value) return;
    const sourceColumn = cardColumn(draggedCard.value);
    const actionMap = {
        'proposal>verifikasi': 'verify',
        'verifikasi>waiting': 'allocate',
        'verifikasi>proposal': 'revert',
        'waiting>aktif': 'disburse',
        'waiting>proposal': 'revert',
    };
    const actionKey = actionMap[`${sourceColumn}>${targetColumn}`];

    if (!actionKey) {
        draggedCard.value = null;
        dropTarget.value = null;
        return;
    }

    openAction(actionKey, draggedCard.value);
    draggedCard.value = null;
    dropTarget.value = null;
}

function submitVerify() {
    verifyForm.patch(`/lending/loans/${activeLoan.value.row_id}/verify`, {
        preserveScroll: true,
        onSuccess: () => {
            actionModalOpen.value = false;
            emit('refresh');
        },
    });
}

function submitApprove() {
    approveForm.patch(`/lending/loans/${activeLoan.value.row_id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            actionModalOpen.value = false;
            emit('refresh');
        },
    });
}

function submitDisburse() {
    disburseForm.patch(`/lending/loans/${activeLoan.value.row_id}/disburse`, {
        preserveScroll: true,
        onSuccess: () => {
            actionModalOpen.value = false;
            emit('refresh');
        },
    });
}

function setBeneficiaryVerifiedAmount(memberRowId, value) {
    verifyForm.verified_amounts[String(memberRowId)] = value;
}

function setBeneficiaryAllocatedAmount(memberRowId, value) {
    const row = approveForm.beneficiaries.find((beneficiary) => beneficiary.member_row_id === memberRowId);
    if (row) row.allocated_amount = value;
}

function verifiedAmountError(beneficiary) {
    return verifyForm.errors[`verified_amounts.${beneficiary.member_row_id}`];
}

function allocatedAmountError(beneficiary) {
    return approveForm.errors[`beneficiaries.${beneficiary.member_row_id}.allocated_amount`];
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant/80">Total Pinjaman</p>
                <p class="mt-1 text-xl font-extrabold tabular-nums leading-tight text-on-surface">{{ totalCards }}</p>
                <p class="mt-0.5 truncate text-[11px] text-on-surface-variant">Semua tahap</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant/80">Nilai Portofolio</p>
                <p class="mt-1 truncate text-xl font-extrabold tabular-nums leading-tight text-on-surface" :title="money(totalNominal)">
                    {{ money(totalNominal) }}
                </p>
                <p class="mt-0.5 truncate text-[11px] text-on-surface-variant">Akumulasi plafon</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant/80">Pinjaman Aktif</p>
                <p class="mt-1 text-xl font-extrabold tabular-nums leading-tight text-secondary">{{ activeLoanCount }}</p>
                <p class="mt-0.5 truncate text-[11px] text-on-surface-variant">Berjalan saat ini</p>
            </div>
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-3.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant/80">Sisa Pokok</p>
                <p class="mt-1 truncate text-xl font-extrabold tabular-nums leading-tight text-on-surface" :title="money(principalRemainingTotal)">
                    {{ money(principalRemainingTotal) }}
                </p>
                <p class="mt-0.5 truncate text-[11px] text-on-surface-variant">Belum tertagih</p>
            </div>
        </div>

        <div v-if="totalCards === 0" class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest/60 px-6 py-12 text-center">
            <AppIcon name="view_kanban" class="text-4xl text-outline" />
            <p class="text-sm font-bold text-on-surface">Belum ada pinjaman untuk ditampilkan</p>
            <p class="max-w-md text-xs text-on-surface-variant">
                Papan kanban akan otomatis terisi ketika ada proposal, verifikasi, atau pencairan yang masuk pada tahap manapun.
            </p>
            <Link v-if="can('loans.propose')" href="/lending/loans/create" class="mt-2">
                <AppButton icon="add">Ajukan Pinjaman</AppButton>
            </Link>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <LoanKanbanColumn
                v-for="column in kanbanTabs"
                :key="column.key"
                :label="column.label"
                :icon="column.icon"
                :accent="column.accent"
                :count="(columns[column.key] ?? []).length"
                :drop-active="dropTarget === column.key"
                @drag-over="dropTarget = column.key"
                @drag-leave="dropTarget = dropTarget === column.key ? null : dropTarget"
                @drop="handleDrop(column.key)"
            >
                <LoanKanbanCard
                    v-for="loan in columns[column.key] ?? []"
                    :key="loan.row_id"
                    :loan="loan"
                    :stage-label="column.label"
                    :stage="column.key"
                    :actions="availableActions(loan)"
                    @dragstart="draggedCard = loan"
                    @dragend="draggedCard = null; dropTarget = null"
                    @action="openAction($event, loan)"
                />
            </LoanKanbanColumn>
        </div>

        <AppModal v-model="actionModalOpen" :title="modalTitle" size="md">
            <form v-if="activeAction?.key === 'verify'" class="space-y-4" @submit.prevent="submitVerify">
                <AppDatePicker v-model="verifyForm.verified_at" label="Tanggal Verifikasi" :max="today" required :error="verifyForm.errors.verified_at" />
                <AppCurrencyInput v-model="verifyForm.verification_amount" label="Nominal Verifikasi (opsional)" :min="0" :error="verifyForm.errors.verification_amount" />
                <AppTextarea v-model="verifyForm.verification_notes" label="Catatan Verifikasi (opsional)" :error="verifyForm.errors.verification_notes" />

                <LoanBeneficiaryAmountTable
                    :beneficiaries="verifyBeneficiaries"
                    amount-field="verified_amount"
                    label="Nominal Verifikasi"
                    :total="verifyBeneficiaryTotal"
                    :error-for="verifiedAmountError"
                    @update-amount="setBeneficiaryVerifiedAmount"
                />
            </form>

            <form v-else-if="activeAction?.key === 'allocate'" class="space-y-4" @submit.prevent="submitApprove">
                <div class="grid gap-4 sm:grid-cols-2">
                    <AppDatePicker v-model="approveForm.approved_at" label="Tanggal Penetapan" :max="today" required :error="approveForm.errors.approved_at" />
                    <AppDatePicker v-model="approveForm.planned_disbursed_at" label="Rencana Tanggal Cair" :min="approveForm.approved_at" required :error="approveForm.errors.planned_disbursed_at" />
                </div>
                <AppCurrencyInput v-model="approveForm.allocated_principal" label="Plafon Alokasi Kelompok" :min="0" required :error="approveForm.errors.allocated_principal" />
                <AppTextarea v-model="approveForm.allocation_notes" label="Catatan Penetapan (opsional)" :error="approveForm.errors.allocation_notes" />

                <LoanBeneficiaryAmountTable
                    :beneficiaries="approveForm.beneficiaries"
                    amount-field="allocated_amount"
                    label="Nominal Alokasi"
                    :total="approveBeneficiaryTotal"
                    :max-amount="Number(approveForm.allocated_principal || 0)"
                    :error-for="allocatedAmountError"
                    @update-amount="setBeneficiaryAllocatedAmount"
                />
                <p v-if="approveBeneficiaryTotal > Number(approveForm.allocated_principal || 0)" class="text-sm text-error">
                    Total alokasi per anggota melebihi plafon alokasi kelompok.
                </p>
            </form>

            <form v-else-if="activeAction?.key === 'disburse'" class="space-y-4" @submit.prevent="submitDisburse">
                <AppDatePicker v-model="disburseForm.disbursed_at" label="Tanggal Cair" :max="today" required :error="disburseForm.errors.disbursed_at" />
                <SmartSelect v-model="disburseForm.disbursement_account_row_id" :options="disbursementAccounts" label="Akun Sumber Dana" required :error="disburseForm.errors.disbursement_account_row_id" />
                <AppTextarea v-model="disburseForm.disbursement_notes" label="Catatan Pencairan (opsional)" :error="disburseForm.errors.disbursement_notes" />
            </form>

            <template #footer>
                <AppButton variant="secondary" @click="actionModalOpen = false">Batal</AppButton>
                <AppButton v-if="activeAction?.key === 'verify'" variant="primary" :loading="verifyForm.processing" @click="submitVerify">Simpan Verifikasi</AppButton>
                <AppButton v-else-if="activeAction?.key === 'allocate'" variant="primary" :loading="approveForm.processing" @click="submitApprove">Simpan &amp; Alokasikan</AppButton>
                <AppButton v-else-if="activeAction?.key === 'disburse'" variant="primary" :loading="disburseForm.processing" @click="submitDisburse">Catat Pencairan</AppButton>
            </template>
        </AppModal>
    </div>
</template>