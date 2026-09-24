<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppAccordion from '../../../Components/AppAccordion.vue';
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
    borrower_type: 'all',
    borrower_name: '',
    principal_amount: Number(props.defaultSimulation?.parameters?.principal_amount || 10000000),
    term_months: Number(props.defaultSimulation?.parameters?.term_months || 12),
    interest_rate: Number(props.defaultSimulation?.parameters?.interest_rate || 1.5),
    rate_unit: props.defaultSimulation?.parameters?.rate_unit || 'monthly',
    installment_method: props.defaultSimulation?.parameters?.installment_method || 'flat',
    principal_frequency: props.defaultSimulation?.parameters?.principal_frequency || 'monthly',
    interest_frequency: props.defaultSimulation?.parameters?.interest_frequency || 'monthly',
    principal_grace_months: Number(props.defaultSimulation?.parameters?.principal_grace_months || 0),
    interest_grace_months: Number(props.defaultSimulation?.parameters?.interest_grace_months || 0),
    rounding_step: props.defaultSimulation?.parameters?.rounding_step !== undefined && props.defaultSimulation?.parameters?.rounding_step !== null
        ? Number(props.defaultSimulation.parameters.rounding_step)
        : 500,
    start_date: props.defaultSimulation?.parameters?.start_date || new Date().toISOString().slice(0, 10),
});

const copied = ref(false);

// Tab state - mobile first: switches between input and result
const activeTab = ref('input'); // 'input' | 'result'

const productOptions = computed(() => {
    const filtered = form.borrower_type === 'all'
        ? props.products
        : props.products.filter((p) => {
            const scope = p.borrower_scope || 'both';
            if (form.borrower_type === 'kelompok') {
                return scope === 'group' || scope === 'both';
            }
            return scope === 'member' || scope === 'both';
        });
    return [
        { value: '', label: 'Kustom / Input Manual' },
        ...filtered.map((p) => ({
            value: p.code,
            label: `${p.name} (${p.code})`,
            data: p,
        })),
    ];
});

const borrowerTypeOptions = [
    { value: 'all', label: 'Semua', description: 'Kelompok & Individu' },
    { value: 'kelompok', label: 'Kelompok', description: 'Hanya kelompok' },
    { value: 'individu', label: 'Individu', description: 'Hanya individu' },
];

const quickBorrowerTypes = computed(() => borrowerTypeOptions);

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
    form.interest_rate = unit === 'annual'
        ? Number((currentRate * 12).toFixed(2))
        : Number((currentRate / 12).toFixed(2));
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
    { value: 1, label: 'M1 — Ditunda 1 bulan' },
    { value: 2, label: 'M2 — Pokok ditunda 2 bulan' },
    { value: 3, label: 'M3 — Pokok ditunda 3 bulan' },
    { value: 6, label: 'M6 — Pokok ditunda 6 bulan' },
    { value: 12, label: 'M12 — Pokok ditunda 12 bulan' },
    { value: 24, label: 'M24 — Pokok ditunda 24 bulan' },
];

const PRINCIPAL_PRESETS = [
    { value: 5000000, label: '5 Jt' },
    { value: 10000000, label: '10 Jt' },
    { value: 15000000, label: '15 Jt' },
    { value: 20000000, label: '20 Jt' },
    { value: 25000000, label: '25 Jt' },
    { value: 50000000, label: '50 Jt' },
];

const TENOR_PRESETS = [6, 10, 12, 18, 24, 36];

