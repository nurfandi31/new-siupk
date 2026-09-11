<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, required: true },
    groups: { type: Array, required: true },
    committee_members: { type: Array, required: true },
});

const path = '/lending/loans';
const today = (() => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
})();

const productOptions = computed(() => props.products.map((product) => ({
    value: product.row_id,
    label: `${product.name} · ${product.code}`,
})));

const installmentMethodOptions = [
    { value: 'flat', label: 'Flat' },
    { value: 'annuity', label: 'Anuitas' },
    { value: 'effective', label: 'Efektif' },
];

const principalFrequencyOptions = [
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

const interestFrequencyOptions = [
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

const frequencyMultiplier = {
    weekly: 4.3333,
    biweekly: 2.1667,
    monthly: 1,
    bimonthly: 0.5,
    quarterly: 0.3333,
    every_4_months: 0.25,
    every_5_months: 0.2,
    every_6_months: 1 / 6,
    every_7_months: 1 / 7,
    every_8_months: 0.125,
    every_9_months: 1 / 9,
    every_10_months: 0.1,
    every_11_months: 1 / 11,
    every_12_months: 1 / 12,
    every_24_months: 1 / 24,
    every_36_months: 1 / 36,
};

const selectedGroupId = ref('');
const selectedProductId = ref('');
const beneficiaryCandidateId = ref('');
const beneficiarySearch = ref('');
const beneficiaryLoading = ref(false);
const beneficiarySearchEmpty = ref(false);
const beneficiaryOptions = ref([]);
const extraBeneficiaries = ref([]);
let beneficiarySearchController = null;

const form = useForm({
    loan_product_id: '',
    group_id: '',
    proposed_at: today,
    principal_amount: '',
    service_rate_total: '',
    term_months: '',
    installment_method: 'flat',
    principal_frequency: 'monthly',
    interest_frequency: 'monthly',
    principal_grace_months: 0,
    interest_grace_months: 0,
    chair_id: '',
    secretary_id: '',
    treasurer_id: '',
    beneficiary_ids: [],
    beneficiary_amounts: {},
});

function currency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0);
}

const beneficiaryTotal = computed(() => Object.values(form.beneficiary_amounts ?? {}).reduce((sum, value) => sum + Number(value || 0), 0));

const selectedGroup = computed(() => props.groups.find((group) => String(group.value) === String(selectedGroupId.value)) || null);
const selectedProduct = computed(() => props.products.find((product) => String(product.row_id) === String(selectedProductId.value)) || null);
const memberOptions = computed(() => {
    const existing = selectedGroup.value?.members || [];
    const existingIds = new Set(existing.map((m) => String(m.value)));
    const extras = extraBeneficiaries.value.filter((m) => !existingIds.has(String(m.value)));
    return [...existing, ...extras];
});
const beneficiaryCandidateOptions = computed(() => {
    const exclude = new Set(memberOptions.value.map((m) => String(m.value)));
    return beneficiaryOptions.value.filter((option) => !exclude.has(String(option.value)));
});

const committeeOption = computed(() => props.committee_members);

const periodPreview = computed(() => {
    const months = Number(form.term_months);
    const total = Number(form.service_rate_total);
    if (!months || !total) return null;
    const principalStep = Math.round(1 / (frequencyMultiplier[form.principal_frequency] || 1));
    const interestStep = Math.round(1 / (frequencyMultiplier[form.interest_frequency] || 1));
    const principalFirst = principalStep + Number(form.principal_grace_months || 0);
    const interestFirst = interestStep + Number(form.interest_grace_months || 0);
    const principalPeriods = form.principal_frequency === 'at_maturity'
        ? 1
        : Math.max(1, Math.floor((months - principalFirst) / principalStep) + 1);
    const interestPeriods = Math.max(1, Math.floor((months - interestFirst) / interestStep) + 1);
    return {
        principal: { periods: principalPeriods, perPeriod: (total / principalPeriods).toFixed(3) },
        interest: { periods: interestPeriods, perPeriod: (total / interestPeriods).toFixed(3) },
    };
});

const rateFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const formatRate = (value) => rateFormatter.format(Number(value ?? 0));

const fillDefaults = () => {
    const product = selectedProduct.value;
    if (!product) return;
    const months = Number(form.term_months);
    const defaultRate = Number(product.default_interest_rate || 0);
    if (!form.term_months && product.default_term_months) form.term_months = String(product.default_term_months);
    if (defaultRate && months) {
        const periods = form.principal_frequency === 'at_maturity'
            ? 1
            : Math.round(months * (frequencyMultiplier[form.principal_frequency] || 0));
        if (!form.service_rate_total) form.service_rate_total = (defaultRate * periods).toFixed(2);
    }
};

