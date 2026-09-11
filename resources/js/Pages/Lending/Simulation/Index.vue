<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { useMoney } from '../../../composables/useMoney';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    defaultSimulation: { type: Object, required: true },
    frequencyOptions: { type: Array, required: true },
    methodOptions: { type: Array, required: true },
    roundingOptions: { type: Array, required: true },
});

const { money } = useMoney();

const form = reactive({
    selectedProduct: '',
    borrower_name: '',
    principal_amount: Number(props.defaultSimulation.parameters.principal_amount || 10000000),
    term_months: Number(props.defaultSimulation.parameters.term_months || 12),
    interest_rate: Number(props.defaultSimulation.parameters.interest_rate || 1.5),
    rate_unit: props.defaultSimulation.parameters.rate_unit || 'monthly',
    installment_method: props.defaultSimulation.parameters.installment_method || 'flat',
    principal_frequency: props.defaultSimulation.parameters.principal_frequency || 'monthly',
    interest_frequency: props.defaultSimulation.parameters.interest_frequency || 'monthly',
    principal_grace_months: props.defaultSimulation.parameters.principal_grace_months || 0,
    interest_grace_months: props.defaultSimulation.parameters.interest_grace_months || 0,
    rounding_step: props.defaultSimulation.parameters.rounding_step !== undefined ? Number(props.defaultSimulation.parameters.rounding_step) : 500,
    start_date: props.defaultSimulation.parameters.start_date || new Date().toISOString().slice(0, 10),
});

const copied = ref(false);

const productOptions = computed(() => [
    { value: '', label: 'Kustom / Input Manual' },
    ...props.products.map((p) => ({
        value: p.code,
        label: `${p.name} (${p.code})`,
        data: p,
    })),
]);

function onProductChange(code) {
    if (!code) return;
    const prod = props.products.find((p) => p.code === code);
    if (!prod) return;

    if (prod.interest_rate) {
        form.interest_rate = Number(prod.interest_rate);
        form.rate_unit = 'monthly';
    }
    if (prod.term_months) form.term_months = Number(prod.term_months);
    if (prod.rounding_step !== undefined) form.rounding_step = Number(prod.rounding_step);
    if (prod.min_amount && form.principal_amount < prod.min_amount) {
        form.principal_amount = prod.min_amount;
    }
}

function setRateUnit(unit) {
    if (form.rate_unit === unit) return;
    const currentRate = Number(form.interest_rate || 0);
    if (unit === 'annual') {
        form.interest_rate = Number((currentRate * 12).toFixed(2));
    } else {
        form.interest_rate = Number((currentRate / 12).toFixed(2));
    }
    form.rate_unit = unit;
}

const FREQUENCY_MONTHS = {
    monthly: 1,
    quarterly: 3,
    semi_annually: 6,
    annually: 12,
    every_4_months: 4,
    every_5_months: 5,
    every_6_months: 6,
    every_7_months: 7,
    every_8_months: 8,
    every_9_months: 9,
    every_10_months: 10,
    every_11_months: 11,
    every_12_months: 12,
    every_24_months: 24,
    every_36_months: 36,
    at_maturity: 0,
};

const graceOptions = [
    { value: 0, label: 'Tanpa Penundaan' },
    { value: 1, label: 'M1 — Angsuran ditunda 1 bulan' },
    { value: 2, label: 'M2 — Pokok ditunda 2 bulan' },
    { value: 3, label: 'M3 — Pokok ditunda 3 bulan' },
    { value: 6, label: 'M6 — Pokok ditunda 6 bulan' },
    { value: 12, label: 'M12 — Pokok ditunda 12 bulan' },
    { value: 24, label: 'M24 — Pokok ditunda 24 bulan' },
];

function roundVal(amount, step) {
    const s = parseInt(step, 10) || 0;
    if (s <= 1) {
        return Math.round(amount * 100) / 100;
    }
    return Math.round(amount / s) * s;
}

