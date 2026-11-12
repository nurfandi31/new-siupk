<script setup>
import { useI18n } from '../../i18n';
const { t } = useI18n();
import { useConfirm } from '../../composables/useConfirm';
import { useRemoteImageFile } from '../../composables/useRemoteImageFile';
import { useToast } from '../../composables/useToast';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AppRichEditor from '../../Components/AppRichEditor.vue';
import AppModal from '../../Components/AppModal.vue';
import ImageAdjuster from '../../Components/ImageAdjuster.vue';
import SignaturePad from '../../Components/SignaturePad.vue';
import AppTabs from '../../Components/AppTabs.vue';
import SavingsSettingsPanel from '../../Components/Savings/SettingsPanel.vue';
import AuthenticatedLayout from '../../Layouts/TenantAdminLayout.vue';

const props = defineProps({
    identity: { type: Object, required: true },
    products: { type: Array, required: true },
    logoUrl: { type: String, default: null },
    whatsapp: { type: Object, required: true },
    signatures: { type: Object, required: true },
    signatureImages: { type: Object, default: () => ({}) },
    offline: { type: Object, default: () => ({ is_enabled: false, user_id: null, users: [] }) },
    savings: { type: Object, default: () => ({ enabled: false }) },
});

const page = usePage();
const route = computed(() => page.url);
const flash = computed(() => page.props.flash?.success);

const tabs = computed(() => {
    const items = [
        { key: 'identity', label: t('Identitas Lembaga'), icon: 'badge', description: t('Profil & kontak') },
        { key: 'lending-system', label: t('Sistem Pinjaman'), icon: 'tune', description: t('Default produk') },
    ];

    if (props.savings?.enabled) {
        items.push({ key: 'savings', label: t('Sistem Simpanan'), icon: 'savings', description: t('Bunga & jenis') });
    }

    items.push(
        { key: 'logo', label: t('Logo Lembaga'), icon: 'image', description: t('Branding') },
        { key: 'offline', label: t('Akses Offline'), icon: 'cloud_off', description: t('Sinkronisasi') },
        { key: 'signatures', label: t('Tanda Tangan'), icon: 'draw', description: t('Template laporan') },
    );

    return items;
});

const tabMeta = {
    identity: { icon: 'badge', tone: 'primary', title: t('Identitas Lembaga'), subtitle: t('Profil hukum dan kontak lembaga. Data ini muncul pada laporan dan dokumen resmi.'), gradient: 'from-primary/10 to-primary/0' },
    'lending-system': { icon: 'tune', tone: 'secondary', title: t('Sistem Pinjaman'), subtitle: t('Default jasa, jangka, dan metode pembulatan angsuran per produk pinjaman.'), gradient: 'from-secondary/10 to-secondary/0' },
    savings: { icon: 'savings', tone: 'tertiary', title: t('Sistem Simpanan'), subtitle: t('SOP bunga, pajak, biaya, metode saldo, dan jenis simpanan. Pengaturan ini dipakai seluruh rekening simpanan di lokasi ini.'), gradient: 'from-tertiary/10 to-tertiary/0' },
    logo: { icon: 'image', tone: 'tertiary', title: t('Logo Lembaga'), subtitle: t('Logo tampil di sidebar, kop dokumen, dan seluruh pelaporan PDF. Format PNG, JPG, atau WebP, maks 2 MB.'), gradient: 'from-tertiary/10 to-tertiary/0' },
    offline: { icon: 'cloud_off', tone: 'info', title: t('Akses Offline'), subtitle: t('Aktifkan agar satu pengguna terpilih tetap dapat menginput, mengedit, dan menghapus data saat offline.'), gradient: 'from-info/10 to-info/0' },
    signatures: { icon: 'draw', tone: 'primary', title: t('Tanda Tangan'), subtitle: t('Blok penandatangan per jenis laporan. Disimpan per lembaga.'), gradient: 'from-primary/10 to-primary/0' },
};

