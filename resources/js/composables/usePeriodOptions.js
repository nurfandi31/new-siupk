import { computed } from 'vue';

/**
 * Shared Indonesian month-name list and year-option generator.
 *
 * Usage:
 *   const { monthOptions, yearOptions } = usePeriodOptions();
 */
export function usePeriodOptions() {
    const monthOptions = [
        { value: '', label: 'Semua Bulan (Tahunan)' },
        { value: 1, label: 'Januari' },
        { value: 2, label: 'Februari' },
        { value: 3, label: 'Maret' },
        { value: 4, label: 'April' },
        { value: 5, label: 'Mei' },
        { value: 6, label: 'Juni' },
        { value: 7, label: 'Juli' },
        { value: 8, label: 'Agustus' },
        { value: 9, label: 'September' },
        { value: 10, label: 'Oktober' },
        { value: 11, label: 'November' },
        { value: 12, label: 'Desember' },
    ];

    const currentYear = new Date().getFullYear();

    const yearOptions = computed(() => {
        const start = currentYear - 2;
        const end = currentYear + 1;
        const result = [];
        for (let y = start; y <= end; y++) {
            result.push({ value: y, label: String(y) });
        }
        return result;
    });

    return { monthOptions, yearOptions };
}