function advanceDate(baseDateStr, months) {
    if (!baseDateStr) return '';
    const parts = String(baseDateStr).split('-');
    if (parts.length !== 3) return baseDateStr;
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);

    const targetDate = new Date(year, month + months, 1);
    const lastDay = new Date(targetDate.getFullYear(), targetDate.getMonth() + 1, 0).getDate();
    const finalDay = Math.min(day, lastDay);

    const resDate = new Date(targetDate.getFullYear(), targetDate.getMonth(), finalDay);
    const y = resDate.getFullYear();
    const m = String(resDate.getMonth() + 1).padStart(2, '0');
    const d = String(resDate.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

// Client-side real-time calculation engine
const simulationResult = computed(() => {
    const principal = Math.max(0, Number(form.principal_amount || 0));
    const termMonths = Math.max(1, parseInt(form.term_months || 12, 10));
    const rawRate = Math.max(0, Number(form.interest_rate || 0));
    const rateUnit = form.rate_unit || 'monthly';
    const method = form.installment_method || 'flat';
    const principalFreq = form.principal_frequency || 'monthly';
    const interestFreq = form.interest_frequency || 'monthly';
    const principalGrace = Math.max(0, parseInt(form.principal_grace_months || 0, 10));
    const interestGrace = Math.max(0, parseInt(form.interest_grace_months || 0, 10));
    const roundingStep = Math.max(0, parseInt(form.rounding_step ?? 500, 10));
    const startDate = form.start_date || new Date().toISOString().slice(0, 10);

    // Compute standardized monthly & annual rates
    let rateMonthly = 0;
    let rateAnnual = 0;
    if (rateUnit === 'monthly') {
        rateMonthly = rawRate;
        rateAnnual = rawRate * 12;
    } else {
        rateAnnual = rawRate;
        rateMonthly = rawRate / 12;
    }

    let schedule = [];

    if (method === 'annuity') {
        const pStep = FREQUENCY_MONTHS[principalFreq] ?? 1;
        const periods = principalFreq === 'at_maturity' ? 1 : Math.max(1, Math.floor((termMonths - principalGrace - pStep) / pStep) + 1);
        const monthsPerPeriod = periods > 0 ? Math.round(termMonths / periods) : 1;
        const periodicRate = (rateMonthly / 100) * monthsPerPeriod;

        const rawPmt = periodicRate > 0
            ? principal * (periodicRate * Math.pow(1 + periodicRate, periods)) / (Math.pow(1 + periodicRate, periods) - 1)
            : principal / periods;
        const pmt = roundVal(rawPmt, roundingStep);

        let remaining = principal;
        for (let i = 1; i <= periods; i++) {
            const rawInterest = remaining * periodicRate;
            const iDue = roundVal(rawInterest, roundingStep);
            let pDue = 0;

            if (i === periods) {
                pDue = remaining;
                remaining = 0;
            } else {
                pDue = Math.max(0, Math.round((pmt - iDue) * 100) / 100);
                if (pDue > remaining) {
                    pDue = remaining;
                }
                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
            }

            const m = principalFreq === 'at_maturity' ? termMonths : (i + principalGrace) * pStep;

            schedule.push({
                number: i,
                due_date: advanceDate(startDate, m),
                principal_due: pDue,
                interest_due: iDue,
                total_due: Math.round((pDue + iDue) * 100) / 100,
                remaining_principal: remaining,
            });
        }
    } else if (method === 'declining') {
        const pStep = FREQUENCY_MONTHS[principalFreq] ?? 1;
        const pPeriods = principalFreq === 'at_maturity' ? 1 : Math.max(1, Math.floor((termMonths - principalGrace - pStep) / pStep) + 1);
        const rawP = pPeriods > 0 ? principal / pPeriods : principal;
        const roundedP = roundVal(rawP, roundingStep);
        const monthsPerPeriod = pPeriods > 0 ? Math.round(termMonths / pPeriods) : 1;
        const periodicRate = (rateMonthly / 100) * monthsPerPeriod;

        let accumulatedP = 0;
        let remaining = principal;

        for (let i = 1; i <= pPeriods; i++) {
            const pDue = (i === pPeriods)
                ? Math.round((principal - accumulatedP) * 100) / 100
                : Math.min(remaining, roundedP);
            accumulatedP += pDue;

            const rawInterest = remaining * periodicRate;
            const iDue = roundVal(rawInterest, roundingStep);
            remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
            const m = principalFreq === 'at_maturity' ? termMonths : (i + principalGrace) * pStep;

            schedule.push({
                number: i,
                due_date: advanceDate(startDate, m),
                principal_due: pDue,
                interest_due: iDue,
                total_due: Math.round((pDue + iDue) * 100) / 100,
                remaining_principal: remaining,
            });
        }
    } else {
        // Flat calculation
        const pStep = FREQUENCY_MONTHS[principalFreq] ?? 1;
        const iStep = FREQUENCY_MONTHS[interestFreq] ?? 1;
        const pPeriods = principalFreq === 'at_maturity' ? 1 : Math.max(1, Math.floor((termMonths - principalGrace - pStep) / pStep) + 1);
        const iPeriods = interestFreq === 'at_maturity' ? 1 : Math.max(1, Math.floor((termMonths - interestGrace - iStep) / iStep) + 1);
        const totalInterest = principal * (rateMonthly / 100) * termMonths;

        const roundedP = roundVal(pPeriods > 0 ? principal / pPeriods : principal, roundingStep);
        const roundedI = roundVal(iPeriods > 0 ? totalInterest / iPeriods : totalInterest, roundingStep);

        if (principalFreq === interestFreq) {
            let accP = 0;
            let accI = 0;
            let remaining = principal;

            for (let i = 1; i <= pPeriods; i++) {
                const pDue = (i === pPeriods)
                    ? Math.round((principal - accP) * 100) / 100
                    : roundedP;
                accP += pDue;

                const iDue = (i === pPeriods)
                    ? Math.round((totalInterest - accI) * 100) / 100
                    : roundedI;
                accI += iDue;

                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
                const m = principalFreq === 'at_maturity' ? termMonths : (i + principalGrace) * pStep;

                schedule.push({
                    number: i,
                    due_date: advanceDate(startDate, m),
                    principal_due: pDue,
                    interest_due: iDue,
                    total_due: Math.round((pDue + iDue) * 100) / 100,
                    remaining_principal: remaining,
                });
            }
        } else {
            let accP = 0;
            let accI = 0;
            let remaining = principal;

            const pMap = {};
            for (let p = 1; p <= pPeriods; p++) {
                const m = principalFreq === 'at_maturity' ? termMonths : (p + principalGrace) * pStep;
                const pDue = (p === pPeriods) ? Math.round((principal - accP) * 100) / 100 : roundedP;
                accP += pDue;
                pMap[m] = pDue;
            }

            const iMap = {};
            for (let it = 1; it <= iPeriods; it++) {
                const m = interestFreq === 'at_maturity' ? termMonths : (it + interestGrace) * iStep;
                const iDue = (it === iPeriods) ? Math.round((totalInterest - accI) * 100) / 100 : roundedI;
                accI += iDue;
                iMap[m] = iDue;
            }

            for (let m = 1; m <= termMonths; m++) {
                const pDue = pMap[m] || 0;
                const iDue = iMap[m] || 0;
                if (pDue <= 0 && iDue <= 0) continue;

                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);

                schedule.push({
                    number: schedule.length + 1,
                    due_date: advanceDate(startDate, m),
                    principal_due: pDue,
                    interest_due: iDue,
                    total_due: Math.round((pDue + iDue) * 100) / 100,
                    remaining_principal: remaining,
                });
            }
        }
    }

    const totalInterest = schedule.reduce((sum, r) => sum + r.interest_due, 0);
    const totalPrincipal = schedule.reduce((sum, r) => sum + r.principal_due, 0);
    const totalPayment = totalPrincipal + totalInterest;
    const estimatedMonthly = termMonths > 0 ? Math.round((totalPayment / termMonths) * 100) / 100 : 0;

    const firstDue = schedule[0]?.total_due || 0;
    const lastDue = schedule[schedule.length - 1]?.total_due || 0;

    return {
        summary: {
            principal_amount: principal,
            total_interest: totalInterest,
            total_payment: totalPayment,
            estimated_monthly: estimatedMonthly,
            first_due: firstDue,
            last_due: lastDue,
            term_months: termMonths,
            interest_rate: rawRate,
            rate_unit: rateUnit,
            interest_rate_monthly: rateMonthly,
            interest_rate_annual: rateAnnual,
            method,
            rounding_step: roundingStep,
            interest_ratio: principal > 0 ? (totalInterest / principal) * 100 : 0,
        },
        schedule,
    };
});

