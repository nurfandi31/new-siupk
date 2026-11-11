<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppConfirmDialog from '../../../Components/AppConfirmDialog.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';
import { useI18n } from '../../../i18n';

const { t } = useI18n();
const { confirm } = useConfirm();
const toast = useToast();

const props = defineProps({
    tenant: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
    modules: { type: Object, default: null },
});

const subForm = useForm({
    plan_id: props.tenant.active_subscription?.plan?.row_id || '',
    status: 'active',
    starts_at: props.tenant.active_subscription?.starts_at || new Date().toISOString().slice(0, 10),
    ends_at: props.tenant.active_subscription?.ends_at || '',
});

const suspendForm = useForm({});
const activateForm = useForm({});
const repairForm = useForm({});
const invoiceForm = useForm({});

const savings = computed(() => props.modules);
const savingsTables = computed(() => {
    if (!savings.value) return [];

    const labels = savings.value.table_labels ?? {};
    const counts = savings.value.row_counts ?? {};

    return Object.entries(savings.value.tables ?? {}).map(([key, table]) => ({
        key,
        label: labels[key] ?? key,
        table,
        count: counts[key],
    }));
});

const savingsHasTables = computed(() => {
    if (!savings.value?.tables_exist) return false;

    return Object.values(savings.value.tables_exist).some(Boolean);
});

async function enableSavings() {
    const ok = await confirm({
        title: t('Aktifkan Modul Simpanan?'),
        message: t('Tabel simpanan khusus lokasi ini akan dibuat beserta jenis simpanan default (Pokok, Wajib, Sukarela, Berjangka).'),
        confirmLabel: t('Aktifkan'),
        variant: 'primary',
        icon: 'savings',
    });
    if (!ok) return;

    router.post(`/admin/tenants/${props.tenant.row_id}/modules/simpanan/enable`, {}, { preserveScroll: true });
}

async function deactivateSavings() {
    const ok = await confirm({
        title: t('Nonaktifkan Modul Simpanan?'),
        message: t('Menu simpanan disembunyikan untuk lokasi ini, tetapi tabel dan seluruh data tetap tersimpan.'),
        confirmLabel: t('Nonaktifkan'),
        variant: 'danger',
        icon: 'visibility_off',
    });
    if (!ok) return;

    router.post(`/admin/tenants/${props.tenant.row_id}/modules/simpanan/deactivate`, {}, { preserveScroll: true });
}

async function disableSavings() {
    if (savings.value?.has_data) {
        await confirm({
            title: t('Tabel Tidak Dapat Dihapus'),
            message: t('Tabel simpanan masih berisi data. Kosongkan data terlebih dahulu, atau cukup nonaktifkan modul tanpa menghapus tabel.'),
            confirmLabel: t('Mengerti'),
            cancelLabel: '',
            variant: 'warning',
            icon: 'warning',
        });

        return;
    }

    const ok = await confirm({
        title: t('Hapus Tabel Simpanan?'),
        message: t('Seluruh tabel simp_* lokasi ini akan dihapus permanen. Tindakan ini hanya dapat dilakukan saat semua tabel kosong.'),
        confirmLabel: t('Hapus Tabel'),
        variant: 'danger',
        icon: 'delete_forever',
    });
    if (!ok) return;

    router.post(`/admin/tenants/${props.tenant.row_id}/modules/simpanan/disable`, {}, { preserveScroll: true });
}


const impersonating = ref(false);
const impersonatingDomain = ref(null);
const impersonatingUserId = ref(null);

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function statusBadge(status) {
    if (status === 'active') return { tone: 'success-soft', label: t('Aktif') };
    if (status === 'suspended') return { tone: 'error-soft', label: t('Suspended') };
    return { tone: 'neutral', label: status || '—' };
}

function userStatusBadge(status) {
    return status === 'active'
        ? { tone: 'success-soft', label: t('Aktif') }
        : { tone: 'neutral', label: status || '—' };
}

const tenantStatus = computed(() => statusBadge(props.tenant.status));
const initial = computed(() => (props.tenant.name || '?').charAt(0).toUpperCase());

