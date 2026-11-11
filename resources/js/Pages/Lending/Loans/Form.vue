<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
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
    label: product.name,
    subtitle: product.code,
})));

const installmentMethodOptions = [
    { value: 'flat', label: t('Flat') },
    { value: 'annuity', label: t('Anuitas') },
    { value: 'effective', label: t('Efektif') },
];

const principalFrequencyOptions = [
    { value: 'weekly', label: t('Mingguan') },
    { value: 'biweekly', label: t('Dua Mingguan') },
    { value: 'monthly', label: t('Bulanan') },
    { value: 'bimonthly', label: t('Tiap 2 Bulan') },
    { value: 'quarterly', label: t('Tiap 3 Bulan') },
    { value: 'every_4_months', label: t('Tiap 4 Bulan') },
    { value: 'every_5_months', label: t('Tiap 5 Bulan') },
    { value: 'every_6_months', label: t('Tiap 6 Bulan') },
    { value: 'every_7_months', label: t('Tiap 7 Bulan') },
    { value: 'every_8_months', label: t('Tiap 8 Bulan') },
    { value: 'every_9_months', label: t('Tiap 9 Bulan') },
    { value: 'every_10_months', label: t('Tiap 10 Bulan') },
    { value: 'every_11_months', label: t('Tiap 11 Bulan') },
    { value: 'every_12_months', label: t('Tiap 12 Bulan') },
    { value: 'every_24_months', label: t('Tiap 24 Bulan') },
    { value: 'every_36_months', label: t('Tiap 36 Bulan') },
    { value: 'at_maturity', label: t('Sekaligus di Akhir') },
];

const graceOptions = [
    { value: 0, label: t('Tanpa Penundaan') },
    { value: 1, label: t('1 Bulan') },
    { value: 2, label: t('2 Bulan') },
    { value: 3, label: t('3 Bulan') },
    { value: 6, label: t('6 Bulan') },
    { value: 12, label: t('12 Bulan') },
    { value: 24, label: t('24 Bulan') },
];

const roundingOptions = [
    { value: '', label: t('Default Produk') },
    { value: '0', label: t('Tanpa Pembulatan (2 Desimal)') },
    { value: '100', label: t('Rp 100') },
    { value: '500', label: 'Rp 500 (<=250: 0, >250: 500)' },
    { value: '1000', label: t('Rp 1.000') },
    { value: '5000', label: t('Rp 5.000') },
    { value: '10000', label: t('Rp 10.000') },
    { value: '50000', label: t('Rp 50.000') },
];

