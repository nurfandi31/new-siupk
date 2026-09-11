<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenants: { type: Array, required: true },
    subscriptions: { type: Array, default: () => [] },
    selected_tenant_id: { type: Number, default: null },
});

const form = useForm({
    tenant_id: props.selected_tenant_id || '',
    purpose: 'other',
    subscription_id: '',
    amount: '',
    currency: 'IDR',
    due_at: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
    description: '',
    notes: '',
    status: 'issued',
    blocks_access: false,
});

const purposeOptions = [
    { value: 'subscription', label: 'Langganan / perpanjangan' },
    { value: 'setup', label: 'Biaya setup / onboarding' },
    { value: 'support', label: 'Dukungan / maintenance' },
    { value: 'training', label: 'Pelatihan' },
    { value: 'custom_dev', label: 'Pengembangan custom' },
    { value: 'other', label: 'Lainnya' },
];

const currencyOptions = [
    { value: 'IDR', label: 'IDR — Rupiah' },
    { value: 'USD', label: 'USD — US Dollar' },
];

const statusOptions = [
    { value: 'issued', label: 'Terbitkan sekarang' },
    { value: 'draft', label: 'Draft' },
];

const tenantOptions = computed(() =>
    props.tenants.map((t) => ({ value: t.row_id, label: `${t.name} (${t.code})` })),
);

const needsSubscription = computed(() => form.purpose === 'subscription');

const subscriptionOptions = computed(() => {
    const rows = props.subscriptions.filter(
        (s) => !form.tenant_id || Number(s.tenant_id) === Number(form.tenant_id),
    );
    return [
        { value: '', label: needsSubscription.value ? '— Pilih langganan —' : '— Tanpa langganan —' },
        ...rows.map((s) => ({
            value: s.row_id,
            label: `#${s.row_id} · ${s.plan?.name || 'plan'} · ${s.status}`,
        })),
    ];
});

watch(() => form.purpose, (value) => {
    if (value !== 'subscription') {
        form.subscription_id = '';
    }
});

function submit() {
    form.post('/admin/invoices');
}
</script>

<template>
    <Head title="Buat Invoice" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <Link href="/admin/invoices" class="text-sm font-semibold text-primary">← Daftar invoice</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Buat Invoice</h1>
                <p class="mt-1 text-on-surface-variant">Tagihan ke tenant untuk langganan, setup, support, training, custom, atau keperluan lain.</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SmartSelect
                            v-model="form.tenant_id"
                            class="min-w-0"
                            label="Tenant"
                            :options="tenantOptions"
                            searchable
                            required
                            :error="form.errors.tenant_id"
                        />
                        <SmartSelect
                            v-model="form.purpose"
                            class="min-w-0"
                            label="Keperluan"
                            :options="purposeOptions"
                            required
                            :error="form.errors.purpose"
                        />
                    </div>

                    <SmartSelect
                        v-if="needsSubscription"
                        v-model="form.subscription_id"
                        label="Langganan"
                        :options="subscriptionOptions"
                        required
                        :error="form.errors.subscription_id"
                    />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <AppCurrencyInput
                            v-model="form.amount"
                            class="min-w-0"
                            label="Nominal"
                            :min="0.01"
                            :step="0.01"
                            required
                            :error="form.errors.amount"
                        />
                        <AppDatePicker
                            v-model="form.due_at"
                            class="min-w-0"
                            label="Jatuh tempo"
                            clearable
                            :error="form.errors.due_at"
                        />
                        <SmartSelect
                            v-model="form.currency"
                            class="min-w-0"
                            label="Mata uang"
                            :options="currencyOptions"
                            required
                            :error="form.errors.currency"
                        />
                        <SmartSelect
                            v-model="form.status"
                            class="min-w-0"
                            label="Status"
                            :options="statusOptions"
                            :error="form.errors.status"
                        />
                    </div>

                    <!-- Blocking Switch Option -->
                    <div class="rounded-xl border border-outline-variant bg-surface-container-low/50 p-4 transition-colors hover:bg-surface-container-low">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input
                                v-model="form.blocks_access"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-outline-variant text-primary focus:ring-primary"
                            />
                            <div class="space-y-0.5">
                                <span class="text-sm font-bold text-primary flex items-center gap-1.5">
                                    <span>Blokir Akses Operasional Tenant Jika Belum Lunas</span>
                                    <span v-if="form.blocks_access" class="rounded bg-error/10 px-1.5 py-0.2 text-[10px] font-bold text-error">Blokir Aktif</span>
                                </span>
                                <p class="text-xs text-on-surface-variant leading-relaxed">
                                    Jika diaktifkan, pengguna tenant tidak dapat mengakses fitur operasional (hanya dapat membuka halaman pembayaran invoice) sampai tagihan ini diselesaikan.
                                </p>
                            </div>
                        </label>
                    </div>

                    <AppInput
                        v-model="form.description"
                        label="Deskripsi"
                        hint="Ringkas isi tagihan, mis. “Setup BUMDes X” atau “Pelatihan kasir batch 2”."
                        :error="form.errors.description"
                    />
                    <AppTextarea v-model="form.notes" label="Catatan internal" :error="form.errors.notes" />

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/invoices"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>