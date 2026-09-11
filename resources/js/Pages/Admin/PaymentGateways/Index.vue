<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
    active_gateway: { type: String, default: 'duitku' },
    xendit: { type: Object, default: () => ({ secret_key: '', public_key: '', has_secret_key: false, mode: 'sandbox', default_method: 'QRIS' }) },
    duitku: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, mode: 'sandbox', default_method: 'VC' }) },
    tripay: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, has_private_key: false, mode: 'sandbox', default_method: 'QRIS2' }) },
});

const page = usePage();

const activeTab = ref('overview');
const toast = useToast();

const setGatewayForm = useForm({ gateway: props.active_gateway || 'duitku' });
const setGateway = (gw) => {
    setGatewayForm.gateway = gw;
    setGatewayForm.post('/admin/payment-gateways/active', {
        preserveScroll: true,
        onSuccess: () => showToast(`Payment Gateway utama diubah ke ${gw.toUpperCase()}`),
    });
};

function showToast(msg, type = 'success') {
    toast.show(type, msg);
}

const apiCall = async (url, opts = {}) => {
    const res = await fetch(url, {
        ...opts,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Accept': 'application/json',
            ...(opts.headers || {}),
        },
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Request gagal');
    return data;
};

// === Duitku Tab ===
const duitkuModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];
const duitkuMethodOptions = [
    { value: 'VC', label: 'Virtual Account (Semua Bank)' },
    { value: 'SP', label: 'QRIS (ShopeePay / Duitku QR)' },
    { value: 'BC', label: 'BCA Virtual Account' },
    { value: 'M2', label: 'Mandiri Virtual Account' },
    { value: 'B1', label: 'BNI Virtual Account' },
    { value: 'I1', label: 'Indomaret / Alfamart' },
];
const duitkuForm = useForm({
    merchant_code: props.duitku?.merchant_code || '',
    api_key: '',
    mode: props.duitku?.mode || 'sandbox',
    default_method: props.duitku?.default_method || 'VC',
});
const submitDuitku = () => {
    duitkuForm.post('/admin/payment-gateways/duitku', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Duitku tersimpan'),
    });
};
const duitkuTestResult = ref(null);
const duitkuTesting = ref(false);
const testDuitkuConnection = async () => {
    duitkuTesting.value = true;
    duitkuTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/duitku/test', { method: 'POST' });
        duitkuTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        duitkuTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        duitkuTesting.value = false;
    }
};

// === Tripay Tab ===
const tripayModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];
const tripayMethodOptions = [
    { value: 'QRIS2', label: 'QRIS Tripay (Semua Bank & E-Wallet)' },
    { value: 'MYBVA', label: 'Maybank Virtual Account' },
    { value: 'PERMATAVA', label: 'Permata Virtual Account' },
    { value: 'BNIVA', label: 'BNI Virtual Account' },
    { value: 'BRIVA', label: 'BRI Virtual Account' },
    { value: 'MANDIRIVA', label: 'Mandiri Virtual Account' },
    { value: 'BCAVA', label: 'BCA Virtual Account' },
    { value: 'ALFAMART', label: 'Alfamart / Indomaret' },
];
const tripayForm = useForm({
    merchant_code: props.tripay?.merchant_code || '',
    api_key: '',
    private_key: '',
    mode: props.tripay?.mode || 'sandbox',
    default_method: props.tripay?.default_method || 'QRIS2',
});
const submitTripay = () => {
    tripayForm.post('/admin/payment-gateways/tripay', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Tripay tersimpan'),
    });
};
const tripayTestResult = ref(null);
const tripayTesting = ref(false);
const testTripayConnection = async () => {
    tripayTesting.value = true;
    tripayTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/tripay/test', { method: 'POST' });
        tripayTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        tripayTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        tripayTesting.value = false;
    }
};

