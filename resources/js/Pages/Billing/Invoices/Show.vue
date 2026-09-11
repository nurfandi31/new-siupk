<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import AppRadioGroup from '../../../Components/AppRadioGroup.vue';
import { useCan } from '../../../composables/useCan';
import { useMoney } from '../../../composables/useMoney';

const { can } = useCan();
const { money } = useMoney();

const props = defineProps({
    invoice: { type: Object, required: true },
    channels: { type: Array, default: () => [] },
    active_payment: { type: Object, default: null },
    payments: { type: Array, default: () => [] },
});

const purposeLabels = {
    subscription: 'Langganan / perpanjangan aplikasi',
    setup: 'Biaya setup / onboarding',
    support: 'Dukungan / maintenance',
    training: 'Pelatihan',
    custom_dev: 'Pengembangan custom',
    other: 'Lainnya',
};

// Selected channel state
const selectedCategory = ref('qris'); // 'qris' | 'virtual_account'
const selectedMethod = ref('QRIS2');
const copiedField = ref('');

const categoryOptions = [
    { value: 'qris', label: 'QRIS (Scan Langsung)', icon: 'qr_code_scanner' },
    { value: 'virtual_account', label: 'Virtual Account Bank (VA)', icon: 'account_balance' },
];

const qrisChannels = computed(() => props.channels.filter((c) => c.group === 'qris' || c.code.includes('QRIS')));
const vaChannels = computed(() => props.channels.filter((c) => c.group === 'virtual_account' || c.code.endsWith('VA')));

// Auto-select first channel in list
if (props.active_payment?.method_code) {
    selectedMethod.value = props.active_payment.method_code;
    selectedCategory.value = props.active_payment.method_code.includes('QRIS') ? 'qris' : 'virtual_account';
}

const payForm = useForm({
    payment_method: selectedMethod.value,
});

const checkingStatus = ref(false);

function selectChannel(code, category) {
    selectedMethod.value = code;
    selectedCategory.value = category;
    payForm.payment_method = code;
}

function pay() {
    payForm.payment_method = selectedMethod.value;
    payForm.post(`/billing/invoices/${props.invoice.row_id}/pay`, {
        preserveScroll: true,
    });
}

function checkStatus() {
    checkingStatus.value = true;
    router.post(`/billing/invoices/${props.invoice.row_id}/check-status`, {}, {
        preserveScroll: true,
        onFinish: () => {
            checkingStatus.value = false;
        },
    });
}

function copyToClipboard(text, fieldName) {
    if (!text) return;
    navigator.clipboard.writeText(String(text));
    copiedField.value = fieldName;
    setTimeout(() => {
        if (copiedField.value === fieldName) copiedField.value = '';
    }, 2500);
}


function tone(status) {
    if (status === 'paid') return 'success';
    if (status === 'overdue' || status === 'void' || status === 'failed' || status === 'expired' || status === 'cancelled') return 'error';
    if (status === 'partially_paid' || status === 'pending') return 'warning';
    return 'neutral';
}

function statusLabel(status) {
    return ({
        issued: 'Belum dibayar',
        partially_paid: 'Sebagian',
        paid: 'Lunas',
        overdue: 'Terlambat',
        void: 'Dibatalkan',
        draft: 'Draft',
        pending: 'Menunggu Pembayaran',
        failed: 'Gagal',
        expired: 'Kedaluwarsa',
        cancelled: 'Dibatalkan',
    })[status] || status;
}

function methodLabel(payment) {
    if (payment.method === 'manual') return 'Transfer Manual';
    if (payment.payment_name) return payment.payment_name;
    return 'Online (Tripay)';
}
</script>

