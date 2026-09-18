<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    users: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    plans: { type: Array, default: () => [] },
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

const impersonating = ref(false);
const impersonatingDomain = ref(null);
const impersonatingUserId = ref(null);

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function statusBadge(status) {
    if (status === 'active') return { tone: 'success-soft', label: 'Aktif' };
    if (status === 'suspended') return { tone: 'error-soft', label: 'Suspended' };
    return { tone: 'neutral', label: status || '—' };
}

function userStatusBadge(status) {
    return status === 'active'
        ? { tone: 'success-soft', label: 'Aktif' }
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
            alert(data.message || 'Gagal membuat sesi auto-login.');
            return;
        }

        window.open(data.redirect_url, '_blank');
    } catch (err) {
        console.error(err);
        alert('Terjadi kesalahan saat memulai auto-login.');
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
                    <Link href="/admin/tenants" aria-label="Kembali ke daftar tenant"
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
                            <span>kec. {{ tenant.district_code || '—' }}</span>
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
                    <p class="text-sm font-bold text-error">Tenant disuspend</p>
                    <p class="text-xs text-on-surface-variant">
                        Tenant tidak dapat diakses sampai diaktifkan kembali.
                        <template v-if="tenant.suspended_at">Sejak {{ tenant.suspended_at }}.</template>
                    </p>
                </div>
                <AppButton size="compact" variant="success" :loading="activateForm.processing" @click="activate">Aktifkan</AppButton>
            </div>
            <div v-else-if="!tenant.provisioned_at" class="flex items-start gap-3 rounded-xl border border-warning/40 bg-warning/10 px-4 py-3">
                <AppIcon name="warning" class="mt-0.5 text-2xl text-warning" />
                <div class="flex-1">
                    <p class="text-sm font-bold text-warning">Tenant belum di-provision</p>
                    <p class="text-xs text-on-surface-variant">Schema shard belum disiapkan. Jalankan "Lengkapi provision" untuk inisialisasi.</p>
                </div>
                <AppButton size="compact" variant="primary" icon="build" :loading="repairForm.processing" @click="repair">Lengkapi provision</AppButton>
            </div>

            <!-- Action toolbar -->
            <AppCard :padded="false">
                <div class="space-y-3 px-5 py-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <AppIcon name="settings" class="text-outline" />
                        <span>Tindakan</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <AppButton
                            v-if="tenant.status === 'active'"
                            class="min-w-0 flex-1 sm:min-w-[150px]"
                            variant="primary"
                            icon="login"
                            :loading="impersonating"
                            @click="autoLogin()"
                        >Login Tenant</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="edit" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/edit`)">Edit</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="group" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/users`)">Users</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="account_balance_wallet" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/onboarding/import`)">Onboarding</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="cleaning_services" @click="$inertia.visit(`/admin/tenants/${tenant.row_id}/data-purifier`)">Data Purifier</AppButton>
                        <AppButton class="min-w-0 flex-1 sm:min-w-[150px]" variant="secondary" icon="receipt_long" @click="$inertia.visit(`/admin/invoices/create?tenant_id=${tenant.row_id}`)">Buat Invoice</AppButton>
                        <AppButton v-if="tenant.status === 'active'" class="min-w-0 flex-1 sm:min-w-[150px]" variant="danger" :loading="suspendForm.processing" @click="suspend">Suspend</AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Main grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Detail -->
                <AppCard class="lg:col-span-2">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="info" class="text-primary" />
                            <h2 class="font-bold text-primary">Detail Tenant</h2>
                        </div>
                        <Link :href="`/admin/tenants/${tenant.row_id}/edit`" class="text-xs font-semibold text-primary hover:underline">Edit detail →</Link>
                    </template>

                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">Shard</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.placement?.shard?.code || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">Database</dt>
                            <dd class="mt-1 truncate font-mono text-sm text-primary">{{ tenant.placement?.shard?.database_name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">Diprovision</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.provisioned_at || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">Disuspend</dt>
                            <dd class="mt-1 font-semibold text-primary">{{ tenant.suspended_at || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-on-surface-variant">Koordinat Peta</dt>
                            <dd class="mt-1">
                                <code v-if="tenant.map_latitude && tenant.map_longitude" class="rounded-md bg-surface-container-low px-2 py-1 font-mono text-xs text-primary">
                                    {{ tenant.map_latitude }}, {{ tenant.map_longitude }}
                                    <span class="text-on-surface-variant">· zoom {{ tenant.map_zoom || 13 }}</span>
                                </code>
                                <span v-else class="text-sm text-on-surface-variant">Belum diatur</span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-6 border-t border-outline-variant pt-5">
                        <div class="flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-sm font-bold text-primary">
                                <AppIcon name="language" class="text-outline" />
                                Custom Domain
                            </h3>
                            <Link :href="`/admin/tenants/${tenant.row_id}/edit`" class="text-xs font-semibold text-primary hover:underline">Kelola →</Link>
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
                                    aria-label="Auto login via domain"
                                    @click="autoLogin(dom)"
                                />
                            </div>
                        </div>
                        <p v-else class="mt-3 text-xs text-on-surface-variant">
                            Belum ada custom domain. Tenant diakses via subdomain default atau route identifier.
                        </p>
                    </div>
                </AppCard>

                <!-- Subscription -->
                <AppCard>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <AppIcon name="card_membership" class="text-primary" />
                            <h2 class="font-bold text-primary">Langganan</h2>
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
                            Generate Invoice
                        </AppButton>
                    </div>
                    <p v-else class="rounded-lg bg-surface-container-low p-3 text-sm text-on-surface-variant">
                        Belum ada langganan aktif.
                    </p>

                    <form class="mt-5 space-y-3 border-t border-outline-variant pt-4" @submit.prevent="assignPlan">
                        <p class="text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Tetapkan / Ubah Plan</p>
                        <SmartSelect
                            v-model="subForm.plan_id"
                            label="Plan"
                            :options="plans.map((p) => ({ value: p.row_id, label: `${p.name} (${money(p.price_amount, p.currency)}/${p.billing_period})` }))"
                            placeholder="Pilih plan"
                            required
                            :error="subForm.errors.plan_id"
                        />
                        <AppButton type="submit" size="compact" class="w-full" :loading="subForm.processing" :disabled="!plans.length" icon="save">
                            Simpan langganan
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
                            <h2 class="font-bold text-primary">Pengguna</h2>
                            <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ users.length }}</span>
                        </div>
                        <Link :href="`/admin/tenants/${tenant.row_id}/users`" class="text-xs font-semibold text-primary hover:underline">Kelola →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="user in users" :key="user.row_id" class="flex items-center gap-3 px-5 py-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-container text-sm font-bold text-primary">
                                {{ user.name?.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-primary">{{ user.name }}</p>
                                <p class="truncate text-xs text-on-surface-variant">{{ user.username }} · {{ user.email || '—' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <AppButton
                                    v-if="user.status === 'active' && tenant.status === 'active'"
                                    variant="ghost"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingUserId === user.row_id"
                                    aria-label="Login sebagai user ini"
                                    @click="autoLogin(null, user.row_id)"
                                />
                                <AppBadge :tone="userStatusBadge(user.status).tone">{{ userStatusBadge(user.status).label }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!users.length" class="flex flex-col items-center gap-1 px-5 py-8 text-center text-on-surface-variant">
                            <AppIcon name="person_off" class="text-2xl text-outline" />
                            <span class="text-sm">Belum ada user.</span>
                        </li>
                    </ul>
                </AppCard>

                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-5 py-3.5">
                        <div class="flex items-center gap-2">
                            <AppIcon name="receipt_long" class="text-primary" />
                            <h2 class="font-bold text-primary">Invoice Terbaru</h2>
                            <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-xs font-bold text-on-surface-variant">{{ invoices.length }}</span>
                        </div>
                        <Link href="/admin/invoices" class="text-xs font-semibold text-primary hover:underline">Semua →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="invoice in invoices" :key="invoice.row_id" class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="block truncate text-sm font-semibold text-primary hover:underline">{{ invoice.number }}</Link>
                                <p class="text-xs text-on-surface-variant">Jatuh tempo {{ invoice.due_at || '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-primary">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge :tone="invoice.status === 'paid' ? 'success-soft' : invoice.status === 'overdue' ? 'error-soft' : 'warning-soft'">{{ invoice.status }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!invoices.length" class="flex flex-col items-center gap-1 px-5 py-8 text-center text-on-surface-variant">
                            <AppIcon name="receipt" class="text-2xl text-outline" />
                            <span class="text-sm">Belum ada invoice.</span>
                        </li>
                    </ul>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>