function assignPlan() {
    subForm.post(`/admin/tenants/${props.tenant.row_id}/subscription`, { preserveScroll: true });
}

function suspend() {
    suspendForm.post(`/admin/tenants/${props.tenant.row_id}/suspend`, { preserveScroll: true });
}

function activate() {
    activateForm.post(`/admin/tenants/${props.tenant.row_id}/activate`, { preserveScroll: true });
}

function repair() {
    repairForm.post(`/admin/tenants/${props.tenant.row_id}/repair`, { preserveScroll: true });
}

function generateInvoice() {
    if (!props.tenant.active_subscription?.row_id) return;
    invoiceForm.post(`/admin/subscriptions/${props.tenant.active_subscription.row_id}/invoices`, { preserveScroll: true });
}

async function autoLogin(domain = null, userId = null) {
    if (domain) {
        impersonatingDomain.value = domain;
    } else if (userId) {
        impersonatingUserId.value = userId;
    } else {
        impersonating.value = true;
    }

    try {
        const response = await fetch(`/admin/tenants/${props.tenant.row_id}/impersonate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                domain: domain || undefined,
                user_id: userId || undefined,
            }),
        });

        const data = await response.json();
        if (!response.ok || !data.redirect_url) {
            toast.error(data.message || t('Gagal membuat sesi auto-login.'));
            return;
        }

        window.open(data.redirect_url, '_blank');
    } catch (err) {
        console.error(err);
        toast.error(t('Terjadi kesalahan saat memulai auto-login.'));
    } finally {
        impersonating.value = false;
        impersonatingDomain.value = null;
        impersonatingUserId.value = null;
    }
}
</script>

<template>
    <Head :title="tenant.name" />
    <AdminLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Header -->
            <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-4">
                    <Link href="/admin/tenants" :aria-label="t('Kembali ke daftar tenant')"
                        class="grid size-11 shrink-0 place-items-center rounded-full bg-primary-container text-primary transition hover:bg-primary hover:text-on-primary">
                        <AppIcon name="arrow_back" class="text-xl" />
                    </Link>
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold leading-tight text-primary">{{ tenant.name }}</h1>
                            <AppBadge :tone="tenantStatus.tone">{{ tenantStatus.label }}</AppBadge>
                        </div>
                        <p class="flex flex-wrap items-center gap-x-1.5 text-sm text-on-surface-variant">
                            <span class="font-mono">{{ tenant.code }}</span>
                            <span class="text-outline">·</span>
                            <span>{{ t('kec.') }} {{ tenant.district_code || '—' }}</span>
                            <template v-if="tenant.placement?.shard?.code">
                                <span class="text-outline">·</span>
                                <span>shard {{ tenant.placement.shard.code }}</span>
                            </template>
                        </p>
                    </div>
                </div>
            </header>

            <!-- Status banners -->
            <div v-if="tenant.status === 'suspended'" class="flex items-start gap-3 rounded-xl border border-error/30 bg-error/10 px-4 py-3">
                <AppIcon name="block" class="mt-0.5 text-2xl text-error" />
                <div class="flex-1">
                    <p class="text-sm font-bold text-error">{{ t('Tenant disuspend') }}</p>
                    <p class="text-xs text-on-surface-variant">
                        {{ t('Tenant tidak dapat diakses sampai diaktifkan kembali.') }}
                        <template v-if="tenant.suspended_at">{{ t('Sejak') }} {{ tenant.suspended_at }}.</template>
                    </p>
                </div>
                <AppButton size="compact" variant="success" :loading="activateForm.processing" @click="activate">{{ t('Aktifkan') }}</AppButton>
            </div>
            <div v-else-if="!tenant.provisioned_at" class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3">
                <AppIcon name="warning" class="mt-0.5 text-2xl text-warning" />
                <div class="flex-1">
                    <p class="text-sm font-bold text-warning">{{ t('Tenant belum di-provision') }}</p>
                    <p class="text-xs text-on-surface-variant">{{ t('Schema shard belum disiapkan. Jalankan "Lengkapi provision" untuk inisialisasi.') }}</p>
                </div>
                <AppButton size="compact" variant="primary" icon="build" :loading="repairForm.processing" @click="repair">{{ t('Lengkapi provision') }}</AppButton>
            </div>

            <!-- Action toolbar -->
            <AppCard :padded="false">
                <div class="space-y-3 px-5 py-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <AppIcon name="settings" class="text-outline" />
                        <span>{{ t('Tindakan') }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <AppButton
                            v-if="tenant.status === 'active'"
                            class="min-w-0 flex-1 sm:min-w-[150px]"
                            variant="primary"
                            icon="login"
                            :loading="impersonating"
                            @click="autoLogin()"
                        >{{ t('Login Tenant') }}</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="edit" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/edit`)">{{ t('Edit') }}</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="group" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/users`)">{{ t('Users') }}</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="account_balance_wallet" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/onboarding/import`)">{{ t('Onboarding') }}</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="cleaning_services" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/data-purifier`)">{{ t('Data Purifier') }}</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="receipt_long" @click="$inertia.visit(`/admin/invoices/create?tenant_id=${tenant.row_id}`)">{{ t('Buat Invoice') }}</AppButton>
                        <AppButton v-if="tenant.status === 'active'" class="min-w-0 flex-1 sm:min-w-[150px]" variant="danger" :loading="suspendForm.processing" @click="suspend">{{ t('Suspend') }}</AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Modul Simpanan -->
            <AppCard :padded="false" class="overflow-hidden border border-outline-variant">
                <div class="flex flex-col gap-3 bg-gradient-to-r from-secondary-container/60 via-surface-container-low to-surface p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-surface text-secondary shadow-sm">
                            <AppIcon name="savings" class="text-2xl" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-bold text-primary">{{ t('Modul Simpanan') }}</h2>
                                <AppBadge v-if="savings" :tone="savings.is_enabled ? 'success-soft' : 'neutral'">
                                    {{ savings.is_enabled ? t('Aktif') : t('Nonaktif') }}
                                </AppBadge>
                                <AppBadge v-else tone="error-soft">{{ t('Tidak terbaca') }}</AppBadge>
                            </div>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                {{ t('Tabel simpanan dibuat khusus per lokasi (prefix simp_) agar lokasi tanpa simpanan tidak memakai penyimpanan ekstra.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <AppButton
                            v-if="!savings || !savings.is_enabled"
                            variant="primary"
                            icon="add_circle"
                            @click="enableSavings"
                        >
                            {{ savings?.flag_enabled ? t('Perbaiki Tabel') : savingsHasTables ? t('Aktifkan Kembali') : t('Aktifkan Modul') }}
                        </AppButton>
                        <template v-else>
                            <AppButton variant="secondary" icon="visibility_off" @click="deactivateSavings">
                                {{ t('Nonaktifkan') }}
                            </AppButton>
                            <AppButton
                                variant="danger"
                                icon="delete_forever"
                                :disabled="savings.has_data"
                                :title="savings.has_data ? t('Tabel masih berisi data') : t('Hapus tabel')"
                                @click="disableSavings"
                            >
                                {{ t('Hapus Tabel') }}
                            </AppButton>
                        </template>
                    </div>
                </div>

                <div v-if="savings" class="grid gap-px bg-outline-variant sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="row in savingsTables" :key="row.key" class="bg-surface p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t(row.label) }}</p>
                        <p class="mt-1 truncate font-mono text-xs text-primary">{{ row.table }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <AppBadge :tone="row.count > 0 ? 'success-soft' : 'neutral'">
                                {{ row.count === null ? t('belum dibuat') : row.count + ' ' + t('baris') }}
                            </AppBadge>
                        </div>
                    </div>
                </div>

                <div v-if="savings?.missing_tables?.length && (savings?.flag_enabled || savingsHasTables)" class="flex items-start gap-2 border-t border-outline-variant bg-error/10 px-5 py-3 text-xs text-on-surface-variant">
                    <AppIcon name="error" class="mt-0.5 text-error" />
                    <p>
                        {{ t('Sebagian tabel simpanan belum dibuat:') }}
                        <span class="font-mono">{{ savings.missing_tables.join(', ') }}</span>.
                        {{ t('Klik Perbaiki Tabel untuk membuat ulang tabel yang hilang tanpa menghapus data yang tersisa.') }}
                    </p>
                </div>
                <div v-else-if="savings?.has_data" class="flex items-start gap-2 border-t border-outline-variant bg-warning/10 px-5 py-3 text-xs text-on-surface-variant">
                    <AppIcon name="warning" class="mt-0.5 text-warning" />
                    <p>
                        {{ t('Tabel simpanan berisi data sehingga tidak dapat dihapus. Nonaktifkan modul bila lokasi berhenti memakai simpanan tanpa kehilangan data.') }}
                    </p>
                </div>
                <div v-else-if="!savings" class="border-t border-outline-variant px-5 py-3 text-xs text-on-surface-variant">
                    {{ t('Status modul belum dapat dibaca. Pastikan shard lokasi ini aktif dan sudah dimigrasikan.') }}
                </div>
            </AppCard>

            <!-- Main grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Detail -->
                <AppCard class="lg:col-span-2">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="info" class="text-primary" />
                            <h2 class="font-bold text-primary">{{ t('Detail Tenant') }}</h2>
                        </div>
                        <Link :href="`/admin/tenants/${tenant.row_id}/edit`" class="text-xs font-semibold text-primary hover:underline">{{ t('Edit detail →') }}</Link>
                    </template>

                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">{{ t('Shard') }}</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.placement?.shard?.code || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">{{ t('Database') }}</dt>
                            <dd class="mt-1 truncate font-mono text-sm text-primary">{{ tenant.placement?.shard?.database_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">{{ t('Diprovision') }}</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.provisioned_at || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">{{ t('Disuspend') }}</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.suspended_at || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">{{ t('Koordinat Peta') }}</dt>
                            <dd class="mt-1">
                                <code v-if="tenant.map_latitude && tenant.map_longitude" class="rounded-md bg-surface-container-low px-2 py-1 font-mono text-xs text-primary">
                                    {{ tenant.map_latitude }}, {{ tenant.map_longitude }}
                                    <span class="text-on-surface-variant">· zoom {{ tenant.map_zoom || 13 }}</span>
                                </code>
                                <span v-else class="text-sm text-on-surface-variant">{{ t('Belum diatur') }}</span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-outline-variant pt-5">
                        <div class="flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-primary">
                                <AppIcon name="language" class="text-outline" />
                                {{ t('Custom Domain') }}
                            </h3>
                            <Link :href="`/admin/tenants/${tenant.row_id}/edit`" class="text-xs font-semibold text-primary hover:underline">{{ t('Kelola →') }}</Link>
                        </div>
                        <div v-if="tenant.custom_domains && tenant.custom_domains.length > 0" class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="dom in tenant.custom_domains"
                                :key="dom"
                                class="flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2"
                            >
                                <AppIcon name="public" class="text-outline" />
                                <a :href="`https://${dom}`" target="_blank" rel="noopener noreferrer" class="flex-1 truncate font-mono text-xs font-semibold text-primary hover:underline">{{ dom }}</a>
                                <AppButton
                                    v-if="tenant.status === 'active'"
                                    variant="ghost"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingDomain === dom"
                                    :aria-label="t('Auto login via domain')"
                                    @click="autoLogin(dom)"
                                />
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-on-surface-variant">
                            {{ t('Belum ada custom domain. Tenant diakses via subdomain default atau route identifier.') }}
                        </p>
                    </div>
                </AppCard>

                <!-- Subscription -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="card_membership" class="text-primary" />
                            <h2 class="font-bold text-primary">{{ t('Langganan') }}</h2>
                        </div>
                    </template>

                    <div v-if="tenant.active_subscription" class="space-y-3">
                        <div class="rounded-lg bg-surface-container-low p-3">
                            <p class="text-base font-bold text-primary">{{ tenant.active_subscription.plan?.name || '—' }}</p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">
                                {{ tenant.active_subscription.status }}
                                · {{ tenant.active_subscription.starts_at }} → {{ tenant.active_subscription.ends_at || '∞' }}
                            </p>
                        </div>
                        <AppButton class="w-full" size="compact" variant="secondary" icon="receipt_long" :loading="invoiceForm.processing" @click="generateInvoice">
                            {{ t('Generate Invoice') }}
                        </AppButton>
                    </div>
                    <p v-else class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
                        {{ t('Belum ada langganan aktif.') }}
                    </p>

                    <form class="mt-5 space-y-3 border-t border-outline-variant pt-4" @submit.prevent="assignPlan">
                        <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">{{ t('Tetapkan / Ubah Plan') }}</p>
                        <SmartSelect
                            v-model="subForm.plan_id"
                            :label="t('Plan')"
                            :options="plans.map((p) => ({ value: p.row_id, label: `${p.name} (${money(p.price_amount, p.currency)}/${p.billing_period})` }))"
                            :placeholder="t('Pilih plan')"
                            required
                            :error="subForm.errors.plan_id"
                        />
                        <AppButton type="submit" size="compact" class="w-full" :loading="subForm.processing" :disabled="!plans.length" icon="save">
                            {{ t('Simpan langganan') }}
                        </AppButton>
                    </form>
                </AppCard>
            </div>

            <!-- Users & Invoices -->
            <div class="grid gap-6 lg:grid-cols-2">
                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <AppIcon name="group" class="text-primary" />
                            <h2 class="font-bold text-primary">{{ t('Pengguna') }}</h2>
                            <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ users.length }}</span>
                        </div>
                        <Link :href="`/admin/tenants/${tenant.row_id}/users`" class="text-xs font-semibold text-primary hover:underline">{{ t('Kelola →') }}</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="user in users" :key="user.row_id" class="flex items-center gap-3 px-5 py-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-container text-sm font-bold text-primary">
                                {{ user.name?.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ user.name }}</p>
                                <p class="truncate text-xs text-on-surface-variant">
                                    <span v-if="user.role" class="font-semibold text-primary">{{ user.role }}</span>
                                    <span v-if="user.role"> · </span>
                                    {{ user.username }} · {{ user.email || '—' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <AppButton
                                    v-if="user.status === 'active' && tenant.status === 'active'"
                                    variant="ghost"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingUserId === user.row_id"
                                    :aria-label="t('Login sebagai user ini')"
                                    @click="autoLogin(null, user.row_id)"
                                />
                                <AppBadge :tone="userStatusBadge(user.status).tone">{{ userStatusBadge(user.status).label }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!users.length" class="flex flex-col items-center gap-1 px-5 py-8 text-center text-on-surface-variant">
                            <AppIcon name="person_off" class="text-2xl text-outline" />
                            <span class="text-sm">{{ t('Belum ada user.') }}</span>
                        </li>
                    </ul>
                </AppCard>

                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <AppIcon name="receipt_long" class="text-primary" />
                            <h2 class="font-bold text-primary">{{ t('Invoice Terbaru') }}</h2>
                            <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ invoices.length }}</span>
                        </div>
                        <Link href="/admin/invoices" class="text-xs font-semibold text-primary hover:underline">{{ t('Semua →') }}</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="invoice in invoices" :key="invoice.row_id" class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="block truncate text-sm font-semibold text-primary hover:underline">{{ invoice.number }}</Link>
                                <p class="text-xs text-on-surface-variant">{{ t('Jatuh tempo') }} {{ invoice.due_at || '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-primary">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge :tone="invoice.status === 'paid' ? 'success-soft' : invoice.status === 'overdue' ? 'error-soft' : 'warning-soft'">{{ invoice.status }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!invoices.length" class="flex flex-col items-center gap-1 px-5 py-8 text-center text-on-surface-variant">
                            <AppIcon name="receipt" class="text-2xl text-outline" />
                            <span class="text-sm">{{ t('Belum ada invoice.') }}</span>
                        </li>
                    </ul>
                </AppCard>
            </div>
        </div>

        <AppConfirmDialog />
    </AdminLayout>
</template>
