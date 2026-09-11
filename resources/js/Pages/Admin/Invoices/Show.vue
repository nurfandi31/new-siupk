<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
});

const purposeLabels = {
    subscription: 'Langganan / perpanjangan',
    setup: 'Biaya setup / onboarding',
    support: 'Dukungan / maintenance',
    training: 'Pelatihan',
    custom_dev: 'Pengembangan custom',
    other: 'Lainnya',
};

const manualForm = useForm({
    amount: props.invoice.remaining,
    paid_at: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
});

const voidForm = useForm({});
const tripayForm = useForm({});
const toggleBlockingForm = useForm({});

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function tone(status) {
    if (status === 'paid') return 'success';
    if (status === 'overdue' || status === 'void' || status === 'failed') return 'error';
    if (status === 'partially_paid' || status === 'pending') return 'warning';
    return 'neutral';
}

function recordManual() {
    manualForm.post(`/admin/invoices/${props.invoice.row_id}/payments/manual`, {
        preserveScroll: true,
        onSuccess: () => manualForm.reset('reference', 'notes'),
    });
}

function initiateTripay() {
    tripayForm.post(`/admin/invoices/${props.invoice.row_id}/payments/tripay`, { preserveScroll: true });
}

function toggleBlocking() {
    toggleBlockingForm.post(`/admin/invoices/${props.invoice.row_id}/toggle-blocking`, { preserveScroll: true });
}

const { confirm: confirmAction } = useConfirm();

async function voidInvoice() {
    if (!await confirmAction({ title: 'Batalkan Invoice', message: 'Batalkan invoice ini? Tindakan ini tidak dapat dibatalkan.' })) return;
    voidForm.post(`/admin/invoices/${props.invoice.row_id}/void`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="invoice.number" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <Link href="/admin/invoices" class="text-sm font-semibold text-primary">← Daftar invoice</Link>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ invoice.number }}</h1>
                        <AppBadge :tone="tone(invoice.status)">{{ invoice.status }}</AppBadge>
                        <span v-if="invoice.blocks_access" class="rounded bg-error/15 px-2 py-0.5 text-xs font-bold text-error">
                            ⛔ Memblokir Akses Tenant
                        </span>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        <Link v-if="invoice.tenant" :href="`/admin/tenants/${invoice.tenant.row_id}`" class="font-semibold text-primary">{{ invoice.tenant.name }}</Link>
                        <span v-else>—</span>
                        · jatuh tempo {{ invoice.due_at || '—' }}
                    </p>
                </div>
                <AppButton
                    v-if="invoice.is_open && invoice.status !== 'partially_paid'"
                    variant="danger"
                    :loading="voidForm.processing"
                    @click="voidInvoice"
                >Void</AppButton>
            </header>

            <!-- Access Blocking Control Card -->
            <div
                class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 rounded-xl p-4 border"
                :class="invoice.blocks_access ? 'border-error/30 bg-error/10' : 'border-outline-variant bg-surface-container-low'"
            >
                <div class="flex items-center gap-3">
                    <AppIcon :name="invoice.blocks_access ? 'block' : 'check_circle'" :class="invoice.blocks_access ? 'text-error' : 'text-on-surface-variant'" class="text-2xl" />
                    <div>
                        <h4 class="text-sm font-bold" :class="invoice.blocks_access ? 'text-error' : 'text-primary'">
                            {{ invoice.blocks_access ? 'Opsi Blokir Akses: AKTIF' : 'Opsi Blokir Akses: Nonaktif' }}
                        </h4>
                        <p class="text-xs text-on-surface-variant">
                            {{ invoice.blocks_access ? 'Akses pengguna tenant diblokir sampai invoice ini dilunasi.' : 'Tenant tetap dapat menggunakan aplikasi walau invoice ini belum dibayar.' }}
                        </p>
                    </div>
                </div>
                <form v-if="invoice.is_open" @submit.prevent="toggleBlocking">
                    <AppButton size="compact" :variant="invoice.blocks_access ? 'secondary' : 'danger'" :loading="toggleBlockingForm.processing">
                        {{ invoice.blocks_access ? 'Nonaktifkan Blokir' : 'Aktifkan Blokir Akses' }}
                    </AppButton>
                </form>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <AppCard class="lg:col-span-2">
                    <h2 class="font-bold text-primary">Ringkasan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-on-surface-variant">Nominal</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.amount, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Dibayar</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.amount_paid, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Sisa</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.remaining, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Keperluan</dt><dd class="font-semibold text-primary">{{ purposeLabels[invoice.purpose] || invoice.purpose || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Diterbitkan</dt><dd class="font-semibold text-primary">{{ invoice.issued_at || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Dibuat oleh</dt><dd class="font-semibold text-primary">{{ invoice.creator?.name || 'Sistem' }}</dd></div>
                        <div v-if="invoice.description" class="sm:col-span-2"><dt class="text-sm text-on-surface-variant">Deskripsi</dt><dd class="text-primary">{{ invoice.description }}</dd></div>
                        <div v-if="invoice.notes" class="sm:col-span-2"><dt class="text-sm text-on-surface-variant">Catatan internal</dt><dd class="whitespace-pre-line text-sm text-primary">{{ invoice.notes }}</dd></div>
                    </dl>
                </AppCard>

                <AppCard v-if="invoice.is_open" class="space-y-4">
                    <h2 class="font-bold text-primary">Catat Pembayaran Manual</h2>
                    <form class="space-y-3" @submit.prevent="recordManual">
                        <AppCurrencyInput
                            v-model="manualForm.amount"
                            label="Nominal"
                            :min="0.01"
                            :step="0.01"
                            required
                            :error="manualForm.errors.amount"
                        />
                        <AppDatePicker
                            v-model="manualForm.paid_at"
                            label="Tanggal bayar"
                            required
                            :error="manualForm.errors.paid_at"
                        />
                        <AppInput v-model="manualForm.reference" label="Nomor referensi / bukti" :error="manualForm.errors.reference" />
                        <AppTextarea v-model="manualForm.notes" label="Catatan" :error="manualForm.errors.notes" />
                        <AppButton type="submit" class="w-full" :loading="manualForm.processing" icon="check">Catat Lunas / Cicil</AppButton>
                    </form>

                    <div class="pt-4 border-t border-outline-variant">
                        <AppButton variant="secondary" class="w-full" :loading="tripayForm.processing" @click="initiateTripay">
                            Generate Link Pembayaran Online
                        </AppButton>
                    </div>
                </AppCard>
            </div>

            <!-- Payments List -->
            <AppCard v-if="payments.length" class="space-y-4">
                <h2 class="font-bold text-primary">Riwayat Pembayaran</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-outline-variant text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="py-2.5">Tanggal</th>
                                <th class="py-2.5">Metode</th>
                                <th class="py-2.5">Nominal</th>
                                <th class="py-2.5">Status</th>
                                <th class="py-2.5">Referensi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            <tr v-for="p in payments" :key="p.row_id">
                                <td class="py-2.5">{{ p.paid_at || '—' }}</td>
                                <td class="py-2.5 font-semibold text-primary">{{ p.method }}</td>
                                <td class="py-2.5 font-bold">{{ money(p.amount) }}</td>
                                <td class="py-2.5"><AppBadge :tone="tone(p.status)">{{ p.status }}</AppBadge></td>
                                <td class="py-2.5 text-xs text-on-surface-variant">{{ p.reference || p.tripay_reference || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>