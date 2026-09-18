<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, required: true },
    members: { type: Array, required: true },
});

const path = '/lending/member-loans';
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

const selectedProductId = ref('');
const selectedMemberId = ref('');

const form = useForm({
    loan_product_id: '',
    member_id: '',
    proposed_at: today,
    principal_amount: '',
    service_rate_total: '',
    term_months: '',
    installment_method: 'flat',
    principal_frequency: 'monthly',
    interest_frequency: 'monthly',
    principal_grace_months: 0,
    interest_grace_months: 0,
    rounding_step: '',
});

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
    form.post(path);
}
</script>

<template>
    <Head title="Register Proposal Pinjaman Individu" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-primary">Register Proposal Pinjaman Individu</h1>
                <p class="mt-1 text-on-surface-variant">Daftarkan proposal pinjaman perorangan. Peminjam adalah satu anggota aktif.</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">Produk & Peminjam</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <SmartSelect v-model="selectedProductId" label="Produk Pinjaman (Individu)" :options="productOptions" placeholder="Pilih produk untuk individu" required searchable :error="form.errors.loan_product_id" />
                            <SmartSelect v-model="selectedMemberId" label="Anggota Peminjam" :options="memberOptions" placeholder="Pilih anggota aktif" required searchable :error="form.errors.member_id" />
                        </div>
                    </section>

                    <section v-if="selectedProduct" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm text-primary">
                        <p class="font-semibold">{{ selectedProduct.name }} ({{ selectedProduct.code }})</p>
                        <p class="mt-1 text-on-surface-variant">Default: suku jasa {{ formatRate(selectedProduct.default_interest_rate) }}% per periode · Tenor {{ selectedProduct.default_term_months }} bulan.</p>
                    </section>

                    <section v-if="selectedMember" class="rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3 text-sm text-primary">
                        <p class="font-semibold">{{ selectedMember.label }}</p>
                        <p class="mt-1 text-on-surface-variant">
                            <span v-if="selectedMember.nik">NIK {{ selectedMember.nik }}</span>
                            <span v-if="selectedMember.village"> · {{ selectedMember.village }}</span>
                        </p>
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
                            <SmartSelect v-model="form.principal_frequency" label="Angsuran Pokok" :options="frequencyOptions" required :error="form.errors.principal_frequency" />
                            <SmartSelect v-model="form.interest_frequency" label="Angsuran Jasa" :options="frequencyOptions" required :error="form.errors.interest_frequency" />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <SmartSelect v-model="form.principal_grace_months" label="Grace Period Pokok" :options="graceOptions" required :error="form.errors.principal_grace_months" />
                            <SmartSelect v-model="form.interest_grace_months" label="Grace Period Jasa" :options="graceOptions" required :error="form.errors.interest_grace_months" />
                            <SmartSelect v-model="form.rounding_step" label="Pembulatan Angsuran" :options="roundingOptions" :error="form.errors.rounding_step" />
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
