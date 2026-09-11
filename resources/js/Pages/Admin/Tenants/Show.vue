<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
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
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <Link href="/admin/tenants" class="text-sm font-semibold text-primary">← Daftar tenant</Link>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ tenant.name }}</h1>
                        <AppBadge :tone="tenant.status === 'active' ? 'success' : tenant.status === 'suspended' ? 'error' : 'neutral'">{{ tenant.status }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">{{ tenant.code }} · kecamatan {{ tenant.district_code || '—' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <AppButton
                        v-if="tenant.status === 'active'"
                        variant="primary"
                        icon="login"
                        :loading="impersonating"
                        @click="autoLogin()"
                    >
                        Auto Login Tenant
                    </AppButton>
                    <Link :href="`/admin/tenants/${tenant.row_id}/edit`"><AppButton variant="secondary" icon="edit">Edit</AppButton></Link>
                    <Link :href="`/admin/tenants/${tenant.row_id}/users`"><AppButton variant="secondary" icon="group">Users</AppButton></Link>
                    <Link :href="`/admin/tenants/${tenant.row_id}/onboarding/import`"><AppButton variant="secondary" icon="account_balance_wallet">Onboarding / Saldo Awal</AppButton></Link>
                    <Link :href="`/admin/tenants/${tenant.row_id}/data-purifier`"><AppButton variant="secondary" icon="cleaning_services">Data Purifier</AppButton></Link>
                    <Link :href="`/admin/invoices/create?tenant_id=${tenant.row_id}`"><AppButton variant="secondary" icon="receipt_long">Buat Invoice</AppButton></Link>
                    <AppButton variant="secondary" icon="build" :loading="repairForm.processing" @click="repair">Lengkapi provision</AppButton>
                    <AppButton v-if="tenant.status !== 'suspended'" variant="danger" :loading="suspendForm.processing" @click="suspend">Suspend</AppButton>
                    <AppButton v-else variant="success" :loading="activateForm.processing" @click="activate">Aktifkan</AppButton>
                </div>
            </header>

            <div class="grid gap-6 xl:grid-cols-3">
                <AppCard class="xl:col-span-2">
                    <h2 class="font-bold text-primary">Detail</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-on-surface-variant">Shard</dt><dd class="font-semibold text-primary">{{ tenant.placement?.shard?.code || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Database</dt><dd class="font-semibold text-primary">{{ tenant.placement?.shard?.database_name || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Provisioned</dt><dd class="font-semibold text-primary">{{ tenant.provisioned_at || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Suspended</dt><dd class="font-semibold text-primary">{{ tenant.suspended_at || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Koordinat Peta</dt><dd class="font-semibold text-primary font-mono text-xs">{{ tenant.map_latitude && tenant.map_longitude ? tenant.map_latitude + ', ' + tenant.map_longitude + ' (Zoom: ' + (tenant.map_zoom || 13) + ')' : 'Belum diatur' }}</dd></div>
                    </dl>

                    <div class="mt-6 border-t border-outline-variant pt-4">
                        <div class="flex items-center justify-between">
                            <dt class="text-sm font-semibold text-primary">Custom Domain Terdaftar</dt>
                            <Link :href="`/admin/tenants/${tenant.row_id}/edit`" class="text-xs font-semibold text-primary hover:underline">
                                Kelola Domain →
                            </Link>
                        </div>
                        <div v-if="tenant.custom_domains && tenant.custom_domains.length > 0" class="mt-2.5 flex flex-wrap gap-2">
                            <div
                                v-for="dom in tenant.custom_domains"
                                :key="dom"
                                class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-1.5 text-xs font-semibold text-primary"
                            >
                                <AppIcon name="language" class="text-sm text-outline" />
                                <a :href="`https://${dom}`" target="_blank" rel="noopener noreferrer" class="hover:underline flex items-center gap-1">
                                    <span>{{ dom }}</span>
                                    <AppIcon name="open_in_new" class="text-xs text-outline" />
                                </a>
                                <AppButton
                                    v-if="tenant.status === 'active'"
                                    variant="ghost"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingDomain === dom"
                                    aria-label="Auto login via domain ini"
                                    @click="autoLogin(dom)"
                                >
                                    Login
                                </AppButton>
                            </div>
                        </div>
                        <p v-else class="mt-1 text-xs text-on-surface-variant">
                            Belum ada custom domain. Tenant diakses via subdomain default atau route identifier.
                        </p>
                    </div>
                </AppCard>

                <AppCard>
                    <h2 class="font-bold text-primary">Langganan</h2>
                    <div v-if="tenant.active_subscription" class="mt-3 space-y-1">
                        <p class="font-semibold text-primary">{{ tenant.active_subscription.plan?.name || '—' }}</p>
                        <p class="text-sm text-on-surface-variant">{{ tenant.active_subscription.status }} · {{ tenant.active_subscription.starts_at }} → {{ tenant.active_subscription.ends_at || '∞' }}</p>
                        <AppButton class="mt-3" size="compact" variant="secondary" :loading="invoiceForm.processing" @click="generateInvoice">Generate invoice</AppButton>
                    </div>
                    <p v-else class="mt-3 text-sm text-on-surface-variant">Belum ada langganan aktif.</p>

                    <form class="mt-5 space-y-3 border-t border-outline-variant pt-4" @submit.prevent="assignPlan">
                        <SmartSelect
                            v-model="subForm.plan_id"
                            label="Tetapkan plan"
                            :options="plans.map((p) => ({ value: p.row_id, label: `${p.name} (${money(p.price_amount, p.currency)}/${p.billing_period})` }))"
                            placeholder="Pilih plan"
                            required
                            :error="subForm.errors.plan_id"
                        />
                        <AppButton type="submit" size="compact" :loading="subForm.processing" :disabled="!plans.length">Simpan langganan</AppButton>
                    </form>
                </AppCard>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <h2 class="font-bold text-primary">Pengguna</h2>
                        <Link :href="`/admin/tenants/${tenant.row_id}/users`" class="text-sm font-semibold text-primary">Kelola</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="user in users" :key="user.row_id" class="flex items-center justify-between px-6 py-3">
                            <div>
                                <p class="font-semibold text-primary">{{ user.name }}</p>
                                <p class="text-sm text-on-surface-variant">{{ user.username }} · {{ user.email || '—' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <AppButton
                                    v-if="user.status === 'active' && tenant.status === 'active'"
                                    variant="ghost"
                                    size="compact"
                                    icon="login"
                                    :loading="impersonatingUserId === user.row_id"
                                    aria-label="Login sebagai pengguna ini"
                                    @click="autoLogin(null, user.row_id)"
                                >
                                    Login User
                                </AppButton>
                                <AppBadge :tone="user.status === 'active' ? 'success' : 'neutral'">{{ user.status }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!users.length" class="px-6 py-8 text-center text-on-surface-variant">Belum ada user.</li>
                    </ul>
                </AppCard>

                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <h2 class="font-bold text-primary">Invoice terbaru</h2>
                        <Link href="/admin/invoices" class="text-sm font-semibold text-primary">Semua</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="invoice in invoices" :key="invoice.row_id" class="flex items-center justify-between px-6 py-3">
                            <div>
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="font-semibold text-primary">{{ invoice.number }}</Link>
                                <p class="text-sm text-on-surface-variant">{{ invoice.due_at || '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-primary">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge>{{ invoice.status }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!invoices.length" class="px-6 py-8 text-center text-on-surface-variant">Belum ada invoice.</li>
                    </ul>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>
