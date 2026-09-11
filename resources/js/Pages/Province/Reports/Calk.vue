<script setup>
import { Head } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import ProvinceLayout from '../../../Layouts/ProvinceLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    province_name: { type: String, default: 'Provinsi' },
});

const baseUrl = '/province/reports/calk';
</script>

<template>
    <Head :title="`CALK - Provinsi ${province_name}`" />
    <ProvinceLayout>
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Catatan Atas Laporan Keuangan (CALK)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Penjelasan dan Catatan Konsolidasi Keuangan Provinsi {{ province_name }} ({{ report.period?.period_label }}).
                    </p>
                </div>

                <ReportPeriodFilter
                    :year="year"
                    :month="month"
                    :base-url="baseUrl"
                    pdf-url="/province/reports/pdf"
                />
            </div>

            <AppCard>
                <div class="border-b border-outline-variant pb-4 mb-4">
                    <h2 class="font-bold text-primary text-lg">CATATAN ATAS LAPORAN KEUANGAN (CALK)</h2>
                </div>
                <div class="space-y-4 text-sm text-on-surface">
                    <div>
                        <h3 class="font-bold text-primary">1. Gambaran Umum Entitas</h3>
                        <p class="mt-1 text-on-surface-variant">{{ report.general_notes }}</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-primary">2. Kebijakan Akuntansi</h3>
                        <p class="mt-1 text-on-surface-variant">{{ report.accounting_policies }}</p>
                    </div>
                    <div>
                        <h3 class="font-bold text-primary">3. Cakupan Konsolidasi</h3>
                        <p class="mt-1 text-on-surface-variant">Laporan ini mencakup {{ report.tenants_count }} Unit Pengelola Kegiatan se-Provinsi.</p>
                    </div>
                </div>
            </AppCard>
        </div>
    </ProvinceLayout>
</template>
