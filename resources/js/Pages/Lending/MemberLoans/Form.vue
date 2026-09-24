<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, required: true },
    members: { type: Array, required: true },
    loan: { type: Object, default: null },
    mode: { type: String, default: 'create' },
});

const isEdit = computed(() => props.mode === 'edit' && props.loan);
const path = computed(() => (isEdit.value ? `/lending/member-loans/${props.loan.row_id}` : '/lending/member-loans'));
const today = (() => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
})();

const productOptions = computed(() => props.products.map((product) => ({
    value: product.row_id,
    label: `${product.name} · ${product.code}`,
})));
const memberOptions = computed(() => props.members.map((member) => ({
    value: member.value,
    label: member.label,
    sublabel: member.nik ? `NIK ${member.nik}` : '',
    village: member.village,
})));

const installmentMethodOptions = [
    { value: 'flat', label: 'Flat' },
    { value: 'annuity', label: 'Anuitas' },
    { value: 'effective', label: 'Efektif' },
];

const frequencyOptions = [
    { value: 'weekly', label: 'Mingguan' },
    { value: 'biweekly', label: '2 Mingguan' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'bimonthly', label: 'Tiap 2 Bulan' },
    { value: 'quarterly', label: 'Tiap 3 Bulan' },
    { value: 'every_4_months', label: 'Tiap 4 Bulan' },
    { value: 'every_5_months', label: 'Tiap 5 Bulan' },
    { value: 'every_6_months', label: 'Tiap 6 Bulan' },
    { value: 'every_7_months', label: 'Tiap 7 Bulan' },
    { value: 'every_8_months', label: 'Tiap 8 Bulan' },
    { value: 'every_9_months', label: 'Tiap 9 Bulan' },
    { value: 'every_10_months', label: 'Tiap 10 Bulan' },
    { value: 'every_11_months', label: 'Tiap 11 Bulan' },
    { value: 'every_12_months', label: 'Tiap 12 Bulan' },
    { value: 'every_24_months', label: 'Tiap 24 Bulan' },
    { value: 'every_36_months', label: 'Tiap 36 Bulan' },
    { value: 'at_maturity', label: 'Sekaligus di Akhir' },
];

const graceOptions = [
    { value: 0, label: 'Tanpa Grace' },
    { value: 1, label: 'M1' },
    { value: 2, label: 'M2' },
    { value: 3, label: 'M3' },
    { value: 6, label: 'M6' },
    { value: 12, label: 'M12' },
    { value: 24, label: 'M24' },
];

const roundingOptions = [
    { value: '', label: 'Default' },
    { value: '0', label: '2 Desimal' },
    { value: '100', label: 'Rp 100' },
    { value: '500', label: 'Rp 500' },
    { value: '1000', label: 'Rp 1.000' },
    { value: '5000', label: 'Rp 5.000' },
    { value: '10000', label: 'Rp 10.000' },
    { value: '50000', label: 'Rp 50.000' },
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
    every_11_months: 1 / 12,
    every_12_months: 1 / 12,
    every_24_months: 1 / 24,
    every_36_months: 1 / 36,
};

const selectedProductId = ref(isEdit.value ? String(props.loan.loan_product_id ?? '') : '');
const selectedMemberId = ref(isEdit.value ? String(props.loan.member_id ?? '') : '');

const form = useForm({
    loan_product_id: isEdit.value ? (props.loan.loan_product_id ?? '') : '',
    member_id: isEdit.value ? (props.loan.member_id ?? '') : '',
    proposed_at: isEdit.value && props.loan.proposed_at ? props.loan.proposed_at : today,
    principal_amount: isEdit.value ? props.loan.principal_amount : '',
    service_rate_total: isEdit.value ? props.loan.service_rate_total : '',
    term_months: isEdit.value ? props.loan.term_months : '',
    installment_method: isEdit.value ? (props.loan.installment_method || 'flat') : 'flat',
    principal_frequency: isEdit.value ? (props.loan.principal_frequency || 'monthly') : 'monthly',
    interest_frequency: isEdit.value ? (props.loan.interest_frequency || 'monthly') : 'monthly',
    principal_grace_months: isEdit.value ? (props.loan.principal_grace_months ?? 0) : 0,
    interest_grace_months: isEdit.value ? (props.loan.interest_grace_months ?? 0) : 0,
    rounding_step: isEdit.value ? (props.loan.rounding_step ?? '') : '',
    collateral: {
        type: isEdit.value && props.loan.collateral?.type ? props.loan.collateral.type : 'kendaraan',
        description: isEdit.value ? (props.loan.collateral?.description ?? '') : '',
        value: isEdit.value ? (props.loan.collateral?.value ?? '') : '',
        reference: isEdit.value ? (props.loan.collateral?.reference ?? '') : '',
    },
    verification_remarks: isEdit.value ? (props.loan.verification_remarks ?? '') : '',
});