// === Xendit Tab ===
const xenditModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];
const xenditMethodOptions = [
    { value: 'QRIS', label: 'QRIS Xendit (Semua Bank & E-Wallet)' },
    { value: 'BCA', label: 'BCA Virtual Account' },
    { value: 'BRI', label: 'BRI Virtual Account' },
    { value: 'BNI', label: 'BNI Virtual Account' },
    { value: 'MANDIRI', label: 'Mandiri Virtual Account' },
    { value: 'PERMATA', label: 'Permata Virtual Account' },
];
const xenditForm = useForm({
    secret_key: '',
    public_key: props.xendit?.public_key || '',
    callback_token: '',
    mode: props.xendit?.mode || 'sandbox',
    default_method: props.xendit?.default_method || 'QRIS',
});
const submitXendit = () => {
    xenditForm.post('/admin/payment-gateways/xendit', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Xendit tersimpan'),
    });
};
const xenditTestResult = ref(null);
const xenditTesting = ref(false);
const testXenditConnection = async () => {
    xenditTesting.value = true;
    xenditTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/xendit/test', { method: 'POST' });
        xenditTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        xenditTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        xenditTesting.value = false;
    }
};

const gatewayTabs = [
    { key: 'overview', label: 'Status & Gateway Utama', icon: 'dashboard' },
    { key: 'duitku', label: 'Duitku Gateway', icon: 'account_balance_wallet' },
    { key: 'tripay', label: 'Tripay Gateway', icon: 'payments' },
    { key: 'xendit', label: 'Xendit Gateway', icon: 'credit_card' },
];
</script>

