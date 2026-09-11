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

const baseUrl = '/regency/reports/balance-sheet';

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
    <Head :title="`Neraca Konsolidasi - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header & Filter -->
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Neraca Konsolidasi Kabupaten {{ regency_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ report.period?.period_label }} · {{ report.is_consolidated ? 'Gabungan Seluruh Kecamatan' : 'Kecamatan Terpilih' }}
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/regency/reports/balance-sheet/pdf"
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
                    <span class="text-sm font-semibold text-on-surface-variant">Total Aktiva</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.total_assets) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Kewajiban</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.total_liabilities) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Ekuitas</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.total_equity) }}</p>
                </AppCard>
            </div>

            <!-- Balance Sheet -->
            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3 w-32">Kode</th>
                                <th class="px-6 py-3">Nama Akun</th>
                                <th
                                    v-if="report.is_consolidated && report.kecamatans.length > 1"
                                    v-for="kec in report.kecamatans"
                                    :key="kec.id"
                                    class="px-4 py-3 text-right"
                                >
                                    {{ kec.name }}
                                </th>
                                <th class="px-6 py-3 text-right">Total (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <template v-for="group in report.groups" :key="group.code">
                                <tr class="bg-surface-container-low font-bold text-primary">
                                    <td class="px-6 py-3 font-mono text-xs">{{ group.code }}</td>
                                    <td class="px-6 py-3" :colspan="(report.is_consolidated && report.kecamatans.length > 1) ? report.kecamatans.length + 1 : 1">
                                        {{ group.name }}
                                    </td>
                                    <td class="px-6 py-3 text-right">{{ money(group.total) }}</td>
                                </tr>

                                <template v-for="sub in group.subgroups" :key="sub.code">
                                    <tr class="bg-surface font-semibold text-on-surface">
                                        <td class="px-6 py-2.5 font-mono text-xs text-on-surface-variant">{{ sub.code }}</td>
                                        <td class="px-6 py-2.5 pl-10" :colspan="(report.is_consolidated && report.kecamatans.length > 1) ? report.kecamatans.length + 1 : 1">
                                            {{ sub.name }}
                                        </td>
                                        <td class="px-6 py-2.5 text-right font-semibold">{{ money(sub.total) }}</td>
                                    </tr>

                                    <tr
                                        v-for="row in sub.rows"
                                        :key="row.row_id"
                                        class="hover:bg-surface-container-low/40"
                                    >
                                        <td class="px-6 py-2 font-mono text-xs text-on-surface-variant">{{ row.code }}</td>
                                        <td class="px-6 py-2 pl-14 text-on-surface">{{ row.name }}</td>
                                        <td
                                            v-if="report.is_consolidated && report.kecamatans.length > 1"
                                            v-for="kec in report.kecamatans"
                                            :key="kec.id"
                                            class="px-4 py-2 text-right text-xs text-on-surface-variant"
                                        >
                                            {{ row.tenants[kec.id] ? money(row.tenants[kec.id]) : '—' }}
                                        </td>
                                        <td class="px-6 py-2 text-right font-medium text-primary">{{ money(row.total) }}</td>
                                    </tr>
                                </template>
                            </template>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold text-primary">
                            <tr>
                                <td class="px-6 py-3" colspan="2">TOTAL AKTIVA</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-3 text-right text-base">{{ money(report.summary?.total_assets) }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3" colspan="2">TOTAL KEWAJIBAN & EKUITAS</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-3 text-right text-base">{{ money(report.summary?.total_liabilities_and_equity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
