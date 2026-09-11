<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import ReportPeriodFilter from '../../../Components/ReportPeriodFilter.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    notes: { type: String, default: '' },
    highlights: { type: Array, required: true },
    policies: { type: Array, required: true },
    monthLabels: { type: Object, required: true },
    filters: { type: Object, required: true },
    can_edit: { type: Boolean, default: false },
});

const allowEdit = computed(() => props.can_edit && can('reports.manage'));

const form = useForm({
    notes: props.notes || '',
    year: props.filters.year,
    month: props.filters.month,
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

const pdfHref = computed(() => {
    const q = new URLSearchParams({
        year: String(props.filters.year),
        month: props.filters.month === null || props.filters.month === undefined
            ? 'all'
            : String(props.filters.month),
    });
    return `/accounting/reports/calk/pdf?${q.toString()}`;
});

function save() {
    form.year = props.filters.year;
    form.month = props.filters.month;
    form.put('/accounting/reports/calk/notes', { preserveScroll: true });
}
</script>

<template>
    <Head title="CALK" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Catatan Atas Laporan Keuangan</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ period.period_label }} · ringkasan otomatis + catatan lembaga
                    </p>
                </div>
                <a :href="pdfHref" target="_blank" rel="noopener">
                    <AppButton variant="secondary" icon="picture_as_pdf" size="compact">PDF</AppButton>
                </a>
            </div>

            <AppCard class="p-4">
                <ReportPeriodFilter
                    :year="filters.year"
                    :month="filters.month"
                    base-url="/accounting/reports/calk"
                    pdf-url="/accounting/reports/calk/pdf"
                />
            </AppCard>

            <AppCard class="p-5">
                <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Identitas entitas</h2>
                <p class="mt-2 text-lg font-bold text-primary">{{ identity.legal_name }}</p>
                <p v-if="identity.address" class="text-sm text-on-surface-variant">{{ identity.address }}</p>
                <p v-if="identity.registration_number || identity.tax_number" class="mt-1 text-xs text-on-surface-variant">
                    <span v-if="identity.registration_number">Registrasi {{ identity.registration_number }}</span>
                    <span v-if="identity.registration_number && identity.tax_number"> · </span>
                    <span v-if="identity.tax_number">NPWP {{ identity.tax_number }}</span>
                </p>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AppCard v-for="h in highlights" :key="h.key">
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ h.label }}</p>
                    <p
                        class="mt-2 text-xl font-bold"
                        :class="h.amount < 0 ? 'text-error' : 'text-primary'"
                    >
                        {{ formatMoney(h.amount) }}
                    </p>
                </AppCard>
            </div>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">Kebijakan akuntansi (ringkas)</h2>
                <ol class="list-decimal space-y-2 pl-5 text-sm text-on-surface">
                    <li v-for="(p, i) in policies" :key="i">{{ p }}</li>
                </ol>
            </AppCard>

            <AppCard class="p-5">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-on-surface-variant">Catatan tambahan</h2>
                <form v-if="allowEdit" class="space-y-3" @submit.prevent="save">
                    <AppTextarea
                        v-model="form.notes"
                        label="Catatan manajemen / peristiwa penting"
                        :error="form.errors.notes"
                        placeholder="Contoh: penyesuaian piutang, pergantian pengurus, penjaminan, kontinjensi…"
                    />
                    <div class="flex justify-end">
                        <AppButton type="submit" icon="save" :loading="form.processing">Simpan catatan</AppButton>
                    </div>
                </form>
                <div v-else class="whitespace-pre-wrap text-sm text-on-surface">
                    {{ notes || 'Belum ada catatan tambahan.' }}
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
