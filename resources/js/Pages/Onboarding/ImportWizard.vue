<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppCurrencyInput from '../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppInput from '../../Components/AppInput.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppTabs from '../../Components/AppTabs.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import { useMoney } from '../../composables/useMoney';

const props = defineProps({
    tenantRowId: { type: Number, required: true },
    accounts: { type: Array, required: true },
    existingOpening: { type: Object, default: null },
    manualOpeningsByYear: { type: Object, default: () => ({}) },
    currentFiscalYear: { type: Number, default: () => new Date().getFullYear() },
});

const baseUrl = computed(() => `/admin/tenants/${props.tenantRowId}`);

const { money: formatMoney } = useMoney();
const activeTab = ref('balances');

const wizardTabs = [
    { key: 'balances', label: '1. Saldo Awal Keuangan (Neraca)' },
    { key: 'masterdata', label: '2. Impor Anggota & Kelompok' },
    { key: 'loans', label: '3. Impor Pinjaman Aktif & Angsuran' },
    { key: 'templates', label: '4. Template File Excel/CSV' },
    { key: 'manual-opening', label: '5. Saldo Awal Manual per Tahun' },
    { key: 'aggregate-journal', label: '6. Jurnal Agregat Mid-Year' },
];

// ============================================================
// TAB 1: Form Saldo Awal Keuangan (existing, post jurnal)
// ============================================================

const balanceLines = ref(
    props.accounts.map(acc => {
        const line = props.existingOpening?.lines?.find(l => l.account_row_id === acc.row_id);
        return {
            account_row_id: acc.row_id,
            code: acc.code,
            name: acc.name,
            account_type: acc.account_type,
            debit: line ? line.debit : 0,
            credit: line ? line.credit : 0,
        };
    })
);

const asOfDate = ref(props.existingOpening?.as_of_date || new Date().toISOString().split('T')[0]);

const balanceForm = useForm({
    as_of_date: asOfDate.value,
    lines: [],
});

const totalDebit = computed(() => {
    return balanceLines.value.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0);
});

const totalCredit = computed(() => {
    return balanceLines.value.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0);
});

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0;
});

const submitOpeningBalances = () => {
    balanceForm.as_of_date = asOfDate.value;
    balanceForm.lines = balanceLines.value
        .filter(l => (parseFloat(l.debit) || 0) > 0 || (parseFloat(l.credit) || 0) > 0)
        .map(l => ({
            account_row_id: l.account_row_id,
            debit: parseFloat(l.debit) || 0,
            credit: parseFloat(l.credit) || 0,
        }));

    balanceForm.post(`${baseUrl.value}/onboarding/opening-balances`, {
        preserveScroll: true,
    });
};

// ============================================================
// TAB 5: Saldo Awal per Tahun (manual, account_opening_balances)
// ============================================================

const manualFiscalYear = ref(props.currentFiscalYear);
const manualLines = ref(
    props.accounts.map(acc => ({
        account_row_id: acc.row_id,
        code: acc.code,
        name: acc.name,
        account_type: acc.account_type,
        debit: 0,
        credit: 0,
    }))
);

// Pre-fill dari existing manual opening untuk fiscal_year aktif.
function loadManualOpeningForYear(year) {
    const existing = props.manualOpeningsByYear[year] || [];
    manualLines.value = props.accounts.map(acc => {
        const found = existing.find(o => Number(o.account_row_id) === Number(acc.row_id));
        return {
            account_row_id: acc.row_id,
            code: acc.code,
            name: acc.name,
            account_type: acc.account_type,
            debit: found ? found.debit : 0,
            credit: found ? found.credit : 0,
        };
    });
}
loadManualOpeningForYear(manualFiscalYear.value);

watch(manualFiscalYear, (newYear) => {
    loadManualOpeningForYear(newYear);
});

const manualTotalDebit = computed(() =>
    manualLines.value.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0)
);
const manualTotalCredit = computed(() =>
    manualLines.value.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0)
);
const manualIsBalanced = computed(() =>
    Math.abs(manualTotalDebit.value - manualTotalCredit.value) < 0.01 && manualTotalDebit.value > 0
);
const manualHasExisting = computed(() => {
    const existing = props.manualOpeningsByYear[manualFiscalYear.value] || [];
    return existing.some(o => Number(o.debit) > 0 || Number(o.credit) > 0);
});

const manualOpeningForm = useForm({
    fiscal_year: props.currentFiscalYear,
    lines: [],
});

