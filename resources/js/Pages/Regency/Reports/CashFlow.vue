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

const baseUrl = '/regency/reports/cash-flow';

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
    <Head :title="`Arus Kas Konsolidasi - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header & Filters -->
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Arus Kas Konsolidasi Kabupaten {{ regency_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ report.period?.period_label }} · {{ report.is_consolidated ? 'Gabungan Seluruh Kecamatan' : 'Kecamatan Terpilih' }}
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/regency/reports/cash-flow/pdf"
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
                    <span class="text-sm font-semibold text-on-surface-variant">Saldo Kas Awal</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.reconciliation?.cash_opening) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Kenaikan / Penurunan Bersih</span>
                    <p
                        class="mt-2 text-2xl font-bold"
                        :class="(report.reconciliation?.net_change || 0) >= 0 ? 'text-primary' : 'text-error'"
                    >
                        {{ money(report.reconciliation?.net_change) }}
                    </p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Saldo Kas Akhir</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.reconciliation?.cash_closing) }}</p>
                </AppCard>
            </div>

            <!-- Cash Flow Table -->
            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3 w-32">Kode</th>
                                <th class="px-6 py-3">Uraian / Aktivitas</th>
                                <th class="px-6 py-3 text-right">Kas Masuk (Rp)</th>
                                <th class="px-6 py-3 text-right">Kas Keluar (Rp)</th>
                                <th class="px-6 py-3 text-right">Arus Bersih (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <!-- Operating -->
                            <tr class="bg-surface-container-low font-bold text-primary">
                                <td class="px-6 py-2.5" colspan="2">{{ report.sections?.operating?.title }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.operating?.total_in) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.operating?.total_out) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.operating?.net) }}</td>
                            </tr>
                            <tr
                                v-for="item in report.sections?.operating?.items"
                                :key="item.code"
                                class="hover:bg-surface-container-low/40"
                            >
                                <td class="px-6 py-2 font-mono text-xs text-on-surface-variant">{{ item.code }}</td>
                                <td class="px-6 py-2 pl-10 text-on-surface">{{ item.name }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_in) }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_out) }}</td>
                                <td class="px-6 py-2 text-right font-medium" :class="item.net >= 0 ? 'text-primary' : 'text-error'">
                                    {{ money(item.net) }}
                                </td>
                            </tr>

                            <!-- Investing -->
                            <tr class="bg-surface-container-low font-bold text-primary">
                                <td class="px-6 py-2.5" colspan="2">{{ report.sections?.investing?.title }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.investing?.total_in) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.investing?.total_out) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.investing?.net) }}</td>
                            </tr>
                            <tr
                                v-for="item in report.sections?.investing?.items"
                                :key="item.code"
                                class="hover:bg-surface-container-low/40"
                            >
                                <td class="px-6 py-2 font-mono text-xs text-on-surface-variant">{{ item.code }}</td>
                                <td class="px-6 py-2 pl-10 text-on-surface">{{ item.name }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_in) }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_out) }}</td>
                                <td class="px-6 py-2 text-right font-medium" :class="item.net >= 0 ? 'text-primary' : 'text-error'">
                                    {{ money(item.net) }}
                                </td>
                            </tr>

                            <!-- Financing -->
                            <tr class="bg-surface-container-low font-bold text-primary">
                                <td class="px-6 py-2.5" colspan="2">{{ report.sections?.financing?.title }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.financing?.total_in) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.financing?.total_out) }}</td>
                                <td class="px-6 py-2.5 text-right">{{ money(report.sections?.financing?.net) }}</td>
                            </tr>
                            <tr
                                v-for="item in report.sections?.financing?.items"
                                :key="item.code"
                                class="hover:bg-surface-container-low/40"
                            >
                                <td class="px-6 py-2 font-mono text-xs text-on-surface-variant">{{ item.code }}</td>
                                <td class="px-6 py-2 pl-10 text-on-surface">{{ item.name }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_in) }}</td>
                                <td class="px-6 py-2 text-right text-xs text-on-surface-variant">{{ money(item.cash_out) }}</td>
                                <td class="px-6 py-2 text-right font-medium" :class="item.net >= 0 ? 'text-primary' : 'text-error'">
                                    {{ money(item.net) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold">
                            <tr class="border-t border-outline-variant">
                                <td class="px-6 py-2.5" colspan="4">Saldo Kas Awal Periode</td>
                                <td class="px-6 py-2.5 text-right font-bold text-primary">{{ money(report.reconciliation?.cash_opening) }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-2.5" colspan="4">Kenaikan (Penurunan) Kas Bersih</td>
                                <td class="px-6 py-2.5 text-right font-bold" :class="(report.reconciliation?.net_change || 0) >= 0 ? 'text-primary' : 'text-error'">
                                    {{ money(report.reconciliation?.net_change) }}
                                </td>
                            </tr>
                            <tr class="bg-primary/10 text-primary text-base">
                                <td class="px-6 py-3" colspan="4">Saldo Kas Akhir Periode</td>
                                <td class="px-6 py-3 text-right font-black">{{ money(report.reconciliation?.cash_closing) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
