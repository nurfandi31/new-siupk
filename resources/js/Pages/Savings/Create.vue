<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppCurrencyInput from '../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useMoney } from '../../composables/useMoney';
import { useI18n } from '../../i18n';

const { t } = useI18n();
const { money } = useMoney();

const props = defineProps({
    products: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
    selectedMember: { type: Object, default: null },
});

const today = new Date().toLocaleDateString('en-CA');

const form = useForm({
    member_row_id: props.selectedMember?.value ?? '',
    product_row_id: '',
    opened_at: today,
    opening_amount: 0,
    opening_fee: props.settings.opening_fee ?? 0,
    minimum_closing_date: today,
    interest_rate: '',
    tax_rate: '',
    admin_fee: '',
    authority_type: 1,
    institution_name: '',
    institution_position: '',
    guardian_name: '',
    relationship: '',
    notes: '',
});

const members = ref(props.selectedMember ? [props.selectedMember] : []);
const memberLoading = ref(false);
let memberController = null;

const productOptions = computed(() =>
    props.products.map((product) => ({
        value: product.row_id,
        label: `${product.code} — ${product.name}`,
    })),
);

const selectedProduct = computed(() =>
    props.products.find((product) => String(product.row_id) === String(form.product_row_id)) ?? null,
);

const requiresClosingDate = computed(() => Number(selectedProduct.value?.form_type ?? 1) !== 1);
const isInstitution = computed(() => Number(form.authority_type) === 2);

const authorityOptions = [
    { value: 1, label: t('Pribadi') },
    { value: 2, label: t('Kuasa / Lembaga') },
];

const netOpening = computed(() => {
    const amount = Number(form.opening_amount || 0);
    const fee = Number(form.opening_fee || 0);

    return Math.max(0, amount - fee);
});

const accountPreview = computed(() => {
    const code = selectedProduct.value?.code || '1';

    return `${code}-000.00000-01`;
});

function applyProductDefaults(productId) {
    const product = props.products.find((item) => String(item.row_id) === String(productId));
    if (!product) return;

    form.interest_rate = product.interest_rate;
    form.tax_rate = product.tax_rate;
    form.admin_fee = product.admin_fee;
    form.opening_fee = product.opening_fee ?? props.settings.opening_fee ?? 0;
    form.minimum_closing_date = Number(product.form_type ?? 1) === 1 ? form.opened_at : '';
}

watch(() => form.product_row_id, (value) => {
    if (value) applyProductDefaults(value);
});

watch(() => form.opened_at, (value) => {
    if (!requiresClosingDate.value) {
        form.minimum_closing_date = value;
    }
});

watch(requiresClosingDate, (required) => {
    if (!required) {
        form.minimum_closing_date = form.opened_at;
    }
});

