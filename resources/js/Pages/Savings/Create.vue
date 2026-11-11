<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
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
        <div class="mx-auto max-w-6xl space-y-4">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-primary">{{ t('Buka Rekening Simpanan') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ t('Isi data rekening. Nomor rekening dibuat otomatis.') }}</p>
                </div>
                <AppButton variant="secondary" icon="print" class="w-full justify-center sm:w-auto" @click="printBlankForm">
                    {{ t('Formulir Kosong') }}
                </AppButton>
            </header>

            <form class="space-y-4" @submit.prevent="submit">
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2.5">
                            <AppIcon name="person_add" tone="success" container-size="9" />
                            <div>
                                <p class="font-semibold text-on-surface">{{ t('Data Rekening') }}</p>
                                <p class="text-xs text-on-surface-variant">{{ t('Data anggota, tanggal, dan setoran.') }}</p>
                            </div>
                        </div>
                    </template>

                    <div class="grid gap-4 sm:grid-cols-2">
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

                        <AppDatePicker
                            v-model="form.opened_at"
                            :label="t('Tanggal Buka')"
                            :error="form.errors.opened_at"
                        />

                        <AppDatePicker
                            v-if="requiresClosingDate"
                            v-model="form.minimum_closing_date"
                            :label="t('Minimal Tutup')"
                            :error="form.errors.minimum_closing_date"
                        />

                        <AppCurrencyInput
                            v-model="form.opening_amount"
                            :label="t('Setoran Awal')"
                            :icon="null"
                            :show-stepper="false"
                            :error="form.errors.opening_amount"
                        />

                        <SmartSelect
                            v-model="form.authority_type"
                            :label="t('Status')"
                            :options="authorityOptions"
                            :error="form.errors.authority_type"
                        />

                        <AppInput
                            v-model="form.relationship"
                            :label="t('Hubungan')"
                            :error="form.errors.relationship"
                            placeholder="Suami / Istri / Anak"
                        />

                        <div :class="!requiresClosingDate ? 'sm:col-span-2' : ''">
                            <AppInput
                                v-model="form.guardian_name"
                                :label="t('Pengampu / Ahli Waris')"
                                :error="form.errors.guardian_name"
                                placeholder="Nama pengampu bila diwakilkan"
                            />
                        </div>

                        <template v-if="isInstitution">
                            <AppInput
                                v-model="form.institution_name"
                                :label="t('Nama Lembaga')"
                                :error="form.errors.institution_name"
                            />
                            <AppInput
                                v-model="form.institution_position"
                                :label="t('Jabatan')"
                                :error="form.errors.institution_position"
                            />
                        </template>

                        <div class="sm:col-span-2">
                            <AppTextarea
                                v-model="form.notes"
                                :label="t('Catatan')"
                                :error="form.errors.notes"
                                :rows="2"
                                :placeholder="t('Catatan internal / nama program')"
                            />
                        </div>
                    </div>
                </AppCard>

                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2.5">
                            <AppIcon name="percent" tone="primary" container-size="9" />
                            <div>
                                <p class="font-semibold text-on-surface">{{ t('Bunga, Pajak & Biaya') }}</p>
                                <p class="text-xs text-on-surface-variant">{{ t('Default dari jenis simpanan.') }}</p>
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
                            :label="t('Bunga (%)')"
                            :error="form.errors.interest_rate"
                        />
                        <AppInput
                            v-model="form.tax_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            :label="t('Pajak (%)')"
                            :error="form.errors.tax_rate"
                        />
                        <AppInput
                            v-model="form.admin_fee"
                            type="number"
                            step="0.01"
                            min="0"
                            :label="t('Admin / Bulan')"
                            :error="form.errors.admin_fee"
                        />
                        <AppCurrencyInput
                            v-model="form.opening_fee"
                            :label="t('Biaya Buka')"
                            :icon="null"
                            :show-stepper="false"
                            :error="form.errors.opening_fee"
                        />
                    </div>
                </AppCard>

                <AppCard class="border border-outline-variant bg-gradient-to-br from-secondary-container/40 to-surface-container-lowest">
                    <template #header>
                        <div class="flex w-full flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <AppIcon name="savings" tone="primary" container-size="9" />
                                <div>
                                    <p class="font-semibold text-on-surface">{{ t('Pratinjau Rekening') }}</p>
                                    <p class="font-mono text-xs text-on-surface-variant">{{ accountPreview }}</p>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-on-surface">{{ selectedProduct?.name || '—' }}</span>
                        </div>
                    </template>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl bg-surface/70 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Bunga') }}</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">{{ form.interest_rate || 0 }}% / {{ t('bulan') }}</p>
                        </div>
                        <div class="rounded-xl bg-surface/70 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Pajak') }}</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">{{ form.tax_rate || 0 }}%</p>
                        </div>
                        <div class="rounded-xl bg-surface/70 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Setoran Awal') }}</p>
                            <p class="mt-1 text-sm font-bold text-on-surface">{{ money(form.opening_amount || 0) }}</p>
                        </div>
                        <div class="rounded-xl bg-surface/70 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Biaya Buka') }}</p>
                            <p class="mt-1 text-sm font-bold text-tertiary">−{{ money(form.opening_fee || 0) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 rounded-xl border border-outline-variant bg-surface/80 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Saldo Awal') }}</p>
                            <p class="text-2xl font-black text-secondary">{{ money(netOpening) }}</p>
                        </div>
                        <AppButton type="submit" variant="primary" icon="save" :loading="form.processing" class="w-full justify-center sm:w-auto">
                            {{ t('Buka Rekening') }}
                        </AppButton>
                    </div>
                </AppCard>

                <AppCard :padded="false" class="border border-outline-variant">
                    <div class="flex items-start gap-2.5 px-4 py-3 text-xs text-on-surface-variant">
                        <AppIcon name="info" tone="info" />
                        <p>{{ t('Nomor rekening otomatis. Biaya buka buku dicatat sebagai mutasi debit.') }}</p>
                    </div>
                </AppCard>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
