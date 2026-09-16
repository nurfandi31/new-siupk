<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import TrendDonutChart from '../../Components/TrendDonutChart.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useMoney } from '../../composables/useMoney.js';

const props = defineProps({
    kpis: { type: Object, required: true },
    chart: { type: Object, required: true },
    recent_tenants: { type: Array, default: () => [] },
    open_invoices: { type: Array, default: () => [] },
});

const { money } = useMoney();

const donutSlices = computed(() => {
    const disbursed = (props.chart.data || []).reduce(
        (sum, row) => sum + Number(row.disbursed || 0),
        0,
    );
    const collected = (props.chart.data || []).reduce(
        (sum, row) => sum + Number(row.collected || 0),
        0,
    );
    return [
        { key: 'collected', label: 'Pembayaran Masuk', value: collected },
        { key: 'outstanding', label: 'Sisa Tagihan', value: Math.max(disbursed - collected, 0) },
    ];
});
</script>

<template>
    <Head title="Admin Dashboard - siupk Next" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Hero Header -->
            <header class="relative overflow-hidden rounded-2xl border border-outline-variant/60 bg-gradient-to-br from-primary-container via-primary-container/70 to-tertiary-fixed/40 p-6 shadow-sm sm:p-8">
                <div class="pointer-events-none absolute -right-12 -top-12 size-48 rounded-full bg-primary/10 blur-3xl" aria-hidden="true" />
                <div class="pointer-events-none absolute -bottom-16 -left-8 size-56 rounded-full bg-secondary-container/30 blur-3xl" aria-hidden="true" />

                <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-extrabold tracking-tight text-on-surface sm:text-3xl">
                            Dashboard Bisnis &amp; Platform
                        </h1>
                        <p class="mt-1.5 max-w-xl text-sm text-on-surface-variant">
                            Ringkasan operasional SaaS hari ini.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-row items-center gap-2">
                        <Link href="/admin/revenue">
                            <AppButton variant="secondary" icon="monitoring">Monitor Pendapatan</AppButton>
                        </Link>
                        <Link href="/admin/invoices/create">
                            <AppButton icon="receipt_long">Terbitkan Tagihan</AppButton>
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Top KPIs -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard class="admin-kpi">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan Bulan Ini</p>
                        <AppIcon name="payments" tone="secondary" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-secondary">{{ money(kpis.revenue_this_month) }}</p>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <AppBadge v-if="kpis.revenue_last_month > 0" :tone="kpis.revenue_growth >= 0 ? 'success' : 'error'">
                            {{ kpis.revenue_growth >= 0 ? '+' : '' }}{{ kpis.revenue_growth }}% MoM
                        </AppBadge>
                        <span class="text-on-surface-variant">Tagihan: <strong class="text-on-surface">{{ money(kpis.invoiced_this_month) }}</strong></span>
                    </div>
                </AppCard>

                <AppCard class="admin-kpi">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan YTD</p>
                        <AppIcon name="account_balance" tone="primary" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-primary">{{ money(kpis.revenue_ytd) }}</p>
                    <p class="mt-2 text-xs text-on-surface-variant">
                        Ditagihkan: <strong class="text-on-surface">{{ money(kpis.invoiced_ytd) }}</strong>
                    </p>
                </AppCard>

                <AppCard class="admin-kpi">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Piutang</p>
                        <AppIcon name="pending_actions" tone="warning" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold" :class="kpis.total_outstanding > 0 ? 'text-error' : 'text-primary'">
                        {{ money(kpis.total_outstanding) }}
                    </p>
                    <p class="mt-2 text-xs text-on-surface-variant">
                        {{ kpis.invoices_open_count }} belum lunas
                        <span v-if="kpis.invoices_overdue_count > 0" class="ml-1 font-bold text-error">
                            ({{ kpis.invoices_overdue_count }} overdue)
                        </span>
                    </p>
                </AppCard>

                <AppCard class="admin-kpi">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tenant</p>
                        <AppIcon name="domain" tone="info" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-primary">
                        {{ kpis.tenants_active }}
                        <span class="text-sm font-normal text-on-surface-variant">/ {{ kpis.tenants_total }}</span>
                    </p>
                    <p class="mt-2 text-xs text-on-surface-variant">
                        {{ kpis.users_total }} pengguna
                    </p>
                </AppCard>
            </div>

            <!-- Bottom row: Donut chart (narrow) + 2 lists -->
            <div class="grid gap-6 xl:grid-cols-3">
                <!-- Donut Chart -->
                <AppCard>
                    <template #header>
                        <div>
                            <h2 class="font-bold text-primary">Komposisi Tagihan</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Pembayaran masuk vs sisa tagihan.</p>
                        </div>
                    </template>
                    <TrendDonutChart
                        :data="donutSlices"
                        center-label="Total"
                        :size="180"
                    />
                </AppCard>

                <!-- Open Invoices -->
                <AppCard :padded="false">
                    <div class="flex items-center justify-between gap-3 border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="font-bold text-primary">Tagihan Terbuka</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Menunggu &amp; jatuh tempo.</p>
                        </div>
                        <Link href="/admin/invoices" class="text-xs font-bold text-primary hover:underline shrink-0">Lihat Semua →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant/30">
                        <li v-for="invoice in open_invoices" :key="invoice.row_id" class="flex items-center justify-between gap-3 px-6 py-3.5 transition-colors hover:bg-surface-container-low/40">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="block truncate text-sm font-semibold text-primary hover:underline">
                                    {{ invoice.number }}
                                </Link>
                                <p class="truncate text-xs text-on-surface-variant">
                                    {{ invoice.tenant?.name || '—' }} · Jatuh tempo: <strong :class="invoice.status === 'overdue' ? 'text-error' : 'text-on-surface'">{{ invoice.due_at || '—' }}</strong>
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-sm font-bold text-primary tabular-nums">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge :tone="invoice.status === 'overdue' ? 'error' : 'warning'" class="mt-0.5">
                                    {{ invoice.status === 'overdue' ? 'Overdue' : 'Menunggu' }}
                                </AppBadge>
                            </div>
                        </li>
                        <li v-if="!open_invoices.length" class="px-6 py-10 text-center text-sm text-on-surface-variant">
                            <AppIcon name="check_circle" class="text-3xl text-secondary" />
                            <p class="mt-2">Tidak ada tagihan terbuka.</p>
                        </li>
                    </ul>
                </AppCard>

                <!-- Recent Tenants -->
                <AppCard :padded="false">
                    <div class="flex items-center justify-between gap-3 border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="font-bold text-primary">Tenant Terbaru</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Baru terdaftar.</p>
                        </div>
                        <Link href="/admin/tenants" class="text-xs font-bold text-primary hover:underline shrink-0">Semua Tenant →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant/30">
                        <li v-for="tenant in recent_tenants" :key="tenant.row_id" class="flex items-center justify-between gap-3 px-6 py-3.5 transition-colors hover:bg-surface-container-low/40">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-container text-xs font-bold text-primary">
                                    {{ tenant.name?.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <Link :href="`/admin/tenants/${tenant.row_id}`" class="block truncate text-sm font-semibold text-primary hover:underline">
                                        {{ tenant.name }}
                                    </Link>
                                    <p class="truncate text-xs text-on-surface-variant">
                                        {{ tenant.code }} · {{ tenant.active_subscription?.plan?.name || 'Tanpa plan' }}
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <AppBadge :tone="tenant.status === 'active' ? 'success' : tenant.status === 'suspended' ? 'error' : 'neutral'">
                                    {{ tenant.status === 'active' ? 'Aktif' : tenant.status }}
                                </AppBadge>
                            </div>
                        </li>
                        <li v-if="!recent_tenants.length" class="px-6 py-10 text-center text-sm text-on-surface-variant">
                            <AppIcon name="domain" class="text-3xl text-primary" />
                            <p class="mt-2">Belum ada tenant.</p>
                        </li>
                    </ul>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.admin-kpi {
    position: relative;
    overflow: hidden;
    transition: transform 200ms cubic-bezier(0.16, 1, 0.3, 1), box-shadow 200ms ease;
}
.admin-kpi::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 60%, color-mix(in srgb, var(--color-primary) 6%, transparent));
    pointer-events: none;
    opacity: 0;
    transition: opacity 220ms ease;
}
.admin-kpi:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -8px color-mix(in srgb, var(--color-primary) 25%, transparent);
}
.admin-kpi:hover::after {
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .admin-kpi,
    .admin-kpi:hover {
        transform: none;
        transition: none;
    }
}
</style>
