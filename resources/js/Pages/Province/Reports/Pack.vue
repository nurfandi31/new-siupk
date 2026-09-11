<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppCard from '../../../Components/AppCard.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import { useMoney } from '../../../composables/useMoney';
import ProvinceLayout from '../../../Layouts/ProvinceLayout.vue';

const props = defineProps({
    pack: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    province_name: { type: String, default: 'Provinsi' },
});

const { money } = useMoney();
const activeTab = ref('balance');
const baseUrl = '/province/reports/pack';

const tabs = [
    { key: 'balance', label: '1. Neraca Konsolidasi' },
    { key: 'income', label: '2. Laba Rugi Konsolidasi' },
    { key: 'cash', label: '3. Arus Kas' },
    { key: 'equity', label: '4. Perubahan Ekuitas' },
    { key: 'calk', label: '5. CALK' },
];
</script>

<template>
    <Head :title="`Paket 5 Laporan Keuangan - ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Paket Laporan Keuangan Konsolidasi</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        5 Laporan Keuangan Standar Provinsi {{ province_name }} ({{ pack.period?.period_label }}).
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/province/reports/pdf"
                />
            </div>

            <!-- Tab Buttons -->
            <div class="border-b border-outline-variant pb-3">
                <AppTabs
                    v-model="activeTab"
                    :items="tabs"
                    variant="underline"
                    aria-label="Tab paket laporan konsolidasi"
                />
            </div>

            <!-- Content Tab 1: Neraca -->
            <AppCard v-if="activeTab === 'balance'" :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">1. NERACA KONSOLIDASI PROVINSI</h2>
                </div>
                <div class="p-6">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Nama Akun</th>
                                <th class="px-4 py-3 text-right">Saldo (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">ASET</td></tr>
                            <tr v-for="row in pack.balance_sheet.assets.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container">
                                <td colspan="2" class="px-4 py-3 text-right">TOTAL ASET</td>
                                <td class="px-4 py-3 text-right">{{ money(pack.balance_sheet.assets.total) }}</td>
                            </tr>

                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2 text-secondary">KEWAJIBAN & EKUITAS</td></tr>
                            <tr v-for="row in pack.balance_sheet.liabilities.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low">
                                <td colspan="2" class="px-4 py-2 text-right">TOTAL KEWAJIBAN</td>
                                <td class="px-4 py-2 text-right">{{ money(pack.balance_sheet.liabilities.total) }}</td>
                            </tr>

                            <tr v-for="row in pack.balance_sheet.equity.rows" :key="row.code" class="hover:bg-surface-container-low/50">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2" :class="row.level === 1 ? 'font-bold' : ''">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right" :class="row.level === 1 ? 'font-bold' : ''">{{ money(row.balance) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low">
                                <td colspan="2" class="px-4 py-2 text-right">TOTAL EKUITAS</td>
                                <td class="px-4 py-2 text-right">{{ money(pack.balance_sheet.equity.total) }}</td>
                            </tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container">
                                <td colspan="2" class="px-4 py-3 text-right">TOTAL KEWAJIBAN & EKUITAS</td>
                                <td class="px-4 py-3 text-right">{{ money(pack.balance_sheet.total_liabilities_and_equity) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Content Tab 2: Laba Rugi -->
            <AppCard v-if="activeTab === 'income'" :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">2. LABA RUGI KONSOLIDASI PROVINSI</h2>
                </div>
                <div class="p-6">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Kode</th>
                                <th class="px-4 py-3">Uraian</th>
                                <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2">PENDAPATAN OPERASIONAL</td></tr>
                            <tr v-for="row in pack.income_statement.revenue_ops.rows" :key="row.code">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right">{{ money(row.amount) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low"><td colspan="2" class="px-4 py-2 text-right">SUBTOTAL PENDAPATAN OPS</td><td class="px-4 py-2 text-right">{{ money(pack.income_statement.revenue_ops.total) }}</td></tr>

                            <tr class="font-bold bg-surface-container-lowest"><td colspan="3" class="px-4 py-2">BEBAN OPERASIONAL</td></tr>
                            <tr v-for="row in pack.income_statement.expense_ops.rows" :key="row.code">
                                <td class="px-4 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-4 py-2">{{ row.name }}</td>
                                <td class="px-4 py-2 text-right">{{ money(row.amount) }}</td>
                            </tr>
                            <tr class="font-bold bg-surface-container-low"><td colspan="2" class="px-4 py-2 text-right">SUBTOTAL BEBAN OPS</td><td class="px-4 py-2 text-right">{{ money(pack.income_statement.expense_ops.total) }}</td></tr>

                            <tr class="font-bold bg-secondary-container text-on-secondary-container"><td colspan="2" class="px-4 py-3 text-right">LABA OPERASIONAL</td><td class="px-4 py-3 text-right">{{ money(pack.income_statement.summary.operating_profit.ytd) }}</td></tr>
                            <tr class="font-bold bg-secondary text-on-secondary text-base"><td colspan="2" class="px-4 py-3 text-right">LABA BERSIH (NET PROFIT)</td><td class="px-4 py-3 text-right">{{ money(pack.income_statement.summary.after_tax.ytd) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Content Tab 3: Arus Kas -->
            <AppCard v-if="activeTab === 'cash'" :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">3. LAPORAN ARUS KAS KONSOLIDASI</h2>
                </div>
                <div class="p-6">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Aktivitas</th>
                                <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Operasional</td><td class="px-4 py-3 text-right">{{ money(pack.cash_flow.operating_activities) }}</td></tr>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Investasi</td><td class="px-4 py-3 text-right">{{ money(pack.cash_flow.investing_activities) }}</td></tr>
                            <tr><td class="px-4 py-3">Arus Kas Aktivitas Pendanaan</td><td class="px-4 py-3 text-right">{{ money(pack.cash_flow.financing_activities) }}</td></tr>
                            <tr class="font-bold bg-surface-container-low"><td class="px-4 py-3">Kenaikan / (Penurunan) Kas Bersih</td><td class="px-4 py-3 text-right">{{ money(pack.cash_flow.net_cash_change) }}</td></tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container"><td class="px-4 py-3">SALDO KAS & BANK AKHIR PERIODE</td><td class="px-4 py-3 text-right">{{ money(pack.cash_flow.ending_cash) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Content Tab 4: Perubahan Ekuitas -->
            <AppCard v-if="activeTab === 'equity'" :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary text-lg">4. LAPORAN PERUBAHAN EKUITAS</h2>
                </div>
                <div class="p-6">
                    <table class="w-full text-left text-sm divide-y divide-outline-variant">
                        <thead>
                            <tr class="bg-surface-container-low text-xs font-bold uppercase">
                                <th class="px-4 py-3">Komponen Ekuitas</th>
                                <th class="px-4 py-3 text-right">Jumlah (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td class="px-4 py-3">Ekuitas Awal Periode</td><td class="px-4 py-3 text-right">{{ money(pack.equity_changes.opening_equity) }}</td></tr>
                            <tr><td class="px-4 py-3">Laba Bersih Berjalan</td><td class="px-4 py-3 text-right">{{ money(pack.equity_changes.net_income) }}</td></tr>
                            <tr class="font-bold bg-secondary-container text-on-secondary-container"><td class="px-4 py-3">TOTAL EKUITAS AKHIR PERIODE</td><td class="px-4 py-3 text-right">{{ money(pack.equity_changes.ending_equity) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>

            <!-- Content Tab 5: CALK -->
            <AppCard v-if="activeTab === 'calk'">
                <div class="border-b border-outline-variant pb-4 mb-4">
                    <h2 class="font-bold text-primary text-lg">5. CATATAN ATAS LAPORAN KEUANGAN (CALK)</h2>
                </div>
                <div class="space-y-4 text-sm text-on-surface">
                    <div>
                        <h3 class="font-bold text-primary">1. Gambaran Umum</h3>
                        <p class="mt-1 text-on-surface-variant">{{ pack.calk.general_notes }}</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-primary">2. Kebijakan Akuntansi</h3>
                        <p class="mt-1 text-on-surface-variant">{{ pack.calk.accounting_policies }}</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-primary">3. Cakupan Konsolidasi</h3>
                        <p class="mt-1 text-on-surface-variant">Laporan ini mencakup {{ pack.calk.tenants_count }} Unit Pengelola Kegiatan se-Provinsi.</p>
                    </div>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
