<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
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
const baseUrl = '/regency/reports/equity-changes';

const tenantOptions = computed(() => [
    { value: '', label: t('Semua Kecamatan (Gabungan)') },
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
    <Head :title="`Perubahan Modal - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ t('Laporan Perubahan Modal Kabupaten') }} {{ regency_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ report.period?.period_label }} · {{ report.is_consolidated ? t('Gabungan Seluruh Kecamatan') : t('Kecamatan Terpilih') }}
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/regency/reports/equity-changes/pdf"
                    :extra="{ tenant_id: selectedTenant || '' }"
                >
                    <template #extra>
                        <SmartSelect
                            v-model="selectedTenant"
                            :options="tenantOptions"
                            :label="t('Kecamatan')"
                            value-key="value"
                            label-key="label"
                            hide-label
                        />
                    </template>
                </ReportPeriodFilter>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">{{ t('Total Modal / Ekuitas') }}</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.summary?.total_equity) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">{{ t('Jumlah Rekening') }}</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ report.rows?.length || 0 }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">{{ t('Cakupan') }}</span>
                    <p class="mt-2 text-lg font-bold text-primary">
                        {{ report.is_consolidated ? 'Konsolidasi' : (report.kecamatans?.[0]?.name || 'Kecamatan') }}
                    </p>
                </AppCard>
            </div>

            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-6 py-3 w-16">{{ t('No') }}</th>
                                <th class="px-6 py-3 w-40">{{ t('Kode') }}</th>
                                <th class="px-6 py-3">{{ t('Rekening Modal') }}</th>
                                <th v-if="report.is_consolidated && report.kecamatans.length > 1" v-for="kec in report.kecamatans" :key="kec.id" class="px-4 py-3 text-right">
                                    {{ kec.name }}
                                </th>
                                <th class="px-6 py-3 text-right">{{ t('Total (Rp)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="(row, index) in report.rows" :key="row.code" class="hover:bg-surface-container-low/40">
                                <td class="px-6 py-2.5 text-on-surface-variant">{{ index + 1 }}</td>
                                <td class="px-6 py-2.5 font-mono text-xs text-on-surface-variant">{{ row.code }}</td>
                                <td class="px-6 py-2.5 text-on-surface">{{ row.name }}</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" v-for="kec in report.kecamatans" :key="kec.id" class="px-4 py-2.5 text-right text-xs text-on-surface-variant">
                                    {{ row.tenants[kec.id] ? money(row.tenants[kec.id]) : '-' }}
                                </td>
                                <td class="px-6 py-2.5 text-right font-medium text-primary">{{ money(row.total) }}</td>
                            </tr>
                            <tr v-if="!report.rows?.length">
                                <td :colspan="report.is_consolidated && report.kecamatans.length > 1 ? report.kecamatans.length + 4 : 4" class="px-6 py-8 text-center text-sm text-on-surface-variant">
                                    {{ t('Belum ada rekening modal pada periode ini.') }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold text-primary">
                            <tr>
                                <td class="px-6 py-3" colspan="3">TOTAL PERUBAHAN MODAL</td>
                                <td v-if="report.is_consolidated && report.kecamatans.length > 1" :colspan="report.kecamatans.length"></td>
                                <td class="px-6 py-3 text-right text-base">{{ money(report.summary?.total_equity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