watch(selectedGroupId, (value) => {
    form.group_id = value;
    extraBeneficiaries.value = [];
    form.beneficiary_ids = [];
    form.beneficiary_amounts = {};
    const group = selectedGroup.value;
    if (group) {
        form.beneficiary_ids = (group.members || []).map((member) => String(member.value));
        const perBene = Math.round((Number(form.principal_amount) || 0) / Math.max(1, form.beneficiary_ids.length));
        const amounts = {};
        form.beneficiary_ids.forEach((id) => { amounts[id] = perBene || 0; });
        form.beneficiary_amounts = amounts;
    }
    form.chair_id = '';
    form.secretary_id = '';
    form.treasurer_id = '';
});
watch(selectedProductId, (value) => {
    form.loan_product_id = value;
    fillDefaults();
});
watch([() => form.term_months, () => form.principal_frequency], () => fillDefaults());
watch(memberOptions, () => {
    if (selectedGroupId.value) searchBeneficiaries('');
});

function submit() {
    form.post(path);
}

async function searchBeneficiaries(search = '') {
    beneficiarySearchController?.abort();
    const query = search.trim();
    const controller = new AbortController();
    beneficiarySearchController = controller;
    beneficiaryLoading.value = true;
    beneficiarySearchEmpty.value = false;
    const exclude = memberOptions.value.map((member) => member.value).join(',');
    const groupParam = selectedGroupId.value ? `&group_id=${encodeURIComponent(selectedGroupId.value)}` : '';
    try {
        const response = await fetch(`/lending/loans/beneficiary-options?search=${encodeURIComponent(query)}&exclude=${encodeURIComponent(exclude)}${groupParam}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('Data anggota gagal dimuat.');
        const payload = await response.json();
        beneficiaryOptions.value = payload.data;
        beneficiarySearchEmpty.value = Boolean(query) && payload.data.length === 0;
    } catch (error) {
        if (error.name !== 'AbortError') beneficiaryOptions.value = [];
    } finally {
        if (beneficiarySearchController === controller) { beneficiaryLoading.value = false; beneficiarySearchController = null; }
    }
}

function updateBeneficiarySearch(search) {
    beneficiarySearch.value = search;
    beneficiarySearchEmpty.value = false;
    searchBeneficiaries(search);
}

function addBeneficiary() {
    const candidate = beneficiaryOptions.value.find((item) => String(item.value) === String(beneficiaryCandidateId.value));
    if (!candidate) return;
    if (!extraBeneficiaries.value.some((m) => String(m.value) === String(candidate.value))) {
        extraBeneficiaries.value.push(candidate);
    }
    const id = String(candidate.value);
    if (!form.beneficiary_ids.some((existing) => String(existing) === id)) {
        form.beneficiary_ids.push(id);
    }
    if (!(id in form.beneficiary_amounts)) {
        const principal = Number(form.principal_amount) || 0;
        const count = Math.max(1, form.beneficiary_ids.length);
        form.beneficiary_amounts = { ...form.beneficiary_amounts, [id]: Math.round(principal / count) };
    }
    beneficiaryCandidateId.value = '';
    beneficiarySearch.value = '';
    beneficiarySearchEmpty.value = false;
    searchBeneficiaries('');
}
</script>

<template>
    <Head title="Register Proposal Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Register Proposal Pinjaman</h1>
                <p class="mt-1 text-on-surface-variant">Daftarkan proposal pinjaman baru untuk kelompok. Pemanfaat adalah anggota terdaftar pada kelompok tersebut.</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">Produk & Kelompok</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <SmartSelect v-model="selectedProductId" label="Produk Pinjaman" :options="productOptions" placeholder="Pilih produk (SPP/UEP/PL)" required searchable :error="form.errors.loan_product_id" />
                            <SmartSelect v-model="selectedGroupId" label="Kelompok" :options="groups.map((g) => ({ value: g.value, label: g.label }))" placeholder="Pilih kelompok" required searchable :error="form.errors.group_id" />
                        </div>
                    </section>

                    <section v-if="selectedProduct" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm text-primary">
                        <p class="font-semibold">{{ selectedProduct.name }} ({{ selectedProduct.code }})</p>
                        <p class="mt-1 text-on-surface-variant">Default: suku jasa {{ formatRate(selectedProduct.default_interest_rate) }}% per periode · Tenor {{ selectedProduct.default_term_months }} bulan.</p>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Detail Pengajuan</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <AppDatePicker v-model="form.proposed_at" label="Tanggal Pengajuan" icon="event" placeholder="Pilih tanggal" :max="today" required :error="form.errors.proposed_at" />
                            <AppCurrencyInput v-model="form.principal_amount" label="Plafon Pinjaman" icon="payments" :min="0" required :error="form.errors.principal_amount" />
                            <AppInput v-model="form.term_months" label="Jangka Waktu (bulan)" icon="schedule" type="number" inputmode="numeric" min="1" max="120" required :error="form.errors.term_months" />
                            <AppInput
                                v-model="form.service_rate_total"
                                label="Prosentase Jasa Total"
                                icon="percent"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                required
                                :error="form.errors.service_rate_total"
                                tooltip="Total jasa sepanjang pinjaman. Contoh: 1,5%/bulan × 12 bulan = 18"
                            />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <SmartSelect v-model="form.installment_method" label="Metode Hitung Jasa" :options="installmentMethodOptions" required :error="form.errors.installment_method" />
                            <SmartSelect v-model="form.principal_frequency" label="Angsuran Pokok" :options="principalFrequencyOptions" required :error="form.errors.principal_frequency" />
                            <SmartSelect v-model="form.interest_frequency" label="Angsuran Jasa" :options="interestFrequencyOptions" required :error="form.errors.interest_frequency" />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <SmartSelect v-model="form.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" required :error="form.errors.principal_grace_months" />
                            <SmartSelect v-model="form.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" required :error="form.errors.interest_grace_months" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Struktur Kelompok (Snapshot)</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Pengurus saat proposal didaftarkan. Disimpan sebagai snapshot.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <div>
                                <SmartSelect v-model="form.chair_id" label="Ketua" :options="committeeOption" placeholder="Cari anggota aktif" searchable required :error="form.errors.chair_id" />
                                <p v-if="selectedGroup?.chair?.name" class="mt-1 text-xs text-on-surface-variant">Pengurus saat ini: {{ selectedGroup.chair.name }}</p>
                            </div>
                            <div>
                                <SmartSelect v-model="form.secretary_id" label="Sekretaris" :options="committeeOption" placeholder="Cari anggota aktif" searchable required :error="form.errors.secretary_id" />
                                <p v-if="selectedGroup?.secretary?.name" class="mt-1 text-xs text-on-surface-variant">Pengurus saat ini: {{ selectedGroup.secretary.name }}</p>
                            </div>
                            <div>
                                <SmartSelect v-model="form.treasurer_id" label="Bendahara" :options="committeeOption" placeholder="Cari anggota aktif" searchable required :error="form.errors.treasurer_id" />
                                <p v-if="selectedGroup?.treasurer?.name" class="mt-1 text-xs text-on-surface-variant">Pengurus saat ini: {{ selectedGroup.treasurer.name }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Pemanfaat</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Pilih anggota yang menerima bagian plafon. Plafon dibagi rata ke seluruh pemanfaat aktif.</p>
                        <div class="mt-3">
                            <div v-if="!selectedGroup" class="rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Pilih kelompok terlebih dahulu.</div>
                            <template v-else>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div class="flex-1"><SmartSelect v-model="beneficiaryCandidateId" label="Cari anggota di luar kelompok" :options="beneficiaryCandidateOptions" searchable :loading="beneficiaryLoading" placeholder="Cari NIK atau nama" @search-change="updateBeneficiarySearch" @search="searchBeneficiaries" /></div>
                                    <AppButton type="button" variant="secondary" icon="person_add" class="min-h-14 w-full sm:w-auto" :disabled="!beneficiaryCandidateId" @click="addBeneficiary">Tambahkan</AppButton>
                                </div>
                                <div v-if="memberOptions.length === 0" class="mt-3 rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Belum ada pemanfaat.</div>
                                <div v-else class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            <tr>
                                                <th class="py-3 px-4">Nama</th>
                                                <th class="py-3 px-4 text-right">Pengajuan (Rp)</th>
                                                <th class="py-3 px-4 text-center">Aktif</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant">
                                            <tr v-for="member in memberOptions" :key="member.value">
                                                <td class="py-2 px-4"><span class="font-semibold text-primary">{{ member.label }}</span></td>
                                                <td class="py-2 px-4">
                                                    <AppCurrencyInput v-model="form.beneficiary_amounts[member.value]" label="" hide-label :min="0" :error="form.errors[`beneficiary_amounts.${member.value}`]" placeholder="0" />
                                                </td>
                                                <td class="py-2 px-4 text-center">
                                                    <AppCheckbox :value="member.value" v-model="form.beneficiary_ids" />
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-surface-container-low">
                                                <td class="py-3 px-4 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Pengajuan</td>
                                                <td class="py-3 px-4 text-right text-base font-bold text-primary">{{ currency(beneficiaryTotal) }}</td>
                                                <td class="py-3 px-4"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p v-if="beneficiaryTotal > 0 && Number(form.principal_amount) > 0 && beneficiaryTotal > Number(form.principal_amount)" class="mt-2 text-sm text-error">Total pengajuan melebihi plafon pinjaman ({{ currency(Number(form.principal_amount)) }}).</p>
                                <p v-if="form.errors.beneficiary_ids" class="mt-2 text-sm text-error">{{ form.errors.beneficiary_ids }}</p>
                                <p v-if="form.errors.beneficiary_amounts" class="mt-2 text-sm text-error">{{ form.errors.beneficiary_amounts }}</p>
                            </template>
                        </div>
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">Simpan Proposal</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