const submitManualOpening = () => {
    manualOpeningForm.fiscal_year = manualFiscalYear.value;
    manualOpeningForm.lines = manualLines.value
        .filter(l => (parseFloat(l.debit) || 0) > 0 || (parseFloat(l.credit) || 0) > 0)
        .map(l => ({
            account_row_id: Number(l.account_row_id),
            debit: parseFloat(l.debit) || 0,
            credit: parseFloat(l.credit) || 0,
        }));

    manualOpeningForm.post(`${baseUrl.value}/onboarding/opening-balances/manual`, {
        preserveScroll: true,
    });
};

// ============================================================
// TAB 6: Jurnal Agregat 5-Bulanan (multi-line)
// ============================================================

function blankAggregateLine() {
    return { account_row_id: '', code: '', name: '', debit: 0, credit: 0, description: '' };
}

const aggregateForm = useForm({
    transaction_date: '',
    description: 'Penyesuaian saldo awal migrasi (jurnal agregat Jan-Mei)',
    lines: [blankAggregateLine(), blankAggregateLine()],
});

function addAggregateLine() {
    aggregateForm.lines.push(blankAggregateLine());
}
function removeAggregateLine(idx) {
    if (aggregateForm.lines.length <= 2) return;
    aggregateForm.lines.splice(idx, 1);
}
function pickAccountForAggregateLine(idx, option) {
    const line = aggregateForm.lines[idx];
    if (!option) return;
    const acc = props.accounts.find(a => Number(a.row_id) === Number(option.value ?? option.row_id ?? option));
    if (!acc) return;
    line.account_row_id = acc.row_id;
    line.code = acc.code;
    line.name = acc.name;
}
function aggregateAccountOptions() {
    return props.accounts.map(a => ({ value: a.row_id, label: `${a.code} — ${a.name}` }));
}

const aggregateTotalDebit = computed(() =>
    aggregateForm.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0)
);
const aggregateTotalCredit = computed(() =>
    aggregateForm.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0)
);
const aggregateIsBalanced = computed(() =>
    Math.abs(aggregateTotalDebit.value - aggregateTotalCredit.value) < 0.01 && aggregateTotalDebit.value > 0
);
const aggregateIsValid = computed(() => {
    if (!aggregateForm.transaction_date) return false;
    if (aggregateForm.description.length < 5) return false;
    if (aggregateForm.lines.length < 2) return false;
    if (aggregateForm.lines.some(l => !l.account_row_id)) return false;
    return aggregateIsBalanced.value;
});

const submitAggregateJournal = () => {
    aggregateForm.post(`${baseUrl.value}/onboarding/aggregate-journal`, {
        preserveScroll: true,
    });
};

// ============================================================
// Upload Forms (existing, Tab 2/3)
// ============================================================

const memberFileForm = useForm({ file: null });
const groupFileForm = useForm({ file: null });
const loanFileForm = useForm({ file: null });

const uploadMembers = () => {
    if (!memberFileForm.file) return;
    memberFileForm.post('/membership/members/import', {
        preserveScroll: true,
        onSuccess: () => memberFileForm.reset(),
    });
};

const uploadGroups = () => {
    if (!groupFileForm.file) return;
    groupFileForm.post('/membership/groups/import', {
        preserveScroll: true,
        onSuccess: () => groupFileForm.reset(),
    });
};

const uploadLoans = () => {
    if (!loanFileForm.file) return;
    loanFileForm.post(`${baseUrl.value}/onboarding/active-loans`, {
        preserveScroll: true,
        onSuccess: () => loanFileForm.reset(),
    });
};
</script>

