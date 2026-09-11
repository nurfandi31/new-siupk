<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const page = usePage();

const props = defineProps({
    originalEntry: { type: Object, required: true },
    transactionTypes: { type: Array, required: true },
    labels: { type: Object, required: true },
    options: { type: Object, required: true },
    accountOptions: { type: Array, required: true },
    prefill: { type: Object, required: true },
});

const path = `/accounting/journals/${props.originalEntry.row_id}`;

const form = useForm({
    transaction_date: props.prefill.transaction_date,
    transaction_type: props.prefill.transaction_type,
    description: props.prefill.description ?? '',
    reference: props.prefill.reference ?? '',
    amount: props.prefill.amount ?? '',
    sumber_dana_row_id: props.prefill.sumber_dana_row_id ?? '',
    disimpan_ke_row_id: props.prefill.disimpan_ke_row_id ?? '',
    asset_name: props.prefill.asset_name ?? '',
    asset_quantity: props.prefill.asset_quantity ?? '',
    asset_unit_cost: props.prefill.asset_unit_cost ?? '',
    asset_useful_life_months: props.prefill.asset_useful_life_months ?? '',
    reason: '',
});

const ASSET_PURCHASE_TYPES = [
    'pembelian_aset_tanah',
    'pembelian_aset_gedung',
    'pembelian_aset_kendaraan',
    'pembelian_aset_peralatan',
    'pembelian_biaya_pendirian',
    'pembelian_lisensi',
    'pembelian_sewa_dibayar_dimuka',
    'pembelian_asuransi_dibayar_dimuka',
    'pembelian_inventaris',
];
const DEFAULT_LIFE = {
    pembelian_aset_tanah: 0,
    pembelian_aset_gedung: 240,
    pembelian_aset_kendaraan: 60,
    pembelian_aset_peralatan: 48,
    pembelian_biaya_pendirian: 60,
    pembelian_lisensi: 60,
    pembelian_sewa_dibayar_dimuka: 60,
    pembelian_asuransi_dibayar_dimuka: 60,
    pembelian_inventaris: 48,
};

const currentType = computed(() => form.transaction_type);
const isInventory = computed(() => ASSET_PURCHASE_TYPES.includes(currentType.value));
const sumberDanaLabel = computed(() => (props.labels[currentType.value]?.sumber_dana ?? 'Sumber Dana'));
const disimpanKeLabel = computed(() => (props.labels[currentType.value]?.disimpan_ke ?? 'Disimpan Ke'));
const sumberDanaOptions = computed(() => props.options[currentType.value]?.sumber_dana ?? []);
const disimpanKeOptions = computed(() => props.options[currentType.value]?.disimpan_ke ?? []);

const acquisitionCost = computed(() => {
    const qty = Number(form.asset_quantity || 0);
    const unit = Number(form.asset_unit_cost || 0);
    if (!Number.isFinite(qty) || !Number.isFinite(unit) || qty <= 0 || unit <= 0) return 0;
    return Math.round(qty * unit);
});

function optionValue(opt) {
    return opt?.value ?? opt?.row_id ?? '';
}

function autoPickSingle(options, field) {
    if (!Array.isArray(options) || options.length !== 1) return;
    const only = optionValue(options[0]);
    if (only !== '' && only != null && !form[field]) form[field] = only;
}

watch(currentType, (type, prev) => {
    if (!type) return;
    if (type !== prev) {
        if (!isInventory.value) {
            form.asset_name = '';
            form.asset_quantity = '';
            form.asset_unit_cost = '';
            form.asset_useful_life_months = '';
        } else {
            const life = DEFAULT_LIFE[type];
            if (life !== undefined && !form.asset_useful_life_months) form.asset_useful_life_months = life;
            if (!form.asset_quantity) form.asset_quantity = 1;
        }
    }
    autoPickSingle(sumberDanaOptions.value, 'sumber_dana_row_id');
    autoPickSingle(disimpanKeOptions.value, 'disimpan_ke_row_id');
}, { immediate: true });

watch(acquisitionCost, (value) => {
    if (!isInventory.value) return;
    form.amount = value > 0 ? value : '';
});

function submit() {
    if (isInventory.value) {
        form.amount = acquisitionCost.value > 0 ? acquisitionCost.value : form.amount;
        if (!form.description) {
            const qty = Number(form.asset_quantity || 0);
            form.description = `Pembelian inventaris: ${form.asset_name || 'barang'}${qty ? ` (${qty} unit)` : ''}`;
        }
    }
    form.put(path, {
        preserveScroll: true,
    });
}

function cancel() {
    window.history.length > 1 ? window.history.back() : (window.location.href = '/accounting/journals');
}

function currency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0);
}

function transactionTypeLabel(value) {
    return props.transactionTypes.find((type) => type.value === value)?.label ?? value ?? '—';
}
</script>