<template>
    <Head title="Pengaturan Payment Gateway" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Pengaturan Payment Gateway</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola provider Payment Gateway (Duitku, Tripay, Xendit) dan tetapkan gateway aktif penagihan sistem.</p>
                </div>
                <div class="flex items-center gap-3">
                    <AppBadge tone="success">Active Gateway: {{ (props.active_gateway || 'duitku').toUpperCase() }}</AppBadge>
                </div>
            </header>


            <!-- Navigation Tabs -->
            <AppTabs
                v-model="activeTab"
                :items="gatewayTabs"
                variant="pills-bar"
                aria-label="Tab payment gateway"
            />

            <!-- OVERVIEW TAB -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <AppCard class="border-2 border-primary/20">
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-primary text-lg">Pilih Payment Gateway Utama (In-App Billing)</h3>
                            <p class="text-xs text-on-surface-variant">Klik tombol aktifkan pada salah satu gateway di bawah ini untuk mengeset gateway penagihan invoice otomatis.</p>
                        </div>
                        <AppBadge tone="success">Aktif: {{ (props.active_gateway || 'duitku').toUpperCase() }}</AppBadge>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Duitku Card -->
                        <div class="flex flex-col justify-between rounded-xl border-2 p-5 transition" :class="props.active_gateway === 'duitku' ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface'">
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-base text-primary">Duitku Gateway</h4>
                                    <AppBadge :tone="props.active_gateway === 'duitku' ? 'success' : 'neutral'">
                                        {{ props.active_gateway === 'duitku' ? 'Aktif Utama' : 'Nonaktif' }}
                                    </AppBadge>
                                </div>
                                <p class="text-xs text-on-surface-variant leading-relaxed">Virtual Account (Semua Bank), QRIS (ShopeePay/Duitku), Credit Card, Indomaret/Alfamart.</p>
                            </div>
                            <AppButton size="compact" :variant="props.active_gateway === 'duitku' ? 'success' : 'secondary'" :disabled="props.active_gateway === 'duitku' || setGatewayForm.processing" icon="check_circle" @click="setGateway('duitku')">
                                {{ props.active_gateway === 'duitku' ? 'Aktif Digunakan' : 'Aktifkan Duitku' }}
                            </AppButton>
                        </div>

                        <!-- Tripay Card -->
                        <div class="flex flex-col justify-between rounded-xl border-2 p-5 transition" :class="props.active_gateway === 'tripay' ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface'">
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-base text-primary">Tripay Gateway</h4>
                                    <AppBadge :tone="props.active_gateway === 'tripay' ? 'success' : 'neutral'">
                                        {{ props.active_gateway === 'tripay' ? 'Aktif Utama' : 'Nonaktif' }}
                                    </AppBadge>
                                </div>
                                <p class="text-xs text-on-surface-variant leading-relaxed">QRIS Tripay, Virtual Account (BCA, BRI, BNI, Mandiri, Permata), Retail Outlets.</p>
                            </div>
                            <AppButton size="compact" :variant="props.active_gateway === 'tripay' ? 'success' : 'secondary'" :disabled="props.active_gateway === 'tripay' || setGatewayForm.processing" icon="check_circle" @click="setGateway('tripay')">
                                {{ props.active_gateway === 'tripay' ? 'Aktif Digunakan' : 'Aktifkan Tripay' }}
                            </AppButton>
                        </div>

                        <!-- Xendit Card -->
                        <div class="flex flex-col justify-between rounded-xl border-2 p-5 transition" :class="props.active_gateway === 'xendit' ? 'border-primary bg-primary/5' : 'border-outline-variant bg-surface'">
                            <div class="space-y-2 mb-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-base text-primary">Xendit Gateway</h4>
                                    <AppBadge :tone="props.active_gateway === 'xendit' ? 'success' : 'neutral'">
                                        {{ props.active_gateway === 'xendit' ? 'Aktif Utama' : 'Nonaktif' }}
                                    </AppBadge>
                                </div>
                                <p class="text-xs text-on-surface-variant leading-relaxed">QRIS Xendit, Virtual Account (BCA, BRI, BNI, Mandiri, Permata), Kartu Kredit/Debit.</p>
                            </div>
                            <AppButton size="compact" :variant="props.active_gateway === 'xendit' ? 'success' : 'secondary'" :disabled="props.active_gateway === 'xendit' || setGatewayForm.processing" icon="check_circle" @click="setGateway('xendit')">
                                {{ props.active_gateway === 'xendit' ? 'Aktif Digunakan' : 'Aktifkan Xendit' }}
                            </AppButton>
                        </div>
                    </div>
                </AppCard>
            </div>

            <!-- DUITKU TAB -->
            <div v-else-if="activeTab === 'duitku'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Duitku Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Kelola Merchant Code dan API Key Duitku secara terpusat dari Superadmin.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.duitku?.has_api_key && props.duitku?.merchant_code ? 'success' : 'warning'">
                                {{ props.duitku?.has_api_key && props.duitku?.merchant_code ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.duitku?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitDuitku" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div><SmartSelect v-model="duitkuForm.mode" label="Mode Lingkungan" :options="duitkuModeOptions" required /></div>
                            <div><AppInput v-model="duitkuForm.merchant_code" label="Merchant Code" placeholder="Contoh: D12345" required /></div>
                            <div class="sm:col-span-2"><AppInput v-model="duitkuForm.api_key" label="API Key (Secret)" type="password" :placeholder="props.duitku?.has_api_key ? '???????????????? (Tersimpan - isi jika ingin mengubah)' : 'Masukkan API Key Duitku'" /></div>
                            <div class="sm:col-span-2"><SmartSelect v-model="duitkuForm.default_method" label="Metode Pembayaran Default" :options="duitkuMethodOptions" required /></div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="duitkuTesting" @click="testDuitkuConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ duitkuTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Duitku API' }}</span>
                            </AppButton>
                            <AppButton type="submit" variant="primary" :disabled="duitkuForm.processing">Simpan Kredensial Duitku</AppButton>
                        </div>
                    </form>

                    <AppCard v-if="duitkuTestResult" :class="duitkuTestResult.ok ? 'border-secondary-container bg-secondary-container/30' : 'border-error-container bg-error-container/30'" class="mt-6">
                        <h4 class="font-bold text-sm" :class="duitkuTestResult.ok ? 'text-secondary' : 'text-on-error-container'">
                            {{ duitkuTestResult.ok ? '? ' : '? ' }}{{ duitkuTestResult.message }}
                        </h4>
                    </AppCard>
                </AppCard>
            </div>

            <!-- TRIPAY TAB -->
            <div v-else-if="activeTab === 'tripay'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Tripay Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Kelola Merchant Code, API Key, dan Private Key Tripay secara terpusat dari Superadmin.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.tripay?.has_api_key && props.tripay?.has_private_key ? 'success' : 'warning'">
                                {{ props.tripay?.has_api_key && props.tripay?.has_private_key ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.tripay?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitTripay" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div><SmartSelect v-model="tripayForm.mode" label="Mode Lingkungan" :options="tripayModeOptions" required /></div>
                            <div><AppInput v-model="tripayForm.merchant_code" label="Merchant Code" placeholder="Contoh: T12345" required /></div>
                            <div><AppInput v-model="tripayForm.api_key" label="API Key" type="password" :placeholder="props.tripay?.has_api_key ? '???????????????? (Tersimpan)' : 'DEV-...' " /></div>
                            <div><AppInput v-model="tripayForm.private_key" label="Private Key" type="password" :placeholder="props.tripay?.has_private_key ? '???????????????? (Tersimpan)' : 'Masukkan Private Key' " /></div>
                            <div class="sm:col-span-2"><SmartSelect v-model="tripayForm.default_method" label="Metode Pembayaran Default" :options="tripayMethodOptions" required /></div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="tripayTesting" @click="testTripayConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ tripayTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Tripay API' }}</span>
                            </AppButton>
                            <AppButton type="submit" variant="primary" :disabled="tripayForm.processing">Simpan Kredensial Tripay</AppButton>
                        </div>
                    </form>

                    <AppCard v-if="tripayTestResult" :class="tripayTestResult.ok ? 'border-secondary-container bg-secondary-container/30' : 'border-error-container bg-error-container/30'" class="mt-6">
                        <h4 class="font-bold text-sm" :class="tripayTestResult.ok ? 'text-secondary' : 'text-on-error-container'">
                            {{ tripayTestResult.ok ? '? ' : '? ' }}{{ tripayTestResult.message }}
                        </h4>
                    </AppCard>
                </AppCard>
            </div>

            <!-- XENDIT TAB -->
            <div v-else-if="activeTab === 'xendit'" class="space-y-6">
                <AppCard>
                    <header class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant pb-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Kredensial & Pengaturan Xendit Payment Gateway</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Kelola Secret Key, Public Key, dan Verification Token Xendit secara terpusat dari Superadmin.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppBadge :tone="props.xendit?.has_secret_key ? 'success' : 'warning'">
                                {{ props.xendit?.has_secret_key ? 'Kredensial Aktif' : 'Kredensial Belum Lengkap' }}
                            </AppBadge>
                            <AppBadge tone="neutral">Mode: {{ (props.xendit?.mode || 'sandbox').toUpperCase() }}</AppBadge>
                        </div>
                    </header>

                    <form @submit.prevent="submitXendit" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div><SmartSelect v-model="xenditForm.mode" label="Mode Lingkungan" :options="xenditModeOptions" required /></div>
                            <div><AppInput v-model="xenditForm.public_key" label="Public Key (Optional)" placeholder="xnd_public_..." /></div>
                            <div class="sm:col-span-2"><AppInput v-model="xenditForm.secret_key" label="Secret Key (API Key)" type="password" :placeholder="props.xendit?.has_secret_key ? '???????????????? (Tersimpan)' : 'xnd_development_... / xnd_production_...'" /></div>
                            <div class="sm:col-span-2"><AppInput v-model="xenditForm.callback_token" label="Webhook Verification Token (x-callback-token)" type="password" placeholder="Masukkan verification token webhook Xendit" /></div>
                            <div class="sm:col-span-2"><SmartSelect v-model="xenditForm.default_method" label="Metode Pembayaran Default" :options="xenditMethodOptions" required /></div>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-outline-variant pt-4">
                            <AppButton type="button" variant="secondary" :disabled="xenditTesting" @click="testXenditConnection">
                                <AppIcon name="network_check" class="mr-1" />
                                <span>{{ xenditTesting ? 'Menguji Koneksi...' : 'Uji Koneksi Xendit API' }}</span>
                            </AppButton>
                            <AppButton type="submit" variant="primary" :disabled="xenditForm.processing">Simpan Kredensial Xendit</AppButton>
                        </div>
                    </form>

                    <AppCard v-if="xenditTestResult" :class="xenditTestResult.ok ? 'border-secondary-container bg-secondary-container/30' : 'border-error-container bg-error-container/30'" class="mt-6">
                        <h4 class="font-bold text-sm" :class="xenditTestResult.ok ? 'text-secondary' : 'text-on-error-container'">
                            {{ xenditTestResult.ok ? '? ' : '? ' }}{{ xenditTestResult.message }}
                        </h4>
                    </AppCard>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>