const activeTab = ref(getInitialTab());

function getInitialTab() {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.value.some((t) => t.key === tab)) return tab;
    return flash.value?.tab ?? 'identity';
}

function go(tab) {
    router.get('/settings', { tab }, { preserveState: true, preserveScroll: true });
}

watch(() => route.value, () => {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.value.some((t) => t.key === tab)) activeTab.value = tab;
});

// === Identity form ===
const identityForm = useForm({
    legal_name: props.identity.legal_name ?? '',
    short_name: props.identity.short_name ?? '',
    registration_number: props.identity.registration_number ?? '',
    tax_number: props.identity.tax_number ?? '',
    address: props.identity.address ?? '',
    district_name: props.identity.district_name ?? '',
    regency_name: props.identity.regency_name ?? '',
    phone: props.identity.phone ?? '',
    email: props.identity.email ?? '',
    website: props.identity.website ?? '',
    timezone: props.identity.timezone ?? 'Asia/Jakarta',
    operational_start_date: props.identity.operational_start_date ?? '',
});
function submitIdentity() {
    identityForm.put('/settings/identity', { preserveScroll: true });
}

// === Lending system form ===
const lendingForm = useForm({
    products: props.products.map((p) => ({ ...p })),
});
const roundingOptions = [
    { value: 'decimal_2', label: t('2 Desimal') },
    { value: 'rupiah_bersih', label: t('Rupiah Bersih') },
    { value: 'ceil_100', label: t('Ke Atas (Rp 100)') },
    { value: 'floor_100', label: t('Ke Bawah (Rp 100)') },
    { value: '500', label: t('Rp 500') },
    { value: '1000', label: t('Rp 1.000') },
    { value: '5000', label: t('Rp 5.000') },
    { value: '10000', label: t('Rp 10.000') },
    { value: '50000', label: t('Rp 50.000') },
];
function submitLending() {
    lendingForm.put('/settings/lending-system', { preserveScroll: true });
}

// === Logo ===
const logoForm = useForm({ logo: null });
const logoPreview = ref(props.logoUrl);
const logoDragOver = ref(false);
const logoCropOpen = ref(false);
const logoSourceFile = ref(null);

watch(() => props.logoUrl, (url) => {
    if (!url) return;
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoPreview.value = url;
});

function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    setLogoFile(file);
    event.target.value = '';
}
function onLogoDrop(event) {
    event.preventDefault();
    logoDragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setLogoFile(file);
}
function setLogoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    logoSourceFile.value = file;
    logoCropOpen.value = true;
}

function applyCroppedLogo(file) {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

const { loading: logoAdjusting, toFile: logoToFile } = useRemoteImageFile();

async function adjustCurrentLogo() {
    if (logoForm.logo) {
        logoCropOpen.value = true;
        return;
    }

    const file = await logoToFile(props.logoUrl, 'logo.png', 'image/png');
    if (!file) {
        toast.error('Gagal memuat logo saat ini. Silakan pilih file logo dari perangkat Anda.');
        return;
    }
    logoSourceFile.value = file;
    logoCropOpen.value = true;
}

onBeforeUnmount(() => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
});
function submitLogo() {
    logoForm.post('/settings/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => logoForm.reset(),
    });
}
const { confirm: confirmAction } = useConfirm();
const toast = useToast();
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const syncLoading = ref(false);
async function syncRounding() {
    if (!await confirmAction({
        title: t('Sinkronkan Pembulatan'),
        message: t('Pembulatan dari setiap produk akan diterapkan ke semua pinjaman berstatus draft/verified dan jadwal angsurannya akan digenerate ulang. Lanjutkan?'),
        confirmLabel: t('Sinkronkan'),
        variant: 'primary',
        icon: 'sync',
    })) return;
    syncLoading.value = true;
    try {
        const res = await fetch('/settings/lending-system/sync-rounding', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const data = await res.json();
        toast.success(data.message);
    } catch {
        toast.error('Terjadi kesalahan saat sinkronisasi pembulatan.');
    } finally {
        syncLoading.value = false;
    }
}

async function destroyLogo() {
    if (!await confirmAction({ title: t('Hapus Logo'), message: t('Hapus logo organisasi?') })) return;
    router.delete('/settings/logo', {
        preserveScroll: true,
        onSuccess: () => {
            if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
                URL.revokeObjectURL(logoPreview.value);
            }
            logoPreview.value = null;
            logoForm.reset();
        },
    });
}