<template>
    <Head title="Migrasi & Saldo Awal Tenant Baru" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header Banner -->
            <header class="rounded-2xl bg-gradient-to-r from-primary to-primary-deep p-6 text-on-primary shadow-xl">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <span class="inline-block rounded-full bg-primary-container px-3 py-1 text-xs font-semibold uppercase tracking-wider text-on-primary-container">
                            Pusat Onboarding Tenant Baru
                        </span>
                        <h1 class="mt-2 text-2xl font-bold">Migrasi Data & Saldo Awal Mandiri</h1>
                        <p class="mt-1 max-w-2xl text-sm text-on-primary-container">
                            Impor neraca keuangan awal, daftar kelompok, keanggotaan, dan portofolio pinjaman aktif beserta akumulasi angsurannya.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <div class="border-b border-outline-variant">
                <AppTabs
                    v-model="activeTab"
                    :items="wizardTabs"
                    variant="underline"
                    aria-label="Langkah wizard onboarding"
                />
            </div>

            <!-- TAB 1: Saldo Awal Keuangan -->
            <div v-if="activeTab === 'balances'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Form Neraca Saldo Awal Akun COA</h2>
                                <p class="text-xs text-on-surface-variant">Masukkan nilai saldo pada tanggal awal konversi operasional aplikasi.</p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <label class="text-xs font-semibold text-on-surface">Tanggal Saldo Awal:</label>
                                <AppDatePicker
                                    v-model="asOfDate"
                                    label="Tanggal Saldo Awal"
                                    hide-label
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Total Live Balance Tracker Banner -->
                    <div class="mb-6 rounded-xl border p-4 shadow-sm" :class="isBalanced ? 'bg-secondary-container border-secondary' : 'bg-tertiary-fixed border-tertiary'">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="text-2xl">{{ isBalanced ? '?' : '??' }}</div>
                                <div>
                                    <h3 class="text-sm font-bold text-primary">
                                        Status Saldo Awal: {{ isBalanced ? 'SEIMBANG (BALANCED)' : 'BELUM SEIMBANG' }}
                                    </h3>
                                    <p class="text-xs text-on-surface-variant">
                                        Total Debit: <strong>Rp {{ formatMoney(totalDebit) }}</strong> | Total Kredit: <strong>Rp {{ formatMoney(totalCredit) }}</strong>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <AppBadge :tone="isBalanced ? 'success' : 'warning'">
                                    Selisih: Rp {{ formatMoney(Math.abs(totalDebit - totalCredit)) }}
                                </AppBadge>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Accounts Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-outline-variant">
                            <thead class="bg-surface-container-low">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface">Kode Akun</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface">Nama Akun (COA)</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface">Kategori</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-on-surface">Saldo Debit (Rp)</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-on-surface">Saldo Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr v-for="line in balanceLines" :key="line.account_row_id" class="hover:bg-surface-container-low">
                                    <td class="px-4 py-2.5 text-xs font-mono font-bold text-primary">{{ line.code }}</td>
                                    <td class="px-4 py-2.5 text-xs font-medium text-on-surface">{{ line.name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-on-surface-variant uppercase">{{ line.account_type }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.debit"
                                            label="Debit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-36"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.credit"
                                            label="Kredit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-36"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-surface-container font-bold text-xs">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-primary">TOTAL SALDO AWAL:</td>
                                    <td class="px-4 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(totalDebit) }}</td>
                                    <td class="px-4 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(totalCredit) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-end pt-6">
                        <AppButton
                            variant="primary"
                            @click="submitOpeningBalances"
                            :disabled="!isBalanced || balanceForm.processing"
                        >
                            <span v-if="balanceForm.processing">Memproses Saldo...</span>
                            <span v-else>?? Simpan & Posting Saldo Awal Keuangan</span>
                        </AppButton>
                    </div>
                </AppCard>
            </div>

            <!-- TAB 2: Impor Anggota & Kelompok -->
            <div v-if="activeTab === 'masterdata'" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Impor Anggota -->
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-primary">Impor Massal Anggota</h2>
                    </template>
                    <form @submit.prevent="uploadMembers" class="space-y-4">
                        <p class="text-xs text-on-surface-variant">
                            Upload file CSV berisi daftar anggota lengkap (NIK, Nama, Jenis Kelamin, Alamat, Desa, Phone).
                        </p>
                        <div>
                            <input
                                type="file"
                                accept=".csv"
                                @change="e => memberFileForm.file = e.target.files[0]"
                                class="block w-full text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-container file:text-primary hover:file:bg-primary-fixed"
                            />
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <a :href="`${baseUrl}/onboarding/templates/anggota`" class="text-xs text-secondary hover:underline font-semibold">
                                ?? Download Template CSV Anggota
                            </a>
                            <AppButton type="submit" variant="primary" size="sm" :disabled="!memberFileForm.file || memberFileForm.processing">
                                Upload & Impor Anggota
                            </AppButton>
                        </div>
                    </form>
                </AppCard>

                <!-- Impor Kelompok -->
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-primary">Impor Massal Kelompok</h2>
                    </template>
                    <form @submit.prevent="uploadGroups" class="space-y-4">
                        <p class="text-xs text-on-surface-variant">
                            Upload file CSV daftar kelompok usaha/masyarakat (Nama Kelompok, Desa, Alamat, Telepon).
                        </p>
                        <div>
                            <input
                                type="file"
                                accept=".csv"
                                @change="e => groupFileForm.file = e.target.files[0]"
                                class="block w-full text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-container file:text-primary hover:file:bg-primary-fixed"
                            />
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <a :href="`${baseUrl}/onboarding/templates/kelompok`" class="text-xs text-secondary hover:underline font-semibold">
                                ?? Download Template CSV Kelompok
                            </a>
                            <AppButton type="submit" variant="primary" size="sm" :disabled="!groupFileForm.file || groupFileForm.processing">
                                Upload & Impor Kelompok
                            </AppButton>
                        </div>
                    </form>
                </AppCard>
            </div>

            <!-- TAB 3: Impor Pinjaman Aktif -->
            <div v-if="activeTab === 'loans'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-primary">Impor Portofolio Pinjaman Aktif & Akumulasi Realisasi Angsuran</h2>
                    </template>
                    <div class="space-y-4 text-xs text-on-surface-variant">
                        <p>
                            Fitur ini digunakan untuk mengimpor pinjaman berjalan beserta <strong>akumulasi pokok & bunga yang sudah terbayar</strong> sampai hari ini. Sistem akan secara otomatis mengalokasikan angsuran FIFO pada jadwal bulanan dan menghitung sisa tagihan berjalan.
                        </p>

                        <div class="rounded-lg bg-secondary-container p-4 border border-secondary text-on-secondary-container">
                            <h4 class="font-bold text-sm">?? Alokasi Otomatis Angsuran Terbayar:</h4>
                            <ul class="list-disc pl-5 mt-1 space-y-1">
                                <li>Nilai <code class="font-mono font-bold">akumulasi_pokok_dibayar</code> dan <code class="font-mono font-bold">akumulasi_bunga_dibayar</code> diisi total akumulasi pembayaran hingga saat migrasi.</li>
                                <li>Sistem membagi jadwal angsuran 1..N bulan dan mengisi status <span class="font-bold text-secondary">Lunas (Paid)</span> untuk bulan-bulan yang sudah tercukupi.</li>
                                <li>Sisa pokok & sisa bunga yang belum terbayar akan otomatis masuk ke tagihan berjalan.</li>
                            </ul>
                        </div>

                        <form @submit.prevent="uploadLoans" class="space-y-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Pilih File CSV Pinjaman Aktif & Angsuran:</label>
                                <input
                                    type="file"
                                    accept=".csv"
                                    @change="e => loanFileForm.file = e.target.files[0]"
                                    class="block w-full text-xs text-on-surface-variant file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-container file:text-primary hover:file:bg-primary-fixed"
                                />
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <a :href="`${baseUrl}/onboarding/templates/pinjaman-aktif`" class="text-xs text-secondary hover:underline font-semibold">
                                    ?? Download Format Template CSV Pinjaman Aktif (.csv)
                                </a>
                                <AppButton type="submit" variant="primary" size="sm" :disabled="!loanFileForm.file || loanFileForm.processing">
                                    Upload & Impor Pinjaman Aktif
                                </AppButton>
                            </div>
                        </form>
                    </div>
                </AppCard>
            </div>

            <!-- TAB 4: Download Seluruh Template -->
            <div v-if="activeTab === 'templates'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <AppCard class="hover:border-primary transition-colors">
                    <h3 class="font-bold text-sm text-primary">1. Template Saldo Awal</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Format neraca saldo awal akun COA debit/kredit.</p>
                    <a :href="`${baseUrl}/onboarding/templates/saldo-awal`" class="mt-4 inline-block text-xs font-semibold text-secondary">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-primary transition-colors">
                    <h3 class="font-bold text-sm text-primary">2. Template Anggota</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Format data NIK, Nama, Alamat, Desa, Phone.</p>
                    <a :href="`${baseUrl}/onboarding/templates/anggota`" class="mt-4 inline-block text-xs font-semibold text-secondary">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-primary transition-colors">
                    <h3 class="font-bold text-sm text-primary">3. Template Kelompok</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Format nama kelompok, alamat, desa, pengurus.</p>
                    <a :href="`${baseUrl}/onboarding/templates/kelompok`" class="mt-4 inline-block text-xs font-semibold text-secondary">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-primary transition-colors">
                    <h3 class="font-bold text-sm text-primary">4. Template Pinjaman Aktif & Angsuran</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Format portofolio pinjaman berjalan & akumulasi angsuran.</p>
                    <a :href="`${baseUrl}/onboarding/templates/pinjaman-aktif`" class="mt-4 inline-block text-xs font-semibold text-secondary">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-primary transition-colors">
                    <h3 class="font-bold text-sm text-primary">5. Template Aset Tetap Awal</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Format barang inventaris, harga perolehan, depresiasi.</p>
                    <a :href="`${baseUrl}/onboarding/templates/aset-tetap`" class="mt-4 inline-block text-xs font-semibold text-secondary">?? Download CSV Template</a>
                </AppCard>
            </div>

            <!-- TAB 5: Saldo Awal Manual per Tahun -->
            <div v-if="activeTab === 'manual-opening'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-primary">Saldo Awal per Tahun Fiskal (Manual)</h2>
                                <p class="text-xs text-on-surface-variant max-w-2xl">
                                    Tulis langsung ke tabel <code class="font-mono">account_opening_balances</code> per tahun fiskal. Cocok untuk tenant baru yang join di tengah tahun (mis. tahu saldo 1 Jan lalu backfill jurnal Jan-Mei terpisah di tab berikutnya).
                                </p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <label class="text-xs font-semibold text-on-surface">Tahun Fiskal:</label>
                                <AppInput
                                    v-model="manualFiscalYear"
                                    type="number"
                                    label="Tahun Fiskal"
                                    hide-label
                                    min="2000"
                                    max="2100"
                                    class="w-24"
                                />
                            </div>
                        </div>
                    </template>

                    <div v-if="manualHasExisting" class="mb-6 rounded-xl border p-4 shadow-sm bg-tertiary-fixed border-tertiary">
                        <div class="flex items-center space-x-3">
                            <div class="text-xl">⚠️</div>
                            <div>
                                <h3 class="text-sm font-bold text-primary">
                                    Saldo Awal Tahun {{ manualFiscalYear }} Sudah Ada
                                </h3>
                                <p class="text-xs text-on-surface-variant">
                                    Menyimpan akan memperbarui nilai existing (preserve source). Tidak menimpa source='migration' (legacy).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 rounded-xl border p-4 shadow-sm" :class="manualIsBalanced ? 'bg-secondary-container border-secondary' : 'bg-tertiary-fixed border-tertiary'">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="text-2xl">{{ manualIsBalanced ? '✅' : '⚠️' }}</div>
                                <div>
                                    <h3 class="text-sm font-bold text-primary">
                                        Status: {{ manualIsBalanced ? 'SEIMBANG' : 'BELUM SEIMBANG' }}
                                    </h3>
                                    <p class="text-xs text-on-surface-variant">
                                        Total Debit: <strong>Rp {{ formatMoney(manualTotalDebit) }}</strong> | Total Kredit: <strong>Rp {{ formatMoney(manualTotalCredit) }}</strong>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <AppBadge :tone="manualIsBalanced ? 'success' : 'warning'">
                                    Selisih: Rp {{ formatMoney(Math.abs(manualTotalDebit - manualTotalCredit)) }}
                                </AppBadge>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-outline-variant">
                            <thead class="bg-surface-container-low">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface">Kode Akun</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-on-surface">Nama Akun</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-on-surface">Saldo Debit (Rp)</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-on-surface">Saldo Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr v-for="line in manualLines" :key="line.account_row_id" class="hover:bg-surface-container-low">
                                    <td class="px-4 py-2.5 text-xs font-mono font-bold text-primary">{{ line.code }}</td>
                                    <td class="px-4 py-2.5 text-xs font-medium text-on-surface">{{ line.name }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.debit"
                                            label="Debit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-36"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.credit"
                                            label="Kredit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-36"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-surface-container font-bold text-xs">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-right text-primary">TOTAL TAHUN {{ manualFiscalYear }}:</td>
                                    <td class="px-4 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(manualTotalDebit) }}</td>
                                    <td class="px-4 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(manualTotalCredit) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-end pt-6">
                        <AppButton
                            variant="primary"
                            @click="submitManualOpening"
                            :disabled="!manualIsBalanced || manualOpeningForm.processing"
                        >
                            <span v-if="manualOpeningForm.processing">Menyimpan...</span>
                            <span v-else>💾 Simpan Saldo Awal Tahun {{ manualFiscalYear }}</span>
                        </AppButton>
                    </div>
                </AppCard>
            </div>

            <!-- TAB 6: Jurnal Agregat Mid-Year -->
            <div v-if="activeTab === 'aggregate-journal'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <div>
                            <h2 class="text-lg font-semibold text-primary">Jurnal Agregat Mid-Year (Backfill Banyak Bulan)</h2>
                            <p class="text-xs text-on-surface-variant max-w-3xl">
                                Posting 1 jurnal <strong>multi-line</strong> untuk backfill agregat transaksi antar tanggal opening balance dan tanggal join (mis. Jan-Mei). Saldo akhir bulan = Opening + Jurnal Agregat + jurnal reguler bulanan.
                            </p>
                        </div>
                    </template>

                    <div class="rounded-xl border p-4 bg-tertiary-fixed border-tertiary mb-6">
                        <div class="flex items-start space-x-3">
                            <div class="text-xl">📌</div>
                            <div class="text-xs text-on-surface">
                                <p class="font-bold">Cara Pakai:</p>
                                <ol class="list-decimal pl-5 mt-1 space-y-1">
                                    <li>Set <code>transaction_date</code> = tanggal join (mis. 2026-06-01).</li>
                                    <li>Tambah baris untuk setiap akun yang berubah (Kas debit, Piutang/Modal/Pendapatan credit, dll).</li>
                                    <li>Total Debit HARUS = Total Kredit.</li>
                                    <li>Submit — jurnal akan di-post lewat <code>JournalPostingService</code> (validasi period open otomatis).</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
                        <AppInput
                            v-model="aggregateForm.transaction_date"
                            type="date"
                            label="Tanggal Jurnal"
                            required
                        />
                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Keterangan</label>
                            <AppInput
                                v-model="aggregateForm.description"
                                label="Keterangan"
                                hide-label
                                placeholder="Opsional"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-outline-variant">
                            <thead class="bg-surface-container-low">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-on-surface w-1/3">Akun</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-on-surface">Debit</th>
                                    <th class="px-3 py-3 text-right text-xs font-semibold text-on-surface">Kredit</th>
                                    <th class="px-3 py-3 text-left text-xs font-semibold text-on-surface">Keterangan Baris</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                <tr v-for="(line, idx) in aggregateForm.lines" :key="idx">
                                    <td class="px-3 py-2">
                                        <SmartSelect
                                            :model-value="line.account_row_id ? Number(line.account_row_id) : null"
                                            :options="aggregateAccountOptions()"
                                            placeholder="Pilih akun..."
                                            @update:model-value="(val) => pickAccountForAggregateLine(idx, val)"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.debit"
                                            label="Debit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-32"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <AppCurrencyInput
                                            v-model="line.credit"
                                            label="Kredit"
                                            hide-label
                                            :min="0"
                                            :step="0.01"
                                            class="w-32"
                                        />
                                    </td>
                                    <td class="px-3 py-2">
                                        <AppInput
                                            v-model="line.description"
                                            label="Keterangan baris"
                                            hide-label
                                            placeholder="Opsional"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <AppIconButton
                                            name="delete"
                                            size="sm"
                                            tone="danger"
                                            tooltip="Hapus baris"
                                            :disabled="aggregateForm.lines.length <= 2"
                                            @click="removeAggregateLine(idx)"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-surface-container font-bold text-xs">
                                <tr>
                                    <td class="px-3 py-3 text-right text-primary">TOTAL:</td>
                                    <td class="px-3 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(aggregateTotalDebit) }}</td>
                                    <td class="px-3 py-3 text-right text-secondary font-mono">Rp {{ formatMoney(aggregateTotalCredit) }}</td>
                                    <td colspan="2" class="px-3 py-3 text-right">
                                        <AppBadge :tone="aggregateIsBalanced ? 'success' : 'warning'">
                                            Selisih: Rp {{ formatMoney(Math.abs(aggregateTotalDebit - aggregateTotalCredit)) }}
                                        </AppBadge>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-between items-center pt-6">
                        <AppButton type="button" variant="secondary" size="sm" @click="addAggregateLine">
                            + Tambah Baris
                        </AppButton>
                        <AppButton
                            variant="primary"
                            @click="submitAggregateJournal"
                            :disabled="!aggregateIsValid || aggregateForm.processing"
                        >
                            <span v-if="aggregateForm.processing">Memproses...</span>
                            <span v-else>📤 Posting Jurnal Agregat</span>
                        </AppButton>
                    </div>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