async function searchMembers(search = '') {
    memberController?.abort();
    const controller = new AbortController();
    memberController = controller;
    memberLoading.value = true;

    try {
        const response = await fetch(`/simpanan/anggota/options?search=${encodeURIComponent(search.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('Data anggota gagal dimuat.');
        const payload = await response.json();
        const data = payload.data ?? [];
        const selected = props.selectedMember;

        members.value = selected && !data.some((item) => String(item.value) === String(selected.value))
            ? [selected, ...data]
            : data;
    } catch (error) {
        if (error.name !== 'AbortError') members.value = [];
    } finally {
        if (memberController === controller) {
            memberLoading.value = false;
            memberController = null;
        }
    }
}

function submit() {
    form.post('/simpanan', { preserveScroll: true });
}

function printBlankForm() {
    window.open('/simpanan/formulir-kosong', '_blank', 'noopener');
}

onMounted(() => {
    searchMembers('');
    if (!form.product_row_id && props.products.length > 0) {
        form.product_row_id = props.products[0].row_id;
        applyProductDefaults(props.products[0].row_id);
    }
});
</script>

<template>
    <Head :title="t('Buka Rekening Simpanan')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ t('Buka Rekening Simpanan') }}</h1>
                        <AppBadge tone="success-soft">{{ t('Modul Lokasi') }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        {{ t('Pilih anggota dan jenis simpanan. Nomor rekening dibuat otomatis dengan format SIUPK.') }}
                    </p>
                </div>
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center">
                    <AppButton variant="secondary" icon="print" class="w-full justify-center sm:w-auto" @click="printBlankForm">
                        {{ t('Formulir Kosong') }}
                    </AppButton>
                    <Link href="/simpanan" class="block">
                        <AppButton variant="ghost" icon="arrow_back" class="w-full justify-center sm:w-auto">{{ t('Kembali') }}</AppButton>
                    </Link>
                </div>
            </header>

            <form class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_340px]" @submit.prevent="submit">
                <div class="min-w-0 space-y-6">
                    <AppCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="person_add" tone="success" container-size="9" />
                                <div>
                                    <p class="font-semibold text-on-surface">{{ t('Data Rekening') }}</p>
                                    <p class="text-sm text-on-surface-variant">{{ t('Anggota, jenis simpanan, dan tanggal buku') }}</p>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-4">
                            <SmartSelect
                                v-model="form.member_row_id"
                                :label="t('Anggota')"
                                :options="members"
                                :loading="memberLoading"
                                :error="form.errors.member_row_id"
                                searchable
                                :placeholder="t('Cari nama, nomor anggota, atau NIK')"
                                @search="searchMembers"
                            />

                            <SmartSelect
                                v-model="form.product_row_id"
                                :label="t('Jenis Simpanan')"
                                :options="productOptions"
                                :error="form.errors.product_row_id"
                                :placeholder="t('Pilih jenis simpanan')"
                            />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppDatePicker
                                    v-model="form.opened_at"
                                    :label="t('Tanggal Buka Rekening')"
                                    :error="form.errors.opened_at"
                                />
                                <AppDatePicker
                                    v-if="requiresClosingDate"
                                    v-model="form.minimum_closing_date"
                                    :label="t('Tanggal Minimal Tutup')"
                                    :error="form.errors.minimum_closing_date"
                                />
                                <AppCurrencyInput
                                    v-model="form.opening_amount"
                                    :label="t('Setoran Awal')"
                                    icon="savings"
                                    :error="form.errors.opening_amount"
                                    hint="Boleh dikosongkan bila rekening dibuka tanpa setoran."
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <SmartSelect
                                    v-model="form.authority_type"
                                    :label="t('Status Permohonan')"
                                    :options="authorityOptions"
                                    :error="form.errors.authority_type"
                                />
                                <AppInput
                                    v-model="form.relationship"
                                    :label="t('Hubungan dengan Pemohon')"
                                    icon="diversity_3"
                                    :error="form.errors.relationship"
                                    placeholder="SUAMI / ISTRI / ANAK"
                                />
                            </div>

                            <div v-if="isInstitution" class="grid gap-4 rounded-2xl border border-outline-variant bg-surface-container-low p-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.institution_name"
                                    :label="t('Nama Lembaga')"
                                    icon="corporate_fare"
                                    :error="form.errors.institution_name"
                                />
                                <AppInput
                                    v-model="form.institution_position"
                                    :label="t('Jabatan')"
                                    icon="badge"
                                    :error="form.errors.institution_position"
                                />
                            </div>

                            <AppInput
                                v-model="form.guardian_name"
                                :label="t('Nama Pengampu / Ahli Waris')"
                                icon="supervisor_account"
                                :error="form.errors.guardian_name"
                                placeholder="Nama pengampu bila pemohon diwakilkan"
                            />

                            <AppTextarea
                                v-model="form.notes"
                                :label="t('Catatan / Jenis Program')"
                                :error="form.errors.notes"
                                :rows="2"
                                :placeholder="t('Catatan internal atau nama program (opsional)')"
                            />
                        </div>
                    </AppCard>

                    <AppCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="percent" tone="primary" container-size="9" />
                                <div>
                                    <p class="font-semibold text-on-surface">{{ t('Bunga, Pajak & Biaya') }}</p>
                                    <p class="text-sm text-on-surface-variant">{{ t('Nilai disalin dari jenis simpanan, bisa disesuaikan per rekening.') }}</p>
                                </div>
                            </div>
                        </template>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <AppInput
                                v-model="form.interest_rate"
                                type="number"
                                step="0.001"
                                min="0"
                                max="100"
                                :label="t('Bunga per Bulan (%)')"
                                icon="percent"
                                :error="form.errors.interest_rate"
                            />
                            <AppInput
                                v-model="form.tax_rate"
                                type="number"
                                step="0.001"
                                min="0"
                                max="100"
                                :label="t('Pajak Bunga (%)')"
                                icon="receipt_long"
                                :error="form.errors.tax_rate"
                            />
                            <AppInput
                                v-model="form.admin_fee"
                                type="number"
                                step="0.01"
                                min="0"
                                :label="t('Admin per Bulan')"
                                icon="payments"
                                :error="form.errors.admin_fee"
                            />
                            <AppCurrencyInput
                                v-model="form.opening_fee"
                                :label="t('Biaya Buka Buku')"
                                icon="receipt"
                                :error="form.errors.opening_fee"
                                :hint="t('Dipotong dari setoran awal')"
                            />
                        </div>
                    </AppCard>
                </div>

                <aside class="min-w-0 space-y-4">
                    <AppCard class="border border-outline-variant bg-gradient-to-br from-secondary-container/50 to-surface-container-low">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-2xl bg-surface/80 text-secondary">
                                <AppIcon name="savings" />
                            </span>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.16em] text-on-surface-variant">{{ t('Pratinjau') }}</p>
                                <p class="font-mono text-sm font-bold text-on-surface">{{ accountPreview }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Jenis') }}</dt>
                                <dd class="max-w-[60%] text-right font-semibold text-on-surface">
                                    {{ selectedProduct?.name || '—' }}
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Bunga') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ form.interest_rate || 0 }}% / {{ t('bulan') }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Pajak') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ form.tax_rate || 0 }}%</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Setoran awal') }}</dt>
                                <dd class="font-semibold text-on-surface">{{ money(form.opening_amount || 0) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-on-surface-variant">{{ t('Biaya buka buku') }}</dt>
                                <dd class="font-semibold text-tertiary">−{{ money(form.opening_fee || 0) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3 border-t border-outline-variant pt-2">
                                <dt class="font-semibold text-on-surface">{{ t('Saldo awal') }}</dt>
                                <dd class="font-bold text-secondary">{{ money(netOpening) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 rounded-xl bg-surface/70 p-3 text-xs text-on-surface-variant">
                            {{ t('Format nomor rekening') }}:
                            <span class="font-mono font-semibold text-on-surface">{jenis}-{lokasi 3 digit}.{anggota}-{urutan 2 digit}</span>
                        </div>
                    </AppCard>

                    <AppCard>
                        <div class="flex items-start gap-3">
                            <AppIcon name="info" tone="info" />
                            <p class="text-sm text-on-surface-variant">
                                {{ t('Biaya buka buku dicatat sebagai mutasi debit tersendiri, sama seperti SIUPK. Nomor rekening mengikuti urutan rekening anggota.') }}
                            </p>
                        </div>
                    </AppCard>

                    <div class="flex flex-col gap-2">
                        <AppButton type="submit" variant="primary" icon="save" :loading="form.processing" class="w-full justify-center">
                            {{ t('Buka Rekening') }}
                        </AppButton>
                        <Link href="/simpanan" class="w-full">
                            <AppButton variant="ghost" type="button" class="w-full justify-center">{{ t('Batal') }}</AppButton>
                        </Link>
                    </div>
                </aside>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