function roundVal(amount, step) {
    const s = parseInt(step, 10) || 0;
    if (s <= 1) return Math.round(amount * 100) / 100;
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

function formatDateId(dateStr) {
    if (!dateStr) return '';
    const parts = String(dateStr).split('-');
    if (parts.length !== 3) return dateStr;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return `${parseInt(parts[2], 10)} ${months[parseInt(parts[1], 10) - 1]} ${parts[0]}`;
}

// Client-side real-time calculation engine
const EMPTY_RESULT = {
    summary: {
        principal_amount: 0,
        total_interest: 0,
        total_payment: 0,
        estimated_monthly: 0,
        first_due: 0,
        last_due: 0,
        term_months: 12,
        interest_rate: 0,
        rate_unit: 'monthly',
        interest_rate_monthly: 0,
        interest_rate_annual: 0,
        method: 'flat',
        rounding_step: 500,
        interest_ratio: 0,
    },
    schedule: [],
};

// Safe accessor: returns summary or fallback (never undefined)
const safeSummary = computed(() => simulationResult.value?.summary ?? EMPTY_RESULT.summary);

// Safe accessor: returns schedule array or fallback (never undefined)
const safeSchedule = computed(() => simulationResult.value?.schedule ?? EMPTY_RESULT.schedule);

const simulationResult = computed(() => {
    try {
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
                if (pDue > remaining) pDue = remaining;
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
                const pDue = (i === pPeriods) ? Math.round((principal - accP) * 100) / 100 : roundedP;
                accP += pDue;

                const iDue = (i === pPeriods) ? Math.round((totalInterest - accI) * 100) / 100 : roundedI;
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
    } catch (err) {
        // Defensive fallback - never let a calc error break the page render
        // eslint-disable-next-line no-console
        console.warn('[simulation] calc error, returning empty result:', err);
        return EMPTY_RESULT;
    }
});

// ===== Derived labels =====
const methodLabel = computed(() => {
    if (form.installment_method === 'flat') return 'Flat / Tetap';
    if (form.installment_method === 'declining') return 'Efektif Menurun';
    return 'Anuitas';
});

const methodShort = computed(() => {
    if (form.installment_method === 'flat') return 'Flat';
    if (form.installment_method === 'declining') return 'Menurun';
    return 'Anuitas';
});

const methodDescription = computed(() => {
    const m = props.methodOptions.find((opt) => opt.value === form.installment_method);
    return m?.description ?? '';
});

const equivalentAnnualRate = computed(() => {
    const r = Number(form.interest_rate || 0);
    return form.rate_unit === 'monthly' ? r * 12 : r;
});

const equivalentMonthlyRate = computed(() => {
    const r = Number(form.interest_rate || 0);
    return form.rate_unit === 'monthly' ? r : r / 12;
});

// ===== Computed: Mini breakdown ring (percent of each component) =====
const principalPct = computed(() => {
    const s = safeSummary.value;
    const t = s.total_payment || 1;
    return Math.round(((s.principal_amount || 0) / t) * 100);
});
const interestPct = computed(() => Math.max(0, 100 - principalPct.value));

// ===== Stepper: progress of completion =====
const sectionCompletion = computed(() => ({
    identity: Boolean(form.borrower_name),
    scheme: form.installment_method && form.interest_rate > 0,
    schedule: form.start_date && form.principal_amount > 0 && form.term_months > 0,
}));

// ===== Accordion items: hanya Identitas defaultOpen, sisanya tertutup, mode multiple =====
const formAccordionItems = computed(() => [
    {
        key: 'identity',
        title: 'Identitas Peminjam',
        subtitle: 'Siapa yang akan meminjam',
        icon: 'person',
        badge: sectionCompletion.value.identity ? '✓' : null,
        defaultOpen: true,
        content: 'identity',
    },
    {
        key: 'plafon',
        title: 'Plafon & Tenor',
        subtitle: 'Besaran pinjaman dan jangka waktu',
        icon: 'payments',
        badge: sectionCompletion.value.schedule ? '✓' : null,
        defaultOpen: false,
        content: 'plafon',
    },
    {
        key: 'scheme',
        title: 'Skema Bunga',
        subtitle: 'Metode perhitungan & pembulatan',
        icon: 'percent',
        badge: sectionCompletion.value.scheme ? '✓' : null,
        defaultOpen: false,
        content: 'scheme',
    },
]);

// ===== Actions =====
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
    form.borrower_type = 'all';
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
    const s = safeSummary.value;
    const text = `*SIMULASI PINJAMAN*
Peminjam: ${form.borrower_name || 'Calon Peminjam'}
Plafon: ${money(s.principal_amount)}
Tenor: ${s.term_months} Bulan
Sistem Bunga: ${methodLabel.value}
Suku Bunga: ${s.interest_rate_monthly.toFixed(2)}% / bulan (${s.interest_rate_annual.toFixed(2)}% / tahun)
Total Jasa/Bunga: ${money(s.total_interest)}
Total Pengembalian: ${money(s.total_payment)}
Est. Angsuran/Bln: ${money(s.estimated_monthly)}`;

    navigator.clipboard.writeText(text).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}

