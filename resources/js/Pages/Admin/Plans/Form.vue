<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    plan: { type: Object, default: null },
});

const editing = !!props.plan;

const form = useForm({
    code: props.plan?.code || '',
    name: props.plan?.name || '',
    price_amount: props.plan?.price_amount ?? 0,
    currency: props.plan?.currency || 'IDR',
    billing_period: props.plan?.billing_period || 'monthly',
    is_active: props.plan?.is_active ?? true,
    features: props.plan?.features || {},
});

const periodOptions = [
    { value: 'monthly', label: 'Bulanan' },
    { value: 'yearly', label: 'Tahunan' },
];

const currencyOptions = [
    { value: 'IDR', label: 'IDR — Rupiah' },
    { value: 'USD', label: 'USD — US Dollar' },
];

function submit() {
    if (editing) form.put(`/admin/plans/${props.plan.row_id}`);
    else form.post('/admin/plans');
}
</script>

<template>
    <Head :title="editing ? 'Edit Plan' : 'Tambah Plan'" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <Link href="/admin/plans" class="text-sm font-semibold text-primary">← Daftar plan</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">{{ editing ? 'Edit Plan' : 'Tambah Plan' }}</h1>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <AppInput v-model="form.code" class="min-w-0" label="Kode" required :error="form.errors.code" :disabled="editing" />
                        <AppInput v-model="form.name" class="min-w-0" label="Nama" required :error="form.errors.name" />
                        <AppCurrencyInput v-model="form.price_amount" class="min-w-0" label="Harga" :min="0" :step="0.01" required :error="form.errors.price_amount" />
                        <SmartSelect v-model="form.currency" class="min-w-0" label="Mata uang" :options="currencyOptions" required :error="form.errors.currency" />
                        <SmartSelect v-model="form.billing_period" class="min-w-0" label="Periode" :options="periodOptions" required :error="form.errors.billing_period" />
                        <AppSwitch v-model="form.is_active" class="min-w-0" field label="Status" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <Link href="/admin/plans"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>