// === Offline Access ===
const offlineForm = useForm({
    is_enabled: props.offline.is_enabled ?? false,
    user_id: props.offline.user_id ?? null,
});

const offlineUserOptions = computed(() => (props.offline.users ?? []).map((u) => ({
    value: u.row_id,
    label: u.name + (u.username ? ` (${u.username})` : ''),
})));

const outbox = ref({ pending: 0, failed: 0, synced: 0 });
async function loadOutbox() {
    try {
        const response = await fetch('/desktop/sync/status', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        outbox.value = data.outbox ?? outbox.value;
    } catch {
        outbox.value = { pending: 0, failed: 0, synced: 0 };
    }
}
onMounted(loadOutbox);

function submitOffline() {
    if (offlineForm.is_enabled && !offlineForm.user_id) {
        toast.error('Pilih satu pengguna offline terlebih dahulu.');
        return;
    }
    offlineForm.put('/settings/offline-access', { preserveScroll: true });
}
// === Signatures ===
const signatureReportKey = ref(props.signatures.reportTypes?.[0]?.key ?? 'default');
const signatureDrafts = ref({ ...(props.signatures.templates ?? {}) });
const signatureForm = useForm({ templates: {} });
const signatureReportOptions = computed(() =>
    (props.signatures.reportTypes ?? []).map((t) => ({ value: t.key, label: t.label })),
);
const currentSignatureHtml = computed({
    get: () => signatureDrafts.value[signatureReportKey.value] ?? '',
    set: (val) => {
        signatureDrafts.value = {
            ...signatureDrafts.value,
            [signatureReportKey.value]: val ?? '',
        };
    },
});
function submitSignatures() {
    signatureForm.templates = { ...signatureDrafts.value };
    signatureForm.put('/settings/signatures', { preserveScroll: true });
}
const showSignaturePad = ref(false);
const signatureImagePad = ref(null);
const signatureImageForm = useForm({ report_key: '', image: '' });
const signatureDeleteForm = useForm({ report_key: '' });

const currentSignatureImageUrl = computed(() => props.signatureImages?.[signatureReportKey.value] ?? null);

function openSignaturePad() {
    signatureImageForm.report_key = signatureReportKey.value;
    signatureImageForm.image = '';
    showSignaturePad.value = true;
}

async function saveSignatureImage(dataUrl) {
    if (!dataUrl) return;
    signatureImageForm.image = dataUrl;
    try {
        await signatureImageForm.post('/settings/signatures/image', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showSignaturePad.value = false;
                signatureImageForm.reset();
            },
        });
    } catch (error) {
        console.error('Gagal menyimpan tanda tangan:', error);
    }
}

async function removeSignatureImage() {
    if (!currentSignatureImageUrl.value) return;
    const confirmed = await confirmAction({
        title: t('Hapus Tanda Tangan'),
        message: t('Hapus tanda tangan digital untuk jenis dokumen ini?'),
    });
    if (!confirmed) return;

    signatureDeleteForm.report_key = signatureReportKey.value;
    router.delete('/settings/signatures/image', {
        data: { report_key: signatureDeleteForm.report_key },
        preserveScroll: true,
        onSuccess: () => signatureDeleteForm.reset(),
    });
}

