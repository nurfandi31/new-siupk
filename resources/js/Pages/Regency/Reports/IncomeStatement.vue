<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppCard from '../../../Components/AppCard.vue';
import RegencyLayout from '../../../Layouts/RegencyLayout.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { useMoney } from '../../../composables/useMoney';

const { money } = useMoney();

const props = defineProps({
    report: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    selected_tenant_id: { type: [Number, String], default: '' },
    regency_name: { type: String, default: 'Kabupaten' },
});

const selectedTenant = ref(props.selected_tenant_id || '');

const baseUrl = '/regency/reports/income-statement';

const tenantOptions = computed(() => [
    { value: '', label: 'Semua Kecamatan (Gabungan)' },
    ...(props.report.kecamatans || []).map(kec => ({
        value: kec.id,
        label: kec.name,
    })),
]);

watch(selectedTenant, () => {
    router.get(baseUrl, {
        year: props.year,
        month: props.month || '',
        tenant_id: selectedTenant.value || '',
    }, { preserveState: true });
});
</script>

<template>
    <Head :title="`Laba Rugi Konsolidasi - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header & Filters -->
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Laba Rugi Konsolidasi Kabupaten {{ regency_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ report.period?.period_label }} · {{ report.is_consolidated ? 'Gabungan Seluruh Kecamatan' : 'Kecamatan Terpilih' }}
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/regency/reports/income-statement/pdf"
                    :extra="{ tenant_id: selectedTenant || '' }"
                >
                    <template #extra>
                        <SmartSelect
                            v-model="selectedTenant"
                            :options="tenantOptions"
                            label="Kecamatan"
                            value-key="value"
                            label-key="label"
                            hide-label
                        />
                    </template>
                </ReportPeriodFilter>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Pendapatan Operasional (YTD)</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.revenue_ops?.ytd) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Beban Operasional (YTD)</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.expense_ops?.ytd) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Laba (Rugi) Bersih (YTD)</span>
                    <p
                        class="mt-2 text-2xl font-bold"
                        :class="(report.summary?.after_tax?.ytd || 0) >= 0 ? 'text-primary' : 'text-error'"
                    >
                        {{ money(report.summary?.after_tax?.ytd) }}
                    </p>
                </AppCard>
            </div>

            <!-- Table -->
            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3 w-32">Kode</th>
                                <th class="px-6 py-3">Nama Rekening</th>
                                <th class="px-4 py-3 text-right">s.d. {{ report.header_lalu }}</th>
                                <th class="px-4 py-3 text-right">{{ report.header_sekarang }}</th>
                                <th
                                    v-if="report.is_consolidated && report.kecamatans.length > 1"
                                    v-for="kec in report.kecamatans"
                                    :key="kec.id"
                                    class="px-4 py-3 text-right"
                                >
                                    {{ kec.name }}
                                </th>
                                <th class="px-6 py-3 text-right font-bold text-primary">s.d. {{ report.header_sekarang }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-if="!report.groups?.length">
                                <td :colspan="(report.is_consolidated && report.kecamatans.length > 1) ? report.kecamatans.length + 5 : 5" class="px-6 py-8 text-center text-on-surface-variant">
                                    Belum ada transaksi pendapatan atau beban pada periode ini.
                                </td>
                            </tr>
                            <template v-for="group in report.groups" :key="group.code">
                                <tr class="bg-surface-container-low font-bold text-primary">
                                    <td class="px-6 py-2.5 font-mono text-xs">{{ group.code }}</td>
                                    <td class="px-6 py-2.5">{{ group.name }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ money(group.prior) }}</td>
                                    <td class="px-4 py-2.5 text-right">{{ money(group.current) }}</td>
                                    <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                    <td class="px-6 py-2.5 text-right">{{ money(group.ytd) }}</td>
                                </tr>

                                <tr
                                    v-for="row in group.children"
                                    :key="row.row_id"
                                    class="hover:bg-surface-container-low/40"
                                >
                                    <td class="px-6 py-2 font-mono text-xs text-on-surface-variant">{{ row.code }}</td>
                                    <td class="px-6 py-2 pl-10 text-on-surface">{{ row.name }}</td>
                                    <td class="px-4 py-2 text-right text-xs text-on-surface-variant">{{ money(row.prior) }}</td>
                                    <td class="px-4 py-2 text-right text-xs text-on-surface-variant">{{ money(row.current) }}</td>
                                    <td
                                        v-if="report.is_consolidated && report.kecamatans.length > 1"
                                        v-for="kec in report.kecamatans"
                                        :key="kec.id"
                                        class="px-4 py-2 text-right text-xs text-on-surface-variant"
                                    >
                                        {{ row.tenants[kec.id] ? money(row.tenants[kec.id]) : '—' }}
                                    </td>
                                    <td class="px-6 py-2 text-right font-medium text-primary">{{ money(row.ytd) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold">
                            <tr class="border-t border-outline-variant">
                                <td class="px-6 py-2.5" colspan="2">Laba (Rugi) Operasional (A)</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.operating?.prior) }}</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.operating?.current) }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-2.5 text-right text-primary">{{ money(report.summary?.operating?.ytd) }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-2.5" colspan="2">Laba (Rugi) Non Operasional (B)</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.non_operating?.prior) }}</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.non_operating?.current) }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-2.5 text-right text-primary">{{ money(report.summary?.non_operating?.ytd) }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-2.5" colspan="2">Laba (Rugi) Sebelum Pajak (A+B)</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.before_tax?.prior) }}</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.before_tax?.current) }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-2.5 text-right text-primary">{{ money(report.summary?.before_tax?.ytd) }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-2.5" colspan="2">Beban Pajak Penghasilan</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.tax?.prior) }}</td>
                                <td class="px-4 py-2.5 text-right text-xs">{{ money(report.summary?.tax?.current) }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-2.5 text-right text-primary">{{ money(report.summary?.tax?.ytd) }}</td>
                            </tr>
                            <tr class="bg-primary/10 text-primary text-base">
                                <td class="px-6 py-3" colspan="2">Laba (Rugi) Bersih Setelah Pajak</td>
                                <td class="px-4 py-3 text-right text-sm">{{ money(report.summary?.after_tax?.prior) }}</td>
                                <td class="px-4 py-3 text-right text-sm">{{ money(report.summary?.after_tax?.current) }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-3 text-right font-black">{{ money(report.summary?.after_tax?.ytd) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