<template>
    <Head :title="`Koreksi Jurnal #${originalEntry.id}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <header>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Koreksi Transaksi</p>
                <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">Koreksi Jurnal #{{ originalEntry.id }}</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    Jurnal lama akan di-<strong>reverse</strong> dan diganti dengan jurnal baru menggunakan data di bawah ini.
                    Audit trail tetap utuh.
                </p>
            </header>

            <AppCard>
                <div class="flex flex-wrap items-start gap-3">
                    <div class="grid size-12 shrink-0 place-items-center rounded-full bg-warning-container text-warning">
                        <AppIcon name="warning" class="text-2xl" />
                    </div>
                    <div class="flex-1">
                        <p class="text-base font-bold text-primary">Perhatian</p>
                        <ul class="mt-2 space-y-1 text-sm text-on-surface-variant">
                            <li>
                                <span class="font-semibold">Tanggal:</span>
                                {{ originalEntry.transaction_date }} ·
                                <span class="font-semibold">Jenis:</span>
                                {{ transactionTypeLabel(originalEntry.transaction_type) }}
                            </li>
                            <li>
                                <span class="font-semibold">Nominal:</span>
                                {{ currency(originalEntry.amount) }}
                            </li>
                            <li v-if="originalEntry.description" class="text-on-surface-variant">
                                <span class="font-semibold">Keterangan:</span> {{ originalEntry.description }}
                            </li>
                        </ul>
                        <p class="mt-2 text-sm text-warning">
                            Jurnal lama tidak berubah sampai Anda klik <strong>Simpan Koreksi</strong>.
                            Kedua jurnal (reversal + baru) akan dibuat bersamaan dalam satu transaksi.
                        </p>
                    </div>
                </div>
            </AppCard>

            <AppCard v-if="page.props.errors?.reason" class="border border-error/40 bg-error-container/40">
                <p class="text-sm text-error">{{ page.props.errors.reason }}</p>
            </AppCard>

            <AppCard>
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-primary">Form Koreksi</h2>
                            <p class="mt-1 text-sm text-on-surface-variant">Lengkapi data jurnal baru di bawah ini.</p>
                        </div>
                        <AppBadge tone="warning">Edit</AppBadge>
                    </div>
                </template>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="form.transaction_date" label="Tanggal Transaksi" required :error="form.errors.transaction_date" />
                        <SmartSelect v-model="form.transaction_type" :options="props.transactionTypes" label="Jenis Transaksi" placeholder="Pilih jenis transaksi" required :error="form.errors.transaction_type" />
                    </div>

                    <AppTextarea
                        v-if="!isInventory"
                        v-model="form.description"
                        label="Keterangan / Deskripsi"
                        required
                        :error="form.errors.description"
                        placeholder="Contoh: Pencatatan biaya operasional bulanan"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <SmartSelect
                            v-model="form.sumber_dana_row_id"
                            :options="sumberDanaOptions"
                            :label="sumberDanaLabel + ' (Kredit)'"
                            placeholder="Pilih akun"
                            :disabled="!currentType"
                            required
                            :error="form.errors.sumber_dana_row_id"
                        />
                        <SmartSelect
                            v-model="form.disimpan_ke_row_id"
                            :options="disimpanKeOptions"
                            :label="disimpanKeLabel + ' (Debit)'"
                            placeholder="Pilih akun"
                            :disabled="!currentType"
                            required
                            :error="form.errors.disimpan_ke_row_id"
                        />
                    </div>

                    <p v-if="form.errors.sumber_dana_row_id || form.errors.disimpan_ke_row_id" class="text-sm text-error">
                        {{ form.errors.sumber_dana_row_id || form.errors.disimpan_ke_row_id }}
                    </p>

                    <template v-if="isInventory">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppInput v-model="form.reference" label="Relasi" :error="form.errors.reference" placeholder="No referensi / vendor" />
                            <AppInput v-model="form.asset_name" label="Nama Barang" required :error="form.errors.asset_name" placeholder="Contoh: Laptop" />
                            <AppInput v-model="form.asset_quantity" label="Jml. Unit" type="number" min="1" required :error="form.errors.asset_quantity" placeholder="1" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <AppCurrencyInput v-model="form.asset_unit_cost" label="Harga Satuan" icon="payments" :min="1" required :error="form.errors.asset_unit_cost" placeholder="0" />
                            <AppInput
                                v-model="form.asset_useful_life_months"
                                label="Umur Eko. (bulan)"
                                type="number"
                                :min="currentType === 'pembelian_aset_tanah' ? 0 : 1"
                                required
                                :error="form.errors.asset_useful_life_months"
                                :placeholder="currentType === 'pembelian_aset_tanah' ? '0' : '48'"
                                :hint="currentType === 'pembelian_aset_tanah' ? 'Tanah: 0 = tidak disusutkan' : null"
                            />
                            <AppCurrencyInput v-model="form.amount" label="Harga Perolehan" icon="payments" :min="1" required readonly :error="form.errors.amount" placeholder="0" hint="Otomatis: unit × harga satuan" />
                        </div>
                    </template>

                    <template v-else>
                        <AppInput v-model="form.reference" label="Relasi (opsional)" :error="form.errors.reference" placeholder="No referensi / catatan tambahan" />
                        <AppCurrencyInput v-model="form.amount" label="Nominal" icon="payments" :min="1" required :error="form.errors.amount" placeholder="0" />
                    </template>

                    <AppTextarea
                        v-model="form.reason"
                        label="Alasan Koreksi"
                        placeholder="Contoh: Salah nominal, akun kredit tertukar, dll."
                        :error="form.errors.reason"
                        hint="Wajib diisi. Disimpan di jurnal reversal dan jurnal baru untuk audit trail."
                    />

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <AppButton variant="secondary" type="button" @click="cancel">Batal</AppButton>
                        <AppButton type="submit" variant="warning" :loading="form.processing" :disabled="form.processing" icon="edit">
                            Simpan Koreksi
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