function openPdf() {
    const params = new URLSearchParams({
        principal_amount: form.principal_amount,
        term_months: form.term_months,
        interest_rate: form.interest_rate,
        rate_unit: form.rate_unit,
        installment_method: form.installment_method,
        principal_frequency: form.principal_frequency,
        interest_frequency: form.interest_frequency,
        principal_grace_months: form.principal_grace_months,
        interest_grace_months: form.interest_grace_months,
        rounding_step: form.rounding_step,
        start_date: form.start_date,
        borrower_name: form.borrower_name || 'Calon Peminjam',
        download: '1',
    });

    window.open(`/lending/simulation/pdf?${params.toString()}`, '_blank');
}

function resetForm() {
    form.selectedProduct = '';
    form.borrower_name = '';
    form.principal_amount = 10000000;
    form.term_months = 12;
    form.interest_rate = 1.5;
    form.rate_unit = 'monthly';
    form.installment_method = 'flat';
    form.principal_frequency = 'monthly';
    form.interest_frequency = 'monthly';
    form.principal_grace_months = 0;
    form.interest_grace_months = 0;
    form.rounding_step = 500;
    form.start_date = new Date().toISOString().slice(0, 10);
}

function copySummary() {
    const s = simulationResult.value.summary;
    const methodName = form.installment_method === 'flat' ? 'Flat / Tetap' : form.installment_method === 'declining' ? 'Efektif Menurun' : 'Anuitas';
    const text = `*SIMULASI PINJAMAN*
Peminjam: ${form.borrower_name || 'Calon Peminjam'}
Plafon: ${money(s.principal_amount)}
Tenor: ${s.term_months} Bulan
Sistem Bunga: ${methodName}
Suku Bunga: ${s.interest_rate_monthly.toFixed(2)}% / bulan (${s.interest_rate_annual.toFixed(2)}% / tahun)
Total Jasa/Bunga: ${money(s.total_interest)}
Total Pengembalian: ${money(s.total_payment)}
Est. Angsuran/Bln: ${money(s.estimated_monthly)}`;

    navigator.clipboard.writeText(text).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}
