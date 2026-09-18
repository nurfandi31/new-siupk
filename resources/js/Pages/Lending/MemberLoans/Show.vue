<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    loan: { type: Object, required: true },
    card_url: { type: String, default: null },
    settlement_letter_url: { type: String, default: null },
    disbursement_account: { type: Object, default: null },
    disbursementAccounts: { type: Array, default: () => [] },
    today: { type: String, default: '' },
    can: { type: Object, default: () => ({}) },
});

const money = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
const tabular = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const dateFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
const dateTimeFmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

function fMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return money.format(Number(value));
}
function fNumber(value) {
    if (value === null || value === undefined || value === '') return '—';
    return tabular.format(Number(value));
}
function fDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return dateFmt.format(d);
}
function fDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return dateTimeFmt.format(d);
}

const statusLabel = computed(() => {
    const map = {
        draft: 'Proposal',
        verified: 'Terverifikasi',
        waiting: 'Waiting',
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
        verified: 'info',
        waiting: 'info',
        approved: 'primary',
        active: 'success',
        disbursed: 'success',
        completed: 'success',
        written_off: 'danger',
        rescheduled: 'secondary',
        rejected: 'danger',
    };
    return map[props.loan.status] ?? 'secondary';
});

function can(action) {
    const perms = props.can ?? {};
    return Boolean(perms[action]);
}

// ===== Verifikasi =====
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

// ===== Approve / Alokasi =====
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

// ===== Disburse =====
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

// ===== Revert =====
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