const interestFrequencyOptions = [
    { value: 'weekly', label: t('Mingguan') },
    { value: 'biweekly', label: t('Dua Mingguan') },
    { value: 'monthly', label: t('Bulanan') },
    { value: 'bimonthly', label: t('Tiap 2 Bulan') },
    { value: 'quarterly', label: t('Tiap 3 Bulan') },
    { value: 'every_4_months', label: t('Tiap 4 Bulan') },
    { value: 'every_5_months', label: t('Tiap 5 Bulan') },
    { value: 'every_6_months', label: t('Tiap 6 Bulan') },
    { value: 'every_7_months', label: t('Tiap 7 Bulan') },
    { value: 'every_8_months', label: t('Tiap 8 Bulan') },
    { value: 'every_9_months', label: t('Tiap 9 Bulan') },
    { value: 'every_10_months', label: t('Tiap 10 Bulan') },
    { value: 'every_11_months', label: t('Tiap 11 Bulan') },
    { value: 'every_12_months', label: t('Tiap 12 Bulan') },
    { value: 'every_24_months', label: t('Tiap 24 Bulan') },
    { value: 'every_36_months', label: t('Tiap 36 Bulan') },
    { value: 'at_maturity', label: t('Sekaligus di Akhir') },
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
    <Head :title="t('Register Proposal Pinjaman')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl">
            <header class="mb-4">
                <h1 class="text-xl font-bold text-primary">{{ t('Register Proposal Pinjaman') }}</h1>
                <p class="mt-0.5 text-sm text-on-surface-variant">{{ t('Daftarkan proposal pinjaman untuk kelompok. Pemanfaat adalah anggota kelompok.') }}</p>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <!-- Produk & Kelompok -->
                    <section>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <SmartSelect v-model="selectedProductId" :label="t('Produk Pinjaman')" :options="productOptions" :placeholder="t('Pilih produk')" required searchable :error="form.errors.loan_product_id" />
                            <SmartSelect v-model="selectedGroupId" :label="t('Kelompok')" :options="groups.map((g) => ({ value: g.value, label: g.label }))" :placeholder="t('Pilih kelompok')" required searchable :error="form.errors.group_id" />
                        </div>
                        <div v-if="selectedProduct" class="mt-2 rounded-lg border border-secondary/30 bg-secondary/10 px-3 py-2 text-xs text-primary">
                            <span class="font-semibold">{{ selectedProduct.name }} ({{ selectedProduct.code }})</span> · Jasa {{ formatRate(selectedProduct.default_interest_rate) }}%/periode · Tenor {{ selectedProduct.default_term_months }} bln.
                        </div>
                    </section>

                    <hr class="border-outline-variant">

                    <!-- Detail Pengajuan -->
                    <section>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <AppDatePicker v-model="form.proposed_at" :label="t('Tgl Pengajuan')" icon="event" :placeholder="t('Pilih tanggal')" :max="today" required :error="form.errors.proposed_at" />
                            <AppCurrencyInput v-model="form.principal_amount" :label="t('Plafon')" icon="payments" :min="0" placeholder="0" required :error="form.errors.principal_amount" />
                            <AppInput v-model="form.term_months" :label="t('Tenor (bln)')" icon="schedule" type="number" inputmode="numeric" min="1" max="120" placeholder="12" required :error="form.errors.term_months" />
                            <AppInput
                                v-model="form.service_rate_total"
                                :label="t('Jasa Total (%)')"
                                icon="percent"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                placeholder="18"
                                required
                                :error="form.errors.service_rate_total"
                                tooltip="Total jasa sepanjang pinjaman. Contoh: 1,5%/bulan × 12 bulan = 18"
                            />
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <SmartSelect v-model="form.installment_method" :label="t('Metode')" :options="installmentMethodOptions" required :error="form.errors.installment_method" />
                            <SmartSelect v-model="form.principal_frequency" :label="t('Frek. Pokok')" :options="principalFrequencyOptions" required :error="form.errors.principal_frequency" />
                            <SmartSelect v-model="form.interest_frequency" :label="t('Frek. Jasa')" :options="interestFrequencyOptions" required :error="form.errors.interest_frequency" />
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <SmartSelect v-model="form.principal_grace_months" :label="t('Grace Pokok')" :options="graceOptions" required :error="form.errors.principal_grace_months" />
                            <SmartSelect v-model="form.interest_grace_months" :label="t('Grace Jasa')" :options="graceOptions" required :error="form.errors.interest_grace_months" />
                        </div>
                    </section>

                    <hr class="border-outline-variant">

                    <!-- Pengurus -->
                    <section>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <SmartSelect v-model="form.chair_id" :label="t('Ketua')" :options="committeeOption" :placeholder="t('Cari anggota')" searchable required :error="form.errors.chair_id" />
                                <p v-if="selectedGroup?.chair?.name" class="ml-1 mt-0.5 text-xs text-on-surface-variant">{{ t('Saat ini:') }} {{ selectedGroup.chair.name }}</p>
                            </div>
                            <div>
                                <SmartSelect v-model="form.secretary_id" :label="t('Sekretaris')" :options="committeeOption" :placeholder="t('Cari anggota')" searchable required :error="form.errors.secretary_id" />
                                <p v-if="selectedGroup?.secretary?.name" class="ml-1 mt-0.5 text-xs text-on-surface-variant">{{ t('Saat ini:') }} {{ selectedGroup.secretary.name }}</p>
                            </div>
                            <div>
                                <SmartSelect v-model="form.treasurer_id" :label="t('Bendahara')" :options="committeeOption" :placeholder="t('Cari anggota')" searchable required :error="form.errors.treasurer_id" />
                                <p v-if="selectedGroup?.treasurer?.name" class="ml-1 mt-0.5 text-xs text-on-surface-variant">{{ t('Saat ini:') }} {{ selectedGroup.treasurer.name }}</p>
                            </div>
                        </div>
                    </section>

                    <hr class="border-outline-variant">

                    <!-- Pemanfaat -->
                    <section>
                        <div v-if="!selectedGroup" class="rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant">{{ t('Pilih kelompok terlebih dahulu.') }}</div>
                        <template v-else>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div class="flex-1"><SmartSelect v-model="beneficiaryCandidateId" :label="t('Tambah Pemanfaat')" :options="beneficiaryCandidateOptions" searchable :loading="beneficiaryLoading" :placeholder="t('Cari nama/NIK')" @search-change="updateBeneficiarySearch" @search="searchBeneficiaries" /></div>
                                <AppButton type="button" variant="secondary" icon="person_add" class="min-h-11 w-full sm:w-auto" :disabled="!beneficiaryCandidateId" @click="addBeneficiary">{{ t('Tambah') }}</AppButton>
                            </div>
                            <div v-if="memberOptions.length === 0" class="mt-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant">{{ t('Belum ada pemanfaat.') }}</div>
                            <div v-else class="mt-2 overflow-x-auto rounded-lg border border-outline-variant">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                        <tr>
                                            <th class="px-3 py-2">{{ t('Nama') }}</th>
                                            <th class="px-3 py-2 text-right">{{ t('Pengajuan (Rp)') }}</th>
                                            <th class="px-3 py-2 text-center w-16">{{ t('Aktif') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant">
                                        <tr v-for="member in memberOptions" :key="member.value">
                                            <td class="px-3 py-1.5"><span class="font-semibold text-primary">{{ member.label }}</span></td>
                                            <td class="px-3 py-1.5">
                                                <AppCurrencyInput v-model="form.beneficiary_amounts[member.value]" label="" hide-label :min="0" :error="form.errors[`beneficiary_amounts.${member.value}`]" placeholder="0" />
                                            </td>
                                            <td class="px-3 py-1.5 text-center">
                                                <AppCheckbox :value="member.value" v-model="form.beneficiary_ids" />
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-surface-container-low">
                                            <td class="px-3 py-2 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">{{ t('Total') }}</td>
                                            <td class="px-3 py-2 text-right text-sm font-bold text-primary">{{ currency(beneficiaryTotal) }}</td>
                                            <td class="px-3 py-2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <p v-if="beneficiaryTotal > 0 && Number(form.principal_amount) > 0 && beneficiaryTotal > Number(form.principal_amount)" class="mt-1.5 text-xs text-error">Total melebihi plafon ({{ currency(Number(form.principal_amount)) }}).</p>
                            <p v-if="form.errors.beneficiary_ids" class="mt-1.5 text-xs text-error">{{ form.errors.beneficiary_ids }}</p>
                            <p v-if="form.errors.beneficiary_amounts" class="mt-1.5 text-xs text-error">{{ form.errors.beneficiary_amounts }}</p>
                        </template>
                    </section>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2 border-t border-outline-variant pt-3">
                        <Link :href="path"><AppButton variant="secondary">{{ t('Batal') }}</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">{{ t('Simpan') }}</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
