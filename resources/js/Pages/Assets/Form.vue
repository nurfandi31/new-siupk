<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppCurrencyInput from '../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppInput from '../../Components/AppInput.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    asset: { type: Object, required: true },
    categories: { type: Array, required: true },
    status_options: { type: Array, required: true },
    units: { type: Array, required: true },
});

const form = useForm({
    name: props.asset?.name || '',
    asset_code: props.asset?.asset_code || '',
    asset_category_row_id: props.asset?.asset_category_row_id || '',
    organization_unit_row_id: props.asset?.organization_unit_row_id || '',
    purchased_at: props.asset?.purchased_at || '',
    quantity: props.asset?.quantity ?? 1,
    unit_cost: props.asset?.unit_cost ?? 0,
    useful_life_months: props.asset?.useful_life_months ?? '',
    status: props.asset?.status || 'good',
    validated_at: props.asset?.validated_at || '',
    status_notes: '',
});

const categoryOptions = props.categories.map((c) => ({
    value: String(c.value),
    label: c.label,
    months: c.default_useful_life_months,
}));
const unitOptions = [{ value: '', label: '— Tanpa unit —' }, ...props.units.map((u) => ({ value: String(u.value), label: u.label }))];

function submit() {
    form.transform(() => ({
        ...form.data(),
        asset_category_row_id: form.asset_category_row_id || null,
        organization_unit_row_id: form.organization_unit_row_id || null,
        useful_life_months: form.useful_life_months === '' ? null : Number(form.useful_life_months),
        quantity: Number(form.quantity) || 1,
        unit_cost: Number(form.unit_cost) || 0,
    })).put(`/accounting/assets/${props.asset.row_id}`);
}
</script>

<template>
    <Head title="Edit Inventaris" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <header class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Edit Inventaris</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Meta &amp; status register. Pembelian baru lewat Jurnal Umum — ubah di sini tidak membuat jurnal.
                    </p>
                </div>
                <Link :href="`/accounting/assets/${asset.row_id}`">
                    <AppButton variant="ghost" icon="arrow_back">Kembali</AppButton>
                </Link>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <AppInput v-model="form.name" label="Nama barang" required :error="form.errors.name" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput v-model="form.asset_code" label="Kode aset" :error="form.errors.asset_code" hint="Opsional, unik" />
                        <SmartSelect
                            v-model="form.asset_category_row_id"
                            label="Kategori"
                            :options="[{ value: '', label: '— Pilih —' }, ...categoryOptions]"
                            :error="form.errors.asset_category_row_id"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppDatePicker v-model="form.purchased_at" label="Tanggal beli" :error="form.errors.purchased_at" />
                        <SmartSelect
                            v-model="form.organization_unit_row_id"
                            label="Unit / desa"
                            :options="unitOptions"
                            :error="form.errors.organization_unit_row_id"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <AppInput v-model="form.quantity" label="Jumlah" type="number" :error="form.errors.quantity" />
                        <AppCurrencyInput v-model="form.unit_cost" label="Harga satuan" :error="form.errors.unit_cost" />
                        <AppInput
                            v-model="form.useful_life_months"
                            label="Umur ekonomis (bulan)"
                            type="number"
                            hint="0 / kosong = tidak disusutkan"
                            :error="form.errors.useful_life_months"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <SmartSelect v-model="form.status" label="Status" :options="status_options" :error="form.errors.status" />
                        <AppDatePicker v-model="form.validated_at" label="Tgl validasi status" :error="form.errors.validated_at" />
                    </div>
                    <AppTextarea
                        v-model="form.status_notes"
                        label="Catatan perubahan status"
                        :error="form.errors.status_notes"
                        hint="Diisi jika status berubah"
                    />
                    <div class="flex justify-end gap-2 pt-2">
                        <Link :href="`/accounting/assets/${asset.row_id}`">
                            <AppButton variant="ghost" type="button">Batal</AppButton>
                        </Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
