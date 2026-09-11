<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import TrendBarChart from '../../Components/TrendBarChart.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useMoney } from '../../composables/useMoney.js';

defineProps({
    kpis: { type: Object, required: true },
    chart: { type: Object, required: true },
    recent_tenants: { type: Array, default: () => [] },
    open_invoices: { type: Array, default: () => [] },
});

const { money } = useMoney();
</script>

<template>
    <Head title="Admin Dashboard" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header -->
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Dashboard Bisnis &amp; Platform</h1>
                    <p class="mt-1 text-on-surface-variant">Ringkasan performa finansial SaaS, pertumbuhan tenant, dan penagihan platform.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/admin/revenue">
                        <AppButton variant="secondary" icon="monitoring">Monitor Pendapatan Tenant</AppButton>
                    </Link>
                    <Link href="/admin/invoices/create">
                        <AppButton icon="receipt_long">Terbitkan Tagihan</AppButton>
                    </Link>
                </div>
            </header>

            <!-- Top Business KPIs -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan Bulan Ini</p>
                        <AppIcon name="payments" tone="secondary" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-secondary">{{ money(kpis.revenue_this_month) }}</p>
                    <div class="mt-1.5 flex items-center gap-2 text-xs text-on-surface-variant">
                        <AppBadge v-if="kpis.revenue_last_month > 0" :tone="kpis.revenue_growth >= 0 ? 'success' : 'error'">
                            {{ kpis.revenue_growth >= 0 ? '+' : '' }}{{ kpis.revenue_growth }}% MoM
                        </AppBadge>
                        <span>Tagihan: {{ money(kpis.invoiced_this_month) }}</span>
                    </div>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pendapatan YTD ({{ chart.year }})</p>
                        <AppIcon name="account_balance" tone="primary" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-primary">{{ money(kpis.revenue_ytd) }}</p>
                    <p class="mt-1.5 text-xs text-on-surface-variant">
                        Total Ditagihkan: <strong class="text-on-surface">{{ money(kpis.invoiced_ytd) }}</strong>
                    </p>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Piutang / Outstanding</p>
                        <AppIcon name="pending_actions" tone="warning" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold" :class="kpis.total_outstanding > 0 ? 'text-error' : 'text-primary'">
                        {{ money(kpis.total_outstanding) }}
                    </p>
                    <p class="mt-1.5 text-xs text-on-surface-variant">
                        {{ kpis.invoices_open_count }} tagihan belum lunas
                        <span v-if="kpis.invoices_overdue_count > 0" class="font-bold text-error">
                            ({{ kpis.invoices_overdue_count }} overdue)
                        </span>
                    </p>
                </AppCard>

                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Tenant</p>
                        <AppIcon name="domain" tone="info" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold text-primary">{{ kpis.tenants_active }} <span class="text-sm font-normal text-on-surface-variant">/ {{ kpis.tenants_total }} aktif</span></p>
                    <p class="mt-1.5 text-xs text-on-surface-variant">
                        {{ kpis.users_total }} total pengguna sistem
                    </p>
                </AppCard>
            </div>

            <!-- Revenue Trend Chart -->
            <AppCard>
                <template #header>
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="font-bold text-primary">Tren Finansial &amp; Tagihan Bulanan ({{ chart.year }})</h2>
                            <p class="text-xs text-on-surface-variant">Perbandingan volume tagihan diterbitkan vs realisasi pembayaran terkumpul.</p>
                        </div>
                        <div class="flex items-center gap-4 text-xs font-medium">
                            <span class="inline-flex items-center gap-1.5 text-primary font-semibold">
                                <span class="size-2.5 rounded-sm bg-primary" /> Tagihan Diterbitkan
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-secondary font-semibold">
                                <span class="size-2.5 rounded-sm bg-secondary" /> Pembayaran Masuk
                            </span>
                        </div>
                    </div>
                </template>
                <div class="pt-2">
                    <TrendBarChart :data="chart.data" />
                </div>
            </AppCard>

            <!-- Bottom Lists: Open Invoices & Recent Tenants -->
            <div class="grid gap-6 xl:grid-cols-2">
                <!-- Open Invoices -->
                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="font-bold text-primary">Tagihan Terbuka &amp; Jatuh Tempo</h2>
                            <p class="text-xs text-on-surface-variant">Daftar invoice yang membutuhkan follow-up pembayaran.</p>
                        </div>
                        <Link href="/admin/invoices" class="text-xs font-bold text-primary hover:underline">Lihat Semua →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant/30">
                        <li v-for="invoice in open_invoices" :key="invoice.row_id" class="flex items-center justify-between gap-3 px-6 py-3.5 hover:bg-surface-container-low/40 transition-colors">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="font-semibold text-primary hover:underline text-sm truncate block">
                                    {{ invoice.number }}
                                </Link>
                                <p class="text-xs text-on-surface-variant truncate">
                                    {{ invoice.tenant?.name || '—' }} · Jatuh tempo: <strong :class="invoice.status === 'overdue' ? 'text-error' : 'text-on-surface'">{{ invoice.due_at || '—' }}</strong>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-primary tabular-nums">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge :tone="invoice.status === 'overdue' ? 'error' : 'warning'" class="mt-0.5">
                                    {{ invoice.status === 'overdue' ? 'Overdue' : 'Menunggu' }}
                                </AppBadge>
                            </div>
                        </li>
                        <li v-if="!open_invoices.length" class="px-6 py-8 text-center text-sm text-on-surface-variant">
                            Tidak ada invoice terbuka atau menunggak saat ini.
                        </li>
                    </ul>
                </AppCard>

                <!-- Recent Tenants -->
                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="font-bold text-primary">Tenant Terbaru</h2>
                            <p class="text-xs text-on-surface-variant">BUMDesma yang baru mendaftar atau diprovisioning.</p>
                        </div>
                        <Link href="/admin/tenants" class="text-xs font-bold text-primary hover:underline">Semua Tenant →</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant/30">
                        <li v-for="tenant in recent_tenants" :key="tenant.row_id" class="flex items-center justify-between gap-3 px-6 py-3.5 hover:bg-surface-container-low/40 transition-colors">
                            <div class="min-w-0 flex-1">
                                <Link :href="`/admin/tenants/${tenant.row_id}`" class="font-semibold text-primary hover:underline text-sm truncate block">
                                    {{ tenant.name }}
                                </Link>
                                <p class="text-xs text-on-surface-variant truncate">
                                    {{ tenant.code }} · {{ tenant.active_subscription?.plan?.name || 'Belum ada plan' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <AppBadge :tone="tenant.status === 'active' ? 'success' : tenant.status === 'suspended' ? 'error' : 'neutral'">
                                    {{ tenant.status === 'active' ? 'Aktif' : tenant.status }}
                                </AppBadge>
                            </div>
                        </li>
                        <li v-if="!recent_tenants.length" class="px-6 py-8 text-center text-sm text-on-surface-variant">
                            Belum ada tenant yang terdaftar.
                        </li>
                    </ul>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>
