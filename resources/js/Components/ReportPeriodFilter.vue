<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppDatePicker from './AppDatePicker.vue';

const props = defineProps({
    year: { type: [Number, String], required: true },
    /** null | 'all' | 1–12 */
    month: { type: [Number, String], default: null },
    /** YYYY-MM-DD or null — only for journals / general ledger */
    day: { type: String, default: null },
    showDay: { type: Boolean, default: false },
    baseUrl: { type: String, required: true },
    extra: { type: Object, default: () => ({}) },
    pdfUrl: { type: String, default: null },
    excelUrl: { type: String, default: null },
});

const selectedYear = ref(toYearValue(props.year));
const selectedMonth = ref(toMonthValue(props.year, props.month));
const selectedDay = ref(props.day && /^\d{4}-\d{2}-\d{2}$/.test(props.day) ? props.day : '');

watch(
    () => [props.year, props.month, props.day],
    () => {
        selectedYear.value = toYearValue(props.year);
        selectedMonth.value = toMonthValue(props.year, props.month);
        selectedDay.value = props.day && /^\d{4}-\d{2}-\d{2}$/.test(props.day) ? props.day : '';
    },
);

watch(selectedYear, (y) => {
    if (!y) return;
    // Keep month empty if cleared; otherwise retarget year part.
    if (selectedMonth.value) {
        const m = selectedMonth.value.slice(5, 7);
        selectedMonth.value = `${y}-${m}`;
    }
    if (selectedDay.value) {
        const rest = selectedDay.value.slice(4); // -MM-DD
        selectedDay.value = `${y}${rest}`;
    }
});

watch(selectedMonth, (m) => {
    if (!m) {
        // Empty month ⇒ clear day (day needs a month context).
        selectedDay.value = '';
        return;
    }
    if (selectedDay.value) {
        const d = selectedDay.value.slice(8, 10);
        selectedDay.value = `${m}-${d}`;
    }
});

const dayMax = computed(() => {
    if (selectedMonth.value) {
        const [y, m] = selectedMonth.value.split('-').map(Number);
        const last = new Date(y, m, 0).getDate();
        return `${selectedMonth.value}-${String(last).padStart(2, '0')}`;
    }
    if (selectedYear.value) {
        return `${selectedYear.value}-12-31`;
    }
    return null;
});

const queryBase = computed(() => {
    const year = selectedYear.value || String(props.year);
    const month = selectedMonth.value ? String(Number(selectedMonth.value.slice(5, 7))) : 'all';
    const q = {
        year,
        month,
        ...props.extra,
    };
    if (props.showDay && selectedDay.value) {
        q.day = selectedDay.value;
    }
    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === '') delete q[k];
    });
    return q;
});

function apply() {
    router.get(props.baseUrl, queryBase.value, { preserveScroll: true, replace: true });
}

function excelHref() {
    if (!props.excelUrl) return '#';
    const params = new URLSearchParams(
        Object.fromEntries(Object.entries(queryBase.value).map(([k, v]) => [k, String(v)])),
    );
    return `${props.excelUrl}?${params.toString()}`;
}

function pdfHref() {
    if (!props.pdfUrl) return '#';
    const params = new URLSearchParams(
        Object.fromEntries(Object.entries(queryBase.value).map(([k, v]) => [k, String(v)])),
    );
    return `${props.pdfUrl}?${params.toString()}`;
}

function toYearValue(year) {
    const y = Number(year);
    return y >= 2000 && y <= 2100 ? String(y) : String(new Date().getFullYear());
}

function toMonthValue(year, month) {
    if (month === null || month === undefined || month === '' || month === 'all') return '';
    const m = Number(month);
    if (m < 1 || m > 12) return '';
    const y = toYearValue(year);
    return `${y}-${String(m).padStart(2, '0')}`;
}
</script>

<template>
    <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-end lg:gap-3">
        <div class="grid w-full flex-1 grid-cols-1 gap-3 sm:grid-cols-2" :class="showDay ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">
            <AppDatePicker v-model="selectedYear" label="Tahun" mode="year" required />
            <AppDatePicker
                v-model="selectedMonth"
                label="Bulan"
                mode="month"
                clearable
                placeholder="Semua bulan"
            />
            <AppDatePicker
                v-if="showDay"
                v-model="selectedDay"
                label="Tanggal"
                mode="date"
                clearable
                placeholder="Semua tanggal"
                :min="selectedMonth ? `${selectedMonth}-01` : selectedYear ? `${selectedYear}-01-01` : null"
                :max="dayMax"
            />
        </div>
        <slot name="extra" />
        <div class="flex shrink-0 items-end gap-2">
            <AppButton type="button" class="!min-h-14 h-14 px-5" @click="apply">Tampilkan</AppButton>
            <a
                v-if="pdfUrl"
                :href="pdfHref()"
                target="_blank"
                rel="noopener"
                class="inline-flex h-14 min-h-14 items-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
            >
                PDF
            </a>
            <a
                v-if="excelUrl"
                :href="excelHref()"
                class="inline-flex h-14 min-h-14 items-center gap-1.5 rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
            >
                <AppIcon name="table_view" class="text-base" />
                Excel
            </a>
        </div>
    </div>
</template>