const collateralTypeOptions = [
    { value: 'kendaraan', label: 'BPKB Kendaraan' },
    { value: 'sertifikat_tanah', label: 'Sertifikat Tanah' },
    { value: 'bpkb', label: 'BPKB saja' },
    { value: 'lainnya', label: 'Lainnya' },
];

const selectedProduct = computed(() => props.products.find((p) => String(p.row_id) === String(selectedProductId.value)) || null);
const selectedMember = computed(() => props.members.find((m) => String(m.value) === String(selectedMemberId.value)) || null);

const rateFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const formatRate = (value) => rateFormatter.format(Number(value ?? 0));

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

const fillDefaults = () => {
    const product = selectedProduct.value;
    if (!product) return;
    const months = Number(form.term_months);
    const defaultRate = Number(product.default_interest_rate || 0);
    if (!form.term_months && product.default_term_months) form.term_months = String(product.default_term_months);
    if (defaultRate && months && !form.service_rate_total) {
        const periods = form.principal_frequency === 'at_maturity'
            ? 1
            : Math.round(months * (frequencyMultiplier[form.principal_frequency] || 0));
        form.service_rate_total = (defaultRate * periods).toFixed(2);
    }
};

watch(selectedProductId, (value) => {
    form.loan_product_id = value;
    fillDefaults();
});
watch(selectedMemberId, (value) => {
    form.member_id = value;
});
watch([() => form.term_months, () => form.principal_frequency], () => fillDefaults());