// Number formatting helper
function formatNumber(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
}
</script>

<template>
    <Head title="Simulasi Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto w-full max-w-7xl space-y-3 sm:space-y-4">
            <!-- ============ PAGE HEADER (tanpa card wrapper) ============ -->
            <div class="flex flex-col gap-3 pb-1 sm:pb-2 sm:gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-lg font-extrabold tracking-tight text-on-surface sm:text-2xl lg:text-[26px]">
                            Simulasi Pinjaman
                        </h1>
                        <AppBadge tone="primary-soft">
                            <AppIcon name="bolt" class="text-xs" />
                            Real-time
                        </AppBadge>
                    </div>
                    <p class="mt-1 max-w-3xl text-xs text-on-surface-variant sm:text-sm">
                        Kalkulator simulasi perhitungan skema angsuran pokok dan jasa pinjaman secara instan dan presisi.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <AppButton
                        icon="restart_alt"
                        variant="secondary"
                        size="compact"
                        class="flex-1 justify-center sm:flex-none"
                        @click="resetForm"
                    >
                        <span class="hidden sm:inline">Reset</span>
                        <span class="sr-only sm:hidden">Reset</span>
                    </AppButton>
                    <AppButton
                        :icon="copied ? 'check_circle' : 'content_copy'"
                        variant="secondary"
                        size="compact"
                        class="flex-1 justify-center sm:flex-none"
                        @click="copySummary"
                    >
                        <span class="hidden sm:inline">{{ copied ? 'Tersalin!' : 'Salin' }}</span>
                        <span class="sr-only sm:hidden">Salin ringkasan</span>
                    </AppButton>
                    <AppButton
                        icon="picture_as_pdf"
                        variant="primary"
                        size="compact"
                        class="flex-1 justify-center sm:flex-none"
                        @click="openPdf"
                    >
                        <span>Unduh PDF</span>
                    </AppButton>
                </div>
            </div>

            <!-- ============ MOBILE TABS (only visible below md) ============ -->
            <div class="md:hidden">
                <div class="inline-flex w-full rounded-xl border border-outline-variant bg-surface-container-low p-1 shadow-xs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'input'"
                        @click="activeTab = 'input'"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition sm:gap-2 sm:px-3 sm:text-sm',
                            activeTab === 'input' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant active:bg-surface-container',
                        ]"
                    >
                        <AppIcon name="tune" class="text-base" />
                        Parameter
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'result'"
                        @click="activeTab = 'result'"
                        :class="[
                            'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-semibold transition sm:gap-2 sm:px-3 sm:text-sm',
                            activeTab === 'result' ? 'bg-primary text-on-primary shadow-sm' : 'text-on-surface-variant active:bg-surface-container',
                        ]"
                    >
                        <AppIcon name="analytics" class="text-base" />
                        Hasil
                    </button>
                </div>
            </div>

            <!-- ============ MAIN GRID ============ -->
            <div class="grid grid-cols-1 gap-3 md:gap-4 lg:grid-cols-12 lg:items-start">
                <!-- ============ LEFT: FORM (Accordion) ============ -->
                <section
                    :class="[
                        activeTab === 'input' ? 'block' : 'hidden md:block',
                        'lg:col-span-5',
                    ]"
                >
                    <AppAccordion :items="formAccordionItems" variant="surface" multiple>
                        <!-- ============== IDENTITAS PEMINJAM ============== -->
                        <template #content-identity>
                            <div class="space-y-3 text-on-surface sm:space-y-3.5">
                                <SmartSelect
                                    v-model="form.selectedProduct"
                                    label="Template Produk"
                                    :options="productOptions"
                                    size="default"
                                    @update:model-value="onProductChange"
                                />

                                <AppInput
                                    v-model="form.borrower_name"
                                    label="Nama Peminjam / Kelompok"
                                    placeholder="cth: Kelompok Mawar 01 / Ibu Siti"
                                    icon="person"
                                />

                                <div>
                                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant sm:text-[11px]">
                                        Tipe Peminjam
                                    </label>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <button
                                            v-for="opt in quickBorrowerTypes"
                                            :key="opt.value"
                                            type="button"
                                            @click="form.borrower_type = opt.value"
                                            :class="[
                                                'flex flex-col items-center gap-0.5 rounded-lg border px-1 py-1.5 text-center transition',
                                                form.borrower_type === opt.value
                                                    ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary'
                                                    : 'border-outline-variant/50 bg-surface text-on-surface-variant hover:bg-surface-container-low hover:text-on-surface',
                                            ]"
                                        >
                                            <span class="text-xs font-bold">{{ opt.label }}</span>
                                            <span class="hidden text-[9px] leading-tight sm:block">{{ opt.description }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ============== PLAFON & TENOR ============== -->
                        <template #content-plafon>
                            <div class="space-y-3 text-on-surface sm:space-y-3.5">
                                <!-- Plafon -->
                                <div>
                                    <AppCurrencyInput
                                        v-model="form.principal_amount"
                                        label="Plafon Pinjaman"
                                        icon="payments"
                                        :step="500000"
                                        required
                                    />
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <button
                                            v-for="amt in PRINCIPAL_PRESETS"
                                            :key="amt.value"
                                            type="button"
                                            @click="form.principal_amount = amt.value"
                                            :class="[
                                                'rounded-md px-2 py-0.5 text-[10px] font-medium transition sm:text-[11px]',
                                                form.principal_amount === amt.value
                                                    ? 'bg-primary text-on-primary font-semibold'
                                                    : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                            ]"
                                        >
                                            {{ amt.label }}
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
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

                                <!-- Tenor -->
                                <div>
                                    <AppInput
                                        v-model="form.term_months"
                                        label="Tenor (Bulan)"
                                        type="number"
                                        min="1"
                                        max="120"
                                        icon="calendar_month"
                                        required
                                    />
                                    <div class="mt-1.5 flex flex-wrap gap-1">
                                        <button
                                            v-for="t in TENOR_PRESETS"
                                            :key="t"
                                            type="button"
                                            @click="form.term_months = t"
                                            :class="[
                                                'rounded-md px-2 py-0.5 text-[10px] font-medium transition sm:text-[11px]',
                                                form.term_months === t
                                                    ? 'bg-primary text-on-primary font-semibold'
                                                    : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high',
                                            ]"
                                        >
                                            {{ t }} bln
                                        </button>
                                    </div>
                                </div>

                                <AppDatePicker
                                    v-model="form.start_date"
                                    label="Tanggal Mulai / Pencairan"
                                />
                            </div>
                        </template>

                        <!-- ============== SKEMA BUNGA ============== -->
                        <template #content-scheme>
                            <div class="space-y-3 text-on-surface sm:space-y-3.5">
                                <!-- Metode -->
                                <div>
                                    <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant sm:text-[11px]">
                                        Sistem Perhitungan
                                    </label>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <button
                                            v-for="m in methodOptions"
                                            :key="m.value"
                                            type="button"
                                            @click="form.installment_method = m.value"
                                            :class="[
                                                'flex flex-col items-center gap-0.5 rounded-lg border px-1 py-1.5 text-center transition',
                                                form.installment_method === m.value
                                                    ? 'border-primary bg-primary/10 text-primary'
                                                    : 'border-outline-variant bg-surface text-on-surface-variant hover:bg-surface-container-low',
                                            ]"
                                        >
                                            <AppIcon
                                                :name="m.value === 'flat' ? 'horizontal_rule' : m.value === 'declining' ? 'trending_down' : 'balance'"
                                                class="text-base"
                                            />
                                            <span class="text-[10px] font-bold leading-tight sm:text-[11px]">
                                                {{ m.value === 'flat' ? 'Flat' : m.value === 'declining' ? 'Menurun' : 'Anuitas' }}
                                            </span>
                                        </button>
                                    </div>
                                    <p class="mt-1.5 text-[10px] text-on-surface-variant sm:text-[11px]">
                                        {{ methodDescription }}
                                    </p>
                                </div>

                                <!-- Suku Bunga -->
                                <div>
                                    <div class="mb-1.5 flex items-center justify-between">
                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant sm:text-[11px]">
                                            Suku Bunga
                                        </label>
                                        <div class="inline-flex rounded-lg bg-surface-container p-0.5 text-[10px] font-medium sm:text-[11px]">
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded-md px-2 py-0.5 transition',
                                                    form.rate_unit === 'monthly' ? 'bg-primary text-on-primary font-bold' : 'text-on-surface-variant hover:text-on-surface',
                                                ]"
                                                @click="setRateUnit('monthly')"
                                            >
                                                / Bulan
                                            </button>
                                            <button
                                                type="button"
                                                :class="[
                                                    'rounded-md px-2 py-0.5 transition',
                                                    form.rate_unit === 'annual' ? 'bg-primary text-on-primary font-bold' : 'text-on-surface-variant hover:text-on-surface',
                                                ]"
                                                @click="setRateUnit('annual')"
                                            >
                                                / Tahun
                                            </button>
                                        </div>
                                    </div>

                                    <AppInput
                                        v-model="form.interest_rate"
                                        :label="`Bunga (${form.rate_unit === 'monthly' ? '% per Bulan' : '% per Tahun / p.a.'})`"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        icon="percent"
                                        required
                                    />

                                    <div class="mt-1.5 flex items-center justify-between rounded-lg bg-surface-container-low px-2.5 py-1.5 text-[10px] text-on-surface-variant sm:text-[11px]">
                                        <span>
                                            Setara
                                            <strong class="text-on-surface">
                                                {{ form.rate_unit === 'monthly'
                                                    ? `${equivalentAnnualRate.toFixed(2)}% p.a.`
                                                    : `${equivalentMonthlyRate.toFixed(2)}% / bln` }}
                                            </strong>
                                        </span>
                                        <span class="font-mono text-primary">
                                            Ratio: {{ safeSummary.interest_ratio.toFixed(1) }}%
                                        </span>
                                    </div>
                                </div>

                                <!-- Frekuensi -->
                                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
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

                                <SmartSelect
                                    v-model="form.rounding_step"
                                    label="Pembulatan Angsuran"
                                    :options="roundingOptions"
                                />
                            </div>
                        </template>
                    </AppAccordion>
                </section>

                <!-- ============ RIGHT: RESULT ============ -->
                <section
                    :class="[
                        'space-y-3 sm:space-y-4',
                        activeTab === 'result' ? 'block' : 'hidden md:block',
                        'lg:col-span-7',
                    ]"
                >
                    <!-- Skema Aktif: gabungan ringkasan finansial + parameter + donut, 1 card -->
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-xs">
                        <header class="flex items-center gap-2.5 border-b border-outline-variant/60 bg-surface-container-low/60 px-3 py-2 sm:px-4 sm:py-2.5">
                            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-primary text-on-primary sm:size-8 sm:rounded-lg">
                                <AppIcon name="schema" class="text-sm sm:text-base" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xs font-bold text-on-surface sm:text-sm">Skema Aktif</h2>
                                <p class="text-[10px] text-on-surface-variant sm:text-[11px]">Ringkasan finansial, parameter &amp; proporsi</p>
                            </div>
                            <AppBadge tone="primary-soft" class="shrink-0 whitespace-nowrap">
                                {{ methodShort }} • {{ safeSchedule.length }}x
                            </AppBadge>
                        </header>

                        <div class="space-y-3 p-3 sm:space-y-4 sm:p-4">
                            <!-- Baris 1: Ringkasan finansial (4 kolom ringkas) -->
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg bg-surface-container-lowest/50 px-3 py-2.5 sm:grid-cols-4 sm:gap-x-4">
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Plafon Pokok
                                    </dt>
                                    <dd class="truncate text-xs font-extrabold tabular-nums text-primary sm:text-sm">
                                        {{ money(safeSummary.principal_amount) }}
                                    </dd>
                                    <dd class="text-[9px] text-on-surface-variant sm:text-[10px]">
                                        Pinjaman awal
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Total Jasa
                                    </dt>
                                    <dd class="truncate text-xs font-extrabold tabular-nums text-secondary sm:text-sm">
                                        {{ money(safeSummary.total_interest) }}
                                    </dd>
                                    <dd class="text-[9px] text-on-surface-variant sm:text-[10px]">
                                        {{ safeSummary.interest_ratio.toFixed(1) }}% dari plafon
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Total Pengembalian
                                    </dt>
                                    <dd class="truncate text-xs font-extrabold tabular-nums text-tertiary sm:text-sm">
                                        {{ money(safeSummary.total_payment) }}
                                    </dd>
                                    <dd class="text-[9px] text-on-surface-variant sm:text-[10px]">
                                        Pokok + Jasa
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                        Est. Angsuran
                                    </dt>
                                    <dd class="truncate text-xs font-extrabold tabular-nums text-on-surface sm:text-sm">
                                        {{ money(safeSummary.estimated_monthly) }}
                                    </dd>
                                    <dd class="text-[9px] text-on-surface-variant sm:text-[10px]">
                                        / {{ form.term_months }} bulan
                                    </dd>
                                </div>
                            </dl>

                            <!-- Baris 2: Parameter + Donut -->
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                                <!-- Parameters -->
                                <dl class="grid grid-cols-2 gap-x-3 gap-y-2.5 text-sm md:col-span-3">
                                    <div class="min-w-0">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Sistem
                                        </dt>
                                        <dd class="truncate text-xs font-bold text-on-surface sm:text-sm">
                                            {{ methodLabel }}
                                        </dd>
                                        <dd class="truncate text-[10px] text-on-surface-variant">
                                            {{ methodDescription }}
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Bunga / Bulan
                                        </dt>
                                        <dd class="truncate text-xs font-bold text-on-surface sm:text-sm">
                                            {{ safeSummary.interest_rate_monthly.toFixed(2) }}%
                                        </dd>
                                        <dd class="truncate text-[10px] text-on-surface-variant">
                                            p.a. {{ safeSummary.interest_rate_annual.toFixed(2) }}%
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Tenor
                                        </dt>
                                        <dd class="truncate text-xs font-bold text-on-surface sm:text-sm">
                                            {{ form.term_months }} bulan
                                        </dd>
                                        <dd class="truncate text-[10px] text-on-surface-variant">
                                            Mulai {{ formatDateId(form.start_date) }}
                                        </dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant">
                                            Pembulatan
                                        </dt>
                                        <dd class="truncate text-xs font-bold text-on-surface sm:text-sm">
                                            {{ form.rounding_step > 0 ? `Rp ${formatNumber(form.rounding_step)}` : 'Tanpa' }}
                                        </dd>
                                        <dd class="truncate text-[10px] text-on-surface-variant">
                                            Pokok: {{ props.frequencyOptions.find(o => o.value === form.principal_frequency)?.label || 'Bulanan' }}
                                        </dd>
                                    </div>
                                </dl>

                                <!-- Donut breakdown: stack vertikal di mobile, side-by-side di md+ -->
                                <div class="flex flex-col items-center justify-center gap-2 md:col-span-2 md:flex-row md:gap-3">
                                    <div class="relative size-20 shrink-0 sm:size-24 md:size-28">
                                        <svg viewBox="0 0 36 36" class="size-full -rotate-90">
                                            <circle
                                                cx="18" cy="18" r="15.9155"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                class="text-outline-variant/40"
                                            />
                                            <circle
                                                cx="18" cy="18" r="15.9155"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                stroke-dasharray="100 100"
                                                :stroke-dashoffset="100 - principalPct"
                                                stroke-linecap="round"
                                                class="text-primary transition-all duration-500"
                                            />
                                            <circle
                                                cx="18" cy="18" r="15.9155"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                :stroke-dasharray="`${interestPct} ${100 - interestPct}`"
                                                :stroke-dashoffset="-principalPct"
                                                stroke-linecap="round"
                                                class="text-secondary transition-all duration-500"
                                            />
                                        </svg>
                                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                                            <span class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant">Total</span>
                                            <span class="text-[10px] font-extrabold text-on-surface tabular-nums sm:text-[11px]">
                                                {{ money(safeSummary.total_payment) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 text-[10px] sm:gap-4 sm:text-[11px] md:flex-col md:items-start md:gap-1 md:ml-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="size-2 shrink-0 rounded-full bg-primary"></span>
                                            <span class="text-on-surface-variant">Pokok</span>
                                            <span class="font-bold tabular-nums text-on-surface">{{ principalPct }}%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="size-2 shrink-0 rounded-full bg-secondary"></span>
                                            <span class="text-on-surface-variant">Jasa</span>
                                            <span class="font-bold tabular-nums text-on-surface">{{ interestPct }}%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Schedule Table -->
                    <section class="overflow-hidden rounded-xl border border-outline-variant bg-surface shadow-xs">
                        <header class="flex items-center gap-2.5 border-b border-outline-variant/60 bg-surface-container-low/60 px-3 py-2 sm:px-4 sm:py-2.5">
                            <span class="grid size-7 shrink-0 place-items-center rounded-md bg-primary text-on-primary sm:size-8 sm:rounded-lg">
                                <AppIcon name="table_chart" class="text-sm sm:text-base" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xs font-bold text-on-surface sm:text-sm">Proyeksi Jadwal Angsuran</h2>
                                <p class="text-[10px] text-on-surface-variant sm:text-[11px]">{{ safeSchedule.length }} periode pembayaran</p>
                            </div>
                            <div class="hidden gap-1.5 sm:flex sm:items-center">
                                <span class="rounded bg-surface-container px-1.5 py-0.5 text-[9px] font-bold uppercase text-on-surface-variant">
                                    Pokok
                                </span>
                                <span class="rounded bg-secondary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-secondary">
                                    Jasa
                                </span>
                                <span class="rounded bg-primary/10 px-1.5 py-0.5 text-[9px] font-bold uppercase text-primary">
                                    Total
                                </span>
                            </div>
                        </header>

                        <div class="max-h-[60vh] overflow-x-auto overflow-y-auto sm:max-h-[400px] md:max-h-[480px]">
                            <table class="w-full min-w-[640px] text-left text-[11px] sm:text-xs">
                                <thead class="sticky top-0 z-10 border-b border-outline-variant bg-surface-container text-[9px] font-bold uppercase tracking-wider text-on-surface-variant sm:text-[10px]">
                                    <tr>
                                        <th class="px-2 py-1.5 text-center w-10 sm:px-3 sm:py-2">Ke</th>
                                        <th class="px-2 py-1.5 sm:px-3 sm:py-2">Jatuh Tempo</th>
                                        <th class="px-2 py-1.5 text-right sm:px-3 sm:py-2">Pokok</th>
                                        <th class="px-2 py-1.5 text-right sm:px-3 sm:py-2">Jasa</th>
                                        <th class="px-2 py-1.5 text-right text-primary sm:px-3 sm:py-2">Total</th>
                                        <th class="px-2 py-1.5 text-right sm:px-3 sm:py-2">Sisa Pokok</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60 bg-surface">
                                    <tr
                                        v-for="row in safeSchedule"
                                        :key="row.number"
                                        class="transition-colors hover:bg-surface-container-low/50"
                                    >
                                        <td class="px-2 py-1.5 text-center sm:px-3 sm:py-2">
                                            <span class="inline-flex size-5 items-center justify-center rounded-full bg-surface-container text-[10px] font-bold text-on-surface-variant sm:size-6 sm:text-[11px]">
                                                {{ row.number }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-1.5 font-medium whitespace-nowrap text-on-surface sm:px-3 sm:py-2">
                                            <span class="block text-[11px] sm:text-xs">{{ formatDateId(row.due_date) }}</span>
                                            <span class="block text-[9px] text-on-surface-variant">{{ row.due_date }}</span>
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-on-surface sm:px-3 sm:py-2">
                                            {{ money(row.principal_due) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-secondary sm:px-3 sm:py-2">
                                            {{ money(row.interest_due) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums font-bold text-primary sm:px-3 sm:py-2">
                                            {{ money(row.total_due) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-on-surface-variant sm:px-3 sm:py-2">
                                            {{ money(row.remaining_principal) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="sticky bottom-0 z-10 border-t-2 border-outline-variant bg-surface-container">
                                    <tr class="text-[10px] font-bold text-on-surface sm:text-[11px]">
                                        <td colspan="2" class="px-2 py-1.5 text-center uppercase tracking-wider sm:px-3 sm:py-2">
                                            TOTAL
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-primary sm:px-3 sm:py-2">
                                            {{ money(safeSummary.principal_amount) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-secondary sm:px-3 sm:py-2">
                                            {{ money(safeSummary.total_interest) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-primary sm:px-3 sm:py-2">
                                            {{ money(safeSummary.total_payment) }}
                                        </td>
                                        <td class="px-2 py-1.5 text-right tabular-nums text-on-surface-variant sm:px-3 sm:py-2">
                                            Rp 0
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