// ===== Reject (Tidak Layak) =====
const rejectOpen = ref(false);
const rejectForm = ref({ notes: '' });
function submitReject() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/reject`, rejectForm.value, {
        preserveScroll: true,
        onSuccess: () => { rejectOpen.value = false; },
    });
}

// ===== Complete (Lunas) =====
const completeOpen = ref(false);
const completeForm = ref({ completed_at: props.today, notes: '' });
function submitComplete() {
    router.patch(`/lending/member-loans/${props.loan.row_id}/complete`, completeForm.value, {
        preserveScroll: true,
        onSuccess: () => { completeOpen.value = false; },
    });
}

// ===== WriteOff =====
const writeOffOpen = ref(false);
const writeOffForm = ref({ written_off_at: props.today, reason: '' });
function submitWriteOff() {
    router.post(`/lending/member-loans/${props.loan.row_id}/write-off`, writeOffForm.value, {
        preserveScroll: true,
        onSuccess: () => { writeOffOpen.value = false; },
    });
}

// ===== Reschedule =====
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

// Show/hide based on status & permission
const canVerify = computed(() => can('loans.verify') && props.loan.status === 'draft');
const canApprove = computed(() => can('loans.approve') && props.loan.status === 'verified');
const canDisburse = computed(() => can('loans.disburse') && ['waiting', 'approved'].includes(props.loan.status));
const canRevert = computed(() => can('loans.manage') && ['verified', 'waiting', 'approved'].includes(props.loan.status));
const canReject = computed(() => can('loans.manage') && ['draft', 'verified'].includes(props.loan.status));
const canComplete = computed(() => (can('loans.complete_director') || can('loans.manage')) && ['active', 'disbursed'].includes(props.loan.status));
const canWriteOff = computed(() => can('loans.write_off') && ['active', 'disbursed'].includes(props.loan.status));
const canReschedule = computed(() => (can('loans.reschedule_director') || can('loans.manage')) && ['active', 'disbursed'].includes(props.loan.status));
const hasActions = computed(() => canVerify.value || canApprove.value || canDisburse.value || canRevert.value || canReject.value || canComplete.value || canWriteOff.value || canReschedule.value);

// ===== Dokumen Cetak =====
const STAGE_INDIVIDUAL_DISBURSEMENT = ['waiting', 'approved', 'active', 'disbursed', 'completed'];
const STAGE_INDIVIDUAL_SETTLEMENT = ['completed', 'written_off'];

const INDIVIDUAL_DOCUMENTS = [
    { key: 'spk_individu',                label: 'Surat Perjanjian Kredit',          stage: 'individual_disbursement', icon: 'gavel',          stageLabel: 'Pencairan' },
    { key: 'kuitansi_pencairan_individu', label: 'Kuitansi Pencairan',              stage: 'individual_disbursement', icon: 'receipt_long',   stageLabel: 'Pencairan' },
    { key: 'berita_acara_pencairan_individu', label: 'Berita Acara Pencairan',       stage: 'individual_disbursement', icon: 'fact_check',     stageLabel: 'Pencairan' },
    { key: 'tanda_terima_jaminan',        label: 'Tanda Terima Jaminan',             stage: 'individual_disbursement', icon: 'inventory_2',    stageLabel: 'Pencairan' },
    { key: 'bukti_pengembalian_jaminan',  label: 'Bukti Pengembalian Jaminan',       stage: 'individual_settlement',   icon: 'assignment_return', stageLabel: 'Pelunasan' },
];

const availableDocuments = computed(() => {
    const status = props.loan.status;
    return INDIVIDUAL_DOCUMENTS.filter((doc) => {
        if (doc.stage === 'individual_disbursement') return STAGE_INDIVIDUAL_DISBURSEMENT.includes(status);
        if (doc.stage === 'individual_settlement') return STAGE_INDIVIDUAL_SETTLEMENT.includes(status);
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
</script>

<template>
    <Head title="Detail Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ loan.loan_number || `Pinjam #${loan.row_id}` }}</h1>
                        <AppBadge :variant="statusVariant">{{ statusLabel }}</AppBadge>
                        <AppBadge variant="secondary">Individu</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">Detail pinjaman perorangan — anggota langsung sebagai peminjam.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link href="/lending/member-loans"><AppButton variant="secondary" icon="arrow_back">Kembali</AppButton></Link>
                    <a v-if="card_url" :href="card_url" target="_blank">
                        <AppButton variant="secondary" icon="print">Kartu Angsuran</AppButton>
                    </a>
                    <a v-if="settlement_letter_url" :href="settlement_letter_url" target="_blank">
                        <AppButton variant="secondary" icon="verified">Surat Lunas</AppButton>
                    </a>
                </div>
            </header>

            <AppCard v-if="hasActions">
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton v-if="canVerify" variant="primary" icon="check" @click="verifyOpen = true">Verifikasi</AppButton>
                    <AppButton v-if="canApprove" variant="primary" icon="assignment_turned_in" @click="approveOpen = true">Alokasikan</AppButton>
                    <AppButton v-if="canDisburse" variant="primary" icon="payments" @click="disburseOpen = true">Catat Pencairan</AppButton>
                    <AppButton v-if="canComplete" variant="primary" icon="task_alt" @click="completeOpen = true">Tandai Lunas</AppButton>
                    <AppButton v-if="canWriteOff" variant="secondary" icon="delete_sweep" @click="writeOffOpen = true">Hapus Piutang</AppButton>
                    <AppButton v-if="canReschedule" variant="secondary" icon="event_repeat" @click="rescheduleOpen = true">Reschedule</AppButton>
                    <AppButton v-if="canReject" variant="danger" icon="block" @click="rejectOpen = true">Tidak Layak</AppButton>
                    <AppButton v-if="canRevert" variant="secondary" icon="undo" @click="revertOpen = true">Kembalikan ke Proposal</AppButton>
                </div>
            </AppCard>

            <div class="grid gap-6 lg:grid-cols-3">
                <AppCard class="lg:col-span-2">
                    <h2 class="font-semibold text-primary">Profil Peminjam</h2>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-on-surface-variant">Nama Lengkap</p>
                            <p class="font-semibold text-primary">{{ loan.member?.full_name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-widest text-on-surface-variant">Nomor Anggota</p>
                            <p class="font-semibold text-primary">{{ loan.member?.member_number ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-widest text-on-surface-variant">NIK</p>
                            <p class="font-semibold text-primary">{{ loan.member?.nik ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-widest text-on-surface-variant">Desa</p>
                            <p class="font-semibold text-primary">{{ loan.member?.village?.name ?? '—' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs uppercase tracking-widest text-on-surface-variant">Alamat</p>
                            <p class="text-primary">{{ loan.member?.address ?? '—' }}</p>
                        </div>
                    </div>
                </AppCard>

                <AppCard>
                    <h2 class="font-semibold text-primary">Ringkasan Pinjaman</h2>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><span class="text-on-surface-variant">Produk</span><span class="font-semibold">{{ loan.product?.name ?? '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-on-surface-variant">Plafon</span><span class="font-semibold tabular-nums">{{ fMoney(loan.principal_amount) }}</span></div>
                        <div class="flex justify-between"><span class="text-on-surface-variant">Sisa Pokok</span><span class="font-semibold tabular-nums">{{ fMoney(loan.principal_remaining) }}</span></div>
                        <div class="flex justify-between"><span class="text-on-surface-variant">Total Jasa Dibayar</span><span class="tabular-nums">{{ fMoney(loan.total_interest_paid) }}</span></div>
                        <div class="flex justify-between"><span class="text-on-surface-variant">Tenor</span><span class="font-semibold">{{ loan.term_months }} bulan</span></div>
                        <div class="flex justify-between"><span class="text-on-surface-variant">Sistem Angsuran</span><span class="font-semibold capitalize">{{ loan.principal_frequency }}</span></div>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-outline-variant">
                            <div class="h-full bg-primary" :style="{ width: loan.progress_percent + '%' }"></div>
                        </div>
                        <p class="text-xs text-on-surface-variant">{{ loan.paid_installments }} / {{ loan.total_installments }} angsuran ({{ loan.progress_percent }}%)</p>
                    </div>
                </AppCard>
            </div>

            <AppCard>
                <h2 class="font-semibold text-primary">Jadwal Angsuran</h2>
                <div class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            <tr>
                                <th class="py-3 px-4">#</th>
                                <th class="py-3 px-4">Komponen</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-right">Pokok</th>
                                <th class="py-3 px-4 text-right">Jasa</th>
                                <th class="py-3 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="i in loan.installments" :key="i.row_id">
                                <td class="py-2 px-4 font-semibold">{{ i.installment_number }}</td>
                                <td class="py-2 px-4 capitalize">{{ i.component }}</td>
                                <td class="py-2 px-4">{{ fDate(i.due_date) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ fNumber(i.principal_due) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ fNumber(i.interest_due) }}</td>
                                <td class="py-2 px-4">
                                    <AppBadge :variant="i.status === 'paid' ? 'success' : (i.status === 'overdue' ? 'danger' : 'warning')">{{ i.status }}</AppBadge>
                                </td>
                            </tr>
                            <tr v-if="!loan.installments?.length">
                                <td colspan="6" class="py-6 px-4 text-center text-on-surface-variant">Belum ada jadwal angsuran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <AppCard v-if="availableDocuments.length > 0">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-primary">Dokumen Cetak</h2>
                    <span class="text-xs text-on-surface-variant">{{ availableDocuments.length }} dokumen tersedia</span>
                </div>
                <p class="mt-1 text-xs text-on-surface-variant">
                    Dokumen akan terbuka di tab baru. Untuk tanda terima & bukti jaminan, pastikan data jaminan sudah diisi sebelum cetak.
                </p>
                <div class="mt-4 space-y-4">
                    <div v-for="(docs, stage) in documentsByStage" :key="stage">
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                            {{ docs[0]?.stageLabel }}
                        </h3>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <a
                                v-for="doc in docs"
                                :key="doc.key"
                                :href="docUrl(doc.key)"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 transition hover:bg-surface-container-low"
                            >
                                <span class="material-icons text-primary">{{ doc.icon }}</span>
                                <span class="flex-1 text-sm font-medium">{{ doc.label }}</span>
                                <span class="material-icons text-on-surface-variant text-base">open_in_new</span>
                            </a>
                        </div>
                    </div>
                </div>
            </AppCard>

            <AppCard>
                <h2 class="font-semibold text-primary">Riwayat Status</h2>
                <div class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
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
                                <td class="py-2 px-4">{{ fDateTime(h.changed_at) }}</td>
                                <td class="py-2 px-4">
                                    <span class="font-semibold">{{ h.from_status ?? '—' }}</span>
                                    <span class="mx-1 text-on-surface-variant">→</span>
                                    <span class="font-semibold text-primary">{{ h.to_status }}</span>
                                </td>
                                <td class="py-2 px-4 tabular-nums">{{ h.principal_amount !== null ? fNumber(h.principal_amount) : '—' }}</td>
                                <td class="py-2 px-4 text-on-surface-variant">{{ h.notes || '—' }}</td>
                                <td class="py-2 px-4">{{ h.changed_by_user_name || `#${h.changed_by_user_id}` }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>

        <!-- Modals -->
        <Teleport to="body">
            <div v-if="verifyOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="verifyOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Verifikasi Proposal</h3>
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
                            <textarea v-model="verifyForm.verification_notes" rows="3" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="verifyOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="check">Verifikasi</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="approveOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="approveOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Alokasikan Pinjaman</h3>
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
                            <textarea v-model="approveForm.allocation_notes" rows="2" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="approveOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="assignment_turned_in">Setujui</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="disburseOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="disburseOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Catat Pencairan</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitDisburse">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Cair</span>
                            <input v-model="disburseForm.disbursed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Rekening Sumber Dana</span>
                            <select v-model="disburseForm.disbursement_account_row_id" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                                <option value="">Pilih rekening…</option>
                                <option v-for="acc in disbursementAccounts" :key="acc.row_id" :value="acc.row_id">{{ acc.code }} — {{ acc.name }}</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Nomor SPK (opsional)</span>
                            <input v-model="disburseForm.spk_no" type="text" maxlength="80" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Waktu & Tempat Pencairan (opsional)</span>
                            <input v-model="disburseForm.disbursement_slot" type="text" maxlength="120" placeholder="mis. 09:00 WIB · Kantor BUMDes" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="disburseForm.disbursement_notes" rows="2" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="disburseOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="payments">Catat Pencairan</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="completeOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="completeOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Validasi Pelunasan</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitComplete">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Lunas</span>
                            <input v-model="completeForm.completed_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Catatan</span>
                            <textarea v-model="completeForm.notes" rows="2" maxlength="500" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="completeOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="task_alt">Tandai Lunas</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="writeOffOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="writeOffOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Hapus Piutang</h3>
                    <form class="mt-4 space-y-3" @submit.prevent="submitWriteOff">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Tanggal Penghapusan</span>
                            <input v-model="writeOffForm.written_off_at" type="date" required class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan</span>
                            <textarea v-model="writeOffForm.reason" rows="3" required maxlength="500" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="writeOffOpen = false">Batal</AppButton>
                            <AppButton variant="danger" type="submit" icon="delete_sweep">Hapus Piutang</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="rescheduleOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="rescheduleOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Reschedule Pinjaman</h3>
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
                            <textarea v-model="rescheduleForm.notes" rows="2" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="rescheduleOpen = false">Batal</AppButton>
                            <AppButton variant="primary" type="submit" icon="event_repeat">Reschedule</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="rejectOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="rejectOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Tidak Layak</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">Pinjaman akan ditandai sebagai Tidak Layak dan tidak dapat dicairkan.</p>
                    <form class="mt-4 space-y-3" @submit.prevent="submitReject">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase tracking-widest text-on-surface-variant">Alasan Penolakan</span>
                            <textarea v-model="rejectForm.notes" rows="3" maxlength="5000" class="mt-1 h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest px-3.5 text-sm transition focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"></textarea>
                        </label>
                        <div class="flex justify-end gap-2 pt-2">
                            <AppButton variant="secondary" type="button" @click="rejectOpen = false">Batal</AppButton>
                            <AppButton variant="danger" type="submit" icon="block">Tolak</AppButton>
                        </div>
                    </form>
                </div>
            </div>

            <div v-if="revertOpen" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="revertOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-surface p-6 shadow-xl">
                    <h3 class="text-lg font-bold text-primary">Kembalikan ke Proposal</h3>
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