function submit() {
    if (isEdit.value) {
        form.put(path.value);
    } else {
        form.post(path.value);
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Proposal Pinjaman Individu' : 'Register Proposal Pinjaman Individu'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6 pb-12">
            <header class="mb-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-primary">
                        {{ isEdit ? 'Edit Proposal Pinjaman Individu' : 'Register Proposal Pinjaman Individu' }}
                    </h1>
                    <p v-if="isEdit" class="mt-1 text-sm text-on-surface-variant">
                        Ubah parameter pinjaman untuk proposal
                        <strong class="text-on-surface">{{ loan?.loan_number || `#${loan?.row_id}` }}</strong>.
                        Produk &amp; anggota tidak dapat diubah pada tahap ini.
                    </p>
                </div>
                <Link v-if="isEdit" :href="`/lending/member-loans/${loan.row_id}`" class="text-sm font-semibold text-primary hover:underline">
                    ← Kembali ke detail
                </Link>
            </header>

            <AppCard>
                <form class="grid gap-x-5 gap-y-4" @submit.prevent="submit">
                    <div class="col-span-12 grid gap-x-5 gap-y-4 sm:grid-cols-12">
                        <div class="sm:col-span-6">
                            <SmartSelect
                                v-model="selectedProductId"
                                label="Produk"
                                :options="productOptions"
                                placeholder="Pilih produk"
                                :required="!isEdit"
                                :disabled="isEdit"
                                :searchable="!isEdit"
                                :error="form.errors.loan_product_id"
                            />
                        </div>
                        <div class="sm:col-span-6">
                            <SmartSelect
                                v-model="selectedMemberId"
                                label="Anggota"
                                :options="memberOptions"
                                placeholder="Pilih anggota"
                                :required="!isEdit"
                                :disabled="isEdit"
                                :searchable="!isEdit"
                                :error="form.errors.member_id"
                            />
                        </div>
                    </div>

                    <div v-if="selectedProduct" class="col-span-12 -mt-1 rounded-lg border border-secondary/30 bg-secondary/10 px-3 py-2 text-xs text-primary">
                        <span class="font-semibold">{{ selectedProduct.name }} ({{ selectedProduct.code }})</span>
                        <span class="text-on-surface-variant"> · Jasa default {{ formatRate(selectedProduct.default_interest_rate) }}% / Tenor {{ selectedProduct.default_term_months }} bln</span>
                    </div>

                    <div v-if="selectedMember" class="col-span-12 -mt-1 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs text-primary">
                        <span class="font-semibold">{{ selectedMember.label }}</span>
                        <span class="text-on-surface-variant">
                            <span v-if="selectedMember.nik"> · NIK {{ selectedMember.nik }}</span>
                            <span v-if="selectedMember.village"> · {{ selectedMember.village }}</span>
                        </span>
                    </div>

                    <div class="col-span-12 my-1 border-t border-outline-variant"></div>

                    <div class="col-span-12 grid gap-x-5 gap-y-4 sm:grid-cols-12">
                        <div class="sm:col-span-3">
                            <AppDatePicker v-model="form.proposed_at" label="Tgl Pengajuan" icon="event" :max="today" required :error="form.errors.proposed_at" />
                        </div>
                        <div class="sm:col-span-4">
                            <AppCurrencyInput v-model="form.principal_amount" label="Plafon" icon="payments" :min="0" required :error="form.errors.principal_amount" />
                        </div>
                        <div class="sm:col-span-2">
                            <AppInput v-model="form.term_months" label="Tenor (bln)" icon="schedule" type="number" inputmode="numeric" min="1" max="120" required :error="form.errors.term_months" />
                        </div>
                        <div class="sm:col-span-3">
                            <AppInput
                                v-model="form.service_rate_total"
                                label="Total Jasa (%)"
                                icon="percent"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                required
                                :error="form.errors.service_rate_total"
                                tooltip="Total jasa sepanjang pinjaman. Contoh: 1,5%/bln × 12 bln = 18"
                            />
                        </div>

                        <div class="sm:col-span-3">
                            <SmartSelect v-model="form.installment_method" label="Metode" :options="installmentMethodOptions" required :error="form.errors.installment_method" />
                        </div>
                        <div class="sm:col-span-3">
                            <SmartSelect v-model="form.principal_frequency" label="Freq. Pokok" :options="frequencyOptions" required :error="form.errors.principal_frequency" />
                        </div>
                        <div class="sm:col-span-3">
                            <SmartSelect v-model="form.interest_frequency" label="Freq. Jasa" :options="frequencyOptions" required :error="form.errors.interest_frequency" />
                        </div>
                        <div class="sm:col-span-3">
                            <SmartSelect v-model="form.rounding_step" label="Pembulatan" :options="roundingOptions" :error="form.errors.rounding_step" />
                        </div>

                        <div class="sm:col-span-6">
                            <SmartSelect v-model="form.principal_grace_months" label="Grace Pokok" :options="graceOptions" required :error="form.errors.principal_grace_months" />
                        </div>
                        <div class="sm:col-span-6">
                            <SmartSelect v-model="form.interest_grace_months" label="Grace Jasa" :options="graceOptions" required :error="form.errors.interest_grace_months" />
                        </div>
                    </div>

                    <div v-if="periodPreview" class="col-span-12 -mt-1 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 text-xs text-on-surface-variant">
                        Pokok: {{ periodPreview.principal.periods }}× angsuran ≈ {{ periodPreview.principal.perPeriod }} ·
                        Jasa: {{ periodPreview.interest.periods }}× angsuran ≈ {{ periodPreview.interest.perPeriod }}
                    </div>

                    <div class="col-span-12 my-1 border-t border-outline-variant"></div>

                    <div class="col-span-12 grid gap-x-5 gap-y-4 sm:grid-cols-12">
                        <div class="sm:col-span-3">
                            <SmartSelect v-model="form.collateral.type" label="Jaminan" :options="collateralTypeOptions" :error="form.errors['collateral.type']" />
                        </div>
                        <div class="sm:col-span-3">
                            <AppInput v-model="form.collateral.reference" label="No. Dokumen" icon="tag" maxlength="120" :error="form.errors['collateral.reference']" placeholder="BP-1234-XX" />
                        </div>
                        <div class="sm:col-span-3">
                            <AppCurrencyInput v-model="form.collateral.value" label="Estimasi Nilai" icon="payments" :min="0" :error="form.errors['collateral.value']" />
                        </div>
                        <div class="sm:col-span-3">
                            <AppInput v-model="form.collateral.description" label="Keterangan" icon="description" maxlength="120" :error="form.errors['collateral.description']" placeholder="Honda Beat 2022" />
                        </div>
                    </div>

                    <div class="col-span-12 my-1 border-t border-outline-variant"></div>

                    <div class="col-span-12">
                        <AppTextarea
                            v-model="form.verification_remarks"
                            label=""
                            hide-label
                            placeholder="Catatan verifikasi (opsional) — sumber dana, tunggakan, dll."
                            :rows="2"
                            :maxlength="2000"
                            :error="form.errors.verification_remarks"
                        />
                    </div>

                    <div class="col-span-12 flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">
                            {{ isEdit ? 'Simpan Perubahan' : 'Simpan Proposal' }}
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