async function handleUploadSignatureImage(event) {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;

    const reader = new FileReader();
    reader.onload = () => {
        signatureImageForm.report_key = signatureReportKey.value;
        signatureImageForm.image = String(reader.result ?? '');
        signatureImageForm.post('/settings/signatures/image', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => signatureImageForm.reset(),
        });
    };
    reader.readAsDataURL(file);

    event.target.value = '';
}

function applySignatureStarter() {
    currentSignatureHtml.value = `<table style="width:100%"><tbody><tr><td style="width:33%;text-align:center"><p>Mengetahui,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Dibuat oleh,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Bendahara,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td></tr></tbody></table>`;
}
</script>

<template>
    <Head :title="t('Pengaturan')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 pb-12">
            <!-- Header -->
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold tracking-tight text-primary sm:text-3xl">{{ t('Pengaturan') }}</h1>
                <p class="text-sm text-on-surface-variant sm:text-base">
                    {{ t('Kelola identitas lembaga, sistem pinjaman, sistem simpanan, logo, dan preferensi aplikasi Anda di satu tempat.') }}
                </p>
            </div>

            <div class="grid gap-0 overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40 lg:grid-cols-[15rem_1fr]">
                <!-- Sidebar Tabs (nempel dengan konten di kanan) -->
                <aside class="bg-outline-variant/40 p-3 lg:min-h-full">
                    <AppTabs
                        v-model="activeTab"
                        :items="tabs"
                        variant="pill"
                        :aria-label="t('Tab pengaturan')"
                        @update:model-value="go($event)"
                    />
                </aside>

                <!-- Tab Content -->
                <Transition name="tab-fade" mode="out-in">
                    <div :key="activeTab" class="min-w-0 space-y-6 p-5 sm:p-7">
                    <!-- Identity -->
                    <div v-if="activeTab === 'identity'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta.identity.icon" :tone="tabMeta.identity.tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta.identity.title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta.identity.subtitle }}</p>
                            </div>
                        </div>

                        <form class="space-y-5" @submit.prevent="submitIdentity">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput v-model="identityForm.legal_name" :label="t('Nama Legal')" required :error="identityForm.errors.legal_name" />
                                <AppInput v-model="identityForm.short_name" :label="t('Nama Pendek')" :error="identityForm.errors.short_name" />
                                <AppInput v-model="identityForm.registration_number" :label="t('Nomor Badan Hukum')" :error="identityForm.errors.registration_number" />
                                <AppInput v-model="identityForm.tax_number" label="NPWP" :error="identityForm.errors.tax_number" />
                                <AppInput v-model="identityForm.district_name" :label="t('Kecamatan')" :error="identityForm.errors.district_name" />
                                <AppInput v-model="identityForm.regency_name" :label="t('Kabupaten')" :error="identityForm.errors.regency_name" />
                                <AppInput v-model="identityForm.phone" :label="t('Telepon')" :error="identityForm.errors.phone" />
                                <AppInput v-model="identityForm.email" :label="t('Email')" type="email" :error="identityForm.errors.email" />
                                <AppInput v-model="identityForm.website" :label="t('Website')" type="url" :error="identityForm.errors.website" />
                                <AppInput v-model="identityForm.timezone" :label="t('Zona Waktu')" required :error="identityForm.errors.timezone" />
                                <AppDatePicker v-model="identityForm.operational_start_date" :label="t('Tanggal Operasional Mulai')" :error="identityForm.errors.operational_start_date" class="sm:col-span-2" />
                            </div>
                            <AppTextarea v-model="identityForm.address" :label="t('Alamat')" :rows="3" :error="identityForm.errors.address" />
                            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="submit" :loading="identityForm.processing" :disabled="identityForm.processing" icon="save">{{ t('Simpan Identitas') }}</AppButton>
                            </div>
                        </form>
                    </div>

                    <!-- Lending System -->
                    <div v-else-if="activeTab === 'lending-system'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta['lending-system'].icon" :tone="tabMeta['lending-system'].tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta['lending-system'].title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta['lending-system'].subtitle }}</p>
                            </div>
                        </div>

                        <div v-if="!lendingForm.products.length" class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-low p-12 text-center">
                            <AppIcon name="inventory_2" class="text-5xl text-on-surface-variant" />
                            <p class="mt-3 text-sm font-bold text-primary">{{ t('Belum ada produk pinjaman') }}</p>
                            <p class="mt-1 text-xs text-on-surface-variant">{{ t('Tambahkan produk pinjaman terlebih dahulu untuk mengatur default parameter.') }}</p>
                        </div>

                        <div v-else class="grid gap-4 md:grid-cols-2">
                            <div v-for="(product, idx) in lendingForm.products" :key="product.row_id" class="group relative overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm transition-all hover:border-primary-container hover:shadow-md">
                                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary to-secondary opacity-0 transition-opacity group-hover:opacity-100" />
                                <div class="mb-4 flex items-start gap-3">
                                    <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/20">
                                        <AppIcon name="savings" class="text-xl" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-primary">{{ product.name }}</p>
                                        <code class="mt-0.5 inline-block rounded bg-surface-container px-1.5 py-0.5 font-mono text-xs text-on-surface-variant">{{ product.code }}</code>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <AppInput v-model.number="lendingForm.products[idx].default_interest_rate" :label="t('Default Jasa (%)')" type="number" step="0.01" :min="0" :max="100" :error="lendingForm.errors[`products.${idx}.default_interest_rate`]" />
                                    <AppInput v-model.number="lendingForm.products[idx].default_term_months" :label="t('Jangka (bulan)')" type="number" :min="1" :max="240" :error="lendingForm.errors[`products.${idx}.default_term_months`]" />
                                    <SmartSelect v-model="lendingForm.products[idx].rounding_method" :options="roundingOptions" :label="t('Pembulatan')" />
                                </div>
                            </div>
                        </div>

                        <div v-if="lendingForm.products.length" class="flex flex-col gap-3 border-t border-outline-variant pt-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-2 text-xs text-on-surface-variant sm:items-center">
                                <AppIcon name="sync" tone="info" :container-size="8" class="shrink-0" />
                                <span>{{ t('Terapkan pembulatan ke pinjaman draft/verified dan generate ulang jadwal.') }}</span>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <AppButton type="button" size="compact" variant="outline" :loading="syncLoading" :disabled="syncLoading" icon="sync" @click="syncRounding">{{ t('Sinkronkan') }}</AppButton>
                                <AppButton type="button" size="compact" :loading="lendingForm.processing" :disabled="lendingForm.processing" icon="save" @click="submitLending">{{ t('Simpan') }}</AppButton>
                            </div>
                        </div>
                    </div>

                    <!-- Savings System -->
                    <div v-else-if="activeTab === 'savings'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta.savings.icon" :tone="tabMeta.savings.tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta.savings.title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta.savings.subtitle }}</p>
                            </div>
                        </div>

                        <SavingsSettingsPanel
                            v-if="props.savings?.enabled"
                            :settings="props.savings.settings"
                            :products="props.savings.products"
                            :methods="props.savings.methods"
                            :categories="props.savings.categories"
                            :form-types="props.savings.formTypes"
                        />

                        <div v-else class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-low p-12 text-center">
                            <AppIcon name="savings" class="text-5xl text-on-surface-variant" />
                            <p class="mt-3 text-sm font-bold text-primary">{{ t('Modul simpanan belum aktif') }}</p>
                            <p class="mt-1 text-xs text-on-surface-variant">{{ t('Aktifkan modul simpanan dari pusat tenant untuk mengatur bunga, pajak, biaya, dan jenis simpanan.') }}</p>
                        </div>
                    </div>

                    <!-- Offline -->
                    <div v-else-if="activeTab === 'offline'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta.offline.icon" :tone="tabMeta.offline.tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta.offline.title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta.offline.subtitle }}</p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <AppBadge tone="primary-soft">{{ t('Menunggu') }}</AppBadge>
                                    <AppIcon name="schedule" tone="primary" :container-size="8" />
                                </div>
                                <p class="mt-3 text-3xl font-bold tabular-nums text-primary">{{ outbox.pending }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Mutasi belum sinkron') }}</p>
                            </div>
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <AppBadge tone="error-soft">{{ t('Gagal') }}</AppBadge>
                                    <AppIcon name="error" tone="danger" :container-size="8" />
                                </div>
                                <p class="mt-3 text-3xl font-bold tabular-nums text-error">{{ outbox.failed }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Perlu ditangani') }}</p>
                            </div>
                            <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4">
                                <div class="flex items-center justify-between">
                                    <AppBadge tone="success-soft">{{ t('Tersinkron') }}</AppBadge>
                                    <AppIcon name="check_circle" tone="success" :container-size="8" />
                                </div>
                                <p class="mt-3 text-3xl font-bold tabular-nums text-success">{{ outbox.synced }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Berhasil terkirim') }}</p>
                            </div>
                        </div>

                        <form class="space-y-5" @submit.prevent="submitOffline">
                            <div class="flex items-center justify-between rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <AppIcon name="power_settings_new" tone="primary" :container-size="9" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-primary">{{ t('Aktifkan Akses Offline') }}</p>
                                        <p class="mt-0.5 text-xs text-on-surface-variant">{{ t('Jika nonaktif, mode offline akan kembali hanya-baca.') }}</p>
                                    </div>
                                </div>
                                <AppSwitch v-model="offlineForm.is_enabled" />
                            </div>
                            <SmartSelect
                                v-model="offlineForm.user_id"
                                :label="t('Pengguna Offline')"
                                :placeholder="t('Pilih satu pengguna')"
                                :options="offlineUserOptions"
                                :required="offlineForm.is_enabled"
                                :disabled="!offlineForm.is_enabled"
                                :hint="t('Hanya satu pengguna per tenant yang dapat diizinkan.')"
                            />
                            <p v-if="offlineForm.errors.user_id" class="text-sm text-error">{{ offlineForm.errors.user_id }}</p>
                            <p v-if="offlineForm.errors.is_enabled" class="text-sm text-error">{{ offlineForm.errors.is_enabled }}</p>
                            <div class="flex flex-wrap justify-end gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="submit" icon="save" :loading="offlineForm.processing" :disabled="offlineForm.processing">
                                    {{ t('Simpan Pengaturan Offline') }}
                                </AppButton>
                            </div>
                        </form>
                    </div>

                    <!-- Logo -->
                    <div v-else-if="activeTab === 'logo'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta.logo.icon" :tone="tabMeta.logo.tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta.logo.title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta.logo.subtitle }}</p>
                            </div>
                        </div>

                        <div class="grid gap-6 md:grid-cols-[14rem_minmax(0,1fr)] md:items-start">
                            <div class="relative mx-auto w-full max-w-[14rem]">
                                <div class="relative grid aspect-square w-full place-items-center overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 shadow-sm">
                                    <img v-if="logoPreview" :src="logoPreview" :alt="t('Logo')" class="max-h-full max-w-full object-contain" />
                                    <div v-else class="text-center">
                                        <AppIcon name="image" class="text-5xl text-on-surface-variant" />
                                        <p class="mt-2 text-xs text-on-surface-variant">{{ t('Belum ada logo') }}</p>
                                    </div>
                                    <button
                                        v-if="logoPreview"
                                        type="button"
                                        class="absolute bottom-2 right-2 grid size-9 place-items-center rounded-full bg-surface-container-lowest/95 text-primary shadow ring-1 ring-outline-variant transition hover:bg-surface-container disabled:opacity-60"
                                        :disabled="logoAdjusting"
                                        :title="t('Sesuaikan logo')"
                                        :aria-label="t('Sesuaikan posisi dan ukuran logo')"
                                        @click="adjustCurrentLogo"
                                    >
                                        <AppIcon :name="logoAdjusting ? 'progress_activity' : 'tune'" class="text-lg" :class="logoAdjusting && 'animate-spin'" />
                                    </button>
                                </div>
                                <p class="mt-2 text-center text-[0.7rem] font-medium text-on-surface-variant">{{ t('Pratinjau logo') }}</p>
                            </div>

                            <form class="space-y-4" @submit.prevent="submitLogo">
                                <label
                                    class="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-outline-variant bg-surface-container-low/60 p-4 transition-colors hover:border-primary hover:bg-primary-container/10"
                                    :class="logoDragOver ? 'border-primary bg-primary-container/20' : ''"
                                    @dragover.prevent="logoDragOver = true"
                                    @dragleave="logoDragOver = false"
                                    @drop="onLogoDrop"
                                >
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange" />
                                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-container/30 text-primary">
                                        <AppIcon name="cloud_upload" class="text-2xl" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-primary">{{ t('Tarik gambar atau klik untuk pilih') }}</span>
                                        <span class="mt-0.5 block text-xs text-on-surface-variant">{{ t('PNG, JPG, WebP · Maks 2 MB · posisi & ukuran bisa diatur') }}</span>
                                    </span>
                                    <AppIcon name="chevron_right" class="shrink-0 text-outline" />
                                </label>

                                <p v-if="logoForm.errors.logo" class="text-sm text-error">{{ logoForm.errors.logo }}</p>

                                <div v-if="logoForm.logo" class="flex items-center gap-2 rounded-xl border border-outline-variant/60 bg-surface-container-low px-3 py-2 text-sm text-on-surface-variant">
                                    <AppIcon name="image" class="shrink-0 text-base text-outline" />
                                    <span class="min-w-0 flex-1 truncate">{{ logoForm.logo.name }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-primary transition hover:bg-surface-container"
                                        @click="logoCropOpen = true"
                                    >
                                        <AppIcon name="tune" class="text-base" />
                                        {{ t('Atur') }}
                                    </button>
                                </div>

                                <div class="flex flex-wrap justify-end gap-2 border-t border-outline-variant/60 pt-4">
                                    <AppButton v-if="props.logoUrl" type="button" variant="danger" icon="delete" :loading="logoForm.processing" @click="destroyLogo">{{ t('Hapus Logo') }}</AppButton>
                                    <AppButton type="submit" :loading="logoForm.processing" :disabled="!logoForm.logo || logoForm.processing" icon="upload">{{ t('Unggah Logo') }}</AppButton>
                                </div>
                            </form>
                        </div>

                        <ImageAdjuster
                            v-model="logoCropOpen"
                            :file="logoSourceFile"
                            :title="t('Sesuaikan Logo Lembaga')"
                            :subtitle="t('Geser logo di dalam kotak untuk mengatur posisi, lalu perbesar atau perkecil agar pas.')"
                            :aspect="1"
                            fit="contain"
                            :output-size="512"
                            output-type="image/png"
                            output-name="logo.png"
                            :confirm-label="t('Gunakan Logo')"
                            checkerboard
                            @confirm="applyCroppedLogo"
                        />
                    </div>

                    <!-- Signatures -->
                    <div v-else-if="activeTab === 'signatures'" class="space-y-6">
                        <div class="flex items-center gap-3">
                            <AppIcon :name="tabMeta.signatures.icon" :tone="tabMeta.signatures.tone" :container-size="11" />
                            <div>
                                <h2 class="text-xl font-bold text-primary">{{ tabMeta.signatures.title }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ tabMeta.signatures.subtitle }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3 rounded-2xl border border-info/30 bg-info-container/30 p-4">
                            <AppIcon name="tips_and_updates" tone="info" :container-size="9" />
                            <p class="text-sm text-on-surface">
                                {{ t('Tanda tangan gambar akan otomatis disisipkan ke PDF melalui placeholder') }}
                                <code class="rounded bg-surface-container px-1.5 py-0.5 font-mono text-xs font-bold">{ttd_image}</code>
                                {{ t('atau langsung ke baris kosong pertama pada template.') }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-outline-variant bg-surface-container-low p-4">
                            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <AppIcon name="gesture" tone="primary" :container-size="9" />
                                    <div>
                                        <p class="text-base font-bold text-primary">{{ t('Tanda Tangan Digital') }}</p>
                                        <p class="text-xs text-on-surface-variant">{{ t('Gambar atau unggah gambar tanda tangan') }}</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <AppButton type="button" variant="secondary" size="compact" icon="draw" @click="openSignaturePad">{{ t('Gambar') }}</AppButton>
                                    <label class="cursor-pointer">
                                        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="handleUploadSignatureImage" />
                                        <span class="inline-flex min-h-9 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-sm font-bold text-primary shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-surface-container-low hover:shadow-lg active:scale-[0.97] active:shadow-sm">
                                            <AppIcon name="upload" class="text-base leading-none" />
                                            {{ t('Unggah') }}
                                        </span>
                                    </label>
                                    <AppButton v-if="currentSignatureImageUrl" type="button" variant="danger" size="compact" icon="delete" :loading="signatureDeleteForm.processing" @click="removeSignatureImage">{{ t('Hapus') }}</AppButton>
                                </div>
                            </div>
                            <div v-if="currentSignatureImageUrl" class="flex justify-center rounded-xl border border-outline-variant bg-white p-6">
                                <img :src="currentSignatureImageUrl" :alt="t('Tanda Tangan Digital')" class="max-h-32 object-contain" />
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest py-10 text-center">
                                <AppIcon name="draw" class="text-4xl text-on-surface-variant" />
                                <p class="mt-2 text-sm text-on-surface-variant">{{ t('Belum ada tanda tangan digital untuk jenis dokumen ini.') }}</p>
                            </div>
                            <p v-if="signatureImageForm.errors.image" class="mt-2 text-sm text-error">{{ signatureImageForm.errors.image }}</p>
                            <p v-if="signatureDeleteForm.errors.report_key" class="mt-2 text-sm text-error">{{ signatureDeleteForm.errors.report_key }}</p>
                        </div>

                        <form class="space-y-5" @submit.prevent="submitSignatures">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <SmartSelect
                                        v-model="signatureReportKey"
                                        :label="t('Jenis Laporan')"
                                        :options="signatureReportOptions"
                                        required
                                    />
                                </div>
                                <AppButton type="button" variant="secondary" size="default" class="shrink-0" icon="auto_awesome" @click="applySignatureStarter">
                                    {{ t('Isi Template 1×3') }}
                                </AppButton>
                            </div>
                            <AppRichEditor :key="signatureReportKey" v-model="currentSignatureHtml" :placeholder="t('Sisipkan tabel...')" />
                            <p v-if="signatureForm.errors.templates" class="text-sm text-error">{{ signatureForm.errors.templates }}</p>
                            <div class="flex flex-wrap justify-end gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="submit" icon="save" :loading="signatureForm.processing" :disabled="signatureForm.processing">{{ t('Simpan Tanda Tangan') }}</AppButton>
                            </div>
                        </form>
                    </div>
                    </div>
                </Transition>
            </div>

            <AppModal v-model="showSignaturePad" :title="t('Gambar Tanda Tangan')" size="md" centered>
                <SignaturePad ref="signatureImagePad" @save="saveSignatureImage" @cancel="showSignaturePad = false" />
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.tab-fade-enter-active,
.tab-fade-leave-active {
    transition: opacity 180ms cubic-bezier(0.16, 1, 0.3, 1), transform 180ms cubic-bezier(0.16, 1, 0.3, 1);
}
.tab-fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}
.tab-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
@media (prefers-reduced-motion: reduce) {
    .tab-fade-enter-active,
    .tab-fade-leave-active {
        transition: none;
    }
}
</style>