</script>
<template>
    <Head title="Simulasi Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header Section -->
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center rounded-2xl bg-surface-container-low p-5 border border-outline-variant shadow-xs">
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-9 place-items-center rounded-xl bg-primary text-on-primary shadow-xs">
                            <AppIcon name="calculate" class="text-xl" />
                        </span>
                        <h1 class="text-2xl font-bold text-on-surface sm:text-3xl">Simulasi Pinjaman</h1>
                    </div>
                    <p class="mt-1.5 text-sm text-on-surface-variant max-w-2xl">
                        Kalkulator simulasi perhitungan skema angsuran pokok dan jasa pinjaman secara instan dan presisi.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <AppButton
                        icon="restart_alt"
                        variant="secondary"
                        size="sm"
                        @click="resetForm"
                    >
                        Reset
                    </AppButton>
                    <AppButton
                        :icon="copied ? 'check' : 'content_copy'"
                        variant="secondary"
                        size="sm"
                        @click="copySummary"
                    >
                        {{ copied ? 'Tersalin!' : 'Salin Ringkasan' }}
                    </AppButton>
                    <AppButton
                        icon="picture_as_pdf"
                        variant="primary"
                        size="sm"
                        @click="openPdf"
                    >
                        Unduh PDF
                    </AppButton>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">
                <!-- Left Column: Input Form (5 cols on lg) -->
                <div class="lg:col-span-5 space-y-5">
                    <!-- Section 1: Template & Peminjam -->
                    <AppCard title="Template & Identitas" icon="badge" class="shadow-xs">
                        <div class="space-y-4">
                            <SmartSelect
                                v-model="form.selectedProduct"
                                label="Template Produk Pinjaman"
                                :options="productOptions"
                                @update:model-value="onProductChange"
                            />

                            <AppInput
                                v-model="form.borrower_name"
                                label="Nama Calon Peminjam / Kelompok"
                                placeholder="Contoh: Kelompok Mawar 01 / Ibu Siti"
                                icon="person"
                            />
                        </div>
                    </AppCard>

                    <!-- Section 2: Plafon & Tenor -->
                    <AppCard title="Plafon & Jangka Waktu" icon="payments" class="shadow-xs">
                        <div class="space-y-4">
                            <!-- Plafon Pinjaman -->
                            <div>
                                <AppCurrencyInput
                                    v-model="form.principal_amount"
                                    label="Plafon Pinjaman (Rp)"
                                    icon="payments"
                                    :step="500000"
                                    required
                                />
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button
                                        v-for="amt in [5000000, 10000000, 15000000, 20000000, 25000000, 50000000]"
                                        :key="amt"
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-medium transition-colors"
                                        :class="form.principal_amount == amt ? 'bg-primary text-on-primary font-semibold' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest'"
                                        @click="form.principal_amount = amt"
                                    >
                                        {{ amt >= 1000000 ? (amt / 1000000) + ' Jt' : money(amt) }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <SmartSelect
                                    v-model="form.principal_grace_months"
                                    label="Grace Period Pokok"
                                    :options="graceOptions"
                                />
                                <SmartSelect
                                    v-model="form.interest_grace_months"
                                    label="Grace Period Jasa"
                                    :options="graceOptions"
                                />
                            </div>

                            <!-- Tenor / Jangka Waktu -->
                            <div>
                                <AppInput
                                    v-model="form.term_months"
                                    label="Jangka Waktu / Tenor (Bulan)"
                                    type="number"
                                    min="1"
                                    max="120"
                                    icon="calendar_month"
                                    required
                                />
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <button
                                        v-for="t in [6, 10, 12, 18, 24, 36]"
                                        :key="t"
                                        type="button"
                                        class="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                        :class="form.term_months == t ? 'bg-primary text-on-primary font-semibold' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest'"
                                        @click="form.term_months = t"
                                    >
                                        {{ t }} Bulan
                                    </button>
                                </div>
                            </div>

                            <!-- Tanggal Mulai -->
                            <AppDatePicker
                                v-model="form.start_date"
                                label="Tanggal Mulai / Pencairan"
                            />
                        </div>
                    </AppCard>

                    <!-- Section 3: Skema Jasa & Pembulatan -->
                    <AppCard title="Skema Bunga & Pembulatan" icon="tune" class="shadow-xs">
                        <div class="space-y-4">
                            <!-- Sistem Bunga (Segmented Card Selection) -->
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                                    Sistem Perhitungan Bunga
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        v-for="m in methodOptions"
                                        :key="m.value"
                                        type="button"
                                        class="flex flex-col items-center justify-center rounded-xl p-2.5 text-center transition-all border"
                                        :class="form.installment_method === m.value
                                            ? 'border-primary bg-primary/10 text-primary font-bold shadow-xs'
                                            : 'border-outline-variant bg-surface-container-lowest text-on-surface-variant hover:border-outline hover:text-on-surface'"
                                        @click="form.installment_method = m.value"
                                    >
                                        <AppIcon
                                            :name="m.value === 'flat' ? 'horizontal_rule' : m.value === 'declining' ? 'trending_down' : 'balance'"
                                            class="mb-1 text-lg"
                                        />
                                        <span class="text-xs font-semibold leading-tight">{{ m.label }}</span>
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-on-surface-variant italic">
                                    {{ methodOptions.find((m) => m.value === form.installment_method)?.description }}
                                </p>
                            </div>

                            <!-- Suku Bunga & Rate Unit Switcher -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                                        Suku Bunga / Jasa
                                    </label>
                                    <div class="inline-flex rounded-lg bg-surface-container p-0.5 text-xs font-medium">
                                        <button
                                            type="button"
                                            class="rounded-md px-2 py-0.5 transition-colors"
                                            :class="form.rate_unit === 'monthly' ? 'bg-primary text-on-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface'"
                                            @click="setRateUnit('monthly')"
                                        >
                                            % / Bulan
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md px-2 py-0.5 transition-colors"
                                            :class="form.rate_unit === 'annual' ? 'bg-primary text-on-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface'"
                                            @click="setRateUnit('annual')"
                                        >
                                            % / Tahun
                                        </button>
                                    </div>
                                </div>

                                <AppInput
                                    v-model="form.interest_rate"
                                    :label="`Suku Bunga (${form.rate_unit === 'monthly' ? '% per Bulan' : '% per Tahun / p.a.'})`"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    icon="percent"
                                    required
                                />

                                <div class="mt-1.5 flex items-center justify-between text-xs text-on-surface-variant">
                                    <span v-if="form.rate_unit === 'monthly'">
                                        Setara dengan <strong>{{ (Number(form.interest_rate || 0) * 12).toFixed(2) }}%</strong> per tahun (p.a.)
                                    </span>
                                    <span v-else>
                                        Setara dengan <strong>{{ (Number(form.interest_rate || 0) / 12).toFixed(2) }}%</strong> per bulan
                                    </span>
                                    <span class="font-mono text-primary">
                                        Total Jasa: {{ (Number(form.interest_rate || 0) * (form.rate_unit === 'monthly' ? Number(form.term_months || 0) : (Number(form.term_months || 0) / 12))).toFixed(2) }}%
                                    </span>
                                </div>
                            </div>

                            <!-- Frekuensi Pokok & Jasa -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <SmartSelect
                                    v-model="form.principal_frequency"
                                    label="Frekuensi Pokok"
                                    :options="frequencyOptions"
                                />
                                <SmartSelect
                                    v-model="form.interest_frequency"
                                    label="Frekuensi Jasa"
                                    :options="frequencyOptions"
                                />
                            </div>

                            <!-- Pembulatan -->
                            <SmartSelect
                                v-model="form.rounding_step"
                                label="Metode Pembulatan Angsuran"
                                :options="roundingOptions"
                            />
                        </div>
                    </AppCard>
                </div>
                <!-- Right Column: KPIs & Schedule Table (7 cols on lg) -->
                <div class="lg:col-span-7 space-y-5">
                    <!-- 4 Summary KPI Cards -->
                    <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
                        <!-- Card 1: Plafon -->
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Plafon Pokok</span>
                                <span class="grid size-7 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <AppIcon name="account_balance_wallet" class="text-base" />
                                </span>
                            </div>
                            <div class="mt-3">
                                <p class="text-lg font-extrabold text-on-surface tabular-nums">
                                    {{ money(simulationResult.summary.principal_amount) }}
                                </p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">Pinjaman awal</p>
                            </div>
                        </div>

                        <!-- Card 2: Total Jasa -->
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Jasa</span>
                                <span class="grid size-7 place-items-center rounded-lg bg-secondary/15 text-secondary">
                                    <AppIcon name="trending_up" class="text-base" />
                                </span>
                            </div>
                            <div class="mt-3">
                                <p class="text-lg font-extrabold text-secondary tabular-nums">
                                    {{ money(simulationResult.summary.total_interest) }}
                                </p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ simulationResult.summary.interest_ratio.toFixed(1) }}% dari plafon
                                </p>
                            </div>
                        </div>

                        <!-- Card 3: Total Pengembalian -->
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Pengembalian</span>
                                <span class="grid size-7 place-items-center rounded-lg bg-tertiary/15 text-tertiary">
                                    <AppIcon name="receipt_long" class="text-base" />
                                </span>
                            </div>
                            <div class="mt-3">
                                <p class="text-lg font-extrabold text-tertiary tabular-nums">
                                    {{ money(simulationResult.summary.total_payment) }}
                                </p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">Pokok + Total Jasa</p>
                            </div>
                        </div>

                        <!-- Card 4: Est. Angsuran -->
                        <div class="rounded-2xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Est. Angsuran</span>
                                <span class="grid size-7 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <AppIcon name="event_repeat" class="text-base" />
                                </span>
                            </div>
                            <div class="mt-3">
                                <p class="text-lg font-extrabold text-primary tabular-nums">
                                    {{ money(simulationResult.summary.estimated_monthly) }}
                                </p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ form.installment_method === 'declining' ? 'Rata-rata per bulan' : `Selama ${form.term_months} bulan` }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Simulation Summary Pills Strip -->
                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-surface-container-low px-4 py-2.5 border border-outline-variant text-xs text-on-surface-variant">
                        <span class="font-semibold text-on-surface">Skema:</span>
                        <AppBadge variant="primary" size="sm">
                            {{ form.installment_method === 'flat' ? 'Flat / Tetap' : form.installment_method === 'declining' ? 'Efektif Menurun' : 'Anuitas' }}
                        </AppBadge>
                        <span class="text-outline">·</span>
                        <span>Suku Bunga: <strong class="text-on-surface">{{ simulationResult.summary.interest_rate_monthly.toFixed(2) }}% / bln</strong> ({{ simulationResult.summary.interest_rate_annual.toFixed(2) }}% p.a.)</span>
                        <span class="text-outline">·</span>
                        <span>Pembulatan: <strong class="text-on-surface">{{ form.rounding_step > 0 ? money(form.rounding_step) : 'Tanpa Pembulatan' }}</strong></span>
                        <span v-if="form.installment_method === 'declining'" class="text-outline">·</span>
                        <span v-if="form.installment_method === 'declining'">
                            Rentang: <strong class="text-primary">{{ money(simulationResult.summary.first_due) }}</strong> s/d <strong class="text-secondary">{{ money(simulationResult.summary.last_due) }}</strong>
                        </span>
                    </div>

                    <!-- Amortization Schedule Table Card -->
                    <AppCard title="Proyeksi Jadwal Angsuran" icon="table_chart" :padded="false" class="shadow-xs overflow-hidden">
                        <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                            <table class="w-full text-left text-xs sm:text-sm">
                                <thead class="sticky top-0 z-10 border-b border-outline-variant bg-surface-container text-xs font-semibold text-on-surface-variant shadow-xs">
                                    <tr>
                                        <th class="px-3.5 py-3 text-center w-12">Ke</th>
                                        <th class="px-3.5 py-3 min-w-[100px]">Jatuh Tempo</th>
                                        <th class="px-3.5 py-3 text-right">Pokok (Rp)</th>
                                        <th class="px-3.5 py-3 text-right">Bunga / Jasa (Rp)</th>
                                        <th class="px-3.5 py-3 text-right font-bold text-primary">Total Angsuran (Rp)</th>
                                        <th class="px-3.5 py-3 text-right">Sisa Pokok (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant bg-surface-container-lowest">
                                    <tr
                                        v-for="row in simulationResult.schedule"
                                        :key="row.number"
                                        class="hover:bg-surface-container-low/60 transition-colors"
                                    >
                                        <td class="px-3.5 py-2.5 text-center font-bold text-on-surface-variant">
                                            <span class="inline-flex size-6 items-center justify-center rounded-full bg-surface-container text-xs">
                                                {{ row.number }}
                                            </span>
                                        </td>
                                        <td class="px-3.5 py-2.5 font-medium text-on-surface whitespace-nowrap">
                                            {{ row.due_date }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right tabular-nums font-mono text-on-surface">
                                            {{ money(row.principal_due) }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right tabular-nums font-mono text-secondary">
                                            {{ money(row.interest_due) }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right tabular-nums font-mono font-bold text-primary bg-primary/5">
                                            {{ money(row.total_due) }}
                                        </td>
                                        <td class="px-3.5 py-2.5 text-right tabular-nums font-mono text-on-surface-variant">
                                            {{ money(row.remaining_principal) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="sticky bottom-0 z-10 border-t-2 border-outline-variant bg-surface-container text-xs font-bold text-on-surface shadow-xs">
                                    <tr>
                                        <td colspan="2" class="px-3.5 py-3 text-center uppercase tracking-wider text-on-surface">
                                            TOTAL
                                        </td>
                                        <td class="px-3.5 py-3 text-right tabular-nums font-mono text-primary">
                                            {{ money(simulationResult.summary.principal_amount) }}
                                        </td>
                                        <td class="px-3.5 py-3 text-right tabular-nums font-mono text-secondary">
                                            {{ money(simulationResult.summary.total_interest) }}
                                        </td>
                                        <td class="px-3.5 py-3 text-right tabular-nums font-mono text-tertiary bg-primary/10 text-sm">
                                            {{ money(simulationResult.summary.total_payment) }}
                                        </td>
                                        <td class="px-3.5 py-3 text-right tabular-nums font-mono text-on-surface-variant">
                                            Rp 0
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </AppCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