<template>
    <Head :title="`Tagihan ${invoice.number}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header -->
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <Link href="/billing/invoices" class="text-sm font-semibold text-primary hover:underline">
                        ← Kembali ke Daftar Tagihan
                    </Link>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ invoice.number }}</h1>
                        <AppBadge :tone="tone(invoice.status)">{{ statusLabel(invoice.status) }}</AppBadge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ purposeLabels[invoice.purpose] || invoice.purpose || 'Tagihan' }}
                        · Jatuh tempo: <strong>{{ invoice.due_at || '—' }}</strong>
                        <span v-if="invoice.subscription"> · Paket: {{ invoice.subscription.plan?.name }}</span>
                    </p>
                </div>

                <div v-if="invoice.is_open && active_payment" class="flex flex-wrap gap-2">
                    <AppButton
                        variant="secondary"
                        icon="refresh"
                        :loading="checkingStatus"
                        @click="checkStatus"
                    >
                        Cek Status Pembayaran
                    </AppButton>
                </div>
            </header>

            <!-- Active In-App Payment Card (If pending Tripay payment exists) -->
            <div v-if="invoice.is_open && active_payment" class="space-y-4">
                <AppCard class="border-2 border-primary/20 bg-primary/5 p-6">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <span class="grid size-12 place-items-center rounded-xl bg-primary text-on-primary shadow-md">
                                <AppIcon :name="active_payment.method_code.includes('QRIS') ? 'qr_code_scanner' : 'account_balance'" size="28" />
                            </span>
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">Instruksi Pembayaran Aktif</span>
                                <h2 class="text-xl font-bold text-on-surface">{{ active_payment.payment_name }}</h2>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-on-surface-variant">Batas Waktu Pembayaran</span>
                            <p class="font-bold text-error">{{ active_payment.expired_time || '24 Jam' }}</p>
                        </div>
                    </div>

                    <!-- 1. Case: QRIS Code In-App Display -->
                    <div v-if="active_payment.qr_url" class="mt-6 flex flex-col items-center justify-center rounded-2xl bg-surface p-6 shadow-sm sm:flex-row sm:gap-8">
                        <div class="flex flex-col items-center">
                            <div class="relative rounded-2xl border-4 border-white bg-white p-3 shadow-lg">
                                <img
                                    :src="active_payment.qr_url"
                                    alt="QRIS Code"
                                    class="size-56 object-contain"
                                />
                            </div>
                            <p class="mt-3 text-xs font-semibold text-on-surface-variant">NMID / Standar QRIS Nasional</p>
                        </div>

                        <div class="mt-6 max-w-md space-y-4 sm:mt-0">
                            <div>
                                <span class="text-xs font-semibold text-on-surface-variant">Total Tagihan</span>
                                <p class="text-3xl font-black text-primary">{{ money(active_payment.total_amount) }}</p>
                            </div>
                            <div class="rounded-xl bg-surface-container-low p-4 text-xs text-on-surface space-y-2">
                                <p class="font-bold text-primary">Cara Pembayaran QRIS:</p>
                                <ol class="list-decimal pl-4 space-y-1">
                                    <li>Buka aplikasi m-Banking (BCA, Mandiri, BRI, BNI, dll.) atau e-Wallet (GoPay, OVO, Dana, ShopeePay).</li>
                                    <li>Pilih menu <strong>Scan / Bayar QRIS</strong>.</li>
                                    <li>Arahkan kamera ke kode QR di layar ini.</li>
                                    <li>Periksa nama merchant dan nominal, lalu masukkan PIN Anda.</li>
                                </ol>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <AppButton variant="primary" icon="refresh" :loading="checkingStatus" @click="checkStatus">
                                    Saya Sudah Bayar
                                </AppButton>
                                <a :href="active_payment.qr_url" download="qris_invoice.png" target="_blank">
                                    <AppButton variant="secondary" icon="download">Unduh QR</AppButton>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Case: Bank Virtual Account In-App Display -->
                    <div v-else-if="active_payment.pay_code" class="mt-6 rounded-2xl bg-surface p-6 shadow-sm">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-4">
                                <div>
                                    <span class="text-xs font-semibold text-on-surface-variant">Nomor Virtual Account</span>
                                    <div class="mt-1 flex items-center gap-3">
                                        <span class="font-mono text-2xl font-black tracking-wider text-primary">
                                            {{ active_payment.pay_code }}
                                        </span>
                                        <AppButton
                                            size="compact"
                                            variant="secondary"
                                            icon="content_copy"
                                            @click="copyToClipboard(active_payment.pay_code, 'va')"
                                        >
                                            {{ copiedField === 'va' ? 'Tersalin ✓' : 'Salin VA' }}
                                        </AppButton>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-xs font-semibold text-on-surface-variant">Total Nominal Transfer</span>
                                    <div class="mt-1 flex items-center gap-3">
                                        <span class="text-2xl font-bold text-on-surface">
                                            {{ money(active_payment.total_amount) }}
                                        </span>
                                        <AppButton
                                            size="compact"
                                            variant="secondary"
                                            icon="content_copy"
                                            @click="copyToClipboard(active_payment.total_amount, 'amount')"
                                        >
                                            {{ copiedField === 'amount' ? 'Tersalin ✓' : 'Salin Nominal' }}
                                        </AppButton>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <AppButton variant="primary" icon="refresh" :loading="checkingStatus" @click="checkStatus">
                                        Saya Sudah Transfer
                                    </AppButton>
                                </div>
                            </div>

                            <!-- Payment Steps / Instructions -->
                            <div v-if="active_payment.instructions?.length" class="space-y-3">
                                <span class="text-xs font-bold uppercase tracking-wider text-primary">Panduan Pembayaran:</span>
                                <div class="max-h-60 overflow-y-auto space-y-2 pr-2">
                                    <div
                                        v-for="(inst, i) in active_payment.instructions"
                                        :key="i"
                                        class="rounded-xl border border-outline-variant/60 bg-surface-container-low/40 p-3"
                                    >
                                        <p class="font-bold text-xs text-primary">{{ inst.title }}</p>
                                        <ol class="mt-1 list-decimal pl-4 text-xs text-on-surface-variant space-y-0.5">
                                            <li v-for="(step, sIdx) in inst.steps" :key="sIdx" v-html="step"></li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- Main Content: Invoice Summary + Payment Channel Selection -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Summary Card -->
                <AppCard class="lg:col-span-1 h-fit">
                    <h2 class="font-bold text-primary text-lg">Rincian Tagihan</h2>
                    <dl class="mt-4 divide-y divide-outline-variant text-sm">
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-on-surface-variant">Nominal Tagihan</dt>
                            <dd class="font-bold text-on-surface">{{ money(invoice.amount, invoice.currency) }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-on-surface-variant">Sudah Dibayar</dt>
                            <dd class="font-bold text-secondary">{{ money(invoice.amount_paid, invoice.currency) }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-on-surface-variant font-semibold">Sisa Kewajiban</dt>
                            <dd class="text-lg font-black text-primary">{{ money(invoice.remaining, invoice.currency) }}</dd>
                        </div>
                        <div class="py-2.5">
                            <dt class="text-on-surface-variant">Keperluan</dt>
                            <dd class="font-semibold text-primary">{{ purposeLabels[invoice.purpose] || invoice.purpose }}</dd>
                        </div>
                        <div class="py-2.5">
                            <dt class="text-on-surface-variant">Deskripsi</dt>
                            <dd class="text-on-surface">{{ invoice.description || '—' }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between">
                            <dt class="text-on-surface-variant">Tanggal Diterbitkan</dt>
                            <dd class="text-on-surface">{{ invoice.issued_at || '—' }}</dd>
                        </div>
                    </dl>
                </AppCard>

                <!-- Payment Channels Card -->
                <AppCard v-if="invoice.is_open" class="lg:col-span-2 space-y-6">
                    <div>
                        <h2 class="font-bold text-primary text-lg">
                            {{ active_payment ? 'Pilih Metode Lain' : 'Pilih Metode Pembayaran Online' }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Pembayaran otomatis diverifikasi secara real-time via Tripay.
                        </p>
                    </div>

                    <AppRadioGroup
                        v-model="selectedCategory"
                        :options="categoryOptions"
                        label="Kategori Pembayaran"
                    />

                    <!-- Category 1: QRIS List -->
                    <div v-if="selectedCategory === 'qris'" class="space-y-3">
                        <div
                            v-for="ch in qrisChannels"
                            :key="ch.code"
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-4 transition"
                            :class="selectedMethod === ch.code ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/40 bg-surface'"
                            @click="selectChannel(ch.code, 'qris')"
                        >
                            <div class="flex items-center gap-3">
                                <span class="grid size-10 place-items-center rounded-lg bg-surface-container text-primary">
                                    <AppIcon name="qr_code_scanner" size="24" />
                                </span>
                                <div>
                                    <p class="font-bold text-sm text-primary">{{ ch.name }}</p>
                                    <p class="text-xs text-on-surface-variant">Semua Mobile Banking & E-Wallet (BCA, Mandiri, BRI, GoPay, Dana, dll.)</p>
                                </div>
                            </div>
                            <input
                                type="radio"
                                name="channel"
                                :value="ch.code"
                                :checked="selectedMethod === ch.code"
                                class="size-4 text-primary focus:ring-primary"
                            />
                        </div>
                    </div>

                    <!-- Category 2: Virtual Account List -->
                    <div v-if="selectedCategory === 'virtual_account'" class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="ch in vaChannels"
                            :key="ch.code"
                            class="flex cursor-pointer items-center justify-between rounded-xl border-2 p-3.5 transition"
                            :class="selectedMethod === ch.code ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/40 bg-surface'"
                            @click="selectChannel(ch.code, 'virtual_account')"
                        >
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-lg bg-surface-container text-primary font-bold text-xs">
                                    {{ ch.code.replace('VA', '') }}
                                </span>
                                <div>
                                    <p class="font-bold text-xs text-primary">{{ ch.name }}</p>
                                    <p class="text-[10px] text-on-surface-variant">Verifikasi Otomatis</p>
                                </div>
                            </div>
                            <input
                                type="radio"
                                name="channel"
                                :value="ch.code"
                                :checked="selectedMethod === ch.code"
                                class="size-4 text-primary focus:ring-primary"
                            />
                        </div>
                    </div>

                    <div class="pt-2">
                        <AppButton
                            class="w-full sm:w-auto"
                            icon="payments"
                            :loading="payForm.processing"
                            @click="pay"
                        >
                            {{ active_payment ? 'Ganti ke Metode Ini' : 'Dapatkan Kode Pembayaran' }}
                        </AppButton>
                    </div>
                </AppCard>

                <!-- Paid Status Card -->
                <AppCard v-else-if="invoice.status === 'paid'" class="lg:col-span-2 border border-secondary bg-secondary-container/30 p-6">
                    <div class="flex items-center gap-3">
                        <AppIcon name="check_circle" tone="success" size="28" container-size="12" container-shape="pill" />
                        <div>
                            <h2 class="text-xl font-bold text-secondary">Tagihan Telah Lunas</h2>
                            <p class="text-sm text-on-secondary-container">
                                Pembayaran telah diterima{{ invoice.paid_at ? ` pada ${invoice.paid_at}` : '' }}. Masa langganan aktif.
                            </p>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- Payment History Table -->
            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">Riwayat Transaksi Pembayaran</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3">Metode</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Nominal</th>
                                <th class="px-6 py-3">Referensi / Kode Bayar</th>
                                <th class="px-6 py-3">Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="payment in payments" :key="payment.row_id" class="hover:bg-surface-container-low/40">
                                <td class="px-6 py-3 font-semibold text-primary">{{ methodLabel(payment) }}</td>
                                <td class="px-6 py-3">
                                    <AppBadge :tone="tone(payment.status)">{{ statusLabel(payment.status) }}</AppBadge>
                                </td>
                                <td class="px-6 py-3 font-bold">{{ money(payment.amount, invoice.currency) }}</td>
                                <td class="px-6 py-3 font-mono text-xs">
                                    <span v-if="payment.pay_code" class="font-bold text-primary">{{ payment.pay_code }}</span>
                                    <span v-else>{{ payment.reference || payment.tripay_reference || '—' }}</span>
                                </td>
                                <td class="px-6 py-3 text-on-surface-variant text-xs">{{ payment.paid_at || '—' }}</td>
                            </tr>
                            <tr v-if="!payments.length">
                                <td colspan="5" class="px-6 py-8 text-center text-on-surface-variant">Belum ada catatan pembayaran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
